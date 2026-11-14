<?php
declare(strict_types=1);

/**
 * test_multiplayer.php
 * Default / core automated test suite for DimensioneY.
 * (For the extended full test suite including world3net-specific tests, see test_multiplayer_full.php)
 *
 * Automated tests for UI hierarchy, GameEngine, UIhtml, UIjson bot API endpoint,
 * player database storage, auto-increment numeric ID, and bcrypt password authentication.
 */

require_once __DIR__ . '/autoload.php';

if (!defined('DIMX_TESTING')) {
    define('DIMX_TESTING', true);
}

use Dimx\UI;
use Dimx\UIhtml;
use Dimx\UIjson;
use Dimx\UIbot;
use Dimx\GameEngine;
use Dimx\PersistenceManager;
use Dimx\Player;

$sections = [
    1  => ['title' => 'Testing UI::detectClientType()...', 'keywords' => ['client', 'detect', 'json', 'format', 'headers', 'ui::detectclienttype']],
    2  => ['title' => 'Testing UI::parseInput()...', 'keywords' => ['input', 'parse', 'password', 'cmd', 'param', 'world', 'ui::parseinput']],
    3  => ['title' => 'Testing GameEngine initialization and world seeding...', 'keywords' => ['seed', 'engine', 'rooms', 'beach', 'bar', 'apple', 'gameengine', 'loadorseedworld']],
    4  => ['title' => 'Testing Player DB Table, Auto-Increment Numeric ID & Bcrypt Passwords...', 'keywords' => ['auth', 'login', 'db', 'player', 'bcrypt', 'password', 'numeric', 'id', 'dimx_players', 'in-memory']],
    5  => ['title' => 'Testing Bot Player via UIjson API...', 'keywords' => ['bot', 'uijson', 'snapshot', 'inventory', 'carrying', 'pick', 'drop', 'apple', 'save', 'exit', 'savexit']],
    6  => ['title' => 'Testing multiplayer.php entrypoint with bot JSON query...', 'keywords' => ['multiplayer', 'bot', 'json', 'entrypoint', 'login_required', 'numeric_id']],
    7  => ['title' => 'Testing multiplayer.php entrypoint with human HTML player...', 'keywords' => ['multiplayer', 'html', 'human', 'entrypoint', 'uihtml', 'score', 'stats']],
    8  => ['title' => 'Testing login prompts and form fields...', 'keywords' => ['login', 'prompt', 'fields', 'form', 'bot', 'html', 'auth']],
    9  => ['title' => 'Testing Bot Logout & Occupant Removal...', 'keywords' => ['logout', 'disconnect', 'occupants', 'bot', 'room', 'removal']],
    10 => ['title' => 'Testing world2.dxw Description Parsing...', 'keywords' => ['world2', 'dxw', 'loader', 'apostrophe', 'description', 'parsing']],
    11 => ['title' => 'Testing onStart Event & Chat PANEL Enforcement...', 'keywords' => ['onstart', 'event', 'panel', 'chat', 'commands', 'messages', 'setpanel']],
    12 => ['title' => 'Testing UIbot vs UIjson and testBots Configuration...', 'keywords' => ['uibot', 'uijson', 'testbots', 'config', 'fallback', 'evalaction']],
    13 => ['title' => 'Testing adminPasswd in config and maint.php authentication...', 'keywords' => ['admin', 'adminpasswd', 'maint', 'maintenance', 'config', 'auth', 'uihtml', 'title', 'console']],
    14 => ['title' => 'Testing Event Model Roles ($OWNER, $AGENT, $TARGET, $WORLD) & Return Values...', 'keywords' => ['event', 'roles', '$owner', '$agent', '$target', '$world', 'return', 'cancel', 'action', 'owner', 'agent', 'target']],
    15 => ['title' => 'Testing UIhtml Ctrls (#ctrls) Command Buttons & On-Screen Object Targeting...', 'keywords' => ['uihtml', 'ctrls', 'buttons', 'onscreen', 'targeting', 'switch', 'jquery', 'command_defs', 'clickobject', 'identifyobject']],
    16 => ['title' => "Testing 'open' and 'close' Commands & actOpen / actClose on 'box' in World 2...", 'keywords' => ['open', 'close', 'box', 'actopen', 'actclose', 'world2', 'cardboard', 'actionsrunner']],
    17 => ['title' => 'Testing World 2 Full Flow: Look at Closed Box, Use Switch in Bungalow, Open Box & Pick Apple...', 'keywords' => ['flow', 'box', 'switch', 'bungalow', 'open', 'pick', 'apple', 'goal', 'uibot', 'evaldescription', 'is closed', 'is open', 'containing', 'rocca', 'spiaggia']],
    18 => ['title' => 'Testing Speak Instruction Behavior & Inanimate Object Fallback...', 'keywords' => ['speak', 'surfer', 'switch', 'voice', 'system', 'sound', 'sys', 'fallback', 'inanimate', 'bungalow']],
    19 => ['title' => 'Testing UIhtml Smartphone Portrait / Vertical Layout (*, 35%, 15%)...', 'keywords' => ['portrait', 'vertical', 'smartphone', 'uihtml', 'layout', '35%', '15%']],
    20 => ['title' => 'Testing Messages Box (Msgs / #msgs), popmsg Command & Per-Player Incoming Message Queue...', 'keywords' => ['msgs', 'popmsg', 'messages', 'queue', 'trim', '10', 'dismiss', 'x', 'incoming', 'speak', 'broadcast']],
    22 => ['title' => 'Testing Character/Player Default showMode (ONSCREEN=1), UIjson vs UIbot Exposure & UIhtml Rendering...', 'keywords' => ['showmode', 'onscreen', 'offscreen', 'uijson', 'uibot', 'uihtml', 'character', 'player', 'avatar', 'people']],
    24 => ['title' => 'Testing Room Description on Link Traversal (go command) & UIhtml Rendering...', 'keywords' => ['go', 'traverse', 'link', 'description', 'room', 'uihtml', 'travel to']],
    47 => ['title' => 'Testing SmallBasic Debug Instruction & GameEngine::log() Writing to debug.log...', 'keywords' => ['debug', 'log', 'debug.log', 'gameengine', 'actionsrunner', 'system folder', 'smallbasic']],
    48 => ['title' => 'Testing Resolution Handling & Login Form Cleanliness (no screenSizeHelper, non-portrait 640x480/800x600)...', 'keywords' => ['screensize', 'landscape', 'resolution', 'portrait', 'uihtml', 'login']],
    49 => ['title' => 'Testing NewImage Function, ImagesFolder Resolution & Image Display/Assignment in SmallBasic...', 'keywords' => ['newimage', 'image', 'smallbasic', 'actionsrunner', 'display', 'imagesfolder']],
    50 => ['title' => 'Testing Item Default SHOW_OFFSCREEN (0), Zoom on Click/Look & Fader Backdrop Overlay...', 'keywords' => ['zoom', 'fader', 'showmode', 'offscreen', 'focus', 'click', 'look', 'screwdriver', 'fader_box']],
    51 => ['title' => 'Testing SmallBasic DropItems Instruction & Notification (Player, NPC, Container)...', 'keywords' => ['dropitems', 'actionsrunner', 'inventory', 'drop', 'slipped']],
    52 => ['title' => 'Testing Put / Insert Command (2-Object OO Model, Capacity, Closed Checks, Events & UIhtml Definitions)...', 'keywords' => ['put', 'insert', 'actput', 'itemput', 'container', 'capacity', 'closed', 'uihtml', 'command_defs']],
    53 => ['title' => 'Testing SmallBasic NewItem Function / Instruction & Snapshot Property Recursion Protection...', 'keywords' => ['newitem', 'htmladminsnapshot', 'snapshot', 'recursion', 'actionsrunner', 'smallbasic']],
    54 => ['title' => 'Testing SmallBasic SetLen() Function, For .. To ... Next & For Each Constructs with debug.log...', 'keywords' => ['setlen', 'for', 'to', 'next', 'foreach', 'for each', 'loop', 'fourinline', 'smallbasic']],
    55 => ['title' => 'Testing Function & Sub Logging (Calling FUNCTION/SUB with varspace) & Indentation (incIndent/decIndent)...', 'keywords' => ['calling function', 'calling', 'indent', 'incindent', 'decindent', 'indentlevel', 'varspace', 'nested']],
    56 => ['title' => 'Testing If..Else Construct Logging (IF ..., TRUE/FALSE, nested running & IF executed)...', 'keywords' => ['if', 'else', 'endif', 'executed', 'running', 'true', 'false', 'fourinline', 'onlook']],
    57 => ['title' => 'Testing Request Interaction Logging (USER, ROOM, STATUS, COMMAND, VIEW, DATE & input=...)', 'keywords' => ['user', 'room', 'status', 'command', 'view', 'date', 'interaction', 'request']],
    58 => ['title' => 'Testing SmallBasic Reset Instruction & GameEngine::resetWorld()...', 'keywords' => ['reset', 'restart', 'smallbasic', 'actionsrunner', 'gameengine', 'reseeding']],
    59 => ['title' => 'Testing SmallBasic Array String Representation [key1=val,key2=val,...] & fourinline.dxw line 204...', 'keywords' => ['array', 'formatarray', 'smallbasic', 'fourinline', 'speak', 'representation', 'actionsrunner']],
    60 => ['title' => 'Testing WORLD Directives (NAME, VERSION, AUTHOR, SITE, GUI.LOGOSRC) & Login Form Layering...', 'keywords' => ['world', 'directives', 'author', 'site', 'version', 'logosrc', 'gui.logosrc', 'login', 'layers', 'background']],
    61 => ['title' => "Testing Who's Online - The players view (multiplayer.php?view=players & format=extended)...", 'keywords' => ['view', 'players', 'view=players', 'format=extended', 'extended', 'online', 'who\'s online']],
    62 => ['title' => 'Testing Player Inactivity Timeout (30s) at Tick Event & Nickname Conflict Check at Login...', 'keywords' => ['timeout', 'inactivity', 'tick', 'kill', 'lastactive', 'lastinteraction', 'nickname', 'conflict', 'login']],
    63 => ['title' => 'Testing PeopleContainer Object Entry & Scene Refresh Dispatch with Deduplication...', 'keywords' => ['refresh', 'scene', 'refresh!scene', 'peoplecontainer', 'room', 'dedup', 'commands', 'entry', 'queue']],
    64 => ['title' => 'Testing SmallBasic Goal Instruction & Database Recording in dimx_goals...', 'keywords' => ['goal', 'smallbasic', 'dimx_goals', 'hall of fame', 'hof', 'mode', 'actionsrunner', 'surfer']],
    65 => ['title' => 'Testing Login Attempt Logging to debug.log & Stale Player Pruning Inside Main Loop at Login...', 'keywords' => ['login', 'stale', 'pruning', 'tick', 'debug.log', 'main loop', 'reconnect']],
    66 => ['title' => 'Testing UIhtml Sound & Music Support Property Storage, Snapshot Inspection & Audio Rendering...', 'keywords' => ['sound', 'music', 'audiosupport', 'musicsupport', 'uihtml', 'snapshot', 'playbackground', 'session']],
    67 => ['title' => 'Testing SmallBasic getItemsIn Function (Objects, Sets, Arrays, $WORLD, $AGENT & loc_relic_vault in world.dxw)...', 'keywords' => ['getitemsin', 'items', 'smallbasic', 'world.dxw', 'loc_relic_vault', 'inventory', 'actionsrunner']],
    68 => ['title' => 'Testing SmallBasic SetContainsKey Function (Sets, Dictionaries, Arrays, Case-Insensitivity, Objects, & loc_relic_vault in world.dxw)...', 'keywords' => ['setcontainskey', 'smallbasic', 'set', 'contains', 'talisman', 'loc_relic_vault', 'world.dxw', 'actionsrunner']],
    69 => ['title' => 'Testing SmallBasic Expression Error Handling & Custom Functions/Subs (getObjectsType, containsType in world.dxw)...', 'keywords' => ['syntax', 'error', 'function', 'sub', 'custom', 'containstype', 'getobjectstype', 'world.dxw', 'actionsrunner']],
    70 => ['title' => 'Testing Character::give() Standard DimX Events (whenPicked, onReceiveItem, onLooseItem, No whenGiven/whenReceived)...', 'keywords' => ['give', 'whenpicked', 'onreceiveitem', 'onlooseitem', 'whengiven', 'whenreceived', 'itemgive', 'character']],
];

// CLI Options parsing
$opts = getopt('s:f:lh', ['section:', 'filter:', 'list', 'help']);

if (isset($opts['h']) || isset($opts['help'])) {
    echo "DimensioneX Multiplayer, Auth & UI Test Suite\n\n";
    echo "Usage:\n";
    echo "  php test_multiplayer.php [options]\n\n";
    echo "Options:\n";
    echo "  -s, --section=<val>   Run only specified section(s).\n";
    echo "                        Examples: --section=18, --section=1,2,5, --section=15-18\n";
    echo "  -f, --filter=<str>    Run only sections matching substring or keywords.\n";
    echo "                        Examples: --filter=Speak, --filter=open, --filter=auth\n";
    echo "  -l, --list            List all available test sections and exit.\n";
    echo "  -h, --help            Show this help screen.\n";
    exit(0);
}

if (isset($opts['l']) || isset($opts['list'])) {
    echo "=== Available Test Sections ===\n";
    foreach ($sections as $num => $sec) {
        $cleanTitle = trim($sec['title'], " .\n");
        printf("  %2d. %s\n", $num, $cleanTitle);
    }
    echo "\nRun a section with: php test_multiplayer.php --section=<number>\n";
    echo "Or filter with:     php test_multiplayer.php --filter=<keyword>\n";
    exit(0);
}

$rawSection = $opts['section'] ?? $opts['s'] ?? null;
$rawFilter  = $opts['filter'] ?? $opts['f'] ?? null;

$selectedSections = null;
if ($rawSection !== null) {
    $selectedSections = [];
    foreach (explode(',', (string)$rawSection) as $part) {
        $part = trim($part);
        if (str_contains($part, '-')) {
            [$start, $end] = explode('-', $part, 2);
            for ($i = (int)$start; $i <= (int)$end; $i++) {
                $selectedSections[] = $i;
            }
        } elseif (is_numeric($part)) {
            $selectedSections[] = (int)$part;
        }
    }
}

$executedSections = [];
function shouldRun(int $num): bool {
    global $sections, $selectedSections, $rawFilter, $executedSections;
    if ($selectedSections !== null && !in_array($num, $selectedSections, true)) {
        return false;
    }
    if ($rawFilter !== null && $rawFilter !== '') {
        $needle = strtolower((string)$rawFilter);
        $sec = $sections[$num] ?? null;
        if (!$sec) return false;
        $matched = false;
        if (str_contains(strtolower($sec['title']), $needle)) {
            $matched = true;
        } else {
            foreach ($sec['keywords'] as $kw) {
                if (str_contains(strtolower($kw), $needle)) {
                    $matched = true;
                    break;
                }
            }
        }
        if (!$matched) return false;
    }
    $executedSections[] = $num;
    return true;
}

// Check if any section matches
$anyWillRun = false;
foreach (array_keys($sections) as $num) {
    if ($selectedSections !== null && !in_array($num, $selectedSections, true)) continue;
    if ($rawFilter !== null && $rawFilter !== '') {
        $needle = strtolower((string)$rawFilter);
        $sec = $sections[$num];
        if (!str_contains(strtolower($sec['title']), $needle)) {
            $kwMatch = false;
            foreach ($sec['keywords'] as $kw) {
                if (str_contains(strtolower($kw), $needle)) { $kwMatch = true; break; }
            }
            if (!$kwMatch) continue;
        }
    }
    $anyWillRun = true;
    break;
}
if (!$anyWillRun) {
    echo "No test sections matched criteria: " . ($rawSection ? "--section={$rawSection} " : "") . ($rawFilter ? "--filter={$rawFilter}" : "") . "\n";
    echo "Use --list to view all available sections.\n";
    exit(0);
}

echo "=== Running DimensioneX Multiplayer, Auth & UI Tests ===\n";
if ($selectedSections !== null) {
    echo "  [Filtered by Section(s): " . implode(', ', $selectedSections) . "]\n";
}
if ($rawFilter !== null && $rawFilter !== '') {
    echo "  [Filtered by Keyword: '{$rawFilter}']\n";
}
echo "\n";

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $title): void
{
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$title}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$title}\n";
        $failCount++;
    }
}

function runMultiplayer(array $get = [], array $post = [], array $server = [], array $session = []): string
{
    if (!isset($server['DIMX_WORLD_FILE'])) {
        $server['DIMX_WORLD_FILE'] = __DIR__ . '/../docs/dxw-examples/world2.dxw';
    }
    if (!isset($server['DIMX_WORLD_ID'])) {
        $server['DIMX_WORLD_ID'] = 'test_unit_w2';
    }
    $runner = sys_get_temp_dir() . '/dimx_run_' . uniqid() . '.php';
    $sessionCode = '';
    if (!empty($session)) {
        $sessionCode = "@session_start();\n"
            . "\$_SESSION = " . var_export($session, true) . ";\n";
    }
    $code = "<?php\n"
        . $sessionCode
        . '$_GET = ' . var_export($get, true) . ";\n"
        . '$_POST = ' . var_export($post, true) . ";\n";
    foreach ($server as $k => $v) {
        $code .= '$_SERVER[' . var_export($k, true) . '] = ' . var_export($v, true) . ";\n";
    }
    $code .= "require " . var_export(__DIR__ . '/multiplayer.php', true) . ";\n";
    file_put_contents($runner, $code);

    $cmd = 'C:\\xampp\\php\\php.exe ' . escapeshellarg($runner);
    $output = (string)shell_exec($cmd);
    @unlink($runner);
    return $output;
}

function runChat(array $get = [], array $post = [], array $server = []): string
{
    if (!isset($get['view'])) {
        $get['view'] = 'msgs';
    }
    if (!isset($get['username']) && isset($get['player'])) {
        $get['username'] = $get['player'];
    }
    if (!isset($server['DIMX_WORLD_ID']) && isset($get['world_id'])) {
        $server['DIMX_WORLD_ID'] = (string)$get['world_id'];
    }
    return runMultiplayer($get, $post, $server);
}

// Common test fixtures available to all sections
$config      = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
$dbConfig    = $config['db'] ?? [];
$baseUrl     = $config['base_url'] ?? '';
$pm          = new PersistenceManager($dbConfig, __DIR__ . '/data');
$unitWorldId = 'test_unit_' . uniqid();
$engine      = new GameEngine($pm, $unitWorldId, null, $baseUrl);
$world       = $engine->loadOrSeedWorld();
$dxwFile     = dirname(__DIR__) . '/docs/dxw-examples/world2.dxw';
$world2Path  = $dxwFile;
$loader      = new Dimx\DxwLoader('http://localhost');
$world2      = $loader->load($world2Path);
$w2          = $world2;
$testWorldId = 'test_w2_' . rand(1000, 9999);
$engineW2    = new Dimx\GameEngine($pm, $testWorldId, $world2Path);
$seededWorld = $engineW2->loadOrSeedWorld();
$uiJson      = new UIjson();
$uiBot       = new UIbot();

// -------------------------------------------------------------
// Test 1: Client Detection
// -------------------------------------------------------------
if (shouldRun(1)) {
    echo "1. Testing UI::detectClientType()...\n";

    $_GET = ['format' => 'json'];
    assertTest(UI::detectClientType() === 'json', "Detects ?format=json as 'json'");

    $_GET = ['ui' => 'json'];
    assertTest(UI::detectClientType() === 'json', "Detects ?ui=json as 'json'");

    $_GET = ['bot' => '1'];
    assertTest(UI::detectClientType() === 'json', "Detects ?bot=1 as 'json'");

    $_GET = ['api' => '1'];
    assertTest(UI::detectClientType() === 'json', "Detects ?api=1 as 'json'");

    $_GET = [];
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    assertTest(UI::detectClientType() === 'json', "Detects Content-Type: application/json as 'json'");

    unset($_SERVER['CONTENT_TYPE']);
    $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml';
    assertTest(UI::detectClientType() === 'html', "Detects browser Accept header as 'html'");
}

// -------------------------------------------------------------
// Test 2: Input Parsing (Single World enforcement & Password)
// -------------------------------------------------------------
if (shouldRun(2)) {
    echo "\n2. Testing UI::parseInput()...\n";
    $_GET = ['cmd' => 'go', 'dir' => 'N', 'player' => 'Explorer1', 'password' => 'secret123', 'world' => 'arbitrary_world'];
    $input = UI::parseInput();
    assertTest($input['username'] === 'Explorer1', "Parsed username 'Explorer1'");
    assertTest($input['password'] === 'secret123', "Parsed password 'secret123'");
    assertTest($input['cmd'] === 'go', "Parsed cmd 'go'");
    assertTest($input['param'] === 'N', "Parsed param 'N' from 'dir'");
    assertTest($input['arg0'] === 'N', "Parsed arg0 'N' from 'dir'");
    assertTest(!isset($input['world']) && !isset($input['world_file']), "World switching parameters are ignored (single world)");

    // Test arg0 and arg1 resolution & single parameter normalization
    $_GET = ['cmd' => 'click', 'arg0' => 'key1'];
    $inClick = UI::parseInput();
    assertTest($inClick['arg0'] === 'key1' && $inClick['arg1'] === '', "Single arg0 parameter is filled and arg1 is empty");

    // Legacy target single parameter shifted to arg0
    $_GET = ['cmd' => 'click', 'target' => 'door1'];
    $inLegacyTarget = UI::parseInput();
    assertTest($inLegacyTarget['arg0'] === 'door1' && $inLegacyTarget['arg1'] === '', "Single legacy target parameter shifted into arg0");

    // Two parameters passed (e.g. usewith key1 on door1)
    $_GET = ['cmd' => 'usewith', 'arg0' => 'key1', 'arg1' => 'door1'];
    $inTwoArgs = UI::parseInput();
    assertTest($inTwoArgs['arg0'] === 'key1' && $inTwoArgs['arg1'] === 'door1', "Two arguments populate arg0 and arg1");

    // txtBox quick speech sets cmd='say', arg0=text, arg1='*'
    $_GET = [];
    $_POST = ['txtBox' => 'Hello adventurer'];
    $inSpeech = UI::parseInput();
    assertTest($inSpeech['cmd'] === 'say' && $inSpeech['arg0'] === 'Hello adventurer' && $inSpeech['arg1'] === '*', "txtBox quick speech populates cmd=say, arg0=speech and arg1=*");
    $_POST = [];
}

// -------------------------------------------------------------
// Test 3: GameEngine Setup & Core Processing
// -------------------------------------------------------------
if (shouldRun(3)) {
    echo "\n3. Testing GameEngine initialization and world seeding...\n";
    $config   = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
    $dbConfig = $config['db'] ?? [];
    $baseUrl  = $config['base_url'] ?? '';
    $pm       = new PersistenceManager($dbConfig, __DIR__ . '/data');

    $unitWorldId = 'test_unit_' . uniqid();
    $engine = new GameEngine($pm, $unitWorldId, null, $baseUrl);

    $world = $engine->loadOrSeedWorld();
    assertTest($world !== null, "Engine seeded world successfully");
    assertTest(isset($world->rooms['beach']), "Seeded world contains 'beach' room");
    assertTest(isset($world->rooms['bar']), "Seeded world contains 'bar' room");
    assertTest(isset($world->items['apple']), "Seeded world contains 'apple' item");
}

// -------------------------------------------------------------
// Test 4: Database Player Accounts & Password Encryption
// -------------------------------------------------------------
if (shouldRun(4)) {
    echo "\n4. Testing Player DB Table, Auto-Increment Numeric ID & Bcrypt Passwords...\n";
$testUser = 'AuthTester_' . rand(1000, 9999);
$testPass = 'p@ssw0rdSecure!';

// Step 4a: Account creation
$auth1 = $pm->authenticateOrCreatePlayer($unitWorldId, $testUser, $testPass, true);
assertTest($auth1['success'] === true, "New player account successfully registered");
assertTest(($auth1['created'] ?? false) === true, "Flags account as newly created");
assertTest(!empty($auth1['numeric_id']) && is_numeric($auth1['numeric_id']), "Account assigned numeric ID: " . ($auth1['numeric_id'] ?? 0));
assertTest(($auth1['is_bot'] ?? false) === true, "Bot player flag stored as true");

// Step 4b: Verify password in DB is encrypted (one-way bcrypt hash, not plaintext)
$pdo = $pm->getPdo();
if ($pdo !== null) {
    $stmt = $pdo->prepare("SELECT id, name, password, is_bot FROM dimx_players WHERE world_id = :wid AND LOWER(name) = :pname");
    $stmt->execute([':wid' => $unitWorldId, ':pname' => strtolower($testUser)]);
    $dbRow = $stmt->fetch();
    assertTest($dbRow !== false, "Player record exists in dimx_players table");
    assertTest($dbRow['name'] === $testUser, "Nickname is clearly readable: " . $dbRow['name']);
    assertTest($dbRow['password'] !== $testPass, "Password is NOT stored in plaintext");
    assertTest(str_starts_with($dbRow['password'], '$2y$') || strlen($dbRow['password']) >= 60, "Password encoded with bcrypt hash: " . substr($dbRow['password'], 0, 10) . '...');
    assertTest((int)$dbRow['id'] === (int)$auth1['numeric_id'], "Database numeric ID matches returned ID: " . $dbRow['id']);
}

// Step 4c: Existing account login with correct password
$auth2 = $pm->authenticateOrCreatePlayer($unitWorldId, $testUser, $testPass, true);
assertTest($auth2['success'] === true, "Existing account authenticated with correct password");
assertTest(($auth2['created'] ?? false) === false, "Existing account recognized (created = false)");
assertTest((int)$auth2['numeric_id'] === (int)$auth1['numeric_id'], "Numeric ID consistent across logins");

// Step 4d: Existing account login with wrong password
$auth3 = $pm->authenticateOrCreatePlayer($unitWorldId, $testUser, 'wrong_password_here', true);
assertTest($auth3['success'] === false, "Login rejected with incorrect password");
assertTest(($auth3['code'] ?? '') === 'invalid_password', "Error code is 'invalid_password'");

// Step 4e: Verify in-memory player ID changes every time the user logs in
echo "\n  Testing that in-memory player ID changes on every login...\n";
$uiJsonTemp = new UIjson();
$resLogin1  = $engine->processCommand($testUser, 'login', '', '', $uiJsonTemp, (int)$auth1['numeric_id'], true, true);
$engineId1  = $resLogin1['player']->id;
assertTest(!empty($engineId1), "Player assigned in-memory ID on login 1: {$engineId1}");
assertTest($engineId1 !== (string)$auth1['numeric_id'], "In-memory ID ({$engineId1}) is NOT the database numeric ID (" . $auth1['numeric_id'] . ")");
assertTest($engineId1 !== $testUser, "In-memory ID ({$engineId1}) is NOT the player nickname ({$testUser})");
assertTest((int)$resLogin1['player']->numericId === (int)$auth1['numeric_id'], "Player numericId matches database ID: " . $auth1['numeric_id']);

// Simulate player logging out
$engine->processCommand($testUser, 'logout', '', '', $uiJsonTemp, (int)$auth1['numeric_id'], true, false, $engineId1);
assertTest($world->getPlayer($engineId1) === null, "Player {$engineId1} cleanly removed from world memory on logout");

// Second login for the SAME user account
$resLogin2 = $engine->processCommand($testUser, 'login', '', '', $uiJsonTemp, (int)$auth1['numeric_id'], true, true);
$engineId2 = $resLogin2['player']->id;
assertTest(!empty($engineId2), "Player assigned in-memory ID on login 2: {$engineId2}");
assertTest($engineId1 !== $engineId2, "In-memory ID CHANGED on second login ({$engineId1} != {$engineId2})");
assertTest((int)$resLogin2['player']->numericId === (int)$auth1['numeric_id'], "Database numeric ID remains unchanged (" . $auth1['numeric_id'] . ")");
assertTest($resLogin2['player']->name === $testUser, "Player nickname remains unchanged ({$testUser})");
}

// -------------------------------------------------------------
// Test 5: Bot Player Command Dispatch via UIjson API
// -------------------------------------------------------------
if (shouldRun(5)) {
    echo "\n5. Testing Bot Player via UIjson API...\n";
$uiJson = new UIjson();
$botName = 'BotAction_' . rand(100, 999);
$botAuth = $pm->authenticateOrCreatePlayer($unitWorldId, $botName, 'botPass123', true);

// Step 5a: Login & initial look
$result1 = $engine->processCommand($botName, 'look', '', '', $uiJson, (int)$botAuth['numeric_id'], true, true);
$player = $result1['player'];
assertTest($player instanceof Player, "Bot player created as Player instance");
assertTest($player->getUI() === $uiJson, "Player has UI property set to UIjson instance");
assertTest($player->numericId === (int)$botAuth['numeric_id'], "Player instance has numericId populated");
assertTest($player->isBot === true, "Player instance has isBot flagged as true");
assertTest($player->getRoom()->id === 'beach', "Bot player starts in 'beach' room");

// Verify room snapshot structure
$snapshot = $uiJson->buildRoomSnapshot($player->getRoom(), $player);
assertTest($snapshot['id'] === 'beach', "Room snapshot ID is 'beach'");
assertTest(!empty($snapshot['description']), "Room snapshot contains description");
assertTest(count($snapshot['items']) === 1 && $snapshot['items'][0]['id'] === 'apple', "Room snapshot contains 'apple' item");
assertTest(count($snapshot['exits']) === 1 && $snapshot['exits'][0]['direction'] === 'E', "Room snapshot contains 'E' exit");

// Step 5b: Bot picks up apple
$result2 = $engine->processCommand($botName, 'pick', '', 'apple', $uiJson);
assertTest(isset($result2['player']->contents['apple']), "Bot player now has 'apple' in inventory");
assertTest(!isset($result2['room']->contents['apple']), "'apple' removed from beach room contents");

$playerStatus = $uiJson->buildPlayerStatus($result2['player']);
assertTest($playerStatus['carrying_count'] === 1, "Player status carrying_count is 1");
assertTest($playerStatus['inventory'][0]['id'] === 'apple', "Player status inventory lists 'apple'");
assertTest($playerStatus['numeric_id'] === (int)$botAuth['numeric_id'], "Player status contains numeric_id");
assertTest($playerStatus['is_bot'] === true, "Player status contains is_bot: true");

// Step 5c: Bot travels East to Tiki Bar
$result3 = $engine->processCommand($botName, 'go', 'E', '', $uiJson);
assertTest($result3['player']->getRoom()->id === 'bar', "Bot travelled East and is now in 'bar'");

// Step 5d: Bot drops apple in Tiki Bar
$result4 = $engine->processCommand($botName, 'drop', '', 'apple', $uiJson);
assertTest(!isset($result4['player']->contents['apple']), "Bot dropped 'apple', no longer in inventory");
assertTest(isset($result4['room']->contents['apple']), "'apple' is now inside 'bar' room contents");

// Step 5e: Bot talks
$result5 = $engine->processCommand($botName, 'say', 'Greetings from AI bot!', '', $uiJson);
assertTest(in_array("<b>{$botName}</b> says: Greetings from AI bot!", $result5['messages'], true), "Bot speech captured in messages");

// Step 5f: 'save' command saves player profile without exiting
$resultSave = $engine->processCommand($botName, 'save', '', '', $uiJson);
assertTest(in_array("Game saved!", $resultSave['messages'], true), "Save command returns 'Game saved!' confirmation");
assertTest(($resultSave['logged_out'] ?? false) === false, "Save command does NOT logout player");

// Step 5g: 'logout' command logs out player
$resultLogout = $engine->processCommand($botName, 'logout', '', '', $uiJson);
assertTest(($resultLogout['logged_out'] ?? false) === true, "Logout command sets logged_out = true");
assertTest($world->getPlayer($resultLogout['player']->id) === null, "Player cleanly removed on logout");

// Step 5h: 'savexit' command is removed completely
$resultSavexit = $engine->processCommand($botName, 'savexit', '', '', $uiJson);
assertTest(($resultSavexit['logged_out'] ?? false) === false, "Legacy 'savexit' does NOT trigger logout");
assertTest(in_array("Unknown command: savexit", $resultSavexit['messages'], true), "Legacy 'savexit' is treated as unknown command");

// Step 5i: Panel buttons verify 'save' & 'logout' replace 'savexit'
$defaultPanel = Dimx\Panel::createDefaultPanel();
assertTest($defaultPanel->getButton('save') !== null, "Default panel includes 'save' button");
assertTest($defaultPanel->getButton('logout') !== null, "Default panel includes 'logout' button");
assertTest($defaultPanel->getButton('logout')?->caption === 'Disconnect', "Default panel 'logout' button caption is 'Disconnect'");
assertTest($defaultPanel->getButton('savexit') === null, "Default panel does NOT include legacy 'savexit' button");

$pm->resetWorld($unitWorldId);
}

