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
    // Support session ID passed explicitly in query, POST, headers, or JSON body (for constrained clients without cookies)
    $incomingSessionId = (string)($_GET['session_id'] ?? $_GET['PHPSESSID'] ?? $_POST['session_id'] ?? $_POST['PHPSESSID'] ?? $_SERVER['HTTP_X_SESSION_ID'] ?? '');
    if ($incomingSessionId === '') {
        $rawInputForSession = file_get_contents('php://input');
        if (!empty($rawInputForSession)) {
            $parsedJson = json_decode($rawInputForSession, true);
            if (is_array($parsedJson) && !empty($parsedJson['session_id'])) {
                $incomingSessionId = trim((string)$parsedJson['session_id']);
            }
        }
    }
    if ($incomingSessionId !== '' && preg_match('/^[-,a-zA-Z0-9]{1,128}$/', $incomingSessionId)) {
        session_id($incomingSessionId);
    }
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
$requestedSkin = $input['skin'] ?? '';
$resetWorld    = $input['reset'] || (isset($_GET['reset']) && $_GET['reset'] === '1');

// 3. Determine client type (bot / JSON API vs human / HTML browser)
$clientType     = UI::detectClientType();
$sessionUser    = (string)($_SESSION['dimx_player'] ?? '');
$activeUsername = !empty($requestedUser) ? $requestedUser : $sessionUser;
$ui             = ($clientType === 'json') ? $createBotUi($activeUsername) : new UIhtml();

// 4. Single authoritative world per installation (configured strictly on server, with query/session fallback)
$worldFile = $_SERVER['DIMX_WORLD_FILE'] ?? $_ENV['DIMX_WORLD_FILE'] ?? ($config['world_file'] ?? $config['default_world'] ?? null);
$worldId   = trim((string)($_SERVER['DIMX_WORLD_ID'] ?? $_GET['world_id'] ?? $_POST['world_id'] ?? $_SESSION['dimx_world'] ?? ($config['world_id'] ?? 'main')));

// 4b. Invalidate session if world was reset or reset is requested
$activeResetToken = (string)($pm->getSetting($worldId, 'world_reset_token', ''));
if ($resetWorld) {
    $pm->resetWorld($worldId);
    $activeResetToken = (string)($pm->getSetting($worldId, 'world_reset_token', ''));
    unset(
        $_SESSION['dimx_player'],
        $_SESSION['dimx_authenticated'],
        $_SESSION['dimx_numeric_id'],
        $_SESSION['dimx_is_bot'],
        $_SESSION['dimx_engine_player_id'],
        $_SESSION['dimx_skin'],
        $_SESSION['dimx_world_obj'],
        $_SESSION['dimx_html_tolerant']
    );
} elseif ($activeResetToken !== '' && (!isset($_SESSION['dimx_world_reset_token']) || $_SESSION['dimx_world_reset_token'] !== $activeResetToken)) {
    unset(
        $_SESSION['dimx_player'],
        $_SESSION['dimx_authenticated'],
        $_SESSION['dimx_numeric_id'],
        $_SESSION['dimx_is_bot'],
        $_SESSION['dimx_engine_player_id'],
        $_SESSION['dimx_skin'],
        $_SESSION['dimx_world_obj'],
        $_SESSION['dimx_html_tolerant']
    );
}

// Instantiate core GameEngine early so UIhtml::renderLogin can list available world SKINS
$engine = new GameEngine($pm, $worldId, $worldFile, $baseUrl, $config);
$ui->setEngine($engine);

// 4c. Special views (e.g. view=players - Section 1.2.16 Who's online)
$view = strtolower(trim((string)($input['view'] ?? $_GET['view'] ?? '')));
if ($view === 'players') {
    $world    = $engine->loadOrSeedWorld();
    $format   = trim((string)($input['raw']['format'] ?? $_GET['format'] ?? ''));
    $skinName = $requestedSkin !== '' ? $requestedSkin : (string)($_SESSION['dimx_skin'] ?? '');
    $skin     = $world->getSkin($skinName) ?? $world->defaultSkin ?? new Skin();
    $ui->renderPlayers($world, $format, $skin);
    exit;
}
if ($view === 'hof') {
    $world    = $engine->loadOrSeedWorld();
    $goals    = $pm->getGoals($worldId);
    $format   = trim((string)($input['raw']['format'] ?? $_GET['format'] ?? ''));
    $skinName = $requestedSkin !== '' ? $requestedSkin : (string)($_SESSION['dimx_skin'] ?? '');
    $skin     = $world->getSkin($skinName) ?? $world->defaultSkin ?? new Skin();
    $ui->renderHof($world, $goals, $format, $skin);
    exit;
}

// 5. Player Authentication & Account Handling
$sessionUser     = (string)($_SESSION['dimx_player'] ?? '');
$isAuthenticated = !empty($_SESSION['dimx_authenticated']);
$numericId       = $_SESSION['dimx_numeric_id'] ?? null;
$sessionEngineId = $_SESSION['dimx_engine_player_id'] ?? null;
$isBot           = ($clientType === 'json');
$isNewLogin      = false;

