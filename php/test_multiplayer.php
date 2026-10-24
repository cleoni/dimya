<?php
declare(strict_types=1);

/**
 * test_multiplayer.php
 * Automated tests for UI hierarchy, GameEngine, UIhtml, UIjson bot API endpoint,
 * player database storage, auto-increment numeric ID, and bcrypt password authentication.
 */

require_once __DIR__ . '/autoload.php';

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
    15 => ['title' => 'Testing UIhtml Cell B Command Buttons & On-Screen Object Targeting...', 'keywords' => ['uihtml', 'cell b', 'buttons', 'onscreen', 'targeting', 'switch', 'jquery', 'command_defs', 'clickobject', 'identifyobject']],
    16 => ['title' => "Testing 'open' and 'close' Commands & actOpen / actClose on 'box' in World 2...", 'keywords' => ['open', 'close', 'box', 'actopen', 'actclose', 'world2', 'cardboard', 'actionsrunner']],
    17 => ['title' => 'Testing World 2 Full Flow: Look at Closed Box, Use Switch in Bungalow, Open Box & Pick Apple...', 'keywords' => ['flow', 'box', 'switch', 'bungalow', 'open', 'pick', 'apple', 'goal', 'uibot', 'evaldescription', 'is closed', 'is open', 'containing', 'rocca', 'spiaggia']],
    18 => ['title' => 'Testing Speak Instruction Behavior & Inanimate Object Fallback...', 'keywords' => ['speak', 'surfer', 'switch', 'voice', 'system', 'sound', 'sys', 'fallback', 'inanimate', 'bungalow']],
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

// Step 5g: 'exit' command logs out player
$resultExit = $engine->processCommand($botName, 'exit', '', '', $uiJson);
assertTest(($resultExit['logged_out'] ?? false) === true, "Exit command sets logged_out = true");
assertTest($world->getPlayer($resultExit['player']->id) === null, "Player cleanly removed on exit");

// Step 5h: 'savexit' command is removed completely
$resultSavexit = $engine->processCommand($botName, 'savexit', '', '', $uiJson);
assertTest(($resultSavexit['logged_out'] ?? false) === false, "Legacy 'savexit' does NOT trigger logout");
assertTest(in_array("Unknown command: savexit", $resultSavexit['messages'], true), "Legacy 'savexit' is treated as unknown command");

// Step 5i: Panel buttons verify 'save' & 'exit' replace 'savexit'
$defaultPanel = Dimx\Panel::createDefaultPanel();
assertTest($defaultPanel->getButton('save') !== null, "Default panel includes 'save' button");
assertTest($defaultPanel->getButton('exit') !== null, "Default panel includes 'exit' button");
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
assertTest(in_array('exit', $chatCmds, true), "Chat panel allows 'exit' command");
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
    in_array('exit', $botCmds, true),
    "Bot commands include 'exit'"
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
$lookMsg = implode(' ', $lookData['messages'] ?? []);
assertTest(
    str_contains($lookMsg, "Walks up and down, I'd say he's got nothing to do."),
    "Bot successfully looks at surfer: character description is in messages"
);
assertTest(
    !str_contains($lookMsg, "staring at me") && !str_contains($lookMsg, "Please look elsewhere!"),
    "surfer.onLook spoken message is NOT in room description / messages"
);

// Verify surfer.onLook Speak output is delivered in chat messages
$chatTexts = array_column($lookData['chat'] ?? [], 'text');
$chatAll   = implode(' ', $chatTexts);
assertTest(
    str_contains($chatAll, "staring at me") || str_contains($chatAll, "Please look elsewhere!"),
    "surfer.onLook spoken message IS heard in chat messages: '{$chatAll}'"
);
$surferChat = array_filter($lookData['chat'] ?? [], fn($c) => ($c['sender'] ?? '') === 'a surfer');
assertTest(!empty($surferChat), "surfer.onLook speech has sender 'a surfer' in chat");
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
$otherChatTexts = array_column($otherChatData['chat'] ?? [], 'text');
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
$clickMsg = implode(' ', $clickData['messages'] ?? []);
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
$disallowedPickMsg = implode(' ', $disallowedPickData['messages'] ?? []);
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
assertTest(str_contains($humanHtmlOutput, "sendCmd('exit')"), "Human player gets Exit button");
assertTest(str_contains($humanHtmlOutput, "sendCmd('go','U')"), "Human player STILL SEES exit button for [U] to the rock");
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

