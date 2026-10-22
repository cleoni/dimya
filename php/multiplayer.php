<?php
declare(strict_types=1);

/**
 * multiplayer.php
 *
 * DimensioneX Multiplayer Player Gateway.
 *
 * Serves two types of players:
 *  1) Human browser players: Rendered with rich graphical HTML, scene imagery, and UI panels (UIhtml).
 *  2) Bot / AI players: API endpoint returning structured JSON room snapshots and command outcomes (UIjson).
 *
 * Concurrency & World Mutations:
 *  - Fully encapsulated in Dimx\GameEngine using WorldLock::executeAtomic() (MySQL GET_LOCK).
 *  - Presentation logic is decoupled into Dimx\UI (UIhtml and UIjson).
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/autoload.php';

use Dimx\UI;
use Dimx\UIhtml;
use Dimx\UIjson;
use Dimx\GameEngine;
use Dimx\PersistenceManager;

// 1. Bootstrap configuration & persistence
$config   = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
$dbConfig = $config['db'] ?? [];
$baseUrl  = $config['base_url'] ?? '';
$pm       = new PersistenceManager($dbConfig, __DIR__ . '/data');

// 2. Determine client type (bot / JSON API vs human / HTML browser)
$clientType = UI::detectClientType();
$ui = ($clientType === 'json') ? new UIjson() : new UIhtml();

// 3. Parse and sanitize input parameters (POST, GET, and JSON body)
$input      = UI::parseInput();
$playerName = $input['username'] ?: ($_SESSION['dimx_player'] ?? '');
$resetWorld = $input['reset'] || (isset($_GET['reset']) && $_GET['reset'] === '1');

// 4. Single authoritative world per installation (configured strictly on server)
$worldFile = $config['world_file'] ?? $config['default_world'] ?? null;
$worldId   = 'main';

// 5. If no player name provided, show login prompt according to UI type
if (empty($playerName)) {
    $ui->renderLogin();
    exit;
}

$_SESSION['dimx_player'] = $playerName;
$_SESSION['dimx_world']  = $worldId;

// 6. Handle world reset request if invoked
if ($resetWorld) {
    $pm->resetWorld($worldId);
    unset($_SESSION['dimx_world_obj']);
}

// 7. Instantiate core GameEngine for atomic command processing
$engine = new GameEngine($pm, $worldId, $worldFile, $baseUrl);
$ui->setEngine($engine);

// 8. Process player command inside concurrency critical section
$result = $engine->processCommand(
    $playerName,
    $input['cmd'],
    $input['param'],
    $input['target'],
    $ui
);

// 9. If player logged out, clear active session
if (!empty($result['logged_out'])) {
    unset($_SESSION['dimx_player']);
}

// 10. Attach UI to player and return outcome via the player's UI
$player = $result['player'] ?? null;
if ($player !== null) {
    $player->setUI($ui);
    $ui->setPlayer($player);
}

$ui->render($result);
