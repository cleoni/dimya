<?php
/**
 * RoboPlayer - Autonomous Exploration Bot for DimensioneY (UIbot)
 * Configured with polite request pacing (5s delay) and standard Chrome headers.
 */

declare(strict_types=1);

class RoboPlayer
{
    private string $endpoint;
    private string $username;
    private string $password;
    private string $cookieFile;
    private int $maxSteps;
    private int $stepCount = 0;
    private int $requestDelaySeconds = 5;

    // Standard modern Chrome User-Agent on Windows 10
    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    // World state memory
    private array $rooms = [];
    private array $items = [];
    private array $characters = [];
    private array $visitedRooms = [];
    private array $searchedRooms = [];
    private array $inspectedItems = [];
    private array $usedItems = [];
    private array $openedItems = [];
    private array $spokenChars = [];

    // Blocked/locked exits: [roomId => [actionKey => true]]
    private array $blockedExits = [];

    // Graph transitions: [roomId => [targetRoomId => actionKey]]
    private array $roomAdjacency = [];
    private ?string $currentRoomId = null;

    // Challenge / bot-mitigation cookies
    private array $extraCookies = [
        'humans_21909' => '1'
    ];

    public function __construct(
        string $endpoint = 'https://test.dimensionex.net/dimya/php/multiplayer.php',
        string $username = 'RoboPlayer',
        string $password = 'RoboPass_2026',
        int $maxSteps = 100,
        int $requestDelaySeconds = 5
    ) {
        $this->endpoint            = $endpoint;
        $this->username            = $username;
        $this->password            = $password;
        $this->maxSteps            = $maxSteps;
        $this->requestDelaySeconds = $requestDelaySeconds;
        $this->cookieFile          = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'roboplayer_cookie_' . uniqid() . '.txt';
    }

