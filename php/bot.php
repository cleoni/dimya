<?php
declare(strict_types=1);

/**
 * bot.php
 *
 * Automated player client for DimensioneY using cURL.
 *
 * Workflow:
 *  1) Authenticate / log into game engine via JSON API.
 *  2) Log all HTTP requests and responses to conversation.log.
 *  3) Speak "Hello!" in the room.
 *  4) Inspect visible room exits (LINKs) and move in an available direction.
 *  5) Read the new room's name and speak "Nice to be in (room name)".
 *  6) Disconnect / exit the game.
 */

// ============================================================================
// CONFIGURATION
// ============================================================================
$gameUrl  = 'http://localhost/dimya/php/multiplayer.php';
$username = 'phpBot';
$password = 'test123';
$logFile  = __DIR__ . '/conversation.log';

// Temporary cookie storage for preserving session across requests
$cookieFile = sys_get_temp_dir() . '/dimya_bot_cookie_' . md5($username) . '.txt';
if (file_exists($cookieFile)) {
    @unlink($cookieFile);
}

// ----------------------------------------------------------------------------
// Helper: Send JSON request to the Game Engine via cURL and log interaction
// ----------------------------------------------------------------------------
function callGameApi(string $url, array $payload, string $cookieFile, string $logFile): array
{
    $jsonPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonPayload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_TIMEOUT        => 15,
    ]);

    $rawResponse = curl_exec($ch);
    $httpCode    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr     = curl_error($ch);
    curl_close($ch);

    // Format log entry
    $timestamp = date('Y-m-d H:i:s');
    $logEntry  = "================================================================================\n";
    $logEntry .= "[{$timestamp}] REQUEST to {$url}\n";
    $logEntry .= "PAYLOAD:\n{$jsonPayload}\n\n";
    $logEntry .= "[{$timestamp}] RESPONSE (HTTP {$httpCode}):\n";
    if ($curlErr !== '') {
        $logEntry .= "CURL ERROR: {$curlErr}\n";
    }
    $logEntry .= ($rawResponse !== false && $rawResponse !== '' ? $rawResponse : '<empty response>') . "\n";
    $logEntry .= "================================================================================\n\n";

    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

    if ($rawResponse === false) {
        throw new RuntimeException("cURL request failed: {$curlErr}");
    }

    $decoded = json_decode($rawResponse, true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Invalid JSON response from engine (HTTP {$httpCode}): {$rawResponse}");
    }

    return $decoded;
}

// ============================================================================
// MAIN BOT EXECUTION
// ============================================================================

echo "=== DimensioneY PHP Bot ===\n";
echo "Target URL : {$gameUrl}\n";
echo "Player     : {$username}\n";
echo "Log file   : {$logFile}\n\n";

// Write session start marker to log
$sessionHeader = "\n################################################################################\n"
               . "# BOT SESSION STARTED AT " . date('Y-m-d H:i:s') . "\n"
               . "# User: {$username} | Target: {$gameUrl}\n"
               . "################################################################################\n\n";
file_put_contents($logFile, $sessionHeader, FILE_APPEND | LOCK_EX);

try {
    // ------------------------------------------------------------------------
    // Step 1: Login
    // ------------------------------------------------------------------------
    echo "[1/5] Logging in as '{$username}'...\n";
    $loginResponse = callGameApi($gameUrl, [
        'username' => $username,
        'password' => $password,
        'cmd'      => 'login',
    ], $cookieFile, $logFile);

    if (empty($loginResponse['success'])) {
        $err = $loginResponse['error'] ?? 'Login failed.';
        throw new RuntimeException("Login was rejected: {$err}");
    }

    $currentRoom     = $loginResponse['room'] ?? null;
    $currentRoomName = $currentRoom['name'] ?? 'Unknown Room';
    echo "      -> Successfully logged in!\n";
    echo "      -> Current Room: \"{$currentRoomName}\"\n\n";

    // ------------------------------------------------------------------------
    // Step 2: Speak "Hello!"
    // ------------------------------------------------------------------------
    echo "[2/5] Saying 'Hello!' in the room...\n";
    $sayHelloResponse = callGameApi($gameUrl, [
        'username' => $username,
        'cmd'      => 'say',
        'param'    => 'Hello!',
    ], $cookieFile, $logFile);
    echo "      -> Said: \"Hello!\"\n\n";

    // ------------------------------------------------------------------------
    // Step 3: Choose an obvious LINK and move
    // ------------------------------------------------------------------------
    echo "[3/5] Inspecting available links/exits...\n";
    $exits = $sayHelloResponse['room']['exits'] ?? $currentRoom['exits'] ?? [];

    if (empty($exits)) {
        throw new RuntimeException("No exits found in room '{$currentRoomName}' to move to.");
    }

    // Pick first open exit or first available exit
    $selectedExit = null;
    foreach ($exits as $exit) {
        if (!isset($exit['open']) || $exit['open']) {
            $selectedExit = $exit;
            break;
        }
    }
    if ($selectedExit === null) {
        $selectedExit = $exits[0];
    }

    $direction   = $selectedExit['direction'] ?? '';
    $exitName    = $selectedExit['name'] ?? $direction;
    $destName    = $selectedExit['target_room_name'] ?? 'next area';

    echo "      -> Found exit: '{$exitName}' heading [{$direction}] towards '{$destName}'.\n";
    echo "      -> Moving direction [{$direction}]...\n";

    $moveResponse = callGameApi($gameUrl, [
        'username' => $username,
        'cmd'      => 'go',
        'param'    => $direction,
    ], $cookieFile, $logFile);

    // ------------------------------------------------------------------------
    // Step 4: Read the room it's in
    // ------------------------------------------------------------------------
    $newRoom     = $moveResponse['room'] ?? null;
    $newRoomName = $newRoom['name'] ?? $destName;
    echo "      -> Arrived in room: \"{$newRoomName}\"\n\n";

    // ------------------------------------------------------------------------
    // Step 5: Speak "Nice to be in (room name here)"
    // ------------------------------------------------------------------------
    $sayRoomText = "Nice to be in {$newRoomName}";
    echo "[4/5] Speaking: \"{$sayRoomText}\"...\n";
    $sayRoomResponse = callGameApi($gameUrl, [
        'username' => $username,
        'cmd'      => 'say',
        'param'    => $sayRoomText,
    ], $cookieFile, $logFile);
    echo "      -> Said: \"{$sayRoomText}\"\n\n";

    // ------------------------------------------------------------------------
    // Step 6: Finally exit
    // ------------------------------------------------------------------------
    echo "[5/5] Exiting the game world...\n";
    $exitResponse = callGameApi($gameUrl, [
        'username' => $username,
        'cmd'      => 'exit',
    ], $cookieFile, $logFile);

    $loggedOut = !empty($exitResponse['logged_out']);
    echo "      -> Disconnected. Logged out status: " . ($loggedOut ? "Yes" : "Done") . "\n\n";

    echo "=== Bot Finished Successfully ===\n";
    echo "See '{$logFile}' for the full request/response audit trail.\n";

} finally {
    // Clean up session cookie file
    if (file_exists($cookieFile)) {
        @unlink($cookieFile);
    }
}
