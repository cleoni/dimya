<?php
/**
 * InteractiveClient.php - CLI Terminal Client for DimensioneY (UIbot)
 * Updated with accumulated message history (/m), dynamic help (/help),
 * directional shorthands (n, s, e, w, u, d), and raw backend debug logging (/debug on|off).
 */

declare(strict_types=1);

class InteractiveClient
{
    private string $endpoint;
    private string $cookieFile;
    private array $extraCookies = [
        'humans_21909' => '1'
    ];
    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    // Global accumulated log of all messages and chat lines
    private array $messageHistory = [];
    private array $seenMessageHashes = [];

    // Inbox holding up to 5 messages (#msgs)
    protected array $inbox = [];
    protected array $dismissedMessageIds = [];

    // Latest commands permitted by the game server
    private array $availableEngineCommands = [];

    // Debug flag to print raw backend responses
    private bool $debug = false;

    public function __construct(
        string $endpoint = 'https://test.dimensionex.net/dimya/php/multiplayer.php',
        bool $debug = false
    ) {
        $this->endpoint   = $endpoint;
        $this->debug      = $debug;
        $this->cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dxclient_cookie_' . uniqid() . '.txt';
    }

    public function __destruct()
    {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function start(): void
    {
        echo "\n" . str_repeat('=', 65) . "\n";
        echo "       DIMENSIONEY INTERACTIVE BOT CLIENT (UIBOT CLI)\n";
        echo str_repeat('=', 65) . "\n\n";

        // Prompt for credentials
        $username = $this->prompt("Enter Bot Username: ");
        $password = $this->prompt("Enter Bot Password: ");

        if ($username === '' || $password === '') {
            echo "Username and password cannot be empty.\n";
            return;
        }

        echo "\nConnecting to {$this->endpoint}...\n";

        // Perform login
        $loginResp = $this->sendPayload([
            'cmd'      => 'login',
            'username' => $username,
            'password' => $password
        ]);

        if (empty($loginResp['success'])) {
            $err = $loginResp['error'] ?? ($loginResp['status'] ?? 'Unknown authentication error');
            echo "[-] Login failed: {$err}\n";
            return;
        }

        echo "[+] Successfully logged in as '{$username}'!\n";
        echo "Tip: Type /help for available commands, 'read' for inbox, or /debug on for raw responses.\n";

        $this->recordAndRenderState($loginResp);

        // Main game loop
        while (true) {
            $input = $this->prompt("\n> ");
            $input = trim($input);

            if ($input === '') {
                continue;
            }

            // Handle client special commands
            $cmdLower = strtolower($input);
            if ($cmdLower === '/m') {
                $this->showMessages();
                continue;
            }

            if ($cmdLower === '/help') {
                $this->showHelp();
                continue;
            }

            if ($cmdLower === 'read' || str_starts_with($cmdLower, 'read ') || $cmdLower === '/read' || str_starts_with($cmdLower, '/read ')) {
                $this->handleReadCommand($input);
                continue;
            }

            if (str_starts_with($cmdLower, '/debug')) {
                if (preg_match('/^\/debug\s+on$/i', $input)) {
                    $this->debug = true;
                    echo "[*] Debug mode enabled (ON). Raw backend responses will be displayed.\n";
                } elseif (preg_match('/^\/debug\s+off$/i', $input)) {
                    $this->debug = false;
                    echo "[*] Debug mode disabled (OFF).\n";
                } elseif (trim($cmdLower) === '/debug') {
                    echo "[*] Debug mode is currently " . ($this->debug ? "ON" : "OFF") . ". Use '/debug on' or '/debug off'.\n";
                } else {
                    echo "Invalid syntax. Usage: /debug on | /debug off\n";
                }
                continue;
            }

            // Parse command and parameters
            $parsed = $this->parseInput($input);

            // Execute action
            $state = $this->sendPayload([
                'cmd'    => $parsed['cmd'],
                'param'  => $parsed['param'],
                'target' => $parsed['target']
            ]);

            // Render updated state and store messages
            $this->recordAndRenderState($state);

            // Check if game or session ended
            if (!empty($state['logged_out']) || strtolower($parsed['cmd']) === 'logout') {
                echo "\nSession terminated. Goodbye!\n";
                break;
            }
        }
    }

    private function parseInput(string $input): array
    {
        $tokens = preg_split('/\s+/', trim($input), 3);

        $cmd    = $tokens[0] ?? '';
        $param  = $tokens[1] ?? '';
        $target = $tokens[2] ?? '';

        $cmdLower   = strtolower($cmd);
        $paramLower = strtolower($param);

        // Direction shorthands and mappings
        $directions = [
            'n'         => 'n',
            's'         => 's',
            'e'         => 'e',
            'w'         => 'w',
            'u'         => 'u',
            'd'         => 'd',
            'ne'        => 'ne',
            'nw'        => 'nw',
            'se'        => 'se',
            'sw'        => 'sw',
            'north'     => 'n',
            'south'     => 's',
            'east'      => 'e',
            'west'      => 'w',
            'up'        => 'u',
            'down'      => 'd',
            'northeast' => 'ne',
            'northwest' => 'nw',
            'southeast' => 'se',
            'southwest' => 'sw',
        ];

        // Standalone directional shorthands (e.g., "n", "s", "e", "w", "u", "d") expand to "go <dir>"
        if ($param === '' && $target === '' && isset($directions[$cmdLower])) {
            return [
                'cmd'    => 'go',
                'param'  => $directions[$cmdLower],
                'target' => ''
            ];
        }

        // Normalize /html command so it is sent to UIbot
        if ($cmdLower === '/html') {
            $cmd      = 'html';
            $cmdLower = 'html';
        }

        // Normalize direction parameters when using explicit "go" (e.g. "go north" -> "go n")
        if ($cmdLower === 'go' && isset($directions[$paramLower])) {
            $param = $directions[$paramLower];
        }

        return [
            'cmd'    => $cmd,
            'param'  => $param,
            'target' => $target
        ];
    }

    private function recordAndRenderState(array $data): void
    {
        // Update permitted command list if returned by engine
        if (!empty($data['commands']) && is_array($data['commands'])) {
            $this->availableEngineCommands = $data['commands'];
        }

        // 1. Process incoming messages into inbox (holds up to 5 messages)
        if (!empty($data['msgs']) && is_array($data['msgs'])) {
            foreach ($data['msgs'] as $chatMsg) {
                if (!is_array($chatMsg)) {
                    continue;
                }
                $sender = (string)($chatMsg['sender'] ?? 'Chat');
                $text   = (string)($chatMsg['text'] ?? ($chatMsg['message'] ?? ''));
                $time   = (string)($chatMsg['time'] ?? date('H:i:s'));
                $msgId  = (string)($chatMsg['id'] ?? md5($sender . ':' . $text . ':' . $time));

                if (isset($this->dismissedMessageIds[$msgId])) {
                    continue;
                }

                $exists = false;
                foreach ($this->inbox as $m) {
                    if ((string)($m['id'] ?? '') === $msgId) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $entry = [
                        'id'        => $msgId,
                        'sender'    => $sender,
                        'recipient' => (string)($chatMsg['recipient'] ?? ''),
                        'text'      => $text,
                        'time'      => $time,
                    ];
                    $this->inbox[] = $entry;

                    $line = "<{$sender}> {$text}";
                    $hash = md5("chat:" . $line);
                    if (!isset($this->seenMessageHashes[$hash])) {
                        $this->seenMessageHashes[$hash] = true;
                        $this->messageHistory[] = [
                            'time'   => $time,
                            'type'   => 'CHAT',
                            'sender' => $sender,
                            'text'   => $text
                        ];
                    }
                }
            }

            // Keep only the most recent 5 messages in inbox
            while (count($this->inbox) > 5) {
                array_shift($this->inbox);
            }
        }

        // 2. Record console output to messageHistory for /m
        if (!empty($data['console']) && is_array($data['console'])) {
            foreach ($data['console'] as $cMsg) {
                $text = (string)$cMsg;
                $this->messageHistory[] = [
                    'time' => date('H:i:s'),
                    'type' => 'SERVER',
                    'text' => $text
                ];
            }
        }

        echo "\n" . str_repeat('-', 65) . "\n";

        $room = $data['room'] ?? null;
        if (!$room) {
            if (!empty($data['console']) && is_array($data['console'])) {
                foreach ($data['console'] as $cMsg) {
                    $t = trim((string)$cMsg);
                    if ($t !== '') {
                        echo "[!] {$t}\n";
                    }
                }
            }
            echo str_repeat('-', 65) . "\n";
            return;
        }

        // 1. First, Room Name
        $roomName = $room['name'] ?? 'Unknown Area';
        $facing   = $room['facing'] ?? ($data['facing'] ?? '');
        $roomId   = $room['id'] ?? '';
        $tag      = ($facing !== '') ? $facing : $roomId;
        $tagStr   = ($tag !== '') ? " [{$tag}]" : '';
        echo "=== {$roomName}{$tagStr} ===\n";

        // 2. Second, Room description
        if (!empty($room['description'])) {
            echo wordwrap(trim((string)$room['description']), 65) . "\n";
        }

        // 3. Third, the #console
        if (!empty($data['console']) && is_array($data['console'])) {
            echo "\n";
            foreach ($data['console'] as $cMsg) {
                $text = trim((string)$cMsg);
                if ($text !== '') {
                    echo "[!] {$text}\n";
                }
            }
        }

        // 4. Fourth, number of messages. If zero don't print anything (no 0 messages)
        $inboxCount = count($this->inbox);
        if ($inboxCount > 0) {
            $msgLabel = ($inboxCount === 1) ? 'message' : 'messages';
            echo "\nInbox: {$inboxCount} {$msgLabel}\n";
        }

        // 5. Fifth, Exits
        if (!empty($room['exits'])) {
            echo "\nExits:\n";
            foreach ($room['exits'] as $exit) {
                $dir    = $exit['direction'] ?? '?';
                $linkId = $exit['link_id'] ?? '';
                $name   = $exit['name'] ?? '';
                $open   = (isset($exit['open']) && !$exit['open']) ? ' [CLOSED]' : '';
                echo "  * {$dir} ({$linkId}) -> {$name}{$open}\n";
            }
        }

        // 6. Sixth, Characters, if any
        if (!empty($room['occupants'])) {
            echo "\nPresent characters:\n";
            foreach ($room['occupants'] as $occ) {
                echo "  * [{$occ['id']}] {$occ['name']}\n";
            }
        }

        // 7. Seventh, Items, if any
        if (!empty($room['items'])) {
            echo "\nItems here:\n";
            foreach ($room['items'] as $item) {
                $status = isset($item['open']) ? ($item['open'] ? ' (open)' : ' (closed)') : '';
                echo "  * [{$item['id']}] {$item['name']}{$status}\n";
            }
        }

        echo str_repeat('-', 65) . "\n";
    }

    private function handleReadCommand(string $input): void
    {
        $parts = preg_split('/\s+/', trim($input));
        $targetIndex = null;
        if (isset($parts[1]) && is_numeric($parts[1])) {
            $targetIndex = (int)$parts[1];
        }

        if ($targetIndex !== null) {
            $this->executeReadMessage($targetIndex);
            return;
        }

        // Pass onto inbox mode
        $this->enterInboxMode();
    }

    private function enterInboxMode(): void
    {
        $count = count($this->inbox);
        if ($count === 0) {
            echo "\nYour inbox is empty (no messages).\n";
            return;
        }

        echo "\n" . str_repeat('=', 65) . "\n";
        echo "                       INBOX (" . $count . " " . ($count === 1 ? 'message' : 'messages') . ")\n";
        echo str_repeat('=', 65) . "\n";
        foreach ($this->inbox as $idx => $msg) {
            $num     = $idx + 1;
            $sender  = $msg['sender'] ?? 'Unknown';
            $time    = $msg['time'] ?? '';
            $text    = $msg['text'] ?? '';
            $preview = (mb_strlen($text) > 42) ? (mb_substr($text, 0, 39) . '...') : $text;
            echo "  [{$num}] <{$sender}> ({$time}): {$preview}\n";
        }
        echo str_repeat('-', 65) . "\n";
        echo "Enter message number (1-{$count}) to read and dismiss, or press Enter to return:\n";

        $choice = $this->prompt("inbox> ");
        $choice = trim($choice);

        if ($choice === '' || in_array(strtolower($choice), ['q', 'exit', 'cancel', 'back'], true)) {
            echo "Exited inbox mode.\n";
            return;
        }

        if (preg_match('/^(?:read\s+)?(\d+)$/i', $choice, $m)) {
            $num = (int)$m[1];
            $this->executeReadMessage($num);
        } else {
            echo "Invalid selection. Exited inbox mode.\n";
        }
    }

    private function executeReadMessage(int $num): void
    {
        $count = count($this->inbox);
        if ($count === 0) {
            echo "\nYour inbox is empty.\n";
            return;
        }

        if ($num < 1 || $num > $count) {
            echo "\nInvalid message number [{$num}]. Inbox has {$count} " . ($count === 1 ? 'message' : 'messages') . " (1-{$count}).\n";
            return;
        }

        $msgIndex = $num - 1;
        $msg      = $this->inbox[$msgIndex];
        $msgId    = (string)($msg['id'] ?? '');

        // 1. Printout message
        echo "\n" . str_repeat('=', 65) . "\n";
        echo "MESSAGE [{$num}] FROM <{$msg['sender']}> ({$msg['time']})\n";
        if (!empty($msg['recipient'])) {
            echo "To: {$msg['recipient']}\n";
        }
        echo str_repeat('-', 65) . "\n";
        echo wordwrap($msg['text'], 65) . "\n";
        echo str_repeat('=', 65) . "\n";
        echo "[Message dismissed]\n";

        // 2. Dismiss message locally
        if ($msgId !== '') {
            $this->dismissedMessageIds[$msgId] = true;
        }
        array_splice($this->inbox, $msgIndex, 1);
        $this->inbox = array_values($this->inbox);

        // 3. Dismiss on game server using popmsg command
        $this->sendPayload([
            'cmd'    => 'popmsg',
            'param'  => ($msgId !== '' ? $msgId : (string)$num),
            'target' => ''
        ]);

        // 4. Return to normal mode as if had performed a plain look command
        $state = $this->sendPayload([
            'cmd'    => 'look',
            'param'  => '',
            'target' => ''
        ]);
        $this->recordAndRenderState($state);
    }

    private function showMessages(): void
    {
        echo "\n" . str_repeat('=', 65) . "\n";
        echo "               ALL RECEIVED MESSAGES HISTORY (/m)\n";
        echo str_repeat('=', 65) . "\n";

        if (empty($this->messageHistory)) {
            echo "No messages recorded yet.\n";
        } else {
            foreach ($this->messageHistory as $idx => $entry) {
                $num = str_pad((string)($idx + 1), 3, ' ', STR_PAD_LEFT);
                if ($entry['type'] === 'CHAT') {
                    echo "[{$num} | {$entry['time']}] <{$entry['sender']}> {$entry['text']}\n";
                } else {
                    echo "[{$num} | {$entry['time']}] [SERVER] {$entry['text']}\n";
                }
            }
        }

        echo str_repeat('=', 65) . "\n";
    }

    private function showHelp(): void
    {
        echo "\n" . str_repeat('=', 65) . "\n";
        echo "                       AVAILABLE COMMANDS (/help)\n";
        echo str_repeat('=', 65) . "\n";

        echo "Client Local Commands:\n";
        echo "  read                 - Enter inbox mode to view and read messages\n";
        echo "  read <1-5>           - Read and dismiss message N from inbox (returns to normal mode)\n";
        echo "  /m                   - List all messages and dialogue history recorded\n";
        echo "  /debug on            - Enable raw backend response printout\n";
        echo "  /debug off           - Disable raw backend response printout\n";
        echo "  /help                - Show this help summary\n\n";

        echo "UIbot Configuration Commands:\n";
        echo "  html on | html off   - Enable or disable HTML tags in #console output (also /html on|off)\n\n";

        echo "Game Engine Commands (Permitted in this world):\n";
        if (!empty($this->availableEngineCommands)) {
            $cmdChunks = array_chunk($this->availableEngineCommands, 6);
            foreach ($cmdChunks as $chunk) {
                echo "  " . implode(', ', $chunk) . "\n";
            }
        } else {
            echo "  go, look, pick, drop, open, close, search, give, use, use2, say, inv, logout\n";
        }

        echo "\nInput Formats:\n";
        echo "  <cmd>                (e.g., look, inv, search, logout)\n";
        echo "  <cmd> <param>        (e.g., go N, look switch1, use switch1, open bag, say hello)\n";
        echo "  <cmd> <param> <target> (e.g., use2 screwd switch2, give apple tommy)\n";
        echo "  n, s, e, w, u, d     (shorthand for go n, go s, go e, etc.)\n";
        echo str_repeat('=', 65) . "\n";
    }

    protected function sendPayload(array $payload): array
    {
        $resp = $this->httpPostJson($payload);

        if (($resp['http_code'] ?? 200) === 503 || ($resp['data']['status'] ?? '') === 'lock_busy') {
            usleep(200000);
            $resp = $this->httpPostJson($payload);
        }

        return $resp['data'] ?? [];
    }

    protected function httpPostJson(array $data): array
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
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if (is_string($rawResponse) && preg_match('/document\.cookie\s*=\s*["\']([^=]+)=([^;\'"]+)["\']/', $rawResponse, $m)) {
            $this->extraCookies[$m[1]] = $m[2];
            return $this->httpPostJson($data);
        }

        if ($this->debug) {
            echo "\n" . str_repeat('-', 25) . " [BACKEND RAW RESPONSE (HTTP {$httpCode})] " . str_repeat('-', 25) . "\n";
            if ($curlErr !== '') {
                echo "[CURL ERROR: {$curlErr}]\n";
            }
            echo ($rawResponse !== false && $rawResponse !== '') ? (string)$rawResponse : '<empty response>';
            echo "\n" . str_repeat('-', 78) . "\n";
        }

        $decoded = is_string($rawResponse) ? json_decode($rawResponse, true) : null;

        return [
            'http_code' => $httpCode,
            'data'      => is_array($decoded) ? $decoded : []
        ];
    }

    private function prompt(string $message): string
    {
        echo $message;
        $line = fgets(STDIN);
        return $line !== false ? trim($line) : '';
    }
}

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $endpoint = 'https://test.dimensionex.net/dimya/php/multiplayer.php';
    $debug = false;
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--debug') {
            $debug = true;
        } elseif (!str_starts_with($arg, '--')) {
            $endpoint = $arg;
        }
    }
    $client = new InteractiveClient(endpoint: $endpoint, debug: $debug);
    $client->start();
}