    public function __destruct()
    {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function run(): void
    {
        $this->log("=== RoboPlayer Initializing (Pacing: {$this->requestDelaySeconds}s delay) ===");
        $loginData = $this->login();

        if (empty($loginData['success'])) {
            $this->log("CRITICAL: Login failed. Aborting.");
            return;
        }

        $this->processRoomState($loginData['room'] ?? []);

        while ($this->stepCount < $this->maxSteps) {
            $acted = false;

            // 1. Mandatory: SEARCH room if newly entered
            if ($this->currentRoomId && empty($this->searchedRooms[$this->currentRoomId])) {
                $this->searchedRooms[$this->currentRoomId] = true;
                $this->log("Searching room: {$this->currentRoomId}");
                $resp = $this->sendCmd('search');
                $this->processRoomState($resp['room'] ?? []);
                $acted = true;
                continue;
            }

            // 2. Mandatory: Greet characters & listen for reply
            $occupants = $this->rooms[$this->currentRoomId]['occupants'] ?? [];
            foreach ($occupants as $actor) {
                $actorId = $actor['id'] ?? '';
                if ($actorId !== '' && empty($this->spokenChars[$actorId])) {
                    $this->spokenChars[$actorId] = true;
                    $actorName = $actor['name'] ?? $actorId;
                    $this->log("Greeting character: {$actorName} ({$actorId})");
                    $resp = $this->sendCmd('say', "Hello, {$actorName}!");
                    $this->processRoomState($resp['room'] ?? []);

                    $this->log("Listening for reply from {$actorName}...");
                    $replyResp = $this->sendCmd('look');
                    $this->processRoomState($replyResp['room'] ?? []);

                    $acted = true;
                    break;
                }
            }
            if ($acted) continue;

            // 3. Mandatory: Look at newly encountered items
            $items = $this->rooms[$this->currentRoomId]['items'] ?? [];
            foreach ($items as $item) {
                $itemId = $item['id'] ?? '';
                if ($itemId === '') continue;

                if (empty($this->inspectedItems[$itemId])) {
                    $this->inspectedItems[$itemId] = true;
                    $this->log("Looking at item: {$itemId}");
                    $resp = $this->sendCmd('look', $itemId);
                    $this->processRoomState($resp['room'] ?? []);
                    $acted = true;
                    break;
                }

                // 4. Mandatory: Try open if contains "bag" or "box"
                $nameLower = strtolower(($item['name'] ?? '') . ' ' . $itemId);
                if ((strpos($nameLower, 'bag') !== false || strpos($nameLower, 'box') !== false) && empty($this->openedItems[$itemId])) {
                    $this->openedItems[$itemId] = true;
                    $this->log("Attempting to open container: {$itemId}");
                    $resp = $this->sendCmd('open', $itemId);
                    $this->processRoomState($resp['room'] ?? []);
                    $acted = true;
                    break;
                }

                // 5. Mandatory: Try use on each item
                if (empty($this->usedItems[$itemId])) {
                    $this->usedItems[$itemId] = true;
                    $this->log("Testing USE on item: {$itemId}");
                    $resp = $this->sendCmd('use', $itemId);
                    $this->processRoomState($resp['room'] ?? []);
                    $acted = true;
                    break;
                }
            }
            if ($acted) continue;

            // 6. Navigation: Explore unvisited/unblocked exits
            $unexploredExit = $this->findUnexploredExitFrom($this->currentRoomId);
            if ($unexploredExit !== null) {
                $actionParam = !empty($unexploredExit['direction']) ? $unexploredExit['direction'] : $unexploredExit['link_id'];
                $fromRoom = $this->currentRoomId;

                $this->log("Taking exit '{$actionParam}' from '{$fromRoom}'");
                $resp = $this->sendCmd('go', $actionParam);

                $newRoom = $resp['room']['id'] ?? null;
                $this->processRoomState($resp['room'] ?? []);

                if ($newRoom && $newRoom !== $fromRoom) {
                    $this->recordConnection($fromRoom, $newRoom, $actionParam);
                } else {
                    $this->log("WARNING: Exit '{$actionParam}' from '{$fromRoom}' appears closed or blocked. Marking as blocked.");
                    $this->blockedExits[$fromRoom][$actionParam] = true;
                }
                continue;
            }

            // 7. Navigation: Backtrack to nearest room with unexplored exits
            $path = $this->findPathToNearestUnexplored();
            if (!empty($path)) {
                $nextStep = array_shift($path);
                $fromRoom = $this->currentRoomId;
                $this->log("Backtracking to '{$nextStep['to']}' via '{$nextStep['action']}'");
                $resp = $this->sendCmd('go', $nextStep['action']);

                $newRoom = $resp['room']['id'] ?? null;
                $this->processRoomState($resp['room'] ?? []);

                if ($newRoom && $newRoom !== $fromRoom) {
                    $this->recordConnection($fromRoom, $newRoom, $nextStep['action']);
                } else {
                    $this->log("WARNING: Path transition '{$nextStep['action']}' from '{$fromRoom}' failed unexpectedly.");
                    $this->blockedExits[$fromRoom][$nextStep['action']] = true;
                }
                continue;
            }

            $this->log("Full exploration finished: No reachable unvisited rooms or unexplored exits remaining.");
            break;
        }

        if ($this->stepCount >= $this->maxSteps) {
            $this->log("Reached maximum configured step limit ({$this->maxSteps}). Terminating exploration.");
        }

        $this->logout();
        $this->printReport();
    }

    private function processRoomState(array $room): void
    {
        if (empty($room) || empty($room['id'])) {
            return;
        }

        $roomId = $room['id'];
        $this->currentRoomId = $roomId;
        $this->visitedRooms[$roomId] = true;

        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = [
                'id'          => $roomId,
                'name'        => $room['name'] ?? 'Unknown Room',
                'description' => $room['description'] ?? '',
                'exits'       => [],
                'items'       => [],
                'occupants'   => []
            ];
            $this->log("Discovered Room: [{$roomId}] \"{$this->rooms[$roomId]['name']}\"");
        }

        if (!empty($room['exits'])) {
            $this->rooms[$roomId]['exits'] = $room['exits'];
            foreach ($room['exits'] as $ex) {
                if (isset($ex['open']) && $ex['open'] === false) {
                    $act = !empty($ex['direction']) ? $ex['direction'] : ($ex['link_id'] ?? '');
                    if ($act !== '') {
                        $this->blockedExits[$roomId][$act] = true;
                    }
                }
            }
        }

        if (!empty($room['items'])) {
            $this->rooms[$roomId]['items'] = $room['items'];
            foreach ($room['items'] as $it) {
                $itemId = $it['id'] ?? '';
                if ($itemId !== '' && !isset($this->items[$itemId])) {
                    $this->items[$itemId] = [
                        'id'            => $itemId,
                        'name'          => $it['name'] ?? $itemId,
                        'description'   => $it['description'] ?? '',
                        'firstSeenRoom' => $roomId
                    ];
                    $this->log("Discovered Item: [{$itemId}] \"{$this->items[$itemId]['name']}\"");
                }
            }
        }

        if (!empty($room['occupants'])) {
            $this->rooms[$roomId]['occupants'] = $room['occupants'];
            foreach ($room['occupants'] as $occ) {
                $charId = $occ['id'] ?? '';
                if ($charId !== '' && !isset($this->characters[$charId])) {
                    $this->characters[$charId] = [
                        'id'            => $charId,
                        'name'          => $occ['name'] ?? $charId,
                        'firstSeenRoom' => $roomId
                    ];
                    $this->log("Discovered Character: [{$charId}] \"{$this->characters[$charId]['name']}\"");
                }
            }
        }
    }