// -------------------------------------------------------------
// Test 6: Simulating multiplayer.php entrypoint for Bot (JSON)
// -------------------------------------------------------------
if (shouldRun(6)) {
    echo "\n6. Testing multiplayer.php entrypoint with bot JSON query...\n";

// 6a: Bot without password should receive 401 login_required
$jsonOutputNoPass = runMultiplayer([
    'format'   => 'json',
    'username' => 'DirectApiBot',
    'cmd'      => 'look',
]);
$decodedNoPass = json_decode($jsonOutputNoPass, true);
assertTest(($decodedNoPass['status'] ?? '') === 'login_required', "Bot without password gets 'login_required'");
assertTest(str_contains($decodedNoPass['error'] ?? '', 'Password is required'), "Bot error indicates password is required");

// 6b: Test Bot (BotAlpha) with username and password should authenticate and return game snapshot via UIjson
$jsonOutput = runMultiplayer([
    'format'   => 'json',
    'username' => 'BotAlpha',
    'password' => 'test123',
    'cmd'      => 'look',
]);
$decoded = json_decode($jsonOutput, true);

assertTest(is_array($decoded), "multiplayer.php returned valid JSON");
assertTest(($decoded['success'] ?? false) === true, "JSON response indicates success: true");
assertTest(isset($decoded['room']['id']), "JSON response includes room snapshot");
assertTest(isset($decoded['player']['name']) && $decoded['player']['name'] === 'BotAlpha', "JSON response includes player name 'BotAlpha'");
assertTest(isset($decoded['player']['numeric_id']) && is_numeric($decoded['player']['numeric_id']), "JSON response includes numeric_id: " . ($decoded['player']['numeric_id'] ?? 0));
assertTest(($decoded['player']['is_bot'] ?? false) === true, "JSON response player has is_bot = true");

// 6c: Bot with wrong password on existing account
$jsonOutputWrongPass = runMultiplayer([
    'format'   => 'json',
    'username' => 'BotAlpha',
    'password' => 'wrongSecret!',
    'cmd'      => 'look',
]);
$decodedWrongPass = json_decode($jsonOutputWrongPass, true);
assertTest(($decodedWrongPass['status'] ?? '') === 'login_required', "Bot with incorrect password gets 'login_required'");
assertTest(str_contains($decodedWrongPass['error'] ?? '', 'Incorrect password'), "Bot error message indicates incorrect password");

// 6d: Bot logs in a second time in a new session: verify in-memory ID changes while numeric ID remains constant
$firstSessionEngineId = $decoded['player']['id'] ?? '';
$firstNumericId       = $decoded['player']['numeric_id'] ?? 0;

$jsonOutput2 = runMultiplayer([
    'format'   => 'json',
    'username' => 'BotAlpha',
    'password' => 'test123',
    'cmd'      => 'look',
]);
$decoded2 = json_decode($jsonOutput2, true);
$secondSessionEngineId = $decoded2['player']['id'] ?? '';
$secondNumericId       = $decoded2['player']['numeric_id'] ?? 0;

assertTest(!empty($firstSessionEngineId) && !empty($secondSessionEngineId), "Both bot logins returned in-memory player IDs");
assertTest($firstSessionEngineId !== $secondSessionEngineId, "In-memory player ID changed between logins ({$firstSessionEngineId} != {$secondSessionEngineId})");
assertTest((int)$firstNumericId === (int)$secondNumericId, "Database numeric ID stayed constant ({$firstNumericId})");
assertTest($firstSessionEngineId !== (string)$firstNumericId, "In-memory ID is NOT the database numeric ID");
}

// -------------------------------------------------------------
// Test 7: Simulating multiplayer.php entrypoint for Human (HTML)
// -------------------------------------------------------------
if (shouldRun(7)) {
    echo "\n7. Testing multiplayer.php entrypoint with human HTML player...\n";
$htmlOutput = runMultiplayer([
    'username' => 'HumanHero',
    'password' => 'heroPass789',
    'cmd'      => 'look',
    'reset'    => '1',
], [], ['HTTP_ACCEPT' => 'text/html']);

assertTest(str_contains($htmlOutput, '<!DOCTYPE html>'), "multiplayer.php returned HTML doctype");
assertTest(str_contains($htmlOutput, 'HumanHero'), "HTML output includes player name 'HumanHero'");
assertTest(str_contains($htmlOutput, '👤 <b>HumanHero</b>'), "Header displays human-readable player name 'HumanHero'");
assertTest(!str_contains($htmlOutput, '(Score:'), "Header does NOT display Score next to player name");
assertTest(!str_contains($htmlOutput, '💾'), "Header does NOT display storage diskette icon / method");
assertTest(!str_contains($htmlOutput, 'Carrying:'), "STATS box does NOT display Carrying 0/5");
assertTest(!preg_match('/👤\s*<b>p_\d+_[a-f0-9]+<\/b>/', $htmlOutput), "Header does NOT display internal engine player ID");
assertTest(str_contains($htmlOutput, 'Welcome to <b>World 2</b>, HumanHero!'), "Action console displays greeting with player nickname HumanHero");
assertTest(!preg_match('/\[p_\d+_[a-f0-9]+\]/', $htmlOutput), "HTML output does NOT expose internal player IDs in visible brackets");
assertTest(str_contains($htmlOutput, 'id="consoleBox"'), "HTML output includes consoleBox element");
assertTest(str_contains($htmlOutput, 'id="chatBox"'), "HTML output includes chatBox element");
assertTest(str_contains($htmlOutput, 'name="arg0"') && str_contains($htmlOutput, 'id="arg0Input"'), "UIhtml form includes arg0 input");
assertTest(str_contains($htmlOutput, 'name="arg1"') && str_contains($htmlOutput, 'id="arg1Input"'), "UIhtml form includes arg1 input");
assertTest(!str_contains($htmlOutput, 'id="paramInput"') && !str_contains($htmlOutput, 'id="targetInput"'), "UIhtml form does NOT include legacy paramInput or targetInput");
}

// -------------------------------------------------------------
// Test 8: Testing login prompt for Bot (JSON) and Human (HTML)
// -------------------------------------------------------------
if (shouldRun(8)) {
    echo "\n8. Testing login prompts and form fields...\n";

// HTML login prompt without username
$htmlLoginPrompt = runMultiplayer([], [], ['HTTP_ACCEPT' => 'text/html']);
assertTest(str_contains($htmlLoginPrompt, 'Enter the World'), "multiplayer.php HTML prompt includes 'Enter the World' form");
assertTest(str_contains($htmlLoginPrompt, 'name="username"'), "multiplayer.php HTML prompt includes username input");
assertTest(str_contains($htmlLoginPrompt, 'name="password"'), "multiplayer.php HTML prompt includes password input");
assertTest(str_contains($htmlLoginPrompt, 'type="password"'), "multiplayer.php password input is masked (type=password)");

// Direct UIhtml renderLogin test with error
ob_start();
$htmlUi = new UIhtml();
$htmlUi->renderLogin("Invalid password. Please try again.", "PlayerOne");
$loginHtml = ob_get_clean();
assertTest(str_contains($loginHtml, 'Invalid password. Please try again.'), "UIhtml renders error message");
assertTest(str_contains($loginHtml, 'value="PlayerOne"'), "UIhtml preserves default username on error");

// Direct UIjson renderLogin test
ob_start();
$jsonUi = new UIjson();
$jsonUi->renderLogin("Test auth error");
$loginJson = ob_get_clean();
$decodedLogin = json_decode($loginJson, true);
assertTest(($decodedLogin['status'] ?? '') === 'login_required', "UIjson login status is 'login_required'");
assertTest(($decodedLogin['error'] ?? '') === 'Test auth error', "UIjson returns error message");
}

// -------------------------------------------------------------
// Test 9: Bot Logout / Disconnect & Room Occupant Verification
// -------------------------------------------------------------
if (shouldRun(9)) {
    echo "\n9. Testing Bot Logout & Occupant Removal...\n";

$testBot1 = 'BotOne_' . rand(1000, 9999);
$testBot2 = 'BotTwo_' . rand(1000, 9999);

// Bot 1 connects
runMultiplayer([
    'format'   => 'json',
    'username' => $testBot1,
    'password' => 'bot1pass',
    'cmd'      => 'look',
]);

// Bot 2 connects and looks around - should see Bot1 in occupants
$bot2Json = runMultiplayer([
    'format'   => 'json',
    'username' => $testBot2,
    'password' => 'bot2pass',
    'cmd'      => 'look',
]);
$bot2Data = json_decode($bot2Json, true);
$occupantsBefore = array_column($bot2Data['room']['occupants'] ?? [], 'name');
assertTest(in_array($testBot1, $occupantsBefore, true), "Bot2 sees {$testBot1} in room occupants");

// Bot 1 logs out
$logoutJson = runMultiplayer([
    'format'   => 'json',
    'username' => $testBot1,
    'password' => 'bot1pass',
    'cmd'      => 'logout',
]);
$logoutData = json_decode($logoutJson, true);
assertTest(($logoutData['status'] ?? '') === 'logged_out', "Bot1 logout response status is 'logged_out'");
assertTest(($logoutData['logged_out'] ?? false) === true, "Bot1 logout response flag logged_out is true");
assertTest(array_key_exists('room', $logoutData) && $logoutData['room'] === null, "Bot1 logout response room snapshot is null");

// Bot 2 looks again - Bot1 should NO LONGER be in occupants!
$bot2JsonAfter = runMultiplayer([
    'format'   => 'json',
    'username' => $testBot2,
    'password' => 'bot2pass',
    'cmd'      => 'look',
]);
$bot2DataAfter = json_decode($bot2JsonAfter, true);
$occupantsAfter = array_column($bot2DataAfter['room']['occupants'] ?? [], 'name');
assertTest(!in_array($testBot1, $occupantsAfter, true), "Bot2 NO LONGER sees {$testBot1} after logout");
}

// -------------------------------------------------------------
// Test 10: world2.dxw Description Parsing (Apostrophe handling)
// -------------------------------------------------------------
if (shouldRun(10)) {
    echo "\n10. Testing world2.dxw Description Parsing...\n";

$world2Path = __DIR__ . '/../docs/dxw-examples/world2.dxw';
$loader = new Dimx\DxwLoader('http://localhost');
$world2 = $loader->load($world2Path);

$surfer = $world2->getCharacter('surfer');
assertTest($surfer !== null, "World 2 contains 'surfer' character");
assertTest(
    $surfer->description === "Walks up and down, I'd say he's got nothing to do.",
    "Surfer description parsed entirely without stopping at apostrophe: '{$surfer->description}'"
);

$box = $world2->items['box'] ?? null;
assertTest($box !== null, "World 2 contains 'box' item");
assertTest(
    $box->description === "It's just an ordinary cardboard box",
    "Box description parsed entirely: '{$box->description}'"
);

$bungalow = $world2->getRoom('bungalow');
assertTest($bungalow !== null, "World 2 contains 'bungalow' room");
assertTest(
    str_contains($bungalow->description, "There's a switch on the left wall."),
    "Bungalow description contains full text with apostrophe: '{$bungalow->description}'"
);

$apple = $world2->items['apple'] ?? null;
assertTest($apple !== null, "World 2 contains 'apple' item");
assertTest(
    str_contains($apple->description, "maybe it's good and tasty"),
    "Apple description contains full text with apostrophe: '{$apple->description}'"
);
}

// -------------------------------------------------------------
// Test 11: onStart Event & Chat PANEL Enforcement
// -------------------------------------------------------------
if (shouldRun(11)) {
    echo "\n11. Testing onStart Event & Chat PANEL Enforcement...\n";

// Purge any stale chat messages from previous runs
if ($pm->getPdo() !== null) {
    try {
        $pm->getPdo()->exec("DELETE FROM dimx_messages WHERE world_id = 'main'");
    } catch (\Throwable $e) {}
}

// Verify prebuilt panels in World
assertTest($world2->getPanel('default') !== null, "World contains prebuilt 'default' panel");
assertTest($world2->getPanel('chat') !== null, "World contains prebuilt 'chat' panel");

$chatPanel = $world2->getPanel('chat');
$chatCmds = $chatPanel->getAvailableCommands();
assertTest(in_array('say', $chatCmds, true), "Chat panel allows 'say' command");
assertTest(in_array('save', $chatCmds, true), "Chat panel allows 'save' command");
assertTest(in_array('logout', $chatCmds, true), "Chat panel allows 'logout' command");
assertTest(in_array('look', $chatCmds, true), "Chat panel allows 'look' command");
assertTest(in_array('click', $chatCmds, true), "Chat panel allows 'click' command");
assertTest(in_array('use', $chatCmds, true), "Chat panel allows 'use' command");
assertTest(in_array('go', $chatCmds, true), "Move commands ('go') are always valid");
assertTest(!in_array('pick', $chatCmds, true), "Chat panel DOES NOT allow 'pick' command");
assertTest(!in_array('drop', $chatCmds, true), "Chat panel DOES NOT allow 'drop' command");

// Seed world2 using GameEngine to trigger onStart
$testWorldId = 'test_w2_' . rand(1000, 9999);
$engineW2 = new Dimx\GameEngine($pm, $testWorldId, $world2Path);
$seededWorld = $engineW2->loadOrSeedWorld();

assertTest($seededWorld->defaultPanel !== null, "Seeded world has active panel");
assertTest($seededWorld->defaultPanel->id === 'chat', "onStart event executed SetPanel: active panel is 'chat'");

// Test Bot login via UIjson on world2
$botW2 = 'ChatBot_' . rand(1000, 9999);
$botJsonOutput = runMultiplayer([
    'format'   => 'json',
    'username' => $botW2,
    'password' => 'botPass123',
    'reset'    => '1', // Reset main world to world2.dxw configuration
]);

$botData = json_decode($botJsonOutput, true);
assertTest(($botData['status'] ?? '') === 'ok', "Bot login response status is 'ok'");
assertTest(!array_key_exists('panel', $botData), "Bot output omits internal 'panel' structure (no panel ID exposed)");
assertTest(array_key_exists('commands', $botData), "Bot output exposes simplified top-level 'commands' list");
$botCmds = $botData['commands'] ?? [];
assertTest(
    in_array('look', $botCmds, true),
    "Bot commands include 'look': " . json_encode($botCmds)
);
assertTest(
    in_array('click', $botCmds, true),
    "Bot commands include 'click': " . json_encode($botCmds)
);
assertTest(
    in_array('go', $botCmds, true),
    "Bot commands include 'go': " . json_encode($botCmds)
);
assertTest(
    in_array('use', $botCmds, true),
    "Bot commands include 'use': " . json_encode($botCmds)
);
assertTest(
    !in_array('examine', $botCmds, true),
    "Bot commands DO NOT include 'examine'"
);
assertTest(
    !in_array('talk', $botCmds, true),
    "Bot commands DO NOT include 'talk'"
);
assertTest(
    !in_array('quit', $botCmds, true),
    "Bot commands DO NOT include 'quit'"
);
assertTest(
    !in_array('disconnect', $botCmds, true),
    "Bot commands DO NOT include 'disconnect'"
);
assertTest(
    !in_array('exit', $botCmds, true),
    "Chat bot commands DO NOT include vehicle 'exit'"
);
assertTest(
    in_array('logout', $botCmds, true),
    "Bot commands include 'logout'"
);

// Verify Bot inspecting surfer receives the complete description
$surferOcc = null;
foreach ($botData['room']['occupants'] ?? [] as $occ) {
    if (($occ['id'] ?? '') === 'surfer') {
        $surferOcc = $occ;
        break;
    }
}
assertTest($surferOcc !== null, "Bot sees surfer in room occupants");
assertTest(!array_key_exists('description', $surferOcc), "Bot room snapshot surfer description is hidden (must look at character to read it)");
assertTest(!array_key_exists('icon', $surferOcc), "Bot room snapshot surfer omits null icon");
assertTest(isset($surferOcc['image']), "Bot room snapshot surfer includes image");
assertTest(!array_key_exists('dimensions', $surferOcc['image']), "Bot room snapshot surfer image omits 'dimensions'");
assertTest(($surferOcc['image']['width'] ?? 0) === 120, "Bot room snapshot surfer image width is 120");
assertTest(($surferOcc['image']['height'] ?? 0) === 120, "Bot room snapshot surfer image height is 120");
assertTest(str_contains($surferOcc['image']['url'] ?? '', 'chrSurfista.gif'), "Bot room snapshot surfer image URL contains 'chrSurfista.gif'");

// Bot uses 'look' on surfer in chat panel - should succeed and trigger surfer.onLook
$lookJson = runMultiplayer([
    'format'   => 'json',
    'username' => $botW2,
    'password' => 'botPass123',
    'cmd'      => 'look',
    'target'   => 'surfer',
]);
$lookData = json_decode($lookJson, true);
$lookMsg = implode(' ', $lookData['console'] ?? []);
assertTest(
    str_contains($lookMsg, "Walks up and down, I'd say he's got nothing to do."),
    "Bot successfully looks at surfer: character description is in console"
);
assertTest(
    !str_contains($lookMsg, "staring at me") && !str_contains($lookMsg, "Please look elsewhere!"),
    "surfer.onLook spoken message is NOT in room description / console"
);

// Verify surfer.onLook Speak output is delivered in msgs
$chatTexts = array_column($lookData['msgs'] ?? [], 'text');
$chatAll   = implode(' ', $chatTexts);
assertTest(
    str_contains($chatAll, "staring at me") || str_contains($chatAll, "Please look elsewhere!"),
    "surfer.onLook spoken message IS heard in msgs: '{$chatAll}'"
);
$surferChat = array_filter($lookData['msgs'] ?? [], fn($c) => ($c['sender'] ?? '') === 'a surfer');
assertTest(!empty($surferChat), "surfer.onLook speech has sender 'a surfer' in msgs");
$surferMsgItem = end($surferChat);
assertTest(($surferMsgItem['recipient'] ?? null) === $botW2, "surfer speaks to \$AGENT ({$botW2}) when second parameter is missing");

// Verify that another player does NOT receive this private message directed to $botW2
$otherChatJson = runMultiplayer([
    'format'   => 'json',
    'username' => 'EavesdropperBot',
    'password' => 'secret123',
    'cmd'      => 'look',
]);
$otherChatData = json_decode($otherChatJson, true);
$otherChatTexts = array_column($otherChatData['msgs'] ?? [], 'text');
$otherChatAll = implode(' ', $otherChatTexts);
assertTest(
    !str_contains($otherChatAll, "staring at me") && !str_contains($otherChatAll, "Please look elsewhere!"),
    "Other player does NOT hear the private speech directed to \$AGENT"
);

// Bot uses 'click' on surfer in chat panel - should also succeed
$clickJson = runMultiplayer([
    'format'   => 'json',
    'username' => $botW2,
    'password' => 'botPass123',
    'cmd'      => 'click',
    'target'   => 'surfer',
]);
$clickData = json_decode($clickJson, true);
$clickMsg = implode(' ', $clickData['console'] ?? []);
assertTest(
    str_contains($clickMsg, "Walks up and down, I'd say he's got nothing to do."),
    "Bot successfully clicks on surfer in chat panel: '{$clickMsg}'"
);

// Bot tries disallowed command 'pick' in chat panel
$disallowedPickJson = runMultiplayer([
    'format'   => 'json',
    'username' => $botW2,
    'password' => 'botPass123',
    'cmd'      => 'pick',
    'target'   => 'box',
]);
$disallowedPickData = json_decode($disallowedPickJson, true);
$disallowedPickMsg = implode(' ', $disallowedPickData['console'] ?? []);
assertTest(
    str_contains($disallowedPickMsg, "Command 'pick' is not allowed in current panel (chat)"),
    "Bot sending 'pick' is rejected in chat panel: '{$disallowedPickMsg}'"
);

// Test continuous session navigation and using switch to start game
$resGo1 = $engineW2->processCommand('SessionBot', 'go', 'U', '', $uiJson, null, true, true);
$botSessionId = $resGo1['player']->id;
assertTest($resGo1['room']?->id === 'rocca', "Move command 'go U' took player to 'rocca'");

$resGo2 = $engineW2->processCommand('SessionBot', 'go', 'E', '', $uiJson, null, true, false, $botSessionId);
assertTest($resGo2['room']?->id === 'bungalow', "Move command 'go E' took player to 'bungalow'");

// Use switch in bungalow: triggers switch.onUse and transitions world to default panel!
$resUse = $engineW2->processCommand('SessionBot', 'use', '', 'switch', $uiJson, null, true, false, $botSessionId);
$useSwitchMsg = implode(' ', $resUse['messages'] ?? []);
assertTest(
    str_contains($useSwitchMsg, "The adventure has started!"),
    "switch.onUse fired and broadcasted: '{$useSwitchMsg}'"
);
assertTest($resUse['world']->defaultPanel->id === 'default', "switch.onUse successfully switched panel to 'default'");
assertTest($resUse['world']->defaultPanel->isCommandAllowed('pick'), "Default panel now allows 'pick'");

// Human HTML verification: Navigation exits remain visible and active
$humanHtmlOutput = runMultiplayer([
    'format'   => 'html',
    'username' => 'HumanChatHero',
    'password' => 'humanPass123',
]);
assertTest(str_contains($humanHtmlOutput, 'id="panel_chat"'), "Human player gets HTML panel with id='panel_chat'");
assertTest(str_contains($humanHtmlOutput, 'name="txtBox"'), "Human player gets speech textbox");
assertTest(str_contains($humanHtmlOutput, "sendCmd('look')"), "Human player sees Look button");
assertTest(!str_contains($humanHtmlOutput, 'id="btn_click"'), "Human player DOES NOT see explicit Click button (click is implicit on objects)");
assertTest(str_contains($humanHtmlOutput, "sendCmd('use')"), "Human player sees Use button");
assertTest(str_contains($humanHtmlOutput, "sendCmd('say')"), "Human player gets Say button");
assertTest(str_contains($humanHtmlOutput, "sendCmd('save')"), "Human player gets Save button");
assertTest(str_contains($humanHtmlOutput, "sendCmd('logout')"), "Human player gets Disconnect (logout) button");
assertTest(str_contains($humanHtmlOutput, "Disconnect"), "Human player sees Disconnect button label");
assertTest(str_contains($humanHtmlOutput, "sendCmd('go','w16')"), "Human player STILL SEES exit button for [U] to the rock");
assertTest(!str_contains($humanHtmlOutput, "sendCmd('pick')"), "Human player DOES NOT see Pick button");

// Test STATS box rendering in UIhtml (Capital letter rule & no Carrying)
$statsWorld = new Dimx\World('stats_world', 'Stats World');
$statsPlayer = new Dimx\Player($statsWorld, 'StatsTester', 'p_stats_1');
$statsPlayer->setAttrList('Health=100,Skill=5,open=0,locked=1,Score=50');
$statsRoom = new Dimx\Room($statsWorld, 'Stats Room', 'stats_room');
$statsPlayer->container = $statsRoom;
ob_start();
$htmlUiInstance = new Dimx\UIhtml($statsPlayer);
$htmlUiInstance->render([
    'world'     => $statsWorld,
    'player'    => $statsPlayer,
    'room'      => $statsRoom,
    'messages'  => [],
    'lockError' => false,
]);
$statsHtml = ob_get_clean();
assertTest(!str_contains($statsHtml, 'Carrying:'), "UIhtml STATS box omits Carrying");
assertTest(str_contains($statsHtml, '<b>Health:</b> 100'), "UIhtml STATS box displays capitalized attribute 'Health: 100'");
assertTest(str_contains($statsHtml, '<b>Skill:</b> 5'), "UIhtml STATS box displays capitalized attribute 'Skill: 5'");
assertTest(str_contains($statsHtml, '<b>Score:</b> 50'), "UIhtml STATS box displays capitalized attribute 'Score: 50'");
assertTest(!str_contains($statsHtml, '<b>open:</b>'), "UIhtml STATS box omits lowercase attribute 'open'");
assertTest(!str_contains($statsHtml, '<b>locked:</b>'), "UIhtml STATS box omits lowercase attribute 'locked'");
assertTest(!str_contains($statsHtml, 'id="btn_go"'), "UIhtml default panel DOES NOT render 'go' button inside #ctrls");
assertTest(!str_contains($statsHtml, "sendCmd('go');"), "UIhtml #ctrls DOES NOT render an explicit Go button");
}

// -------------------------------------------------------------
// Test 12: Testing UIbot vs UIjson and testBots Configuration
// -------------------------------------------------------------
if (shouldRun(12)) {
    echo "\n12. Testing UIbot vs UIjson and testBots Configuration...\n";

    $uiBot = new UIbot();
    assertTest($uiBot instanceof Dimx\UIjson, "UIbot inherits from UIjson");
    assertTest($uiBot->getType() === 'bot', "UIbot type is 'bot'");

    // Create mock room with both NPC Character and Player occupant
    $w = $seededWorld ?? $world;
$mockRoom = new Dimx\Room($w, 'Test Room', 'test_room');
$targetRoom = new Dimx\Room($w, 'Secret Target', 'secret_target');
$mockLink = new Dimx\Link($w, 'Test Door', 'test_door', $mockRoom, $targetRoom, true, 'E');
$npcChar  = new Dimx\Character($w, 'NpcDude', 'npc_dude');
$npcChar->move($mockRoom);
$otherPlayer = new Dimx\Player($w, 'OtherPlayer', 'p_other');
$otherPlayer->move($mockRoom);
$observerPlayer = new Dimx\Player($w, 'Observer', 'p_observer');
$observerPlayer->move($mockRoom);

$uiBotSnapshot = $uiBot->buildRoomSnapshot($mockRoom, $observerPlayer);
$uiJsonSnapshot = $uiJson->buildRoomSnapshot($mockRoom, $observerPlayer);

// UIbot must NOT have 'is_player' in any occupant
$uiBotOccupants = $uiBotSnapshot['occupants'] ?? [];
assertTest(count($uiBotOccupants) === 2, "UIbot snapshot contains 2 occupants");
$hasIsPlayerInBot = false;
foreach ($uiBotOccupants as $occ) {
    if (array_key_exists('is_player', $occ)) {
        $hasIsPlayerInBot = true;
    }
}
assertTest(!$hasIsPlayerInBot, "UIbot occupant snapshot omits 'is_player' (NPCs and players indistinguishable)");

$hasDescInBot = false;
foreach ($uiBotOccupants as $occ) {
    if (array_key_exists('description', $occ)) {
        $hasDescInBot = true;
    }
}
assertTest(!$hasDescInBot, "UIbot occupant snapshot omits 'description' (bot must look at character)");

// UIbot must NOT have 'target_room_id' or 'target_room_name' in exits
$uiBotExits = $uiBotSnapshot['exits'] ?? [];
assertTest(count($uiBotExits) === 1, "UIbot snapshot contains 1 exit");
$hasTargetInBotExit = false;
foreach ($uiBotExits as $ex) {
    if (array_key_exists('target_room_id', $ex) || array_key_exists('target_room_name', $ex)) {
        $hasTargetInBotExit = true;
    }
}
assertTest(!$hasTargetInBotExit, "UIbot exit snapshot omits 'target_room_id' and 'target_room_name'");

// UIbot stats must display only attributes starting with capital letters (like UIhtml)
$statsTestPlayer = new Dimx\Player($w, 'StatsBot', 'p_stats_bot');
$statsTestPlayer->setAttrList('Health=100,Strength=15,open=0,locked=1');
$uiBotStats = $uiBot->buildPlayerStats($statsTestPlayer);
assertTest(isset($uiBotStats['Health']) && $uiBotStats['Health'] === 100, "UIbot buildPlayerStats includes capitalized attribute 'Health'");
assertTest(isset($uiBotStats['Strength']) && $uiBotStats['Strength'] === 15, "UIbot buildPlayerStats includes capitalized attribute 'Strength'");
assertTest(!isset($uiBotStats['open']), "UIbot buildPlayerStats omits lowercase attribute 'open'");
assertTest(!isset($uiBotStats['locked']), "UIbot buildPlayerStats omits lowercase attribute 'locked'");

// UIjson MUST have 'is_player' in occupants
$uiJsonOccupants = $uiJsonSnapshot['occupants'] ?? [];
$hasIsPlayerInJson = true;
foreach ($uiJsonOccupants as $occ) {
    if (!array_key_exists('is_player', $occ)) {
        $hasIsPlayerInJson = false;
    }
}
assertTest($hasIsPlayerInJson, "UIjson occupant snapshot retains 'is_player' for diagnostic/testing");

// UIjson MUST have 'target_room_id' and 'target_room_name' in exits
$uiJsonExits = $uiJsonSnapshot['exits'] ?? [];
$hasTargetInJsonExit = true;
foreach ($uiJsonExits as $ex) {
    if (!array_key_exists('target_room_id', $ex) || !array_key_exists('target_room_name', $ex)) {
        $hasTargetInJsonExit = false;
    }
}
assertTest($hasTargetInJsonExit, "UIjson exit snapshot retains 'target_room_id' and 'target_room_name'");

// UIjson player status retains internal diagnostic player fields
$uiJsonPlayerStatus = $uiJson->buildPlayerStatus($observerPlayer);
$uiJsonHasInternalPlayerFields = (
    array_key_exists('id', $uiJsonPlayerStatus) &&
    array_key_exists('numeric_id', $uiJsonPlayerStatus) &&
    array_key_exists('is_bot', $uiJsonPlayerStatus) &&
    array_key_exists('capacity', $uiJsonPlayerStatus) &&
    array_key_exists('volume', $uiJsonPlayerStatus) &&
    array_key_exists('carrying_count', $uiJsonPlayerStatus)
);
assertTest($uiJsonHasInternalPlayerFields, "UIjson player status retains diagnostic fields (id, numeric_id, is_bot, capacity, volume, carrying_count)");

// Test room items: UIbot omits 'pickable', 'volume', 'type' while UIjson retains them
$mockItem = new Dimx\Item($w, 'Magic Wand', 'magic_wand');
$mockItem->volume = 2;
$mockItem->pickable = true;
$mockItem->move($mockRoom);

$uiBotSnapshotWithItem = $uiBot->buildRoomSnapshot($mockRoom, $observerPlayer);
$uiJsonSnapshotWithItem = $uiJson->buildRoomSnapshot($mockRoom, $observerPlayer);

$botItem = $uiBotSnapshotWithItem['items'][0] ?? [];
$jsonItem = $uiJsonSnapshotWithItem['items'][0] ?? [];

assertTest(!array_key_exists('pickable', $botItem), "UIbot item snapshot omits 'pickable'");
assertTest(!array_key_exists('volume', $botItem), "UIbot item snapshot omits 'volume'");
assertTest(!array_key_exists('type', $botItem), "UIbot item snapshot omits 'type'");
assertTest(isset($botItem['name']) && $botItem['name'] === 'Magic Wand', "UIbot item snapshot retains 'name'");

assertTest(array_key_exists('pickable', $jsonItem) && $jsonItem['pickable'] === true, "UIjson item snapshot retains 'pickable'");
assertTest(array_key_exists('volume', $jsonItem) && $jsonItem['volume'] === 2, "UIjson item snapshot retains 'volume'");
assertTest(array_key_exists('type', $jsonItem), "UIjson item snapshot retains 'type'");

// Test via multiplayer.php gateway: BotAlpha in testBots gets UIjson
$botAlphaJson = runMultiplayer([
    'bot'      => '1',
    'username' => 'BotAlpha',
    'password' => 'test123',
    'cmd'      => 'look',
]);
$botAlphaData = json_decode($botAlphaJson, true);
$botAlphaOccupants = $botAlphaData['room']['occupants'] ?? [];
$botAlphaHasIsPlayer = false;
foreach ($botAlphaOccupants as $occ) {
    if (array_key_exists('is_player', $occ)) {
        $botAlphaHasIsPlayer = true;
    }
}
assertTest($botAlphaHasIsPlayer, "BotAlpha (in testBots) receives UIjson with 'is_player' visible");

$botAlphaExits = $botAlphaData['room']['exits'] ?? [];
$botAlphaHasTarget = false;
foreach ($botAlphaExits as $ex) {
    if (array_key_exists('target_room_id', $ex) || array_key_exists('target_room_name', $ex)) {
        $botAlphaHasTarget = true;
    }
}
assertTest($botAlphaHasTarget, "BotAlpha (in testBots) receives UIjson with target room info in exits");
assertTest(isset($botAlphaData['panel']['id']), "BotAlpha (in testBots) receives UIjson with panel id: " . ($botAlphaData['panel']['id'] ?? ''));
assertTest(isset($botAlphaData['player']['numeric_id']), "BotAlpha (in testBots) receives UIjson with numeric_id in player");

// Test via multiplayer.php gateway: Standard bot (not in testBots) gets UIbot
$standardBotJson = runMultiplayer([
    'bot'      => '1',
    'username' => 'StandardBot_' . rand(100, 999),
    'password' => 'std123',
    'cmd'      => 'look',
]);
$standardBotData = json_decode($standardBotJson, true);
$standardBotOccupants = $standardBotData['room']['occupants'] ?? [];
$standardBotHasIsPlayer = false;
foreach ($standardBotOccupants as $occ) {
    if (array_key_exists('is_player', $occ)) {
        $standardBotHasIsPlayer = true;
    }
}
assertTest(!$standardBotHasIsPlayer, "Standard bot receives UIbot without 'is_player'");

$standardBotExits = $standardBotData['room']['exits'] ?? [];
$standardBotHasTarget = false;
foreach ($standardBotExits as $ex) {
    if (array_key_exists('target_room_id', $ex) || array_key_exists('target_room_name', $ex)) {
        $standardBotHasTarget = true;
    }
}
assertTest(!$standardBotHasTarget, "Standard bot receives UIbot without target room info in exits");
assertTest(!array_key_exists('panel', $standardBotData), "Standard bot receives UIbot without 'panel' structure (no panel ID)");
assertTest(array_key_exists('commands', $standardBotData) && is_array($standardBotData['commands']), "Standard bot receives UIbot with top-level 'commands' array");

assertTest(!array_key_exists('player', $standardBotData), "Standard bot receives UIbot without 'player' structure (replaced by 'stats')");
assertTest(array_key_exists('stats', $standardBotData) && is_array($standardBotData['stats']), "Standard bot receives UIbot with 'stats' structure");
assertTest($standardBotData['stats'] === [], "Standard bot receives empty stats list for World 2");

// Standard bot world section displays only 'name' and 'tick_length'
$stdWorld = $standardBotData['world'] ?? [];
assertTest(array_key_exists('name', $stdWorld), "Standard bot world section displays 'name'");
assertTest(array_key_exists('tick_length', $stdWorld), "Standard bot world section displays 'tick_length'");
assertTest(!array_key_exists('id', $stdWorld), "Standard bot world section omits 'id'");
assertTest(!array_key_exists('mode', $stdWorld), "Standard bot world section omits 'mode'");
assertTest(array_key_exists('console', $standardBotData) && is_array($standardBotData['console']), "Standard bot receives 'console' array");
assertTest(array_key_exists('msgs', $standardBotData) && is_array($standardBotData['msgs']), "Standard bot receives 'msgs' array");
assertTest(!array_key_exists('messages', $standardBotData), "Standard bot omits legacy 'messages' key");
assertTest(!array_key_exists('chat', $standardBotData), "Standard bot omits legacy 'chat' key");

// Test UIbot HTML tolerance (default false -> strips HTML tags in console; true -> preserves HTML tags)
assertTest($uiBot->isHtmlTolerant() === false, "UIbot htmlTolerant defaults to false");
$stdConsoleJoined = implode(' ', $standardBotData['console'] ?? []);
assertTest(!str_contains($stdConsoleJoined, '<b>'), "UIbot strips HTML tags from console by default: '{$stdConsoleJoined}'");

$htmlBotName = 'HtmlTolerantBot_' . rand(100, 999);
$htmlOnResp = json_decode(runMultiplayer([
    'bot'      => '1',
    'username' => $htmlBotName,
    'password' => 'html123',
    'cmd'      => 'html',
    'param'    => 'on',
]), true);
assertTest(($htmlOnResp['status'] ?? '') === 'ok', "UIbot intercepts 'html on' call with status 'ok'");
assertTest(str_contains(implode(' ', $htmlOnResp['console'] ?? []), 'ON'), "UIbot 'html on' confirms ON in console");

// Verify subsequent console output preserves HTML tags when htmlTolerant is ON
$uiBotTolerant = new UIbot();
$uiBotTolerant->setHtmlTolerant(true);
assertTest($uiBotTolerant->isHtmlTolerant() === true, "UIbot setHtmlTolerant(true) sets htmlTolerant to true");
ob_start();
$uiBotTolerant->render([
    'world'    => $w,
    'player'   => $observerPlayer,
    'room'     => $mockRoom,
    'messages' => ['You have reached <b>courtyard</b>'],
]);
$tolerantOut = json_decode((string)ob_get_clean(), true);
assertTest(($tolerantOut['console'][0] ?? '') === 'You have reached <b>courtyard</b>', "UIbot preserves HTML tags in console when htmlTolerant is true");

$uiBotTolerant->setHtmlTolerant(false);
ob_start();
$uiBotTolerant->render([
    'world'    => $w,
    'player'   => $observerPlayer,
    'room'     => $mockRoom,
    'messages' => ['You have reached <b>courtyard</b>'],
]);
$strippedOut = json_decode((string)ob_get_clean(), true);
assertTest(($strippedOut['console'][0] ?? '') === 'You have reached courtyard', "UIbot strips HTML tags in console when htmlTolerant is false");
}

