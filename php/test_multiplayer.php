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

// 6b: Bot with username and password should authenticate and return game snapshot
$jsonOutput = runMultiplayer([
    'format'   => 'json',
    'username' => 'DirectApiBot',
    'password' => 'botSecret456',
    'cmd'      => 'look',
]);
$decoded = json_decode($jsonOutput, true);

assertTest(is_array($decoded), "multiplayer.php returned valid JSON");
assertTest(($decoded['success'] ?? false) === true, "JSON response indicates success: true");
assertTest(isset($decoded['room']['id']), "JSON response includes room snapshot");
assertTest(isset($decoded['player']['name']) && $decoded['player']['name'] === 'DirectApiBot', "JSON response includes player name 'DirectApiBot'");
assertTest(isset($decoded['player']['numeric_id']) && is_numeric($decoded['player']['numeric_id']), "JSON response includes numeric_id: " . ($decoded['player']['numeric_id'] ?? 0));
assertTest(($decoded['player']['is_bot'] ?? false) === true, "JSON response player has is_bot = true");

// 6c: Bot with wrong password on existing account
$jsonOutputWrongPass = runMultiplayer([
    'format'   => 'json',
    'username' => 'DirectApiBot',
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
    'username' => 'DirectApiBot',
    'password' => 'botSecret456',
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
assertTest(in_array('go', $chatCmds, true), "Move commands ('go') are always valid");
assertTest(!in_array('look', $chatCmds, true), "Chat panel DOES NOT allow 'look' command");
assertTest(!in_array('pick', $chatCmds, true), "Chat panel DOES NOT allow 'pick' command");
assertTest(!in_array('use', $chatCmds, true), "Chat panel DOES NOT allow 'use' command");

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
assertTest(($botData['panel']['id'] ?? '') === 'chat', "Bot received active panel 'chat'");
assertTest(
    in_array('go', $botData['panel']['commands'] ?? [], true),
    "Bot panel commands include 'go': " . json_encode($botData['panel']['commands'] ?? [])
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

// Bot tries disallowed command 'look' in chat panel
$disallowedJson = runMultiplayer([
    'format'   => 'json',
    'username' => $botW2,
    'password' => 'botPass123',
    'cmd'      => 'look',
]);
$disallowedData = json_decode($disallowedJson, true);
$disallowedMsg = implode(' ', $disallowedData['messages'] ?? []);
assertTest(
    str_contains($disallowedMsg, "Command 'look' is not allowed in current panel (chat)"),
    "Bot sending 'look' is rejected in chat panel: '{$disallowedMsg}'"
);

// Bot moves using 'go U' (Up to rocca) - move command is valid and succeeds
$goJson = runMultiplayer([
    'format'   => 'json',
    'username' => $botW2,
    'password' => 'botPass123',
    'cmd'      => 'go',
    'param'    => 'U',
]);
$goData = json_decode($goJson, true);
assertTest(($goData['room']['id'] ?? '') === 'rocca', "Move command 'go U' took player to 'rocca': " . ($goData['room']['id'] ?? 'none'));

// Human HTML verification: Navigation exits remain visible and active
$humanHtmlOutput = runMultiplayer([
    'format'   => 'html',
    'username' => 'HumanChatHero',
    'password' => 'humanPass123',
]);
assertTest(str_contains($humanHtmlOutput, 'id="panel_chat"'), "Human player gets HTML panel with id='panel_chat'");
assertTest(str_contains($humanHtmlOutput, 'name="txtBox"'), "Human player gets speech textbox");
assertTest(str_contains($humanHtmlOutput, "sendCmd('say')"), "Human player gets Say button");
assertTest(str_contains($humanHtmlOutput, "sendCmd('save')"), "Human player gets Save button");
assertTest(str_contains($humanHtmlOutput, "sendCmd('exit')"), "Human player gets Exit button");
assertTest(str_contains($humanHtmlOutput, "sendCmd('go','U')"), "Human player STILL SEES exit button for [U] to the rock");
assertTest(!str_contains($humanHtmlOutput, "sendCmd('look')"), "Human player DOES NOT see Look button");
assertTest(!str_contains($humanHtmlOutput, "sendCmd('pick')"), "Human player DOES NOT see Pick button");
assertTest(!str_contains($humanHtmlOutput, "sendCmd('use')"), "Human player DOES NOT see Use button");

$pm->resetWorld($testWorldId);

// Clean up single test world
$pm->resetWorld('main');

echo "\n=== Summary: {$passCount} passed, {$failCount} failed ===\n";
exit($failCount > 0 ? 1 : 0);
