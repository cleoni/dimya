<?php
declare(strict_types=1);

/**
 * test_multiplayer.php
 * Automated tests for UI hierarchy, GameEngine, UIhtml, and UIjson bot API endpoint.
 */

require_once __DIR__ . '/autoload.php';

use Dimx\UI;
use Dimx\UIhtml;
use Dimx\UIjson;
use Dimx\GameEngine;
use Dimx\PersistenceManager;
use Dimx\Player;

echo "=== Running DimensioneX Multiplayer & UI Tests ===\n\n";

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
// Test 2: Input Parsing
// -------------------------------------------------------------
echo "\n2. Testing UI::parseInput()...\n";
$_GET = ['cmd' => 'go', 'dir' => 'N', 'player' => 'Explorer1'];
$input = UI::parseInput();
assertTest($input['username'] === 'Explorer1', "Parsed username 'Explorer1'");
assertTest($input['cmd'] === 'go', "Parsed cmd 'go'");
assertTest($input['param'] === 'N', "Parsed param 'N' from 'dir'");

// -------------------------------------------------------------
// Test 3: GameEngine Setup & Core Processing
// -------------------------------------------------------------
echo "\n3. Testing GameEngine initialization and world seeding...\n";
$config   = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
$dbConfig = $config['db'] ?? [];
$baseUrl  = $config['base_url'] ?? '';
$pm       = new PersistenceManager($dbConfig, __DIR__ . '/data');

$testWorldId = 'test_engine_' . uniqid();
$engine = new GameEngine($pm, $testWorldId, null, $baseUrl);

$world = $engine->loadOrSeedWorld();
assertTest($world !== null, "Engine seeded world successfully");
assertTest(isset($world->rooms['beach']), "Seeded world contains 'beach' room");
assertTest(isset($world->rooms['bar']), "Seeded world contains 'bar' room");
assertTest(isset($world->items['apple']), "Seeded world contains 'apple' item");

// -------------------------------------------------------------
// Test 4: Bot Player Command Dispatch via UIjson
// -------------------------------------------------------------
echo "\n4. Testing Bot Player via UIjson API...\n";
$uiJson = new UIjson();
$botName = 'BotTester_' . rand(100, 999);

// Step 4a: Login & initial look
$result1 = $engine->processCommand($botName, 'look', '', '', $uiJson);
$player = $result1['player'];
assertTest($player instanceof Player, "Bot player created as Player instance");
assertTest($player->getUI() === $uiJson, "Player has UI property set to UIjson instance");
assertTest($player->getRoom()->id === 'beach', "Bot player starts in 'beach' room");

// Verify room snapshot structure
$snapshot = $uiJson->buildRoomSnapshot($player->getRoom(), $player);
assertTest($snapshot['id'] === 'beach', "Room snapshot ID is 'beach'");
assertTest(!empty($snapshot['description']), "Room snapshot contains description");
assertTest(count($snapshot['items']) === 1 && $snapshot['items'][0]['id'] === 'apple', "Room snapshot contains 'apple' item");
assertTest(count($snapshot['exits']) === 1 && $snapshot['exits'][0]['direction'] === 'E', "Room snapshot contains 'E' exit");

// Step 4b: Bot picks up apple
$result2 = $engine->processCommand($botName, 'pick', '', 'apple', $uiJson);
assertTest(isset($result2['player']->contents['apple']), "Bot player now has 'apple' in inventory");
assertTest(!isset($result2['room']->contents['apple']), "'apple' removed from beach room contents");

$playerStatus = $uiJson->buildPlayerStatus($result2['player']);
assertTest($playerStatus['carrying_count'] === 1, "Player status carrying_count is 1");
assertTest($playerStatus['inventory'][0]['id'] === 'apple', "Player status inventory lists 'apple'");

// Step 4c: Bot travels East to Tiki Bar
$result3 = $engine->processCommand($botName, 'go', 'E', '', $uiJson);
assertTest($result3['player']->getRoom()->id === 'bar', "Bot travelled East and is now in 'bar'");

// Step 4d: Bot drops apple in Tiki Bar
$result4 = $engine->processCommand($botName, 'drop', '', 'apple', $uiJson);
assertTest(!isset($result4['player']->contents['apple']), "Bot dropped 'apple', no longer in inventory");
assertTest(isset($result4['room']->contents['apple']), "'apple' is now inside 'bar' room contents");

// Step 4e: Bot talks
$result5 = $engine->processCommand($botName, 'say', 'Greetings from AI bot!', '', $uiJson);
assertTest(in_array("<b>{$botName}</b> says: Greetings from AI bot!", $result5['messages'], true), "Bot speech captured in messages");

// -------------------------------------------------------------
// Test 5: Simulating multiplayer.php entrypoint for Bot (JSON)
// -------------------------------------------------------------
echo "\n5. Testing multiplayer.php entrypoint with bot JSON query...\n";
$_GET = [
    'format'   => 'json',
    'username' => 'DirectApiBot',
    'cmd'      => 'look',
    'world'    => $testWorldId,
];
$_POST = [];

ob_start();
require __DIR__ . '/multiplayer.php';
$jsonOutput = ob_get_clean();