if ($requestedSkin !== '') {
    $_SESSION['dimx_skin'] = $requestedSkin;
}
$activeSkin = $requestedSkin !== '' ? $requestedSkin : (string)($_SESSION['dimx_skin'] ?? '');

// Capture Sounds & Music checkboxes at login form submission or when explicitly passed
$isLoginSubmission = (!empty($requestedUser) && $password !== '');
$explicitSounds = ($isLoginSubmission || !empty($input['has_sounds'])) ? (bool)($input['sounds'] ?? false) : null;
$explicitMusic  = ($isLoginSubmission || !empty($input['has_music']))  ? (bool)($input['music'] ?? false)  : null;

if ($ui instanceof UI) {
    if ($explicitSounds !== null) {
        $ui->setSounds($explicitSounds);
    }
    if ($explicitMusic !== null) {
        $ui->setMusic($explicitMusic);
    }
}
$activeSounds = $explicitSounds;
$activeMusic  = $explicitMusic;

if (!empty($requestedUser)) {
    // If client supplied an explicit username:
    // Check if session is already authenticated for this exact user
    if ($isAuthenticated && $sessionUser === $requestedUser) {
        $playerName = $requestedUser;
    } elseif ($view === 'msgs' && $password === '') {
        $world = $engine->loadOrSeedWorld(false);
        $activePlayer = $world->getPlayerByName($requestedUser) ?? $world->getPlayer($requestedUser);
        if ($activePlayer !== null) {
            $playerName      = $activePlayer->name;
            $numericId       = $activePlayer->numericId;
            $sessionEngineId = $activePlayer->id;
            $isNewLogin      = false;
        } else {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Player not found'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        // Authenticate credentials or auto-create account
        if ($password === '') {
            $commandStr = ($input['cmd'] ?? 'login') . '(' . ($input['arg0'] ?? '') . ',' . ($input['arg1'] ?? '') . ')';
            $dateStr    = date('Y-m-d H:i:s');
            $engine->log("{$dateStr} USER: {$requestedUser} | COMMAND: {$commandStr} | VIEW: {$view} | ROOM: | STATUS: DISCONNECTED (REFUSAL: password required)");
            $engine->executeTick();
            if ($ui instanceof UIhtml) {
                $ui->renderLogin("Password is required for player '{$requestedUser}'.", $requestedUser, $activeSkin, $ui->hasSounds(), $ui->hasMusic());
            } else {
                $ui->renderLogin("Password is required for player '{$requestedUser}'.", $requestedUser, $activeSkin);
            }
            exit;
        }

        $auth = $pm->authenticateOrCreatePlayer($worldId, $requestedUser, $password, $isBot);
        if (!$auth['success']) {
            $commandStr = ($input['cmd'] ?? 'login') . '(' . ($input['arg0'] ?? '') . ',' . ($input['arg1'] ?? '') . ')';
            $dateStr    = date('Y-m-d H:i:s');
            $err        = $auth['error'] ?? 'Authentication failed';
            $engine->log("{$dateStr} USER: {$requestedUser} | COMMAND: {$commandStr} | VIEW: {$view} | ROOM: | STATUS: DISCONNECTED (REFUSAL: {$err})");
            $engine->executeTick();
            if ($ui instanceof UIhtml) {
                $ui->renderLogin($auth['error'] ?? 'Authentication failed.', $requestedUser, $activeSkin, $ui->hasSounds(), $ui->hasMusic());
            } else {
                $ui->renderLogin($auth['error'] ?? 'Authentication failed.', $requestedUser, $activeSkin);
            }
            exit;
        }

        $playerName = (string)($auth['name'] ?? $requestedUser);
        $numericId  = (int)($auth['numeric_id'] ?? 0);
        $isBot      = !empty($auth['is_bot']) || $isBot;
        $isNewLogin = true;
        $sessionEngineId = null;
    }
} elseif ($isAuthenticated && $sessionUser !== '') {
    // Ongoing authenticated session
    $playerName = $sessionUser;
} else {
    // No username provided and no authenticated session
    $commandStr = ($input['cmd'] ?? 'login') . '(' . ($input['arg0'] ?? '') . ',' . ($input['arg1'] ?? '') . ')';
    $dateStr    = date('Y-m-d H:i:s');
    $engine->log("{$dateStr} USER: anonymous | COMMAND: {$commandStr} | VIEW: {$view} | ROOM: | STATUS: DISCONNECTED (REFUSAL: no session or credentials)");
    if ($view === 'msgs') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Player not specified'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($ui instanceof UIhtml) {
        $ui->renderLogin(null, null, $activeSkin, $ui->hasSounds(), $ui->hasMusic());
    } else {
        $ui->renderLogin(null, null, $activeSkin);
    }
    exit;
}

