<?php
declare(strict_types=1);

/**
 * chat.php
 *
 * Lightweight async chat endpoint — fully concurrent, no world lock.
 *
 * GET  chat.php?world_id=X&after=N   → JSON {messages:[{id,sender,text,time},...]}
 * POST chat.php                      → body: world_id=X&sender=Y&text=Z  → JSON {ok:true}
 *
 * This endpoint is intentionally separate from multiplayer.php so that
 * reading/writing chat messages NEVER contends with the game-object critical
 * section (WorldLock). Multiple players can poll simultaneously without any
 * coordination overhead.
 */

require_once __DIR__ . '/autoload.php';

use Dimx\PersistenceManager;

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config   = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
$dbConfig = $config['db'] ?? [];
$pm       = new PersistenceManager($dbConfig, __DIR__ . '/data');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$worldId = trim((string)($_GET['world_id'] ?? $_POST['world_id'] ?? $_SESSION['dimx_world'] ?? $_SERVER['DIMX_WORLD_ID'] ?? ($config['world_id'] ?? 'main')));

if ($method === 'GET') {
    $playerName = trim((string)($_GET['player'] ?? $_GET['username'] ?? $_SESSION['dimx_player'] ?? ''));
    $afterId    = isset($_GET['after']) ? (int)$_GET['after'] : null;

    $messages = [];
    $world = $pm->loadWorld($worldId);
    if ($world !== null && $playerName !== '') {
        $player = $world->getPlayerByName($playerName) ?? $world->getPlayer($playerName);
        if ($player !== null) {
            $messages = $player->getMessages();
        }
    }

    // Fallback to persistence manager messages if no active world player messages
    if (empty($messages)) {
        $messages = $pm->getMessages($worldId, $afterId !== null && $afterId > 0 ? $afterId : 0, 10, $playerName !== '' ? $playerName : null);
    } elseif ($afterId !== null && $afterId > 0) {
        $messages = array_values(array_filter($messages, function ($m) use ($afterId) {
            return isset($m['id']) && is_numeric($m['id']) && (int)$m['id'] > $afterId;
        }));
    }

    $commands = $playerName !== '' ? $pm->drainCommands($worldId, $playerName) : '';

    $response = ['messages' => $messages, 'ok' => true];
    if ($commands !== '') {
        $response['commands'] = $commands;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $senderName = trim($_POST['sender'] ?? '');
    $text       = trim($_POST['text']   ?? '');

    if ($senderName === '' || $text === '') {
        http_response_code(400);
        echo json_encode(['error' => 'missing fields']);
        exit;
    }

    // Sanitize text (strip tags, limit length)
    $text = mb_substr(strip_tags($text), 0, 512);

    $ok = $pm->postMessage($worldId, $senderName, $text);
    echo json_encode(['ok' => $ok]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method not allowed']);