// -------------------------------------------------------------
// Test 13: Maintenance Console Authentication & UIhtml Title Bar
// -------------------------------------------------------------
if (shouldRun(13)) {
    echo "\n13. Testing adminPasswd in config and maint.php authentication...\n";

$cfg = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
assertTest(isset($cfg['adminPasswd']) && is_string($cfg['adminPasswd']) && $cfg['adminPasswd'] !== '', "config.php defines non-empty 'adminPasswd'");

// UIhtml title bar does not expose Maint or Reset links
$testW = new Dimx\World('w_title_test', 'Title Test');
$testR = new Dimx\Room($testW, 'Quiet Room', 'quiet_rm');
$testP = new Dimx\Player($testW, 'TitlePlayer', 'p_title');
$testP->container = $testR;
$testR->contents[$testP->id] = $testP;
$testUi = new Dimx\UIhtml($testP);
ob_start();
$testUi->render(['world' => $testW, 'player' => $testP, 'room' => $testR]);
$titleHtml = ob_get_clean();

assertTest(!str_contains($titleHtml, 'maint.php'), "UIhtml title bar omits Maint link");
assertTest(!str_contains($titleHtml, '?reset=1'), "UIhtml title bar omits Reset link");

// Test maint.php denial without password
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['action' => 'reset_world'];
ob_start();
include __DIR__ . '/maint.php';
$maintDeniedNoPass = ob_get_clean();
assertTest(str_contains($maintDeniedNoPass, 'Access denied: incorrect or missing admin password'), "maint.php denies action without adminPasswd");

// Test maint.php denial with incorrect password
$_POST = ['action' => 'reset_world', 'adminPasswd' => 'incorrectSecret999'];
ob_start();
include __DIR__ . '/maint.php';
$maintDeniedBadPass = ob_get_clean();
assertTest(str_contains($maintDeniedBadPass, 'Access denied: incorrect or missing admin password'), "maint.php denies action with incorrect adminPasswd");

// Test maint.php authorization with correct password and debug.log deletion
$testDebugLog = __DIR__ . '/debug.log';
file_put_contents($testDebugLog, "temporary test debug log content\n");
assertTest(file_exists($testDebugLog), "test debug.log file exists prior to reset_world");

$_POST = ['action' => 'reset_world', 'adminPasswd' => $cfg['adminPasswd']];
ob_start();
include __DIR__ . '/maint.php';
$maintSuccess = ob_get_clean();
assertTest(str_contains($maintSuccess, 'The game world has been reset'), "maint.php authorizes action with correct adminPasswd");
assertTest(!file_exists($testDebugLog), "maint.php deletes debug.log before world reset");
}

// -------------------------------------------------------------
// Test 14: Event Model Roles ($OWNER, $AGENT, $TARGET, $WORLD) & Return (§4.1.3 & §4.1.4)
// -------------------------------------------------------------
if (shouldRun(14)) {
    echo "\n14. Testing Event Model Roles (\$OWNER, \$AGENT, \$TARGET, \$WORLD) & Return Values...\n";

// Verify World::getObjectExt
assertTest($world->getObjectExt('beach') !== null, "getObjectExt resolves room 'beach'");
assertTest($world->getObjectExt('apple') !== null, "getObjectExt resolves item 'apple'");
assertTest($world->getObjectExt('$WORLD') === $world, "getObjectExt resolves '\$WORLD' to World instance");

// Test ActionsRunner execution with role variables and expressions
$runnerOwner = $world->items['apple'] ?? new \Dimx\Item('apple', 'Apple');
$runnerAgent = new \Dimx\Player($world, 'TestHero', 'p_test');
$runnerAgent->score = 42;
$runnerTarget = 'explicit';

// 1. Script accessing $OWNER, $AGENT, $TARGET, $WORLD and modifying properties
$runner = new \Dimx\ActionsRunner($world, $runnerOwner, $runnerAgent, $runnerTarget);
$script1 = <<<DXW
' Modify agent score and world property
If \$TARGET = "explicit"
    \$AGENT.score = \$AGENT.score + 8
    \$WORLD.mode = 2
End_If
DXW;
$res1 = $runner->run($script1);
assertTest($res1 === true, "ActionsRunner returns true by default (§4.1.4)");
assertTest($runnerAgent->score === 50, "Script successfully reads and updates \$AGENT.score using arithmetic: 50");
assertTest($world->mode === 2, "Script successfully reads and updates \$WORLD.mode: 2");

// 2. Cancellable Return statement returning 0 / False (§4.1.4)
$scriptCancel = <<<DXW
If \$AGENT.score >= 50
    Return 0
End_If
Return 1
DXW;
$runner2 = new \Dimx\ActionsRunner($world, $runnerOwner, $runnerAgent, $runnerTarget);
$resCancel = $runner2->run($scriptCancel);
assertTest($resCancel == 0, "ActionsRunner Return 0 successfully evaluates and returns cancellation result (§4.1.4)");

// 3. Living event pattern from §4.3: Health calculation and condition return
$testChar = new \Dimx\Character($world, 'TestNPC', 'npc1');
$testChar->setProperty('Health', 8.5);
$livingRunner = new \Dimx\ActionsRunner($world, $testChar, $testChar, $testChar);
$livingScript = <<<DXW
\$OWNER.Health = \$OWNER.Health + 0.5
Return \$OWNER.Health > 5
DXW;
$livingRes = $livingRunner->run($livingScript);
assertTest($livingRes === true, "Living script evaluates condition and returns true: character lives");
assertTest((float)$testChar->getProperty('Health') === 9.0, "Living script updated \$OWNER.Health to 9.0");
}

// -------------------------------------------------------------
// Test 15: Testing UIhtml Ctrls (#ctrls) Command Buttons & On-Screen Object Targeting...
// -------------------------------------------------------------
if (shouldRun(15)) {
    echo "\n15. Testing UIhtml Ctrls (#ctrls) Command Buttons & On-Screen Object Targeting...\n";
// Load world2.dxw to test bungalow and switch
$dxwFile = dirname(__DIR__) . '/docs/dxw-examples/world2.dxw';
$w2Loader = new \Dimx\DxwLoader();
$w2 = $w2Loader->load($dxwFile);
$bungalow = $w2->getRoom('bungalow');
assertTest($bungalow !== null, "World 2 contains 'bungalow' room");
$switch = $bungalow->contents['switch'] ?? null;
assertTest($switch instanceof \Dimx\Item, "Bungalow contains 'switch' item");
assertTest($switch->showX === 62 && $switch->showY === 68, "Switch item has parsed coordinates SHOW ICON 62,68");
assertTest($switch->showmode === \Dimx\AdvObject::SHOW_ICON, "Switch item has parsed showmode SHOW_ICON");

// Render UIhtml for player in bungalow
$w2Player = new \Dimx\Player($w2, 'TesterInBungalow', 'p_bungalow_1');
$w2Player->move($bungalow);
$w2Ui = new \Dimx\UIhtml($w2Player);
ob_start();
$w2Ui->render([
    'world'    => $w2,
    'player'   => $w2Player,
    'room'     => $bungalow,
    'messages' => [],
]);
$bungalowHtml = ob_get_clean();

assertTest(str_contains($bungalowHtml, 'jquery-3.7.1.min.js'), "UIhtml includes jQuery library");
assertTest(str_contains($bungalowHtml, 'id="cmdPrompt"'), "UIhtml Ctrls (#ctrls) contains cmdPrompt div");
assertTest(str_contains($bungalowHtml, 'const COMMAND_DEFS = {'), "UIhtml defines generic COMMAND_DEFS dictionary");
assertTest(str_contains($bungalowHtml, "function identifyObject("), "UIhtml includes identifyObject function");
assertTest(str_contains($bungalowHtml, "function clickObject("), "UIhtml includes clickObject function");
assertTest(str_contains($bungalowHtml, "function handleCommand("), "UIhtml includes handleCommand function");
assertTest(str_contains($bungalowHtml, "data-cmd=\"use\" data-model=\"O\""), "UIhtml Ctrls (#ctrls) USE button has data-cmd='use' and data-model='O'");
assertTest(str_contains($bungalowHtml, "data-cmd=\"look\" data-model=\"O\""), "UIhtml Ctrls (#ctrls) LOOK button has data-cmd='look' and data-model='O'");
assertTest(str_contains($bungalowHtml, 'data-obj-id="switch"') && str_contains($bungalowHtml, 'data-obj-type="i"'), "UIhtml renders switch as on-screen object with id='switch' and type='i'");
assertTest(str_contains($bungalowHtml, "width:444px;height:301px") || str_contains($bungalowHtml, "width: 444px; height: 301px") || str_contains($bungalowHtml, 'width="444"'), "UIhtml scales bungalow room scene to 444x301 for 1280x800 desktop client capped at 1024px (factorx=1.28)");
assertTest(str_contains($bungalowHtml, "left:81px;top:193px;width:20px;height:20px;"), "UIhtml positions and renders switch on scene with scaled icon size at left:81px;top:193px;width:20px;height:20px;");
}

// -------------------------------------------------------------
// Test 16: Testing 'open' and 'close' Commands & actOpen / actClose on 'box' in World 2...
// -------------------------------------------------------------
if (shouldRun(16)) {
    echo "\n16. Testing 'open' and 'close' Commands & actOpen / actClose on 'box' in World 2...\n";
$spiaggia = $w2->getRoom('spiaggia');
assertTest($spiaggia !== null, "World 2 contains 'spiaggia' room");
$box = $spiaggia->contents['box'] ?? null;
assertTest($box instanceof \Dimx\Item, "Spiaggia contains 'box' item");
assertTest($box->isOpenable(), "Box is recognized as openable");
assertTest(!$box->isOpen(), "Box is initially closed (open=0)");
assertTest(empty($box->getContents(true)), "Box contents are concealed when box is closed");

// Test Player using GameEngine executeCommandAction for 'open box'
$pOpenTester = new \Dimx\Player($w2, 'BoxTester', 'p_box_1');
$pOpenTester->move($spiaggia);
$w2Engine = new \Dimx\GameEngine($pm, 'w2_test', $dxwFile);
// Switch panel to default so open/close commands are allowed
$w2->defaultPanel = \Dimx\Panel::createDefaultPanel();
$pOpenTester->setPanel($w2->defaultPanel);

// Process 'open box'
$w2Engine->executeCommandAction($w2, $pOpenTester, 'open', '', 'box');
assertTest($box->isOpen(), "Box is now open after 'open box' command");
assertTest((int)$box->getProperty('open') === 1, "Box property 'open' equals 1");
$boxMessages = $pOpenTester->drainConsole();
assertTest(str_contains(implode(' ', $boxMessages), "You opened"), "Player receives confirmation message 'You opened'");
assertTest(!empty($box->getContents(true)), "Box contents (apple) are revealed after opening");

// Process 'close box'
$w2Engine->executeCommandAction($w2, $pOpenTester, 'close', '', 'box');
assertTest(!$box->isOpen(), "Box is closed after 'close box' command");
assertTest((int)$box->getProperty('open') === 0, "Box property 'open' equals 0");
$closeMessages = $pOpenTester->drainConsole();
assertTest(str_contains(implode(' ', $closeMessages), "You closed"), "Player receives confirmation message 'You closed'");

// Test SmallBasic script instructions 'Open box' and 'Close box' via ActionsRunner
$scriptRunner = new \Dimx\ActionsRunner($w2, $box, $pOpenTester, $box);
$scriptRunner->run("Open box");
assertTest($box->isOpen(), "ActionsRunner 'Open box' successfully opened the box");
$scriptRunner->run("Close box");
assertTest(!$box->isOpen(), "ActionsRunner 'Close box' successfully closed the box");
}

// -------------------------------------------------------------
// Test 17: Testing World 2 Full Flow: Look at Closed Box, Use Switch in Bungalow, Open Box & Pick Apple...
// -------------------------------------------------------------
if (shouldRun(17)) {
    echo "\n17. Testing World 2 Full Flow: Look at Closed Box, Use Switch in Bungalow, Open Box & Pick Apple...\n";

// Fresh engine & world 2 instance
$w2FlowWorld = $loader->load($dxwFile, 'w2_flow_world');
$w2FlowEngine = new \Dimx\GameEngine($pm, 'w2_flow', $dxwFile);
// Player enters at spiaggia
$pFlow = new \Dimx\Player($w2FlowWorld, 'FlowHero', 'p_flow_1');
$pFlow->setPanel($w2FlowWorld->defaultPanel ?? \Dimx\Panel::createDefaultPanel());
$spiaggiaRoom = $w2FlowWorld->getRoom('spiaggia');
$pFlow->move($spiaggiaRoom);

$flowBox = $spiaggiaRoom->contents['box'] ?? null;
assertTest($flowBox instanceof \Dimx\Item, "Spiaggia contains 'box' item");
assertTest($flowBox->isOpenable(), "Box is recognized as openable");
assertTest(!$flowBox->isOpen(), "Box is initially closed");

// 1. Look at box: verify game engine prints that the box is closed
$w2FlowEngine->executeCommandAction($w2FlowWorld, $pFlow, 'look', '', 'box');
$lookBoxMsgs = implode(' ', $pFlow->drainConsole());
assertTest(
    str_contains(strtolower($lookBoxMsgs), 'box is closed') || str_contains(strtolower($lookBoxMsgs), 'is closed'),
    "Game engine prints that box is closed when looked at: '{$lookBoxMsgs}'"
);
$evalBoxDesc = $flowBox->evalDescription($pFlow);
assertTest(
    str_contains(strtolower($evalBoxDesc), 'is closed'),
    "AdvObject::evalDescription() includes 'is closed' hint: '{$evalBoxDesc}'"
);

// Render in UIhtml: verify Display DIV (#display) console output formats line break before 'a cardboard box is closed.'
ob_start();
$htmlUiEarly = new \Dimx\UIhtml($pFlow);
$htmlUiEarly->render([
    'world'     => $w2FlowWorld,
    'player'    => $pFlow,
    'room'      => $spiaggiaRoom,
    'messages'  => [$lookBoxMsgs],
    'lockError' => false,
]);
$lookBoxClosedHtml = ob_get_clean();
assertTest(
    str_contains($lookBoxClosedHtml, '<br />') && str_contains($lookBoxClosedHtml, 'a cardboard box is closed.'),
    "UIhtml console in Display DIV (#display) formats line breaks so 'a cardboard box is closed.' appears on a new line"
);

// 2. Move to bungalow room: go U (to rocca), then go E (to bungalow)
$w2FlowEngine->executeCommandAction($w2FlowWorld, $pFlow, 'go', 'U', '');
assertTest($pFlow->getRoom()?->id === 'rocca', "Move 'go U' took player to 'rocca'");
$w2FlowEngine->executeCommandAction($w2FlowWorld, $pFlow, 'go', 'E', '');
assertTest($pFlow->getRoom()?->id === 'bungalow', "Move 'go E' took player to 'bungalow'");

// 3. Use the switch in bungalow: triggers switch.onUse
$w2FlowEngine->executeCommandAction($w2FlowWorld, $pFlow, 'use', '', 'switch');
$useMsgs = implode(' ', $pFlow->drainConsole());
assertTest((int)$w2FlowWorld->mode === 1, "switch.onUse set \$WORLD.mode = 1");
assertTest(!$flowBox->isOpen(), "switch.onUse ensured box.open = 0");
$appleItem = $w2FlowWorld->getItem('apple');
assertTest($appleItem !== null, "World 2 defines 'apple' item");
assertTest($appleItem->container === $flowBox, "switch.onUse moved apple inside box");

// 4. Return to beach room: go W (to rocca), then go D (to spiaggia)
$w2FlowEngine->executeCommandAction($w2FlowWorld, $pFlow, 'go', 'W', '');
assertTest($pFlow->getRoom()?->id === 'rocca', "Move 'go W' took player back to 'rocca'");
$w2FlowEngine->executeCommandAction($w2FlowWorld, $pFlow, 'go', 'D', '');
assertTest($pFlow->getRoom()?->id === 'spiaggia', "Move 'go D' took player back to 'spiaggia'");

// While box is closed, verify apple is concealed in bot snapshot
$botUi = new \Dimx\UIbot();
$closedSnapshot = $botUi->buildRoomSnapshot($spiaggiaRoom, $pFlow);
$closedItems = array_column($closedSnapshot['items'], 'id');
assertTest(in_array('box', $closedItems, true), "Closed snapshot lists 'box'");
assertTest(!in_array('apple', $closedItems, true), "Closed snapshot conceals 'apple'");

// 5. Open the box
$w2FlowEngine->executeCommandAction($w2FlowWorld, $pFlow, 'open', '', 'box');
assertTest($flowBox->isOpen(), "Box is now open after 'open box'");
$openBoxMsgsList = $pFlow->drainConsole();
$openBoxMsgs = implode(' ', $openBoxMsgsList);
assertTest(str_contains($openBoxMsgs, "You opened"), "Player receives open confirmation: '{$openBoxMsgs}'");
assertTest(str_contains($openBoxMsgs, "Containing:"), "Open output includes 'Containing:'");
assertTest(str_contains($openBoxMsgs, "icoMela.gif"), "Containing message includes icon for apple");
assertTest(str_contains($openBoxMsgs, 'data-obj-id="apple"'), "Containing message includes data-obj-id='apple'");

// 6. Direct contents of box displayed on screen with clickable icon
// a) UIhtml rendering
ob_start();
$htmlUi = new \Dimx\UIhtml($pFlow);
$htmlUi->render([
    'world'     => $w2FlowWorld,
    'player'    => $pFlow,
    'room'      => $spiaggiaRoom,
    'messages'  => $openBoxMsgsList,
    'lockError' => false,
]);
$spiaggiaHtml = ob_get_clean();

// Contained items must NOT appear in the room items list in Ctrls (#ctrls) / Dashboard (#dashboard)
$itemsListStart = strpos($spiaggiaHtml, 'dimx-section-title" style="color:#38bdf8">Items</div>');
$invListStart = strpos($spiaggiaHtml, '🎒 Inventory</div>');
$itemsListSection = ($itemsListStart !== false && $invListStart !== false)
    ? substr($spiaggiaHtml, $itemsListStart, $invListStart - $itemsListStart)
    : '';
assertTest(
    !str_contains($itemsListSection, 'an apple') && !str_contains($itemsListSection, 'data-obj-id="apple"'),
    "Contained object 'apple' does NOT appear in the room items list"
);
assertTest(
    !str_contains($itemsListSection, '(open)') && !str_contains($itemsListSection, '(closed)'),
    "Items list in Dashboard (#dashboard) omits (open)/(closed) status as requested"
);

// Look at box in UIhtml: verify Display DIV (#display) console output formats line break before 'a cardboard box is open.'
ob_start();
$htmlUi->render([
    'world'     => $w2FlowWorld,
    'player'    => $pFlow,
    'room'      => $spiaggiaRoom,
    'messages'  => ["<b>a cardboard box</b>: " . $flowBox->evalDescription($pFlow)],
    'lockError' => false,
]);
$lookBoxHtml = ob_get_clean();
assertTest(
    str_contains($lookBoxHtml, '<br />') && str_contains($lookBoxHtml, 'a cardboard box is open.'),
    "UIhtml console in Display DIV (#display) formats line breaks so 'a cardboard box is open.' appears on a new line"
);

// Contained items appear directly after/below open message in console
assertTest(str_contains($spiaggiaHtml, 'dimx-console'), "UIhtml includes console");
assertTest(str_contains($spiaggiaHtml, 'Containing:'), "UIhtml console displays 'Containing:' below open message");
assertTest(str_contains($spiaggiaHtml, 'data-obj-id="apple"'), "UIhtml displays apple with data-obj-id='apple'");
assertTest(str_contains($spiaggiaHtml, 'data-obj-type="i"'), "UIhtml displays apple with data-obj-type='i'");
assertTest(str_contains($spiaggiaHtml, 'onclick="clickObject(this)"'), "UIhtml displays apple with clickable onclick handler");
assertTest(str_contains($spiaggiaHtml, 'icoMela.gif'), "UIhtml displays clickable icon for apple (icoMela.gif)");
assertTest(str_contains($spiaggiaHtml, 'an apple'), "UIhtml displays name 'an apple'");
assertTest(str_contains($spiaggiaHtml, "\$(document).on('click', '.dimx-onscreen-obj, [data-obj-id]'"), "UIhtml includes delegated point-and-click handler for objects anywhere including Ctrls (#ctrls)");

// b) Bot UIs: bots can see apple item id
$openSnapshot = $botUi->buildRoomSnapshot($spiaggiaRoom, $pFlow);
$openItems = array_column($openSnapshot['items'], 'id');
assertTest(in_array('apple', $openItems, true), "Bot UI snapshot reveals 'apple' inside open box");

$jsonUiFlow = new \Dimx\UIjson();
$jsonSnapshot = $jsonUiFlow->buildRoomSnapshot($spiaggiaRoom, $pFlow);
$jsonItemIds = array_column($jsonSnapshot['items'], 'id');
assertTest(in_array('apple', $jsonItemIds, true), "UIjson snapshot reveals 'apple' inside open box");

// 7. Pick apple via PICK command/button using its ID
$w2FlowEngine->executeCommandAction($w2FlowWorld, $pFlow, 'pick', '', 'apple');
$pickMsgs = implode(' ', $pFlow->drainConsole());
assertTest(str_contains($pickMsgs, "You picked up"), "Player receives pickup confirmation: '{$pickMsgs}'");
assertTest($appleItem->container === $pFlow, "Apple is now carried by player in inventory");
assertTest(!isset($flowBox->contents['apple']), "Apple is no longer inside box");
assertTest($w2FlowWorld->defaultPanel?->id === 'chat' || $pFlow->getPanel()?->id === 'chat', "apple.whenPicked triggered Goal and switched panel to 'chat'");

// Verify UIhtml render with item in inventory does not trigger Undefined variable $activePanel warning
ob_start();
$htmlUiAfterPick = new \Dimx\UIhtml($pFlow);
$htmlUiAfterPick->render([
    'world'     => $w2FlowWorld,
    'player'    => $pFlow,
    'room'      => $spiaggiaRoom,
    'messages'  => $pickMsgs ? [$pickMsgs] : [],
    'lockError' => false,
]);
$htmlAfterPick = ob_get_clean();
assertTest(!str_contains($htmlAfterPick, 'Undefined variable $activePanel'), "UIhtml renders with items in inventory without 'Undefined variable \$activePanel' warning");
assertTest(str_contains($htmlAfterPick, '🎒 Inventory'), "UIhtml renders inventory section");
assertTest(str_contains($htmlAfterPick, 'an apple'), "UIhtml renders 'an apple' in inventory section");
assertTest(str_contains($htmlAfterPick, 'data-obj-id="apple"'), "UIhtml inventory renders apple as on-screen object with data-obj-id='apple'");
assertTest(!str_contains($htmlAfterPick, 'dimx-mini-btn'), "UIhtml inventory omits inline 'Use' and 'Drop' buttons beside carried items");

// Verify UIbot render with item in inventory renders cleanly without errors
ob_start();
$uiBot->render([
    'world'     => $w2FlowWorld,
    'player'    => $pFlow,
    'room'      => $spiaggiaRoom,
    'messages'  => $pickMsgs ? [$pickMsgs] : [],
    'lockError' => false,
]);
$botAfterPickRaw = ob_get_clean();
$botAfterPick = json_decode($botAfterPickRaw, true);
assertTest(($botAfterPick['status'] ?? '') === 'ok', "UIbot renders status 'ok' after game completion");
assertTest(is_array($botAfterPick['commands'] ?? null), "UIbot payload contains commands list");
assertTest(!str_contains($botAfterPickRaw, 'Warning'), "UIbot output contains no PHP warnings");
assertTest(!str_contains($botAfterPickRaw, 'activePanel'), "UIbot output contains no activePanel errors");
}

// -------------------------------------------------------------
// Test 18: Speak Instruction Behavior and Default Speaker ("A voice...")
// -------------------------------------------------------------
if (shouldRun(18)) {
    echo "\n18. Testing Speak Instruction Behavior & Inanimate Object Fallback...\n";

// In world 2, travel to bungalow and look at the switch:
// switch.onLook executes: Speak "USE this switch to start a new game!"
// Because 'switch' is an Item and not a Character, speaker must NOT be 'a switch' or 'switch'.
// The speaker must fall back to the default system voice ("a voice...").
$w2SpeakWorld = $loader->load($dxwFile, 'w2_speak_test');
$w2SpeakEngine = new \Dimx\GameEngine($pm, 'w2_speak', $dxwFile);
$pSpeak = new \Dimx\Player($w2SpeakWorld, 'SpeakTester', 'p_speak_1');
$pSpeak->move($w2SpeakWorld->getRoom('spiaggia'));
$w2SpeakWorld->addPlayer($pSpeak);

$w2SpeakEngine->executeCommandAction($w2SpeakWorld, $pSpeak, 'go', 'U', '');
$w2SpeakEngine->executeCommandAction($w2SpeakWorld, $pSpeak, 'go', 'E', '');
assertTest($pSpeak->getRoom()?->id === 'bungalow', "Player travelled to bungalow");

// Clear existing chat queue
$w2SpeakWorld->chatQueue = [];

// Player looks at the switch
$w2SpeakEngine->executeCommandAction($w2SpeakWorld, $pSpeak, 'look', '', 'switch');
$switchChat = $w2SpeakWorld->chatQueue;

assertTest(!empty($switchChat), "switch.onLook Speak emitted a chat message");
$firstChat = $switchChat[0] ?? [];
assertTest(
    ($firstChat['sender'] ?? '') !== 'a switch' && ($firstChat['sender'] ?? '') !== 'switch',
    "Phrase is NOT spoken by the switch itself ('{$firstChat['sender']}')"
);
assertTest(
    ($firstChat['sender'] ?? '') === 'a voice...',
    "Phrase is spoken by default system character 'a voice...' ('{$firstChat['sender']}')"
);
assertTest(
    ($firstChat['recipient'] ?? null) === 'SpeakTester',
    "Phrase is delivered to the player (\$AGENT 'SpeakTester')"
);
assertTest(
    ($firstChat['text'] ?? '') === 'USE this switch to start a new game!',
    "Phrase text matches 'USE this switch to start a new game!': '{$firstChat['text']}'"
);

// Dialogue sequence test on surfer:
// surfer.onLook has 2 messages:
// 1) "Hey, why the hell you are staring at me?"
// 2) "Please look elsewhere!"
$pSpeak->move($w2SpeakWorld->getRoom('spiaggia'));
$w2SpeakWorld->chatQueue = [];
$w2SpeakEngine->executeCommandAction($w2SpeakWorld, $pSpeak, 'look', '', 'surfer');
$surferChat1 = end($w2SpeakWorld->chatQueue);
assertTest(($surferChat1['sender'] ?? '') === 'a surfer', "Surfer speaks as 'a surfer' (Character)");
assertTest(
    ($surferChat1['text'] ?? '') === 'Hey, why the hell you are staring at me?',
    "First interaction selects first phrase: '{$surferChat1['text']}'"
);

// Second interaction with surfer
$w2SpeakEngine->executeCommandAction($w2SpeakWorld, $pSpeak, 'look', '', 'surfer');
$surferChat2 = end($w2SpeakWorld->chatQueue);
assertTest(
    ($surferChat2['text'] ?? '') === 'Please look elsewhere!',
    "Second interaction selects second phrase in sequence: '{$surferChat2['text']}'"
);

// Third interaction wraps around
$w2SpeakEngine->executeCommandAction($w2SpeakWorld, $pSpeak, 'look', '', 'surfer');
$surferChat3 = end($w2SpeakWorld->chatQueue);
assertTest(
    ($surferChat3['text'] ?? '') === 'Hey, why the hell you are staring at me?',
    "Third interaction wraps around to first phrase: '{$surferChat3['text']}'"
);

// Verify debug.log output for Speak instructions (mimicking Java ActionsRunner)
$debugLogPath = dirname(__FILE__) . '/debug.log';
if (file_exists($debugLogPath)) {
    $debugContent = file_get_contents($debugLogPath);
    assertTest(
        str_contains($debugContent, 'Warning: invalid speaker: switch'),
        "debug.log contains 'Warning: invalid speaker: switch'"
    );
    assertTest(
        str_contains($debugContent, 'Speaking parameter #2 to') && str_contains($debugContent, 'USE this switch to start a new game!'),
        "debug.log contains 'Speaking parameter #2 to ...: USE this switch to start a new game!'"
    );
    assertTest(
        str_contains($debugContent, 'Speaking parameter #3 to') && str_contains($debugContent, 'Please look elsewhere!'),
        "debug.log contains sequential 'Speaking parameter #3 to ...: Please look elsewhere!'"
    );
}
}

// --------------------------------------------------------------------------
// 19. UIhtml Smartphone Portrait / Vertical Layout (*, 35%, 15%)
// --------------------------------------------------------------------------
if (shouldRun(19)) {
    echo "\n19. Testing UIhtml Smartphone Portrait / Vertical Layout (*, 35%, 15%)...\n";

    $testWorld = new \Dimx\World('test_mobile_world', 'Mobile World');
    $testRoom = new \Dimx\Room($testWorld, 'mobile_test_room', 'Test Room');

    $pMobile = new Player($testWorld, 'MobileUser');
    $pMobile->screenWidth = 390;
    $pMobile->screenHeight = 844;
    $pMobile->isPortrait = ($pMobile->screenWidth < $pMobile->screenHeight);

    ob_start();
    $uiMobile = new \Dimx\UIhtml($pMobile);
    $uiMobile->render([
        'world'     => $testWorld,
        'player'    => $pMobile,
        'room'      => $testRoom,
        'messages'  => [],
        'lockError' => false,
    ]);
    $mobileHtml = ob_get_clean();

    assertTest($pMobile->isPortrait === true, "Player isPortrait is true when screenWidth < screenHeight (390 < 844)");
    assertTest(str_contains($mobileHtml, 'dimx-root dimx-portrait'), "UIhtml renders 'dimx-root dimx-portrait' class when in portrait mode");
    assertTest(str_contains($mobileHtml, '.dimx-portrait #ctrls'), "UIhtml CSS defines .dimx-portrait #ctrls");
    assertTest(str_contains($mobileHtml, '35vh'), "UIhtml CSS specifies 35vh height for Ctrls (#ctrls) in portrait");
    assertTest(str_contains($mobileHtml, '15vh'), "UIhtml CSS specifies 15vh height for Msgs (#msgs) in portrait");
    assertTest(str_contains($mobileHtml, '.dimx-portrait .dimx-inner-row') && str_contains($mobileHtml, 'flex-direction: row'), "UIhtml CSS preserves row disposition for Display DIV (#display) and Dashboard (#dashboard) in portrait");
    assertTest(str_contains($mobileHtml, '.dimx-portrait #display'), "UIhtml CSS styles Display DIV (#display) in portrait at left");
    assertTest(str_contains($mobileHtml, '.dimx-portrait #dashboard'), "UIhtml CSS styles Dashboard (#dashboard) in portrait at right");
    assertTest(str_contains($mobileHtml, '@media (orientation: portrait)'), "UIhtml CSS includes responsive @media (orientation: portrait)");

    // Test landscape player does not get dimx-portrait class on root
    $pDesktop = new Player($testWorld, 'DesktopUser');
    $pDesktop->screenWidth = 1366;
    $pDesktop->screenHeight = 768;
    $pDesktop->isPortrait = ($pDesktop->screenWidth < $pDesktop->screenHeight);

    ob_start();
    $uiDesktop = new \Dimx\UIhtml($pDesktop);
    $uiDesktop->render([
        'world'     => $testWorld,
        'player'    => $pDesktop,
        'room'      => $testRoom,
        'messages'  => [],
        'lockError' => false,
    ]);
    $desktopHtml = ob_get_clean();

    assertTest($pDesktop->isPortrait === false, "Player isPortrait is false when screenWidth > screenHeight (1366 > 768)");
    assertTest(!str_contains($desktopHtml, 'dimx-root dimx-portrait'), "UIhtml root does not have dimx-portrait class in landscape");
}