    private function recordConnection(string $from, string $to, string $action): void
    {
        $this->roomAdjacency[$from][$to] = $action;
    }

    private function findUnexploredExitFrom(string $roomId): ?array
    {
        $exits = $this->rooms[$roomId]['exits'] ?? [];
        foreach ($exits as $exit) {
            $dir = $exit['direction'] ?? '';
            $linkId = $exit['link_id'] ?? '';
            $action = !empty($dir) ? $dir : $linkId;

            if (!empty($this->blockedExits[$roomId][$action])) {
                continue;
            }

            $alreadyMapped = false;
            if (isset($this->roomAdjacency[$roomId])) {
                foreach ($this->roomAdjacency[$roomId] as $targetRoom => $act) {
                    if ($act === $action) {
                        $alreadyMapped = true;
                        break;
                    }
                }
            }

            if (!$alreadyMapped) {
                return $exit;
            }
        }

        return null;
    }

    private function findPathToNearestUnexplored(): array
    {
        $start = $this->currentRoomId;
        if (!$start) return [];

        $queue = [[$start, []]];
        $visited = [$start => true];

        while (!empty($queue)) {
            [$curr, $path] = array_shift($queue);

            if ($this->findUnexploredExitFrom($curr) !== null && $curr !== $start) {
                return $path;
            }

            if (!empty($this->roomAdjacency[$curr])) {
                foreach ($this->roomAdjacency[$curr] as $nextRoom => $action) {
                    if (empty($visited[$nextRoom]) && empty($this->blockedExits[$curr][$action])) {
                        $visited[$nextRoom] = true;
                        $newPath = $path;
                        $newPath[] = ['from' => $curr, 'to' => $nextRoom, 'action' => $action];
                        $queue[] = [$nextRoom, $newPath];
                    }
                }
            }
        }

        return [];
    }

    private function sendCmd(string $cmd, string $param = '', string $target = ''): array
    {
        $this->stepCount++;
        $payload = [
            'cmd'    => $cmd,
            'param'  => $param,
            'target' => $target
        ];

        $resp = $this->httpPostJson($payload);

        if (($resp['http_code'] ?? 200) === 503 || ($resp['data']['status'] ?? '') === 'lock_busy') {
            usleep(250000);
            $resp = $this->httpPostJson($payload);
        }

        $data = $resp['data'] ?? [];

        if (!empty($data['messages'])) {
            foreach ($data['messages'] as $msg) {
                $this->log("  [Server Msg] {$msg}");
            }
        }

        if (!empty($data['chat'])) {
            foreach ($data['chat'] as $chatMsg) {
                $sender = $chatMsg['sender'] ?? 'Chat';
                $text   = $chatMsg['text'] ?? ($chatMsg['message'] ?? json_encode($chatMsg));
                $this->log("  [Chat/Dialogue] <{$sender}> {$text}");
            }
        }

        // Pacing delay after executing an operation
        if ($this->requestDelaySeconds > 0) {
            sleep($this->requestDelaySeconds);
        }

        return $data;
    }

    private function login(): array
    {
        $this->stepCount++;

        $payload = [
            'cmd'      => 'login',
            'username' => $this->username,
            'password' => $this->password
        ];

        $resp = $this->httpPostJson($payload);
        $data = $resp['data'] ?? [];

        if (!empty($data['success'])) {
            $this->log("Logged in as '{$this->username}'. Start room: " . ($data['room']['name'] ?? 'Unknown'));
        } else {
            $errMsg = $data['error'] ?? ($data['status'] ?? 'Unknown reason');
            $this->log("Login error: {$errMsg}");
            $this->log("--- SERVER RESPONSE DUMP ---");
            $this->log("HTTP Status Code: " . $resp['http_code']);
            $this->log("cURL Error: " . ($resp['curl_error'] ?: 'None'));
            $this->log("Raw Body:\n" . ($resp['raw_body'] !== '' ? $resp['raw_body'] : '(empty body)'));
            $this->log("----------------------------");
        }

        if ($this->requestDelaySeconds > 0) {
            sleep($this->requestDelaySeconds);
        }

        return $data;
    }

    private function logout(): void
    {
        $this->log("Logging out...");
        $this->sendCmd('logout');
    }