// Test maint.php authorization with correct password
$_POST = ['action' => 'reset_world', 'adminPasswd' => $cfg['adminPasswd']];
ob_start();
include __DIR__ . '/maint.php';
$maintSuccess = ob_get_clean();
assertTest(str_contains($maintSuccess, 'The game world has been reset'), "maint.php authorizes action with correct adminPasswd");
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
// Test 15: Testing UIhtml Cell B Command Buttons & On-Screen Object Targeting...
// -------------------------------------------------------------
if (shouldRun(15)) {
    echo "\n15. Testing UIhtml Cell B Command Buttons & On-Screen Object Targeting...\n";
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
assertTest(str_contains($bungalowHtml, 'id="cmdPrompt"'), "UIhtml cell B contains cmdPrompt div");
assertTest(str_contains($bungalowHtml, 'const COMMAND_DEFS = {'), "UIhtml defines generic COMMAND_DEFS dictionary");
assertTest(str_contains($bungalowHtml, "function identifyObject("), "UIhtml includes identifyObject function");
assertTest(str_contains($bungalowHtml, "function clickObject("), "UIhtml includes clickObject function");
assertTest(str_contains($bungalowHtml, "function handleCommand("), "UIhtml includes handleCommand function");
assertTest(str_contains($bungalowHtml, "data-cmd=\"use\" data-model=\"O\""), "UIhtml cell B USE button has data-cmd='use' and data-model='O'");
assertTest(str_contains($bungalowHtml, "data-cmd=\"look\" data-model=\"O\""), "UIhtml cell B LOOK button has data-cmd='look' and data-model='O'");
assertTest(str_contains($bungalowHtml, 'data-obj-id="switch"') && str_contains($bungalowHtml, 'data-obj-type="i"'), "UIhtml renders switch as on-screen object with id='switch' and type='i'");
assertTest(str_contains($bungalowHtml, "left:62px;top:68px;"), "UIhtml positions switch on scene at left:62px;top:68px;");
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

// Render in UIhtml: verify subcell D console output formats line break before 'a cardboard box is closed.'
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
    "UIhtml console in subcell D formats line breaks so 'a cardboard box is closed.' appears on a new line"
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

// Contained items must NOT appear in the room items list in cell B / subcell E
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
    "Items list in subcell E omits (open)/(closed) status as requested"
);

// Look at box in UIhtml: verify subcell D console output formats line break before 'a cardboard box is open.'
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
    "UIhtml console in subcell D formats line breaks so 'a cardboard box is open.' appears on a new line"
);

// Contained items appear directly after/below open message in console
assertTest(str_contains($spiaggiaHtml, 'dimx-console'), "UIhtml includes console");
assertTest(str_contains($spiaggiaHtml, 'Containing:'), "UIhtml console displays 'Containing:' below open message");
assertTest(str_contains($spiaggiaHtml, 'data-obj-id="apple"'), "UIhtml displays apple with data-obj-id='apple'");
assertTest(str_contains($spiaggiaHtml, 'data-obj-type="i"'), "UIhtml displays apple with data-obj-type='i'");
assertTest(str_contains($spiaggiaHtml, 'onclick="clickObject(this)"'), "UIhtml displays apple with clickable onclick handler");
assertTest(str_contains($spiaggiaHtml, 'icoMela.gif'), "UIhtml displays clickable icon for apple (icoMela.gif)");
assertTest(str_contains($spiaggiaHtml, 'an apple'), "UIhtml displays name 'an apple'");
assertTest(str_contains($spiaggiaHtml, "\$(document).on('click', '.dimx-onscreen-obj, [data-obj-id]'"), "UIhtml includes delegated point-and-click handler for objects anywhere including cell B");

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

// Clean up test worlds
if (isset($pm)) {
    if (isset($testWorldId)) {
        @$pm->resetWorld($testWorldId);
    }
    @$pm->resetWorld('main');
    if (isset($unitWorldId)) {
        @$pm->resetWorld($unitWorldId);
    }
}

$executedCount = count($executedSections);
$totalCount = count($sections);
if ($executedCount < $totalCount) {
    echo "\n=== Summary: {$passCount} passed, {$failCount} failed (filtered: {$executedCount}/{$totalCount} sections executed) ===\n";
} else {
    echo "\n=== Summary: {$passCount} passed, {$failCount} failed ===\n";
}
exit($failCount > 0 ? 1 : 0);