// --------------------------------------------------------------------------
// 20. Messages Box (Msgs / #msgs), popmsg Command & Per-Player Incoming Message Queue
// --------------------------------------------------------------------------
if (shouldRun(20)) {
    echo "\n20. Testing Messages Box (Msgs / #msgs), popmsg Command & Per-Player Incoming Message Queue...\n";

    $msgWorld = new \Dimx\World('msg_test_world', 'Message Test World');
    $roomLounge = new \Dimx\Room($msgWorld, 'The Lounge', 'lounge');
    $msgWorld->addRoom($roomLounge);
    $roomGarden = new \Dimx\Room($msgWorld, 'The Garden', 'garden');
    $msgWorld->addRoom($roomGarden);

    // 1. Queue is empty upon connection
    $pA = new Player($msgWorld, 'PlayerA', 'p_msg_a');
    $pA->move($roomLounge);
    $msgWorld->addPlayer($pA);

    $pB = new Player($msgWorld, 'PlayerB', 'p_msg_b');
    $pB->move($roomLounge);
    $msgWorld->addPlayer($pB);

    $pC = new Player($msgWorld, 'PlayerC', 'p_msg_c');
    $pC->move($roomGarden);
    $msgWorld->addPlayer($pC);

    assertTest(count($pA->getMessages()) === 0, "PlayerA message queue is empty upon connection");
    assertTest(count($pB->getMessages()) === 0, "PlayerB message queue is empty upon connection");
    assertTest(count($pC->getMessages()) === 0, "PlayerC message queue is empty upon connection");

    // 2. Pre-spawn message: spoken before PlayerD spawns
    $msgWorld->postChatMessage('Oracle', 'Old ancient whisper from centuries ago');
    assertTest(count($pA->getMessages()) === 1, "PlayerA received the world broadcast");

    $pD = new Player($msgWorld, 'PlayerD', 'p_msg_d');
    $pD->move($roomLounge);
    $msgWorld->addPlayer($pD);
    assertTest(count($pD->getMessages()) === 0, "PlayerD spawned later and does NOT see messages said before spawn");

    // 3. Spoken to all players in a specific room
    $msgWorld->postChatMessage('LoungeMaster', 'Drinks are on the house in the lounge!', null, 'lounge');
    $msgsA = $pA->getMessages();
    $msgsB = $pB->getMessages();
    $msgsC = $pC->getMessages();
    $msgsD = $pD->getMessages();

    assertTest(end($msgsA)['text'] === 'Drinks are on the house in the lounge!', "PlayerA in lounge received room broadcast");
    assertTest(end($msgsB)['text'] === 'Drinks are on the house in the lounge!', "PlayerB in lounge received room broadcast");
    assertTest(end($msgsD)['text'] === 'Drinks are on the house in the lounge!', "PlayerD in lounge received room broadcast");
    assertTest(count($msgsC) === 1 && $msgsC[0]['text'] === 'Old ancient whisper from centuries ago', "PlayerC in garden did NOT receive lounge room broadcast");

    // 4. Trimming to 10 most recent messages, oldest discarded first, arrival order
    for ($i = 1; $i <= 12; $i++) {
        $pA->deliverMessage('Announcer', "Message number {$i}", "id_{$i}");
    }
    $queueA = $pA->getMessages();
    assertTest(count($queueA) === 10, "Queue is capped at exactly 10 messages");
    assertTest($queueA[0]['text'] === 'Message number 3', "Oldest messages 1 and 2 were discarded (first message is now number 3)");
    assertTest(end($queueA)['text'] === 'Message number 12', "Most recent message is number 12 (arrival order maintained)");

    // 5. popmsg command discards / deletes the message from the player's queue
    $engine = new GameEngine($pm ?? null, 'msg_test_world');
    $targetPopId = $queueA[0]['id']; // "id_3"
    $engine->executeCommandAction($msgWorld, $pA, 'popmsg', $targetPopId, '');

    $queueAfterPop = $pA->getMessages();
    assertTest(count($queueAfterPop) === 9, "popmsg reduced message queue count from 10 to 9");
    assertTest($queueAfterPop[0]['id'] !== $targetPopId, "Target message '{$targetPopId}' was completely removed from the queue");

    // 6. UIhtml rendering with clickable '✕' before each message
    ob_start();
    $ui = new \Dimx\UIhtml($pA);
    $ui->render([
        'world'     => $msgWorld,
        'player'    => $pA,
        'room'      => $roomLounge,
        'messages'  => [],
        'chat'      => $pA->getMessages(),
        'lockError' => false,
    ]);
    $cellHtml = ob_get_clean();

    assertTest(str_contains($cellHtml, 'class="chat-pop-btn"'), "UIhtml renders clickable '✕' buttons with class chat-pop-btn");
    assertTest(str_contains($cellHtml, 'onclick="popMsg('), "UIhtml '✕' button calls popMsg() JavaScript handler");
    assertTest(str_contains($cellHtml, 'function popMsg('), "UIhtml script contains popMsg() implementation issuing cmd=popmsg");
    assertTest(str_contains($cellHtml, 'cmd=popmsg'), "UIhtml script sends cmd=popmsg to engine");
    assertTest(str_contains($cellHtml, '#101040') && str_contains($cellHtml, ':nth-child(odd)'), "UIhtml styles odd message lines with #101040");
    assertTest(str_contains($cellHtml, '#313161') && str_contains($cellHtml, ':nth-child(even)'), "UIhtml styles even message lines with #313161");
    assertTest(!str_contains($cellHtml, 'chat-line-time'), "UIhtml omits timestamp from message box lines");
}

// -------------------------------------------------------------
// Test 21: Testing ROOM DEFAULT Directive & Player Initial Spawn Room Resolution...
// -------------------------------------------------------------
if (shouldRun(22)) {
    echo "\n22. " . $sections[22]['title'] . "\n";
    $executedSections[] = 22;

    $testShowWorldId = 'test_showmode_' . bin2hex(random_bytes(4));
    $showEngine = new GameEngine($pm, $testShowWorldId, null, $baseUrl);
    $showWorld = $showEngine->loadOrSeedWorld();

    // Verify basic class defaults
    $npcChar = new \Dimx\Character($showWorld, 'TestNPC', 'test_npc', 'A test character');
    assertTest($npcChar->showmode === \Dimx\AdvObject::SHOW_ONSCREEN, "Character instance defaults to showmode SHOW_ONSCREEN (1)");
    assertTest($npcChar->getProperty('showmode') === 1, "Character getProperty('showmode') returns 1");

    $playerObj = new \Dimx\Player($showWorld, 'TestPlayerOne', 'p_test_1', 'Adventurer');
    assertTest($playerObj->showmode === \Dimx\AdvObject::SHOW_ONSCREEN, "Player instance defaults to showmode SHOW_ONSCREEN (1)");
    assertTest($playerObj->getProperty('showmode') === 1, "Player getProperty('showmode') returns 1");

    // Connect two players to the same room
    $pAlice = $showEngine->ensurePlayer($showWorld, 'AliceShowTester');
    $pBob   = $showEngine->ensurePlayer($showWorld, 'BobShowTester');
    assertTest($pAlice->showmode === \Dimx\AdvObject::SHOW_ONSCREEN, "Connected Player Alice has showmode = 1");
    assertTest($pBob->showmode === \Dimx\AdvObject::SHOW_ONSCREEN, "Connected Player Bob has showmode = 1");

    $beachRoom = $showWorld->getRoom('beach');
    $pAlice->move($beachRoom);
    $pBob->move($beachRoom);

    // Verify UIjson occupant and item snapshots expose showmode
    $jsonHandler = new \Dimx\UIjson($pAlice, $showEngine);
    $jsonSnapshot = $jsonHandler->buildRoomSnapshot($beachRoom, $pAlice);
    $bobJsonOccupant = null;
    foreach ($jsonSnapshot['occupants'] ?? [] as $occ) {
        if (($occ['name'] ?? '') === 'BobShowTester') {
            $bobJsonOccupant = $occ;
            break;
        }
    }
    assertTest($bobJsonOccupant !== null, "Alice sees Bob in UIjson room occupants");
    assertTest(array_key_exists('showmode', $bobJsonOccupant), "UIjson occupant snapshot exposes 'showmode'");
    assertTest($bobJsonOccupant['showmode'] === 1, "UIjson occupant snapshot Bob has showmode = 1 (ONSCREEN)");

    $appleJsonItem = null;
    foreach ($jsonSnapshot['items'] ?? [] as $itm) {
        if (($itm['id'] ?? '') === 'apple') {
            $appleJsonItem = $itm;
            break;
        }
    }
    if ($appleJsonItem !== null) {
        assertTest(array_key_exists('showmode', $appleJsonItem), "UIjson item snapshot exposes 'showmode'");
    }

    // Verify UIbot occupant and item snapshots DO NOT expose showmode
    $botHandler = new \Dimx\UIbot($pAlice, $showEngine);
    $botSnapshot = $botHandler->buildRoomSnapshot($beachRoom, $pAlice);
    $bobBotOccupant = null;
    foreach ($botSnapshot['occupants'] ?? [] as $occ) {
        if (($occ['name'] ?? '') === 'BobShowTester') {
            $bobBotOccupant = $occ;
            break;
        }
    }
    assertTest($bobBotOccupant !== null, "Alice sees Bob in UIbot room occupants");
    assertTest(!array_key_exists('showmode', $bobBotOccupant), "UIbot occupant snapshot omits 'showmode'");
    assertTest(!array_key_exists('showmode', $botSnapshot['items'][0] ?? []), "UIbot item snapshot omits 'showmode'");

    // Verify UIhtml rendering for onscreen player
    $htmlHandler = new \Dimx\UIhtml();
    $htmlHandler->setEngine($showEngine);
    $htmlHandler->setPlayer($pAlice);

    ob_start();
    $htmlHandler->render(['success' => true, 'world' => $showWorld, 'player' => $pAlice]);
    $htmlAliceView = ob_get_clean();

    assertTest(strpos($htmlAliceView, 'data-obj-name="BobShowTester"') !== false, "UIhtml includes BobShowTester on screen");
    assertTest(preg_match('/<img[^>]*class="[^"]*dimx-onscreen-obj[^"]*"[^>]*data-obj-name="BobShowTester"/i', $htmlAliceView) === 1, "UIhtml renders onscreen Bob as sprite on room scene");

    // Add offscreen NPC to room and verify UIhtml scene omission and Dashboard (#dashboard) People listing
    $offscreenNpc = new \Dimx\Character($showWorld, 'ShyGhost', 'shy_ghost', 'A ghost in the shadows');
    $offscreenNpc->showmode = \Dimx\AdvObject::SHOW_OFFSCREEN;
    $offscreenNpc->move($beachRoom);

    // In UIjson, offscreen NPC has showmode = 0
    $jsonSnapWithGhost = $jsonHandler->buildRoomSnapshot($beachRoom, $pAlice);
    $ghostJson = null;
    foreach ($jsonSnapWithGhost['occupants'] ?? [] as $occ) {
        if (($occ['id'] ?? '') === 'shy_ghost') {
            $ghostJson = $occ;
            break;
        }
    }
    assertTest($ghostJson !== null && $ghostJson['showmode'] === 0, "UIjson occupant ShyGhost has showmode = 0 (OFFSCREEN)");

    // In UIbot, offscreen NPC does not have showmode
    $botSnapWithGhost = $botHandler->buildRoomSnapshot($beachRoom, $pAlice);
    $ghostBot = null;
    foreach ($botSnapWithGhost['occupants'] ?? [] as $occ) {
        if (($occ['id'] ?? '') === 'shy_ghost') {
            $ghostBot = $occ;
            break;
        }
    }
    assertTest($ghostBot !== null && !array_key_exists('showmode', $ghostBot), "UIbot occupant ShyGhost omits 'showmode'");

    // In UIhtml, offscreen NPC is NOT on scene image, but is listed in Dashboard (#dashboard) People section
    ob_start();
    $htmlHandler->render(['success' => true, 'world' => $showWorld, 'player' => $pAlice]);
    $htmlWithGhost = ob_get_clean();

    assertTest(preg_match('/<img[^>]*data-obj-id="shy_ghost"[^>]*style="position:absolute/i', $htmlWithGhost) === 0, "UIhtml does NOT render offscreen character on room scene");
    assertTest(strpos($htmlWithGhost, '>People</div>') !== false, "UIhtml renders 'People' section in Dashboard (#dashboard) for offscreen characters");
    assertTest(strpos($htmlWithGhost, 'data-obj-name="ShyGhost"') !== false, "UIhtml lists ShyGhost in Dashboard (#dashboard) People section as clickable object");
}

// ----------------------------------------------------------------------------
// 23. SETS Section, RndSet Function & Initial Object Positioning (scroll3)
// ----------------------------------------------------------------------------
if (shouldRun(24)) {
    echo "\n24. Testing Room Description on Link Traversal (go command) & UIhtml Rendering...\n";

    $dxwFile = __DIR__ . '/../docs/dxw-examples/world2.dxw';
    $loader = new \Dimx\DxwLoader('http://localhost');
    $w2TestWorld = $loader->load($dxwFile, 'w2_trav_world');
    $travEngine = new \Dimx\GameEngine($pm, 'w2_trav', $dxwFile);

    $pTrav = new \Dimx\Player($w2TestWorld, 'Traveller', 'p_trav_1');
    $pTrav->setPanel($w2TestWorld->defaultPanel ?? \Dimx\Panel::createDefaultPanel());
    $spiaggia = $w2TestWorld->getRoom('spiaggia');
    $pTrav->move($spiaggia);

    // Initial check: in spiaggia
    assertTest($pTrav->getRoom()?->id === 'spiaggia', "Player begins in 'spiaggia'");

    // 1. Traverse link U to 'rocca'
    $travEngine->executeCommandAction($w2TestWorld, $pTrav, 'go', 'U', '');
    assertTest($pTrav->getRoom()?->id === 'rocca', "Move 'go U' took player to 'rocca'");

    $roccaMsgs = $pTrav->drainConsole();
    $roccaOutput = implode("\n", $roccaMsgs);
    $roccaDesc = $pTrav->getRoom()->evalDescription($pTrav);

    assertTest(!str_contains(strtolower($roccaOutput), 'you travel to'), "Engine output does NOT contain 'You travel to'");
    assertTest(!str_contains($roccaOutput, $roccaDesc), "Engine console output upon room arrival does NOT echo room description");

    // Render in UIhtml and verify console box displays room description and omits 'You travel to'
    ob_start();
    $htmlUiTrav = new \Dimx\UIhtml($pTrav);
    $htmlUiTrav->render([
        'world'     => $w2TestWorld,
        'player'    => $pTrav,
        'room'      => $pTrav->getRoom(),
        'messages'  => $roccaMsgs,
        'lockError' => false,
    ]);
    $roccaHtml = ob_get_clean();

    assertTest(!str_contains(strtolower($roccaHtml), 'you travel to'), "UIhtml does NOT render 'You travel to'");
    assertTest(str_contains($roccaHtml, $roccaDesc), "UIhtml console renders room description: '{$roccaDesc}'");

    // 2. Traverse link E to 'bungalow'
    $travEngine->executeCommandAction($w2TestWorld, $pTrav, 'go', 'E', '');
    assertTest($pTrav->getRoom()?->id === 'bungalow', "Move 'go E' took player to 'bungalow'");

    $bungalowMsgs = $pTrav->drainConsole();
    $bungalowOutput = implode("\n", $bungalowMsgs);
    $bungalowDesc = $pTrav->getRoom()->evalDescription($pTrav);

    assertTest(!str_contains(strtolower($bungalowOutput), 'you travel to'), "Engine output does NOT contain 'You travel to' for bungalow");
    assertTest(!str_contains($bungalowOutput, $bungalowDesc), "Engine console output upon arrival in bungalow does NOT echo description");

    ob_start();
    $htmlUiTrav->render([
        'world'     => $w2TestWorld,
        'player'    => $pTrav,
        'room'      => $pTrav->getRoom(),
        'messages'  => $bungalowMsgs,
        'lockError' => false,
    ]);
    $bungalowHtml = ob_get_clean();

    assertTest(!str_contains(strtolower($bungalowHtml), 'you travel to'), "UIhtml does NOT render 'You travel to' for bungalow");
    assertTest(str_contains($bungalowHtml, $bungalowDesc), "UIhtml console renders bungalow description: '{$bungalowDesc}'");
}

// -------------------------------------------------------------
// Test 25: Navigation Pad Missing LINK Name Fallback to Extended Direction Name (UIhtml)
// -------------------------------------------------------------
if (shouldRun(47)) {
    echo "\n--- Section 47: Testing SmallBasic Debug Instruction & GameEngine::log() Writing to debug.log ---\n";

    $sysFolder = Dimx\GameEngine::getSystemFolder();
    assertTest(is_dir($sysFolder), "GameEngine::getSystemFolder() returns a valid directory ('{$sysFolder}')");
    assertTest(file_exists($sysFolder . '/config.php') || file_exists($sysFolder . '/config.sample.php'), "GameEngine system folder contains config.php (or sample)");

    $logFile = $sysFolder . '/debug.log';
    $originalLog = file_exists($logFile) ? file_get_contents($logFile) : null;
    if (file_exists($logFile)) {
        @unlink($logFile);
    }

    $sec47WorldId = 'w_sec47_debug_' . rand(1000, 9999);
    $eng47 = new Dimx\GameEngine($pm, $sec47WorldId, null, $baseUrl, ['debugTo' => 'none']);
    assertTest($eng47->debugTo === 'none', "GameEngine::\$debugTo is 'none' when configured");

    // 1. engine.log() does nothing when engine.debugTo !== 'file'
    $ignoredMarker = "TEST_IGNORED_LOG_" . uniqid();
    $eng47->log($ignoredMarker);
    assertTest(!file_exists($logFile), "engine.log() does NOT create debug.log when debugTo is 'none'");

    // 2. ActionsRunner Debug does nothing when engine.debugTo !== 'file'
    $world47 = $eng47->loadOrSeedWorld();
    assertTest($world47->engine === $eng47, "loadOrSeedWorld() sets \$world->engine to GameEngine instance");
    $runner47 = new Dimx\ActionsRunner($world47);
    $runner47->run("Debug \"{$ignoredMarker}\"");
    assertTest(!file_exists($logFile), "ActionsRunner Debug statement does NOT write to debug.log when debugTo is 'none'");

    // 3. Enable debugTo = 'file' on engine
    $eng47->debugTo = 'file';
    $uniqueMarker1 = "TEST_INSTANCE_LOG_" . uniqid();
    $eng47->log($uniqueMarker1);
    assertTest(file_exists($logFile), "engine.log() creates debug.log when debugTo is 'file'");
    $content1 = (string)file_get_contents($logFile);
    assertTest(str_contains($content1, $uniqueMarker1 . "\n"), "engine.log() appends message to debug.log when debugTo is 'file'");

    // 4. SmallBasic Debug statement in ActionsRunner when debugTo is 'file'
    $uniqueMarker2 = "TEST_SB_DEBUG_" . uniqid();
    $res2 = $runner47->run("Debug \"{$uniqueMarker2}\"");
    assertTest($res2 === true, "ActionsRunner executes Debug statement successfully");
    $content2 = (string)file_get_contents($logFile);
    assertTest(str_contains($content2, $uniqueMarker2 . "\n"), "Debug statement written to debug.log via engine.log() when debugTo is 'file'");

    // 5. SmallBasic Debug with expressions and special variables ($AGENT, $WORLD)
    $pDebug = new Dimx\Player($world47, 'DebugHero', 'p_debug_hero');
    $world47->addPlayer($pDebug);
    $runner47Agent = new Dimx\ActionsRunner($world47, null, $pDebug);
    $runner47Agent->run('Debug "Player name: " + $AGENT.name');
    $content3 = (string)file_get_contents($logFile);
    assertTest(str_contains($content3, "Player name: DebugHero\n"), "Debug evaluates expression with \$AGENT.name and writes to debug.log");

    // 6. Debug with numeric value
    $runner47Agent->run('Debug 12345');
    $content4 = (string)file_get_contents($logFile);
    assertTest(str_contains($content4, "12345\n"), "Debug logs numeric scalar to debug.log");

    // 7. Debug with parentheses syntax
    $uniqueMarker3 = "TEST_PAREN_DEBUG_" . uniqid();
    $runner47Agent->run("Debug(\"{$uniqueMarker3}\")");
    $content5 = (string)file_get_contents($logFile);
    assertTest(str_contains($content5, $uniqueMarker3 . "\n"), "Debug(\"message\") syntax executed and logged correctly");

    // 8. Setting engine.debugTo back to 'none' suppresses further logging
    $eng47->debugTo = 'none';
    $suppressedMarker = "TEST_SUPPRESSED_" . uniqid();
    $eng47->log($suppressedMarker);
    $runner47Agent->run("Debug \"{$suppressedMarker}\"");
    $contentFinal = (string)file_get_contents($logFile);
    assertTest(!str_contains($contentFinal, $suppressedMarker), "Logging stops immediately when debugTo is changed back to 'none'");

    // Restore original debug.log content if previously existed, or remove test file
    if ($originalLog !== null) {
        file_put_contents($logFile, $originalLog);
    } else {
        @unlink($logFile);
    }

    @$pm->resetWorld($sec47WorldId);
}

// ---------------------------------------------------------------------------
// Section 48: Testing Resolution Handling & Login Form Cleanliness
// ---------------------------------------------------------------------------
if (shouldRun(48)) {
    echo "\n--- Section 48: Testing Resolution Handling & Login Form Cleanliness ---\n";

    // 1. Verify config.sample.php does NOT define deprecated 'screenSizeHelper'
    $sampleCfg = file_exists(__DIR__ . '/config.sample.php') ? require __DIR__ . '/config.sample.php' : [];
    assertTest(!array_key_exists('screenSizeHelper', $sampleCfg), "config.sample.php does not contain deprecated 'screenSizeHelper'");

    // 2. UIhtml login screen does NOT render 'Screen Size:' or 'screenSizeHelper'
    $engine = new \Dimx\GameEngine($pm, 'w_sec48', __DIR__ . '/../docs/dxw-examples/world1.dxw');
    $ui = new \Dimx\UIhtml(null, $engine);
    ob_start();
    $ui->renderLogin();
    $htmlLogin = ob_get_clean();
    assertTest(!str_contains($htmlLogin, 'Screen Size:'), "UIhtml login form does NOT render 'Screen Size:' dropdown");
    assertTest(!str_contains($htmlLogin, 'id="screenSizeHelper"'), "UIhtml login form does NOT render screenSizeHelper select");
    assertTest(!str_contains($htmlLogin, 'resizeScreen'), "UIhtml login form does NOT contain resizeScreen function");

    // 4. Verify 640x480 and 800x600 do NOT trigger vertical/smartphone disposition
    $testW48 = new \Dimx\World('w_test_48', 'Test World 48');
    $testR48 = new \Dimx\Room($testW48, 'r48', 'Test Room 48');
    $p640 = new \Dimx\Player($testW48, 'User640');
    $p640->screenWidth = 640;
    $p640->screenHeight = 480;
    $p640->isPortrait = ($p640->screenWidth < $p640->screenHeight);
    assertTest($p640->isPortrait === false, "640x480 is NOT portrait (screenWidth > screenHeight)");

    $p800 = new \Dimx\Player($testW48, 'User800');
    $p800->screenWidth = 800;
    $p800->screenHeight = 600;
    $p800->isPortrait = ($p800->screenWidth < $p800->screenHeight);
    assertTest($p800->isPortrait === false, "800x600 is NOT portrait (screenWidth > screenHeight)");

    ob_start();
    $ui640 = new \Dimx\UIhtml($p640);
    $ui640->render([
        'world'     => $testW48,
        'player'    => $p640,
        'room'      => $testR48,
        'messages'  => [],
        'lockError' => false,
    ]);
    $html640 = ob_get_clean();
    assertTest(!str_contains($html640, 'dimx-root dimx-portrait'), "640x480 does NOT render 'dimx-root dimx-portrait' class");
    assertTest(!str_contains($html640, '@media (max-width: 800px) and (orientation: landscape)'), "UIhtml does NOT contain '@media (max-width: 800px) and (orientation: landscape)' fallback");
}

// ---------------------------------------------------------------------------
// Section 49: Testing NewImage Function, ImagesFolder Resolution & Image Display/Assignment in SmallBasic
// ---------------------------------------------------------------------------
if (shouldRun(49)) {
    echo "\n--- Section 49: Testing NewImage Function, ImagesFolder Resolution & Image Display/Assignment in SmallBasic ---\n";

    // 1. Basic Image object properties and methods
    $sec49Img = new \Dimx\Image('https://example.com/test.png', 64, 128);
    assertTest($sec49Img instanceof \Dimx\Image, "Image is instance of Image");
    assertTest($sec49Img->src === 'https://example.com/test.png', "Image src matches");
    assertTest($sec49Img->url === 'https://example.com/test.png', "Image url property alias matches");
    assertTest($sec49Img->width === 64, "Image width matches 64");
    assertTest($sec49Img->height === 128, "Image height matches 128");
    assertTest(str_contains($sec49Img->toHtml(), '<img src="https://example.com/test.png"'), "Image toHtml renders img tag");
    assertTest(str_contains($sec49Img->html(), '<img src="https://example.com/test.png"'), "Image html() alias renders img tag");
    assertTest((string)$sec49Img === 'NewImage("https://example.com/test.png", 64, 128)', "Image string representation is NewImage(...)");

    // Setup isolated test world and runner
    $sec49World = new \Dimx\World();
    $sec49World->imagesFolder = 'https://www.dimensionex.net/pics/';

    $sec49Room = new \Dimx\Room($sec49World, 'Test Room 49', 'testroom49', 'A test room for section 49');
    $sec49World->rooms['testroom49'] = $sec49Room;

    $sec49Player = new \Dimx\Player($sec49World, 'Tester49', 'tester49', 'A test player for section 49');
    $sec49Player->room = $sec49Room;
    $sec49World->characters['tester49'] = $sec49Player;

    $sec49Runner = new \Dimx\ActionsRunner($sec49World, $sec49Player, $sec49Player, null);

    // 2. Relative URL absolutization with imagesFolder
    $imgRelative = $sec49Runner->run('return NewImage("cross.gif", 16, 16)');
    assertTest($imgRelative instanceof \Dimx\Image, "evalValue returns Image instance for NewImage");
    assertTest($imgRelative->src === 'https://www.dimensionex.net/pics/cross.gif', "Relative URL absolutized against imagesFolder");
    assertTest($imgRelative->width === 16, "Width evaluated correctly (16)");
    assertTest($imgRelative->height === 16, "Height evaluated correctly (16)");

    // 3. Absolute URL remains unaltered
    $imgAbsolute = $sec49Runner->run('return NewImage("https://cdn.example.com/icon.png", 32, 48)');
    assertTest($imgAbsolute instanceof \Dimx\Image, "evalValue returns Image for absolute URL");
    assertTest($imgAbsolute->src === 'https://cdn.example.com/icon.png', "Absolute URL remains unaltered");
    assertTest($imgAbsolute->width === 32, "Absolute image width is 32");
    assertTest($imgAbsolute->height === 48, "Absolute image height is 48");

    // 4. Property assignment to typed Image property (Room effect)
    $sec49Runner->run('testroom49.effect = NewImage("rain.gif", 100, 100)');
    assertTest($sec49Room->effect instanceof \Dimx\Image, "Room effect assigned Image instance");
    assertTest($sec49Room->effect->src === 'https://www.dimensionex.net/pics/rain.gif', "Room effect has correct URL");
    assertTest($sec49Room->effect->width === 100, "Room effect has correct width");

    // 5. Property assignment to directional images: testroom49.image("E") = NewImage(...)
    $sec49Runner->run('testroom49.image("E") = NewImage("east_view.jpg", 800, 600)');
    assertTest(isset($sec49Room->images['E']), "testroom49.images['E'] is set");
    assertTest($sec49Room->images['E'] instanceof \Dimx\Image, "testroom49.images['E'] is Image instance");
    assertTest($sec49Room->images['E']->src === 'https://www.dimensionex.net/pics/east_view.jpg', "Keyed directional image has correct URL");
    assertTest($sec49Room->images['E']->width === 800, "Keyed directional image has width 800");
    assertTest($sec49Room->images['E']->height === 600, "Keyed directional image has height 600");

    // 6. Direct Display / Print with NewImage
    $sec49Player->clientConsole = [];
    $sec49Runner->run('Display NewImage("badge.png", 50, 50)');
    $consoleOut = implode("\n", $sec49Player->clientConsole);
    assertTest(str_contains($consoleOut, '<img src="https://www.dimensionex.net/pics/badge.png"'), "Display NewImage outputs <img> HTML tag to console");
    assertTest(str_contains($consoleOut, 'width="50"') && str_contains($consoleOut, 'height="50"'), "Display NewImage HTML tag contains width and height");

    // 7. Display with explicit destination: Display $AGENT, NewImage(...)
    $sec49Player->clientConsole = [];
    $sec49Runner->run('Display $AGENT, NewImage("star.png", 24, 24)');
    $consoleOut2 = implode("\n", $sec49Player->clientConsole);
    assertTest(str_contains($consoleOut2, '<img src="https://www.dimensionex.net/pics/star.png"'), "Display \$AGENT, NewImage(...) outputs to \$AGENT console");

    // 8. String concatenation with NewImage
    $concatResult = $sec49Runner->run('return "Item icon: " + NewImage("sword.png", 32, 32)');
    assertTest(str_starts_with((string)$concatResult, 'Item icon: <img src="https://www.dimensionex.net/pics/sword.png"'), "String concat with NewImage renders HTML tag");
}

