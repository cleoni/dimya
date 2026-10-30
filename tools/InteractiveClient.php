<?php
/**
 * InteractiveClient.php - CLI Terminal Client for DimensioneY (UIbot)
 * Updated with accumulated message history (/m) and dynamic help (/help).
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

    // Latest commands permitted by the game server
    private array $availableEngineCommands = [];

    public function __construct(string $endpoint = 'https://test.dimensionex.net/dimya/php/multiplayer.php')
    {
        $this->endpoint   = $endpoint;
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
        echo "Tip: Type /help for available commands or /m to review message history.\n";

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

        $newMessagesThisTurn = [];

        // 1. Process server messages
        if (!empty($data['messages'])) {
            foreach ($data['messages'] as $msg) {
                $clean = strip_tags($msg);
                $hash  = md5("srv:" . $clean);
                $entry = [
                    'time' => date('H:i:s'),
                    'type' => 'SERVER',
                    'text' => $clean
                ];
                $this->messageHistory[] = $entry;
                $newMessagesThisTurn[] = "[!] {$clean}";
            }
        }

        // 2. Process chat / dialogue
        if (!empty($data['chat'])) {
            foreach ($data['chat'] as $chatMsg) {
                $sender = $chatMsg['sender'] ?? 'Chat';
                $text   = $chatMsg['text'] ?? ($chatMsg['message'] ?? json_encode($chatMsg));
                $line   = "<{$sender}> {$text}";
                $hash   = md5("chat:" . $line);

                // Chat array on UIbot may repeat past chat lines in the backlog
                if (!isset($this->seenMessageHashes[$hash])) {
                    $this->seenMessageHashes[$hash] = true;
                    $entry = [
                        'time'   => date('H:i:s'),
                        'type'   => 'CHAT',
                        'sender' => $sender,
                        'text'   => $text
                    ];
                    $this->messageHistory[] = $entry;
                    $newMessagesThisTurn[] = "  {$line}";
                }
            }
        }

        echo "\n" . str_repeat('-', 65) . "\n";

        // Print incoming messages received this turn
        if (!empty($newMessagesThisTurn)) {
            foreach ($newMessagesThisTurn as $msgLine) {
                echo "{$msgLine}\n";
            }
        }

        $room = $data['room'] ?? null;
        if (!$room) {
            echo str_repeat('-', 65) . "\n";
            return;
        }

        // 3. Room details
        $roomName = $room['name'] ?? 'Unknown Area';
        $roomId   = $room['id'] ?? '';
        echo "\n=== {$roomName} [{$roomId}] ===\n";

        if (!empty($room['description'])) {
            echo wordwrap(trim($room['description']), 65) . "\n";
        }

        // 4. Visible items
        if (!empty($room['items'])) {
            echo "\nItems here:\n";
            foreach ($room['items'] as $item) {
                $status = isset($item['open']) ? ($item['open'] ? ' (open)' : ' (closed)') : '';
                echo "  * [{$item['id']}] {$item['name']}{$status}\n";
            }
        }

        // 5. Present characters
        if (!empty($room['occupants'])) {
            echo "\nPresent characters:\n";
            foreach ($room['occupants'] as $occ) {
                echo "  * [{$occ['id']}] {$occ['name']}\n";
            }
        }

        // 6. Available exits
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

        echo str_repeat('-', 65) . "\n";
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
        echo "  /m                   - List all messages and dialogue history recorded\n";
        echo "  /help                - Show this help summary\n\n";

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
        echo str_repeat('=', 65) . "\n";
    }

    private function sendPayload(array $payload): array
    {
        $resp = $this->httpPostJson($payload);

        if (($resp['http_code'] ?? 200) === 503 || ($resp['data']['status'] ?? '') === 'lock_busy') {
            usleep(200000);
            $resp = $this->httpPostJson($payload);
        }

        return $resp['data'] ?? [];
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
        curl_close($ch);

        if (is_string($rawResponse) && preg_match('/document\.cookie\s*=\s*["\']([^=]+)=([^;\'"]+)["\']/', $rawResponse, $m)) {
            $this->extraCookies[$m[1]] = $m[2];
            return $this->httpPostJson($data);
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

if (php_sapi_name() === 'cli') {
    $endpoint = $argv[1] ?? 'https://test.dimensionex.net/dimya/php/multiplayer.php';
    $client = new InteractiveClient(endpoint: $endpoint);
    $client->start();
}