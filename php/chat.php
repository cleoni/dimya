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

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config   = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
$dbConfig = $config['db'] ?? [];
$pm       = new PersistenceManager($dbConfig, __DIR__ . '/data');

$method = $_SERVER['REQUEST_METHOD'];

$worldId = 'main'; // Single world per installation

if ($method === 'GET') {
    $afterId    = max(0, (int)($_GET['after'] ?? 0));
    $playerName = trim((string)($_GET['player'] ?? $_GET['username'] ?? ''));
    if ($afterId === 0) {
        // Newly connected player: do not dump historical room messages from before spawn
        echo json_encode(['messages' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $messages   = $pm->getMessages($worldId, $afterId, 60, $playerName !== '' ? $playerName : null);
    echo json_encode(['messages' => $messages], JSON_UNESCAPED_UNICODE);
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