// ----------------------------------------------------------------------------
// 50. Testing Item Default SHOW_OFFSCREEN (0), Zoom on Click/Look & Fader Backdrop Overlay
// ----------------------------------------------------------------------------
if (shouldRun(50)) {
    echo "\n50. " . $sections[50]['title'] . "\n";
    $executedSections[] = 50;

    $sec50WorldId = 'test_zoom_' . bin2hex(random_bytes(4));
    $sec50Engine = new \Dimx\GameEngine($pm, $sec50WorldId, null, $baseUrl);
    $sec50World = $sec50Engine->loadOrSeedWorld();

    // 1. Verify Item class default showmode is SHOW_OFFSCREEN (0)
    $testItem = new \Dimx\Item($sec50World, 'TestScrewdriver', 'test_screwd', 'A handy screwdriver');
    assertTest($testItem->showmode === \Dimx\AdvObject::SHOW_OFFSCREEN, "Item instance defaults to showmode SHOW_OFFSCREEN (0)");
    assertTest($testItem->getProperty('showmode') === 0, "Item getProperty('showmode') returns 0");

    // Configure test room and items
    $zoomRoom = new \Dimx\Room($sec50World, 'Workshop', 'workshop', 'A messy workshop.');
    $sec50World->addRoom($zoomRoom);

    $zoomScrewd = new \Dimx\Item($sec50World, 'a screwdriver', 'screwd', 'A flathead screwdriver');
    $zoomScrewd->images['N'] = new \Dimx\Image('http://localhost/dimx/pics/cacciavite.gif', 113, 30);
    $zoomScrewd->pickable = true;
    $zoomScrewd->move($zoomRoom);

    $playerZ = $sec50Engine->ensurePlayer($sec50World, 'ZoomTester');
    $playerZ->move($zoomRoom);
    $pm->saveWorld($sec50World);

    // 2. Initial room render: offscreen item is NOT drawn on scene, but listed in dashboard items
    $resInit = $sec50Engine->processCommand('ZoomTester', 'look', '', '');
    $htmlInit = new \Dimx\UIhtml();
    $htmlInit->setEngine($sec50Engine);
    $htmlInit->setPlayer($playerZ);
    ob_start();
    $htmlInit->render($resInit);
    $viewInit = ob_get_clean();

    assertTest(strpos($viewInit, 'class="fader_box"') === false, "Initial scene has no fader overlay");
    assertTest(preg_match('/<img[^>]*data-obj-id="screwd"[^>]*style="[^"]*z-index:\s*256/i', $viewInit) === 0, "Initial scene does not render offscreen screwdriver on scene image");
    assertTest(strpos($viewInit, 'data-obj-id="screwd"') !== false, "Initial scene lists offscreen screwdriver in items list");

    // 3. Click offscreen screwdriver -> player focus is set to screwd
    $resClick = $sec50Engine->processCommand('ZoomTester', 'click', '', 'screwd');
    $pClicked = $resClick['player'];
    assertTest($pClicked->focus !== null && $pClicked->focus->id === 'screwd', "Click on 'screwd' sets player focus to screwd item");

    // 4. Render refreshed scene: fader overlay (layer 255) and zoomed image (layer 256)
    $htmlClick = new \Dimx\UIhtml();
    $htmlClick->setEngine($sec50Engine);
    $htmlClick->setPlayer($pClicked);
    ob_start();
    $htmlClick->render($resClick);
    $viewClick = ob_get_clean();

    assertTest(strpos($viewClick, 'class="fader_box"') !== false, "Refreshed scene renders fader overlay (.fader_box) at z-index 255");
    assertTest(strpos($viewClick, 'fadewhite.gif') !== false, "Fader overlay utilizes skin picFader image (fadewhite.gif)");
    assertTest(strpos($viewClick, "sendCmd('click','workshop')") !== false, "Fader overlay click handler returns focus to room container (workshop)");
    assertTest(preg_match('/<img[^>]*data-obj-id="screwd"[^>]*style="[^"]*z-index:\s*256/i', $viewClick) === 1, "Refreshed scene renders zoomed screwdriver at z-index 256 on top of fader");
    assertTest(strpos($viewClick, 'cacciavite.gif') !== false, "Zoomed item uses object image (cacciavite.gif)");

    // 5. Dismiss zoom by clicking the fader overlay (click on container workshop)
    $resDismiss = $sec50Engine->processCommand('ZoomTester', 'click', 'workshop', '');
    $pDismiss = $resDismiss['player'];
    assertTest($pDismiss->focus !== null && $pDismiss->focus->id === 'workshop', "Click on room container updates focus to container");
    assertTest(!str_contains(implode(' ', $resDismiss['messages']), "Containing:"), "Clicking room container does NOT output 'Containing:' contents list");

    ob_start();
    $htmlClick->render($resDismiss);
    $viewDismiss = ob_get_clean();
    assertTest(strpos($viewDismiss, 'class="fader_box"') === false, "Clicking fader dismisses fader overlay");
    assertTest(preg_match('/<img[^>]*data-obj-id="screwd"[^>]*style="[^"]*z-index:\s*256/i', $viewDismiss) === 0, "Dismissed view removes zoomed screwdriver from scene");

    // 6. Inspecting inventory item also zooms it with fader
    $resPick = $sec50Engine->processCommand('ZoomTester', 'pick', 'screwd', '');
    $pPicked = $resPick['player'];
    assertTest(isset($pPicked->contents['screwd']), "Player picked up screwdriver into inventory");

    $resInvClick = $sec50Engine->processCommand('ZoomTester', 'click', '', 'screwd');
    $pInv = $resInvClick['player'];
    assertTest($pInv->focus !== null && $pInv->focus->id === 'screwd', "Click on carried item sets player focus to item");

    ob_start();
    $htmlClick->render($resInvClick);
    $viewInvClick = ob_get_clean();
    assertTest(strpos($viewInvClick, 'class="fader_box"') !== false, "Clicking carried inventory item renders fader overlay");
    assertTest(preg_match('/<img[^>]*data-obj-id="screwd"[^>]*style="[^"]*z-index:\s*256/i', $viewInvClick) === 1, "Clicking carried inventory item renders zoomed image over fader");

    // 7. Dropping item resets focus and dismisses fader
    $resDrop = $sec50Engine->processCommand('ZoomTester', 'drop', 'screwd', '');
    $pDrop = $resDrop['player'];
    assertTest($pDrop->focus === null, "Dropping item clears player focus");

    ob_start();
    $htmlClick->render($resDrop);
    $viewDrop = ob_get_clean();
    assertTest(strpos($viewDrop, 'class="fader_box"') === false, "Scene after drop has no fader overlay");

    // 8. On-screen character zoom: ensure character is NOT rendered twice (excluded from scene under fader)
    $charTommy = new \Dimx\Character($sec50World, 'Tommy', 'tommy', 'A friendly companion');
    $charTommy->images['N'] = new \Dimx\Image('http://localhost/dimx/pics/tommy.gif', 80, 120);
    $charTommy->showmode = \Dimx\AdvObject::SHOW_ONSCREEN;
    $charTommy->move($zoomRoom);
    $sec50World->addCharacter($charTommy);
    $pm->saveWorld($sec50World);

    $resLookTommy = $sec50Engine->processCommand('ZoomTester', 'look', 'tommy', '');
    $pTommy = $resLookTommy['player'];
    assertTest($pTommy->focus !== null && $pTommy->focus->id === 'tommy', "Looking at on-screen character 'tommy' sets player focus");

    ob_start();
    $htmlClick->render($resLookTommy);
    $viewTommy = ob_get_clean();
    assertTest(strpos($viewTommy, 'class="fader_box"') !== false, "Looking at on-screen character renders fader overlay");
    $tommyImgCount = substr_count($viewTommy, 'data-obj-id="tommy"');
    assertTest($tommyImgCount === 1, "Focused on-screen character is rendered exactly once on scene (at z-index 256, not duplicated below fader)");
    assertTest(preg_match('/<img[^>]*data-obj-id="tommy"[^>]*style="[^"]*z-index:\s*256/i', $viewTommy) === 1, "Focused character rendered at z-index 256 over fader");

    // 9. On-screen objects and player/character exclusion from #dashboard Items list
    $zoomRoom->images['N'] = new \Dimx\Image('http://localhost/dimx/pics/workshop.jpg', 320, 200);
    $onscreenItem = new \Dimx\Item($sec50World, 'Anvil', 'anvil', 'A heavy iron anvil');
    $onscreenItem->showmode = \Dimx\AdvObject::SHOW_ONSCREEN;
    $onscreenItem->move($zoomRoom);
    $sec50World->addItem($onscreenItem);
    $pm->saveWorld($sec50World);

    $resRoomView = $sec50Engine->processCommand('ZoomTester', 'look', '', 'workshop');
    ob_start();
    $htmlClick->render($resRoomView);
    $viewDash = ob_get_clean();

    // Extract #dashboard contents
    $dashContent = '';
    if (preg_match('/<td id="dashboard">([\s\S]*?)<\/td>\s*<\/tr>\s*<\/table>/i', $viewDash, $mDash)) {
        $dashContent = $mDash[1];
    }

    // Extract Items list within dashboard
    $itemsSection = '';
    if (preg_match('/<div class="dimx-section-title" style="color:#38bdf8">Items<\/div>([\s\S]*?)<div class="dimx-section-title"/i', $dashContent, $mItems)) {
        $itemsSection = $mItems[1];
    }

    assertTest(strpos($itemsSection, 'data-obj-id="screwd"') !== false, "Off-screen item (screwdriver, showmode=0) appears in #dashboard Items list");
    assertTest(strpos($itemsSection, 'data-obj-id="anvil"') === false, "On-screen item (anvil, showmode=1) does NOT appear in #dashboard Items list");
    assertTest(strpos($itemsSection, 'data-obj-id="tommy"') === false, "On-screen character (tommy, showmode=1) does NOT appear in #dashboard Items list");
    assertTest(strpos($itemsSection, 'ZoomTester') === false, "Player does NOT appear in #dashboard Items list");
    assertTest(strpos($dashContent, 'data-obj-id="tommy"') === false, "On-screen character (tommy, showmode=1) does NOT appear in #dashboard People list");

    @$pm->resetWorld($sec50WorldId);
}

// ----------------------------------------------------------------------------
// Section 51: Testing SmallBasic DropItems Instruction & Notification
// ----------------------------------------------------------------------------
if (shouldRun(51)) {
    echo "\n51. " . $sections[51]['title'] . "\n";
    $executedSections[] = 51;

    $sec51World = new \Dimx\World();
    $sec51Room = new \Dimx\Room($sec51World, 'Vault Room', 'vault', 'A secure vault room.');
    $sec51World->addRoom($sec51Room);

    $sec51Player = new \Dimx\Player($sec51World, 'DropTester', 'droptester', 'A test player');
    $sec51Player->move($sec51Room, false);
    $sec51World->characters['droptester'] = $sec51Player;

    $item1 = new \Dimx\Item($sec51World, 'Coin', 'coin', 'A gold coin');
    $item2 = new \Dimx\Item($sec51World, 'Torch', 'torch', 'A wooden torch');
    $sec51World->addItem($item1);
    $sec51World->addItem($item2);

    $item1->move($sec51Player, false);
    $item2->move($sec51Player, false);
    assertTest(count($sec51Player->contents) === 2, "Player is carrying 2 items initially");
    assertTest(count($sec51Room->getItems()) === 0, "Room has 0 items initially");

    $runner51 = new \Dimx\ActionsRunner($sec51World, $sec51Player, $sec51Player, null);

    // 1. DropItems with explicit $AGENT
    $sec51Player->clientConsole = [];
    $resDrop = $runner51->run('DropItems $AGENT');
    assertTest($resDrop === true, "DropItems \$AGENT returns true");
    assertTest(count($sec51Player->contents) === 0, "Player has 0 items after DropItems");
    assertTest(count($sec51Room->getItems()) === 2, "Room contains 2 dropped items");
    assertTest(isset($sec51Room->contents['coin']), "Coin is now in room contents");
    assertTest(isset($sec51Room->contents['torch']), "Torch is now in room contents");
    $consoleText = implode(' ', $sec51Player->clientConsole);
    assertTest(str_contains($consoleText, "Everything I was carrying just slipped from my hands!"), "Player received slipped message: '{$consoleText}'");

    // 2. DropItems with no items: does not display message
    $sec51Player->clientConsole = [];
    $runner51->run('DropItems $AGENT');
    assertTest(empty($sec51Player->clientConsole), "DropItems on empty inventory does NOT display message");

    // 3. DropItems with no arguments defaults to $AGENT
    $item1->move($sec51Player, false);
    assertTest(count($sec51Player->contents) === 1, "Player carries coin again");
    $sec51Player->clientConsole = [];
    $runner51->run('DropItems');
    assertTest(count($sec51Player->contents) === 0, "DropItems with no argument dropped player items");
    assertTest(str_contains(implode(' ', $sec51Player->clientConsole), "Everything I was carrying just slipped from my hands!"), "Player received notification on DropItems without args");

    // 4. DropItems on NPC character ($TARGET)
    $npcGuard = new \Dimx\Character($sec51World, 'Guard', 'guard', 'A castle guard');
    $npcGuard->container = $sec51Room;
    $sec51World->characters['guard'] = $npcGuard;
    $shield = new \Dimx\Item($sec51World, 'Shield', 'shield', 'A wooden shield');
    $sec51World->addItem($shield);
    $shield->move($npcGuard, false);
    assertTest(count($npcGuard->contents) === 1, "Guard carries shield initially");

    $runnerWithTarget = new \Dimx\ActionsRunner($sec51World, $sec51Player, $sec51Player, $npcGuard);
    $runnerWithTarget->run('DropItems $TARGET');
    assertTest(count($npcGuard->contents) === 0, "Guard has 0 items after DropItems \$TARGET");
    assertTest(isset($sec51Room->contents['shield']), "Shield is now on room floor");

    // 5. DropItems on Item container (box)
    $box = new \Dimx\Item($sec51World, 'Chest', 'chest', 'A wooden chest');
    $sec51World->addItem($box);
    $box->move($sec51Room, false);
    $gem = new \Dimx\Item($sec51World, 'Ruby', 'ruby', 'A red ruby');
    $sec51World->addItem($gem);
    $gem->move($box, false);
    assertTest(count($box->contents) === 1, "Chest contains ruby initially");

    $runner51->run('DropItems chest');
    assertTest(count($box->contents) === 0, "Chest is empty after DropItems chest");
    assertTest(isset($sec51Room->contents['ruby']), "Ruby is now in vault room floor");
}

// =========================================================================
// SECTION 52: Testing Put / Insert Command
// =========================================================================
if (shouldRun(52)) {
    echo "\n52. " . $sections[52]['title'] . "\n";
    $executedSections[] = 52;

    // 1. Check UIhtml JavaScript definitions
    $uiSource = file_get_contents(__DIR__ . '/src/UIhtml.php');
    assertTest(strpos($uiSource, "'put':") !== false, "UIhtml COMMAND_DEFS contains 'put'");
    assertTest(preg_match("/'put':\s*\{\s*noArgs:\s*2,\s*model:\s*'OO'/s", $uiSource) === 1, "UIhtml 'put' defined with noArgs: 2 and model: 'OO'");
    assertTest(strpos($uiSource, "pendingCmd === 'put'") !== false, "UIhtml updatePrompt handles pendingCmd === 'put'");

    // 2. Setup isolated test world and engine
    $sec52World = new \Dimx\World('w_sec52', 'Section 52 Test');
    $engine52 = new \Dimx\GameEngine(new \Dimx\PersistenceManager([], sys_get_temp_dir()), 'w_sec52', null, '', []);
    $sec52Room = new \Dimx\Room($sec52World, 'VaultRoom', 'vaultRoom', 'A testing vault');
    $sec52World->addRoom($sec52Room);

    $sec52Player = new \Dimx\Player($sec52World, 'Alice', 'p_sec52');
    $sec52Player->capacity = 100;
    $sec52World->addPlayer($sec52Player);
    $sec52Player->container = $sec52Room;

    // 3. Put carried item into carried container (bag)
    $key = new \Dimx\Item($sec52World, 'Silver Key', 'silverKey', 'A shiny key');
    $key->volume = 1;
    $key->pickable = true;
    $sec52World->addItem($key);
    $key->move($sec52Player, false);

    $bag = new \Dimx\Item($sec52World, 'Leather Bag', 'bag', 'A spacious bag');
    $bag->capacity = 10;
    $bag->pickable = true;
    $sec52World->addItem($bag);
    $bag->move($sec52Player, false);

    assertTest(isset($sec52Player->contents['silverKey']), "Player carries key initially");
    assertTest(isset($sec52Player->contents['bag']), "Player carries bag initially");

    $sec52Player->drainConsole();
    $engine52->executeCommandAction($sec52World, $sec52Player, 'put', 'silverKey', 'bag');
    $msgs = $sec52Player->drainConsole();
    assertTest(!isset($sec52Player->contents['silverKey']), "Key is no longer directly in player contents");
    assertTest(isset($bag->contents['silverKey']), "Key is now inside bag contents");
    assertTest(count($msgs) > 0 && strpos($msgs[0], "You put the <b>Silver Key</b> into the <b>Leather Bag</b>.") !== false, "Output confirmed putting key into bag (got: '" . ($msgs[0] ?? '') . "')");

    // 4. Put room item into carried container
    $coin = new \Dimx\Item($sec52World, 'Gold Coin', 'goldCoin', 'A shiny gold coin');
    $coin->volume = 1;
    $coin->pickable = true;
    $sec52World->addItem($coin);
    $coin->move($sec52Room, false);
    assertTest(isset($sec52Room->contents['goldCoin']), "Coin is in room initially");

    $engine52->executeCommandAction($sec52World, $sec52Player, 'put', 'goldCoin', 'bag');
    assertTest(!isset($sec52Room->contents['goldCoin']), "Coin is no longer in room floor");
    assertTest(isset($bag->contents['goldCoin']), "Coin is now inside carried bag");

    // 5. Put carried item into open room container (chest)
    $gem = new \Dimx\Item($sec52World, 'Emerald', 'emerald', 'A green gem');
    $gem->volume = 2;
    $gem->pickable = true;
    $sec52World->addItem($gem);
    $gem->move($sec52Player, false);

    $chest = new \Dimx\Item($sec52World, 'Oak Chest', 'chest', 'A sturdy chest');
    $chest->openable = true;
    $chest->open = true;
    $chest->capacity = 20;
    $chest->pickable = true;
    $sec52World->addItem($chest);
    $chest->move($sec52Room, false);

    $engine52->executeCommandAction($sec52World, $sec52Player, 'put', 'emerald', 'chest');
    assertTest(!isset($sec52Player->contents['emerald']), "Emerald no longer carried by player");
    assertTest(isset($chest->contents['emerald']), "Emerald is now inside room chest");

    // 6. Rejection when destination container is closed
    $chest->open = false;
    $scroll = new \Dimx\Item($sec52World, 'Old Scroll', 'scroll', 'A parchment scroll');
    $scroll->volume = 1;
    $scroll->pickable = true;
    $sec52World->addItem($scroll);
    $scroll->move($sec52Player, false);

    $sec52Player->drainConsole();
    $engine52->executeCommandAction($sec52World, $sec52Player, 'put', 'scroll', 'chest');
    $msgs = $sec52Player->drainConsole();
    assertTest(isset($sec52Player->contents['scroll']), "Scroll remains in player inventory when chest is closed");
    assertTest(!isset($chest->contents['scroll']), "Scroll was not moved into closed chest");
    assertTest(count($msgs) > 0 && strpos($msgs[0], "It's closed.") !== false, "Output says 'It's closed.' (got: '" . ($msgs[0] ?? '') . "')");

    // 7. Rejection when capacity is exceeded
    $pouch = new \Dimx\Item($sec52World, 'Tiny Pouch', 'pouch', 'A small pouch');
    $pouch->capacity = 2;
    $pouch->pickable = true;
    $sec52World->addItem($pouch);
    $pouch->move($sec52Player, false);

    $boulder = new \Dimx\Item($sec52World, 'Heavy Boulder', 'boulder', 'A huge rock');
    $boulder->volume = 5;
    $boulder->pickable = true;
    $sec52World->addItem($boulder);
    $boulder->move($sec52Player, false);

    $sec52Player->drainConsole();
    $engine52->executeCommandAction($sec52World, $sec52Player, 'put', 'boulder', 'pouch');
    $msgs = $sec52Player->drainConsole();
    assertTest(isset($sec52Player->contents['boulder']), "Boulder remains in player inventory when capacity exceeded");
    assertTest(!isset($pouch->contents['boulder']), "Boulder not placed into pouch");
    assertTest(count($msgs) > 0 && strpos($msgs[0], "There's not enough room.") !== false, "Output says 'There's not enough room.' (got: '" . ($msgs[0] ?? '') . "')");

    // 8. Rejection when putting object into itself
    $sec52Player->drainConsole();
    $engine52->executeCommandAction($sec52World, $sec52Player, 'put', 'bag', 'bag');
    $msgs = $sec52Player->drainConsole();
    assertTest(count($msgs) > 0 && strpos($msgs[0], "Cannot put object into itself!") !== false, "Putting object into itself rejected with message");

    // 9. Rejection when object does not exist
    $sec52Player->drainConsole();
    $engine52->executeCommandAction($sec52World, $sec52Player, 'put', 'nonExistentItem', 'bag');
    $msgs = $sec52Player->drainConsole();
    assertTest(count($msgs) > 0 && strpos($msgs[0], "does not exist anymore") !== false, "Non-existent item rejected");

    // 10. Text syntax parsing: 'put scroll into pouch' via $param
    $sec52Player->drainConsole();
    $engine52->executeCommandAction($sec52World, $sec52Player, 'put', 'scroll into pouch', '');
    assertTest(isset($pouch->contents['scroll']), "Text syntax 'put scroll into pouch' successfully moved scroll into pouch");

    // 11. Event triggering & cancellation via onReceiveItem
    $magicBox = new \Dimx\Item($sec52World, 'Magic Box', 'magicBox', 'A cursed container');
    $magicBox->capacity = 10;
    $magicBox->pickable = true;
    $sec52World->addItem($magicBox);
    $magicBox->move($sec52Player, false);

    $dagger = new \Dimx\Item($sec52World, 'Dagger', 'dagger', 'A sharp dagger');
    $dagger->volume = 1;
    $dagger->pickable = true;
    $sec52World->addItem($dagger);
    $dagger->move($sec52Player, false);

    // Cancel put via onReceiveItem
    $sec52World->events['magicBox.onReceiveItem'] = new \Dimx\Event('magicBox.onReceiveItem', 'EVENT', function ($owner, $agent, $target) {
        return 0;
    });
    $sec52Player->drainConsole();
    $engine52->executeCommandAction($sec52World, $sec52Player, 'put', 'dagger', 'magicBox');
    assertTest(isset($sec52Player->contents['dagger']), "Dagger restored to player when onReceiveItem returns 0 (cancelled)");
    assertTest(!isset($magicBox->contents['dagger']), "Magic box does not contain dagger");

    // Allow put when event returns 1
    $sec52World->events['magicBox.onReceiveItem'] = new \Dimx\Event('magicBox.onReceiveItem', 'EVENT', function ($owner, $agent, $target) {
        return 1;
    });
    $engine52->executeCommandAction($sec52World, $sec52Player, 'put', 'dagger', 'magicBox');
    assertTest(isset($magicBox->contents['dagger']), "Dagger moved into magicBox when onReceiveItem returns 1");
}

// Section 53: Testing SmallBasic NewItem Function / Instruction & Snapshot Property Recursion Protection
if (shouldRun(53)) {
    echo "\n53. " . $sections[53]['title'] . "\n";
    $executedSections[] = 53;

    $sec53World = new \Dimx\World('w_sec53', 'Section 53 Test World');
    $sec53World->imagesFolder = 'https://example.com/images/';
    $vault = new \Dimx\Room($sec53World, 'Secure Vault', 'vault', 'A high-security underground vault.');
    $sec53World->addRoom($vault);

    $sec53Runner = new \Dimx\ActionsRunner($sec53World, null, null, null);

    // 1. NewItem as SmallBasic function returning item ID
    $newId = $sec53Runner->run('return NewItem(vault, "Gold Ingot", "A heavy solid gold ingot.", NewImage("gold.png", 32, 32), "type=treasure.gold,pickable=1,showmode=1,showx=120,showy=80")');
    assertTest(is_string($newId) && str_starts_with($newId, '_obj'), "NewItem function returns dynamic object ID string (got: " . var_export($newId, true) . ")");
    assertTest(isset($sec53World->items[$newId]), "Created item registered in world items collection");
    assertTest(isset($vault->contents[$newId]), "Created item placed in target container (vault)");

    $goldItem = $sec53World->items[$newId];
    assertTest($goldItem->name === 'Gold Ingot', "Created item has correct name");
    assertTest($goldItem->description === 'A heavy solid gold ingot.', "Created item has correct description");
    assertTest($goldItem->pickable === true, "Created item has pickable attribute set");
    assertTest($goldItem->showmode === 1, "Created item has showmode attribute set");
    assertTest($goldItem->showX === 120, "Created item has showX coordinate set");
    assertTest($goldItem->showY === 80, "Created item has showY coordinate set");
    assertTest($goldItem->images['N'] instanceof \Dimx\Image, "Created item has image set on facing 'N'");
    assertTest($goldItem->images['N']->width === 32 && $goldItem->images['N']->height === 32, "Created item image has correct dimensions");

    // 2. NewItem as SmallBasic statement / instruction
    $sec53Runner->run('NewItem vault, "Silver Goblet", "A polished goblet.", null, "type=treasure.goblet,pickable=1"');
    $goblet = null;
    foreach ($vault->getItems() as $itm) {
        if ($itm->name === 'Silver Goblet') {
            $goblet = $itm;
            break;
        }
    }
    assertTest($goblet instanceof \Dimx\Item, "NewItem statement creates item inside target container");
    assertTest($goblet !== null && $goblet->pickable === true, "NewItem statement parses attributes properly");

    // 3. Overflow capacity handling (places in container's container when container full)
    $pouch = new \Dimx\Item($sec53World, 'Tiny Pouch', 'pouch', 'A very small pouch.', null, 'capacity=2', 2, 1);
    $sec53World->addItem($pouch);
    $pouch->move($vault, false);

    // Create item volume=5 into pouch (capacity 2): should escalate to vault
    $bigRockId = $sec53Runner->run('return NewItem(pouch, "Big Rock", "Too big for pouch.", null, "volume=5")');
    $bigRock = $sec53World->items[$bigRockId] ?? null;
    assertTest($bigRock instanceof \Dimx\Item, "NewItem creates item when container overflows");
    assertTest($bigRock !== null && $bigRock->container === $vault, "Overflowing item placed into container's container (vault)");

    // 4. Snapshot property recursion protection (when an array property contains object references with circular links)
    $player53 = new \Dimx\Player($sec53World, 'Explorer', 'p_53');
    $sec53World->addPlayer($player53);
    $sec53World->setProperty('players_list', [$player53, '(empty)']);

    $snapshotHtml = $sec53World->htmlAdminSnapshot();
    assertTest(is_string($snapshotHtml) && strlen($snapshotHtml) > 0, "htmlAdminSnapshot() succeeds without TypeError on circular object properties");
    assertTest(str_contains($snapshotHtml, 'Explorer'), "htmlAdminSnapshot() formats object in array using its string representation");
}

// -------------------------------------------------------------
// Test 54: SmallBasic SetLen() Function, For .. To ... Next & For Each Constructs with debug.log
// -------------------------------------------------------------
if (shouldRun(54)) {
    echo "\n--- Section 54: Testing SmallBasic SetLen() Function, For .. To ... Next & For Each Constructs with debug.log ---\n";

    $sec54WorldId = 'w_sec54_' . rand(1000, 9999);
    $eng54 = new \Dimx\GameEngine($pm, $sec54WorldId, null, $baseUrl, ['debugTo' => 'file']);
    $sec54World = new \Dimx\World($sec54WorldId, 'Section 54 World');
    $sec54World->engine = $eng54;

    $sysFolder = \Dimx\GameEngine::getSystemFolder();
    $logFile = $sysFolder . '/debug.log';
    if (file_exists($logFile)) {
        @unlink($logFile);
    }

    $sec54World->sets['players'] = ['(empty)', '(empty)'];
    $sec54World->setProperty('players', ['(empty)', '(empty)']);

    $runner54 = new \Dimx\ActionsRunner($sec54World);

    // 1. SetLen on existing array
    $len1 = $runner54->run('return SetLen(players)');
    assertTest($len1 === 2, "SetLen(players) returns count of elements in array: expected 2, got " . var_export($len1, true));

    // 2. SetLen on non-collection returns -1 (SmallBasic spec)
    $lenNon = $runner54->run('return SetLen("not_a_collection")');
    assertTest($lenNon === -1, "SetLen on non-collection returns -1 (got " . var_export($lenNon, true) . ")");

    $lenNum = $runner54->run('return SetLen(123)');
    assertTest($lenNum === -1, "SetLen on numeric scalar returns -1 (got " . var_export($lenNum, true) . ")");

    // 3. SetLen on Split expression
    $lenSplit = $runner54->run('return SetLen(Split("alpha,beta,gamma,delta", ","))');
    assertTest($lenSplit === 4, "SetLen(Split(...)) evaluates expression and returns element count: expected 4, got " . var_export($lenSplit, true));

    // 4. For .. To ... Next basic iteration & variable assignment
    $scriptFor = <<<'SB'
Dim sum = 0
For i = 1 To 5
    sum = sum + i
Next
Return sum
SB;
    $sumRes = $runner54->run($scriptFor);
    assertTest($sumRes === 15, "For 1 To 5 sums values to 15 (got " . var_export($sumRes, true) . ")");

    // 5. For .. To ... Next with Step
    $scriptStep = <<<'SB'
Dim total = 0
For k = 1 To 9 Step 2
    total = total + k
Next
Return total
SB;
    $stepRes = $runner54->run($scriptStep);
    assertTest($stepRes === 25, "For 1 To 9 Step 2 calculates 1+3+5+7+9 = 25 (got " . var_export($stepRes, true) . ")");

    // 6. For loop early return via Return statement
    $scriptEarly = <<<'SB'
For i = 1 To 10
    If i = 3
        Return i * 10
    End_If
Next
Return 0
SB;
    $earlyRes = $runner54->run($scriptEarly);
    assertTest($earlyRes === 30, "For loop exits immediately on Return statement (got " . var_export($earlyRes, true) . ")");

    // 7. For Each ... In ... Next construct
    $scriptForEach = <<<'SB'
Dim fruits = Split("apple/orange/pear", "/")
Dim collected = ""
For Each f In fruits
    collected = collected + f + " "
Next
Return collected
SB;
    $eachRes = $runner54->run($scriptForEach);
    assertTest(trim((string)$eachRes) === "apple orange pear", "For Each iterates collection elements (got " . var_export($eachRes, true) . ")");

    // 8. Verify logging to debug.log matches DimensioneX Java logger.debug()
    $logContent = file_exists($logFile) ? (string)file_get_contents($logFile) : '';
    assertTest(str_contains($logContent, "Looping. Index= 1\n"), "debug.log contains 'Looping. Index= 1' from For loop");
    assertTest(str_contains($logContent, "Looping. Index= 5\n"), "debug.log contains 'Looping. Index= 5' from For loop");
    assertTest(str_contains($logContent, "Looping. Set element # 0\n"), "debug.log contains 'Looping. Set element # 0' from For Each loop");
    assertTest(str_contains($logContent, "Looping. Set element # 2\n"), "debug.log contains 'Looping. Set element # 2' from For Each loop");

    // 9. Integration test on fourinline.dxw line 146
    $loaderFour = new \Dimx\DxwLoader();
    $fourWorld = $loaderFour->load(__DIR__ . '/../docs/dxw-examples/fourinline.dxw');
    $fourWorld->engine = $eng54;

    // Initialize world
    $fourWorld->fireEvent('onStart', $fourWorld);
    $initialSit = (string)$fourWorld->fireEvent('getSituation', $fourWorld);
    assertTest(str_contains($initialSit, 'target is: 2'), "fourinline getSituation contains target players: 2");
    assertTest(str_contains($initialSit, '<LI>player#1 (empty)'), "fourinline line 146 For loop generates player#1 (empty)");
    assertTest(str_contains($initialSit, '<LI>player#2 (empty)'), "fourinline line 146 For loop generates player#2 (empty)");

    // Add first player
    $pTest1 = new \Dimx\Player($fourWorld, 'Champion', 'p_champ');
    $fourWorld->addPlayer($pTest1);
    $fourWorld->fireEvent('onNew', $fourWorld, $pTest1);
    $sitWithP1 = (string)$fourWorld->fireEvent('getSituation', $fourWorld);
    assertTest(str_contains($sitWithP1, 'Champion'), "fourinline getSituation includes player 1 name 'Champion'");
    assertTest(str_contains($sitWithP1, 'Waiting for 1 more player'), "fourinline getSituation calculates (target_players - nplayers) = 1");

    @$pm->resetWorld($sec54WorldId);
}

// -------------------------------------------------------------
// Test 55: Function & Sub Logging & Indentation (incIndent/decIndent)
// -------------------------------------------------------------
if (shouldRun(55)) {
    echo "\n--- Section 55: Testing Function & Sub Logging (Calling FUNCTION/SUB with varspace) & Indentation (incIndent/decIndent) ---\n";
    $executedSections[] = 55;

    $sec55WorldId = 'w_sec55_' . rand(1000, 9999);
    $eng55 = new \Dimx\GameEngine($pm, $sec55WorldId, null, $baseUrl, ['debugTo' => 'file']);
    $sec55World = new \Dimx\World($sec55WorldId, 'Section 55 World');
    $sec55World->engine = $eng55;

    $sysFolder = \Dimx\GameEngine::getSystemFolder();
    $logFile = $sysFolder . '/debug.log';
    if (file_exists($logFile)) {
        @unlink($logFile);
    }

    // 1. Test incIndent() and decIndent() on GameEngine directly
    assertTest($eng55->indentLevel === 0, "GameEngine indentLevel starts at 0 (got {$eng55->indentLevel})");
    $eng55->incIndent();
    assertTest($eng55->indentLevel === 1, "GameEngine incIndent increments to 1 (got {$eng55->indentLevel})");
    $eng55->incIndent();
    assertTest($eng55->indentLevel === 2, "GameEngine incIndent increments to 2 (got {$eng55->indentLevel})");
    $eng55->decIndent();
    assertTest($eng55->indentLevel === 1, "GameEngine decIndent decrements to 1 (got {$eng55->indentLevel})");
    $eng55->decIndent();
    assertTest($eng55->indentLevel === 0, "GameEngine decIndent decrements to 0 (got {$eng55->indentLevel})");
    $eng55->decIndent();
    assertTest($eng55->indentLevel === 0, "GameEngine decIndent does not go below 0 (got {$eng55->indentLevel})");

    // 2. Test World and ActionsRunner forwarding incIndent / decIndent
    $sec55World->incIndent();
    assertTest($eng55->indentLevel === 1, "World incIndent forwards to GameEngine (got {$eng55->indentLevel})");
    $runner55 = new \Dimx\ActionsRunner($sec55World);
    $runner55->incIndent();
    assertTest($eng55->indentLevel === 2, "ActionsRunner incIndent forwards to GameEngine (got {$eng55->indentLevel})");
    $runner55->decIndent();
    $sec55World->decIndent();
    assertTest($eng55->indentLevel === 0, "World/ActionsRunner decIndent restores level to 0 (got {$eng55->indentLevel})");

    // 3. Test nested functions in SmallBasic with Event::fire
    // Function outerFn calling innerFn(x)
    $innerEvt = new \Dimx\Event('innerFn', 'FUNCTION', null, ['x'], 'test55.dxw', 'test55.dxw', 10);
    $innerEvt->actions = <<<'SB'
Debug "Inside innerFn with x=" + x
Return x * 2
SB;
    $sec55World->registerEvent($innerEvt);

    $outerEvt = new \Dimx\Event('outerFn', 'FUNCTION', null, ['n'], 'test55.dxw', 'test55.dxw', 20);
    $outerEvt->actions = <<<'SB'
Debug "Entering outerFn"
Dim res = innerFn(n)
Debug "Leaving outerFn with res=" + res
Return res + 1
SB;
    $sec55World->registerEvent($outerEvt);

    $resOuter = $sec55World->fireEvent('outerFn', $sec55World, null, null, [5]);
    assertTest($resOuter === 11, "outerFn(5) correctly computes 5*2+1 = 11 (got " . var_export($resOuter, true) . ")");
    assertTest($eng55->indentLevel === 0, "indentLevel restores to 0 after outerFn finishes (got {$eng55->indentLevel})");

    $logContent55 = file_exists($logFile) ? file_get_contents($logFile) : '';

    // Verify Calling FUNCTION signature and varspace
    assertTest(str_contains($logContent55, "Calling FUNCTION outerFn(n) - test55.dxw(20) with varspace: {input={}, n=5}"),
        "debug.log contains 'Calling FUNCTION outerFn(n) - test55.dxw(20) with varspace: {input={}, n=5}'");

    // Verify nested call is indented with \t
    assertTest(str_contains($logContent55, "\tCalling FUNCTION innerFn(x) - test55.dxw(10) with varspace: {input={}, x=5}"),
        "debug.log contains indented '\\tCalling FUNCTION innerFn(x) - test55.dxw(10) with varspace: {input={}, x=5}'");

    // Verify inner logs have double indentation \t\t
    assertTest(str_contains($logContent55, "\t\tInside innerFn with x=5"),
        "debug.log contains double-indented '\\t\\tInside innerFn with x=5'");

    // Verify Returning: 10 has single indent
    assertTest(str_contains($logContent55, "\tReturning: 10"),
        "debug.log contains '\\tReturning: 10'");

    // Verify outer Returning: 11 has 0 indent
    assertTest(str_contains($logContent55, "Returning: 11"),
        "debug.log contains root-level 'Returning: 11'");

    @$pm->resetWorld($sec55WorldId);
}

