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
use Dimx\GameEngine;
use Dimx\PersistenceManager;
use Dimx\Player;

echo "=== Running DimensioneX Multiplayer, Auth & UI Tests ===\n\n";

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

// -------------------------------------------------------------
// Test 1: Client Detection
// -------------------------------------------------------------
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

// -------------------------------------------------------------
// Test 2: Input Parsing (Single World enforcement & Password)
// -------------------------------------------------------------
echo "\n2. Testing UI::parseInput()...\n";
$_GET = ['cmd' => 'go', 'dir' => 'N', 'player' => 'Explorer1', 'password' => 'secret123', 'world' => 'arbitrary_world'];
$input = UI::parseInput();
assertTest($input['username'] === 'Explorer1', "Parsed username 'Explorer1'");
assertTest($input['password'] === 'secret123', "Parsed password 'secret123'");
assertTest($input['cmd'] === 'go', "Parsed cmd 'go'");
assertTest($input['param'] === 'N', "Parsed param 'N' from 'dir'");
assertTest(!isset($input['world']) && !isset($input['world_file']), "World switching parameters are ignored (single world)");

// -------------------------------------------------------------
// Test 3: GameEngine Setup & Core Processing
// -------------------------------------------------------------
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

// -------------------------------------------------------------
// Test 4: Database Player Accounts & Password Encryption
// -------------------------------------------------------------
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

// -------------------------------------------------------------
// Test 5: Bot Player Command Dispatch via UIjson API
// -------------------------------------------------------------
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

// -------------------------------------------------------------
// Test 6: Simulating multiplayer.php entrypoint for Bot (JSON)
// -------------------------------------------------------------
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

// -------------------------------------------------------------
// Test 7: Simulating multiplayer.php entrypoint for Human (HTML)
// -------------------------------------------------------------
echo "\n7. Testing multiplayer.php entrypoint with human HTML player...\n";
$htmlOutput = runMultiplayer([
    'username' => 'HumanHero',
    'password' => 'heroPass789',
    'cmd'      => 'look',
], [], ['HTTP_ACCEPT' => 'text/html']);

assertTest(str_contains($htmlOutput, '<!DOCTYPE html>'), "multiplayer.php returned HTML doctype");
assertTest(str_contains($htmlOutput, 'HumanHero'), "HTML output includes player name 'HumanHero'");
assertTest(str_contains($htmlOutput, '👤 <b>HumanHero</b>'), "Header displays human-readable player name 'HumanHero'");
assertTest(!preg_match('/👤\s*<b>p_\d+_[a-f0-9]+<\/b>/', $htmlOutput), "Header does NOT display internal engine player ID");
assertTest(str_contains($htmlOutput, 'Welcome to <b>World 2</b>, HumanHero!'), "Action console displays greeting with player nickname HumanHero");
assertTest(!preg_match('/\[p_\d+_[a-f0-9]+\]/', $htmlOutput), "HTML output does NOT expose internal player IDs in visible brackets");
assertTest(str_contains($htmlOutput, 'id="consoleBox"'), "HTML output includes consoleBox element");
assertTest(str_contains($htmlOutput, 'id="chatBox"'), "HTML output includes chatBox element");

// -------------------------------------------------------------
// Test 8: Testing login prompt for Bot (JSON) and Human (HTML)
// -------------------------------------------------------------
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

// -------------------------------------------------------------
// Test 9: Bot Logout / Disconnect & Room Occupant Verification
// -------------------------------------------------------------
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

// -------------------------------------------------------------
// Test 10: world2.dxw Description Parsing (Apostrophe handling)
// -------------------------------------------------------------
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

// -------------------------------------------------------------
// Test 11: onStart Event & Chat PANEL Enforcement
// -------------------------------------------------------------
echo "\n11. Testing onStart Event & Chat PANEL Enforcement...\n";

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
assertTest(
    ($surferOcc['description'] ?? '') === "Walks up and down, I'd say he's got nothing to do.",
    "Bot room snapshot surfer description is complete: '{$surferOcc['description']}'"
);

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
    "Bot successfully looks at surfer in chat panel: '{$lookMsg}'"
);
assertTest(
    str_contains($lookMsg, "staring at me") || str_contains($lookMsg, "Please look elsewhere!"),
    "surfer.onLook script event fired on look: '{$lookMsg}'"
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
assertTest(str_contains($humanHtmlOutput, "sendCmd('click')"), "Human player sees Click button");
assertTest(str_contains($humanHtmlOutput, "sendCmd('use')"), "Human player sees Use button");
assertTest(str_contains($humanHtmlOutput, "sendCmd('say')"), "Human player gets Say button");
assertTest(str_contains($humanHtmlOutput, "sendCmd('save')"), "Human player gets Save button");
assertTest(str_contains($humanHtmlOutput, "sendCmd('exit')"), "Human player gets Exit button");
assertTest(str_contains($humanHtmlOutput, "sendCmd('go','U')"), "Human player STILL SEES exit button for [U] to the rock");
assertTest(!str_contains($humanHtmlOutput, "sendCmd('pick')"), "Human player DOES NOT see Pick button");

// Test 12: Testing UIbot vs UIjson and testBots Configuration
echo "\n12. Testing UIbot vs UIjson and testBots Configuration...\n";
use Dimx\UIbot;

$uiBot = new UIbot();
assertTest($uiBot instanceof Dimx\UIjson, "UIbot inherits from UIjson");
assertTest($uiBot->getType() === 'bot', "UIbot type is 'bot'");

// Create mock room with both NPC Character and Player occupant
$w = $seededWorld;
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

// UIbot player status must NOT have 'id', 'numeric_id', 'is_bot', 'capacity', 'volume', 'carrying_count'
$uiBotPlayerStatus = $uiBot->buildPlayerStatus($observerPlayer);
$uiBotHasInternalPlayerFields = (
    array_key_exists('id', $uiBotPlayerStatus) ||
    array_key_exists('numeric_id', $uiBotPlayerStatus) ||
    array_key_exists('is_bot', $uiBotPlayerStatus) ||
    array_key_exists('capacity', $uiBotPlayerStatus) ||
    array_key_exists('volume', $uiBotPlayerStatus) ||
    array_key_exists('carrying_count', $uiBotPlayerStatus)
);
assertTest(!$uiBotHasInternalPlayerFields, "UIbot player status omits id, numeric_id, is_bot, capacity, volume, carrying_count");
assertTest(array_key_exists('name', $uiBotPlayerStatus) && array_key_exists('inventory', $uiBotPlayerStatus), "UIbot player status retains name, score, health, inventory");

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

$stdPlayer = $standardBotData['player'] ?? [];
$stdBotHasInternalFields = (
    array_key_exists('id', $stdPlayer) ||
    array_key_exists('numeric_id', $stdPlayer) ||
    array_key_exists('is_bot', $stdPlayer) ||
    array_key_exists('capacity', $stdPlayer) ||
    array_key_exists('volume', $stdPlayer) ||
    array_key_exists('carrying_count', $stdPlayer)
);
assertTest(!$stdBotHasInternalFields, "Standard bot receives UIbot without internal player fields (id, numeric_id, is_bot, capacity, volume, carrying_count)");
assertTest(array_key_exists('name', $stdPlayer) && array_key_exists('health', $stdPlayer), "Standard bot receives UIbot with name, health, score, inventory in player");

$pm->resetWorld($testWorldId);

// Clean up single test world
$pm->resetWorld('main');

echo "\n=== Summary: {$passCount} passed, {$failCount} failed ===\n";
exit($failCount > 0 ? 1 : 0);