    private function httpPostJson(array $data): array
    {
        $ch = curl_init($this->endpoint);
        $json = json_encode($data);

        $cookieHeader = '';
        foreach ($this->extraCookies as $name => $val) {
            $cookieHeader .= "{$name}={$val}; ";
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR      => $this->cookieFile,
            CURLOPT_COOKIEFILE     => $this->cookieFile,
            CURLOPT_COOKIE         => rtrim($cookieHeader, '; '),
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERAGENT      => $this->userAgent,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Sec-Ch-Ua: "Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
                'Sec-Ch-Ua-Mobile: ?0',
                'Sec-Ch-Ua-Platform: "Windows"'
            ]
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Auto-detect and parse dynamic challenge cookies if server serves a new hash
        if (is_string($rawResponse) && preg_match('/document\.cookie\s*=\s*["\']([^=]+)=([^;\'"]+)["\']/', $rawResponse, $m)) {
            $this->extraCookies[$m[1]] = $m[2];
            $this->log("Captured challenge cookie: '{$m[1]}={$m[2]}'. Retrying request...");
            sleep(1);
            return $this->httpPostJson($data);
        }

        $decoded = is_string($rawResponse) ? json_decode($rawResponse, true) : null;

        return [
            'http_code'  => $httpCode,
            'curl_error' => $curlError,
            'raw_body'   => is_string($rawResponse) ? $rawResponse : '',
            'data'       => is_array($decoded) ? $decoded : []
        ];
    }

    private function log(string $msg): void
    {
        $time = date('H:i:s');
        echo "[Step {$this->stepCount} | {$time}] {$msg}\n";
    }

    private function printReport(): void
    {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "               ROBOPLAYER EXPLORATION REPORT\n";
        echo str_repeat('=', 60) . "\n\n";

        echo "1. MAP OF ROOMS & EXITS:\n";
        echo str_repeat('-', 40) . "\n";
        foreach ($this->rooms as $id => $info) {
            echo "Room ID: [{$id}] \"{$info['name']}\"\n";
            if (!empty($info['description'])) {
                echo "  Description: " . trim($info['description']) . "\n";
            }
            echo "  Declared Exits:\n";
            if (empty($info['exits'])) {
                echo "    (None declared)\n";
            } else {
                foreach ($info['exits'] as $ex) {
                    $dir = $ex['direction'] ?? '?';
                    $lid = $ex['link_id'] ?? '';
                    $name = $ex['name'] ?? '';
                    $act = !empty($dir) ? $dir : $lid;
                    $isBlocked = !empty($this->blockedExits[$id][$act]) ? ' [BLOCKED/LOCKED]' : '';
                    echo "    - Direction [{$dir}] (link: {$lid}) -> {$name}{$isBlocked}\n";
                }
            }

            echo "  Discovered Transitions (Graph Edges):\n";
            if (empty($this->roomAdjacency[$id])) {
                echo "    (No outgoing transitions mapped)\n";
            } else {
                foreach ($this->roomAdjacency[$id] as $dest => $action) {
                    echo "    - Via '{$action}' ===> [{$dest}]\n";
                }
            }
            echo "\n";
        }

        echo "\n2. DISCOVERED ITEMS:\n";
        echo str_repeat('-', 40) . "\n";
        if (empty($this->items)) {
            echo "No items encountered.\n";
        } else {
            foreach ($this->items as $id => $it) {
                echo "Item ID: [{$id}]\n";
                echo "  Name: {$it['name']}\n";
                echo "  Location: Found in room [{$it['firstSeenRoom']}]\n";
                if (!empty($it['description'])) {
                    echo "  Description: {$it['description']}\n";
                }
                echo "\n";
            }
        }

        echo "\n3. DISCOVERED CHARACTERS / NPCS:\n";
        echo str_repeat('-', 40) . "\n";
        if (empty($this->characters)) {
            echo "No characters encountered.\n";
        } else {
            foreach ($this->characters as $id => $ch) {
                echo "Character ID: [{$id}]\n";
                echo "  Name: {$ch['name']}\n";
                echo "  Location: Encountered in room [{$ch['firstSeenRoom']}]\n\n";
            }
        }

        echo str_repeat('=', 60) . "\n";
        echo "End of Exploration. Total Steps: {$this->stepCount}\n";
        echo str_repeat('=', 60) . "\n";
    }
}

if (php_sapi_name() === 'cli') {
    $endpoint = $argv[1] ?? 'https://test.dimensionex.net/dimya/php/multiplayer.php';
    $bot = new RoboPlayer(endpoint: $endpoint, requestDelaySeconds: 5);
    $bot->run();
}