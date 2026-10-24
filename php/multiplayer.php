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
use Dimx\UIbot;
use Dimx\GameEngine;
use Dimx\PersistenceManager;

// 1. Bootstrap configuration & persistence
$config   = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
$dbConfig = $config['db'] ?? [];
$baseUrl  = $config['base_url'] ?? '';
$testBots = (array)($config['testBots'] ?? ['BotAlpha']);
$pm       = new PersistenceManager($dbConfig, __DIR__ . '/data');

// Helper to instantiate appropriate Bot UI (UIjson for test bots, UIbot for standard bots)
$createBotUi = function (string $name) use ($testBots): UI {
    return in_array($name, $testBots, true) ? new UIjson() : new UIbot();
};

// 2. Parse and sanitize input parameters (POST, GET, and JSON body)
$input         = UI::parseInput();
$requestedUser = $input['username'];
$password      = $input['password'];
$resetWorld    = $input['reset'] || (isset($_GET['reset']) && $_GET['reset'] === '1');

// 3. Determine client type (bot / JSON API vs human / HTML browser)
$clientType     = UI::detectClientType();
$sessionUser    = (string)($_SESSION['dimx_player'] ?? '');
$activeUsername = !empty($requestedUser) ? $requestedUser : $sessionUser;
$ui             = ($clientType === 'json') ? $createBotUi($activeUsername) : new UIhtml();

// 4. Single authoritative world per installation (configured strictly on server)
$worldFile = $config['world_file'] ?? $config['default_world'] ?? null;
$worldId   = 'main';

// 5. Player Authentication & Account Handling
$sessionUser     = (string)($_SESSION['dimx_player'] ?? '');
$isAuthenticated = !empty($_SESSION['dimx_authenticated']);
$numericId       = $_SESSION['dimx_numeric_id'] ?? null;
$sessionEngineId = $_SESSION['dimx_engine_player_id'] ?? null;
$isBot           = ($clientType === 'json');
$isNewLogin      = false;

if (!empty($requestedUser)) {
    // If client supplied an explicit username:
    // Check if session is already authenticated for this exact user
    if ($isAuthenticated && $sessionUser === $requestedUser) {
        $playerName = $requestedUser;
    } else {
        // Authenticate credentials or auto-create account
        if ($password === '') {
            $ui->renderLogin("Password is required for player '{$requestedUser}'.", $requestedUser);
            exit;
        }

        $auth = $pm->authenticateOrCreatePlayer($worldId, $requestedUser, $password, $isBot);
        if (!$auth['success']) {
            $ui->renderLogin($auth['error'] ?? 'Authentication failed.', $requestedUser);
            exit;
        }

        $playerName = (string)($auth['name'] ?? $requestedUser);
        $numericId  = (int)($auth['numeric_id'] ?? 0);
        $isBot      = !empty($auth['is_bot']) || $isBot;

        $_SESSION['dimx_player']        = $playerName;
        $_SESSION['dimx_authenticated'] = true;
        $_SESSION['dimx_numeric_id']    = $numericId;
        $_SESSION['dimx_is_bot']        = $isBot;

        $isNewLogin      = true;
        $sessionEngineId = null;
    }
} elseif ($isAuthenticated && $sessionUser !== '') {
    // Ongoing authenticated session
    $playerName = $sessionUser;
} else {
    // No username provided and no authenticated session
    $ui->renderLogin();
    exit;
}

$_SESSION['dimx_player'] = $playerName;
$_SESSION['dimx_world']  = $worldId;

// Ensure UI instance matches finalized playerName
if ($clientType === 'json') {
    $ui = $createBotUi($playerName);
}

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
    $ui,
    $numericId ? (int)$numericId : null,
    (bool)$isBot,
    $isNewLogin,
    $sessionEngineId
);

// Store the active in-memory player ID in session
if (!empty($result['player']) && $result['player'] instanceof Dimx\Player) {
    $_SESSION['dimx_engine_player_id'] = $result['player']->id;
}

// 9. If player logged out, clear active session
if (!empty($result['logged_out'])) {
    unset(
        $_SESSION['dimx_player'],
        $_SESSION['dimx_authenticated'],
        $_SESSION['dimx_numeric_id'],
        $_SESSION['dimx_is_bot'],
        $_SESSION['dimx_engine_player_id']
    );
}

// 10. Attach UI to player and return outcome via the player's UI
$player = $result['player'] ?? null;
if ($player !== null) {
    if (isset($_REQUEST['width']) && (int)$_REQUEST['width'] > 0) {
        $_SESSION['dimx_screenwidth'] = (int)$_REQUEST['width'];
    } elseif (isset($_REQUEST['screensize']) && preg_match('/^(\d+)x(\d+)$/i', (string)$_REQUEST['screensize'], $sm)) {
        $_SESSION['dimx_screenwidth'] = (int)$sm[1];
        $_SESSION['dimx_screenheight'] = (int)$sm[2];
    }
    if (isset($_SESSION['dimx_screenwidth'])) {
        $player->screenWidth = (int)$_SESSION['dimx_screenwidth'];
    }
    if (isset($_SESSION['dimx_screenheight'])) {
        $player->screenHeight = (int)$_SESSION['dimx_screenheight'];
    }
    $player->setUI($ui);
    $ui->setPlayer($player);
}

$ui->render($result);