$decoded = json_decode($jsonOutput, true);
assertTest(is_array($decoded), "multiplayer.php returned valid JSON");
assertTest(($decoded['success'] ?? false) === true, "JSON response indicates success: true");
assertTest(isset($decoded['room']['id']), "JSON response includes room snapshot: " . ($decoded['room']['name'] ?? ''));
assertTest(isset($decoded['player']['name']) && $decoded['player']['name'] === 'DirectApiBot', "JSON response includes player record: " . ($decoded['player']['name'] ?? ''));

// -------------------------------------------------------------
// Test 6: Simulating multiplayer.php entrypoint for Human (HTML)
// -------------------------------------------------------------
echo "\n6. Testing multiplayer.php entrypoint with human HTML player...\n";
$_GET = [
    'username' => 'HumanHero',
    'cmd'      => 'look',
    'world'    => $testWorldId,
];
$_POST = [];
$_SERVER['HTTP_ACCEPT'] = 'text/html';

ob_start();
require __DIR__ . '/multiplayer.php';
$htmlOutput = ob_get_clean();

assertTest(str_contains($htmlOutput, '<!DOCTYPE html>'), "multiplayer.php returned HTML doctype");
assertTest(str_contains($htmlOutput, 'HumanHero'), "HTML output includes player name 'HumanHero'");
assertTest(str_contains($htmlOutput, 'Sunny Beach'), "HTML output includes room name 'Sunny Beach'");
assertTest(str_contains($htmlOutput, 'id="consoleBox"'), "HTML output includes consoleBox element");
assertTest(str_contains($htmlOutput, 'id="chatBox"'), "HTML output includes chatBox element");

// -------------------------------------------------------------
// Test 7: Testing login prompt for Bot (JSON) and Human (HTML)
// -------------------------------------------------------------
echo "\n7. Testing login prompt for Bot (JSON) and Human (HTML)...\n";
$_GET = ['format' => 'json'];
$_POST = [];
unset($_SESSION['dimx_player']);

ob_start();
// Test UIjson directly
$jsonUi = new UIjson();
$jsonUi->renderLogin('test.dxw');
$loginJson = ob_get_clean();
$decodedLogin = json_decode($loginJson, true);
assertTest(($decodedLogin['status'] ?? '') === 'login_required', "UIjson login status is 'login_required'");
assertTest(($decodedLogin['world_file'] ?? '') === 'test.dxw', "UIjson login includes world_file");

ob_start();
$htmlUi = new UIhtml();
$htmlUi->renderLogin('test.dxw');
$loginHtml = ob_get_clean();
assertTest(str_contains($loginHtml, 'Enter the World'), "UIhtml login includes 'Enter the World' form");
assertTest(str_contains($loginHtml, 'name="username"'), "UIhtml login includes username input");

// -------------------------------------------------------------
// Test 8: Bot Logout / Disconnect & Room Occupant Verification
// -------------------------------------------------------------
echo "\n8. Testing Bot Logout & Occupant Removal...\n";
// Bot 1 connects
$_GET = ['format' => 'json', 'username' => 'Bot1', 'cmd' => 'look', 'world' => $testWorldId];
ob_start();
require __DIR__ . '/multiplayer.php';
ob_get_clean();

// Bot 2 connects and looks around - should see Bot1 in occupants
$_GET = ['format' => 'json', 'username' => 'Bot2', 'cmd' => 'look', 'world' => $testWorldId];
ob_start();
require __DIR__ . '/multiplayer.php';
$bot2Json = ob_get_clean();
$bot2Data = json_decode($bot2Json, true);
$occupantsBefore = array_column($bot2Data['room']['occupants'] ?? [], 'name');
assertTest(in_array('Bot1', $occupantsBefore, true), "Bot2 sees Bot1 in room occupants");

// Bot 1 logs out
$_GET = ['format' => 'json', 'username' => 'Bot1', 'cmd' => 'logout', 'world' => $testWorldId];
ob_start();
require __DIR__ . '/multiplayer.php';
$logoutJson = ob_get_clean();
$logoutData = json_decode($logoutJson, true);
assertTest(($logoutData['status'] ?? '') === 'logged_out', "Bot1 logout response status is 'logged_out'");
assertTest(($logoutData['logged_out'] ?? false) === true, "Bot1 logout response flag logged_out is true");
assertTest(array_key_exists('room', $logoutData) && $logoutData['room'] === null, "Bot1 logout response room snapshot is null");

// Bot 2 looks again - Bot1 should NO LONGER be in occupants!
$_GET = ['format' => 'json', 'username' => 'Bot2', 'cmd' => 'look', 'world' => $testWorldId];
ob_start();
require __DIR__ . '/multiplayer.php';
$bot2JsonAfter = ob_get_clean();
$bot2DataAfter = json_decode($bot2JsonAfter, true);
$occupantsAfter = array_column($bot2DataAfter['room']['occupants'] ?? [], 'name');
assertTest(!in_array('Bot1', $occupantsAfter, true), "Bot2 NO LONGER sees Bot1 after Bot1 logged out");

// Clean up test world
$pm->resetWorld($testWorldId);

echo "\n=== Summary: {$passCount} passed, {$failCount} failed ===\n";
exit($failCount > 0 ? 1 : 0);