// --------------------------------------------------------------------------
// 56. If..Else Construct Logging (IF ..., TRUE/FALSE, nested running & IF executed)
// --------------------------------------------------------------------------
if (shouldRun(56)) {
    echo "\n--- Section 56: Testing If..Else Construct Logging (IF ..., TRUE/FALSE, nested running & IF executed) ---\n";

    $logFile = \Dimx\GameEngine::getSystemFolder() . DIRECTORY_SEPARATOR . 'debug.log';
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
    }

    $sec56WorldId = 'w_sec56_' . mt_rand(1000, 9999);
    $eng56 = new \Dimx\GameEngine($pm, $sec56WorldId);
    $fourDFile = __DIR__ . '/../docs/dxw-examples/fourinline.dxw';
    $loaderFour = new \Dimx\DxwLoader();
    $fourWorld = $loaderFour->load($fourDFile, $sec56WorldId);
    $fourWorld->engine = $eng56;

    // Start world and setup 2 players in play mode
    $fourWorld->fireEvent('onStart', $fourWorld);
    $p1 = new \Dimx\Player($fourWorld, 'Cris', 'p1_test');
    $p2 = new \Dimx\Player($fourWorld, 'ciccio', 'p2_test');
    $fourWorld->addPlayer($p1);
    $fourWorld->addPlayer($p2);
    $fourWorld->fireEvent('onNew', $fourWorld, $p1);
    $fourWorld->fireEvent('onNew', $fourWorld, $p2);

    // Call play() to set mode="play"
    $fourWorld->fireEvent('play', $fourWorld);
    $fourWorld->setProperty('nowplays', 1);

    // Clear log before onLook
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
    }

    // Now trigger onLook for player 1 on a cell
    $cell = $fourWorld->getItem('_obj16') ?? $fourWorld->getItem('_obj0');
    if ($cell !== null) {
        $fourWorld->fireEvent('onLook', $cell, $p1);
    }

    $log56 = file_exists($logFile) ? file_get_contents($logFile) : '';

    assertTest(str_contains($log56, "EVENT onLook executing\n"), "debug.log contains root 'EVENT onLook executing'");
    assertTest(str_contains($log56, "\tfourinline.dxw(268): running\n"), "debug.log contains '\\tfourinline.dxw(268): running'");
    assertTest(str_contains($log56, "\tIF mode...\n"), "debug.log contains '\\tIF mode...'");
    assertTest(str_contains($log56, "\tFALSE\n"), "debug.log contains '\\tFALSE'");
    assertTest(str_contains($log56, "\tfourinline.dxw(271): IF executed\n"), "debug.log contains '\\tfourinline.dxw(271): IF executed'");
    assertTest(str_contains($log56, "\tIF \$AGENT...\n"), "debug.log contains '\\tIF \$AGENT...'");
    assertTest(str_contains($log56, "\tTRUE\n"), "debug.log contains '\\tTRUE'");
    assertTest(str_contains($log56, "\t\tfourinline.dxw(273): running\n"), "debug.log contains '\\t\\tfourinline.dxw(273): running'");
    assertTest(str_contains($log56, "\t\tIF Not(\$OWNER...\n"), "debug.log contains '\\t\\tIF Not(\$OWNER...'");
    assertTest(str_contains($log56, "\t\tFALSE\n"), "debug.log contains '\\t\\tFALSE'");
    assertTest(str_contains($log56, "\t\tfourinline.dxw(275): IF executed\n"), "debug.log contains '\\t\\tfourinline.dxw(275): IF executed'");

    @$pm->resetWorld($sec56WorldId);
}

// --------------------------------------------------------------------------
// 57. Request Interaction Logging (USER, ROOM, STATUS, COMMAND, VIEW, DATE & input=...)
// --------------------------------------------------------------------------
if (shouldRun(57)) {
    echo "\n--- Section 57: Testing Request Interaction Logging (USER, ROOM, STATUS, COMMAND, VIEW, DATE & input=...) ---\n";

    $logFile = \Dimx\GameEngine::getSystemFolder() . DIRECTORY_SEPARATOR . 'debug.log';
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
    }

    $sec57WorldId = 'w_sec57_' . mt_rand(1000, 9999);
    $eng57 = new \Dimx\GameEngine($pm, $sec57WorldId);
    $fourDFile = __DIR__ . '/../docs/dxw-examples/fourinline.dxw';
    $loaderFour = new \Dimx\DxwLoader();
    $fourWorld = $loaderFour->load($fourDFile);
    $fourWorld->id = $sec57WorldId;
    $fourWorld->engine = $eng57;

    // Start world and setup 2 players in play mode
    $fourWorld->fireEvent('onStart', $fourWorld);
    $boardRoom = $fourWorld->getRoom('board');
    $p1 = new \Dimx\Player($fourWorld, 'Cris', 'p1_sec57');
    $p2 = new \Dimx\Player($fourWorld, 'ciccio', 'p2_sec57');
    $fourWorld->addPlayer($p1);
    $fourWorld->addPlayer($p2);
    if ($boardRoom !== null) {
        $p1->move($boardRoom);
        $p2->move($boardRoom);
    }
    $fourWorld->fireEvent('onNew', $fourWorld, $p1);
    $fourWorld->fireEvent('onNew', $fourWorld, $p2);
    $fourWorld->fireEvent('play', $fourWorld);
    $pm->saveWorld($fourWorld);

    // Clear log before click interaction
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
    }

    // Now issue processCommand simulating click on cell item i!_obj0 with view=scene
    $eng57->processCommand('Cris', 'click', 'i!_obj0', '', null, 5701, false, false, 'p1_sec57', null, null, null, 'scene', [
        'arg0' => 'i!_obj0',
        'arg1' => '',
        'cmd' => 'click',
        'txtBox' => '',
        'view' => 'scene',
    ]);

    $log57 = file_exists($logFile) ? file_get_contents($logFile) : '';

    // Verify interaction header: USER, ROOM, STATUS, COMMAND, VIEW, DATE
    assertTest(str_contains($log57, 'USER: Cris (p1_sec57)'), "debug.log contains 'USER: Cris (p1_sec57)'");
    assertTest(str_contains($log57, 'ROOM: Four-in-line (board)'), "debug.log contains 'ROOM: Four-in-line (board)'");
    assertTest(str_contains($log57, 'STATUS: CONNECTED'), "debug.log contains 'STATUS: CONNECTED'");
    assertTest(str_contains($log57, 'COMMAND: click(i!_obj0,)'), "debug.log contains 'COMMAND: click(i!_obj0,)'");
    assertTest(str_contains($log57, 'VIEW: scene'), "debug.log contains 'VIEW: scene'");
    assertTest(str_contains($log57, date('Y-m-d')), "debug.log contains current DATE (" . date('Y-m-d') . ")");

    // Verify world.fireEvent onLook with owner string, agent id, target, and input dictionary
    assertTest(str_contains($log57, 'world.fireEvent: onLook($OWNER=_obj0 (column 1),$AGENT=p1_sec57,$TARGET='),
        "debug.log contains 'world.fireEvent: onLook(\$OWNER=_obj0 (column 1),\$AGENT=p1_sec57,\$TARGET=...'");
    assertTest(str_contains($log57, 'input={arg0=(S|i!_obj0|0.0), arg1=(S||0.0), cmd=(S|click|0.0), txtBox=(S||0.0), view=(S|scene|0.0)}'),
        "debug.log contains DimensioneX input dictionary with string tokens");

    @$pm->resetWorld($sec57WorldId);
}

// ---------------------------------------------------------------------------
// Section 58: Testing SmallBasic Reset Instruction & GameEngine::resetWorld()
// ---------------------------------------------------------------------------
if (shouldRun(58)) {
    echo "\n--- Section 58: Testing SmallBasic Reset Instruction & GameEngine::resetWorld() ---\n";

    $sec58WorldId = 'w_sec58_reset_' . rand(1000, 9999);
    $eng58 = new Dimx\GameEngine($pm, $sec58WorldId, __DIR__ . '/../docs/dxw-examples/world1.dxw', $baseUrl, ['debugTo' => 'file']);

    $sysFolder = Dimx\GameEngine::getSystemFolder();
    $logFile = $sysFolder . '/debug.log';
    if (file_exists($logFile)) {
        @unlink($logFile);
    }

    // 1. ActionsRunner sets $world->reset = true and logs "Action: RESET"
    $world58 = $eng58->loadOrSeedWorld();
    assertTest($world58->reset === false, "World initial reset flag is false");

    $runner58 = new Dimx\ActionsRunner($world58);
    $resReset = $runner58->run('Reset');
    assertTest($resReset === true, "ActionsRunner executes Reset statement successfully");
    assertTest($world58->reset === true, "World reset flag set to true by Reset instruction");

    $logContent = file_exists($logFile) ? file_get_contents($logFile) : '';
    assertTest(str_contains($logContent, "Action: RESET\n"), "debug.log contains 'Action: RESET'");

    // 2. Parenthesized Reset() syntax
    $world58->reset = false;
    $runner58->run('Reset()');
    assertTest($world58->reset === true, "World reset flag set to true by parenthesized Reset() instruction");

    // 3. Optional broadcast message argument with Reset
    $world58->reset = false;
    $p1 = new Dimx\Player($world58, 'Hero58', 'p_hero58');
    $world58->addPlayer($p1);
    $runner58->run('Reset "World is restarting now"');
    assertTest($world58->reset === true, "Reset with message sets world reset flag to true");
    $p1Msgs = $p1->drainConsole();
    assertTest(count($p1Msgs) > 0 && str_contains($p1Msgs[0], 'World is restarting now'), "Reset broadcasts notification message to players");

    // 4. End-to-end command execution triggering Reset
    // Set up an item with onUse event that executes Reset
    $world58->reset = false;
    $beach = $world58->getRoom('beach') ?? reset($world58->rooms);
    if ($beach !== null) {
        $p1->move($beach);
        $resetItem = new Dimx\Item($world58, 'Reset Crystal', 'reset_crystal');
        $world58->addItem($resetItem);
        $resetItem->move($beach);
        $evtReset = new Dimx\Event('reset_crystal.onUse', 'EVENT');
        $evtReset->actions = "Reset\n";
        $world58->registerEvent($evtReset);
    }
    // Mutate world state before Reset
    $world58->setProperty('custom_marker', 'mutated_before_reset');
    $pm->saveWorld($world58);

    if (file_exists($logFile)) {
        @unlink($logFile);
    }
    $initialToken = (string)$pm->getSetting($sec58WorldId, 'world_reset_token', '');

    // Human UI / JSON UI execution of 'use reset_crystal' command
    $ui58 = new Dimx\UIjson();
    $ui58->setEngine($eng58);
    $cmdRes = $eng58->processCommand('Hero58', 'use', 'reset_crystal', '', $ui58);

    assertTest(!empty($cmdRes['reset']), "processCommand returns reset => true when Reset is executed");
    assertTest(!empty($cmdRes['logged_out']), "processCommand returns logged_out => true upon world reset");

    $logContent2 = file_exists($logFile) ? file_get_contents($logFile) : '';
    assertTest(str_contains($logContent2, "Action: RESET\n"), "debug.log contains 'Action: RESET' during command execution");
    assertTest(str_contains($logContent2, "performing RESTART upon world reset flag\n"), "debug.log contains 'performing RESTART upon world reset flag'");

    // 5. Verify world was re-seeded and previous mutated state was wiped
    $reseededWorld = $eng58->loadOrSeedWorld();
    assertTest($reseededWorld->getProperty('custom_marker') === null, "Mutated world property is cleared after re-seeding");

    // 6. Verify world_reset_token has been updated in persistence
    $newToken = (string)$pm->getSetting($sec58WorldId, 'world_reset_token', '');
    assertTest($newToken !== '' && $newToken !== $initialToken, "world_reset_token in persistence changed after reset");

    // 7. Verify UIjson payload reflects reset
    ob_start();
    $ui58->render($cmdRes);
    $jsonOut = ob_get_clean();
    $parsedJson = json_decode($jsonOut, true);
    assertTest(is_array($parsedJson), "UIjson produces valid JSON output for reset");
    assertTest(($parsedJson['status'] ?? '') === 'reset', "UIjson status is 'reset'");
    assertTest(!empty($parsedJson['reset']), "UIjson payload contains reset: true");

    // 8. Direct GameEngine::resetWorld() call
    $directResetWorld = $eng58->resetWorld();
    assertTest($directResetWorld instanceof Dimx\World, "GameEngine::resetWorld() returns a fresh World instance");
    assertTest($directResetWorld->engine === $eng58, "re-seeded World has engine property bound to GameEngine");

    if (file_exists($logFile)) {
        @unlink($logFile);
    }
    @$pm->resetWorld($sec58WorldId);
}

// -------------------------------------------------------------
// Test 59: SmallBasic Array String Representation & fourinline line 204
// -------------------------------------------------------------
if (shouldRun(59)) {
    echo "\n--- Section 59: Testing SmallBasic Array String Representation [key1=val,key2=val,...] & fourinline.dxw line 204 ---\n";

    // 1. Static formatArray formatting
    assertTest(\Dimx\ActionsRunner::formatArray([]) === '[]', "Empty array formats as '[]'");
    assertTest(\Dimx\ActionsRunner::formatArray([1 => '(empty)', 2 => '(empty)']) === '[1=(empty),2=(empty)]', "1-based indexed array formats as '[1=(empty),2=(empty)]'");
    assertTest(\Dimx\ActionsRunner::formatArray(['hp' => 100, 'mp' => 50]) === '[hp=100,mp=50]', "Associative array formats as '[hp=100,mp=50]'");
    assertTest(\Dimx\ActionsRunner::formatArray(['items' => [1 => 'sword', 2 => 'shield']]) === '[items=[1=sword,2=shield]]', "Nested array formats recursively");
    assertTest(\Dimx\ActionsRunner::formatArray(['active' => true, 'dead' => false, 'extra' => null]) === '[active=true,dead=false,extra=]', "Array with booleans/null formats cleanly");

    // 2. DxwLoader ARRAY directive 1-based indexing verification
    $fourDFile = __DIR__ . '/../docs/dxw-examples/fourinline.dxw';
    $loaderFour = new \Dimx\DxwLoader();
    $fourLoaded = $loaderFour->load($fourDFile);
    assertTest(array_keys($fourLoaded->sets['players']) === [1, 2], "fourinline.dxw ARRAY players loads with 1-based index keys [1, 2]");
    assertTest(array_keys($fourLoaded->sets['opposite']) === [1, 2, 3, 4, 5, 6, 7, 8], "fourinline.dxw ARRAY opposite loads with 1-based index keys 1 to 8");

    // 3. ActionsRunner SmallBasic concatenation with array
    $sec59World = new \Dimx\World('w_sec59', 'Section 59 World');
    $p1 = new \Dimx\Player($sec59World, 'Champion', 'p_champ');
    $sec59World->addPlayer($p1);
    $sec59World->sets['players'] = [1 => $p1, 2 => '(empty)'];
    $sec59World->setProperty('players', [1 => $p1, 2 => '(empty)']);
    $sec59World->setProperty('nplayers', 1);
    $sec59World->setProperty('target_players', 2);

    $runner59 = new \Dimx\ActionsRunner($sec59World, null, $p1);

    // Expression concatenation
    $concatRes = $runner59->run('return "Players: " + players');
    assertTest($concatRes === 'Players: [1=Champion,2=(empty)]', "SmallBasic concatenation with array returns 1-based formatted string (got: " . var_export($concatRes, true) . ")");

    // 4. Exact fourinline.dxw line 204
    $line204Script = 'Speak "You are not one of the players - sorry. You are welcome to stay as a watcher." + nplayers + " :: " + target_players + " :: " + players';
    $runner59->run($line204Script);
    $lastMsg = end($sec59World->chatQueue);
    $expectedMsg = 'You are not one of the players - sorry. You are welcome to stay as a watcher.1 :: 2 :: [1=Champion,2=(empty)]';
    assertTest(($lastMsg['text'] ?? '') === $expectedMsg, "fourinline.dxw line 204 Speak generates formatted message: " . var_export($lastMsg['text'] ?? '', true));

    // 5. Direct Speak array
    $runner59->run('Speak players');
    $lastMsg2 = end($sec59World->chatQueue);
    assertTest(($lastMsg2['text'] ?? '') === '[1=Champion,2=(empty)]', "Speak players directly outputs array representation: " . var_export($lastMsg2['text'] ?? '', true));

    // 6. Split() and NewArray() 1-based index keys
    $splitRes = $runner59->run('return Split("apple,banana,cherry", ",")');
    assertTest(array_keys($splitRes) === [1, 2, 3], "Split() returns 1-based indexed array keys [1, 2, 3]");
    $newArr = $runner59->run('return NewArray("alpha,beta,gamma")');
    assertTest(array_keys($newArr) === [1, 2, 3], "NewArray() returns 1-based indexed array keys [1, 2, 3]");

    // 7. Dynamic 1-based array element assignment
    $runner59->run('testArr(1) = "first"');
    $runner59->run('testArr(2) = "second"');
    $arrVal = $runner59->getVariable('testArr');
    assertTest(is_array($arrVal) && array_keys($arrVal) === [1, 2], "Dynamic array assignment testArr(1)=..., testArr(2)=... starts at index 1");
    assertTest($runner59->run('return testArr(1)') === 'first', "testArr(1) returns 'first'");
    assertTest($runner59->run('return testArr(2)') === 'second', "testArr(2) returns 'second'");

    // 8. Display / Print array
    $runner59->run('Print players');
    $runner59->run('Display players');

    // 9. Test fourinline.dxw file contains updated line 204
    $fourContent = file_get_contents($fourDFile);
    assertTest(str_contains($fourContent, 'Speak "You are not one of the players - sorry. You are welcome to stay as a watcher." + nplayers + " :: " + target_players + " :: " + players'), "fourinline.dxw line 204 matches exact expected expression");

    // 10. Equality comparison with game objects across sessions (findUser)
    $pChampOldSession = new \Dimx\Player($sec59World, 'Champion', 'p_champ_oldsession');
    $pChampOldSession->numericId = $p1->numericId;
    $pCiccio = new \Dimx\Player($sec59World, 'ciccio', 'p_ciccio_123');
    $pCiccio->numericId = 9999;
    $sec59World->sets['players'] = [1 => $pChampOldSession, 2 => $pCiccio];
    $sec59World->setProperty('players', [1 => $pChampOldSession, 2 => $pCiccio]);

    $runner59->setLocalVar('user', $p1);
    $eqRes1 = $runner59->run('return user = players(1)');
    assertTest($eqRes1 === true, "user = players(1) is true for player across sessions (same account)");
    $eqRes2 = $runner59->run('return user = players(2)');
    assertTest($eqRes2 === false, "user = players(2) is false for different players");

    $findUserTestScript = <<<'SB'
For i=1 To SetLen(players)
    If user = players(i)
        Return i
    End_If
Next
Return 0
SB;
    $foundUserIdx = $runner59->run($findUserTestScript);
    assertTest($foundUserIdx === 1, "findUser(user) successfully finds matching player index (got: {$foundUserIdx})");

    // Scalar-to-object comparison
    assertTest($runner59->run('return user = "Champion"') === true, "user = 'Champion' matches player by name");
    assertTest($runner59->run('return user = "' . $p1->id . '"') === true, "user = '<id>' matches player by ID");
}

// -------------------------------------------------------------
// Section 60: Testing WORLD Directives (NAME, VERSION, AUTHOR, SITE, GUI.LOGOSRC) & Login Form Layering
// -------------------------------------------------------------
if (shouldRun(60)) {
    echo "\n--- Section 60: Testing WORLD Directives & Login Form Layering ---\n";

    // 1. Test world3net.dxw directive parsing
    $loader = new \Dimx\DxwLoader('https://example.com');
    $w3 = $loader->load(dirname(__DIR__) . '/docs/dxw-examples/world3net.dxw');

    assertTest($w3->getProperty('NAME') === 'The Beach', "world3net.dxw NAME stored in world properties as 'The Beach'");
    assertTest($w3->name === 'The Beach', "world3net.dxw name property is 'The Beach'");
    assertTest($w3->getProperty('VERSION') === '2.30', "world3net.dxw VERSION stored in world properties as '2.30'");
    assertTest($w3->version === '2.30', "world3net.dxw version property is '2.30'");
    assertTest($w3->getProperty('AUTHOR') === 'Cris', "world3net.dxw AUTHOR stored in world properties as 'Cris'");
    assertTest($w3->author === 'Cris', "world3net.dxw author property is 'Cris'");
    assertTest($w3->getProperty('SITE') === 'https://www.dimensionex.net/thebeach/multiplayer.htm', "world3net.dxw SITE stored in world properties");
    assertTest($w3->site === 'https://www.dimensionex.net/thebeach/multiplayer.htm', "world3net.dxw site property matches SITE directive");
    assertTest(str_contains((string)$w3->getProperty('GUI.LOGOSRC'), 'thebeachfront.jpg'), "world3net.dxw GUI.LOGOSRC stored in world properties");
    assertTest(str_contains((string)$w3->getProperty('LOGOSRC'), 'thebeachfront.jpg'), "world3net.dxw LOGOSRC stored in world properties");
    assertTest(str_contains((string)$w3->logoSrc, 'thebeachfront.jpg'), "world3net.dxw logoSrc property matches LOGOSRC");

    // 2. Test world2.dxw (LOGOSRC under WORLD section, AUTHOR, VERSION, no SITE)
    $w2 = $loader->load(__DIR__ . '/../docs/dxw-examples/world2.dxw');
    assertTest($w2->name === 'World 2', "world2.dxw name is 'World 2'");
    assertTest($w2->version === '1.9', "world2.dxw version is '1.9'");
    assertTest($w2->author === 'Cristiano Leoni', "world2.dxw author is 'Cristiano Leoni'");
    assertTest(str_contains((string)$w2->getProperty('GUI.LOGOSRC'), 'rana.gif'), "world2.dxw LOGOSRC under WORLD stored in GUI.LOGOSRC property");
    assertTest($w2->site === '', "world2.dxw site is empty when not specified");

    // 3. Test UIhtml::renderLogin() layering with world3net
    $eng60 = new \Dimx\GameEngine($pm, 'w_sec60_beach', dirname(__DIR__) . '/docs/dxw-examples/world3net.dxw');
    $ui60 = new \Dimx\UIhtml(null, $eng60);
    ob_start();
    $ui60->renderLogin();
    $html60 = (string)ob_get_clean();

    // Check lowest z-layer: logosrc background occupying whole width (stretch y dimension proportionally)
    assertTest(str_contains($html60, 'login-bg-layer'), "Login page includes lowest z-layer container (.login-bg-layer)");
    assertTest(str_contains($html60, 'z-index:0'), "Lowest z-layer has z-index:0");
    assertTest(str_contains($html60, 'width:100%'), "Lowest z-layer background occupies whole width (width:100%)");
    assertTest(str_contains($html60, 'thebeachfront.jpg'), "Lowest z-layer background image contains thebeachfront.jpg");
    assertTest(str_contains($html60, 'height:auto'), "Lowest z-layer background image stretches y dimension proportionally (height:auto)");

    // Check next layer: z-index:1 containing NAME, AUTHOR, VERSION, SITE, login form
    assertTest(str_contains($html60, 'login-layer'), "Login page includes next layer container (.login-layer)");
    assertTest(str_contains($html60, 'z-index:1'), "Next layer has z-index:1");
    assertTest(str_contains($html60, 'class="world-name"'), "Next layer contains world name container");
    assertTest(str_contains($html60, 'The Beach'), "Next layer renders world NAME 'The Beach'");
    assertTest(str_contains($html60, 'class="world-author">by Cris</span>'), "Next layer renders 'by Cris'");
    assertTest(str_contains($html60, 'class="world-version">ver. 2.30</span>'), "Next layer renders 'ver. 2.30'");
    assertTest(str_contains($html60, 'href="https://www.dimensionex.net/thebeach/multiplayer.htm"'), "Next layer renders link to SITE");
    assertTest(str_contains($html60, '<form method="POST">'), "Next layer contains login form");
    assertTest(str_contains($html60, 'name="username"'), "Next layer contains login form username field");

    // Verify ordering of elements in DOM:
    // lowest z-layer bg -> next layer content (NAME -> break -> by AUTHOR -> break -> ver. VERSION -> break -> form)
    $posBg      = strpos($html60, '<div class="login-bg-layer">');
    $posName    = strpos($html60, '<div class="world-name">');
    $posAuthor  = strpos($html60, 'by Cris');
    $posVer     = strpos($html60, 'ver. 2.30');
    $posForm    = strpos($html60, '<form method="POST">');

    assertTest($posBg !== false && $posName !== false && $posBg < $posName, "logosrc background layer appears before foreground content layer in DOM");
    assertTest($posName < $posAuthor, "NAME appears before 'by AUTHOR'");
    assertTest($posAuthor < $posVer, "'by AUTHOR' appears before 'ver. VERSION'");
    assertTest($posVer < $posForm, "'ver. VERSION' appears before login form");

    // Verify line breaks between elements
    $nameToAuthor = substr($html60, $posName, $posAuthor - $posName);
    $authorToVer  = substr($html60, $posAuthor, $posVer - $posAuthor);
    $verToForm    = substr($html60, $posVer, $posForm - $posVer);

    assertTest(str_contains($nameToAuthor, '<br>'), "Break (<br>) exists between NAME and by AUTHOR");
    assertTest(str_contains($authorToVer, '<br>'), "Break (<br>) exists between by AUTHOR and ver. VERSION");
    assertTest(str_contains($verToForm, '<br>'), "Break (<br>) exists between ver. VERSION and login form");

    // 4. Test minimal world without AUTHOR, SITE, LOGOSRC
    $customDxw = "WORLD\nNAME Custom Land\nVERSION 5.0\nEND_WORLD\nROOMS\nROOM start, \"Start\"\nEND_ROOMS\n";
    $tmpDxw = sys_get_temp_dir() . '/custom_' . uniqid() . '.dxw';
    file_put_contents($tmpDxw, $customDxw);

    $engCustom = new \Dimx\GameEngine($pm, 'w_sec60_custom', $tmpDxw);
    $uiCustom = new \Dimx\UIhtml(null, $engCustom);
    ob_start();
    $uiCustom->renderLogin();
    $htmlCustom = (string)ob_get_clean();
    @unlink($tmpDxw);

    assertTest(!str_contains($htmlCustom, '<div class="login-bg-layer">'), "No background image layer rendered when LOGOSRC is absent");
    assertTest(str_contains($htmlCustom, 'Custom Land'), "World NAME 'Custom Land' is rendered");
    assertTest(str_contains($htmlCustom, 'ver. 5.0'), "ver. 5.0 is rendered when VERSION is present");
    assertTest(!str_contains($htmlCustom, 'by '), "'by AUTHOR' is NOT rendered when AUTHOR is absent");
    assertTest(!str_contains($htmlCustom, 'class="world-site"'), "Site link is NOT rendered when SITE is absent");
}

// -------------------------------------------------------------
// Section 61: Testing Who's Online - The players view
// (multiplayer.php?view=players & format=extended)
// -------------------------------------------------------------
if (shouldRun(61)) {
    echo "\n--- Section 61: Testing Who's Online - The players view ---\n";

    $sec61WorldId = 'test_w_sec61_' . uniqid();
    $serverW61 = [
        'DIMX_WORLD_ID'   => $sec61WorldId,
        'DIMX_WORLD_FILE' => __DIR__ . '/../docs/dxw-examples/world2.dxw',
    ];

    // 1. Initial empty world: view=players should output "Server up!"
    $outEmpty = runMultiplayer(['view' => 'players'], [], $serverW61);
    assertTest(str_contains($outEmpty, 'Server up!'), "Initial empty world outputs 'Server up!'");
    assertTest(str_contains($outEmpty, '- Players</title>'), "Output contains '<title>... - Players</title>'");
    assertTest(str_contains($outEmpty, '<body') && str_contains($outEmpty, 'bgcolor='), "Output includes body with bgcolor");
    assertTest(!str_contains($outEmpty, 'players'), "Initial empty world does NOT contain 'players' count");
    assertTest(!str_contains($outEmpty, '<b>'), "Initial empty world does NOT list any player bold tags");

    // 2. Initial empty world: view=players&format=extended
    $outEmptyExt = runMultiplayer(['view' => 'players', 'format' => 'extended'], [], $serverW61);
    assertTest(str_contains($outEmptyExt, 'Server up!'), "Extended empty world outputs 'Server up!'");
    assertTest(str_contains($outEmptyExt, '<br/><br/>'), "Extended empty world includes '<br/><br/>'");
    assertTest(!str_contains($outEmptyExt, 'is in'), "Extended empty world has no 'is in' lines");

    // 3. Connect 1 player (PlayerOne)
    $p1Login = runMultiplayer([
        'username' => 'PlayerOne',
        'password' => 'secret1',
        'cmd'      => 'look',
    ], [], $serverW61);

    // Test view=players with 1 player
    $out1 = runMultiplayer(['view' => 'players'], [], $serverW61);
    assertTest(str_contains($out1, '1 players'), "view=players with 1 player outputs '1 players'");
    assertTest(!str_contains($out1, 'Server up!'), "view=players with active players does NOT output 'Server up!'");
    assertTest(!str_contains($out1, 'PlayerOne'), "Standard view=players does NOT list player names");

    // Test view=players&format=extended with 1 player
    $out1Ext = runMultiplayer(['view' => 'players', 'format' => 'extended'], [], $serverW61);
    assertTest(str_contains($out1Ext, '1 players'), "Extended view with 1 player outputs '1 players'");
    assertTest(str_contains($out1Ext, '<br/><br/>'), "Extended view includes '<br/><br/>'");
    assertTest(str_contains($out1Ext, '<b>PlayerOne</b> is in '), "Extended view includes '<b>PlayerOne</b> is in <room>'");

    // 4. Connect second player (PlayerTwo)
    $p2Login = runMultiplayer([
        'username' => 'PlayerTwo',
        'password' => 'secret2',
        'cmd'      => 'look',
    ], [], $serverW61);

    // Test view=players with 2 players
    $out2 = runMultiplayer(['view' => 'players'], [], $serverW61);
    assertTest(str_contains($out2, '2 players'), "view=players with 2 players outputs '2 players'");

    // Test view=players&format=extended with 2 players
    $out2Ext = runMultiplayer(['view' => 'players', 'format' => 'extended'], [], $serverW61);
    assertTest(str_contains($out2Ext, '2 players'), "Extended view with 2 players outputs '2 players'");
    assertTest(str_contains($out2Ext, '<b>PlayerOne</b> is in '), "Extended view lists PlayerOne");
    assertTest(str_contains($out2Ext, '<b>PlayerTwo</b> is in '), "Extended view lists PlayerTwo");

    // 5. JSON formats
    $jsonStandard = runMultiplayer(['view' => 'players', 'format' => 'json'], [], $serverW61);
    $dataStd = json_decode($jsonStandard, true);
    assertTest(is_array($dataStd) && ($dataStd['count'] ?? 0) === 2, "JSON view=players returns count: 2");

    $jsonExt = runMultiplayer(['view' => 'players', 'format' => 'extended', 'ui' => 'json'], [], $serverW61);
    $dataExt = json_decode($jsonExt, true);
    assertTest(is_array($dataExt) && count($dataExt['players'] ?? []) === 2, "JSON view=players&format=extended returns players list of 2");

    // Clean up
    @$pm->resetWorld($sec61WorldId);
}