// Ensure UI instance matches finalized playerName
if ($clientType === 'json') {
    $ui = $createBotUi($playerName);
    $ui->setEngine($engine);
}

// Intercept UIbot-specific calls (e.g. toggling HTML tolerance via 'html on' / 'html off')
if ($ui instanceof UIbot && $ui->interceptCall($input, $engine, $playerName, $numericId ? (int)$numericId : null, $sessionEngineId)) {
    $_SESSION['dimx_player']            = $playerName;
    $_SESSION['dimx_world']             = $worldId;
    $_SESSION['dimx_authenticated']     = true;
    $_SESSION['dimx_numeric_id']        = $numericId;
    $_SESSION['dimx_is_bot']            = $isBot;
    $_SESSION['dimx_world_reset_token'] = $activeResetToken;
    if ($ui->getPlayer() instanceof Dimx\Player) {
        $_SESSION['dimx_engine_player_id'] = $ui->getPlayer()->id;
    }
    exit;
}

// 8. Process player command inside concurrency critical section
$result = $engine->processCommand(
    $playerName,
    $input['cmd'],
    $input['arg0'],
    $input['arg1'],
    $ui,
    $numericId ? (int)$numericId : null,
    (bool)$isBot,
    $isNewLogin,
    $sessionEngineId,
    $activeSkin !== '' ? $activeSkin : null,
    $activeSounds,
    $activeMusic,
    $view !== '' ? $view : ($input['view'] ?? 'scene'),
    $input['raw'] ?? []
);

if (!empty($result['login_error'])) {
    $dateStr = date('Y-m-d H:i:s');
    $engine->log("{$dateStr} USER: {$playerName} | STATUS: DISCONNECTED (REFUSAL: {$result['login_error']})");
    unset(
        $_SESSION['dimx_player'],
        $_SESSION['dimx_authenticated'],
        $_SESSION['dimx_numeric_id'],
        $_SESSION['dimx_is_bot'],
        $_SESSION['dimx_engine_player_id']
    );
    if ($ui instanceof UIhtml) {
        $ui->renderLogin($result['login_error'], $requestedUser, $activeSkin, $ui->hasSounds(), $ui->hasMusic());
    } else {
        $ui->renderLogin($result['login_error'], $requestedUser, $activeSkin);
    }
    exit;
}

$result['is_new_login'] = (bool)$isNewLogin;

$_SESSION['dimx_player']            = $playerName;
$_SESSION['dimx_world']             = $worldId;
$_SESSION['dimx_authenticated']     = true;
$_SESSION['dimx_numeric_id']        = $numericId;
$_SESSION['dimx_is_bot']            = $isBot;
$_SESSION['dimx_world_reset_token'] = $activeResetToken;

// Store the active in-memory player ID in session
if (!empty($result['player']) && $result['player'] instanceof Dimx\Player) {
    $_SESSION['dimx_engine_player_id'] = $result['player']->id;
}

// 9. If player logged out or world was reset, clear active session
if (!empty($result['logged_out']) || !empty($result['reset'])) {
    unset(
        $_SESSION['dimx_player'],
        $_SESSION['dimx_authenticated'],
        $_SESSION['dimx_numeric_id'],
        $_SESSION['dimx_is_bot'],
        $_SESSION['dimx_engine_player_id'],
        $_SESSION['dimx_skin'],
        $_SESSION['dimx_html_tolerant']
    );
}

// If view is msgs, return JSON payload immediately matching DimensioneX/chat poll format
if ($view === 'msgs') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $response = [
        'ok'       => true,
        'messages' => $result['chat'] ?? [],
    ];
    if (!empty($result['commands'])) {
        $response['commands'] = $result['commands'];
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// 10. Attach UI to player and return outcome via the player's UI
$player = $result['player'] ?? null;
if ($player !== null) {
    if (isset($_REQUEST['width']) && (int)$_REQUEST['width'] > 0) {
        $_SESSION['dimx_screenwidth'] = (int)$_REQUEST['width'];
    }
    if (isset($_REQUEST['height']) && (int)$_REQUEST['height'] > 0) {
        $_SESSION['dimx_screenheight'] = (int)$_REQUEST['height'];
    }
    if (isset($_REQUEST['screensize']) && preg_match('/^(\d+)x(\d+)$/i', (string)$_REQUEST['screensize'], $sm)) {
        $_SESSION['dimx_screenwidth'] = (int)$sm[1];
        $_SESSION['dimx_screenheight'] = (int)$sm[2];
    }
    if (isset($_SESSION['dimx_screenwidth'])) {
        $player->screenWidth = (int)$_SESSION['dimx_screenwidth'];
    }
    if (isset($_SESSION['dimx_screenheight'])) {
        $player->screenHeight = (int)$_SESSION['dimx_screenheight'];
    }
    $player->isPortrait = ($player->screenWidth > 0 && $player->screenHeight > 0 && $player->screenWidth < $player->screenHeight);
    $player->setUI($ui);
    $ui->setPlayer($player);
}

$ui->render($result);
