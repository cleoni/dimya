<?php
declare(strict_types=1);

/**
 * chat.php
 *
 * Backward-compatible endpoint forwarding GET message polling to multiplayer.php?view=msgs
 * to ensure all polling executes inside the engine's critical section (WorldLock),
 * updates player interaction timestamps, advances world ticks, prunes stale players,
 * and logs to debug.log.
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $_GET['view'] = 'msgs';
    if (!isset($_GET['username']) && isset($_GET['player'])) {
        $_GET['username'] = $_GET['player'];
    }
    if (!isset($_SERVER['DIMX_WORLD_ID']) && isset($_GET['world_id'])) {
        $_SERVER['DIMX_WORLD_ID'] = (string)$_GET['world_id'];
    }
    require __DIR__ . '/multiplayer.php';
    exit;
}

if ($method === 'POST') {
    require_once __DIR__ . '/autoload.php';
    $config   = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
    $dbConfig = $config['db'] ?? [];
    $pm       = new \Dimx\PersistenceManager($dbConfig, __DIR__ . '/data');

    $worldId    = trim((string)($_GET['world_id'] ?? $_POST['world_id'] ?? $_SESSION['dimx_world'] ?? $_SERVER['DIMX_WORLD_ID'] ?? ($config['world_id'] ?? 'main')));
    $senderName = trim((string)($_POST['sender'] ?? ''));
    $text       = trim((string)($_POST['text']   ?? ''));

    if ($senderName === '' || $text === '') {
        http_response_code(400);
        echo json_encode(['error' => 'missing fields']);
        exit;
    }

    $text = mb_substr(strip_tags($text), 0, 512);
    $ok   = $pm->postMessage($worldId, $senderName, $text);
    echo json_encode(['ok' => $ok]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method not allowed']);