// -------------------------------------------------------------
// Section 62: Testing Player Inactivity Timeout (30s) at Tick Event & Nickname Conflict Check at Login
// -------------------------------------------------------------
if (shouldRun(62)) {
    echo "\n--- Section 62: Testing Player Inactivity Timeout (30s) at Tick Event & Nickname Conflict Check at Login ---\n";

    $sec62WorldId = 'test_w_sec62_' . uniqid();
    $serverW62 = [
        'DIMX_WORLD_ID'   => $sec62WorldId,
        'DIMX_WORLD_FILE' => dirname(__DIR__) . '/docs/dxw-examples/world3net.dxw',
    ];

    // Part 1: Verify Player instance records timestamp on construct and interaction
    $pTest = new \Dimx\Player(null, 'Alice');
    assertTest(isset($pTest->lastActive) && $pTest->lastActive > 0, "Player instance has lastActive property populated (> 0)");
    assertTest(isset($pTest->lastInteraction) && $pTest->lastInteraction > 0, "Player instance has lastInteraction property populated (> 0)");
    assertTest($pTest->lastActive === $pTest->lastInteraction, "lastActive matches lastInteraction on initialization");

    $pastTime = time() - 100;
    $pTest->recordInteraction($pastTime);
    assertTest($pTest->lastActive === $pastTime, "recordInteraction updates lastActive");
    assertTest($pTest->lastInteraction === $pastTime, "recordInteraction updates lastInteraction");

    // Part 2: Verify Tick kills players with > 30s inactivity
    $w62 = new \Dimx\World($sec62WorldId, 'TimeoutWorld');
    $room = new \Dimx\Room($w62, 'Lobby', 'lobby');
    $w62->addRoom($room);

    $pActive = new \Dimx\Player($w62, 'ActivePlayer', 'p_act');
    $pActive->recordInteraction(time() - 10); // 10s ago (< 30s)
    $w62->addPlayer($pActive);
    $room->addObject($pActive);

    $pStale = new \Dimx\Player($w62, 'StalePlayer', 'p_stale');
    $pStale->recordInteraction(time() - 35); // 35s ago (> 30s)
    $w62->addPlayer($pStale);
    $room->addObject($pStale);

    assertTest(isset($w62->players['p_act']), "Active player present in world before tick");
    assertTest(isset($w62->players['p_stale']), "Stale player present in world before tick");

    // Execute clock tick
    $w62->tick(force: true);

    assertTest(isset($w62->players['p_act']), "Active player survives tick (< 30s inactivity)");
    assertTest(!isset($w62->players['p_stale']), "Stale player killed at tick (> 30s inactivity)");
    assertTest(!isset($room->contents['p_stale']), "Stale player removed from room container contents");

    // Part 3: Test World::isNameInUse()
    $npc = new \Dimx\Character($w62, 'Old Sailor', 'sailor');
    $w62->addCharacter($npc);

    assertTest($w62->isNameInUse('ActivePlayer') === true, "isNameInUse matches active player name");
    assertTest($w62->isNameInUse('activeplayer') === true, "isNameInUse is case-insensitive for active player name");
    assertTest($w62->isNameInUse('Old Sailor') === true, "isNameInUse matches character display name");
    assertTest($w62->isNameInUse('sailor') === true, "isNameInUse matches character ID");
    assertTest($w62->isNameInUse('SAILOR') === true, "isNameInUse is case-insensitive for character ID");
    assertTest($w62->isNameInUse('NonExistent') === false, "isNameInUse returns false for unused name");
    assertTest($w62->isNameInUse('StalePlayer') === false, "isNameInUse returns false for player who was killed");

    // Part 4: Integration test via multiplayer.php login
    // 4a. Log in initial player 'Alice62'
    $loginAlice = runMultiplayer([
        'format'   => 'json',
        'username' => 'Alice62',
        'password' => 'secret123',
        'cmd'      => 'look',
    ], [], $serverW62);
    $dataAlice = json_decode($loginAlice, true);
    assertTest(($dataAlice['success'] ?? false) === true, "Alice62 logged in successfully");

    // 4b. Another user tries to log in with SAME nickname 'Alice62'
    $loginConflict = runMultiplayer([
        'format'   => 'json',
        'username' => 'Alice62',
        'password' => 'secret123',
        'cmd'      => 'login',
    ], [], $serverW62);
    $dataConflict = json_decode($loginConflict, true);
    assertTest(($dataConflict['success'] ?? true) === false, "Login with already used nickname rejected");
    assertTest(($dataConflict['status'] ?? '') === 'login_required', "Rejected login status is 'login_required'");
    assertTest(($dataConflict['error'] ?? '') === 'Invalid nickname - choose another', "Error is 'Invalid nickname - choose another'");

    // 4c. User tries to log in with existing NPC Character name in world3net ('surfer')
    $loginNpc = runMultiplayer([
        'format'   => 'json',
        'username' => 'surfer',
        'password' => 'pass123',
        'cmd'      => 'look',
    ], [], $serverW62);
    $dataNpc = json_decode($loginNpc, true);
    assertTest(($dataNpc['success'] ?? true) === false, "Login with NPC Character nickname rejected");
    assertTest(($dataNpc['error'] ?? '') === 'Invalid nickname - choose another', "NPC nickname collision returns 'Invalid nickname - choose another'");

    // 4d. HTML client receives "Invalid nickname - choose another"
    $loginHtmlConflict = runMultiplayer([
        'username' => 'Alice62',
        'password' => 'secret123',
    ], [], $serverW62);
    assertTest(str_contains($loginHtmlConflict, 'Invalid nickname - choose another'), "UIhtml renders 'Invalid nickname - choose another' error");

    // Clean up
    @$pm->resetWorld($sec62WorldId);
}

// -------------------------------------------------------------
// Section 63: Testing PeopleContainer Object Entry & Scene Refresh Dispatch with Deduplication
// -------------------------------------------------------------
if (shouldRun(63)) {
    echo "\n--- Section 63: Testing PeopleContainer Object Entry & Scene Refresh Dispatch with Deduplication ---\n";

    $sec63WorldId = 'test_w_sec63_' . uniqid();
    $serverW63 = [
        'DIMX_WORLD_ID'   => $sec63WorldId,
        'DIMX_WORLD_FILE' => dirname(__DIR__) . '/docs/dxw-examples/world3net.dxw',
    ];

    // Part 1: Unit test with Room and Players
    $sec63DataDir = __DIR__ . '/data_test_sec63_' . uniqid();
    $sec63Pm = new \Dimx\PersistenceManager([], $sec63DataDir);
    $w63 = new \Dimx\World($sec63WorldId, 'Sec63 World');
    $w63->persistence = $sec63Pm;
    $startRoom = new \Dimx\Room($w63, 'Starting Room', 'start_room');
    $otherRoom = new \Dimx\Room($w63, 'Other Room', 'other_room');
    $w63->addRoom($startRoom);
    $w63->addRoom($otherRoom);

    // Add Player1 to startRoom
    $p1 = new \Dimx\Player($w63, 'PlayerOne', 'p_one');
    $w63->addPlayer($p1);
    $p1->move($startRoom);

    // Initial state: drain any commands from initial placement
    $p1->drainCommands();
    $sec63Pm->drainCommands($sec63WorldId, 'PlayerOne');
    assertTest($p1->getPendingCommands() === [], "Player1 has empty pending commands after initial drain");
    assertTest($sec63Pm->hasCommand($sec63WorldId, 'PlayerOne', 'refresh!scene') === false, "Persistence has no pending refresh!scene for Player1");

    // Player2 joins the same room (startRoom)
    $p2 = new \Dimx\Player($w63, 'PlayerTwo', 'p_two');
    $w63->addPlayer($p2);
    $p2->move($startRoom);

    // Player1 must have received refresh!scene
    assertTest(in_array('refresh!scene', $p1->getPendingCommands(), true), "Player1 received refresh!scene when Player2 joined room");
    assertTest($sec63Pm->hasCommand($sec63WorldId, 'PlayerOne', 'refresh!scene') === true, "Persistence recorded refresh!scene for Player1");

    // Part 2: Deduplication test
    // While refresh!scene is in Player1's queue, another object enters (e.g. items, or another player)
    $item1 = new \Dimx\Item($w63, 'Gold Coin', 'coin1');
    $startRoom->addObject($item1);
    $item2 = new \Dimx\Item($w63, 'Silver Coin', 'coin2');
    $startRoom->addObject($item2);

    // Player1 queue should have exactly one refresh!scene, not three
    $p1Pending = $p1->getPendingCommands();
    $refreshCount = count(array_filter($p1Pending, fn($c) => $c === 'refresh!scene'));
    assertTest($refreshCount === 1, "Player1 pendingCommands contains exactly one refresh!scene despite multiple object entries");

    // Draining persistence commands produces exactly one 'refresh!scene;'
    $drained = $sec63Pm->drainCommands($sec63WorldId, 'PlayerOne');
    assertTest($drained === 'refresh!scene;', "drainCommands for Player1 returned exactly 'refresh!scene;' without duplication");
    assertTest($sec63Pm->hasCommand($sec63WorldId, 'PlayerOne', 'refresh!scene') === false, "Persistence queue is empty after drain");

    // Part 3: Object departure triggers refresh for players remaining in room
    $p1->drainCommands();
    assertTest($p1->getPendingCommands() === [], "Player1 queue cleared");

    // Player2 moves from startRoom to otherRoom
    $p2->move($otherRoom);

    assertTest(in_array('refresh!scene', $p1->getPendingCommands(), true), "Player1 in startRoom received refresh!scene when Player2 left");
    $p1->drainCommands();
    $sec63Pm->drainCommands($sec63WorldId, 'PlayerOne');

    // Part 4: End-to-end multiplayer login integration test via runMultiplayer & runChat
    // 4a. Human Player1 logs into world3net
    $loginP1 = runMultiplayer([
        'username' => 'HumanAlpha',
        'password' => 'secret123',
        'cmd'      => 'look',
    ], [], $serverW63);
    assertTest(str_contains($loginP1, 'id="scene"'), "HumanAlpha logged in and received HTML scene");

    // Ensure HumanAlpha's command queue is drained/clean
    $pm->drainCommands($sec63WorldId, 'HumanAlpha');

    // 4b. Human Player2 logs into world3net (enters the same default starting room 'spiaggia2')
    $loginP2 = runMultiplayer([
        'username' => 'HumanBeta',
        'password' => 'secret123',
        'cmd'      => 'look',
    ], [], $serverW63);
    assertTest(str_contains($loginP2, 'id="scene"'), "HumanBeta logged in and received HTML scene");

    // 4c. HumanAlpha polls multiplayer.php?view=msgs (chat polling)
    $chatResp = runChat([
        'world_id' => $sec63WorldId,
        'player'   => 'HumanAlpha',
    ]);
    $chatData = json_decode($chatResp, true);
    assertTest(($chatData['ok'] ?? false) === true, "Chat poll for HumanAlpha succeeded");
    assertTest(isset($chatData['commands']), "Chat poll returned commands payload for HumanAlpha");
    assertTest(str_contains($chatData['commands'] ?? '', 'refresh!scene'), "HumanAlpha received refresh!scene command via chat polling");

    // 4d. Subsequent immediate chat poll has no leftover commands (drained)
    $chatResp2 = runChat([
        'world_id' => $sec63WorldId,
        'player'   => 'HumanAlpha',
    ]);
    $chatData2 = json_decode($chatResp2, true);
    assertTest(!isset($chatData2['commands']) || empty($chatData2['commands']), "Subsequent chat poll has no pending commands");

    // 4e. HumanBeta exits the room by moving to spiaggia3 via LINK w24
    $moveBeta = runMultiplayer([
        'username' => 'HumanBeta',
        'password' => 'secret123',
        'format'   => 'json',
        'cmd'      => 'go',
        'dir'      => 'w24',
    ], [], $serverW63);
    $dataMoveBeta = json_decode($moveBeta, true);
    assertTest(($dataMoveBeta['success'] ?? false) === true, "HumanBeta moved via exit w24");

    // HumanAlpha (staying behind in spiaggia2) must receive refresh!scene because HumanBeta left the room
    $chatRespExit = runChat([
        'world_id' => $sec63WorldId,
        'player'   => 'HumanAlpha',
    ]);
    $chatDataExit = json_decode($chatRespExit, true);
    assertTest(isset($chatDataExit['commands']), "Chat poll returned commands for HumanAlpha after HumanBeta exited room");
    assertTest(str_contains($chatDataExit['commands'] ?? '', 'refresh!scene'), "HumanAlpha received refresh!scene when HumanBeta exited room");

    // 4f. HumanBeta moves back to spiaggia2 via LINK w24
    $moveBetaBack = runMultiplayer([
        'username' => 'HumanBeta',
        'password' => 'secret123',
        'format'   => 'json',
        'cmd'      => 'go',
        'dir'      => 'w24',
    ], [], $serverW63);
    $dataMoveBack = json_decode($moveBetaBack, true);
    assertTest(($dataMoveBack['success'] ?? false) === true, "HumanBeta returned to spiaggia2 via exit w24");

    // HumanAlpha receives refresh!scene because HumanBeta re-entered
    $chatRespReenter = runChat([
        'world_id' => $sec63WorldId,
        'player'   => 'HumanAlpha',
    ]);
    $chatDataReenter = json_decode($chatRespReenter, true);
    assertTest(str_contains($chatDataReenter['commands'] ?? '', 'refresh!scene'), "HumanAlpha received refresh!scene when HumanBeta re-entered room");

    // 4g. HumanBeta logs out of the world while in the same room as HumanAlpha
    $logoutBeta = runMultiplayer([
        'username' => 'HumanBeta',
        'password' => 'secret123',
        'format'   => 'json',
        'cmd'      => 'logout',
    ], [], $serverW63);
    $dataLogout = json_decode($logoutBeta, true);
    assertTest(($dataLogout['logged_out'] ?? false) === true, "HumanBeta logged out successfully");

    // HumanAlpha receives refresh!scene because HumanBeta left on logout
    $chatRespLogout = runChat([
        'world_id' => $sec63WorldId,
        'player'   => 'HumanAlpha',
    ]);
    $chatDataLogout = json_decode($chatRespLogout, true);
    assertTest(str_contains($chatDataLogout['commands'] ?? '', 'refresh!scene'), "HumanAlpha received refresh!scene when HumanBeta left on logout");

    // 4h. Queue clearing on #scene render:
    // If a player has a pending refresh!scene in queue, rendering #scene clears it.
    $pm->queueCommand($sec63WorldId, 'HumanAlpha', 'refresh!scene');
    assertTest($pm->hasCommand($sec63WorldId, 'HumanAlpha', 'refresh!scene') === true, "refresh!scene is queued in persistence for HumanAlpha");

    // HumanAlpha renders #scene (e.g. sends 'look' command)
    $resetToken = (string)$pm->getSetting($sec63WorldId, 'world_reset_token', '');
    $renderScene = runMultiplayer([
        'username' => 'HumanAlpha',
        'password' => 'secret123',
        'cmd'      => 'look',
    ], [], $serverW63, [
        'dimx_player'            => 'HumanAlpha',
        'dimx_authenticated'     => true,
        'dimx_world_reset_token' => $resetToken,
    ]);
    assertTest(str_contains($renderScene, 'id="scene"'), "HumanAlpha rendered #scene HTML");

    // Any refresh!scene in persistence and in-memory must now be cleared
    assertTest($pm->hasCommand($sec63WorldId, 'HumanAlpha', 'refresh!scene') === false, "Rendering #scene cleared refresh!scene from persistence");
    $chatCheck = runChat([
        'world_id' => $sec63WorldId,
        'player'   => 'HumanAlpha',
    ]);
    $chatDataCheck = json_decode($chatCheck, true);
    assertTest(!isset($chatDataCheck['commands']) || empty($chatDataCheck['commands']), "No redundant refresh!scene returned via chat after rendering #scene");

    // 5. Test stale player pruning triggered by view=msgs polling
    // Re-login Player1 (HumanAlpha) and Player2 (HumanBeta)
    runMultiplayer([
        'username' => 'HumanAlpha',
        'password' => 'secret123',
        'cmd'      => 'look',
    ], [], $serverW63);
    runMultiplayer([
        'username' => 'HumanBeta',
        'password' => 'secret123',
        'cmd'      => 'look',
    ], [], $serverW63);

    // Drain pending commands for HumanAlpha so queue is clean
    $pm->drainCommands($sec63WorldId, 'HumanAlpha');

    // Make HumanBeta stale (idle for 35 seconds), HumanAlpha active (idle 5 seconds)
    $wStale = $pm->loadWorld($sec63WorldId);
    $betaObj = $wStale->getPlayerByName('HumanBeta');
    $alphaObj = $wStale->getPlayerByName('HumanAlpha');
    assertTest($betaObj !== null && $alphaObj !== null, "Both players exist in world before stale check");
    $betaObj->lastInteraction = time() - 35;
    $betaObj->lastActive = time() - 35;
    $betaObj->setProperty('lastInteraction', time() - 35);
    $betaObj->setProperty('lastActive', time() - 35);
    $alphaOldTime = time() - 5;
    $alphaObj->lastInteraction = $alphaOldTime;
    $alphaObj->lastActive = $alphaOldTime;
    $alphaObj->setProperty('lastInteraction', $alphaOldTime);
    $alphaObj->setProperty('lastActive', $alphaOldTime);
    $alphaObj->drainCommands();
    $pm->saveWorld($wStale);

    // HumanAlpha polls multiplayer.php?view=msgs
    $pollResp = runMultiplayer([], ['view' => 'msgs', 'world_id' => $sec63WorldId, 'player' => 'HumanAlpha'], $serverW63);
    $pollData = json_decode($pollResp, true);
    assertTest(($pollData['ok'] ?? false) === true, "Polling view=msgs succeeded");
    assertTest(isset($pollData['commands']) && str_contains($pollData['commands'], 'refresh!scene'), "HumanAlpha received refresh!scene upon stale HumanBeta pruning");

    // Verify HumanBeta was removed from world, HumanAlpha remains and was touched (not stale)
    $wAfter = $pm->loadWorld($sec63WorldId);
    assertTest($wAfter->getPlayerByName('HumanBeta') === null, "Stale player HumanBeta was pruned from world");
    $alphaAfter = $wAfter->getPlayerByName('HumanAlpha');
    assertTest($alphaAfter !== null, "Active player HumanAlpha remains in world");
    assertTest($alphaAfter->lastInteraction > $alphaOldTime, "HumanAlpha interaction timestamp was updated by view=msgs polling");

    // Clean up
    @$pm->resetWorld($sec63WorldId);
    if (is_dir($sec63DataDir)) {
        array_map('unlink', glob("{$sec63DataDir}/*.*"));
        @rmdir($sec63DataDir);
    }
}

// =========================================================================
// Section 64: Testing SmallBasic Goal Instruction & Database Recording in dimx_goals
// =========================================================================
if (shouldRun(64)) {
    echo "\n--- Section 64: Testing SmallBasic Goal Instruction & Database Recording in dimx_goals ---\n";

    $sec64WorldId = 'test_w_sec64_' . bin2hex(random_bytes(4));
    $sec64World = new \Dimx\World($sec64WorldId, 'Goal World');
    $sec64World->version = '3.14';
    $sec64World->mode = 1;
    $sec64World->setProperty('mode', 1);
    $sec64World->persistence = $pm;

    $sec64Room = new \Dimx\Room($sec64World, 'Chamber of Victory');
    $sec64World->addRoom($sec64Room);

    $sec64Player = new \Dimx\Player($sec64World, 'Champion', 'p_champion', 'Hero');
    $sec64Player->score = 50;
    $sec64Player->move($sec64Room);
    $sec64World->addPlayer($sec64Player);

    // 1. Basic Goal execution without argument
    $pm->clearGoals($sec64WorldId);
    $runner1 = new \Dimx\ActionsRunner($sec64World, $sec64Room, $sec64Player, null);
    $ret1 = $runner1->run("Goal");

    assertTest($ret1 === true, "ActionsRunner executes 'Goal' successfully");
    assertTest($sec64World->mode === 0, "World mode changed to 0 upon Goal");
    assertTest($sec64World->getProperty('mode') === 0, "\$WORLD.mode property set to 0");
    assertTest($sec64Player->score === 60, "Player score incremented by 10 (50 -> 60)");

    $playerCons1 = implode(' ', $sec64Player->drainConsole());
    assertTest(str_contains($playerCons1, 'Champion') && str_contains($playerCons1, 'goal'), "Default goal message broadcast to player console: '{$playerCons1}'");

    $goals1 = $pm->getGoals($sec64WorldId);
    assertTest(count($goals1) === 1, "Goal event recorded in persistence (1 entry)");
    assertTest(($goals1[0]['player_name'] ?? '') === 'Champion', "Goal event player_name is 'Champion'");
    assertTest((int)($goals1[0]['score'] ?? 0) === 60, "Goal event score is 60");
    assertTest(($goals1[0]['world_version'] ?? '') === '3.14', "Goal event world_version is '3.14'");
    assertTest(str_contains($goals1[0]['message'] ?? '', 'Champion'), "Goal event message contains player name");

    // 2. Goal with custom message
    $sec64World->mode = 1;
    $runner2 = new \Dimx\ActionsRunner($sec64World, $sec64Room, $sec64Player, null);
    $runner2->run('Goal "Completed the epic quest!"');

    assertTest($sec64World->mode === 0, "World mode changed to 0 upon custom Goal");
    assertTest($sec64Player->score === 70, "Player score incremented by 10 (60 -> 70)");
    $playerCons2 = implode(' ', $sec64Player->drainConsole());
    assertTest(str_contains($playerCons2, 'Completed the epic quest!'), "Custom goal message broadcast to console: '{$playerCons2}'");

    $goals2 = $pm->getGoals($sec64WorldId);
    assertTest(count($goals2) === 2, "Persistence contains 2 goal events");
    assertTest(($goals2[0]['message'] ?? '') === 'Completed the epic quest!', "Most recent goal message is 'Completed the epic quest!'");

    // 3. Parenthesized syntax Goal("...") and Goal()
    $sec64World->mode = 1;
    $runner3 = new \Dimx\ActionsRunner($sec64World, $sec64Room, $sec64Player, null);
    $ret3 = $runner3->run('Goal("Victory via parens!")');
    assertTest($ret3 === true, "ActionsRunner executes Goal(\"...\") syntax");
    assertTest($sec64World->mode === 0, "World mode set to 0 by parenthesized Goal(...)");

    // 4. File-based fallback when database is offline
    $fileDataDir = sys_get_temp_dir() . '/dimx_goal_filetest_' . uniqid();
    @mkdir($fileDataDir, 0777, true);
    $filePm = new \Dimx\PersistenceManager([], $fileDataDir); // Empty db config forces file fallback
    $fileWorldId = 'file_world_test';
    $filePm->recordGoal($fileWorldId, 'FilePlayer', 42, '1.0', 'Offline achievement');
    $fileGoals = $filePm->getGoals($fileWorldId);
    assertTest(count($fileGoals) === 1, "File-based PersistenceManager records goal to JSONL when DB disconnected");
    assertTest(($fileGoals[0]['player_name'] ?? '') === 'FilePlayer', "File goal player_name is 'FilePlayer'");
    assertTest((int)($fileGoals[0]['score'] ?? 0) === 42, "File goal score is 42");
    assertTest(($fileGoals[0]['message'] ?? '') === 'Offline achievement', "File goal message matches");
    @unlink("{$fileDataDir}/goals_{$fileWorldId}.jsonl");
    @rmdir($fileDataDir);

    // 5. End-to-end integration: Look at surfer in world3net.dxw
    $w3Sec64Id = 'test_w3_goal_' . bin2hex(random_bytes(4));
    $w3File = __DIR__ . '/../../worlds/world3net.dxw';
    if (!file_exists($w3File)) {
        $w3File = __DIR__ . '/../docs/dxw-examples/world3net.dxw';
    }

    $pm->clearGoals($w3Sec64Id);
    $sec64Engine = new \Dimx\GameEngine($pm, $w3Sec64Id, $w3File);
    $w3World = $sec64Engine->loadOrSeedWorld();
    $surferEv = $w3World->getEvent('surfer.onLook');
    assertTest($surferEv !== null, "world3net.dxw loaded with surfer.onLook event");
    if ($surferEv !== null) {
        $surferEv->actions = "Goal \"Finished game\"\n" . ($surferEv->actions ?? '');
        $sec64Engine->pm->saveWorld($w3World);
    }

    // Connect player and look at surfer
    $player = $sec64Engine->ensurePlayer($w3World, 'GoalHunter');
    $surfer = $w3World->getCharacter('surfer');
    if ($surfer !== null && $player->getRoom() !== null) {
        $surfer->move($player->getRoom());
        $sec64Engine->pm->saveWorld($w3World);
    }
    $lookResult = $sec64Engine->processCommand('GoalHunter', 'look', 'surfer');
    assertTest(is_array($lookResult), "processCommand('GoalHunter', 'look', 'surfer') succeeded");

    $lookMsgs = implode(' ', $lookResult['messages'] ?? []);
    assertTest(str_contains($lookMsgs, 'Finished game'), "Response messages contain goal broadcast 'Finished game': '{$lookMsgs}'");

    // Verify chat messages contain surfer speech and voice broadcast
    $chatItems = $lookResult['chat'] ?? [];
    $chatTexts = array_column($chatItems, 'text');
    $chatAll = implode(' ', $chatTexts);
    assertTest(str_contains($chatAll, 'Finished game'), "Chat contains goal message 'Finished game'");

    // Check goals recorded in dimx_goals
    $w3Goals = $pm->getGoals($w3Sec64Id);
    assertTest(count($w3Goals) >= 1, "Goal recorded in database for world3net.dxw");
    $topGoal = $w3Goals[0];
    assertTest(($topGoal['player_name'] ?? '') === 'GoalHunter', "Goal record player_name is 'GoalHunter'");
    assertTest(($topGoal['message'] ?? '') === 'Finished game', "Goal record message is 'Finished game'");
    assertTest((int)($topGoal['score'] ?? 0) === 10, "GoalHunter score in goal record is 10");

    // Verify world mode became 0
    $w3Reloaded = $sec64Engine->loadOrSeedWorld();
    assertTest((int)$w3Reloaded->mode === 0, "world3net.dxw world mode is 0 after Goal execution");

    // 6. Test multiplayer.php?view=hof (Hall of Fame HTML and JSON views)
    $hofHtml = runMultiplayer(
        ['view' => 'hof'],
        [],
        ['DIMX_WORLD_ID' => $w3Sec64Id, 'DIMX_WORLD_FILE' => $w3File]
    );
    assertTest(str_contains($hofHtml, '<!DOCTYPE HTML'), "view=hof returns valid HTML document");
    assertTest(str_contains($hofHtml, 'Hall of Fame'), "view=hof contains 'Hall of Fame'");
    assertTest(str_contains($hofHtml, $w3World->getName()), "view=hof prints World name ('" . $w3World->getName() . "')");
    assertTest(str_contains($hofHtml, 'GoalHunter'), "view=hof table contains player 'GoalHunter'");
    assertTest(str_contains($hofHtml, 'Finished game'), "view=hof table contains goal message 'Finished game'");
    assertTest(str_contains($hofHtml, '<table') && str_contains($hofHtml, '>Date</th>') && str_contains($hofHtml, '>Player</th>'), "view=hof renders table with Date and Player headers");
    assertTest(str_contains($hofHtml, 'Back to game'), "view=hof contains link 'Back to game'");

    // Test view=hof in JSON format
    $hofJson = runMultiplayer(
        ['view' => 'hof', 'format' => 'json'],
        [],
        ['DIMX_WORLD_ID' => $w3Sec64Id, 'DIMX_WORLD_FILE' => $w3File]
    );
    $hofData = json_decode($hofJson, true);
    assertTest(is_array($hofData) && !empty($hofData['success']), "view=hof with format=json returns successful JSON response");
    assertTest(($hofData['view'] ?? '') === 'hof', "JSON response view is 'hof'");
    assertTest(($hofData['goals'][0]['player_name'] ?? '') === 'GoalHunter', "JSON goals list contains 'GoalHunter'");

    // Test view=hof with empty goals
    $emptyHofWorldId = 'test_w_empty_hof_' . bin2hex(random_bytes(4));
    $emptyHofHtml = runMultiplayer(
        ['view' => 'hof'],
        [],
        ['DIMX_WORLD_ID' => $emptyHofWorldId, 'DIMX_WORLD_FILE' => $w3File]
    );
    assertTest(str_contains($emptyHofHtml, 'Nobody was able to finish this game yet'), "Empty HOF displays 'Nobody was able to finish this game yet'");

    // Clean up
    @$pm->resetWorld($sec64WorldId);
    @$pm->clearGoals($sec64WorldId);
    @$pm->resetWorld($w3Sec64Id);
    @$pm->clearGoals($w3Sec64Id);
    @$pm->resetWorld($emptyHofWorldId);
    @$pm->clearGoals($emptyHofWorldId);
}

// -------------------------------------------------------------
// Section 65: Testing Login Attempt Logging to debug.log & Stale Player Pruning Inside Main Loop at Login
// -------------------------------------------------------------
if (shouldRun(65)) {
    echo "\n--- Section 65: Testing Login Attempt Logging to debug.log & Stale Player Pruning Inside Main Loop at Login ---\n";

    $sec65WorldId = 'test_w_sec65_' . uniqid();
    $serverW65 = [
        'DIMX_WORLD_ID'   => $sec65WorldId,
        'DIMX_WORLD_FILE' => dirname(__DIR__) . '/docs/dxw-examples/world3net.dxw',
    ];

    $logFile = \Dimx\GameEngine::getSystemFolder() . DIRECTORY_SEPARATOR . 'debug.log';
    $originalLog = file_exists($logFile) ? file_get_contents($logFile) : null;
    file_put_contents($logFile, ''); // Clear debug.log for isolated assertion

    // Step 1: User "Cris65" logs in as human player
    $loginHtml1 = runMultiplayer([
        'username' => 'Cris65',
        'password' => 'crisPass123',
    ], [], $serverW65);

    assertTest(str_contains($loginHtml1, '<!DOCTYPE html>'), "Cris65 received HTML scene upon initial login");
    assertTest(str_contains($loginHtml1, 'Cris65'), "Cris65 is displayed in HTML output");

    $logContent = file_get_contents($logFile);
    assertTest(str_contains($logContent, 'USER: Cris65 (p_'), "debug.log contains initial successful login with in-memory ID");
    assertTest(str_contains($logContent, 'STATUS: CONNECTED'), "debug.log contains STATUS: CONNECTED on successful login");
    assertTest(str_contains($logContent, 'COMMAND: login(,)'), "debug.log contains COMMAND: login(,)");

    // Step 2: Cris65 closed browser. Another tab or user immediately tries to log in with same name 'Cris65'
    file_put_contents($logFile, ''); // Clear log to inspect this specific request
    $loginConflictHtml = runMultiplayer([
        'username' => 'Cris65',
        'password' => 'crisPass123',
    ], [], $serverW65);

    assertTest(str_contains($loginConflictHtml, 'Invalid nickname - choose another'), "Login with active player returns 'Invalid nickname - choose another'");
    $logContentConflict = file_get_contents($logFile);
    assertTest(str_contains($logContentConflict, 'USER: Cris65'), "debug.log contains login attempt for Cris65");
    assertTest(str_contains($logContentConflict, 'STATUS: DISCONNECTED'), "debug.log contains STATUS: DISCONNECTED on nickname conflict");
    assertTest(str_contains($logContentConflict, 'COMMAND: login(,)'), "debug.log contains COMMAND: login(,) for rejected attempt");

    // Step 3: Test wrong password attempt is also logged to debug.log with STATUS: DISCONNECTED
    file_put_contents($logFile, '');
    $wrongPassHtml = runMultiplayer([
        'username' => 'Cris65',
        'password' => 'wrong_password_here',
    ], [], $serverW65);
    assertTest(str_contains($wrongPassHtml, 'Authentication failed') || str_contains($wrongPassHtml, 'Incorrect password'), "Wrong password returns authentication error");
    $logContentWrong = file_get_contents($logFile);
    assertTest(str_contains($logContentWrong, 'USER: Cris65'), "debug.log contains failed login attempt for Cris65 with wrong password");
    assertTest(str_contains($logContentWrong, 'STATUS: DISCONNECTED'), "debug.log contains STATUS: DISCONNECTED for wrong password attempt");

    // Step 4: Simulate 35 seconds passing with no contact from Cris65 (closed browser)
    $engine65 = new \Dimx\GameEngine($pm, $sec65WorldId, $serverW65['DIMX_WORLD_FILE'], '', ['debugTo' => 'file']);
    $w65 = $pm->loadWorld($sec65WorldId);
    $crisPlayer = $w65->getPlayerByName('Cris65');
    assertTest($crisPlayer !== null, "Cris65 exists in persisted world before timeout");
    $crisPlayer->lastInteraction = time() - 35;
    $crisPlayer->lastActive = time() - 35;
    $pm->saveWorld($w65);

    // Step 5: Cris65 returns and submits login with 'Cris65' and correct password
    file_put_contents($logFile, '');
    $loginHtmlReconnected = runMultiplayer([
        'username' => 'Cris65',
        'password' => 'crisPass123',
    ], [], $serverW65);

    assertTest(!str_contains($loginHtmlReconnected, 'Invalid nickname - choose another'), "Returning player after 35s is NOT blocked by nickname collision");
    assertTest(str_contains($loginHtmlReconnected, '<!DOCTYPE html>'), "Returning player receives HTML scene");
    assertTest(str_contains($loginHtmlReconnected, 'Cris65'), "Returning player scene includes Cris65");

    $logContentReconnected = file_get_contents($logFile);
    assertTest(str_contains($logContentReconnected, 'Pruning stale player Cris65'), "debug.log records stale player pruning of old Cris65 session");
    assertTest(str_contains($logContentReconnected, 'STATUS: CONNECTED'), "debug.log records STATUS: CONNECTED for reconnected Cris65");

    // Restore debug.log
    if ($originalLog !== null) {
        file_put_contents($logFile, $originalLog);
    } else {
        @unlink($logFile);
    }

    // Clean up
    @$pm->resetWorld($sec65WorldId);
}

