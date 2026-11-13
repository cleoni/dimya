<?php
declare(strict_types=1);

/**
 * test_multiplayer.php
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
    21 => ['title' => 'Testing ROOM DEFAULT Directive & Player Initial Spawn Room Resolution...', 'keywords' => ['default', 'room default', 'spawn', 'startroom', 'world3net', 'spiaggia2', 'parcolunetta']],
    22 => ['title' => 'Testing Character/Player Default showMode (ONSCREEN=1), UIjson vs UIbot Exposure & UIhtml Rendering...', 'keywords' => ['showmode', 'onscreen', 'offscreen', 'uijson', 'uibot', 'uihtml', 'character', 'player', 'avatar', 'people']],
    23 => ['title' => 'Testing SETS Section, RndSet Function & Initial Object Positioning (scroll3)...', 'keywords' => ['sets', 'set', 'rndset', 'scroll3', 'setbeach', 'position', 'defcontainer', 'reset']],
    24 => ['title' => 'Testing Room Description on Link Traversal (go command) & UIhtml Rendering...', 'keywords' => ['go', 'traverse', 'link', 'description', 'room', 'uihtml', 'travel to']],
    25 => ['title' => 'Testing Navigation Pad Missing LINK Name Fallback to Extended Direction Name (UIhtml)...', 'keywords' => ['navpad', 'navigation', 'link', 'name', 'direction', 'uihtml', 'extended', 'north', 'south', 'east', 'west', 'up', 'down']],
    26 => ['title' => 'Testing On-Screen Links (SHOW ONSCREEN, IMAGE, ICON on LINK / MLINK) & Trapdoor (w6) UIhtml Rendering...', 'keywords' => ['link', 'mlink', 'onscreen', 'w6', 'trapdoor', 'td.gif', 'show', 'image', 'icon', 'uihtml', 'scene']],
    27 => ['title' => 'Testing Multi-line Double-Quoted Strings & Barman Dialogue Cycle (world3net)...', 'keywords' => ['multiline', 'string', 'quote', 'barman', 'onhear', 'onspeechfinish', 'lattina', 'speak', 'cycle']],
    28 => ['title' => 'Testing use2 ("Use With") Command, Default Panel Button & Trapdoor Unlocking (key1 on w6)...', 'keywords' => ['use2', 'usewith', 'key1', 'w6', 'onusewith', 'panel', 'defaultpanel', 'trapdoor', 'unlock']],
    29 => ['title' => 'Testing Hidden Objects (hidden=1) in UIhtml, UIbot, and UIjson (world3net treasure)...', 'keywords' => ['hidden', 'ishidden', 'treasure', 'beachcliff', 'uihtml', 'uibot', 'uijson', 'attrlist']],
    30 => ['title' => 'Testing Display & Call Instructions, Keyed Image Assignment & switch1.onUse (world3net)...', 'keywords' => ['display', 'call', 'switch1', 'gate1', 'onuse', 'onopen', 'image', 'actionsrunner']],
    31 => ['title' => 'Testing Server Setting tickLength & Clock Tick Management (world3net switch1 & onTick)...', 'keywords' => ['tick', 'ticklength', 'ontick', 'lasttick', 'clock', 'interval', 'config', 'switch1', 'gate1']],
    32 => ['title' => 'Testing Search Command, onSearch Event & Revealing Hidden Objects (world3net)...', 'keywords' => ['search', 'onsearch', 'hidden', 'visible', 'found nothing new', 'treasure', 'forbici']],
    33 => ['title' => 'Testing Give Command, Default Panel Button & onReceiveItem Quest Transfer (world3net Tommy & apple)...', 'keywords' => ['give', 'itemgive', 'actgive', 'panel', 'tommy', 'cell', 'apple', 'onreceiveitem', 'accepts']],
    34 => ['title' => "Testing Apostrophes in ROOM & CHARACTER Definitions (world3net parcoAnatre 'The duck\\'s house')...", 'keywords' => ['apostrophe', 'comment', 'name', 'room', 'character', 'parcoanatre', 'duck', 'house', 'dxwloader']],
    35 => ['title' => 'Testing 3x3 Navpad Table, Default Skin Arrow Icons & Direction Placement (UIhtml)...', 'keywords' => ['navpad', 'skin', 'default', 'arrow', 'icons', 'direction', 'n', 'w', 'e', 's', 'uihtml']],
    36 => ['title' => 'Testing Player Orientation, Multi-faced Rooms, Rotation Commands (rotl/rotr/rev) & Navpad Multiface...', 'keywords' => ['facing', 'orientation', 'multiface', 'pathgate', 'rotl', 'rotr', 'rev', 'rotate', 'view', 'title', 'navpad']],
    37 => ['title' => 'Testing SHOWAREA Bounds & Baseline Positioning for Onscreen Objects...', 'keywords' => ['showarea', 'position', 'onscreen', 'kitchen', 'tommy', 'switch1', 'baseline', 'coords']],
    38 => ['title' => "Testing Non-pickable Items, PickUp Button Label & Locked Link Open Message ('It\\'s locked.')...", 'keywords' => ['pickable', 'trash', 'pickup', 'locked', 'w22', 'w21', 'open']],
    39 => ['title' => 'Testing Use With (use2) Execution Semantics: Done! on Success vs Nothing happens. (key2 on w22 vs w21 and key5 on w21)...', 'keywords' => ['use2', 'usewith', 'key2', 'key5', 'w21', 'w22', 'done', 'nothing happens', 'onusewith']],
    40 => ['title' => 'Testing VEHICLE Support & Onscreen Display/Click in bungalowStanza (world3net tele1 & tele2)...', 'keywords' => ['vehicle', 'tele1', 'tele2', 'bungalowstanza', 'teletransporter', 'innerimage', 'click', 'onscreen']],
    41 => ['title' => 'Testing System Snapshot (htmlAdminSnapshot) & maint.php Snapshot Action...', 'keywords' => ['snapshot', 'maint', 'htmladminsnapshot', 'adminpasswd', 'system snapshot', 'sec41']],
    42 => ['title' => 'Testing SKINS Resolution (IMAGESFOLDER / baseUrl), No Hardcoded Localhost & Navpad Arrow Icons...', 'keywords' => ['skins', 'imagesfolder', 'base_url', 'localhost', 'navpad', 'icowayn', 'dxwloader', 'skin']],
    43 => ['title' => 'Testing Default Item Icon (ICOITEM) & Fallbacks in UIhtml and Skin...', 'keywords' => ['icoitem', 'item icon', 'skin', 'default icon', 'uihtml', 'inventory', 'room items']],
    44 => ['title' => 'Testing SKINFOLDER Resolution, aqua_net.dxs URL Loading, Login Skin Dropdown & Per-Player Skin Navpad Icons...', 'keywords' => ['skinfolder', 'aqua_net', 'aqua', 'forest', 'default', 'dropdown', 'login', 'navpad', 'player skin']],
    45 => ['title' => 'Testing Sounds & Music Login Checkboxes, PlaySound Routing (Player, Room, $WORLD), Embedded Sound Player & Skin SNDNEWMSG...', 'keywords' => ['sounds', 'music', 'playsound', 'sndnewmsg', 'sndplayer', 'audio', 'skin', 'uihtml']],
    46 => ['title' => 'Testing Say Command Target Handling (Speak to Person vs Speak Aloud with target=* in UIhtml & GameEngine)...', 'keywords' => ['say', 'target', 'speak', 'aloud', 'asterisk', 'txtbox', 'uihtml', 'character']],
    47 => ['title' => 'Testing SmallBasic Debug Instruction & GameEngine::log() Writing to debug.log...', 'keywords' => ['debug', 'log', 'debug.log', 'gameengine', 'actionsrunner', 'system folder', 'smallbasic']],
    48 => ['title' => 'Testing Resolution Handling & Login Form Cleanliness (no screenSizeHelper, non-portrait 640x480/800x600)...', 'keywords' => ['screensize', 'landscape', 'resolution', 'portrait', 'uihtml', 'login']],
    49 => ['title' => 'Testing NewImage Function, ImagesFolder Resolution & Image Display/Assignment in SmallBasic...', 'keywords' => ['newimage', 'image', 'smallbasic', 'actionsrunner', 'display', 'imagesfolder']],
    50 => ['title' => 'Testing Item Default SHOW_OFFSCREEN (0), Zoom on Click/Look & Fader Backdrop Overlay...', 'keywords' => ['zoom', 'fader', 'showmode', 'offscreen', 'focus', 'click', 'look', 'screwdriver', 'fader_box']],
    51 => ['title' => 'Testing SmallBasic DropItems Instruction & Notification (Player, NPC, Container)...', 'keywords' => ['dropitems', 'actionsrunner', 'inventory', 'drop', 'slipped']],
    52 => ['title' => 'Testing Put / Insert Command (2-Object OO Model, Capacity, Closed Checks, Events & UIhtml Definitions)...', 'keywords' => ['put', 'insert', 'actput', 'itemput', 'container', 'capacity', 'closed', 'uihtml', 'command_defs']],
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

function runMultiplayer(array $get = [], array $post = [], array $server = []): string
{
    if (!isset($server['DIMX_WORLD_FILE'])) {
        $server['DIMX_WORLD_FILE'] = __DIR__ . '/../docs/dxw-examples/world2.dxw';
    }
    if (!isset($server['DIMX_WORLD_ID'])) {
        $server['DIMX_WORLD_ID'] = 'test_unit_w2';
    }
    $runner = sys_get_temp_dir() . '/dimx_run_' . uniqid() . '.php';
    $code = "<?php\n"
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
    assertTest(!isset($input['world']) && !isset($input['world_file']), "World switching parameters are ignored (single world)");
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
if (shouldRun(21)) {
    echo "\n21. Testing ROOM DEFAULT Directive & Player Initial Spawn Room Resolution...\n";

    $dxwWorld3 = dirname(__DIR__) . '/docs/dxw-examples/world3net.dxw';
    $w3Loader = new \Dimx\DxwLoader('http://localhost');
    $w3 = $w3Loader->load($dxwWorld3);

    // Verify DxwLoader set startRoom and defaultRoom from 'ROOM spiaggia2 DEFAULT'
    assertTest($w3->startRoom instanceof \Dimx\Room, "World3 has an initialized startRoom");
    assertTest(($w3->startRoom->id ?? '') === 'spiaggia2', "World3 startRoom is 'spiaggia2'");
    assertTest(($w3->defaultRoom ?? '') === 'spiaggia2', "World3 defaultRoom property is 'spiaggia2'");
    assertTest(!empty($w3->rooms['spiaggia2']->getProperty('DEFAULT')), "Room spiaggia2 has property 'DEFAULT' = 1");
    assertTest(empty($w3->rooms['parcoLunetta']->getProperty('DEFAULT')), "First defined room parcoLunetta does NOT have property 'DEFAULT'");

    // Verify player spawn via GameEngine::ensurePlayer
    $testW3Engine = new \Dimx\GameEngine($pm, 'test_w3_spawn_' . rand(1000, 9999), $dxwWorld3);
    $spawnedPlayer = $testW3Engine->ensurePlayer($w3, 'NewBeachExplorer_' . rand(1000, 9999));
    assertTest($spawnedPlayer->getRoom() instanceof \Dimx\Room, "New player was placed in a room");
    assertTest(($spawnedPlayer->getRoom()->id ?? '') === 'spiaggia2', "New player spawned into designated DEFAULT room 'spiaggia2' rather than first room 'parcoLunetta'");
    assertTest(($spawnedPlayer->getRoom()->name ?? '') === 'The beach and the palm trees', "Spawned room title is 'The beach and the palm trees'");
}

// ----------------------------------------------------------------------------
// 22. Character/Player Default showMode (ONSCREEN=1), UIjson vs UIbot Exposure & UIhtml Rendering
// ----------------------------------------------------------------------------
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
if (shouldRun(23)) {
    echo "\n23. " . $sections[23]['title'] . "\n";
    $executedSections[] = 23;

    $dxwWorld3 = dirname(__DIR__) . '/docs/dxw-examples/world3net.dxw';
    $w3Loader = new \Dimx\DxwLoader('http://localhost');
    $w3 = $w3Loader->load($dxwWorld3);

    // Verify SETS parsing
    assertTest(!empty($w3->sets), "World has parsed sets");
    assertTest(isset($w3->sets['setBeach']), "setBeach is defined in world sets");
    assertTest(count($w3->sets['setBeach']) === 4, "setBeach contains 4 beach rooms");
    assertTest($w3->sets['setBeach'] === ['spiaggia', 'spiaggia2', 'spiaggia3', 'spiaggia4'], "setBeach elements match spiaggia, spiaggia2, spiaggia3, spiaggia4");

    // Verify other sets defined in world3net.dxw
    assertTest(isset($w3->sets['setFaraway']), "setFaraway is defined");
    assertTest(isset($w3->sets['setValigie']), "setValigie is defined");
    assertTest(isset($w3->sets['setPark']), "setPark is defined");
    assertTest(isset($w3->sets['setTutte']), "setTutte is defined");

    // Verify scroll3 item definition and initial positioning
    $scroll3 = $w3->getItem('scroll3');
    assertTest($scroll3 instanceof \Dimx\Item, "Item scroll3 exists in loaded world");
    assertTest($scroll3->defContainer === 'rndSet(setBeach)', "scroll3 defContainer is 'rndSet(setBeach)'");
    assertTest($scroll3->container instanceof \Dimx\Room, "scroll3 has a resolved container room");
    assertTest(in_array($scroll3->container?->id, $w3->sets['setBeach'], true), "scroll3 is positioned inside one of the setBeach rooms (" . ($scroll3->container?->id ?? 'null') . ")");

    // Verify character surfer positioning via RndSet
    $surfer = $w3->getCharacter('surfer');
    assertTest($surfer instanceof \Dimx\Character, "Character surfer exists in loaded world");
    assertTest($surfer->container instanceof \Dimx\Room, "surfer has a resolved container room");
    assertTest(in_array($surfer->container?->id, $w3->sets['setBeach'], true), "surfer is positioned inside one of the setBeach rooms (" . ($surfer->container?->id ?? 'null') . ")");

    // Verify AdvObject::reset() re-evaluates rndSet
    $scroll3->container = null;
    $scroll3->reset();
    assertTest($scroll3->container instanceof \Dimx\Room, "scroll3->reset() successfully re-positions item");
    assertTest(in_array($scroll3->container?->id, $w3->sets['setBeach'], true), "scroll3->reset() places item in a setBeach room");

    // Verify ActionsRunner SmallBasic RndSet evaluation
    $runner = new \Dimx\ActionsRunner($w3);
    $reflection = new \ReflectionClass($runner);
    $evalMethod = $reflection->getMethod('evalValue');
    $evalMethod->setAccessible(true);

    $chosenRoom = $evalMethod->invoke($runner, 'rndSet(setBeach)');
    assertTest(in_array($chosenRoom, $w3->sets['setBeach'], true), "ActionsRunner evalValue rndSet(setBeach) returns an element from setBeach ('{$chosenRoom}')");

    $chosenRoomWithWorld = $evalMethod->invoke($runner, 'RndSet($WORLD.setBeach)');
    assertTest(in_array($chosenRoomWithWorld, $w3->sets['setBeach'], true), "ActionsRunner evalValue RndSet(\$WORLD.setBeach) returns an element from setBeach ('{$chosenRoomWithWorld}')");

    // Verify key5 and setValigie initial placement in closed containers
    $key5 = $w3->getItem('key5');
    assertTest($key5 instanceof \Dimx\Item, "Item key5 exists in loaded world");
    assertTest($key5->defContainer === 'rndSet(setValigie)', "key5 defContainer is 'rndSet(setValigie)'");
    assertTest($key5->container !== null, "key5 is positioned in a container (not NULL, got: " . ($key5->container?->id ?? 'null') . ")");
    assertTest(in_array($key5->container?->id, $w3->sets['setValigie'], true), "key5 container is an element of setValigie ('" . ($key5->container?->id ?? 'null') . "')");

    // Verify Move instruction bypasses closed container checks
    $valigia = $w3->getItem('valigia');
    assertTest($valigia instanceof \Dimx\Item, "Item valigia exists in loaded world");
    assertTest(!$valigia->isOpen(), "valigia is initially closed (open=0)");
    $moveRes = $runner->run('Move key5, valigia');
    assertTest($moveRes === true, "Move instruction into closed container returns true");
    assertTest($key5->container === $valigia, "key5 container is now valigia after Move instruction bypassed closed container check");
}

// -------------------------------------------------------------
// Test 24: Room Description on Link Traversal (go command) & UIhtml Rendering
// -------------------------------------------------------------
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
    assertTest(str_contains($roccaOutput, $roccaDesc), "Engine output upon room arrival contains room description ('{$roccaDesc}')");

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
    assertTest(str_contains($bungalowOutput, $bungalowDesc), "Engine output upon arrival in bungalow contains description ('{$bungalowDesc}')");

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
if (shouldRun(25)) {
    echo "\n25. Testing Navigation Pad Missing LINK Name Fallback to Extended Direction Name (UIhtml)...\n";

    // 1. Static helper tests
    assertTest(\Dimx\Link::getExtendedDirectionName('N') === 'North', "getExtendedDirectionName('N') returns 'North'");
    assertTest(\Dimx\Link::getExtendedDirectionName('S') === 'South', "getExtendedDirectionName('S') returns 'South'");
    assertTest(\Dimx\Link::getExtendedDirectionName('E') === 'East', "getExtendedDirectionName('E') returns 'East'");
    assertTest(\Dimx\Link::getExtendedDirectionName('W') === 'West', "getExtendedDirectionName('W') returns 'West'");
    assertTest(\Dimx\Link::getExtendedDirectionName('U') === 'Up', "getExtendedDirectionName('U') returns 'Up'");
    assertTest(\Dimx\Link::getExtendedDirectionName('D') === 'Down', "getExtendedDirectionName('D') returns 'Down'");
    assertTest(\Dimx\Link::getExtendedDirectionName('NE') === 'Northeast', "getExtendedDirectionName('NE') returns 'Northeast'");
    assertTest(\Dimx\Link::getExtendedDirectionName('NW') === 'Northwest', "getExtendedDirectionName('NW') returns 'Northwest'");
    assertTest(\Dimx\Link::getExtendedDirectionName('SE') === 'Southeast', "getExtendedDirectionName('SE') returns 'Southeast'");
    assertTest(\Dimx\Link::getExtendedDirectionName('SW') === 'Southwest', "getExtendedDirectionName('SW') returns 'Southwest'");

    // 2. Link::getDisplayName tests
    $dummyWorld = new \Dimx\World('test_links_nav', 'TestLinks');
    $r1 = new \Dimx\Room($dummyWorld, 'Room1', 'r1');
    $r2 = new \Dimx\Room($dummyWorld, 'Room2', 'r2');

    // Link with explicit name
    $namedLink = new \Dimx\Link($dummyWorld, 'Iron Gate', 'gate_1', $r1, $r2, true, 'N');
    assertTest($namedLink->getDisplayName('r1') === 'Iron Gate', "Named link returns explicit name 'Iron Gate'");

    // Link with missing name (defaults to ID)
    $unnamedLink = new \Dimx\Link($dummyWorld, 'w24', 'w24', $r1, $r2, true, 'N');
    assertTest($unnamedLink->getDisplayName('r1') === 'North', "Unnamed link w24 from r1 returns 'North'");
    assertTest($unnamedLink->getDisplayName('r2') === 'South', "Unnamed bidirectional link w24 from r2 returns 'South'");

    // Link with U/D
    $unnamedVertLink = new \Dimx\Link($dummyWorld, 'w25', 'w25', $r1, $r2, true, 'U');
    assertTest($unnamedVertLink->getDisplayName('r1') === 'Up', "Unnamed link w25 from r1 returns 'Up'");
    assertTest($unnamedVertLink->getDisplayName('r2') === 'Down', "Unnamed bidirectional link w25 from r2 returns 'Down'");

    // 3. UIhtml rendering test with world3net.dxw
    $dxwFile = __DIR__ . '/../docs/dxw-examples/world3net.dxw';
    $loader = new \Dimx\DxwLoader('http://localhost');
    $w3NavWorld = $loader->load($dxwFile, 'w3_nav_world');

    // Player in 'spiaggia2' where links w24 and w25 exist without explicit names
    $pNav = new \Dimx\Player($w3NavWorld, 'NavTester', 'p_nav_1');
    $spiaggia2 = $w3NavWorld->getRoom('spiaggia2');
    $pNav->move($spiaggia2);

    ob_start();
    $htmlUiNav = new \Dimx\UIhtml($pNav);
    $htmlUiNav->render([
        'world'     => $w3NavWorld,
        'player'    => $pNav,
        'room'      => $spiaggia2,
        'messages'  => [],
        'lockError' => false,
    ]);
    $navHtml = ob_get_clean();

    // Verify 3x3 table with ID 'navpad' exists
    assertTest(str_contains($navHtml, '<table id="navpad"'), "UIhtml contains table with ID 'navpad'");
    assertTest(!str_contains($navHtml, '] w24'), "UIhtml navigation pad does NOT show link ID 'w24'");
    assertTest(!str_contains($navHtml, '] w25'), "UIhtml navigation pad does NOT show link ID 'w25'");
    assertTest(!str_contains($navHtml, '[N] North'), "UIhtml navpad contains NO text label '[N] North'");
    assertTest(!str_contains($navHtml, '[S] South'), "UIhtml navpad contains NO text label '[S] South'");
    assertTest(str_contains($navHtml, 'skins/default/icowayn.gif'), "UIhtml navpad contains default skin North arrow 'icowayn.gif'");
    assertTest(str_contains($navHtml, 'skins/default/icoways.gif'), "UIhtml navpad contains default skin South arrow 'icoways.gif'");
    assertTest(str_contains($navHtml, 'skins/default/blank.gif'), "UIhtml navpad contains spacer 'blank.gif' in empty cells");
    assertTest(str_contains($navHtml, 'data-obj-name="North"'), "UIhtml exit button has data-obj-name='North'");
    assertTest(str_contains($navHtml, 'data-obj-name="South"'), "UIhtml exit button has data-obj-name='South'");

    // Move player to 'pathgate' where 'gate1' has NAME 'the gate' and direction 'E'
    $pathgate = $w3NavWorld->getRoom('pathgate');
    $pNav->move($pathgate);
    $pNav->facing = 'E';

    ob_start();
    $htmlUiNav->render([
        'world'     => $w3NavWorld,
        'player'    => $pNav,
        'room'      => $pathgate,
        'messages'  => [],
        'lockError' => false,
    ]);
    $gateHtml = ob_get_clean();

    assertTest(str_contains($gateHtml, '<table id="navpad"'), "UIhtml pathgate contains table with ID 'navpad'");
    assertTest(!str_contains($gateHtml, '[E] the gate'), "UIhtml navpad preserves no text '[E] the gate'");
    assertTest(str_contains($gateHtml, 'icoDoor.gif'), "UIhtml navpad contains explicit link ICON 'icoDoor.gif' for gate1");
    assertTest(str_contains($gateHtml, 'data-obj-name="the gate"'), "UIhtml exit button preserves explicit data-obj-name='the gate'");
}

// -------------------------------------------------------------
// Test 26: On-Screen Links (SHOW ONSCREEN, IMAGE, ICON on LINK / MLINK) & Trapdoor (w6) UIhtml Rendering
// -------------------------------------------------------------
if (shouldRun(26)) {
    echo "\n26. Testing On-Screen Links (SHOW ONSCREEN, IMAGE, ICON on LINK / MLINK) & Trapdoor (w6) UIhtml Rendering...\n";

    $dxwFile = __DIR__ . '/../docs/dxw-examples/world3net.dxw';
    $loader = new \Dimx\DxwLoader('http://localhost');
    $w3LinkWorld = $loader->load($dxwFile, 'w3_link_world');

    // 1. Verify link w6 properties loaded from world3net.dxw lines 195-201
    $w6 = $w3LinkWorld->links['w6'] ?? null;
    assertTest($w6 instanceof \Dimx\Link, "world3net defines link 'w6'");
    assertTest($w6->name === 'a trapdoor', "Link w6 name is 'a trapdoor'");
    assertTest($w6->showmode === \Dimx\AdvObject::SHOW_ONSCREEN, "Link w6 has showmode = SHOW_ONSCREEN (1)");
    assertTest($w6->showX === 200, "Link w6 showX is 200");
    assertTest($w6->showY === 5, "Link w6 showY is 5");
    assertTest(str_contains($w6->icon ?? '', 'trapdoor.gif'), "Link w6 icon resolved to trapdoor.gif: '{$w6->icon}'");
    assertTest(isset($w6->images['N']), "Link w6 defines image for facing 'N'");
    assertTest($w6->images['N']->width === 41, "Link w6 image width is 41");
    assertTest($w6->images['N']->height === 30, "Link w6 image height is 30");
    assertTest(str_contains($w6->images['N']->src, 'td.gif'), "Link w6 image src contains 'td.gif': '{$w6->images['N']->src}'");
    assertTest(!$w6->isOpen(), "Link w6 is initially closed (open=0,locked=1)");
    assertTest($w6->isLocked(), "Link w6 is locked (locked=1)");

    // 2. Verify UIhtml rendering for player inside 'bar' room
    $barRoom = $w3LinkWorld->getRoom('bar');
    assertTest($barRoom !== null, "World 3 defines 'bar' room");
    $barWays = $barRoom->getLinks();
    assertTest(in_array($w6, $barWays, true), "Bar room ways list includes trapdoor w6");

    $pBar = new \Dimx\Player($w3LinkWorld, 'BarFly', 'p_bar_1');
    $pBar->move($barRoom);

    ob_start();
    $htmlUiBar = new \Dimx\UIhtml($pBar);
    $htmlUiBar->render([
        'world'     => $w3LinkWorld,
        'player'    => $pBar,
        'room'      => $barRoom,
        'messages'  => [],
        'lockError' => false,
    ]);
    $barSceneHtml = ob_get_clean();

    // Verify on-screen trapdoor image is rendered in scene container
    assertTest(
        str_contains($barSceneHtml, 'data-obj-id="w6"'),
        "UIhtml scene container renders onscreen trapdoor with data-obj-id='w6'"
    );
    assertTest(
        str_contains($barSceneHtml, 'data-obj-type="w"'),
        "UIhtml scene container renders onscreen trapdoor with data-obj-type='w'"
    );
    assertTest(
        str_contains($barSceneHtml, 'data-obj-name="a trapdoor"'),
        "UIhtml scene container renders onscreen trapdoor with data-obj-name='a trapdoor'"
    );
    assertTest(
        str_contains($barSceneHtml, 'td.gif'),
        "UIhtml scene container renders trapdoor image with src containing 'td.gif'"
    );
    assertTest(
        preg_match('/<img[^>]*class="[^"]*dimx-onscreen-obj[^"]*"[^>]*data-obj-id="w6"[^>]*style="[^"]*position:\s*absolute/i', $barSceneHtml) === 1,
        "UIhtml renders w6 trapdoor as an absolute-positioned on-screen object"
    );

    // Verify navigation pad includes trapdoor exit with custom icon trapdoor.gif
    assertTest(
        str_contains($barSceneHtml, 'trapdoor.gif'),
        "UIhtml navigation pad includes w6 exit with 'trapdoor.gif'"
    );

    // 3. Attempt to traverse closed trapdoor w6
    $barEngine = new \Dimx\GameEngine($pm, 'w3_link_trav', $dxwFile);

    // 3a. By link ID: 'go w6'
    $barEngine->executeCommandAction($w3LinkWorld, $pBar, 'go', 'w6', '');
    $goW6Msgs = implode(' ', $pBar->drainConsole());
    assertTest(
        str_contains($goW6Msgs, 'a trapdoor is closed.'),
        "Traversing closed link by ID ('go w6') displays 'a trapdoor is closed.': '{$goW6Msgs}'"
    );
    assertTest(
        !str_contains($goW6Msgs, 'You cannot go that way'),
        "Traversing closed link by ID does NOT display generic 'You cannot go that way'"
    );

    // 3b. By direction: 'go D'
    $barEngine->executeCommandAction($w3LinkWorld, $pBar, 'go', 'D', '');
    $goDMsgs = implode(' ', $pBar->drainConsole());
    assertTest(
        str_contains($goDMsgs, 'a trapdoor is closed.'),
        "Traversing closed link by direction ('go D') displays 'a trapdoor is closed.': '{$goDMsgs}'"
    );
    assertTest(
        !str_contains($goDMsgs, 'You cannot go that way'),
        "Traversing closed link by direction does NOT display generic 'You cannot go that way'"
    );

    // 4. Test opening locked link w6 (a trapdoor):
    // Trying to open locked object should NOT say "You cannot reach that." but simply "It's locked"
    $barEngine->executeCommandAction($w3LinkWorld, $pBar, 'open', '', 'w6');
    $openW6Msgs = implode(' ', $pBar->drainConsole());
    assertTest(
        !str_contains($openW6Msgs, 'You cannot reach that'),
        "Opening link w6 does NOT say 'You cannot reach that': '{$openW6Msgs}'"
    );
    assertTest(
        str_contains($openW6Msgs, "It's locked"),
        "Opening locked link w6 displays simply \"It's locked\": '{$openW6Msgs}'"
    );
    assertTest(
        !str_contains($openW6Msgs, 'is locked.'),
        "Opening locked link w6 does NOT use old format '... is locked.'"
    );
}

// -------------------------------------------------------------
// Test 27: Multi-line Double-Quoted Strings & Barman Dialogue Cycle (world3net)
// -------------------------------------------------------------
if (shouldRun(27)) {
    echo "\n27. Testing Multi-line Double-Quoted Strings & Barman Dialogue Cycle (world3net)...\n";

    $dxwFile = __DIR__ . '/../docs/dxw-examples/world3net.dxw';
    $loader = new \Dimx\DxwLoader('http://localhost');
    $w3 = $loader->load($dxwFile, 'w3_multiline_world');

    // 1. Verify barman.onHear event contains the full multi-line Speak instruction
    $onHear = $w3->events['barman.onHear'] ?? null;
    assertTest($onHear instanceof \Dimx\Event, "world3net registers 'barman.onHear' event");
    assertTest(
        str_contains($onHear->actions, "What did you say? What should I give you?"),
        "barman.onHear contains phrase 1: 'What did you say? What should I give you?'"
    );
    assertTest(
        str_contains($onHear->actions, "Oh shit why don't they turn this damned volume down now!"),
        "barman.onHear contains multi-line phrase 2 with apostrophe preserved: 'Oh shit why don\'t they...'"
    );
    assertTest(
        str_contains($onHear->actions, "Wha-a-at?"),
        "barman.onHear contains phrase 3: 'Wha-a-at?'"
    );
    assertTest(
        str_contains($onHear->actions, '"*"'),
        "barman.onHear contains phrase 4: '*'"
    );

    // 2. Test Player interacting with Barman in the bar
    $bar = $w3->getRoom('bar');
    $barman = $w3->getCharacter('barman');
    $lattina = $w3->contents['lattina'] ?? null;
    assertTest($lattina instanceof \Dimx\Item, "World 3 defines item 'lattina'");
    assertTest($lattina->container === $barman, "lattina is initially held by barman");

    $pSpeak = new \Dimx\Player($w3, 'ThirstyTraveler', 'p_thirsty_1');
    $pSpeak->move($bar);

    $barEngine = new \Dimx\GameEngine($pm, 'w3_multiline', $dxwFile);

    // Speak 1: Barman speaks phrase 1
    $w3->chatQueue = [];
    $barEngine->executeCommandAction($w3, $pSpeak, 'say', 'hello', 'barman');
    $lastChat = end($w3->chatQueue);
    assertTest(
        $lastChat !== false && $lastChat['sender'] === 'the barman' && str_contains($lastChat['text'], "What did you say? What should I give you?"),
        "Barman speak 1 cycles to phrase 1 ('What did you say? What should I give you?'): '{$lastChat['text']}'"
    );

    // Speak 2: Barman speaks multi-line phrase 2
    $w3->chatQueue = [];
    $barEngine->executeCommandAction($w3, $pSpeak, 'say', 'hello', 'barman');
    $lastChat = end($w3->chatQueue);
    assertTest(
        $lastChat !== false && $lastChat['sender'] === 'the barman' && str_contains($lastChat['text'], "Oh shit why don't they turn this damned volume down now!"),
        "Barman speak 2 cycles to multi-line phrase 2: '{$lastChat['text']}'"
    );

    // Speak 3: Barman speaks phrase 3
    $w3->chatQueue = [];
    $barEngine->executeCommandAction($w3, $pSpeak, 'say', 'hello', 'barman');
    $lastChat = end($w3->chatQueue);
    assertTest(
        $lastChat !== false && $lastChat['sender'] === 'the barman' && str_contains($lastChat['text'], "Wha-a-at?"),
        "Barman speak 3 cycles to phrase 3 ('Wha-a-at?'): '{$lastChat['text']}'"
    );

    // Speak 4: Sequence reaches '*', triggers barman.onSpeechFinish, gives lattina to player
    $w3->chatQueue = [];
    $barEngine->executeCommandAction($w3, $pSpeak, 'say', 'hello', 'barman');
    $lastChat = end($w3->chatQueue);
    assertTest(
        $lastChat !== false && $lastChat['sender'] === 'the barman' && str_contains($lastChat['text'], "Wanna drink huh? Take this!"),
        "Barman speak 4 triggers onSpeechFinish ('Wanna drink huh? Take this!'): '{$lastChat['text']}'"
    );
    assertTest(
        $lattina->container === $pSpeak,
        "Lattina was moved into player's inventory by onSpeechFinish"
    );
}

// -------------------------------------------------------------
// Test 28: use2 ("Use With") Command, Default Panel Button & Trapdoor Unlocking (key1 on w6)
// -------------------------------------------------------------
if (shouldRun(28)) {
    echo "\n28. Testing use2 (\"Use With\") Command, Default Panel Button & Trapdoor Unlocking (key1 on w6)...\n";

    // 1. Verify default panel includes use2 button
    $defPanel = \Dimx\Panel::createDefaultPanel();
    $use2Btn = $defPanel->buttons['use2'] ?? null;
    assertTest($use2Btn instanceof \Dimx\Ctrl, "Default panel contains 'use2' button");
    assertTest($use2Btn->type === \Dimx\Ctrl::TYPE_BUTTON, "'use2' is of type button");
    assertTest($use2Btn->caption === 'Use with', "'use2' button label is 'Use with'");
    assertTest($use2Btn->eventModel === 'OO', "'use2' button model is 'OO' (two objects)");
    assertTest($use2Btn->event === 'onUseWith', "'use2' button target event is 'onUseWith'");

    $cmds = $defPanel->getAvailableCommands();
    assertTest(in_array('use2', $cmds, true), "getAvailableCommands includes 'use2'");
    assertTest(in_array('usewith', $cmds, true), "getAvailableCommands includes alias 'usewith'");
    assertTest($defPanel->isCommandAllowed('use2'), "isCommandAllowed('use2') is true");
    assertTest($defPanel->isCommandAllowed('usewith'), "isCommandAllowed('usewith') is true");

    // 2. Verify UIhtml renders the use2 button in panel buttons container
    $dxwFile = __DIR__ . '/../docs/dxw-examples/world3net.dxw';
    $loader = new \Dimx\DxwLoader('http://localhost');
    $w3Use = $loader->load($dxwFile, 'w3_use2_world');

    $bar = $w3Use->getRoom('bar');
    $pKey = new \Dimx\Player($w3Use, 'KeyMaster', 'p_key_1');
    $pKey->move($bar);
    $pKey->setPanel($defPanel);

    ob_start();
    $htmlUi = new \Dimx\UIhtml($pKey);
    $htmlUi->render([
        'world'     => $w3Use,
        'player'    => $pKey,
        'room'      => $bar,
        'messages'  => [],
        'lockError' => false,
    ]);
    $htmlOutput = ob_get_clean();

    assertTest(
        str_contains($htmlOutput, 'data-cmd="use2"') || str_contains($htmlOutput, 'data-id="use2"'),
        "UIhtml panel buttons container renders 'use2' button"
    );
    assertTest(
        str_contains($htmlOutput, 'Use with'),
        "UIhtml panel renders 'Use with' label on command button"
    );
    assertTest(
        str_contains($htmlOutput, "'use2':") && str_contains($htmlOutput, "noArgs: 2"),
        "UIhtml COMMAND_DEFS includes 'use2' with noArgs: 2"
    );

    // 3. Verify UIjson / UIbot exposes use2 in available commands
    $jsonUi = new \Dimx\UIjson($pKey);
    ob_start();
    $jsonUi->render([
        'world'      => $w3Use,
        'player'     => $pKey,
        'room'       => $bar,
        'panel'      => $defPanel,
        'messages'   => [],
        'loggedOut'  => false,
        'lockError'  => false,
        'status'     => 'ok',
    ]);
    $jsonStr = ob_get_clean();
    $payload = json_decode($jsonStr, true);
    assertTest(in_array('use2', $payload['panel']['commands'] ?? []), "UIjson payload lists 'use2' in available commands");

    // 4. Test GameEngine executing use2 (key1 on w6):
    $useEngine = new \Dimx\GameEngine($pm, 'w3_use2', $dxwFile);
    $w6 = $bar->getWay('w6');
    assertTest($w6 instanceof \Dimx\Link, "Bar contains Link 'w6'");
    assertTest($w6->isLocked(), "Link w6 is initially locked");

    // Before unlock: open w6 displays 'It's locked'
    $useEngine->executeCommandAction($w3Use, $pKey, 'open', '', 'w6');
    $openLockedMsgs = implode(' ', $pKey->drainConsole());
    assertTest(str_contains($openLockedMsgs, "It's locked"), "Opening locked w6 returns \"It's locked\": '{$openLockedMsgs}'");

    // Give key1 to player
    $key1 = $w3Use->contents['key1'] ?? null;
    assertTest($key1 instanceof \Dimx\Item, "World 3 defines item 'key1'");
    $key1->move($pKey);
    assertTest(isset($pKey->contents['key1']), "Player is carrying 'key1'");

    // Execute use2 key1 on w6
    $useEngine->executeCommandAction($w3Use, $pKey, 'use2', 'key1', 'w6');
    $use2Msgs = implode(' ', $pKey->drainConsole());
    assertTest(str_contains($use2Msgs, "Done!"), "Using key1 on w6 outputs 'Done!': '{$use2Msgs}'");
    assertTest(!$w6->isLocked(), "Link w6 is now UNLOCKED after key1.onUseWith (locked=0)");

    // Now open w6 succeeds!
    $useEngine->executeCommandAction($w3Use, $pKey, 'open', '', 'w6');
    $openUnlockedMsgs = implode(' ', $pKey->drainConsole());
    assertTest(str_contains($openUnlockedMsgs, "You opened"), "Opening unlocked w6 succeeds with 'You opened': '{$openUnlockedMsgs}'");
    assertTest($w6->isOpen(), "Link w6 is now OPEN");

    // Now traversal via go w6 / go D succeeds!
    $useEngine->executeCommandAction($w3Use, $pKey, 'go', 'w6', '');
    assertTest($pKey->getRoom()->id === 'cellar', "Traversing open w6 moved player into 'cellar'");
}

// ---------------------------------------------------------------------------
// Section 29: Testing Hidden Objects (hidden=1) in UIhtml, UIbot, and UIjson
// ---------------------------------------------------------------------------
if (shouldRun(29)) {
    echo "\n--- Section 29: Testing Hidden Objects (hidden=1) in UIhtml, UIbot, and UIjson (world3net treasure) ---\n";

    $dxwLoader = new \Dimx\DxwLoader();
    $w3Hidden = $dxwLoader->load(__DIR__ . '/../docs/dxw-examples/world3net.dxw');

    // 1. Verify 'treasure' item definition in beachcliff
    $treasure = $w3Hidden->contents['treasure'] ?? null;
    assertTest($treasure instanceof \Dimx\Item, "World 3 defines item 'treasure'");
    assertTest($treasure->isHidden() === true, "Item 'treasure' has isHidden() === true due to 'hidden' in ATTRLIST");
    assertTest($treasure->showmode === \Dimx\AdvObject::SHOW_ONSCREEN, "Item 'treasure' has SHOW ONSCREEN");
    assertTest($treasure->container instanceof \Dimx\Room && $treasure->container->id === 'beachcliff', "Item 'treasure' is positioned in 'beachcliff'");

    // 2. Setup player in beachcliff
    $beachcliff = $w3Hidden->contents['beachcliff'] ?? null;
    assertTest($beachcliff instanceof \Dimx\Room, "World 3 contains room 'beachcliff'");
    $pObserver = new \Dimx\Player($w3Hidden, 'Observer', 'observer');
    $pObserver->move($beachcliff);
    assertTest($pObserver->getRoom()->id === 'beachcliff', "Observer is inside 'beachcliff'");

    // 3. Test UIhtml rendering: hidden item must NOT appear on scene or in sidebar
    $uiHtml = new \Dimx\UIhtml($pObserver, null);
    ob_start();
    $uiHtml->render([
        'world'    => $w3Hidden,
        'player'   => $pObserver,
        'room'     => $beachcliff,
        'messages' => [],
    ]);
    $htmlOutput = ob_get_clean();

    assertTest(!str_contains($htmlOutput, 'data-obj-id="treasure"'), "UIhtml does NOT render data-obj-id=\"treasure\" on scene or in items list");
    assertTest(!str_contains($htmlOutput, 'treasure chest'), "UIhtml does NOT render item name 'treasure chest'");

    // 4. Test UIbot snapshot: hidden item must NOT appear in items
    $uiBotHidden = new \Dimx\UIbot($pObserver, null);
    $botSnapshot = $uiBotHidden->buildRoomSnapshot($beachcliff, $pObserver);
    $botItemIds = array_column($botSnapshot['items'] ?? [], 'id');
    assertTest(!in_array('treasure', $botItemIds, true), "UIbot room snapshot does NOT include hidden item 'treasure'");

    // 5. Test UIjson snapshot: hidden item IS included for testing/diagnostics
    $uiJsonHidden = new \Dimx\UIjson($pObserver, null);
    $jsonSnapshot = $uiJsonHidden->buildRoomSnapshot($beachcliff, $pObserver);
    $jsonItemIds = array_column($jsonSnapshot['items'] ?? [], 'id');
    assertTest(in_array('treasure', $jsonItemIds, true), "UIjson room snapshot retains hidden item 'treasure' for testing/diagnostics");

    // 6. Test unhiding treasure: becomes visible in UIhtml and UIbot
    $treasure->hidden = false;
    $treasure->setProperty('hidden', false);
    assertTest($treasure->isHidden() === false, "'treasure' is now unhidden ($treasure->isHidden() === false)");

    ob_start();
    $uiHtml->render([
        'world'    => $w3Hidden,
        'player'   => $pObserver,
        'room'     => $beachcliff,
        'messages' => [],
    ]);
    $htmlOutputUnhidden = ob_get_clean();
    assertTest(str_contains($htmlOutputUnhidden, 'data-obj-id="treasure"'), "UIhtml renders unhidden 'treasure' on scene");
    assertTest(str_contains($htmlOutputUnhidden, 'treasure chest'), "UIhtml renders unhidden item name 'treasure chest'");

    $botSnapshotUnhidden = $uiBotHidden->buildRoomSnapshot($beachcliff, $pObserver);
    $botItemIdsUnhidden = array_column($botSnapshotUnhidden['items'] ?? [], 'id');
    assertTest(in_array('treasure', $botItemIdsUnhidden, true), "UIbot room snapshot includes unhidden item 'treasure'");
}

// ---------------------------------------------------------------------------
// Section 30: Testing Display & Call Instructions, Keyed Image Assignment & switch1.onUse (world3net)
// ---------------------------------------------------------------------------
if (shouldRun(30)) {
    echo "\n--- Section 30: Testing Display & Call Instructions, Keyed Image Assignment & switch1.onUse (world3net) ---\n";

    $dxwLoader = new \Dimx\DxwLoader();
    $w3Switch = $dxwLoader->load(__DIR__ . '/../docs/dxw-examples/world3net.dxw', 'w3_switch_world');

    // 1. Objects exist and initial states
    $kitchen = $w3Switch->getRoom('kitchen');
    assertTest($kitchen instanceof \Dimx\Room, "world3net defines 'kitchen' room");
    $switch1 = $w3Switch->contents['switch1'] ?? null;
    assertTest($switch1 instanceof \Dimx\Item, "world3net defines item 'switch1'");
    assertTest($switch1->container->id === 'kitchen', "'switch1' is positioned inside 'kitchen'");

    $gate1 = $w3Switch->links['gate1'] ?? null;
    assertTest($gate1 instanceof \Dimx\Link, "world3net defines link 'gate1'");
    assertTest(!$gate1->isOpen(), "'gate1' is initially closed");
    assertTest($gate1->isLocked(), "'gate1' is initially locked");

    $pathgate = $w3Switch->getRoom('pathgate');
    assertTest($pathgate instanceof \Dimx\Room, "world3net defines 'pathgate' room");
    $initialPathgateImageE = $pathgate->images['E']->src ?? null;
    assertTest($initialPathgateImageE !== null && str_contains($initialPathgateImageE, 'pathgatee.jpg'), "'pathgate' has initial 'E' image defined (pathgatee.jpg)");

    // 2. Setup player in kitchen
    $pSwitch = new \Dimx\Player($w3Switch, 'Electrician', 'p_switch_1');
    $pSwitch->move($kitchen);
    assertTest($pSwitch->getRoom()->id === 'kitchen', "Electrician is inside 'kitchen'");

    // 3. Execute 'use switch1'
    $switchEngine = new \Dimx\GameEngine($pm, 'w3_switch_test', __DIR__ . '/../docs/dxw-examples/world3net.dxw');
    $switchEngine->executeCommandAction($w3Switch, $pSwitch, 'use', 'switch1', '');

    $consoleMsgs = $pSwitch->drainConsole();
    $combinedMsgs = implode(' ', $consoleMsgs);

    // Verify Display instruction executed and output message
    assertTest(str_contains($combinedMsgs, "Hmmm.. the light doesn't turn on. What is this for then?!?"), "Player console contains Display output: '{$combinedMsgs}'");
    assertTest(str_contains($combinedMsgs, "Done!"), "Player console contains 'Done!' confirmation");

    // Verify switch1.onUse opened and unlocked gate1
    assertTest($gate1->isOpen(), "'gate1' is now open (gate1.open = 1)");
    assertTest(!$gate1->isLocked(), "'gate1' is now unlocked (gate1.locked = 0)");

    // Verify Call gate1.onOpen executed: pathgate.image(\"E\") = \"pathgateeo.jpg\"
    $updatedPathgateImageE = $pathgate->images['E']->src ?? null;
    assertTest($updatedPathgateImageE !== null && str_contains($updatedPathgateImageE, 'pathgateeo.jpg'), "Call gate1.onOpen set pathgate.image('E') to pathgateeo.jpg: '{$updatedPathgateImageE}'");
}

// ---------------------------------------------------------------------------
// Section 31: Testing Server Setting tickLength & Clock Tick Management
// ---------------------------------------------------------------------------
if (shouldRun(31)) {
    echo "\n--- Section 31: Testing Server Setting tickLength & Clock Tick Management (world3net switch1 & onTick) ---\n";

    // 1. Check config.php setting
    $configFile = __DIR__ . '/config.php';
    assertTest(file_exists($configFile), "config.php exists");
    $mainConfig = require $configFile;
    assertTest(isset($mainConfig['tickLength']), "config.php defines 'tickLength'");
    assertTest($mainConfig['tickLength'] === 30, "config.php 'tickLength' defaults to 30: " . var_export($mainConfig['tickLength'], true));
    assertTest(isset($mainConfig['msgRefreshRate']), "config.php defines 'msgRefreshRate'");
    assertTest($mainConfig['msgRefreshRate'] === 5, "config.php 'msgRefreshRate' defaults to 5: " . var_export($mainConfig['msgRefreshRate'], true));
    assertTest(isset($mainConfig['serverType']), "config.php defines 'serverType'");
    assertTest($mainConfig['serverType'] === 'public', "config.php 'serverType' defaults to 'public': " . var_export($mainConfig['serverType'], true));
    assertTest(isset($mainConfig['debugTo']), "config.php defines 'debugTo'");
    assertTest($mainConfig['debugTo'] === 'none', "config.php 'debugTo' defaults to 'none': " . var_export($mainConfig['debugTo'], true));

    // 2. Check config.sample.php setting
    $sampleConfigFile = __DIR__ . '/config.sample.php';
    assertTest(file_exists($sampleConfigFile), "config.sample.php exists");
    $sampleConfig = require $sampleConfigFile;
    assertTest(isset($sampleConfig['tickLength']), "config.sample.php defines 'tickLength'");
    assertTest($sampleConfig['tickLength'] === 30, "config.sample.php 'tickLength' defaults to 30: " . var_export($sampleConfig['tickLength'], true));
    assertTest(isset($sampleConfig['msgRefreshRate']), "config.sample.php defines 'msgRefreshRate'");
    assertTest($sampleConfig['msgRefreshRate'] === 5, "config.sample.php 'msgRefreshRate' defaults to 5: " . var_export($sampleConfig['msgRefreshRate'], true));
    assertTest(isset($sampleConfig['serverType']), "config.sample.php defines 'serverType'");
    assertTest($sampleConfig['serverType'] === 'public', "config.sample.php 'serverType' defaults to 'public': " . var_export($sampleConfig['serverType'], true));
    assertTest(isset($sampleConfig['debugTo']), "config.sample.php defines 'debugTo'");
    assertTest($sampleConfig['debugTo'] === 'none', "config.sample.php 'debugTo' defaults to 'none': " . var_export($sampleConfig['debugTo'], true));

    // 2c. Test GameEngine::$serverType & DxwLoader IMAGESFOLDER_PUBLIC vs IMAGESFOLDER_LOCAL
    $engineDefaultType = new \Dimx\GameEngine($pm, 'servertype_default', null, '', []);
    assertTest($engineDefaultType->serverType === 'public', "GameEngine::\$serverType defaults to 'public'");
    assertTest($engineDefaultType->debugTo === 'none', "GameEngine::\$debugTo defaults to 'none'");

    $engineLocalType = new \Dimx\GameEngine($pm, 'servertype_local', null, '', ['serverType' => 'local', 'debugTo' => 'file']);
    assertTest($engineLocalType->serverType === 'local', "GameEngine::\$serverType maps to 'local' when configured");
    assertTest($engineLocalType->debugTo === 'file', "GameEngine::\$debugTo maps to 'file' when configured");

    $dxwDualFolder = <<<'DXW'
WORLD
    NAME Dual Folder World
    IMAGESFOLDER_LOCAL https://localhost:8080/dimx/pics/
    IMAGESFOLDER_PUBLIC https://www.dimensionex.net/pics/
ROOMS
    ROOM r1
        NAME Room 1
        IMAGE test.jpg
END_ROOMS
END_WORLD
DXW;
    $loaderPublic = new \Dimx\DxwLoader('', 'public');
    $wPublic = $loaderPublic->parse($dxwDualFolder, 'dual_pub.dxw');
    assertTest($wPublic->imagesFolder === 'https://www.dimensionex.net/pics/', "DxwLoader with serverType='public' selects IMAGESFOLDER_PUBLIC ('{$wPublic->imagesFolder}')");
    assertTest(($wPublic->getRoom('r1')?->images['N']?->src ?? '') === 'https://www.dimensionex.net/pics/test.jpg', "Room image resolved against IMAGESFOLDER_PUBLIC when serverType='public'");
    assertTest($wPublic->getProperty('imagesFolderPublic') === null && $wPublic->getProperty('imagesFolderLocal') === null, "World object does NOT store imagesFolderPublic or imagesFolderLocal properties");

    $loaderLocal = new \Dimx\DxwLoader('', 'local');
    $wLocal = $loaderLocal->parse($dxwDualFolder, 'dual_loc.dxw');
    assertTest($wLocal->imagesFolder === 'https://localhost:8080/dimx/pics/', "DxwLoader with serverType='local' selects IMAGESFOLDER_LOCAL ('{$wLocal->imagesFolder}')");
    assertTest(($wLocal->getRoom('r1')?->images['N']?->src ?? '') === 'https://localhost:8080/dimx/pics/test.jpg', "Room image resolved against IMAGESFOLDER_LOCAL when serverType='local'");
    assertTest($wLocal->getProperty('imagesFolderPublic') === null && $wLocal->getProperty('imagesFolderLocal') === null, "World object (local) does NOT store imagesFolderPublic or imagesFolderLocal properties");

    // 2b. Test UIhtml refreshes #msgs every msgRefreshRate seconds (default 5s -> 5000ms, custom 8s -> 8000ms)
    $msgRateWorld = new \Dimx\World('msg_rate_world', 'Msg Rate World');
    $msgRateRoom  = new \Dimx\Room($msgRateWorld, 'Lobby', 'lobby', 'Lobby room.');
    $msgRateWorld->addRoom($msgRateRoom);
    $msgRatePlayer = new \Dimx\Player($msgRateWorld, 'RateTester', 'p_rate_tester');
    $msgRatePlayer->move($msgRateRoom);

    $uiDefaultRate = new \Dimx\UIhtml($msgRatePlayer);
    ob_start();
    $uiDefaultRate->render(['world' => $msgRateWorld, 'player' => $msgRatePlayer, 'room' => $msgRateRoom]);
    $defaultRateHtml = ob_get_clean();
    assertTest(str_contains($defaultRateHtml, 'const MSG_REFRESH_RATE = 5;'), "UIhtml sets MSG_REFRESH_RATE = 5 by default");
    assertTest(str_contains($defaultRateHtml, 'setInterval(pollChat, 5000);'), "UIhtml refreshes #msgs every 5000ms (5 seconds) by default");

    $customRateEngine = new \Dimx\GameEngine($pm, 'msg_rate_custom', null, '', ['msgRefreshRate' => 8]);
    $uiCustomRate = new \Dimx\UIhtml($msgRatePlayer, $customRateEngine);
    ob_start();
    $uiCustomRate->render(['world' => $msgRateWorld, 'player' => $msgRatePlayer, 'room' => $msgRateRoom]);
    $customRateHtml = ob_get_clean();
    assertTest(str_contains($customRateHtml, 'const MSG_REFRESH_RATE = 8;'), "UIhtml sets MSG_REFRESH_RATE = 8 when configured in GameEngine config");
    assertTest(str_contains($customRateHtml, 'setInterval(pollChat, 8000);'), "UIhtml refreshes #msgs every 8000ms (8 seconds) when msgRefreshRate = 8");

    // 3. Test GameEngine applies tickLength from config to World
    $customEngine = new \Dimx\GameEngine($pm, 'tick_config_test', null, '', ['tickLength' => 45]);
    $seededWorld = $customEngine->loadOrSeedWorld();
    assertTest($seededWorld->tickLength === 45, "GameEngine applied config tickLength (45) to seeded world: {$seededWorld->tickLength}");

    // 4. Test World::tick() timing logic
    $tickTestWorld = new \Dimx\World('test_tick_world', 'Tick Test World');
    $tickTestWorld->tickLength = 30;
    $tickState = new \stdClass();
    $tickState->count = 0;
    $tickTestWorld->events['onTick'] = new class($tickState) extends \Dimx\Event {
        private \stdClass $state;
        public function __construct(\stdClass $s) { parent::__construct('onTick', 'onTick', null); $this->state = $s; }
        public function fire(?\Dimx\DimxObject $owner = null, mixed $agent = null, mixed $target = null, array $input = [], mixed $defaultResult = true, ?\Dimx\World $worldFallback = null): mixed {
            $this->state->count++;
            return true;
        }
    };

    // First call when lastTick is 0 initializes lastTick without firing onTick
    $tickTestWorld->lastTick = 0;
    $rInit = $tickTestWorld->tick();
    assertTest($rInit === false, "World::tick() when lastTick was 0 returns false (initializes timestamp)");
    assertTest($tickTestWorld->lastTick > 0, "World::tick() initialized lastTick timestamp: {$tickTestWorld->lastTick}");
    assertTest($tickState->count === 0, "onTick was NOT triggered during initial tick");

    // Immediate second call (< 30s elapsed) must be skipped
    $rSkipped = $tickTestWorld->tick();
    assertTest($rSkipped === false, "World::tick() called immediately (< 30s elapsed) returns false");
    assertTest($tickState->count === 0, "onTick was NOT triggered on immediate call");

    // Calling with force = true bypasses interval
    $rForced = $tickTestWorld->tick(force: true);
    assertTest($rForced === true, "World::tick(force: true) returns true");
    assertTest($tickState->count === 1, "onTick was triggered when force = true (count: {$tickState->count})");

    // Advance time by setting lastTick to 35 seconds ago
    $tickTestWorld->lastTick = time() - 35;
    $rElapsed = $tickTestWorld->tick();
    assertTest($rElapsed === true, "World::tick() returns true when time elapsed (35s) >= tickLength (30s)");
    assertTest($tickState->count === 2, "onTick was triggered after tickLength elapsed (count: {$tickState->count})");
    assertTest((time() - $tickTestWorld->lastTick) <= 1, "lastTick timestamp was updated to current time");

    // 5. Test tick interval behavior in world3net (switch1 & gate1)
    $dxwLoader = new \Dimx\DxwLoader('http://localhost');
    $w3Tick = $dxwLoader->load(__DIR__ . '/../docs/dxw-examples/world3net.dxw');
    $w3Tick->tickLength = 30;
    $w3Tick->lastTick = time();

    $kitchenRoom = $w3Tick->contents['kitchen'] ?? null;
    $gate1Link   = $w3Tick->contents['gate1'] ?? null;
    $switchItem  = $w3Tick->contents['switch1'] ?? null;
    $pTickTester = new \Dimx\Player($w3Tick, 'TickTester', 'p_tick_tester');
    $pTickTester->move($kitchenRoom);

    // Initial state: gate1 is closed and locked
    assertTest(!$gate1Link->isOpen(), "Initial gate1 is closed");
    assertTest($gate1Link->isLocked(), "Initial gate1 is locked");

    // Use switch1 -> opens gate1
    $w3TickEngine = new \Dimx\GameEngine($pm, 'w3_tick_engine', __DIR__ . '/../docs/dxw-examples/world3net.dxw');
    $w3TickEngine->executeCommandAction($w3Tick, $pTickTester, 'use', 'switch1', '');
    assertTest($gate1Link->isOpen(), "After switch1.onUse, gate1 is open");
    assertTest(!$gate1Link->isLocked(), "After switch1.onUse, gate1 is unlocked");

    // Simulate an immediate next command / player action (< 30s elapsed)
    $tickExecuted = $w3Tick->tick();
    assertTest($tickExecuted === false, "Immediate next player action does NOT trigger world clock tick (tick returned false)");
    assertTest($gate1Link->isOpen(), "gate1 remains OPEN because clock tick did not fire yet");
    assertTest(!$gate1Link->isLocked(), "gate1 remains UNLOCKED because clock tick did not fire yet");

    // Simulate 30 seconds passing
    $w3Tick->lastTick = time() - 30;
    $tickExecutedAfterWait = $w3Tick->tick();
    assertTest($tickExecutedAfterWait === true, "Clock tick triggered after 30 seconds (tick returned true)");
    assertTest(!$gate1Link->isOpen(), "onTick closed gate1 after tickLength elapsed (gate1.open = 0)");
    assertTest($gate1Link->isLocked(), "onTick locked gate1 after tickLength elapsed (gate1.locked = 1)");
}

// ---------------------------------------------------------------------------
// Section 32: Testing Search Command, onSearch Event & Revealing Hidden Objects
// ---------------------------------------------------------------------------
if (shouldRun(32)) {
    echo "\n--- Section 32: Testing Search Command, onSearch Event & Revealing Hidden Objects (world3net) ---\n";

    $dxwLoader = new \Dimx\DxwLoader('http://localhost');
    $w3Search = $dxwLoader->load(__DIR__ . '/../docs/dxw-examples/world3net.dxw');
    $searchEngine = new \Dimx\GameEngine($pm, 'w3_search_test', __DIR__ . '/../docs/dxw-examples/world3net.dxw');

    // 1. Verify default panel allows 'search'
    $defPanel = $w3Search->defaultPanel;
    assertTest($defPanel !== null, "World 3 has default panel");
    assertTest($defPanel->isCommandAllowed('search'), "Default panel allows 'search' command");

    // 2. Test searching in a room with NO hidden objects (e.g. 'bar')
    $barRoom = $w3Search->contents['bar'] ?? null;
    assertTest($barRoom instanceof \Dimx\Room, "World 3 contains room 'bar'");
    $pSearcher = new \Dimx\Player($w3Search, 'Searcher', 'p_searcher');
    $pSearcher->move($barRoom);

    $searchEngine->executeCommandAction($w3Search, $pSearcher, 'search', '', '');
    $barMsgs = $pSearcher->drainConsole();
    $barOutput = implode(' ', $barMsgs);
    assertTest(str_contains($barOutput, "I found nothing new."), "Searching room with no hidden objects displays 'I found nothing new.': '{$barOutput}'");

    // 3. Test revealing hidden object without onSearch restriction: 'forbici' in 'parcoLunetta'
    $parcoRoom = $w3Search->contents['parcoLunetta'] ?? null;
    $forbiciItem = $w3Search->contents['forbici'] ?? null;
    assertTest($parcoRoom instanceof \Dimx\Room, "World 3 contains room 'parcoLunetta'");
    assertTest($forbiciItem instanceof \Dimx\Item, "World 3 defines item 'forbici'");
    assertTest($forbiciItem->isHidden(), "Initial 'forbici' isHidden() === true");

    $pSearcher->move($parcoRoom);
    $searchEngine->executeCommandAction($w3Search, $pSearcher, 'search', '', '');
    $parcoMsgs = $pSearcher->drainConsole();
    $parcoOutput = implode(' ', $parcoMsgs);

    assertTest(str_contains($parcoOutput, "I found scissors!!"), "Searching parcoLunetta reveals scissors ('I found scissors!!'): '{$parcoOutput}'");
    assertTest(!$forbiciItem->isHidden(), "'forbici' isHidden() is now false after search");

    // Searching parcoLunetta again produces 'I found nothing new.'
    $searchEngine->executeCommandAction($w3Search, $pSearcher, 'search', '', '');
    $parcoMsgs2 = $pSearcher->drainConsole();
    $parcoOutput2 = implode(' ', $parcoMsgs2);
    assertTest(str_contains($parcoOutput2, "I found nothing new."), "Repeated search in parcoLunetta outputs 'I found nothing new.': '{$parcoOutput2}'");

    // 4. Test onSearch conditional event: 'treasure' in 'beachcliff'
    // treasure.onSearch: If (Not($AGENT.foundmap)) Return false
    $beachcliffRoom = $w3Search->contents['beachcliff'] ?? null;
    $treasureItem = $w3Search->contents['treasure'] ?? null;
    assertTest($beachcliffRoom instanceof \Dimx\Room, "World 3 contains room 'beachcliff'");
    assertTest($treasureItem instanceof \Dimx\Item, "World 3 defines item 'treasure'");
    assertTest($treasureItem->isHidden(), "Initial 'treasure' isHidden() === true");

    $pSearcher->move($beachcliffRoom);
    // Player does NOT have foundmap yet
    assertTest(!$pSearcher->varExists('foundmap') || empty($pSearcher->getProperty('foundmap')), "Player does not have foundmap");

    $searchEngine->executeCommandAction($w3Search, $pSearcher, 'search', '', '');
    $cliffMsgs1 = $pSearcher->drainConsole();
    $cliffOutput1 = implode(' ', $cliffMsgs1);

    assertTest(str_contains($cliffOutput1, "I found nothing new."), "Searching beachcliff without map outputs 'I found nothing new.': '{$cliffOutput1}'");
    assertTest($treasureItem->isHidden(), "'treasure' remains hidden because treasure.onSearch returned false");

    // Now give player foundmap = 1
    $pSearcher->setProperty('foundmap', 1);
    $pSearcher->foundmap = 1;

    $searchEngine->executeCommandAction($w3Search, $pSearcher, 'search', '', '');
    $cliffMsgs2 = $pSearcher->drainConsole();
    $cliffOutput2 = implode(' ', $cliffMsgs2);

    assertTest(str_contains($cliffOutput2, "I found treasure chest!!"), "Searching beachcliff WITH map reveals treasure ('I found treasure chest!!'): '{$cliffOutput2}'");
    assertTest(!$treasureItem->isHidden(), "'treasure' isHidden() is now false after successful onSearch");

    // 5. Test searching non-existent target
    $searchEngine->executeCommandAction($w3Search, $pSearcher, 'search', 'nonexistent_box', '');
    $errMsgs = $pSearcher->drainConsole();
    $errOutput = implode(' ', $errMsgs);
    assertTest(str_contains($errOutput, "The selected item does not exist anymore."), "Searching invalid item outputs 'The selected item does not exist anymore.': '{$errOutput}'");
}

// ---------------------------------------------------------------------------
// Section 33: Testing Give Command, Default Panel Button & onReceiveItem Quest Transfer
// ---------------------------------------------------------------------------
if (shouldRun(33)) {
    echo "\n--- Section 33: Testing Give Command, Default Panel Button & onReceiveItem Quest Transfer (world3net Tommy & apple) ---\n";

    $dxwLoader = new \Dimx\DxwLoader('http://localhost');
    $w3Give = $dxwLoader->load(__DIR__ . '/../docs/dxw-examples/world3net.dxw');
    $giveEngine = new \Dimx\GameEngine($pm, 'w3_give_test', __DIR__ . '/../docs/dxw-examples/world3net.dxw');

    // 1. Verify Default Panel defines 'give' button
    $defPanel = $w3Give->defaultPanel;
    assertTest($defPanel !== null, "World 3 has default panel");
    $giveBtn = $defPanel->buttons['give'] ?? null;
    assertTest($giveBtn instanceof \Dimx\Ctrl, "Default panel contains 'give' button");
    assertTest($giveBtn->type === \Dimx\Ctrl::TYPE_BUTTON, "'give' ctrl is a button");
    assertTest($giveBtn->caption === 'Give', "'give' button caption is 'Give'");
    assertTest($giveBtn->eventModel === 'OO', "'give' button eventModel is 'OO' (two objects: item, character)");
    assertTest($defPanel->isCommandAllowed('give'), "Default panel allows 'give' command");

    // 2. Verify UIhtml COMMAND_DEFS includes 'give' with noArgs: 2
    $htmlP = new \Dimx\Player($w3Give, 'HtmlHero', 'p_give_html');
    $w3Give->addPlayer($htmlP);
    $uiHtml = new \Dimx\UIhtml($htmlP, null);
    $kitchenRoom = $w3Give->contents['kitchen'] ?? null;
    ob_start();
    $uiHtml->render([
        'world'    => $w3Give,
        'player'   => $htmlP,
        'room'     => $kitchenRoom,
        'messages' => [],
    ]);
    $htmlOutput = ob_get_clean();
    assertTest(str_contains($htmlOutput, "'give': {"), "UIhtml script contains 'give' command definition");
    assertTest(str_contains($htmlOutput, "model: 'OO'"), "UIhtml command definition specifies model 'OO'");

    // 3. Setup quest entities in world3net
    $tommyChar = $w3Give->contents['tommy'] ?? null;
    assertTest($tommyChar instanceof \Dimx\Character, "World 3 defines character 'tommy'");
    assertTest($tommyChar->accepts === 'apple', "Tommy accepts 'apple': {$tommyChar->accepts}");
    $cellItem = $w3Give->contents['cell'] ?? null;
    assertTest($cellItem instanceof \Dimx\Item, "World 3 defines item 'cell'");
    assertTest($cellItem->container === $tommyChar, "Tommy is initially holding 'cell'");

    $pGiver = new \Dimx\Player($w3Give, 'GiverHero', 'p_giver');
    $w3Give->addPlayer($pGiver);
    $pGiver->move($kitchenRoom);
    assertTest($pGiver->getRoom()->id === 'kitchen', "Giver is inside 'kitchen'");
    assertTest($tommyChar->container === $kitchenRoom, "Tommy is inside 'kitchen'");

    // Give player an apple
    $appleItem = new \Dimx\Item($w3Give, 'Fresh Apple', 'apple', 'A juicy red apple.', null, 'pickable=1');
    $appleItem->move($pGiver);
    assertTest(isset($pGiver->contents['apple']), "Giver is carrying 'apple'");

    // 4. Test giving rejected item: create non-accepted key1 item
    $rejectItem = new \Dimx\Item($w3Give, 'Rusty Key', 'rusty_key', 'An old key.', null, 'pickable=1');
    $rejectItem->move($pGiver);

    $giveEngine->executeCommandAction($w3Give, $pGiver, 'give', 'rusty_key', 'tommy');
    $rejectMsgs = $pGiver->drainConsole();
    $rejectOutput = implode(' ', $rejectMsgs);
    assertTest(str_contains($rejectOutput, "Does not accept it."), "Giving rejected item outputs 'Does not accept it.': '{$rejectOutput}'");
    assertTest(isset($pGiver->contents['rusty_key']), "Rejected item remains in giver's inventory");

    // 5. Test successful give: give apple to tommy
    $w3Give->chatQueue = [];
    $giveEngine->executeCommandAction($w3Give, $pGiver, 'give', 'apple', 'tommy');
    $giverMsgs = $pGiver->drainConsole();
    $giverOutput = implode(' ', $giverMsgs);

    assertTest(str_contains($giverOutput, "I gave Fresh Apple to Tommy."), "Giver console receives 'I gave Fresh Apple to Tommy.': '{$giverOutput}'");
    $lastChat = end($w3Give->chatQueue);
    assertTest(
        $lastChat !== false && str_contains($lastChat['text'], "Thank you! While I'm eating, would you please hold this cellar phone..."),
        "Tommy spoke via tommy.onReceiveItem: '{$lastChat['text']}'"
    );

    // Verify item ownership transferred
    assertTest(!isset($pGiver->contents['apple']), "Apple is no longer in giver's inventory");
    assertTest(isset($tommyChar->contents['apple']), "Apple is now held by Tommy");
    assertTest(isset($pGiver->contents['cell']), "Cellular phone was transferred to giver by tommy.onReceiveItem (Move cell, \$AGENT)");
    assertTest(!isset($tommyChar->contents['cell']), "Tommy is no longer holding cell");

    // 6. Test giving to non-actor / room
    $giveEngine->executeCommandAction($w3Give, $pGiver, 'give', 'cell', 'kitchen');
    $actorMsgs = $pGiver->drainConsole();
    $actorOutput = implode(' ', $actorMsgs);
    assertTest(str_contains($actorOutput, "I can only give objects to other actors."), "Giving to room outputs 'I can only give objects to other actors.': '{$actorOutput}'");
}

// ---------------------------------------------------------------------------
// Section 34: Testing Apostrophes in ROOM & CHARACTER Definitions
// ---------------------------------------------------------------------------
if (shouldRun(34)) {
    echo "\n--- Section 34: Testing Apostrophes in ROOM & CHARACTER Definitions (world3net parcoAnatre 'The duck\'s house') ---\n";

    $dxwLoader = new \Dimx\DxwLoader('http://localhost');
    $w3Rooms = $dxwLoader->load(__DIR__ . '/../docs/dxw-examples/world3net.dxw');

    // 1. Verify 'parcoAnatre' NAME preserves "'s" and does not truncate to 'The duck'
    $parcoAnatre = $w3Rooms->getRoom('parcoAnatre');
    assertTest($parcoAnatre instanceof \Dimx\Room, "World 3 contains room 'parcoAnatre'");
    assertTest($parcoAnatre->name === "The duck's house", "parcoAnatre NAME is 'The duck\'s house' (got '{$parcoAnatre->name}')");
    assertTest($parcoAnatre->name !== "The duck", "parcoAnatre NAME is NOT truncated to 'The duck'");

    // 2. Verify other room names with apostrophes in world3net
    $bungalowPatio = $w3Rooms->getRoom('bungalowPatio');
    assertTest($bungalowPatio instanceof \Dimx\Room, "World 3 contains room 'bungalowPatio'");
    assertTest($bungalowPatio->name === "The bungalow's back", "bungalowPatio NAME is 'The bungalow\'s back' (got '{$bungalowPatio->name}')");

    $cellar = $w3Rooms->getRoom('cellar');
    assertTest($cellar instanceof \Dimx\Room, "World 3 contains room 'cellar'");
    assertTest($cellar->name === "The bar's cellar", "cellar NAME is 'The bar\'s cellar' (got '{$cellar->name}')");

    $beachcliff = $w3Rooms->getRoom('beachcliff');
    assertTest($beachcliff instanceof \Dimx\Room, "World 3 contains room 'beachcliff'");
    assertTest($beachcliff->name === "The cliff's bottom", "beachcliff NAME is 'The cliff\'s bottom' (got '{$beachcliff->name}')");

    // Also verify link name with apostrophe
    $lakeLink = $w3Rooms->getLink('w11a') ?? $w3Rooms->contents['w11a'] ?? null;
    if ($lakeLink instanceof \Dimx\Link) {
        assertTest($lakeLink->name === "lake's edge", "w11a link NAME is 'lake\'s edge' (got '{$lakeLink->name}')");
    }

    // 3. Verify UIhtml rendering of parcoAnatre displays full name 'The duck's house'
    $pDuck = new \Dimx\Player($w3Rooms, 'DuckObserver', 'p_duck_obs');
    $pDuck->move($parcoAnatre);
    $uiHtml = new \Dimx\UIhtml($pDuck, null);
    ob_start();
    $uiHtml->render([
        'world'    => $w3Rooms,
        'player'   => $pDuck,
        'room'     => $parcoAnatre,
        'messages' => [],
    ]);
    $htmlOutput = ob_get_clean();

    assertTest(str_contains($htmlOutput, "The duck&#039;s house") || str_contains($htmlOutput, "The duck's house"), "UIhtml renders 'The duck\'s house' in header/scene");
    assertTest(!str_contains($htmlOutput, "<b>The duck</b>") && !str_contains($htmlOutput, "<h2>The duck</h2>"), "UIhtml does NOT render truncated 'The duck'");

    // 4. Verify standalone comment lines starting with ' were properly ignored
    // e.g. line 768: ' Plays the background music only once
    assertTest(!isset($w3Rooms->contents["'"]), "No object created from comment lines");
}

// -------------------------------------------------------------
// Test 35: 3x3 Navpad Table, Default Skin Arrow Icons & Direction Placement (UIhtml)
// -------------------------------------------------------------
if (shouldRun(35)) {
    echo "\n--- Section 35: Testing 3x3 Navpad Table, Default Skin Arrow Icons & Direction Placement (UIhtml) ---\n";

    $dxwFile = __DIR__ . '/../docs/dxw-examples/world3net.dxw';
    $loader = new \Dimx\DxwLoader('http://localhost');
    $w3Nav = $loader->load($dxwFile, 'w3_navpad_test');

    // 1. Verify default skin registration and defaults
    assertTest(isset($w3Nav->skins['default']), "World has 'default' skin registered");
    assertTest($w3Nav->defaultSkin instanceof \Dimx\Skin, "World defaultSkin is set");
    assertTest($w3Nav->defaultSkin->id === 'default', "World defaultSkin ID is 'default'");
    assertTest(str_ends_with($w3Nav->defaultSkin->getWayIcon('N'), 'icowayn.gif'), "defaultSkin getWayIcon('N') ends with 'icowayn.gif'");
    assertTest(str_ends_with($w3Nav->defaultSkin->getWayIcon('S'), 'icoways.gif'), "defaultSkin getWayIcon('S') ends with 'icoways.gif'");
    assertTest(str_ends_with($w3Nav->defaultSkin->getWayIcon('E'), 'icowaye.gif'), "defaultSkin getWayIcon('E') ends with 'icowaye.gif'");
    assertTest(str_ends_with($w3Nav->defaultSkin->getWayIcon('W'), 'icowayw.gif'), "defaultSkin getWayIcon('W') ends with 'icowayw.gif'");
    assertTest(str_ends_with($w3Nav->defaultSkin->getSpacerIcon(), 'blank.gif'), "defaultSkin getSpacerIcon() ends with 'blank.gif'");

    // 2. Render UI in 'spiaggia' (exits E to beachcliff, N to spiaggia2, W to spiaggia4)
    $pSpiaggia = new \Dimx\Player($w3Nav, 'SpiaggiaWalker', 'p_sp_walk');
    $spiaggia = $w3Nav->getRoom('spiaggia');
    $pSpiaggia->move($spiaggia);

    $ui = new \Dimx\UIhtml($pSpiaggia);
    ob_start();
    $ui->render([
        'world'     => $w3Nav,
        'player'    => $pSpiaggia,
        'room'      => $spiaggia,
        'messages'  => [],
        'lockError' => false,
    ]);
    $html = ob_get_clean();

    // Verify navpad table exists with ID 'navpad'
    assertTest(str_contains($html, '<table id="navpad"'), "UIhtml has <table id=\"navpad\"");

    // Extract navpad table content
    preg_match('/<table id="navpad"[^>]*>(.*?)<\/table>/s', $html, $matches);
    assertTest(!empty($matches[1]), "Navpad table captured successfully");
    $navpadBody = $matches[1] ?? '';

    // Verify 3 rows and 9 cells
    $trCount = substr_count($navpadBody, '<tr');
    $tdCount = substr_count($navpadBody, '<td');
    assertTest($trCount === 3, "Navpad table has exactly 3 rows (got $trCount)");
    assertTest($tdCount === 9, "Navpad table has exactly 9 cells (got $tdCount)");

    // Verify NO text appears inside the navpad table
    // Strip HTML tags and check if anything besides whitespace remains
    $tableText = trim(strip_tags($navpadBody));
    assertTest($tableText === '', "Navpad table contains NO text (got '$tableText')");

    // Verify direction icons in spiaggia: N, W, E present; S absent (spacer)
    assertTest(str_contains($navpadBody, 'icowayn.gif'), "Navpad contains North arrow (icowayn.gif)");
    assertTest(str_contains($navpadBody, 'icowayw.gif'), "Navpad contains West arrow (icowayw.gif)");
    assertTest(str_contains($navpadBody, 'icowaye.gif'), "Navpad contains East arrow (icowaye.gif)");
    assertTest(!str_contains($navpadBody, 'icoways.gif'), "Navpad does NOT contain South arrow (no S exit in spiaggia)");
    assertTest(str_contains($navpadBody, 'blank.gif'), "Navpad contains spacer blanks");
    assertTest(str_contains($html, 'width="16" height="16"'), "Navpad icons use unresized standard size 16x16");
    assertTest(str_contains($html, 'SILVER'), "Navpad table background color is SILVER");

    // 3. Test room with vertical exit and custom link ICON (e.g. 'bar' has w6 Going Down 'D' with ICON trapdoor.gif, w7 East to kitchen, w4b West to beach)
    $pBar = new \Dimx\Player($w3Nav, 'BarWalker', 'p_bar_walk');
    $bar = $w3Nav->getRoom('bar');
    $pBar->move($bar);

    ob_start();
    $ui->render([
        'world'     => $w3Nav,
        'player'    => $pBar,
        'room'      => $bar,
        'messages'  => [],
        'lockError' => false,
    ]);
    $barHtml = ob_get_clean();

    preg_match('/<table id="navpad"[^>]*>(.*?)<\/table>/s', $barHtml, $barMatches);
    $barNavpad = $barMatches[1] ?? '';

    // Navpad has East exit (w7) with default arrow
    assertTest(str_contains($barNavpad, 'icowaye.gif'), "Bar navpad contains East arrow (w7)");
    // Navpad has West exit (w4b) with default arrow
    assertTest(str_contains($barNavpad, 'icowayw.gif'), "Bar navpad contains West arrow (w4b)");
    // Trapdoor w6 (Down) is mapped to the bottom exit in navpad and uses custom ICON trapdoor.gif
    assertTest(str_contains($barNavpad, 'trapdoor.gif'), "Bar navpad contains custom LINK ICON 'trapdoor.gif' for w6 (D)");
    assertTest(str_contains($barNavpad, 'data-obj-id="w6"'), "Bar navpad w6 has data-obj-id='w6'");
    assertTest(str_contains($barNavpad, 'data-dir="D"'), "Bar navpad w6 has data-dir='D'");
    assertTest(str_contains($barNavpad, 'width="16" height="16"'), "Bar navpad custom icon has standard 16x16 dimensions");
}

// -------------------------------------------------------------
// Test 36: Player Orientation, Multi-faced Rooms, Rotation Commands (rotl/rotr/rev) & Navpad Multiface
// -------------------------------------------------------------
if (shouldRun(36)) {
    echo "\n--- Section 36: Testing Player Orientation, Multi-faced Rooms, Rotation Commands (rotl/rotr/rev) & Navpad Multiface ---\n";

    $dxwLoader = new \Dimx\DxwLoader();
    $w3Ori = $dxwLoader->load(__DIR__ . '/../docs/dxw-examples/world3net.dxw', 'w3_orientation_world');
    $engineOri = new \Dimx\GameEngine($pm, 'w3_orientation_test', __DIR__ . '/../docs/dxw-examples/world3net.dxw');

    // 1. Verify pathgate is multi-faced and images are loaded per face
    $pathgate = $w3Ori->getRoom('pathgate');
    assertTest($pathgate instanceof \Dimx\Room, "world3net defines 'pathgate' room");
    assertTest($pathgate->hasSeveralFaces(), "'pathgate' hasSeveralFaces() === true");
    assertTest(count($pathgate->images) === 4, "'pathgate' has 4 face images (N, S, W, E)");
    assertTest(str_contains($pathgate->getExactImage('N')->src, 'pathgaten.jpg'), "pathgate face N has pathgaten.jpg");
    assertTest(str_contains($pathgate->getExactImage('S')->src, 'pathgates.jpg'), "pathgate face S has pathgates.jpg");
    assertTest(str_contains($pathgate->getExactImage('W')->src, 'pathgatew.jpg'), "pathgate face W has pathgatew.jpg");
    assertTest(str_contains($pathgate->getExactImage('E')->src, 'pathgatee.jpg'), "pathgate face E has pathgatee.jpg");
    assertTest($pathgate->getExactImage('S')->showareaX1 === 100, "pathgate face S has parsed SHOWAREA X1=100");

    // Verify single-faced room (e.g. ballhouse)
    $ballhouse = $w3Ori->getRoom('ballhouse');
    assertTest($ballhouse instanceof \Dimx\Room, "world3net defines 'ballhouse' room");
    assertTest(!$ballhouse->hasSeveralFaces(), "'ballhouse' hasSeveralFaces() === false (single face)");

    // 2. Test Player Orientation on room traversal (Character::go)
    $pCris = new \Dimx\Player($w3Ori, 'Cris', 'p_cris_1');
    $pCris->move($ballhouse);
    assertTest($pCris->getRoom()->id === 'ballhouse', "Cris starts in ballhouse");

    // In world3net: LINK w31 pathgate-ballhouse S (pathgate -> ballhouse is S; ballhouse -> pathgate is N)
    $movedToGate = $pCris->go('w31');
    assertTest($movedToGate, "Cris navigated from ballhouse to pathgate via w31");
    assertTest($pCris->getRoom()->id === 'pathgate', "Cris is now inside pathgate");
    assertTest($pCris->facing === 'N', "Cris arrived facing 'N' matching entrance link direction");

    // Test entering from rocca: LINK w18 pathgate-rocca N (pathgate -> rocca is N; rocca -> pathgate is S)
    $rocca = $w3Ori->getRoom('rocca');
    $pCris->move($rocca);
    $movedFromRocca = $pCris->go('w18');
    assertTest($movedFromRocca, "Cris navigated from rocca to pathgate via w18");
    assertTest($pCris->facing === 'S', "Cris arrived facing 'S' matching entrance link direction");

    // 3. Test Title Bar Display in UIhtml
    $uiHtml = new \Dimx\UIhtml($pCris);
    ob_start();
    $uiHtml->render([
        'world'     => $w3Ori,
        'player'    => $pCris,
        'room'      => $pathgate,
        'messages'  => [],
        'lockError' => false,
    ]);
    $htmlFacingS = ob_get_clean();

    assertTest(str_contains($htmlFacingS, 'The gate - South view'), "UIhtml title bar displays 'The gate - South view': " . (preg_match('/<div class="dimx-title-bar">.*?<\/div>/s', $htmlFacingS, $m) ? strip_tags($m[0]) : ''));
    assertTest(str_contains($htmlFacingS, 'The gate - South view') && str_contains($htmlFacingS, 'Cris'), "UIhtml title bar contains 'The gate - South view (Cris)'");
    // Verify room image matches facing 'S' (pathgates.jpg)
    assertTest(str_contains($htmlFacingS, 'pathgates.jpg'), "UIhtml scene container displays 'pathgates.jpg' when facing S");
    assertTest(!str_contains($htmlFacingS, 'pathgaten.jpg'), "UIhtml scene container does NOT display 'pathgaten.jpg' when facing S");

    // Single-faced room title bar does NOT display orientation view
    $pCris->move($ballhouse);
    ob_start();
    $uiHtml->render([
        'world'     => $w3Ori,
        'player'    => $pCris,
        'room'      => $ballhouse,
        'messages'  => [],
        'lockError' => false,
    ]);
    $htmlSingleFace = ob_get_clean();
    assertTest(!str_contains($htmlSingleFace, '- South view') && !str_contains($htmlSingleFace, '- North view'), "Single-faced room title bar does NOT add orientation view string");
    $pCris->move($pathgate);
    $pCris->facing = 'S';

    // 4. Test Rotation Commands (rotl, rotr, rev) via GameEngine
    // Initial: facing 'S'
    // 'rotr' rotates 90 degrees right: S -> W
    $engineOri->executeCommandAction($w3Ori, $pCris, 'rotr', '', '');
    assertTest($pCris->facing === 'W', "After 'rotr', player facing rotated S -> W (got '{$pCris->facing}')");
    assertTest(str_contains($pathgate->getImageForFacing($pCris->facing)->src, 'pathgatew.jpg'), "Room image facing W is pathgatew.jpg");

    // 'rotr' rotates W -> N
    $engineOri->executeCommandAction($w3Ori, $pCris, 'rotr', '', '');
    assertTest($pCris->facing === 'N', "After 'rotr', player facing rotated W -> N (got '{$pCris->facing}')");
    assertTest(str_contains($pathgate->getImageForFacing($pCris->facing)->src, 'pathgaten.jpg'), "Room image facing N is pathgaten.jpg");

    // 'rotr' rotates N -> E
    $engineOri->executeCommandAction($w3Ori, $pCris, 'rotr', '', '');
    assertTest($pCris->facing === 'E', "After 'rotr', player facing rotated N -> E (got '{$pCris->facing}')");
    assertTest(str_contains($pathgate->getImageForFacing($pCris->facing)->src, 'pathgatee.jpg'), "Room image facing E is pathgatee.jpg");

    // 'rotl' rotates 90 degrees left: E -> N
    $engineOri->executeCommandAction($w3Ori, $pCris, 'rotl', '', '');
    assertTest($pCris->facing === 'N', "After 'rotl', player facing rotated E -> N (got '{$pCris->facing}')");

    // 'rotl' rotates N -> W
    $engineOri->executeCommandAction($w3Ori, $pCris, 'rotl', '', '');
    assertTest($pCris->facing === 'W', "After 'rotl', player facing rotated N -> W (got '{$pCris->facing}')");

    // 'rev' reverses 180 degrees: W -> E
    $engineOri->executeCommandAction($w3Ori, $pCris, 'rev', '', '');
    assertTest($pCris->facing === 'E', "After 'rev', player facing reversed W -> E (got '{$pCris->facing}')");

    // 'rev' reverses 180 degrees back: E -> W
    $engineOri->executeCommandAction($w3Ori, $pCris, 'rev', '', '');
    assertTest($pCris->facing === 'W', "After 'rev', player facing reversed E -> W (got '{$pCris->facing}')");

    // 5. Test Navpad Multiface HTML rendering
    // When facing 'E':
    $pCris->facing = 'E';
    ob_start();
    $uiHtml->render([
        'world'     => $w3Ori,
        'player'    => $pCris,
        'room'      => $pathgate,
        'messages'  => [],
        'lockError' => false,
    ]);
    $htmlFacingE = ob_get_clean();

    assertTest(str_contains($htmlFacingE, 'The gate - East view'), "UIhtml title bar displays 'The gate - East view'");
    assertTest(str_contains($htmlFacingE, 'pathgatee.jpg'), "UIhtml scene displays 'pathgatee.jpg' when facing E");

    // Extract navpad table
    preg_match('/<table id="navpad"[^>]*>(.*?)<\/table>/s', $htmlFacingE, $navMatchE);
    $navpadE = $navMatchE[1] ?? '';
    assertTest(!empty($navpadE), "Navpad table extracted for facing E");

    // Cell 2 has forward exit gate1 with custom ICON icoDoor.gif (direction E)
    assertTest(str_contains($navpadE, 'icoDoor.gif'), "Facing E: forward exit in navpad is gate1 with 'icoDoor.gif'");
    assertTest(str_contains($navpadE, 'data-obj-id="gate1"'), "Facing E: forward exit data-obj-id='gate1'");
    assertTest(str_contains($navpadE, 'data-dir="E"'), "Facing E: forward exit data-dir='E'");

    // Cell 4 has Rotate Left button (icorotl.gif) with command 'rotl'
    assertTest(str_contains($navpadE, 'icorotl.gif'), "Navpad contains Rotate Left icon 'icorotl.gif'");
    assertTest(str_contains($navpadE, "sendCmd('rotl','')"), "Rotate Left button calls sendCmd('rotl','')");

    // Cell 6 has Rotate Right button (icorotr.gif) with command 'rotr'
    assertTest(str_contains($navpadE, 'icorotr.gif'), "Navpad contains Rotate Right icon 'icorotr.gif'");
    assertTest(str_contains($navpadE, "sendCmd('rotr','')"), "Rotate Right button calls sendCmd('rotr','')");

    // Cell 8 has Reverse button (reverse.gif) with command 'rev'
    assertTest(str_contains($navpadE, 'reverse.gif'), "Navpad contains Reverse icon 'reverse.gif'");
    assertTest(str_contains($navpadE, "sendCmd('rev','')"), "Reverse button calls sendCmd('rev','')");

    // Navpad does NOT contain directional arrows except forward
    assertTest(!str_contains($navpadE, 'icowayw.gif'), "Facing E: Navpad does NOT contain West directional arrow (replaced by rotation)");
    assertTest(!str_contains($navpadE, 'icoways.gif'), "Facing E: Navpad does NOT contain South directional arrow (replaced by rotation)");

    // 6. Test UIjson API output for orientation and commands
    $uiJson = new \Dimx\UIjson($pCris, $engineOri);
    $jsonContext = [
        'world'     => $w3Ori,
        'player'    => $pCris,
        'room'      => $pathgate,
        'messages'  => [],
        'lockError' => false,
    ];
    $jsonStatus = $uiJson->buildPlayerStatus($pCris);
    assertTest(isset($jsonStatus['facing']) && $jsonStatus['facing'] === 'E', "UIjson buildPlayerStatus includes facing='E'");

    $jsonRoom = $uiJson->buildRoomSnapshot($pathgate, $pCris);
    assertTest($jsonRoom['facing'] === 'E', "UIjson buildRoomSnapshot includes facing='E'");
    assertTest($jsonRoom['has_several_faces'] === true, "UIjson buildRoomSnapshot has_several_faces === true");
    assertTest(str_contains($jsonRoom['image'] ?? '', 'pathgatee.jpg'), "UIjson buildRoomSnapshot image is pathgatee.jpg");

    // 7. Test UIbot API output for orientation and available commands
    $uiBot = new \Dimx\UIbot($pCris, $engineOri);
    $panelDefault = $w3Ori->defaultPanel;
    $botCommands = $panelDefault->getAvailableCommands();
    assertTest(in_array('rotl', $botCommands, true), "Available commands includes 'rotl'");
    assertTest(in_array('rotr', $botCommands, true), "Available commands includes 'rotr'");
    assertTest(in_array('rev', $botCommands, true), "Available commands includes 'rev'");

    // Build bot response payload
    $botRefl = new \ReflectionClass($uiBot);
    $buildPayloadMethod = $botRefl->getMethod('buildResponsePayload');
    $buildPayloadMethod->setAccessible(true);
    $botPayload = $buildPayloadMethod->invoke($uiBot, $jsonContext, $w3Ori, $pCris, $pathgate, $panelDefault, 'ok', false, false);

    assertTest(isset($botPayload['facing']) && $botPayload['facing'] === 'E', "UIbot payload top-level facing is readable ('E')");
    assertTest(isset($botPayload['room']['facing']) && $botPayload['room']['facing'] === 'E', "UIbot room facing is readable ('E')");
    assertTest(str_contains($botPayload['room']['image'] ?? '', 'pathgatee.jpg'), "UIbot room image is pathgatee.jpg");
    assertTest(in_array('rotl', $botPayload['commands'] ?? [], true), "UIbot commands list contains 'rotl'");
    assertTest(in_array('rotr', $botPayload['commands'] ?? [], true), "UIbot commands list contains 'rotr'");
    assertTest(in_array('rev', $botPayload['commands'] ?? [], true), "UIbot commands list contains 'rev'");

    // 8. Test scnCampoarato: forward exit in navpad cell #2 displays North-pointing arrow while preserving South title
    assertTest(!str_contains($htmlFacingS, '🗺 Exits'), "UIhtml does NOT render '🗺 Exits' section title label");

    $scnCampoarato = $w3Ori->getRoom('scnCampoarato');
    assertTest($scnCampoarato instanceof \Dimx\Room, "world3net defines 'scnCampoarato' room");
    assertTest($scnCampoarato->hasSeveralFaces(), "scnCampoarato hasSeveralFaces() === true (S and N)");

    // Move player from scnPassage1 (North) into scnCampoarato via w30 (w30 is N from scnCampoarato; link direction from scnPassage1 is S)
    $scnPassage1 = $w3Ori->getRoom('scnPassage1');
    $pCris->move($scnPassage1);
    $movedFromPassage = $pCris->go('w30');
    assertTest($movedFromPassage, "Cris navigated from scnPassage1 to scnCampoarato via w30");
    assertTest($pCris->getRoom()->id === 'scnCampoarato', "Cris is in scnCampoarato");
    assertTest($pCris->facing === 'S', "Cris arrived facing 'S' (from North, facing South)");

    ob_start();
    $uiHtml->render([
        'world'     => $w3Ori,
        'player'    => $pCris,
        'room'      => $scnCampoarato,
        'messages'  => [],
        'lockError' => false,
    ]);
    $campoHtml = ob_get_clean();

    assertTest(str_contains($campoHtml, 'The pathway to the hill - South view'), "scnCampoarato title bar shows 'The pathway to the hill - South view'");
    assertTest(str_contains($campoHtml, 'scnCampoarato2.jpg'), "scnCampoarato scene displays 'scnCampoarato2.jpg' for South view");

    preg_match('/<table id="navpad"[^>]*>(.*?)<\/table>/s', $campoHtml, $campoNavMatch);
    $campoNav = $campoNavMatch[1] ?? '';
    assertTest(!empty($campoNav), "Navpad table extracted for scnCampoarato facing S");

    // In cell #2 (forward): should show North-pointing arrow (meaning forward!), but with title "South"
    assertTest(str_contains($campoNav, 'icowayn.gif'), "scnCampoarato navpad cell #2 displays North arrow 'icowayn.gif' (meaning forward)");
    assertTest(!str_contains($campoNav, 'icoways.gif'), "scnCampoarato navpad cell #2 does NOT display South arrow 'icoways.gif'");
    assertTest(str_contains($campoNav, 'title="South"'), "scnCampoarato navpad cell #2 preserves HTML title='South'");
    assertTest(str_contains($campoNav, 'data-obj-id="w10"'), "scnCampoarato navpad cell #2 has data-obj-id='w10'");
    assertTest(str_contains($campoNav, 'data-dir="S"'), "scnCampoarato navpad cell #2 has data-dir='S'");
    assertTest(str_contains($campoNav, "sendCmd('go','w10')"), "scnCampoarato navpad cell #2 click sends go w10");

    // 9. Test Single-faced Room Forced Orientation & Relative Navpad in ballhouse
    // ballhouse has 1 image facing 'S': IMAGE S SHOWAREA 175,-1,-1 ballhouse.jpg
    // and 1 link: w31 leading to pathgate in direction North ('N')
    $ballhouse = $w3Ori->getRoom('ballhouse');
    assertTest($ballhouse instanceof \Dimx\Room, "ballhouse room exists in world3net");
    assertTest(count($ballhouse->images) === 1, "ballhouse has exactly 1 image");
    assertTest(array_key_first($ballhouse->images) === 'S', "ballhouse single image face is 'S'");

    // Player enters ballhouse from pathgate
    $pCris->facing = 'N'; // initially facing N in pathgate
    $pCris->move($ballhouse);
    assertTest($pCris->facing === 'S', "Entering ballhouse forced player facing to 'S' (got '{$pCris->facing}')");

    ob_start();
    $uiHtml->render([
        'world'     => $w3Ori,
        'player'    => $pCris,
        'room'      => $ballhouse,
        'messages'  => [],
        'lockError' => false,
    ]);
    $ballhouseHtml = ob_get_clean();

    // 1) Title bar does NOT show orientation string in single-faced room
    assertTest(str_contains($ballhouseHtml, 'The stone house') && str_contains($ballhouseHtml, 'Cris'), "ballhouse title bar displays room name and player name");
    assertTest(!str_contains($ballhouseHtml, 'South view'), "ballhouse title bar does NOT display 'South view'");

    // 2) Navpad reinterpretation:
    // Player is facing South, so the North exit (w31) is behind the player (relative direction: South).
    // It must appear in navpad cell #8 (bottom / South slot) as a South-pointing arrow (icoways.gif),
    // with HTML title indicating the real direction 'North', and action go N.
    preg_match('/<table id="navpad"[^>]*>(.*?)<\/table>/s', $ballhouseHtml, $bhNavMatch);
    $bhNav = $bhNavMatch[1] ?? '';
    assertTest(!empty($bhNav), "Navpad table extracted for ballhouse");

    // Row 1 (cell 2) must NOT have w31 (it should be empty spacer)
    assertTest(!str_contains($bhNav, 'icowayn.gif'), "ballhouse navpad does NOT contain North arrow 'icowayn.gif'");
    // Row 3 (cell 8) must have South-pointing arrow icoways.gif
    assertTest(str_contains($bhNav, 'icoways.gif'), "ballhouse navpad cell #8 displays South arrow 'icoways.gif'");
    // HTML title attribute indicates real direction 'North'
    assertTest(str_contains($bhNav, 'title="North"'), "ballhouse navpad cell #8 has title='North'");
    // data-obj-id and sendCmd preserve the actual LINK id and cardinal direction 'N'
    assertTest(str_contains($bhNav, 'data-obj-id="w31"'), "ballhouse navpad cell #8 has data-obj-id='w31'");
    assertTest(str_contains($bhNav, 'data-dir="N"'), "ballhouse navpad cell #8 has data-dir='N'");
    assertTest(str_contains($bhNav, "sendCmd('go','w31')"), "ballhouse navpad cell #8 sends go w31");

    // 10. Test exit down 'to the beach' at rocca / cliff does NOT have [D] label
    $rocca = $w3Ori->getRoom('rocca');
    $pCris->move($rocca);
    ob_start();
    $uiHtml->render([
        'world'     => $w3Ori,
        'player'    => $pCris,
        'room'      => $rocca,
        'messages'  => [],
        'lockError' => false,
    ]);
    $roccaHtml = ob_get_clean();

    // Verify exit 'to the beach' does NOT render [D] label
    assertTest(!str_contains($roccaHtml, '[D]'), "Exit down 'to the beach' does NOT render '[D]' text label");
    assertTest(str_contains($roccaHtml, 'to the beach'), "Exit down displays 'to the beach' label");
    assertTest(str_contains($roccaHtml, 'title="Down"'), "Exit down has title='Down' on icon/button");
    assertTest(str_contains($roccaHtml, "sendCmd('go','w17')"), "Exit down click sends go w17");
}

// -------------------------------------------------------------
// Test 37: SHOWAREA Bounds & Baseline Positioning for Onscreen Objects
// -------------------------------------------------------------
if (shouldRun(37)) {
    echo "\n--- Section 37: Testing SHOWAREA Bounds & Baseline Positioning for Onscreen Objects ---\n";

    $dxwLoader = new \Dimx\DxwLoader();
    $w3Show = $dxwLoader->load(__DIR__ . '/../docs/dxw-examples/world3net.dxw', 'w3_showarea_world');
    $engineShow = new \Dimx\GameEngine($pm, 'w3_showarea_test', __DIR__ . '/../docs/dxw-examples/world3net.dxw');

    // 1. Check room kitchen has IMAGE SHOWAREA 185,280,55 scnbarinterior.jpg
    $kitchen = $w3Show->getRoom('kitchen');
    assertTest($kitchen !== null, "world3net defines 'kitchen' room");
    $kitchenImg = $kitchen->getImageForFacing('N');
    assertTest($kitchenImg !== null, "'kitchen' has an image");
    assertTest($kitchenImg->showareaX1 === 185, "kitchen showareaX1 is 185 (got {$kitchenImg->showareaX1})");
    assertTest($kitchenImg->showareaX2 === 280, "kitchen showareaX2 is 280 (got {$kitchenImg->showareaX2})");
    assertTest($kitchenImg->showareaY === 55, "kitchen showareaY is 55 (got {$kitchenImg->showareaY})");

    // 2. Character Tommy is positioned in kitchen with no explicit coordinates (showX = -1, showY = -1)
    $tommy = $w3Show->getCharacter('tommy');
    assertTest($tommy !== null, "world3net defines character 'tommy'");
    assertTest($tommy->showX === -1, "tommy has showX = -1 (automatic SHOWAREA placement)");
    assertTest($tommy->showY === -1, "tommy has showY = -1 (automatic SHOWAREA placement)");

    // 3. Connect player to kitchen and test UIhtml rendering
    $pTest = $engineShow->ensurePlayer($w3Show, 'ShowTester');
    $pTest->move($kitchen);

    // Test at design resolution (screenWidth = 1024 -> factorx = 1.0)
    $pTest->screenWidth = 1024;
    $pTest->screenHeight = 768;
    $uiHtml = new \Dimx\UIhtml($pTest, $engineShow);
    assertTest(abs($uiHtml->getFactorX($pTest) - 1.0) < 0.001, "UIhtml has factorx = 1.0 at design resolution 1024x768");

    // Run multiple renders (e.g. 25 times) to verify random x position always stays within SHOWAREA [185, 280]
    $allLeftsValid = true;
    $allTopsValid = true;
    $leftsObserved = [];

    for ($i = 0; $i < 25; $i++) {
        ob_start();
        $uiHtml->render([
            'world'     => $w3Show,
            'player'    => $pTest,
            'room'      => $kitchen,
            'messages'  => [],
            'lockError' => false,
        ]);
        $htmlOut = ob_get_clean();

        // Extract Tommy's style attribute
        // <img ... class="dimx-onscreen-obj" data-obj-id="tommy" ... style="position:absolute;left:(...)px;top:(...)px...
        if (preg_match('/<img[^>]*data-obj-id="tommy"[^>]*style="([^"]*)"/i', $htmlOut, $m)) {
            $style = $m[1];
            if (preg_match('/left:\s*(\d+)px/i', $style, $lm) && preg_match('/top:\s*(\d+)px/i', $style, $tm)) {
                $leftPx = (int)$lm[1];
                $topPx = (int)$tm[1];
                $leftsObserved[] = $leftPx;

                // Screen coordinates at factorx = 1.0:
                // screenX = 1 + cartX
                // cartX must fall in [185, 235], so screenX in [186, 236]
                if ($leftPx < 186 || $leftPx > 236) {
                    $allLeftsValid = false;
                }
                // top: screenY = roomH (235) - cartY (55) - baseH (100) = 80px
                if ($topPx !== 80) {
                    $allTopsValid = false;
                }
            } else {
                $allLeftsValid = false;
            }
        } else {
            $allLeftsValid = false;
        }
    }

    assertTest($allLeftsValid, "Tommy's random x position in kitchen at 1024x768 always falls within scaled SHOWAREA (observed lefts: " . implode(', ', array_unique($leftsObserved)) . ")");
    assertTest(min($leftsObserved) >= 186, "Tommy's minimum observed left (" . min($leftsObserved) . "px) is >= 186px");
    assertTest(max($leftsObserved) <= 236, "Tommy's maximum observed left (" . max($leftsObserved) . "px) is <= 236px");
    assertTest($allTopsValid, "Tommy's y coordinate correctly reflects baseline 55 at factorx = 1.0 (top: 80px)");

    // Test desktop screen cap at 1024px:
    // On 800-width design, 1280x800 display is capped to 1024 (factorx = 1024 / 800 = 1.28)
    $w3Show->screenwidth = 800;
    $pTest->screenWidth = 1280;
    $pTest->screenHeight = 800;
    assertTest(abs($uiHtml->getFactorX($pTest) - 1.28) < 0.001, "UIhtml caps 1280x800 desktop display to 1024 width (factorx = 1.28 on 800-wide world)");

    $scaledLefts = [];
    for ($i = 0; $i < 25; $i++) {
        ob_start();
        $uiHtml->render([
            'world'     => $w3Show,
            'player'    => $pTest,
            'room'      => $kitchen,
            'messages'  => [],
            'lockError' => false,
        ]);
        $htmlScaled = ob_get_clean();

        if (preg_match('/<img[^>]*data-obj-id="tommy"[^>]*style="([^"]*)"/i', $htmlScaled, $m)) {
            if (preg_match('/left:\s*(\d+)px/i', $m[1], $lm)) {
                $scaledLefts[] = (int)$lm[1];
            }
        }
    }
    assertTest(min($scaledLefts) >= 238, "Tommy's minimum scaled left (" . min($scaledLefts) . "px) is >= 238px (round(1.28 * 186))");
    assertTest(max($scaledLefts) <= 303, "Tommy's maximum scaled left (" . max($scaledLefts) . "px) is <= 303px (round(1.28 * 236))");
    $w3Show->screenwidth = 1024; // restore design width

    // 4. Test explicit coordinates (e.g. switch1: SHOW ICON 120,138)
    $switch1 = $w3Show->getItem('switch1');
    assertTest($switch1 !== null, "world3net defines item 'switch1'");
    assertTest($switch1->showX === 120, "switch1 has showX = 120");
    assertTest($switch1->showY === 138, "switch1 has showY = 138");

    // Switch1 at 1024x768 (factorx = 1.0)
    $pTest->screenWidth = 1024;
    $pTest->screenHeight = 768;
    ob_start();
    $uiHtml->render([
        'world'     => $w3Show,
        'player'    => $pTest,
        'room'      => $kitchen,
        'messages'  => [],
        'lockError' => false,
    ]);
    $switchHtml = ob_get_clean();

    assertTest(preg_match('/<img[^>]*data-obj-id="switch1"[^>]*style="([^"]*)"/i', $switchHtml, $sm) === 1, "switch1 is rendered onscreen in kitchen");
    if (preg_match('/<img[^>]*data-obj-id="switch1"[^>]*style="([^"]*)"/i', $switchHtml, $sm)) {
        $swStyle = $sm[1];
        // screenX = 1 + 120 = 121px -> round(1.0 * 121) = 121px; screenY = 235 - 138 - 16 = 81px -> round(1.0 * 81) = 81px
        assertTest(str_contains($swStyle, 'left:121px'), "switch1 explicit X at 1024x768 is respected (left:121px)");
        assertTest(str_contains($swStyle, 'top:81px'), "switch1 explicit Y at 1024x768 is respected (top:81px)");
    }
}

// ---------------------------------------------------------------------------
// Section 38: Testing Non-pickable Items, PickUp Button Label & Locked Link Open Message
// ---------------------------------------------------------------------------
if (shouldRun(38)) {
    echo "\n--- Section 38: Testing Non-pickable Items, PickUp Button Label & Locked Link Open Message ---\n";

    $dxwLoader = new \Dimx\DxwLoader('http://localhost');
    $w3Sec38 = $dxwLoader->load(__DIR__ . '/../docs/dxw-examples/world3net.dxw');

    // 1. Test non-pickable item (trash in parco)
    $trash = $w3Sec38->getItem('trash');
    assertTest($trash !== null, "world3net defines item 'trash'");
    assertTest($trash->isPickable() === false, "trash has isPickable() === false (pickable not in ATTRLIST)");

    // Test pickable item (key3 in parco)
    $key3 = $w3Sec38->getItem('key3');
    assertTest($key3 !== null, "world3net defines item 'key3'");
    assertTest($key3->isPickable() === true, "key3 has isPickable() === true (pickable in ATTRLIST)");

    // Test picking up trash via engine
    $eng38 = new \Dimx\GameEngine($pm, 'w3_sec38_test', __DIR__ . '/../docs/dxw-examples/world3net.dxw');
    $w3Sec38 = $eng38->loadOrSeedWorld();
    $cris = new \Dimx\Player($w3Sec38, 'Cris', 'p_cris_sec38');
    $w3Sec38->addPlayer($cris);
    $parco = $w3Sec38->getRoom('parco');
    $cris->move($parco);

    // Player attempts to pick trash
    $eng38->executeCommandAction($w3Sec38, $cris, 'pick', 'trash', '');
    $crisMsgs = implode(' ', $cris->drainConsole());
    assertTest(str_contains($crisMsgs, 'Could not take with me a trash can'), "Attempting to pick trash outputs 'Could not take with me a trash can' (got: '{$crisMsgs}')");
    assertTest(!$cris->contains('trash'), "Player does NOT have trash in inventory");

    // Player picks up key3
    $eng38->executeCommandAction($w3Sec38, $cris, 'pick', 'key3', '');
    $cris->drainConsole();
    assertTest($cris->contains('key3'), "Player successfully picked up key3 (pickable item)");

    // 2. Test PickUp button label in Panel and UIhtml
    $defPanel = \Dimx\Panel::createDefaultPanel();
    $btnPick = $defPanel->buttons['pick'] ?? null;
    assertTest($btnPick !== null, "Default panel contains 'pick' control");
    assertTest($btnPick->caption === 'PickUp', "Default panel 'pick' button caption is 'PickUp' (got: '{$btnPick->caption}')");

    $uiHtml38 = new \Dimx\UIhtml($cris, null);
    ob_start();
    $uiHtml38->render([
        'world'     => $w3Sec38,
        'player'    => $cris,
        'room'      => $parco,
        'messages'  => [],
        'lockError' => false,
    ]);
    $html38 = ob_get_clean();
    assertTest(preg_match('/<button[^>]*id="btn_pick"[^>]*>\s*PickUp\s*<\/button>/i', $html38) === 1, "UIhtml renders button id='btn_pick' with text 'PickUp'");

    // 3. Test locked link open message ("It's locked.")
    // Move Cris to bungalowPatio where w22 is located
    $bungalowPatio = $w3Sec38->getRoom('bungalowPatio');
    $cris->move($bungalowPatio);
    $w22 = $w3Sec38->getLink('w22');
    assertTest($w22 !== null, "world3net defines link 'w22'");
    assertTest($w22->isLocked() === true, "w22 is locked");
    assertTest($w22->isOpen() === false, "w22 is closed");

    // Attempting to open w22 by id
    $eng38->executeCommandAction($w3Sec38, $cris, 'open', 'w22', '');
    $w22Msgs = implode(' ', $cris->drainConsole());
    assertTest(str_contains($w22Msgs, "It's locked."), "Attempting to open locked link 'w22' outputs 'It\\'s locked.' (got: '{$w22Msgs}')");

    // Attempting to open by direction 'S'
    $eng38->executeCommandAction($w3Sec38, $cris, 'open', 'S', '');
    $sMsgs = implode(' ', $cris->drainConsole());
    assertTest(str_contains($sMsgs, "It's locked."), "Attempting to open locked link via direction 'S' outputs 'It\\'s locked.' (got: '{$sMsgs}')");

    // 4. Test navpad rendered HTML in bungalowPatio contains LINK id w22
    ob_start();
    $uiHtml38->render([
        'world'     => $w3Sec38,
        'player'    => $cris,
        'room'      => $bungalowPatio,
        'messages'  => [],
        'lockError' => false,
    ]);
    $patioHtml = ob_get_clean();
    preg_match('/<table id="navpad"[^>]*>(.*?)<\/table>/s', $patioHtml, $patioNavMatch);
    $patioNav = $patioNavMatch[1] ?? '';
    assertTest(str_contains($patioNav, 'data-obj-id="w22"'), "bungalowPatio navpad contains LINK ID data-obj-id='w22'");
    assertTest(str_contains($patioNav, 'data-dir="S"'), "bungalowPatio navpad contains data-dir='S'");
    assertTest(str_contains($patioNav, 'icoDoor.gif'), "bungalowPatio navpad contains door icon 'icoDoor.gif'");
    assertTest(str_contains($patioNav, "sendCmd('go','w22')"), "bungalowPatio navpad click invokes sendCmd('go','w22')");

    // 5. Test navpad rendered HTML in bungalow contains LINK id w21 (the door)
    $bungalow = $w3Sec38->getRoom('bungalow');
    $cris->move($bungalow);
    ob_start();
    $uiHtml38->render([
        'world'     => $w3Sec38,
        'player'    => $cris,
        'room'      => $bungalow,
        'messages'  => [],
        'lockError' => false,
    ]);
    $bungHtml = ob_get_clean();
    preg_match('/<table id="navpad"[^>]*>(.*?)<\/table>/s', $bungHtml, $bungNavMatch);
    $bungNav = $bungNavMatch[1] ?? '';
    assertTest(str_contains($bungNav, 'data-obj-id="w21"'), "bungalow navpad contains door LINK ID data-obj-id='w21'");
    assertTest(str_contains($bungNav, 'data-dir="N"'), "bungalow navpad contains data-dir='N'");
    assertTest(str_contains($bungNav, 'icoDoor.gif'), "bungalow navpad contains door icon 'icoDoor.gif'");
    assertTest(str_contains($bungNav, "sendCmd('go','w21')"), "bungalow navpad click invokes sendCmd('go','w21')");

    // 6. Test opening by direction 'N' in bungalow infers locked door w21
    $eng38->executeCommandAction($w3Sec38, $cris, 'open', 'N', '');
    $nMsgs = implode(' ', $cris->drainConsole());
    assertTest(str_contains($nMsgs, "It's locked."), "In bungalow, 'open N' infers locked door w21 and outputs 'It\\'s locked.' (got: '{$nMsgs}')");

    // 7. Test looking at locked door w21 in bungalow via 'look w21' (cmd=look, param=w21)
    $eng38->executeCommandAction($w3Sec38, $cris, 'look', 'w21', '');
    $lookW21Msgs = implode("\n", $cris->drainConsole());
    assertTest(str_contains($lookW21Msgs, "It's firmly locked."), "Looking at w21 displays description 'It\\'s firmly locked.' (got: '{$lookW21Msgs}')");
    assertTest(str_contains($lookW21Msgs, "the door is closed."), "Looking at w21 displays status 'the door is closed.' (got: '{$lookW21Msgs}')");
    assertTest(!str_contains($lookW21Msgs, "tourism is coming everywhere"), "Looking at w21 does NOT print room description (got: '{$lookW21Msgs}')");

    // 8. Verify UIhtml client script properly targets param for 1-arg commands and stops click event propagation
    assertTest(str_contains($patioHtml, "submitCmd(cmdToSubmit, firstArg, '')"), "UIhtml client script routes 1-argument command target to param (submitCmd(cmdToSubmit, firstArg, ''))");
    assertTest(str_contains($patioHtml, "submitCmd(cmdToSubmit, firstArg, secondArg)"), "UIhtml client script routes 2-argument command target to param & target (submitCmd(cmdToSubmit, firstArg, secondArg))");
    assertTest(str_contains($patioHtml, "event.stopPropagation()"), "Navpad links call event.stopPropagation() to prevent duplicate delegated dispatch");
    assertTest(str_contains($patioHtml, "this.getAttribute('onclick')?.includes('sendCmd')"), "Delegated listener guards against elements with inline sendCmd");
}

// ---------------------------------------------------------------------------
// Section 39: Testing Use With (use2) Execution Semantics: Done! vs Nothing happens.
// ---------------------------------------------------------------------------
if (shouldRun(39)) {
    echo "\n--- Section 39: Testing Use With (use2) Execution Semantics: Done! on Success vs Nothing happens. ---\n";

    @$pm->resetWorld('w3_sec39_test');
    $dxwLoader = new \Dimx\DxwLoader('http://localhost');
    $w3Sec39 = $dxwLoader->load(__DIR__ . '/../docs/dxw-examples/world3net.dxw');
    $eng39 = new \Dimx\GameEngine($pm, 'w3_sec39_test', __DIR__ . '/../docs/dxw-examples/world3net.dxw');
    $w3Sec39 = $eng39->loadOrSeedWorld();

    $bungalow = $w3Sec39->getRoom('bungalow');
    $player39 = new \Dimx\Player($w3Sec39, 'KeyTester', 'p_key_test_39');
    $w3Sec39->addPlayer($player39);
    $player39->move($bungalow);

    $k1 = $w3Sec39->getItem('key1');
    $k2 = $w3Sec39->getItem('key2');
    $k5 = $w3Sec39->getItem('key5');
    $w21 = $w3Sec39->getLink('w21');
    $w22 = $w3Sec39->getLink('w22');

    $k1->move($player39);
    $k2->move($player39);
    $k5->move($player39);

    assertTest($w21 instanceof \Dimx\Link, "Link w21 (the door) exists in bungalow");
    assertTest($w21->isLocked(), "Link w21 is initially locked");
    assertTest($k2 instanceof \Dimx\Item, "Item key2 exists");
    assertTest($k5 instanceof \Dimx\Item, "Item key5 exists");

    // 1. Using key5 on w21 (key5 has no matching onUseWith): outputs "Nothing happens."
    $eng39->executeCommandAction($w3Sec39, $player39, 'use2', 'key5', 'w21');
    $k5Msgs = implode(' ', $player39->drainConsole());
    assertTest(str_contains($k5Msgs, "Nothing happens."), "Using key5 on w21 outputs 'Nothing happens.' (got: '{$k5Msgs}')");
    assertTest(!str_contains($k5Msgs, "Done!"), "Using key5 on w21 does NOT output 'Done!'");
    assertTest($w21->isLocked(), "Link w21 remains locked after using key5");

    // 2. Using key1 on w21 (key1.onUseWith checks w6, condition is false): outputs "Nothing happens."
    $eng39->executeCommandAction($w3Sec39, $player39, 'use2', 'key1', 'w21');
    $k1Msgs = implode(' ', $player39->drainConsole());
    assertTest(str_contains($k1Msgs, "Nothing happens."), "Using key1 on w21 outputs 'Nothing happens.' (got: '{$k1Msgs}')");
    assertTest(!str_contains($k1Msgs, "Done!"), "Using key1 on w21 does NOT output 'Done!'");
    assertTest($w21->isLocked(), "Link w21 remains locked after using key1");

    // 3. Using key2 on key5 (key2.onUseWith target condition false): outputs "Nothing happens."
    $eng39->executeCommandAction($w3Sec39, $player39, 'use2', 'key2', 'key5');
    $k2k5Msgs = implode(' ', $player39->drainConsole());
    assertTest(str_contains($k2k5Msgs, "Nothing happens."), "Using key2 on key5 outputs 'Nothing happens.' (got: '{$k2k5Msgs}')");
    assertTest(!str_contains($k2k5Msgs, "Done!"), "Using key2 on key5 does NOT output 'Done!'");

    // 4. Using key2 on w21 (key2 does NOT unlock w21; it only unlocks w22): outputs "Nothing happens." and w21 remains locked
    $eng39->executeCommandAction($w3Sec39, $player39, 'use2', 'key2', 'w21');
    $k2Msgs = implode(' ', $player39->drainConsole());
    assertTest(str_contains($k2Msgs, "Nothing happens."), "Using key2 on w21 outputs 'Nothing happens.' (got: '{$k2Msgs}')");
    assertTest(!str_contains($k2Msgs, "Done!"), "Using key2 on w21 does NOT output 'Done!'");
    assertTest($w21->isLocked(), "Link w21 remains locked after using key2");

    // 5. Using key2 on w22 (key2 also unlocks patio service door w22 when at patio)
    $patio = $w3Sec39->getRoom('bungalowPatio');
    $player39->move($patio);
    assertTest($w22->isLocked(), "Link w22 is initially locked");
    $eng39->executeCommandAction($w3Sec39, $player39, 'use2', 'key2', 'w22');
    $k2w22Msgs = implode(' ', $player39->drainConsole());
    assertTest(str_contains($k2w22Msgs, "Done!"), "Using key2 on w22 outputs 'Done!' (got: '{$k2w22Msgs}')");
    assertTest(!$w22->isLocked(), "Link w22 is unlocked (locked=0) after using key2");

    // 6. Test screwdriver (screwd) on switch2 with use2: use2 screwd switch2 (param=screwd, target=switch2)
    $screwd = $w3Sec39->contents['screwd'] ?? null;
    $switch2 = $w3Sec39->contents['switch2'] ?? null;
    $tele1 = $w3Sec39->contents['tele1'] ?? null;
    assertTest($screwd instanceof \Dimx\Item, "Item screwd exists");
    assertTest($switch2 instanceof \Dimx\Item, "Item switch2 exists");
    assertTest((int)$switch2->getProperty('broken') === 1, "switch2 is initially broken (broken=1)");
    $screwd->move($player39);
    $player39->move($tele1);
    $eng39->executeCommandAction($w3Sec39, $player39, 'use2', 'screwd', 'switch2');
    $screwdMsgs = implode(' ', $player39->drainConsole());
    assertTest(str_contains($screwdMsgs, "Hmmmm.... I seem to have fixed it."), "Using screwd on switch2 outputs event message (got: '{$screwdMsgs}')");
    assertTest(str_contains($screwdMsgs, "Done!"), "Using screwd on switch2 outputs 'Done!' (got: '{$screwdMsgs}')");
    assertTest((int)$switch2->getProperty('broken') === 0, "switch2 is fixed after use2 screwd switch2 (broken=0)");
}

// ---------------------------------------------------------------------------
// Section 40: Testing VEHICLE Support & Onscreen Display/Click in bungalowStanza
// ---------------------------------------------------------------------------
if (shouldRun(40)) {
    echo "\n--- Section 40: Testing VEHICLE Support & Onscreen Display/Click in bungalowStanza (world3net tele1 & tele2) ---\n";

    @$pm->resetWorld('w3_sec40_test');
    $eng40 = new \Dimx\GameEngine($pm, 'w3_sec40_test', __DIR__ . '/world3net.dxw');
    $w3Sec40 = $eng40->loadOrSeedWorld();

    // 1. Verify tele1 definition and Vehicle properties
    $tele1 = $w3Sec40->getItem('tele1');
    assertTest($tele1 !== null, "world3net defines vehicle 'tele1'");
    assertTest($tele1 instanceof \Dimx\Vehicle, "tele1 is an instance of Dimx\\Vehicle");
    assertTest($tele1 instanceof \Dimx\Item, "tele1 is an instance of Dimx\\Item");
    assertTest($tele1->isVehicle(), "tele1->isVehicle() returns true");
    assertTest($tele1->name === 'the teletransporter', "tele1 has name 'the teletransporter' (got: '{$tele1->name}')");
    assertTest($tele1->capacity === 19, "tele1 has CAPACITY 19 (got: {$tele1->capacity})");
    assertTest(!$tele1->hideable, "tele1 has hideable=0 (hideable=false)");
    assertTest($tele1->showmode === \Dimx\AdvObject::SHOW_ONSCREEN, "tele1 has SHOW ONSCREEN");
    assertTest($tele1->showX === 260 && $tele1->showY === 5, "tele1 has showX=260, showY=5 (got: {$tele1->showX}, {$tele1->showY})");
    assertTest($tele1->images['N']?->width === 40 && $tele1->images['N']?->height === 150, "tele1 has 40x150 image");
    assertTest($tele1->innerImage !== null, "tele1 has innerImage parsed");
    assertTest($tele1->innerImage?->width === 350 && $tele1->innerImage?->height === 235, "tele1 innerImage has 350x235 dimensions");

    // 2. Verify tele2 in pianetaAlieno
    $tele2 = $w3Sec40->getItem('tele2');
    assertTest($tele2 !== null, "world3net defines vehicle 'tele2'");
    assertTest($tele2 instanceof \Dimx\Vehicle, "tele2 is an instance of Dimx\\Vehicle");
    assertTest($tele2->container?->id === 'pianetaAlieno', "tele2 is positioned in 'pianetaAlieno'");

    // 3. Verify bungalowStanza contains tele1
    $bungalowStanza = $w3Sec40->getRoom('bungalowStanza');
    assertTest($bungalowStanza !== null, "Room 'bungalowStanza' exists");
    $roomItems = $bungalowStanza->getItems();
    assertTest(isset($roomItems['tele1']), "bungalowStanza->getItems() includes 'tele1'");
    $roomVehicles = $bungalowStanza->getVehicles();
    assertTest(isset($roomVehicles['tele1']), "bungalowStanza->getVehicles() includes 'tele1'");

    // 4. Entering bungalowStanza with a player renders tele1 onscreen and clickable
    $player40 = new \Dimx\Player($w3Sec40, 'VehicleExplorer', 'p_veh_40');
    $w3Sec40->addPlayer($player40);
    $player40->move($bungalowStanza);
    assertTest($player40->getRoom()?->id === 'bungalowStanza', "Player entered 'bungalowStanza'");

    $ui40 = new \Dimx\UIhtml($player40);
    ob_start();
    $ui40->render(['world' => $w3Sec40, 'player' => $player40]);
    $htmlOutput = ob_get_clean();

    assertTest(str_contains($htmlOutput, 'data-obj-id="tele1"'), "Rendered HTML contains tele1 (data-obj-id='tele1')");
    assertTest(str_contains($htmlOutput, 'lampadastyle.gif'), "Rendered HTML contains tele1 image 'lampadastyle.gif'");
    assertTest(str_contains($htmlOutput, 'onclick="clickObject(this)"'), "Rendered HTML contains clickable handler onclick='clickObject(this)'");

    // Match onscreen element attributes for tele1
    $hasOnscreenTele = preg_match('/<img[^>]+class="[^"]*dimx-onscreen-obj[^"]*"[^>]+data-obj-id="tele1"[^>]*onclick="clickObject\(this\)"/i', $htmlOutput);
    assertTest((bool)$hasOnscreenTele, "tele1 is rendered as an interactive on-screen object with dimx-onscreen-obj class and clickObject");

    // 5. Test clicking tele1 (inspects vehicle without revealing its inner contents from outside)
    $player40->drainConsole();
    $eng40->executeCommandAction($w3Sec40, $player40, 'click', '', 'tele1');
    $clickMsgs = implode(' ', $player40->drainConsole());
    assertTest(str_contains($clickMsgs, "It looks like one of those things in Star Trek."), "Clicking tele1 displays description (got: '{$clickMsgs}')");
    assertTest(!str_contains($clickMsgs, "a switch"), "Clicking tele1 from outside does NOT reveal inner object 'a switch'");
    assertTest(!str_contains($clickMsgs, "Containing:"), "Clicking tele1 from outside does NOT show 'Containing:'");

    // 6. Test looking at tele1 (inspects vehicle without revealing its inner contents from outside)
    $player40->drainConsole();
    $eng40->executeCommandAction($w3Sec40, $player40, 'look', 'tele1', '');
    $lookMsgs = implode(' ', $player40->drainConsole());
    assertTest(str_contains($lookMsgs, "It looks like one of those things in Star Trek."), "Looking at tele1 displays description (got: '{$lookMsgs}')");
    assertTest(!str_contains($lookMsgs, "a switch"), "Looking at tele1 from outside does NOT reveal inner object 'a switch'");
    assertTest(!str_contains($lookMsgs, "Containing:"), "Looking at tele1 from outside does NOT show 'Containing:'");

    // 7. Verify default panel includes enter and exit buttons specifically for vehicles
    $defPanel = \Dimx\Panel::createDefaultPanel();
    assertTest($defPanel->getButton('enter') !== null, "Default panel includes 'enter' button");
    assertTest($defPanel->getButton('exit') !== null, "Default panel includes 'exit' button");
    assertTest($defPanel->getButton('enter')?->eventModel === 'O', "Button 'enter' has event model 'O'");
    assertTest($defPanel->getButton('exit')?->eventModel === '', "Button 'exit' has event model ''");
    assertTest($defPanel->isCommandAllowed('enter'), "Default panel permits 'enter' command");
    assertTest($defPanel->isCommandAllowed('exit'), "Default panel permits 'exit' command");

    // 8. Attempting to enter a non-vehicle item (e.g. nonVehicleCrate) fails
    $nonVehicleCrate = new \Dimx\Item($w3Sec40, 'a wooden crate', 'test_crate');
    $nonVehicleCrate->move($bungalowStanza);
    $player40->drainConsole();
    $eng40->executeCommandAction($w3Sec40, $player40, 'enter', 'test_crate', '');
    $enterFailMsgs = implode(' ', $player40->drainConsole());
    assertTest(str_contains($enterFailMsgs, "You can only enter vehicles."), "Attempting to enter non-vehicle item outputs 'You can only enter vehicles.' (got: '{$enterFailMsgs}')");
    assertTest($player40->container === $bungalowStanza, "Player remains in bungalowStanza when enter fails");

    // 9. Entering vehicle tele1
    $player40->drainConsole();
    $eng40->executeCommandAction($w3Sec40, $player40, 'enter', 'tele1', '');
    $enterMsgs = implode(' ', $player40->drainConsole());
    assertTest(str_contains($enterMsgs, "You enter the teletransporter."), "Entering tele1 outputs confirmation (got: '{$enterMsgs}')");
    assertTest($player40->container === $tele1, "Player's container is now tele1");
    assertTest($player40->getRoom() === $bungalowStanza, "Player's enclosing room is still bungalowStanza");

    // 10. Render UIhtml while inside vehicle tele1
    ob_start();
    $ui40->render(['world' => $w3Sec40, 'player' => $player40]);
    $inVehicleHtml = ob_get_clean();

    assertTest(str_contains($inVehicleHtml, 'class="vehicle_interiors"'), "While in vehicle, UIhtml renders vehicle_interiors overlay");
    assertTest(str_contains($inVehicleHtml, 'fadewhite.gif'), "Vehicle interior overlay contains innerImage 'fadewhite.gif'");
    // The vehicle itself is not drawn on the scene floor while inside it
    assertTest(!str_contains($inVehicleHtml, 'lampadastyle.gif'), "Outer sprite 'lampadastyle.gif' is excluded from scene while inside the vehicle");
    // Inner item switch2 is rendered in UIhtml
    assertTest(str_contains($inVehicleHtml, 'data-obj-id="switch2"'), "While in vehicle, inner item 'switch2' is rendered in UIhtml");
    assertTest(str_contains($inVehicleHtml, 'interruttore.gif'), "Inner item switch2 image 'interruttore.gif' is rendered");
    assertTest(str_contains($inVehicleHtml, 'icoInterruttore.gif'), "Inner item switch2 icon 'icoInterruttore.gif' is rendered in Dashboard (#dashboard)");
    assertTest(str_contains($inVehicleHtml, 'the teletransporter'), "Title bar shows vehicle name 'the teletransporter'");
    assertTest(str_contains($inVehicleHtml, 'No visible exits.'), "No visible exits shown while inside vehicle");

    // 10b. Test interacting with switch2 while inside vehicle tele1
    $player40->drainConsole();
    $eng40->executeCommandAction($w3Sec40, $player40, 'look', 'switch2', '');
    $lookSwitchMsgs = implode(' ', $player40->drainConsole());
    assertTest(str_contains($lookSwitchMsgs, "It is a switch"), "Looking at switch2 inside vehicle displays description (got: '{$lookSwitchMsgs}')");

    // Try using switch2 while broken
    $player40->drainConsole();
    $eng40->executeCommandAction($w3Sec40, $player40, 'use', 'switch2', '');
    $useSwitchMsgs = implode(' ', $player40->drainConsole());
    assertTest(str_contains($useSwitchMsgs, "The main control seems to be broken."), "Using broken switch2 outputs error prompt (got: '{$useSwitchMsgs}')");

    // Give player the screwdriver and fix switch2 using use2 screwd switch2
    $screwd = $w3Sec40->getItem('screwd');
    assertTest($screwd !== null, "world3net item 'screwd' exists");
    $screwd->move($player40);
    assertTest(isset($player40->contents['screwd']), "Player carries screwdriver");

    $player40->drainConsole();
    $eng40->executeCommandAction($w3Sec40, $player40, 'use2', 'screwd', 'switch2');
    $fixMsgs = implode(' ', $player40->drainConsole());
    assertTest(str_contains($fixMsgs, "Hmmmm.... I seem to have fixed it."), "Using screwd on switch2 fixes it (got: '{$fixMsgs}')");
    $switch2Obj = $w3Sec40->getItem('switch2');
    assertTest((int)$switch2Obj->getProperty('broken') === 0, "switch2.broken is now 0");

    // 11. Exiting vehicle tele1
    $player40->drainConsole();
    $eng40->executeCommandAction($w3Sec40, $player40, 'exit', '', '');
    $exitMsgs = implode(' ', $player40->drainConsole());
    assertTest(str_contains($exitMsgs, "You exit the teletransporter."), "Exiting tele1 outputs confirmation (got: '{$exitMsgs}')");
    assertTest($player40->container === $bungalowStanza, "Player's container is restored to bungalowStanza");

    // 12. Attempting to exit when not in a vehicle
    $player40->drainConsole();
    $eng40->executeCommandAction($w3Sec40, $player40, 'exit', '', '');
    $exitFailMsgs = implode(' ', $player40->drainConsole());
    assertTest(str_contains($exitFailMsgs, "You are not inside any vehicle."), "Attempting to exit when not in vehicle outputs 'You are not inside any vehicle.' (got: '{$exitFailMsgs}')");

    // 13. Re-enter vehicle tele1 and activate fixed switch2 (teleports player to alien planet)
    $player40->drainConsole();
    $eng40->executeCommandAction($w3Sec40, $player40, 'enter', 'tele1', '');
    assertTest($player40->container === $tele1, "Player re-entered tele1");

    $player40->drainConsole();
    $eng40->executeCommandAction($w3Sec40, $player40, 'use', 'switch2', '');
    $useFixedMsgs = implode(' ', $player40->drainConsole());
    assertTest(str_contains($useFixedMsgs, "ssWWWWzzzzsssss"), "Using fixed switch2 activates teleporter (got: '{$useFixedMsgs}')");
    assertTest($player40->container?->id === 'pianeta', "Teleporter moved player to alien planet 'pianeta' (got: '{$player40->container?->id}')");
    $eng40->executeCommandAction($w3Sec40, $player40, 'exit', '', '');
    $exitFailMsgs = implode(' ', $player40->drainConsole());
    assertTest(str_contains($exitFailMsgs, "You are not inside any vehicle."), "Attempting to exit when not in vehicle outputs 'You are not inside any vehicle.' (got: '{$exitFailMsgs}')");

    @$pm->resetWorld('w3_sec40_test');
}

// -----------------------------------------------------------------------------
// Section 41: Testing System Snapshot (htmlAdminSnapshot) & maint.php Snapshot Action
// -----------------------------------------------------------------------------
if (shouldRun(41)) {
    echo "\n--- Section 41: Testing System Snapshot (htmlAdminSnapshot) & maint.php Snapshot Action ---\n";

    // 1. Verify Engine Version constant
    assertTest(defined('\Dimx\GameEngine::ENGINE_VERSION'), "GameEngine::ENGINE_VERSION constant is defined");
    assertTest(!empty(\Dimx\GameEngine::ENGINE_VERSION), "GameEngine::ENGINE_VERSION is non-empty (got: '" . \Dimx\GameEngine::ENGINE_VERSION . "')");

    // 2. Load world3net.dxw and verify entity counts
    $eng41 = new \Dimx\GameEngine($pm, 'w3_sec41_test', __DIR__ . '/world3net.dxw');
    $w3Sec41 = $eng41->loadOrSeedWorld();

    assertTest(count($w3Sec41->rooms) === 28, "world3net has 28 rooms (got: " . count($w3Sec41->rooms) . ")");
    assertTest(count($w3Sec41->characters) === 10, "world3net has 10 characters (got: " . count($w3Sec41->characters) . ")");
    assertTest(count($w3Sec41->links) === 34, "world3net has 34 links (got: " . count($w3Sec41->links) . ")");
    assertTest(count($w3Sec41->items) === 41, "world3net has 41 items (got: " . count($w3Sec41->items) . ")");
    assertTest(count($w3Sec41->events) === 46, "world3net has 46 events (got: " . count($w3Sec41->events) . ")");
    assertTest(count($w3Sec41->panels) === 2, "world3net has 2 panels (got: " . count($w3Sec41->panels) . ")");

    // 3. Test htmlAdminSnapshot() structure
    $snapshot = $w3Sec41->htmlAdminSnapshot();
    assertTest(str_contains($snapshot, 'id="top"'), "Snapshot contains top anchor #top");
    assertTest(str_contains($snapshot, 'Engine version: ' . \Dimx\GameEngine::ENGINE_VERSION), "Snapshot contains Engine version");
    assertTest(str_contains($snapshot, 'World: The Beach v.2.30'), "Snapshot contains World title and version ('The Beach v.2.30')");
    assertTest(str_contains($snapshot, 'Images folder: '), "Snapshot contains Images folder line");
    assertTest(str_contains($snapshot, 'Server time is now: '), "Snapshot contains Server time line");
    assertTest(str_contains($snapshot, 'Players: (none)'), "Snapshot displays Players: (none) when no players are connected");

    // 4. Verify summary links to all sections
    assertTest(str_contains($snapshot, '<a href="#rooms">28 rooms</a>'), "Summary links to #rooms (28 rooms)");
    assertTest(str_contains($snapshot, '<a href="#characters">10 characters</a>'), "Summary links to #characters (10 characters)");
    assertTest(str_contains($snapshot, '<a href="#events">(46) events</a>'), "Summary links to #events ((46) events)");
    assertTest(str_contains($snapshot, '<a href="#ways">34 links</a>'), "Summary links to #ways (34 links)");
    assertTest(str_contains($snapshot, '<a href="#items">41 items</a>'), "Summary links to #items (41 items)");
    assertTest(str_contains($snapshot, '<a href="#properties">World properties</a>'), "Summary links to #properties (World properties)");
    assertTest(str_contains($snapshot, '<a href="#panels">2 panels</a>'), "Summary links to #panels (2 panels)");

    // 5. Verify section headers and anchors
    assertTest(str_contains($snapshot, 'id="rooms"') && str_contains($snapshot, '<h1>28 rooms</h1>'), "Snapshot has #rooms section header");
    assertTest(str_contains($snapshot, 'id="characters"') && str_contains($snapshot, '<h1>10 characters</h1>'), "Snapshot has #characters section header");
    assertTest(str_contains($snapshot, 'id="events"') && str_contains($snapshot, '<h1>(46) events</h1>'), "Snapshot has #events section header");
    assertTest(str_contains($snapshot, 'id="ways"') && str_contains($snapshot, '<h1>34 links</h1>'), "Snapshot has #ways section header");
    assertTest(str_contains($snapshot, 'id="items"') && str_contains($snapshot, '<h1>Items</h1>'), "Snapshot has #items section header");
    assertTest(str_contains($snapshot, 'Items count=41'), "Snapshot items section includes 'Items count=41'");
    assertTest(str_contains($snapshot, 'id="properties"') && str_contains($snapshot, '<h1>World properties</h1>'), "Snapshot has #properties section header");
    assertTest(str_contains($snapshot, 'id="panels"') && str_contains($snapshot, '<h1>2 panels</h1>'), "Snapshot has #panels section header");

    // 6. Verify inner properties and formatting
    assertTest(str_contains($snapshot, 'parcoLunetta (The park)'), "Snapshot lists room parcoLunetta");
    assertTest(str_contains($snapshot, 'surfer (a surfer)'), "Snapshot lists character surfer");
    assertTest(str_contains($snapshot, 'w6 (') && str_contains($snapshot, 'bar-- D'), "Snapshot lists link w6 with connections");
    assertTest(str_contains($snapshot, 'a can') && str_contains($snapshot, 'in <a href="#barman">barman (the barman)</a>'), "Snapshot lists contained item in its container");
    assertTest(str_contains($snapshot, 'default: look, click, go'), "Snapshot lists default panel with commands");
    assertTest(str_contains($snapshot, 'chat: look, click, use'), "Snapshot lists chat panel with commands");
    assertTest(str_contains($snapshot, '<a href="#top">Summary</a>'), "Snapshot includes 'Summary' back links");

    // 7. Verify player list updates when a player connects
    $player41 = new \Dimx\Player($w3Sec41, 'SnapshotPlayer', 'p_snap_41');
    $w3Sec41->addPlayer($player41);
    $snapshotWithPlayer = $w3Sec41->htmlAdminSnapshot();
    assertTest(str_contains($snapshotWithPlayer, '<a href="#p_snap_41">p_snap_41 (SnapshotPlayer)</a>'), "Snapshot summary lists connected player link");
    assertTest(str_contains($snapshotWithPlayer, 'p_snap_41 (SnapshotPlayer)'), "Player is listed under characters section");
    assertTest(str_contains($snapshotWithPlayer, '0 messages'), "Player displays message count");
    $w3Sec41->removePlayer($player41);

    // 8. Testing maint.php integration
    // 8a. Access denied without password
    unset($_SESSION['adminPasswd']);
    $_POST = [];
    $_GET  = ['action' => 'snapshot'];
    ob_start();
    require __DIR__ . '/maint.php';
    $maintDenyNoPwd = ob_get_clean();
    assertTest(str_contains($maintDenyNoPwd, "Access denied: incorrect or missing admin password."), "maint.php denies snapshot without admin password");

    // 8b. Access denied with wrong password
    $_POST = [];
    $_GET  = ['action' => 'snapshot', 'adminPasswd' => 'wrong_password_xyz'];
    ob_start();
    require __DIR__ . '/maint.php';
    $maintDenyWrongPwd = ob_get_clean();
    assertTest(str_contains($maintDenyWrongPwd, "Access denied: incorrect or missing admin password."), "maint.php denies snapshot with wrong password");

    // 8c. Snapshot rendered with valid admin password
    $adminPwd = (string)($config['adminPasswd'] ?? 'admin123');
    $_POST = [];
    $_GET  = ['action' => 'snapshot', 'adminPasswd' => $adminPwd];
    ob_start();
    require __DIR__ . '/maint.php';
    $maintSnapshot = ob_get_clean();
    assertTest(str_contains($maintSnapshot, '<!DOCTYPE html>'), "maint.php snapshot outputs valid HTML document");
    assertTest(str_contains($maintSnapshot, '<title>The Beach - Snapshot</title>'), "maint.php snapshot includes page title");
    assertTest(str_contains($maintSnapshot, 'snapshot-container'), "maint.php snapshot includes snapshot container");
    assertTest(str_contains($maintSnapshot, '28 rooms'), "maint.php snapshot contains 28 rooms");
    assertTest(str_contains($maintSnapshot, '10 characters'), "maint.php snapshot contains 10 characters");

    // 8d. maint.php main page includes System Snapshot card and button
    $_POST = [];
    $_GET  = [];
    ob_start();
    require __DIR__ . '/maint.php';
    $maintMain = ob_get_clean();
    assertTest(str_contains($maintMain, '📸 System Snapshot'), "maint.php console includes System Snapshot card");
    assertTest(str_contains($maintMain, 'value="snapshot"'), "maint.php console includes form with action=snapshot");
    assertTest(str_contains($maintMain, 'Generate System Snapshot'), "maint.php console includes 'Generate System Snapshot' button");

    @$pm->resetWorld('w3_sec41_test');
}

// -----------------------------------------------------------------------------
// Section 42: Testing SKINS Resolution (IMAGESFOLDER / baseUrl), No Hardcoded Localhost
// -----------------------------------------------------------------------------
if (shouldRun(42)) {
    echo "\n--- Section 42: Testing SKINS Resolution (IMAGESFOLDER / baseUrl) & No Hardcoded Localhost ---\n";

    // 1. Loading world3net with production baseUrl resolves IMAGESFOLDER_PUBLIC
    $prodLoader = new \Dimx\DxwLoader('https://test.dimensionex.net');
    $w3Prod = $prodLoader->load(__DIR__ . '/world3net.dxw');

    assertTest($w3Prod->imagesFolder === 'https://www.dimensionex.net/pics/', "world3net imagesFolder resolved to IMAGESFOLDER_PUBLIC ('https://www.dimensionex.net/pics/')");
    assertTest(!str_contains($w3Prod->imagesFolder, 'localhost'), "world3net imagesFolder does NOT contain 'localhost'");

    // 2. Default skin folder resolution
    assertTest($w3Prod->defaultSkin !== null, "world3net defaultSkin is defined");
    assertTest($w3Prod->defaultSkin->skinFolder === 'https://www.dimensionex.net/skins/default/', "defaultSkin folder resolved to 'https://www.dimensionex.net/skins/default/' (got: '{$w3Prod->defaultSkin->skinFolder}')");
    assertTest(!str_contains($w3Prod->defaultSkin->skinFolder, 'localhost'), "defaultSkin folder does NOT contain 'localhost'");

    // 3. Arrow icons in default skin
    $northIcon = $w3Prod->defaultSkin->getWayIcon('N');
    assertTest($northIcon === 'https://www.dimensionex.net/skins/default/icowayn.gif', "defaultSkin getWayIcon('N') is 'https://www.dimensionex.net/skins/default/icowayn.gif' (got: '{$northIcon}')");
    assertTest(!str_contains($northIcon, 'localhost'), "North arrow icon does NOT contain 'localhost'");

    $southIcon = $w3Prod->defaultSkin->getWayIcon('S');
    assertTest($southIcon === 'https://www.dimensionex.net/skins/default/icoways.gif', "defaultSkin getWayIcon('S') is 'https://www.dimensionex.net/skins/default/icoways.gif'");

    $spacerIcon = $w3Prod->defaultSkin->getSpacerIcon();
    assertTest(str_contains($spacerIcon, 'blank.gif'), "defaultSkin spacer icon references 'blank.gif'");
    assertTest(!str_contains($spacerIcon, 'localhost'), "Spacer icon does NOT contain 'localhost'");

    // 4. Other declared skins in world3net (aqua, forest)
    assertTest(isset($w3Prod->skins['aqua']), "world3net has 'aqua' skin");
    assertTest($w3Prod->skins['aqua']->skinFolder === 'https://www.dimensionex.net/skins/aqua/', "aqua skinFolder is 'https://www.dimensionex.net/skins/aqua/'");
    assertTest(!str_contains($w3Prod->skins['aqua']->skinFolder, 'localhost'), "aqua skinFolder does NOT contain 'localhost'");

    assertTest(isset($w3Prod->skins['forest']), "world3net has 'forest' skin");
    assertTest($w3Prod->skins['forest']->skinFolder === 'https://www.dimensionex.net/skins/forest/', "forest skinFolder is 'https://www.dimensionex.net/skins/forest/'");
    assertTest(!str_contains($w3Prod->skins['forest']->skinFolder, 'localhost'), "forest skinFolder does NOT contain 'localhost'");

    // 5. Test resolution when IMAGESFOLDER is empty, but baseUrl is specified
    $customLoader = new \Dimx\DxwLoader('https://test.dimensionex.net');
    $customSkinFolder = $customLoader->resolveSkinFolderUrl('default', '');
    assertTest($customSkinFolder === 'https://test.dimensionex.net/skins/default/', "When imagesFolder is empty, skin resolves against baseUrl ('https://test.dimensionex.net/skins/default/')");

    // 6. Test resolution when IMAGESFOLDER is relative (/dimx/pics/)
    $relSkinFolder = $customLoader->resolveSkinFolderUrl('default', '/dimx/pics/');
    assertTest($relSkinFolder === 'https://test.dimensionex.net/dimx/skins/default/', "When imagesFolder is '/dimx/pics/', skin resolves to 'https://test.dimensionex.net/dimx/skins/default/'");

    // 7. Test resolution when IMAGESFOLDER contains localhost, but baseUrl is a production host
    $overrideSkinFolder = $customLoader->resolveSkinFolderUrl('default', 'https://localhost:8080/dimx/pics/');
    assertTest($overrideSkinFolder === 'https://test.dimensionex.net/skins/default/', "Production baseUrl overrides localhost in imagesFolder");

    // 8. Render UIhtml with a player and verify navpad arrow image URLs
    $p42 = new \Dimx\Player($w3Prod, 'SkinTester', 'p_sk_42');
    $w3Prod->addPlayer($p42);
    $spiaggia2 = $w3Prod->getRoom('spiaggia2');
    $p42->move($spiaggia2);

    $ui42 = new \Dimx\UIhtml($p42);
    ob_start();
    $ui42->render(['world' => $w3Prod, 'player' => $p42]);
    $html42 = ob_get_clean();

    assertTest(str_contains($html42, 'https://www.dimensionex.net/skins/default/icowayn.gif'), "Rendered navpad contains public North arrow URL 'https://www.dimensionex.net/skins/default/icowayn.gif'");
    assertTest(!str_contains($html42, 'localhost/dimx/skins/default/'), "Rendered HTML contains NO references to 'localhost/dimx/skins/default/'");
}

// ---------------------------------------------------------------------------
// Section 43: Testing Default Item Icon (ICOITEM) & Fallbacks in UIhtml and Skin
// ---------------------------------------------------------------------------
if (shouldRun(43)) {
    echo "\n--- Section 43: Testing Default Item Icon (ICOITEM) & Fallbacks in UIhtml and Skin ---\n";

    $defaultSkin = new \Dimx\Skin('default', 'Default', 'https://www.dimensionex.net/skins/default/');
    assertTest($defaultSkin->getItemIcon() === 'https://www.dimensionex.net/skins/default/icoitem.gif', "Skin getItemIcon() returns default icoitem.gif URL (got: '{$defaultSkin->getItemIcon()}')");
    assertTest($defaultSkin->getCharacterIcon() === 'https://www.dimensionex.net/skins/default/icouser.gif', "Skin getCharacterIcon() returns default icouser.gif URL (got: '{$defaultSkin->getCharacterIcon()}')");
    assertTest($defaultSkin->getPlayerIcon() === 'https://www.dimensionex.net/skins/default/icoPlayer.gif', "Skin getPlayerIcon() returns default icoPlayer.gif URL (got: '{$defaultSkin->getPlayerIcon()}')");

    // Load world3net
    $dxwLoader = new \Dimx\DxwLoader('https://test.dimensionex.net');
    $w43 = $dxwLoader->load(__DIR__ . '/../docs/dxw-examples/world3net.dxw');

    // Create a player and room
    $p43 = new \Dimx\Player($w43, 'IconTester', 'p_ico_43');
    $w43->addPlayer($p43);
    $testRoom = $w43->getRoom('spiaggia2');
    $p43->move($testRoom);

    // Create an item with NO custom icon defined
    $itemWithoutIcon = new \Dimx\Item($w43, 'Plain Stone', 'plainstone');
    $itemWithoutIcon->icon = null;
    $itemWithoutIcon->move($testRoom);

    // Create an item in player inventory with NO custom icon defined
    $invItemWithoutIcon = new \Dimx\Item($w43, 'Plain Twig', 'plaintwig');
    $invItemWithoutIcon->icon = null;
    $invItemWithoutIcon->move($p43);

    // Render UIhtml
    $ui43 = new \Dimx\UIhtml($p43);
    ob_start();
    $ui43->render(['world' => $w43, 'player' => $p43]);
    $html43 = ob_get_clean();

    // Verify room items list renders skin's ICOITEM for Plain Stone
    $expectedItemIcon = $w43->skins['default']->getItemIcon();
    assertTest(
        str_contains($html43, $expectedItemIcon),
        "UIhtml renders Skin ICOITEM icon URL ('{$expectedItemIcon}')"
    );
    assertTest(
        str_contains($html43, 'alt="Plain Stone"'),
        "UIhtml room items list includes Plain Stone with alt attribute"
    );
    assertTest(
        str_contains($html43, 'alt="Plain Twig"'),
        "UIhtml inventory list includes Plain Twig with alt attribute"
    );
    // Verify room items and inventory do NOT use placeholder emoji for items
    assertTest(
        !str_contains($html43, '<span>📦</span>'),
        "UIhtml does NOT render raw '<span>📦</span>' placeholder when skin default item icon is available"
    );

    // Verify container "Containing:" listing uses skin item icon
    $box = new \Dimx\Item($w43, 'Wooden Box', 'woodenbox');
    $box->openable = true;
    $box->open = true;
    $box->move($testRoom);
    $innerItem = new \Dimx\Item($w43, 'Secret Gem', 'secretgem');
    $innerItem->icon = null;
    $innerItem->move($box);

    $engine43 = new \Dimx\GameEngine($pm);
    $engine43->executeCommandAction($w43, $p43, 'look', 'woodenbox', '');
    $lookMsgs = implode(' ', $p43->drainConsole());
    assertTest(
        str_contains($lookMsgs, $expectedItemIcon),
        "Look command 'Containing:' list displays skin default ICOITEM image for item without icon ('{$expectedItemIcon}')"
    );
}

// ---------------------------------------------------------------------------
// Section 44: Testing SKINFOLDER Resolution, aqua_net.dxs URL Loading, Login Skin Dropdown & Per-Player Skin Navpad Icons
// ---------------------------------------------------------------------------
if (shouldRun(44)) {
    echo "\n--- Section 44: Testing SKINFOLDER Resolution, aqua_net.dxs URL Loading, Login Skin Dropdown & Per-Player Skin Navpad Icons ---\n";

    // 1. Load world3net_aquanet.dxw (which specifies SKINS https://www.dimensionex.net/skins/aqua/aqua_net.dxs,forest.dxs,default)
    $loader44 = new \Dimx\DxwLoader('https://test.dimensionex.net');
    $w44 = $loader44->load(__DIR__ . '/world3net_aquanet.dxw');

    assertTest(isset($w44->skins['aqua']), "world3net_aquanet.dxw loaded 'aqua' skin from https://www.dimensionex.net/skins/aqua/aqua_net.dxs");
    assertTest(isset($w44->skins['forest']), "world3net_aquanet.dxw loaded 'forest' skin from forest.dxs");
    assertTest(isset($w44->skins['default']), "world3net_aquanet.dxw loaded 'default' skin");

    $aquaSkin    = $w44->skins['aqua'];
    $forestSkin  = $w44->skins['forest'];
    $defaultSkin = $w44->skins['default'];

    // Verify skin IDs and names
    assertTest($aquaSkin->id === 'aqua' && $aquaSkin->name === 'Aqua', "aqua_net.dxs parsed SKIN id='aqua' and NAME='Aqua' (got id='{$aquaSkin->id}', name='{$aquaSkin->name}')");
    assertTest($forestSkin->id === 'forest' && $forestSkin->name === 'Forest', "forest.dxs parsed SKIN id='forest' and NAME='Forest' (got id='{$forestSkin->id}', name='{$forestSkin->name}')");
    assertTest($defaultSkin->id === 'default' && $defaultSkin->name === 'Default', "default skin has id='default' and name='Default'");

    // Verify SKINFOLDER determination rule:
    // - aqua_net.dxs explicitly defines SKINFOLDER http://www.dimensionex.net/skins/aqua/
    assertTest(
        $aquaSkin->hasExplicitSkinFolder === true && $aquaSkin->skinFolder === 'http://www.dimensionex.net/skins/aqua/',
        "aqua_net.dxs uses its explicit SKINFOLDER ('http://www.dimensionex.net/skins/aqua/', got '{$aquaSkin->skinFolder}')"
    );
    // - forest.dxs has no SKINFOLDER, so it uses default calculation from world IMAGESFOLDER (https://www.dimensionex.net/pics/ -> https://www.dimensionex.net/skins/forest/)
    assertTest(
        $forestSkin->hasExplicitSkinFolder === false && $forestSkin->skinFolder === 'https://www.dimensionex.net/skins/forest/',
        "forest.dxs (no SKINFOLDER in .dxs) calculates SKINFOLDER from world IMAGESFOLDER ('https://www.dimensionex.net/skins/forest/', got '{$forestSkin->skinFolder}')"
    );
    // - default skin has no .dxs file, uses default calculation from world IMAGESFOLDER (https://www.dimensionex.net/skins/default/)
    assertTest(
        $defaultSkin->hasExplicitSkinFolder === false && $defaultSkin->skinFolder === 'https://www.dimensionex.net/skins/default/',
        "default skin calculates SKINFOLDER from world IMAGESFOLDER ('https://www.dimensionex.net/skins/default/', got '{$defaultSkin->skinFolder}')"
    );

    // Verify Skin isolation & directional icon URLs per Skin instance
    assertTest($aquaSkin->getWayIcon('N') === 'http://www.dimensionex.net/skins/aqua/north.gif', "Aqua skin North icon is 'http://www.dimensionex.net/skins/aqua/north.gif'");
    assertTest($aquaSkin->getWayIcon('S') === 'http://www.dimensionex.net/skins/aqua/south.gif', "Aqua skin South icon is 'http://www.dimensionex.net/skins/aqua/south.gif'");
    assertTest($aquaSkin->getWayIcon('E') === 'http://www.dimensionex.net/skins/aqua/right.gif', "Aqua skin East icon is 'http://www.dimensionex.net/skins/aqua/right.gif'");
    assertTest($aquaSkin->getWayIcon('W') === 'http://www.dimensionex.net/skins/aqua/left.gif', "Aqua skin West icon is 'http://www.dimensionex.net/skins/aqua/left.gif'");

    assertTest($forestSkin->getWayIcon('N') === 'https://www.dimensionex.net/skins/forest/north.gif', "Forest skin North icon is 'https://www.dimensionex.net/skins/forest/north.gif'");
    assertTest($forestSkin->getWayIcon('E') === 'https://www.dimensionex.net/skins/forest/right.gif', "Forest skin East icon is 'https://www.dimensionex.net/skins/forest/right.gif'");

    assertTest($defaultSkin->getWayIcon('N') === 'https://www.dimensionex.net/skins/default/icowayn.gif', "Default skin North icon is 'https://www.dimensionex.net/skins/default/icowayn.gif'");
    assertTest($defaultSkin->getWayIcon('E') === 'https://www.dimensionex.net/skins/default/icowaye.gif', "Default skin East icon is 'https://www.dimensionex.net/skins/default/icowaye.gif'");

    // 2. Test Connect Form Skin Dropdown in UIhtml::renderLogin()
    $sec44WorldId = 'w3_sec44_test';
    @$pm->resetWorld($sec44WorldId);
    $engine44 = new \Dimx\GameEngine($pm, $sec44WorldId, __DIR__ . '/world3net_aquanet.dxw', 'https://test.dimensionex.net');
    $loginUi = new \Dimx\UIhtml(null, $engine44);
    ob_start();
    $loginUi->renderLogin(null, 'TestRunner', 'forest');
    $loginHtml = ob_get_clean();

    assertTest(str_contains($loginHtml, '<select id="skin" name="skin">'), "Connect form includes <select id=\"skin\" name=\"skin\"> dropdown");
    assertTest(str_contains($loginHtml, '<option value="aqua">Aqua</option>'), "Connect form dropdown includes Aqua skin option");
    assertTest(str_contains($loginHtml, '<option value="forest" selected>Forest</option>'), "Connect form dropdown includes Forest skin option (selected)");
    assertTest(str_contains($loginHtml, '<option value="default">Default</option>'), "Connect form dropdown includes Default skin option");

    // 3. Test Per-Player Skin Choice & #navpad Directional Icon Rendering in UIhtml::render()
    // Connect Player 1 choosing 'aqua', Player 2 choosing 'forest', Player 3 choosing 'default'
    $resAqua = $engine44->processCommand('PlayerAqua', 'look', '', '', null, 441, false, true, null, 'aqua');
    $pAqua = $resAqua['player'];
    assertTest($pAqua->skin !== null && $pAqua->skin->id === 'aqua', "PlayerAqua has chosen Skin instance 'aqua' assigned to \$player->skin");

    $resForest = $engine44->processCommand('PlayerForest', 'look', '', '', null, 442, false, true, null, 'forest');
    $pForest = $resForest['player'];
    assertTest($pForest->skin !== null && $pForest->skin->id === 'forest', "PlayerForest has chosen Skin instance 'forest' assigned to \$player->skin");

    $resDefault = $engine44->processCommand('PlayerDefault', 'look', '', '', null, 443, false, true, null, 'default');
    $pDefault = $resDefault['player'];
    assertTest($pDefault->skin !== null && $pDefault->skin->id === 'default', "PlayerDefault has chosen Skin instance 'default' assigned to \$player->skin");

    // Verify PlayerAqua's skin was not overwritten when PlayerForest and PlayerDefault connected
    assertTest($pAqua->skin->id === 'aqua' && $pAqua->skin->skinFolder === 'http://www.dimensionex.net/skins/aqua/', "PlayerAqua's skin remains 'aqua' and untouched by other players' skins");

    // Render #navpad for each player in spiaggia (which has N, W, and E exits)
    $pAqua->move($resAqua['world']->getRoom('spiaggia'));
    $resAqua['room'] = $resAqua['world']->getRoom('spiaggia');
    $uiAqua = new \Dimx\UIhtml($pAqua, $engine44);
    ob_start();
    $uiAqua->render($resAqua);
    $htmlAqua = ob_get_clean();

    $pForest->move($resForest['world']->getRoom('spiaggia'));
    $resForest['room'] = $resForest['world']->getRoom('spiaggia');
    $uiForest = new \Dimx\UIhtml($pForest, $engine44);
    ob_start();
    $uiForest->render($resForest);
    $htmlForest = ob_get_clean();

    $pDefault->move($resDefault['world']->getRoom('spiaggia'));
    $resDefault['room'] = $resDefault['world']->getRoom('spiaggia');
    $uiDefault = new \Dimx\UIhtml($pDefault, $engine44);
    ob_start();
    $uiDefault->render($resDefault);
    $htmlDefault = ob_get_clean();

    // Extract #navpad table from each player's rendered HTML
    $extractNavpad = function (string $html): string {
        $start = strpos($html, '<table id="navpad"');
        if ($start === false) return '';
        $end = strpos($html, '</table>', $start);
        return $end !== false ? substr($html, $start, $end - $start + 8) : '';
    };

    $navpadAqua    = $extractNavpad($htmlAqua);
    $navpadForest  = $extractNavpad($htmlForest);
    $navpadDefault = $extractNavpad($htmlDefault);

    assertTest(
        str_contains($navpadAqua, 'http://www.dimensionex.net/skins/aqua/north.gif') &&
        str_contains($navpadAqua, 'http://www.dimensionex.net/skins/aqua/right.gif') &&
        str_contains($navpadAqua, 'http://www.dimensionex.net/skins/aqua/blank.gif'),
        "PlayerAqua #navpad renders Aqua directional icons (north.gif, right.gif, blank.gif from http://www.dimensionex.net/skins/aqua/)"
    );
    assertTest(
        str_contains($navpadForest, 'https://www.dimensionex.net/skins/forest/north.gif') &&
        str_contains($navpadForest, 'https://www.dimensionex.net/skins/forest/right.gif') &&
        str_contains($navpadForest, 'https://www.dimensionex.net/skins/forest/blank.gif'),
        "PlayerForest #navpad renders Forest directional icons (north.gif, right.gif, blank.gif from https://www.dimensionex.net/skins/forest/)"
    );
    assertTest(
        str_contains($navpadDefault, 'https://www.dimensionex.net/skins/default/icowayn.gif') &&
        str_contains($navpadDefault, 'https://www.dimensionex.net/skins/default/icowaye.gif') &&
        str_contains($navpadDefault, 'https://www.dimensionex.net/skins/default/blank.gif'),
        "PlayerDefault #navpad renders Default directional icons (icowayn.gif, icowaye.gif, blank.gif from https://www.dimensionex.net/skins/default/)"
    );

    // 4. Verify #ctrls PANELBGCOLOR and PANELBACKGROUND per chosen skin
    assertTest(
        $aquaSkin->panelBgColor === 'SILVER' && $aquaSkin->panelBackground === 'http://www.dimensionex.net/skins/aqua/aqua_sea.jpg',
        "Aqua skin parsed PANELBGCOLOR='SILVER' and PANELBACKGROUND='http://www.dimensionex.net/skins/aqua/aqua_sea.jpg'"
    );
    assertTest(
        $forestSkin->panelBgColor === 'SILVER' && $forestSkin->panelBackground === 'https://www.dimensionex.net/skins/forest/vine-001.jpg',
        "Forest skin parsed PANELBGCOLOR='SILVER' and PANELBACKGROUND='https://www.dimensionex.net/skins/forest/vine-001.jpg'"
    );
    assertTest(
        $defaultSkin->panelBgColor === 'SILVER' && $defaultSkin->panelBackground === '',
        "Default skin has PANELBGCOLOR='SILVER' and empty PANELBACKGROUND"
    );

    assertTest(
        str_contains($htmlAqua, '<div id="ctrls" style="background-color:SILVER;background-image:url(\'http://www.dimensionex.net/skins/aqua/aqua_sea.jpg\');">'),
        "PlayerAqua #ctrls div has background-color:SILVER and background-image url('http://www.dimensionex.net/skins/aqua/aqua_sea.jpg')"
    );
    assertTest(
        str_contains($htmlForest, '<div id="ctrls" style="background-color:SILVER;background-image:url(\'https://www.dimensionex.net/skins/forest/vine-001.jpg\');">'),
        "PlayerForest #ctrls div has background-color:SILVER and background-image url('https://www.dimensionex.net/skins/forest/vine-001.jpg')"
    );
    assertTest(
        str_contains($htmlDefault, '<div id="ctrls" style="background-color:SILVER;">'),
        "PlayerDefault #ctrls div has background-color:SILVER and no background-image"
    );

    // 5. Verify #msgs alternating message line colors (LIST1BGCOLOR odd, LIST2BGCOLOR even)
    assertTest(
        $aquaSkin->list1BgColor === '#101040' && $aquaSkin->list2BgColor === '#313161',
        "Aqua skin parsed LIST1BGCOLOR='#101040' and LIST2BGCOLOR='#313161'"
    );
    assertTest(
        $forestSkin->list1BgColor === '#004000' && $forestSkin->list2BgColor === '#316131',
        "Forest skin parsed LIST1BGCOLOR='#004000' and LIST2BGCOLOR='#316131'"
    );
    assertTest(
        $defaultSkin->list1BgColor === '#101040' && $defaultSkin->list2BgColor === '#313161',
        "Default skin has LIST1BGCOLOR='#101040' and LIST2BGCOLOR='#313161'"
    );

    assertTest(
        str_contains($htmlForest, ".chat-line:nth-child(odd) {\r\n              background-color: #004000;") ||
        str_contains($htmlForest, ".chat-line:nth-child(odd) {\n              background-color: #004000;"),
        "PlayerForest #msgs odd message lines use Forest LIST1BGCOLOR (#004000)"
    );
    assertTest(
        str_contains($htmlForest, ".chat-line:nth-child(even) {\r\n              background-color: #316131;") ||
        str_contains($htmlForest, ".chat-line:nth-child(even) {\n              background-color: #316131;"),
        "PlayerForest #msgs even message lines use Forest LIST2BGCOLOR (#316131)"
    );

    // 6. Verify LISTSTYLE on #chatBox inside #msgs
    assertTest(
        $aquaSkin->listStyle === '{font-size:8pt;font-family:Century Gothic;color:#DDDDDD}',
        "Aqua skin parsed LISTSTYLE='{font-size:8pt;font-family:Century Gothic;color:#DDDDDD}' (got '{$aquaSkin->listStyle}')"
    );
    assertTest(
        str_starts_with($forestSkin->listStyle, '{font-size:8pt;font-family:Courier New;color:#DDDDDD}'),
        "Forest skin parsed LISTSTYLE starting with '{font-size:8pt;font-family:Courier New;color:#DDDDDD}' (got '{$forestSkin->listStyle}')"
    );
    assertTest(
        $defaultSkin->listStyle === '{font-size: 8pt;font-family: Tahoma;color:#A0B0C0;}',
        "Default skin has default LISTSTYLE='{font-size: 8pt;font-family: Tahoma;color:#A0B0C0;}'"
    );
    assertTest(
        str_contains($htmlAqua, '#chatBox {font-size:8pt;font-family:Century Gothic;color:#DDDDDD}') &&
        str_contains($htmlAqua, '<div id="chatBox" style="font-size:8pt;font-family:Century Gothic;color:#DDDDDD">'),
        "PlayerAqua #chatBox inside #msgs is styled with Aqua LISTSTYLE"
    );
    assertTest(
        str_contains($htmlForest, '#chatBox {font-size:8pt;font-family:Courier New;color:#DDDDDD}') &&
        str_contains($htmlForest, '<div id="chatBox" style="font-size:8pt;font-family:Courier New;color:#DDDDDD">'),
        "PlayerForest #chatBox inside #msgs is styled with Forest LISTSTYLE"
    );
    assertTest(
        str_contains($htmlDefault, '#chatBox {font-size: 8pt;font-family: Tahoma;color:#A0B0C0;}') &&
        str_contains($htmlDefault, '<div id="chatBox" style="font-size: 8pt;font-family: Tahoma;color:#A0B0C0;">'),
        "PlayerDefault #chatBox inside #msgs is styled with Default LISTSTYLE"
    );

    // 7. Verify PANELSTYLE on #ctrls .dimx-btn
    assertTest(
        $aquaSkin->panelStyle === '{font-size:10pt;font-family:Tahoma;color:#000050}',
        "Aqua skin parsed PANELSTYLE='{font-size:10pt;font-family:Tahoma;color:#000050}' (got '{$aquaSkin->panelStyle}')"
    );
    assertTest(
        $forestSkin->panelStyle === '{font-size:10pt;font-family:Lucida Sans, Lucida Console;color:#FFFFFF};',
        "Forest skin parsed PANELSTYLE='{font-size:10pt;font-family:Lucida Sans, Lucida Console;color:#FFFFFF};' (got '{$forestSkin->panelStyle}')"
    );
    assertTest(
        $defaultSkin->panelStyle === '{font-size: 10pt;font-family: Tahoma;color:#600000;}',
        "Default skin has default PANELSTYLE='{font-size: 10pt;font-family: Tahoma;color:#600000;}'"
    );
    assertTest(
        str_contains($htmlAqua, 'font-size:10pt;font-family:Tahoma;color:#000050;'),
        "PlayerAqua #ctrls .dimx-btn uses Aqua PANELSTYLE (font-size:10pt;font-family:Tahoma;color:#000050;)"
    );
    assertTest(
        str_contains($htmlForest, 'font-size:10pt;font-family:Lucida Sans, Lucida Console;color:#FFFFFF;'),
        "PlayerForest #ctrls .dimx-btn uses Forest PANELSTYLE (font-size:10pt;font-family:Lucida Sans, Lucida Console;color:#FFFFFF;)"
    );
    assertTest(
        str_contains($htmlDefault, 'font-size: 10pt;font-family: Tahoma;color:#600000;'),
        "PlayerDefault #ctrls .dimx-btn uses Default PANELSTYLE (font-size: 10pt;font-family: Tahoma;color:#600000;)"
    );

    // 8. Verify TITLESTYLE with $SKINFOLDER interpolation on #dimx-title-bar
    assertTest(
        $aquaSkin->titleStyle === '{BACKGROUND: #0020A0 url(http://www.dimensionex.net/skins/aqua/gradblu.jpg); COLOR: #FFFFFF; FONT-SIZE: 10pt; FONT-FAMILY: Fixedsys; TEXT-DECORATION: none}',
        "Aqua skin interpolated \$SKINFOLDER in TITLESTYLE ('{$aquaSkin->titleStyle}')"
    );
    assertTest(
        $forestSkin->titleStyle === '{font-size:10pt;font-family:Lucida Sans;font-weight:bold;color:#FFFF20;background-color:#009000}',
        "Forest skin parsed TITLESTYLE ('{$forestSkin->titleStyle}')"
    );
    assertTest(
        str_contains($htmlAqua, 'id="dimx-title-bar"') &&
        str_contains($htmlAqua, 'BACKGROUND: #0020A0 url(http://www.dimensionex.net/skins/aqua/gradblu.jpg); COLOR: #FFFFFF; FONT-SIZE: 10pt; FONT-FAMILY: Fixedsys; TEXT-DECORATION: none'),
        "PlayerAqua #dimx-title-bar is styled with interpolated Aqua TITLESTYLE"
    );
    assertTest(
        str_contains($htmlForest, 'id="dimx-title-bar"') &&
        str_contains($htmlForest, 'font-size:10pt;font-family:Lucida Sans;font-weight:bold;color:#FFFF20;background-color:#009000'),
        "PlayerForest #dimx-title-bar is styled with Forest TITLESTYLE"
    );
    assertTest(
        str_contains($htmlDefault, 'id="dimx-title-bar"') &&
        str_contains($htmlDefault, 'font-size: 10pt;font-family: Tahoma;color:#FFFF10;background-color:#000090'),
        "PlayerDefault #dimx-title-bar is styled with Default TITLESTYLE"
    );

    // 9. Verify BODYBGCOLOR on UIhtml body background property (and BODYBACKGROUND ignored)
    assertTest(
        $aquaSkin->bodyBgColor === '#000030',
        "Aqua skin parsed BODYBGCOLOR='#000030' (got '{$aquaSkin->bodyBgColor}')"
    );
    assertTest(
        $forestSkin->bodyBgColor === '#206020',
        "Forest skin parsed BODYBGCOLOR='#206020' (got '{$forestSkin->bodyBgColor}')"
    );
    assertTest(
        $defaultSkin->bodyBgColor === '#000040',
        "Default skin has default BODYBGCOLOR='#000040' (got '{$defaultSkin->bodyBgColor}')"
    );
    assertTest(
        str_contains($htmlAqua, '<body style="background: #000030;">') && str_contains($htmlAqua, 'background: #000030;'),
        "PlayerAqua UIhtml body background uses Aqua BODYBGCOLOR (#000030)"
    );
    assertTest(
        str_contains($htmlForest, '<body style="background: #206020;">') && str_contains($htmlForest, 'background: #206020;'),
        "PlayerForest UIhtml body background uses Forest BODYBGCOLOR (#206020)"
    );
    assertTest(
        str_contains($htmlDefault, '<body style="background: #000040;">') && str_contains($htmlDefault, 'background: #000040;'),
        "PlayerDefault UIhtml body background uses Default BODYBGCOLOR (#000040)"
    );

    // 10. Verify BODYSTYLE on body, td
    assertTest(
        $aquaSkin->bodyStyle === '{font-size:10pt;font-family:Century Gothic;color:#B0EAFF}',
        "Aqua skin parsed BODYSTYLE='{font-size:10pt;font-family:Century Gothic;color:#B0EAFF}' (got '{$aquaSkin->bodyStyle}')"
    );
    assertTest(
        $forestSkin->bodyStyle === '{font-size:10pt;font-family:Courier New;color:#00FFFF}',
        "Forest skin parsed BODYSTYLE='{font-size:10pt;font-family:Courier New;color:#00FFFF}' (got '{$forestSkin->bodyStyle}')"
    );
    assertTest(
        $defaultSkin->bodyStyle === '{font-size: 10pt;font-family: Tahoma;color:#00FFFF;margin:0;}',
        "Default skin has default BODYSTYLE='{font-size: 10pt;font-family: Tahoma;color:#00FFFF;margin:0;}'"
    );
    assertTest(
        str_contains($htmlAqua, "body, td {\r\n              font-size:10pt;font-family:Century Gothic;color:#B0EAFF;") ||
        str_contains($htmlAqua, "body, td {\n              font-size:10pt;font-family:Century Gothic;color:#B0EAFF;"),
        "PlayerAqua UIhtml body, td uses Aqua BODYSTYLE"
    );
    assertTest(
        str_contains($htmlForest, "body, td {\r\n              font-size:10pt;font-family:Courier New;color:#00FFFF;") ||
        str_contains($htmlForest, "body, td {\n              font-size:10pt;font-family:Courier New;color:#00FFFF;"),
        "PlayerForest UIhtml body, td uses Forest BODYSTYLE"
    );
    assertTest(
        str_contains($htmlDefault, "body, td {\r\n              font-size: 10pt;font-family: Tahoma;color:#00FFFF;margin:0;") ||
        str_contains($htmlDefault, "body, td {\n              font-size: 10pt;font-family: Tahoma;color:#00FFFF;margin:0;"),
        "PlayerDefault UIhtml body, td uses Default BODYSTYLE"
    );

    // 11. Verify BUTTONSTYLE inline style on #ctrls command buttons with $SKINICON interpolation
    assertTest(
        $aquaSkin->buttonStyle === '{background: dimgray $SKINICON no-repeat; text-align: left; padding-left: 17px; color: white; cursor: hand; font-family: Tahoma; font-size: 9pt; height: 21px}',
        "Aqua skin parsed BUTTONSTYLE with \$SKINICON placeholder"
    );
    assertTest(
        $forestSkin->buttonStyle === '{background: #00CC00 $SKINICON no-repeat; text-align: left; padding-left: 17px; color: black; cursor: hand; font-family: Lucida Sans, Lucida Console; font-size: 8pt; height: 24px; border: 2px outset #00CC00;}',
        "Forest skin parsed BUTTONSTYLE with \$SKINICON placeholder"
    );
    assertTest(
        $defaultSkin->buttonStyle === '',
        "Default skin has empty BUTTONSTYLE"
    );
    assertTest(
        str_contains($htmlAqua, 'id="btn_look"') &&
        str_contains($htmlAqua, 'style="background: dimgray url(http://www.dimensionex.net/skins/aqua/panlook.gif) no-repeat; text-align: left; padding-left: 17px; color: white; cursor: hand; font-family: Tahoma; font-size: 9pt; height: 21px"') &&
        str_contains($htmlAqua, 'style="background: dimgray url(http://www.dimensionex.net/skins/aqua/pansay.gif) no-repeat; text-align: left; padding-left: 17px; color: white; cursor: hand; font-family: Tahoma; font-size: 9pt; height: 21px"'),
        "PlayerAqua #ctrls buttons render inline style from BUTTONSTYLE with \$SKINICON replaced by url(http://www.dimensionex.net/skins/aqua/pan<cmd>.gif)"
    );
    assertTest(
        str_contains($htmlForest, 'id="btn_look"') &&
        str_contains($htmlForest, 'style="background: #00CC00 url(https://www.dimensionex.net/skins/forest/panlook.gif) no-repeat; text-align: left; padding-left: 17px; color: black; cursor: hand; font-family: Lucida Sans, Lucida Console; font-size: 8pt; height: 24px; border: 2px outset #00CC00;"') &&
        str_contains($htmlForest, 'style="background: #00CC00 url(https://www.dimensionex.net/skins/forest/pansay.gif) no-repeat; text-align: left; padding-left: 17px; color: black; cursor: hand; font-family: Lucida Sans, Lucida Console; font-size: 8pt; height: 24px; border: 2px outset #00CC00;"'),
        "PlayerForest #ctrls buttons render inline style from BUTTONSTYLE with \$SKINICON replaced by url(https://www.dimensionex.net/skins/forest/pan<cmd>.gif)"
    );
    assertTest(
        str_contains($htmlDefault, '<button type="button" class="dimx-btn" id="btn_look" data-cmd="look" data-model="O" title="Look around or inspect object" onclick="sendCmd(\'look\');">Look</button>'),
        "PlayerDefault #ctrls buttons omit inline style when BUTTONSTYLE is empty"
    );

    @$pm->resetWorld($sec44WorldId);
}

// ---------------------------------------------------------------------------
// 45. Sounds & Music Login Checkboxes, PlaySound Routing, Embedded Player & SNDNEWMSG
// ---------------------------------------------------------------------------
if (shouldRun(45)) {
    echo "\n45. " . $sections[45]['title'] . "\n";

    $sec45WorldId = 'w3_sec45_sounds';
    $sec45WorldFile = __DIR__ . '/world3net_aquanet.dxw';
    @$pm->resetWorld($sec45WorldId);

    $sec45Engine = new GameEngine($pm, $sec45WorldId, $sec45WorldFile, '', $config);
    $sec45Ui = new UIhtml(null, $sec45Engine);

    // 1. Verify Login form has Sounds and Music checkboxes BEFORE Skin chooser
    ob_start();
    $sec45Ui->renderLogin(null, 'TestUser', 'aqua', true, false);
    $loginHtml = (string)ob_get_clean();

    $posSounds = strpos($loginHtml, 'id="sounds"');
    $posMusic  = strpos($loginHtml, 'id="music"');
    $posSkin   = strpos($loginHtml, 'id="skin"');

    assertTest(
        $posSounds !== false && str_contains($loginHtml, 'type="checkbox"') && str_contains($loginHtml, 'Sounds'),
        "UIhtml login form renders 'Sounds' checkbox"
    );
    assertTest(
        $posMusic !== false && str_contains($loginHtml, 'Music'),
        "UIhtml login form renders 'Music' checkbox"
    );
    assertTest(
        $posSounds !== false && $posMusic !== false && $posSkin !== false && $posSounds < $posSkin && $posMusic < $posSkin,
        "Both 'Sounds' and 'Music' checkboxes appear before the 'Skin' chooser in UIhtml::renderLogin()"
    );

    // 2. Verify Skin SNDNEWMSG resolution (default and custom .dxs)
    $sec45World = $sec45Engine->loadOrSeedWorld();
    $defaultSkin = $sec45World->getSkin('default');
    $aquaSkin    = $sec45World->getSkin('aqua');
    $forestSkin  = $sec45World->getSkin('forest');

    assertTest(
        $defaultSkin !== null && $defaultSkin->sndNewmsg === 'https://www.dimensionex.net/skins/default/sndnewmsg.wav',
        "Default skin resolves SNDNEWMSG to SKINFOLDER + sndnewmsg.wav (got '{$defaultSkin?->sndNewmsg}')"
    );
    assertTest(
        $aquaSkin !== null && $aquaSkin->sndNewmsg === 'http://www.dimensionex.net/skins/aqua/drip.wav',
        "Aqua skin resolves SNDNEWMSG from .dxs (drip.wav) against SKINFOLDER (got '{$aquaSkin?->sndNewmsg}')"
    );
    assertTest(
        $forestSkin !== null && $forestSkin->sndNewmsg === 'https://www.dimensionex.net/skins/forest/notify4.wav',
        "Forest skin resolves SNDNEWMSG from .dxs (notify4.wav) against SKINFOLDER (got '{$forestSkin?->sndNewmsg}')"
    );

    // 3. Log in 3 players with different Sounds/Music settings:
    //    - PlayerSoundOn:  sounds=true,  music=true  (in start room 'spiaggia2', skin='aqua')
    //    - PlayerSoundOff: sounds=false, music=true  (in start room 'spiaggia2', skin='default')
    //    - PlayerOtherRoom: sounds=true, music=false (moved to 'parco', skin='forest')
    $uiSoundOn = new UIhtml(null, $sec45Engine);
    $resOn = $sec45Engine->processCommand('PlayerSoundOn', 'login', '', '', $uiSoundOn, 4501, false, true, null, 'aqua', true, true);
    $pSoundOn = $resOn['player'];

    $uiSoundOff = new UIhtml(null, $sec45Engine);
    $resOff = $sec45Engine->processCommand('PlayerSoundOff', 'login', '', '', $uiSoundOff, 4502, false, true, null, 'default', false, true);
    $pSoundOff = $resOff['player'];

    $uiOther = new UIhtml(null, $sec45Engine);
    $resOther = $sec45Engine->processCommand('PlayerOtherRoom', 'login', '', '', $uiOther, 4503, false, true, null, 'forest', true, false);
    $pOther = $resOther['player'];

    assertTest(
        $pSoundOn->sounds === true && $pSoundOn->music === true,
        "PlayerSoundOn keeps sounds=true and music=true inside Player instance"
    );
    assertTest(
        $pSoundOff->sounds === false && $pSoundOff->music === true,
        "PlayerSoundOff keeps sounds=false and music=true inside Player instance"
    );
    assertTest(
        $pOther->sounds === true && $pOther->music === false,
        "PlayerOtherRoom keeps sounds=true and music=false inside Player instance"
    );

    // Move PlayerOtherRoom to a different room ('parco')
    $world = $sec45Engine->loadOrSeedWorld();
    $pOnInWorld    = $world->getPlayerByName('PlayerSoundOn');
    $pOffInWorld   = $world->getPlayerByName('PlayerSoundOff');
    $pOtherInWorld = $world->getPlayerByName('PlayerOtherRoom');
    $roomSpiaggia2 = $world->getRoom('spiaggia2');
    $roomParco     = $world->getRoom('parco');
    if ($pOtherInWorld !== null && $roomParco !== null) {
        $pOtherInWorld->move($roomParco);
    }
    // Clear any initial login messages/sounds
    $pOnInWorld?->drainSound();
    $pOnInWorld?->drainNewMessages();
    $pOffInWorld?->drainSound();
    $pOffInWorld?->drainNewMessages();
    $pOtherInWorld?->drainSound();
    $pOtherInWorld?->drainNewMessages();

    // 4. Test PlaySound directed to a single Player (default $AGENT or explicit player)
    $runner = new \Dimx\ActionsRunner($world, $roomSpiaggia2, $pOnInWorld, null);
    $runner->run('PlaySound " splash.wav "');

    assertTest(
        $pOnInWorld->sound === 'https://www.dimensionex.net/pics/splash.wav',
        "PlaySound 'splash.wav' (to \$AGENT) resolves with IMAGESFOLDER to 'https://www.dimensionex.net/pics/splash.wav' (got '{$pOnInWorld->sound}')"
    );

    ob_start();
    $uiSoundOn->render([
        'world'    => $world,
        'player'   => $pOnInWorld,
        'room'     => $pOnInWorld->getRoom(),
        'messages' => [],
        'chat'     => [],
    ]);
    $htmlOnSingle = (string)ob_get_clean();

    assertTest(
        str_contains($htmlOnSingle, '<audio id="sndplayer"') &&
        str_contains($htmlOnSingle, 'https://www.dimensionex.net/pics/splash.wav') &&
        str_contains($htmlOnSingle, 'autoplay'),
        "UIhtml embeds hidden <audio id=\"sndplayer\" autoplay> with IMAGESFOLDER + soundFile for sound-enabled player"
    );
    assertTest(
        $pOnInWorld->sound === null,
        "Pending one-shot sound on Player is drained after UIhtml::render()"
    );

    // 5. Test PlaySound directed to a Room: all players in that room receive it (sound-enabled only), players in other rooms do not
    $runnerRoom = new \Dimx\ActionsRunner($world, $roomSpiaggia2, $pOnInWorld, null);
    $runnerRoom->run('PlaySound spiaggia2, "Gong.wav"');

    assertTest(
        $pOnInWorld->sound === 'https://www.dimensionex.net/pics/Gong.wav',
        "PlaySound to room 'spiaggia2' queues sound for sound-enabled PlayerSoundOn in that room"
    );
    assertTest(
        $pOffInWorld->sound === null,
        "PlaySound to room 'spiaggia2' does NOT queue sound for sound-disabled PlayerSoundOff in that room"
    );
    assertTest(
        $pOtherInWorld->sound === null,
        "PlaySound to room 'spiaggia2' does NOT queue sound for PlayerOtherRoom in another room ('parco')"
    );

    // Render UIhtml for PlayerSoundOff: must NOT have <audio id="sndplayer">
    ob_start();
    $uiSoundOff->render([
        'world'    => $world,
        'player'   => $pOffInWorld,
        'room'     => $pOffInWorld->getRoom(),
        'messages' => [],
        'chat'     => [],
    ]);
    $htmlOffRoom = (string)ob_get_clean();
    assertTest(
        !str_contains($htmlOffRoom, '<audio id="sndplayer"'),
        "UIhtml does NOT embed <audio id=\"sndplayer\"> for PlayerSoundOff (Sounds disabled at login)"
    );
    $pOnInWorld->drainSound();

    // 6. Test PlaySound directed to $WORLD: all sound-enabled players in the world receive it
    $runnerWorld = new \Dimx\ActionsRunner($world, $roomSpiaggia2, $pOnInWorld, null);
    $runnerWorld->run('PlaySound $WORLD, "thunder.wav"');

    assertTest(
        $pOnInWorld->sound === 'https://www.dimensionex.net/pics/thunder.wav' &&
        $pOtherInWorld->sound === 'https://www.dimensionex.net/pics/thunder.wav' &&
        $pOffInWorld->sound === null,
        "PlaySound to \$WORLD queues 'https://www.dimensionex.net/pics/thunder.wav' for all sound-enabled players across all rooms, and not for sound-disabled players"
    );
    $pOnInWorld->drainSound();
    $pOtherInWorld->drainSound();

    // 7. Test Skin SNDNEWMSG playback when a new message pops in for sound-enabled vs sound-disabled players
    $world->postChatMessage('Surfer', 'Hey there, waves are great today!', null, 'spiaggia2');

    assertTest(
        $pOnInWorld->newMessages === true && $pOffInWorld->newMessages === true && $pOtherInWorld->newMessages === false,
        "Room chat message sets newMessages=true on occupants of 'spiaggia2'"
    );

    ob_start();
    $uiSoundOn->render([
        'world'    => $world,
        'player'   => $pOnInWorld,
        'room'     => $pOnInWorld->getRoom(),
        'messages' => [],
        'chat'     => $pOnInWorld->getMessages(),
    ]);
    $htmlOnMsg = (string)ob_get_clean();

    ob_start();
    $uiSoundOff->render([
        'world'    => $world,
        'player'   => $pOffInWorld,
        'room'     => $pOffInWorld->getRoom(),
        'messages' => [],
        'chat'     => $pOffInWorld->getMessages(),
    ]);
    $htmlOffMsg = (string)ob_get_clean();

    assertTest(
        str_contains($htmlOnMsg, '<audio id="sndplayer"') &&
        str_contains($htmlOnMsg, 'http://www.dimensionex.net/skins/aqua/drip.wav'),
        "Sound-enabled PlayerSoundOn (Aqua skin) plays skin SNDNEWMSG ('http://www.dimensionex.net/skins/aqua/drip.wav') when a new message pops in"
    );
    assertTest(
        !str_contains($htmlOffMsg, '<audio id="sndplayer"'),
        "Sound-disabled PlayerSoundOff does NOT get <audio id=\"sndplayer\"> when a new message pops in"
    );

    // 8. Test Background Music (#bgplayer silent by default for music-enabled players, absent for music-disabled players)
    ob_start();
    $uiOther->render([
        'world'    => $world,
        'player'   => $pOtherInWorld,
        'room'     => $pOtherInWorld->getRoom(),
        'messages' => [],
        'chat'     => [],
    ]);
    $htmlOther = (string)ob_get_clean();

    assertTest(
        str_contains($htmlOnMsg, '<audio id="bgplayer"') &&
        !str_contains($htmlOnMsg, 'playBackgroundMusic("') &&
        str_contains($htmlOffMsg, '<audio id="bgplayer"') &&
        !str_contains($htmlOther, '<audio id="bgplayer"'),
        "UIhtml renders silent <audio id=\"bgplayer\"> for music-enabled players (PlayerSoundOn & PlayerSoundOff) and omits it for music-disabled PlayerOtherRoom"
    );

    $posCtrls    = strpos($htmlOnMsg, 'id="ctrls"');
    $posBgPlayer = strpos($htmlOnMsg, 'id="bgplayer"');
    $posMsgs     = strpos($htmlOnMsg, 'id="msgs"');
    assertTest(
        $posCtrls !== false && $posBgPlayer !== false && $posMsgs !== false &&
        $posCtrls < $posBgPlayer && $posBgPlayer < $posMsgs &&
        str_contains($htmlOnMsg, 'function refreshAreas('),
        "UIhtml places <audio id=\"bgplayer\"> inside #ctrls (before #msgs) and includes selective refreshAreas() for #scene, #ctrls, and #msgs"
    );

    // 9. Test world3net bar.onReceive triggering PlayBackground "reggaeton.mp3" upon room entry
    $barRoom = $world->getRoom('bar');
    $pOnInWorld->go('w24'); // spiaggia2 -> spiaggia3
    $pOnInWorld->go('w4a'); // spiaggia3 -> bar (fires bar.onReceive -> PlayBackground "reggaeton.mp3")

    assertTest(
        $pOnInWorld->getRoom()->id === 'bar' &&
        $barRoom->backgroundMusic === 'https://www.dimensionex.net/pics/reggaeton.mp3' &&
        $barRoom->backgroundMusicLoop === false &&
        (int)$pOnInWorld->getProperty('barmusic') === 1,
        "Walking into 'bar' in world3net fires bar.onReceive -> PlayBackground \"reggaeton.mp3\", setting bar->backgroundMusic to 'https://www.dimensionex.net/pics/reggaeton.mp3' and \$TARGET.barmusic = 1"
    );

    ob_start();
    $uiSoundOn->render([
        'world'            => $world,
        'player'           => $pOnInWorld,
        'room'             => $pOnInWorld->getRoom(),
        'background_music' => $pOnInWorld->drainBackgroundSound(),
        'messages'         => [],
        'chat'             => [],
    ]);
    $htmlBarMusic = (string)ob_get_clean();

    assertTest(
        str_contains($htmlBarMusic, '<audio id="bgplayer"') &&
        str_contains($htmlBarMusic, 'playBackgroundMusic(') &&
        str_contains($htmlBarMusic, 'reggaeton.mp3') &&
        str_contains($htmlBarMusic, ', false);'),
        "UIhtml scene render in 'bar' invokes playBackgroundMusic(\"https://www.dimensionex.net/pics/reggaeton.mp3\", false)"
    );

    // 10. Test PlayBackground with loop=true option and persistence across logout/login
    $runnerBar = new \Dimx\ActionsRunner($world, $barRoom, $pOnInWorld, null);
    $runnerBar->run('PlayBackground "reggaeton.mp3", true');

    assertTest(
        $barRoom->backgroundMusic === 'https://www.dimensionex.net/pics/reggaeton.mp3' &&
        $barRoom->backgroundMusicLoop === true,
        "PlayBackground with loop=true updates room->backgroundMusicLoop to true"
    );
    // Drain the immediate one-time player notification and save the world to simulate logout/re-login
    $pOnInWorld->drainBackgroundSound();
    $pm->saveWorld($world);

    // Simulate a fresh scene render after logging back in (no new PlayBackground triggered, $pOnInWorld->backgroundSound is null)
    $reloadedWorld = $pm->loadWorld($sec45WorldId);
    $reloadedBar = $reloadedWorld->getRoom('bar');
    $reloadedPOn = $reloadedWorld->getPlayer($pSoundOn->id);

    ob_start();
    $uiSoundOn->render([
        'world'    => $reloadedWorld,
        'player'   => $reloadedPOn,
        'room'     => $reloadedBar,
        'messages' => [],
        'chat'     => [],
    ]);
    $htmlReloadedBar = (string)ob_get_clean();

    assertTest(
        $reloadedBar->backgroundMusic === 'https://www.dimensionex.net/pics/reggaeton.mp3' &&
        $reloadedBar->backgroundMusicLoop === true &&
        str_contains($htmlReloadedBar, 'reggaeton.mp3') &&
        str_contains($htmlReloadedBar, ', true);'),
        "Room backgroundMusic and loop flag persist on the room across save/reload (logout/login) and trigger playBackgroundMusic(..., true) on scene render without re-running PlayBackground"
    );

    // 11. Test PlayBackground $WORLD and independence of #sndplayer (PlaySound) + #bgplayer (PlayBackground)
    $runnerWorldBg = new \Dimx\ActionsRunner($reloadedWorld, $reloadedWorld, $reloadedPOn, null);
    $runnerWorldBg->run('PlayBackground $WORLD, "sndMimovie.mp3", true');
    $runnerWorldBg->run('PlaySound $WORLD, "splash.wav"');

    $reloadedPOff = $reloadedWorld->getPlayer($pSoundOff->id); // sounds=false, music=true in spiaggia2 (no room bgMusic)
    $reloadedPOther = $reloadedWorld->getPlayer($pOther->id); // sounds=true, music=false in parco

    ob_start();
    $uiSoundOff->render([
        'world'    => $reloadedWorld,
        'player'   => $reloadedPOff,
        'room'     => $reloadedPOff->getRoom(),
        'messages' => [],
        'chat'     => [],
    ]);
    $htmlWorldBgOff = (string)ob_get_clean();

    ob_start();
    $uiOther->render([
        'world'    => $reloadedWorld,
        'player'   => $reloadedPOther,
        'room'     => $reloadedPOther->getRoom(),
        'messages' => [],
        'chat'     => [],
    ]);
    $htmlWorldBgOther = (string)ob_get_clean();

    assertTest(
        $reloadedWorld->backgroundMusic === 'https://www.dimensionex.net/pics/sndMimovie.mp3' &&
        $reloadedWorld->backgroundMusicLoop === true &&
        !str_contains($htmlWorldBgOff, '<audio id="sndplayer"') &&
        str_contains($htmlWorldBgOff, '<audio id="bgplayer"') &&
        str_contains($htmlWorldBgOff, 'sndMimovie.mp3'),
        "PlayerSoundOff (sounds=false, music=true) in a room without room music receives \$WORLD background music ('sndMimovie.mp3') on #bgplayer and no #sndplayer"
    );

    assertTest(
        str_contains($htmlWorldBgOther, '<audio id="sndplayer"') &&
        str_contains($htmlWorldBgOther, 'splash.wav') &&
        !str_contains($htmlWorldBgOther, '<audio id="bgplayer"') &&
        !str_contains($htmlWorldBgOther, 'playBackgroundMusic("') &&
        true,
        "PlayerOtherRoom (sounds=true, music=false) receives PlaySound ('splash.wav') on #sndplayer and no #bgplayer / playBackgroundMusic call"
    );

    @$pm->resetWorld($sec45WorldId);
}

// ============================================================================
// Section 46: Testing Say Command Target Handling (Speak to Person vs Speak Aloud with target=*)
// ============================================================================
if (shouldRun(46)) {
    echo "\n--- Section 46: Testing Say Command Target Handling (Speak to Person vs Speak Aloud with target=* in UIhtml & GameEngine) ---\n";

    // 1. UI::parseInput() tests with target='*' and target='character_id' and txtBox quick chat
    $origPost = $_POST;
    $origGet = $_GET;

    $_POST = ['cmd' => 'say', 'param' => 'Hello everyone!', 'target' => '*'];
    $_GET = [];
    $parsed1 = Dimx\UI::parseInput();
    assertTest($parsed1['cmd'] === 'say', "UI::parseInput parses cmd='say'");
    assertTest($parsed1['param'] === 'Hello everyone!', "UI::parseInput parses param='Hello everyone!'");
    assertTest($parsed1['target'] === '*', "UI::parseInput parses target='*' (speak aloud)");

    $_POST = ['cmd' => 'say', 'param' => 'Greetings friend', 'target' => 'surfer'];
    $_GET = [];
    $parsed2 = Dimx\UI::parseInput();
    assertTest($parsed2['cmd'] === 'say', "UI::parseInput parses cmd='say' with target person");
    assertTest($parsed2['param'] === 'Greetings friend', "UI::parseInput parses param='Greetings friend'");
    assertTest($parsed2['target'] === 'surfer', "UI::parseInput parses target='surfer'");

    $_POST = ['txtBox' => 'Quick chat aloud'];
    $_GET = [];
    $parsed3 = Dimx\UI::parseInput();
    assertTest($parsed3['cmd'] === 'say', "UI::parseInput defaults empty cmd to 'say' when txtBox is set");
    assertTest($parsed3['param'] === 'Quick chat aloud', "UI::parseInput reads param from txtBox");
    assertTest($parsed3['target'] === '*', "UI::parseInput defaults target to '*' when quick chatting via txtBox");

    $_POST = $origPost;
    $_GET = $origGet;

    // 2. Ctrl textbox rendering contains sendCmd('say', this.value, '*')
    $txtCtrl = new Dimx\Ctrl('txtBox', Dimx\Ctrl::TYPE_TEXTBOX, '', 'Enter speech...');
    $txtHtml = $txtCtrl->toHtml();
    assertTest(
        str_contains($txtHtml, "sendCmd('say', this.value, '*')"),
        "Ctrl textbox onkeydown sends sendCmd('say', this.value, '*')"
    );

    // 3. UIhtml rendering contains Say command definitions and JS logic
    $sec46WorldId = 'w_sec46_say_test';
    @$pm->resetWorld($sec46WorldId);
    $loader46 = new Dimx\DxwLoader('http://localhost');
    $sec46World = $loader46->load($dxwFile, $sec46WorldId);
    $engine46 = new Dimx\GameEngine($pm, $sec46WorldId, $dxwFile);

    $pTester = new Dimx\Player($sec46World, 'TesterSay', 'p_testersay_1');
    $pTester->numericId = 9901;
    $targetRoom = $sec46World->getRoom('spiaggia2') ?? $sec46World->getRoom((string)$sec46World->defaultRoom) ?? reset($sec46World->rooms);
    $pTester->move($targetRoom);
    $sec46World->addPlayer($pTester);

    $uiHtml46 = new Dimx\UIhtml($pTester, $engine46);
    ob_start();
    $uiHtml46->render([
        'world'    => $sec46World,
        'player'   => $pTester,
        'room'     => $pTester->getRoom(),
        'worldId'  => $sec46WorldId,
        'messages' => [],
        'chat'     => [],
    ]);
    $htmlOutput = ob_get_clean();

    assertTest(
        str_contains($htmlOutput, "'say': {") || str_contains($htmlOutput, "'say':{"),
        "UIhtml contains 'say' command in COMMAND_DEFS"
    );
    assertTest(
        str_contains($htmlOutput, "allowDouble: true") && str_contains($htmlOutput, "model: 'TO'"),
        "UIhtml COMMAND_DEFS['say'] specifies allowDouble: true and model: 'TO'"
    );
    assertTest(
        str_contains($htmlOutput, "submitCmd('say', text, '*')"),
        "UIhtml handleCommand submits submitCmd('say', text, '*') on double-click Say"
    );
    assertTest(
        str_contains($htmlOutput, "submitCmd('say', text, firstArg)"),
        "UIhtml clickObject submits submitCmd('say', text, firstArg) when clicking a character"
    );
    assertTest(
        str_contains($htmlOutput, "target = '*'"),
        "UIhtml submitCmd defaults empty target for say to '*'"
    );

    // 4. GameEngine execution: target='*' speaks aloud to room occupants
    $room = $pTester->getRoom();
    $pListener = new Dimx\Player($sec46World, 'Listener', 'p_listener_2');
    $pListener->numericId = 9902;
    $pListener->move($room);
    $sec46World->addPlayer($pListener);

    $engine46->executeCommandAction($sec46World, $pTester, 'say', 'Hello to everyone in the room!', '*');
    assertTest(
        in_array("<b>TesterSay</b> says: Hello to everyone in the room!", $pListener->clientConsole, true),
        "GameEngine: target='*' broadcasts 'says: <text>' to other player in the room"
    );
    assertTest(
        in_array("<b>TesterSay</b> says: Hello to everyone in the room!", $pTester->clientConsole, true),
        "GameEngine: target='*' displays 'says: <text>' to speaker in the room"
    );

    $chatHistory = $sec46World->chatQueue ?? [];
    $lastChat = end($chatHistory);
    assertTest(
        $lastChat !== false && $lastChat['text'] === 'Hello to everyone in the room!' && $lastChat['recipient'] === null,
        "GameEngine: target='*' posts chat message without specific recipient (public room broadcast)"
    );

    // Verify speaking player does NOT deliver room broadcast message to himself in messagesQueue (#msgs)
    $testerMsgs = $pTester->getMessages();
    $listenerMsgs = $pListener->getMessages();
    $testerHasSpokenMsg = false;
    foreach ($testerMsgs as $m) {
        if ($m['text'] === 'Hello to everyone in the room!') {
            $testerHasSpokenMsg = true;
            break;
        }
    }
    $listenerHasSpokenMsg = false;
    foreach ($listenerMsgs as $m) {
        if ($m['text'] === 'Hello to everyone in the room!') {
            $listenerHasSpokenMsg = true;
            break;
        }
    }
    assertTest(!$testerHasSpokenMsg, "Room Speak: speaking player does NOT deliver room broadcast message to himself in messagesQueue (#msgs)");
    assertTest($listenerHasSpokenMsg, "Room Speak: other player in room receives room broadcast message in messagesQueue (#msgs)");

    // 5. GameEngine execution: target='id of character' speaks directly to specific person
    $surfer = $sec46World->getCharacter('surfer');
    if ($surfer !== null) {
        $surfer->move($room);
    }
    $pTester->clientConsole = [];
    $pTester->messagesQueue = [];
    $pListener->clientConsole = [];
    $pListener->messagesQueue = [];

    $engine46->executeCommandAction($sec46World, $pTester, 'say', 'Hello Surfer!', 'surfer');
    assertTest(
        in_array("You say to <b>" . ($surfer?->name ?? 'surfer') . "</b>: Hello Surfer!", $pTester->clientConsole, true),
        "GameEngine: target='surfer' displays 'You say to <b>...</b>: <text>' to speaker"
    );

    // Listener in the same room should NOT receive "TesterSay says to you: ..."
    assertTest(
        !in_array("<b>TesterSay</b> says to you: Hello Surfer!", $pListener->clientConsole, true),
        "GameEngine: direct speech to surfer is not whispered to other listeners"
    );

    $chatHistory2 = $sec46World->chatQueue ?? [];
    $lastChat2 = end($chatHistory2);
    assertTest(
        $lastChat2 !== false && str_contains($lastChat2['text'], 'Hello Surfer!') && $lastChat2['recipient'] === ($surfer?->name ?? 'surfer'),
        "GameEngine: target='surfer' posts chat message directed to target person"
    );

    // 6. GameEngine execution: target='p_listener_2' speaks directly to player Listener
    $pTester->clientConsole = [];
    $pTester->messagesQueue = [];
    $pListener->clientConsole = [];
    $pListener->messagesQueue = [];

    $engine46->executeCommandAction($sec46World, $pTester, 'say', 'Psst, secret message', 'p_listener_2');
    assertTest(
        in_array("You say to <b>Listener</b>: Psst, secret message", $pTester->clientConsole, true),
        "GameEngine: target player receives direct speech confirmation for speaker"
    );
    assertTest(
        in_array("<b>TesterSay</b> says to you: Psst, secret message", $pListener->clientConsole, true),
        "GameEngine: target player receives '<b>TesterSay</b> says to you: Psst, secret message'"
    );

    // 7. Test in world3net: speaking aloud in the bar should NOT trigger barman.onHear event
    $w3File = dirname(__DIR__) . '/docs/dxw-examples/world3net.dxw';
    if (!file_exists($w3File)) {
        $w3File = __DIR__ . '/world3net.dxw';
    }
    $sec46W3Id = 'w_sec46_w3_test';
    @$pm->resetWorld($sec46W3Id);
    $w3Loader = new Dimx\DxwLoader('http://localhost');
    $w3net = $w3Loader->load($w3File);
    $w3net->id = $sec46W3Id;
    $engineW3 = new Dimx\GameEngine($pm, $sec46W3Id, $w3File);

    $barRoom = $w3net->getRoom('bar');
    $barman = $w3net->getCharacter('barman');
    assertTest($barRoom instanceof Dimx\Room, "World3net: room 'bar' exists");
    assertTest($barman instanceof Dimx\Character, "World3net: character 'barman' exists");
    assertTest(!($barman instanceof Dimx\Player), "World3net: 'barman' is a computer controlled character (not Player)");

    $pBarPlayer = new Dimx\Player($w3net, 'BarCustomer', 'p_bar_cust_1');
    $pBarPlayer->numericId = 9903;
    $pBarPlayer->move($barRoom);
    $w3net->addPlayer($pBarPlayer);

    // Speak aloud in the bar
    $w3net->chatQueue = [];
    $pBarPlayer->clientConsole = [];
    $engineW3->executeCommandAction($w3net, $pBarPlayer, 'say', 'Can I get some service around here?', '*');

    // Verify barman.onHear was NOT triggered by speak aloud
    $barmanResponses = array_filter($w3net->chatQueue, fn($m) => ($m['sender'] ?? '') === 'the barman' || ($m['sender'] ?? '') === 'barman');
    assertTest(
        empty($barmanResponses),
        "World3net: speaking aloud in the bar does NOT trigger barman.onHear"
    );
    assertTest(
        in_array("<b>BarCustomer</b> says: Can I get some service around here?", $pBarPlayer->clientConsole, true),
        "World3net: player speaking aloud displays phrase on player's console"
    );

    // Direct speech to barman DOES trigger barman.onHear
    $w3net->chatQueue = [];
    $pBarPlayer->clientConsole = [];
    $engineW3->executeCommandAction($w3net, $pBarPlayer, 'say', 'A drink please', 'barman');

    $barmanResponsesDirect = array_filter($w3net->chatQueue, fn($m) => ($m['sender'] ?? '') === 'the barman' || ($m['sender'] ?? '') === 'barman');
    $firstBarmanMsg = reset($barmanResponsesDirect);
    assertTest(
        !empty($barmanResponsesDirect) && str_contains($firstBarmanMsg['text'] ?? '', "What did you say? What should I give you?"),
        "World3net: direct speech to barman (say text barman) DOES trigger barman.onHear"
    );

    // 8. Testing multiplayer.php?view=msgs async refresh endpoint for #msgs / #chatBox
    // Save current world with BarCustomer having messages
    $pm->saveWorld($w3net);

    // Call multiplayer.php?view=msgs for BarCustomer
    $multiplayerScript = __DIR__ . '/multiplayer.php';
    $chatRunner = __DIR__ . '/temp_chat_runner.php';
    file_put_contents($chatRunner, "<?php
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_GET = ['view' => 'msgs', 'world_id' => '{$sec46W3Id}', 'player' => 'BarCustomer'];
require '{$multiplayerScript}';
");
    $chatJson = shell_exec("php {$chatRunner}");
    @unlink($chatRunner);

    $chatData = json_decode((string)$chatJson, true);
    assertTest(is_array($chatData) && !empty($chatData['ok']), "multiplayer.php?view=msgs returns valid JSON with ok=true");
    assertTest(isset($chatData['messages']) && is_array($chatData['messages']), "multiplayer.php?view=msgs returns 'messages' array");
    assertTest(
        !empty($chatData['messages']) && end($chatData['messages'])['sender'] === 'the barman',
        "multiplayer.php?view=msgs retrieves barman response for BarCustomer"
    );

    assertTest(
        str_contains($htmlOutput, 'pollChat()') && str_contains($htmlOutput, "multiplayer.php?view=msgs"),
        "UIhtml script defines pollChat() querying multiplayer.php?view=msgs"
    );
    assertTest(
        str_contains($htmlOutput, "box.innerHTML = newHtml") || str_contains($htmlOutput, "box.innerHTML = rendered"),
        "UIhtml pollChat updates chatBox innerHTML when messages change"
    );

    @$pm->resetWorld($sec46W3Id);
    @$pm->resetWorld($sec46WorldId);
}

// -------------------------------------------------------------
// Test 47: SmallBasic Debug Instruction & GameEngine::log()
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
    $eng47 = new Dimx\GameEngine($pm, $sec47WorldId, null, $baseUrl);
    assertTest($eng47->debugTo === 'none', "GameEngine::\$debugTo defaults to 'none'");

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