// -------------------------------------------------------------
// Section 66: Testing UIhtml Sound & Music Support Property Storage, Snapshot Inspection & Audio Rendering
// -------------------------------------------------------------
if (shouldRun(66)) {
    echo "\n--- Section 66: Testing UIhtml Sound & Music Support Property Storage, Snapshot Inspection & Audio Rendering ---\n";

    // 1. UIhtml constructor defaults & methods
    $uiDefault = new \Dimx\UIhtml();
    assertTest($uiDefault->hasSounds() === true, "UIhtml default sounds is ON (true)");
    assertTest($uiDefault->hasMusic() === true, "UIhtml default music is ON (true)");

    $uiDefault->setSounds(false);
    $uiDefault->setMusic(false);
    assertTest($uiDefault->hasSounds() === false, "UIhtml setSounds(false) turns sounds OFF");
    assertTest($uiDefault->hasMusic() === false, "UIhtml setMusic(false) turns music OFF");

    // 2. Snapshot output on Player character
    $sec66WorldId = 'test_w_sec66_' . uniqid();
    $serverW66 = [
        'DIMX_WORLD_ID'   => $sec66WorldId,
        'DIMX_WORLD_FILE' => dirname(__DIR__) . '/docs/dxw-examples/world3net.dxw',
    ];

    // Login with Sounds & Music ON
    $sessionMusicOn = [];
    $htmlMusicOn = runMultiplayer([
        'username'   => 'AudioTester',
        'password'   => 'audioPass123',
        'sounds'     => '1',
        'music'      => '1',
        'has_sounds' => '1',
        'has_music'  => '1',
    ], $sessionMusicOn, $serverW66);

    assertTest(str_contains($htmlMusicOn, '<!DOCTYPE html>'), "AudioTester logged in successfully");
    assertTest(!isset($sessionMusicOn['dimx_sounds']), "Sound support is NOT stored in PHP \$_SESSION");
    assertTest(!isset($sessionMusicOn['dimx_music']), "Music support is NOT stored in PHP \$_SESSION");

    // Verify System Snapshot via $world->htmlAdminSnapshot()
    $engine66 = new \Dimx\GameEngine($pm, $sec66WorldId, $serverW66['DIMX_WORLD_FILE']);
    $w66 = $engine66->loadOrSeedWorld(false);
    $audioPlayer = $w66->getPlayerByName('AudioTester');
    assertTest($audioPlayer !== null, "AudioTester found in persisted world");
    assertTest($audioPlayer->getUI() instanceof \Dimx\UIhtml, "AudioTester has active UIhtml instance");
    assertTest($audioPlayer->getUI()->hasSounds() === true, "AudioTester UIhtml hasSounds() is true");
    assertTest($audioPlayer->getUI()->hasMusic() === true, "AudioTester UIhtml hasMusic() is true");

    $snapshotOn = $w66->htmlAdminSnapshot();
    assertTest(str_contains($snapshotOn, 'sound support: ON'), "System Snapshot contains 'sound support: ON'");
    assertTest(str_contains($snapshotOn, 'music support: ON'), "System Snapshot contains 'music support: ON'");

    // Login with Sounds & Music OFF
    $sessionMusicOff = [];
    $htmlMusicOff = runMultiplayer([
        'username'   => 'SilentTester',
        'password'   => 'silentPass123',
        'has_sounds' => '1',
        'has_music'  => '1',
    ], $sessionMusicOff, $serverW66);

    assertTest(str_contains($htmlMusicOff, '<!DOCTYPE html>'), "SilentTester logged in successfully");
    assertTest(!isset($sessionMusicOff['dimx_sounds']), "Sound support is NOT stored in PHP \$_SESSION for SilentTester");
    assertTest(!isset($sessionMusicOff['dimx_music']), "Music support is NOT stored in PHP \$_SESSION for SilentTester");

    $w66Reloaded = $engine66->loadOrSeedWorld(false);
    $silentPlayer = $w66Reloaded->getPlayerByName('SilentTester');
    assertTest($silentPlayer !== null, "SilentTester found in persisted world");
    assertTest($silentPlayer->getUI() instanceof \Dimx\UIhtml, "SilentTester has active UIhtml instance");
    assertTest($silentPlayer->getUI()->hasSounds() === false, "SilentTester UIhtml hasSounds() is false");
    assertTest($silentPlayer->getUI()->hasMusic() === false, "SilentTester UIhtml hasMusic() is false");

    $snapshotOff = $w66Reloaded->htmlAdminSnapshot();
    assertTest(str_contains($snapshotOff, 'sound support: OFF'), "System Snapshot contains 'sound support: OFF'");
    assertTest(str_contains($snapshotOff, 'music support: OFF'), "System Snapshot contains 'music support: OFF'");

    // 3. Audio / Background music rendering behavior
    // For AudioTester (music ON), playBackground queues music
    $audioPlayer->playBackground('test_track.mp3', true);
    assertTest($audioPlayer->backgroundSound !== null, "playBackground queues sound for AudioTester (music ON)");

    // For SilentTester (music OFF), playBackground is ignored
    $queued = $silentPlayer->playBackground('test_track.mp3', true);
    assertTest($queued === false, "playBackground returns false for SilentTester (music OFF)");
    assertTest($silentPlayer->backgroundSound === null, "playBackground does NOT queue sound for SilentTester");

    // Clean up
    @$pm->resetWorld($sec66WorldId);
}

// -------------------------------------------------------------
// Section 67: Testing SmallBasic getItemsIn Function (Objects, Sets, Arrays, $WORLD, $AGENT & loc_relic_vault in world.dxw)
// -------------------------------------------------------------
if (shouldRun(67)) {
    echo "\n--- Section 67: Testing SmallBasic getItemsIn Function (Objects, Sets, Arrays, \$WORLD, \$AGENT & loc_relic_vault in world.dxw) ---\n";

    // 1. Direct ActionsRunner unit tests with objects, rooms, items
    $sec67World = new \Dimx\World('w_sec67');
    $sec67World->name = 'Relic World';
    $roomVault = new \Dimx\Room($sec67World, 'Subterranean Relic Vault', 'loc_relic_vault');
    $sec67World->addRoom($roomVault);

    $roomGlade = new \Dimx\Room($sec67World, 'Hidden Glade', 'loc_glade');
    $sec67World->addRoom($roomGlade);

    $itemOrb = new \Dimx\Item($sec67World, 'Celestial Star Orb', 'celestial_orb', 'A glowing orb');
    $sec67World->addItem($itemOrb);
    $itemOrb->move($roomVault);

    $itemFlute = new \Dimx\Item($sec67World, 'Silver Flute', 'silver_flute', 'A silver flute');
    $sec67World->addItem($itemFlute);
    $itemFlute->move($roomGlade);

    $player = new \Dimx\Player($sec67World, 'Cris', 'p_tester');
    $sec67World->addPlayer($player);
    $player->container = $roomVault;

    // A. Empty inventory getItemsIn($AGENT)
    $runner = new \Dimx\ActionsRunner($sec67World, $roomVault, $player, null);
    $runner->run('Dim myItems = getItemsIn($AGENT)');
    $varItems = $runner->getLocalVar('myItems');
    assertTest(is_array($varItems) && count($varItems) === 0, "getItemsIn(\$AGENT) on empty inventory returns empty array");

    // B. Concatenation with empty array: "Items" + myItems
    $runner->run('Display "Items" + myItems');
    $cons = implode(' ', $player->drainConsole());
    assertTest(str_contains($cons, 'Items[]'), "Display 'Items' + myItems outputs 'Items[]' when empty: '{$cons}'");

    // C. Non-empty inventory getItemsIn($AGENT)
    $itemFlute->move($player);
    $runner2 = new \Dimx\ActionsRunner($sec67World, $roomVault, $player, null);
    $runner2->run('Dim myItems = getItemsIn($AGENT)');
    $varItems2 = $runner2->getLocalVar('myItems');
    assertTest(is_array($varItems2) && count($varItems2) === 1, "getItemsIn(\$AGENT) with item returns array with 1 item");
    assertTest(isset($varItems2['silver_flute']) && $varItems2['silver_flute'] === $itemFlute, "Item in array has key 'silver_flute' and Item object reference");

    $runner2->run('Display "Items" + myItems');
    $cons2 = implode(' ', $player->drainConsole());
    assertTest(str_contains($cons2, 'Items[silver_flute=Silver Flute]'), "Display 'Items' + myItems outputs 'Items[silver_flute=Silver Flute]': '{$cons2}'");

    // D. getItemsIn on room object and room string ID
    $runner->run('Dim vItems = getItemsIn(loc_relic_vault)');
    $varVItems = $runner->getLocalVar('vItems');
    assertTest(isset($varVItems['celestial_orb']), "getItemsIn(loc_relic_vault) returns celestial_orb");

    // E. getItemsIn on $WORLD returns all items
    $runner->run('Dim wItems = getItemsIn($WORLD)');
    $varWItems = $runner->getLocalVar('wItems');
    assertTest(count($varWItems) === 2 && isset($varWItems['celestial_orb']) && isset($varWItems['silver_flute']), "getItemsIn(\$WORLD) returns all items in the world");

    // F. getItemsIn on an array/set of rooms
    $runner->run('Dim setRooms = NewArray("loc_relic_vault,loc_glade")');
    $runner->run('Dim multiItems = getItemsIn(setRooms)');
    $varMulti = $runner->getLocalVar('multiItems');
    assertTest(isset($varMulti['celestial_orb']), "getItemsIn(setRooms) returns items from collection of rooms");

    // G. SetLen and For Each with getItemsIn
    $runner2->run('Dim numItems = SetLen(getItemsIn($AGENT))');
    $len = $runner2->getLocalVar('numItems');
    assertTest($len === 1, "SetLen(getItemsIn(\$AGENT)) returns 1");

    $runner2->run("Dim names = ''\nFor Each it In getItemsIn(\$AGENT)\nnames = names + it.name\nNext");
    $namesVal = $runner2->getLocalVar('names');
    assertTest($namesVal === 'Silver Flute', "For Each loop iterates items from getItemsIn(): '{$namesVal}'");

    // H. Associative key lookup: myItems("silver_flute")
    $runner2->run('Dim pickedObj = myItems("silver_flute")');
    $pickedObj = $runner2->getLocalVar('pickedObj');
    assertTest($pickedObj === $itemFlute, "Key lookup myItems(\"silver_flute\") returns Item object");

    // 2. Integration test with world.dxw
    $worldDxwFile = dirname(__DIR__, 2) . '/worlds/world.dxw';
    if (!file_exists($worldDxwFile)) {
        $worldDxwFile = dirname(__DIR__) . '/docs/dxw-examples/world.dxw';
    }

    if (file_exists($worldDxwFile)) {
        $sec67WorldId = 'test_w_sec67_' . uniqid();
        $pm->clearGoals($sec67WorldId);
        $engine67 = new \Dimx\GameEngine($pm, $sec67WorldId, $worldDxwFile);

        // Cris logs in and navigates: loc_shrine_entrance -> loc_courtyard -> loc_inner_sanctum -> loc_celestial_chamber -> loc_relic_vault
        $engine67->processCommand('Cris', 'look');
        $pStart = $engine67->loadOrSeedWorld()->getPlayerByName('Cris');
        assertTest($pStart !== null && $pStart->getRoom()->id === 'loc_shrine_entrance', "Cris started in loc_shrine_entrance");

        // Move N to loc_courtyard
        $engine67->processCommand('Cris', 'go', 'N');
        // Move N to loc_inner_sanctum
        $engine67->processCommand('Cris', 'go', 'N');
        // Move W to loc_celestial_chamber
        $engine67->processCommand('Cris', 'go', 'W');

        // Move D into loc_relic_vault with empty inventory
        $resD = $engine67->processCommand('Cris', 'go', 'D');
        $messagesD = json_encode($resD['messages'] ?? []);
        assertTest(str_contains($messagesD, 'Items[]') || str_contains($messagesD, "You don't have the talisman"), "Entering loc_relic_vault with empty inventory executes onReceive on console: {$messagesD}");

        // Move silver_flute into Cris's inventory
        $activeWorld = $engine67->loadOrSeedWorld();
        $crisObj = $activeWorld->getPlayerByName('Cris');
        $fluteObj = $activeWorld->getItem('silver_flute');
        if ($crisObj !== null && $fluteObj !== null) {
            $fluteObj->move($crisObj);
            $pm->saveWorld($activeWorld, $sec67WorldId);
        }

        // Move U back to celestial chamber
        $engine67->processCommand('Cris', 'go', 'U');

        // Move D back into loc_relic_vault with flute in inventory
        $resD2 = $engine67->processCommand('Cris', 'go', 'D');
        $messagesD2 = json_encode($resD2['messages'] ?? []);
        assertTest(str_contains($messagesD2, 'Items[silver_flute=Silver Flute]') || str_contains($messagesD2, "You don't have the talisman"), "Entering loc_relic_vault with Silver Flute executes onReceive on console: {$messagesD2}");

        // Clean up
        @$pm->resetWorld($sec67WorldId);
    }
}

// -------------------------------------------------------------
// Section 68: Testing SmallBasic SetContainsKey Function (Sets, Dictionaries, Arrays, Case-Insensitivity, Objects, & loc_relic_vault in world.dxw)
// -------------------------------------------------------------
if (shouldRun(68)) {
    echo "\n--- Section 68: Testing SmallBasic SetContainsKey Function (Sets, Dictionaries, Arrays, Case-Insensitivity, Objects, & loc_relic_vault in world.dxw) ---\n";

    // 1. Direct unit tests
    $sec68World = new \Dimx\World('w_sec68');
    $roomVault = new \Dimx\Room($sec68World, 'Subterranean Relic Vault', 'loc_relic_vault');
    $sec68World->addRoom($roomVault);

    $itemTalisman = new \Dimx\Item($sec68World, 'Ceremonial Talisman', 'talisman', 'A sacred paper talisman');
    $sec68World->addItem($itemTalisman);

    $player = new \Dimx\Player($sec68World, 'Cris', 'p_cris');
    $sec68World->addPlayer($player);
    $player->container = $roomVault;

    // A. Without talisman in inventory
    $runner = new \Dimx\ActionsRunner($sec68World, $roomVault, $player, null);
    $runner->run('Dim items = getItemsIn($AGENT)');
    $runner->run('Dim hasTalisman = SetContainsKey(items, "talisman")');
    $hasTalisman = $runner->getLocalVar('hasTalisman');
    assertTest($hasTalisman === false, "SetContainsKey(items, 'talisman') returns false when key is absent");

    $runner->run('Display "test: " + SetContainsKey(items, "talisman")');
    $cons = implode(' ', $player->drainConsole());
    assertTest(str_contains($cons, 'test: false'), "Display 'test: ' + SetContainsKey(items, 'talisman') outputs 'test: false': '{$cons}'");

    $runner->run("If SetContainsKey(items, \"talisman\")\nDisplay \"HAS_KEY\"\nElse\nDisplay \"NO_KEY\"\nEnd_If");
    $consIf = implode(' ', $player->drainConsole());
    assertTest(str_contains($consIf, 'NO_KEY') && !str_contains($consIf, 'HAS_KEY'), "If SetContainsKey branch jumps to Else when false: '{$consIf}'");

    // B. With talisman in inventory
    $itemTalisman->move($player);
    $runner2 = new \Dimx\ActionsRunner($sec68World, $roomVault, $player, null);
    $runner2->run('Dim items = getItemsIn($AGENT)');
    $runner2->run('Dim hasTalisman2 = SetContainsKey(items, "talisman")');
    $hasTalisman2 = $runner2->getLocalVar('hasTalisman2');
    assertTest($hasTalisman2 === true, "SetContainsKey(items, 'talisman') returns true when key is present");

    $runner2->run('Display "test: " + SetContainsKey(items, "talisman")');
    $cons2 = implode(' ', $player->drainConsole());
    assertTest(str_contains($cons2, 'test: true'), "Display 'test: ' + SetContainsKey(items, 'talisman') outputs 'test: true': '{$cons2}'");

    $runner2->run("If SetContainsKey(items, \"talisman\")\nDisplay \"HAS_KEY\"\nElse\nDisplay \"NO_KEY\"\nEnd_If");
    $consIf2 = implode(' ', $player->drainConsole());
    assertTest(str_contains($consIf2, 'HAS_KEY') && !str_contains($consIf2, 'NO_KEY'), "If SetContainsKey branch jumps to Then when true: '{$consIf2}'");

    // C. Case-insensitivity test
    $runner2->run('Dim hasUpper = SetContainsKey(items, "TALISMAN")');
    assertTest($runner2->getLocalVar('hasUpper') === true, "SetContainsKey is case-insensitive with 'TALISMAN'");
    $runner2->run('Dim hasMixed = SetContainsKey(items, "TaLiSmAn")');
    assertTest($runner2->getLocalVar('hasMixed') === true, "SetContainsKey is case-insensitive with 'TaLiSmAn'");

    // D. Key passed as DimxObject
    $runner2->run('Dim hasObj = SetContainsKey(items, talisman)');
    assertTest($runner2->getLocalVar('hasObj') === true, "SetContainsKey accepts DimxObject item as key argument");

    // E. Indexed / numeric array keys
    $runner->run('Dim arr = Split("alpha,beta,gamma", ",")');
    $runner->run('Dim hasKey1 = SetContainsKey(arr, 1)');
    $runner->run('Dim hasKey99 = SetContainsKey(arr, 99)');
    assertTest($runner->getLocalVar('hasKey1') === true, "SetContainsKey(arr, 1) returns true for numeric key 1");
    assertTest($runner->getLocalVar('hasKey99') === false, "SetContainsKey(arr, 99) returns false for non-existent key 99");

    // F. Non-array or empty collection checks
    $runner->run('Dim nonArr = SetContainsKey("not_an_array", "key")');
    assertTest($runner->getLocalVar('nonArr') === false, "SetContainsKey on non-array returns false");
    $runner->run('Dim emptyArr = NewArray()');
    $runner->run('Dim emptyRes = SetContainsKey(emptyArr, "key")');
    assertTest($runner->getLocalVar('emptyRes') === false, "SetContainsKey on empty array returns false");

    // 2. Integration test with world.dxw
    $worldDxwFile = dirname(__DIR__, 2) . '/worlds/world.dxw';
    if (!file_exists($worldDxwFile)) {
        $worldDxwFile = dirname(__DIR__) . '/docs/dxw-examples/world.dxw';
    }

    if (file_exists($worldDxwFile)) {
        $sec68WorldId = 'test_w_sec68_' . uniqid();
        $pm->clearGoals($sec68WorldId);
        $engine68 = new \Dimx\GameEngine($pm, $sec68WorldId, $worldDxwFile);

        // Cris logs in and navigates: loc_shrine_entrance -> loc_courtyard -> loc_inner_sanctum -> loc_celestial_chamber -> loc_relic_vault
        $engine68->processCommand('Cris', 'look');

        // Move N to loc_courtyard
        $engine68->processCommand('Cris', 'go', 'N');
        // Move N to loc_inner_sanctum
        $engine68->processCommand('Cris', 'go', 'N');
        // Move W to loc_celestial_chamber
        $engine68->processCommand('Cris', 'go', 'W');

        // Move D into loc_relic_vault WITHOUT talisman
        $resD = $engine68->processCommand('Cris', 'go', 'D');
        $messagesD = json_encode($resD['messages'] ?? []);
        assertTest(str_contains($messagesD, "You don't have the talisman"), "loc_relic_vault without talisman outputs ' You don\\'t have the talisman': {$messagesD}");
        assertTest(!str_contains($messagesD, 'WOO! you have got the talisman'), "loc_relic_vault without talisman does NOT output 'WOO! you have got the talisman'");

        // Move talisman into Cris's inventory
        $activeWorld = $engine68->loadOrSeedWorld();
        $crisObj = $activeWorld->getPlayerByName('Cris');
        $talismanInWorld = $activeWorld->getItem('talisman');
        if ($crisObj !== null && $talismanInWorld !== null) {
            $talismanInWorld->move($crisObj);
            $pm->saveWorld($activeWorld, $sec68WorldId);
        }

        // Move U back to celestial chamber
        $engine68->processCommand('Cris', 'go', 'U');

        // Move D back into loc_relic_vault WITH talisman
        $resD2 = $engine68->processCommand('Cris', 'go', 'D');
        $messagesD2 = json_encode($resD2['messages'] ?? []);
        assertTest(str_contains($messagesD2, 'WOO! you have got the talisman'), "loc_relic_vault WITH talisman outputs 'WOO! you have got the talisman': {$messagesD2}");
        assertTest(!str_contains($messagesD2, "You don't have the talisman"), "loc_relic_vault WITH talisman does NOT output ' You don\\'t have the talisman'");

        // Clean up
        @$pm->resetWorld($sec68WorldId);
    }
}

// -------------------------------------------------------------
// Section 69: Testing SmallBasic Expression Error Handling & Custom Functions/Subs (getObjectsType, containsType in world.dxw)
// -------------------------------------------------------------
if (shouldRun(69)) {
    echo "\n--- Section 69: Testing SmallBasic Expression Error Handling & Custom Functions/Subs (getObjectsType, containsType in world.dxw) ---\n";

    $sec69World = new \Dimx\World('w_sec69');
    $sec69Player = new \Dimx\Player($sec69World, 'Explorer', 'p_sec69');
    $sec69World->addPlayer($sec69Player);
    $sec69Runner = new \Dimx\ActionsRunner($sec69World, null, $sec69Player, null, [], true);

    // 1. Part 1: Unknown function/array in Display statement triggers error, logs and displays via actDisplay, proceeds
    $sec69Player->clientConsole = [];
    $scriptErr1 = "Display \"First instruction\"\nDisplay \"Val: \" + unknownFunction(1, 2)\nDisplay \"Third instruction\"";
    $sec69Runner->run($scriptErr1);
    assertTest(count($sec69Player->clientConsole) === 3, "Execution proceeded after unknown function in Display");
    assertTest($sec69Player->clientConsole[0] === 'First instruction', "Output contains 'First instruction'");
    assertTest(str_contains($sec69Player->clientConsole[1], 'ERROR: Unknown Function or Array: unknownFunction'), "Output displays 'ERROR: Unknown Function or Array: unknownFunction': '{$sec69Player->clientConsole[1]}'");
    assertTest($sec69Player->clientConsole[2] === 'Third instruction', "Output contains 'Third instruction'");

    // 2. Part 1: Unknown function in IF statement condition triggers error, skips IF block to End_If, proceeds
    $sec69Player->clientConsole = [];
    $scriptErr2 = "Display \"Start\"\nIf missingCondition(42)\nDisplay \"Inside Then\"\nElse\nDisplay \"Inside Else\"\nEnd_If\nDisplay \"End\"";
    $sec69Runner->run($scriptErr2);
    assertTest(count($sec69Player->clientConsole) === 3, "Execution proceeded after unknown function in IF");
    assertTest($sec69Player->clientConsole[0] === 'Start', "Output contains 'Start'");
    assertTest(str_contains($sec69Player->clientConsole[1], 'ERROR: Unknown Function or Array: missingCondition'), "Output displays 'ERROR: Unknown Function or Array: missingCondition': '{$sec69Player->clientConsole[1]}'");
    assertTest($sec69Player->clientConsole[2] === 'End', "Output contains 'End'");

    // 3. Part 1: Unbalanced parentheses syntax error
    $sec69Player->clientConsole = [];
    $scriptErr3 = "Display (1 + 2\nDisplay \"After paren error\"";
    $sec69Runner->run($scriptErr3);
    assertTest(count($sec69Player->clientConsole) === 2, "Execution proceeded after unbalanced parens error");
    assertTest(str_contains($sec69Player->clientConsole[0], 'ERROR: Syntax error: unbalanced parentheses'), "Output displays 'ERROR: Syntax error: unbalanced parentheses': '{$sec69Player->clientConsole[0]}'");
    assertTest($sec69Player->clientConsole[1] === 'After paren error', "Output contains 'After paren error'");

    // 4. Part 2: Custom Function and Sub definitions
    $customFuncScript = "Function doubleVal(n)\nReturn n * 2\nEnd_Function\n";
    $eventFunc = new \Dimx\Event('doubleVal', 'FUNCTION', null, ['n']);
    $eventFunc->actions = "Return n * 2";
    $sec69World->registerEvent($eventFunc);

    // Call custom function inside expression
    $sec69Player->clientConsole = [];
    $sec69Runner->run('Display "Double 5 is " + doubleVal(5)');
    assertTest(($sec69Player->clientConsole[0] ?? '') === 'Double 5 is 10', "Custom function doubleVal(5) evaluated in expression: '{$sec69Player->clientConsole[0]}'");

    // 5. Part 2: getObjectsType built-in function
    $roomVault = new \Dimx\Room($sec69World, 'Vault', 'r_vault');
    $sec69World->addRoom($roomVault);
    $itemTalis = new \Dimx\Item($sec69World, 'Talisman', 'talisman');
    $itemTalis->setProperty('type', 'talis');
    $itemTalis->move($roomVault);

    $itemOrb = new \Dimx\Item($sec69World, 'Orb', 'orb');
    $itemOrb->setProperty('type', 'crystal');
    $itemOrb->move($roomVault);

    $sec69Runner->run('Dim foundItems = getObjectsType(r_vault, "talis")');
    $found = $sec69Runner->getLocalVar('foundItems');
    assertTest(is_array($found) && isset($found['talisman']) && !isset($found['orb']), "getObjectsType(r_vault, 'talis') matches talisman by prefix");

    $sec69Runner->run('Dim noneFound = getObjectsType(r_vault, "magic")');
    $notFound = $sec69Runner->getLocalVar('noneFound');
    assertTest(is_array($notFound) && empty($notFound), "getObjectsType(r_vault, 'magic') returns empty array for non-matching type");

    // 6. Part 2: world.dxw containsType function execution
    $worldDxwPath = dirname(__DIR__, 2) . '/worlds/world.dxw';
    if (!file_exists($worldDxwPath)) {
        $worldDxwPath = dirname(__DIR__) . '/docs/dxw-examples/world.dxw';
    }
    if (file_exists($worldDxwPath)) {
        $sec69WorldId = 'test_w_sec69_' . uniqid();
        $pm->clearGoals($sec69WorldId);
        $engine69 = new \Dimx\GameEngine($pm, $sec69WorldId, $worldDxwPath);
        $worldInst = $engine69->loadOrSeedWorld();

        // Verify containsType was parsed and registered
        $evContainsType = $worldInst->getEvent('containsType');
        assertTest($evContainsType !== null, "containsType function is registered in world.dxw");
        assertTest($evContainsType?->type === 'FUNCTION', "containsType event type is FUNCTION");
        assertTest($evContainsType?->formalParams === ['container', 'type', 'recursive'], "containsType has correct formal parameters");

        // Verify entering loc_relic_vault with and without talisman
        $engine69->processCommand('Cris', 'look');
        $engine69->processCommand('Cris', 'go', 'N'); // courtyard
        $engine69->processCommand('Cris', 'go', 'N'); // inner sanctum
        $engine69->processCommand('Cris', 'go', 'W'); // celestial chamber

        // Enter loc_relic_vault WITHOUT talisman
        $resNoTalis = $engine69->processCommand('Cris', 'go', 'D');
        $msgNoTalis = json_encode($resNoTalis['messages'] ?? []);
        assertTest(str_contains($msgNoTalis, "You don't have the talisman"), "loc_relic_vault without talisman outputs ' You don\\'t have the talisman': {$msgNoTalis}");
        assertTest(!str_contains($msgNoTalis, 'WOO! you have got the talisman'), "loc_relic_vault without talisman does NOT output 'WOO!': {$msgNoTalis}");

        // Acquire talisman
        $wActive = $engine69->loadOrSeedWorld();
        $crisPlayer = $wActive->getPlayerByName('Cris');
        $talismanItem = $wActive->getItem('talisman');
        if ($crisPlayer !== null && $talismanItem !== null) {
            $talismanItem->move($crisPlayer);
            $pm->saveWorld($wActive, $sec69WorldId);
        }

        // Return U and re-enter D WITH talisman
        $engine69->processCommand('Cris', 'go', 'U');
        $resWithTalis = $engine69->processCommand('Cris', 'go', 'D');
        $msgWithTalis = json_encode($resWithTalis['messages'] ?? []);
        assertTest(str_contains($msgWithTalis, 'WOO! you have got the talismantrue'), "loc_relic_vault WITH talisman outputs 'WOO! you have got the talismantrue': {$msgWithTalis}");
        assertTest(!str_contains($msgWithTalis, "You don't have the talisman"), "loc_relic_vault WITH talisman does NOT output ' You don\\'t have the talisman'");

        // Clean up
        @$pm->resetWorld($sec69WorldId);
    }
}

// -------------------------------------------------------------
// Section 70: Testing Character::give() Standard DimX Events
// -------------------------------------------------------------
if (shouldRun(70)) {
    echo "\n--- Section 70: Testing Character::give() Standard DimX Events (whenPicked, onReceiveItem, onLooseItem, No whenGiven/whenReceived) ---\n";

    $sec70WorldId = 'test_w_sec70_' . uniqid();
    $w70 = new \Dimx\World($sec70WorldId, 'Sec70 World');
    $room70 = new \Dimx\Room($w70, 'Room 70', 'r70');
    $w70->addRoom($room70);

    $giver = new \Dimx\Character($w70, 'Alice', 'char_alice');
    $receiver = new \Dimx\Character($w70, 'Bob', 'char_bob');
    $receiver->accepts = '*';
    $gift = new \Dimx\Item($w70, 'Magic Wand', 'wand');
    $gift->pickable = true;

    $w70->addCharacter($giver);
    $w70->addCharacter($receiver);
    $w70->addItem($gift);

    $giver->move($room70);
    $receiver->move($room70);
    $gift->move($giver);

    // Track all events fired via an event recording subclass or world event handlers
    $firedEvents = [];
    $recordEvent = function (string $evtName) use (&$firedEvents) {
        $firedEvents[] = $evtName;
        return true;
    };

    $w70->registerEvent(new \Dimx\Event('onLooseItem', 'EVENT', function () use ($recordEvent) {
        return $recordEvent('onLooseItem');
    }));
    $w70->registerEvent(new \Dimx\Event('whenPicked', 'EVENT', function () use ($recordEvent) {
        return $recordEvent('whenPicked');
    }));
    $w70->registerEvent(new \Dimx\Event('onReceiveItem', 'EVENT', function () use ($recordEvent) {
        return $recordEvent('onReceiveItem');
    }));
    $w70->registerEvent(new \Dimx\Event('whenGiven', 'EVENT', function () use ($recordEvent) {
        return $recordEvent('whenGiven');
    }));
    $w70->registerEvent(new \Dimx\Event('whenReceived', 'EVENT', function () use ($recordEvent) {
        return $recordEvent('whenReceived');
    }));

    $giveSuccess = $giver->actGive($gift, $receiver);
    assertTest($giveSuccess === true, "giver->actGive() returned true");
    assertTest($gift->container === $receiver, "Item container is now the recipient character");
    assertTest(in_array('onLooseItem', $firedEvents, true), "Fired standard onLooseItem event");
    assertTest(in_array('whenPicked', $firedEvents, true), "Fired standard whenPicked event");
    assertTest(in_array('onReceiveItem', $firedEvents, true), "Fired standard onReceiveItem event");
    assertTest(!in_array('whenGiven', $firedEvents, true), "Did NOT fire non-standard whenGiven event");
    assertTest(!in_array('whenReceived', $firedEvents, true), "Did NOT fire non-standard whenReceived event");

    // Test giving back via itemGive() alias
    $giver->accepts = '*';
    $firedEvents = [];
    $giveBack = $receiver->itemGive('wand', 'char_alice');
    assertTest($giveBack === true, "receiver->itemGive('wand', 'char_alice') returned true");
    assertTest($gift->container === $giver, "Item returned to giver");
    assertTest(in_array('whenPicked', $firedEvents, true) && in_array('onReceiveItem', $firedEvents, true), "Fired whenPicked and onReceiveItem on give back");
    assertTest(!in_array('whenGiven', $firedEvents, true) && !in_array('whenReceived', $firedEvents, true), "Did NOT fire whenGiven/whenReceived on give back");
}

// Clean up test worlds
if (isset($pm)) {
    if (isset($testWorldId)) {
        @$pm->resetWorld($testWorldId);
    }
    if (isset($unitWorldId)) {
        @$pm->resetWorld($unitWorldId);
    }
    @$pm->resetWorld('w3_sec39_test');
}

$executedCount = count($executedSections);
$totalCount = count($sections);
if ($executedCount < $totalCount) {
    echo "\n=== Summary: {$passCount} passed, {$failCount} failed (filtered: {$executedCount}/{$totalCount} sections executed) ===\n";
} else {
    echo "\n=== Summary: {$passCount} passed, {$failCount} failed ===\n";
}
exit($failCount > 0 ? 1 : 0);
