<?php
declare(strict_types=1);

/**
 * multiplayer.php
 *
 * DimensioneX Web Player Interface.
 *
 * Concurrency model:
 *  - The authoritative world state lives in MySQL (dimx_worlds.state_json).
 *  - Every player action that mutates game objects is wrapped in a
 *    WorldLock::executeAtomic() critical section backed by MySQL GET_LOCK().
 *  - The PHP session stores ONLY player_id and world_id — not the world object.
 *  - Chat messages are written/read outside the lock (dimx_messages table).
 *  - The browser polls chat.php every 3 seconds via JS fetch() for new messages.
 */

session_start();
require_once __DIR__ . '/autoload.php';

use Dimx\World;
use Dimx\Room;
use Dimx\Item;
use Dimx\Vehicle;
use Dimx\Character;
use Dimx\Player;
use Dimx\Link;
use Dimx\Skin;
use Dimx\PersistenceManager;
use Dimx\WorldLock;

// ---------------------------------------------------------------------------
// 1. Bootstrap config & persistence
// ---------------------------------------------------------------------------
$config   = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
$dbConfig = $config['db'] ?? [];
$baseUrl  = $config['base_url'] ?? '';
$pm       = new PersistenceManager($dbConfig, __DIR__ . '/data');

// ---------------------------------------------------------------------------
// 2. Determine world ID and player name from request / session
// ---------------------------------------------------------------------------
$requestedWorldFile = $_GET['world'] ?? null;
$resetWorld         = isset($_GET['reset']) && $_GET['reset'] === '1';
$playerName         = trim($_POST['username'] ?? $_SESSION['dimx_player'] ?? '');

if (empty($playerName)) {
    // Show login form
    ?><!DOCTYPE html>
    <html lang="en">
    <head><meta charset="UTF-8"><title>DimensioneX — Login</title>
    <style>body{background:#0f172a;color:#f8fafc;font-family:system-ui;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
    .box{background:#1e293b;padding:36px 40px;border-radius:12px;border:1px solid #334155;width:320px}
    h2{margin:0 0 20px;color:#38bdf8}input{width:100%;padding:8px;background:#0f172a;border:1px solid #475569;border-radius:4px;color:#f8fafc;font-size:14px;box-sizing:border-box;margin-bottom:14px}
    button{width:100%;padding:10px;background:#2563eb;color:#fff;border:none;border-radius:4px;font-weight:600;cursor:pointer}</style>
    </head><body>
    <div class="box">
      <h2>Enter the World</h2>
      <form method="POST">
        <input type="text" name="username" placeholder="Your name…" required autofocus maxlength="32" />
        <?php if ($requestedWorldFile): ?>
          <input type="hidden" name="world_file" value="<?= htmlspecialchars($requestedWorldFile) ?>" />
        <?php endif; ?>
        <button type="submit">Play</button>
      </form>
    </div>
    </body></html>
    <?php
    exit;
}

// World file (from POST carry-through, GET, or config default)
$worldFile = $_POST['world_file'] ?? $requestedWorldFile ?? $config['default_world'] ?? null;

// Derive a stable world_id from the world file name (or use a default)
$worldId = 'default';
if (!empty($worldFile)) {
    $worldId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', basename($worldFile, '.dxw'));
}

$_SESSION['dimx_player'] = $playerName;
$_SESSION['dimx_world']  = $worldId;

// ---------------------------------------------------------------------------
// 3. Get PDO and WorldLock
// ---------------------------------------------------------------------------
$pdo  = $pm->getPdo();
$lock = ($pdo !== null) ? new WorldLock($pdo) : null;

// Register a shutdown handler so the lock is always released even on fatal errors
if ($lock !== null) {
    register_shutdown_function(fn() => $lock->releaseAll());
}

// ---------------------------------------------------------------------------
// 4. Helper: load or seed world (called inside the lock)
// ---------------------------------------------------------------------------
function loadOrSeedWorld(PersistenceManager $pm, string $worldId, ?string $worldFile, string $baseUrl): World
{
    // Try loading from DB first
    $world = $pm->loadWorld($worldId);
    if ($world !== null) {
        $world->persistence = $pm;
        return $world;
    }

    // Not found — seed from .dxw file
    $loader = new \Dimx\DxwLoader($baseUrl);
    if (!empty($worldFile)) {
        try {
            $world = $loader->load($worldFile);
        } catch (\Throwable $e) {
            error_log("Failed loading world '{$worldFile}': " . $e->getMessage());
        }
    }

    if ($world === null) {
        // Fallback demo world
        $world = new World($worldId, 'DimensioneX Demo World');
        $beach = new \Dimx\Room($world, 'Sunny Beach', 'beach', 'A warm sandy beach.');
        $bar   = new \Dimx\Room($world, 'Tiki Bar', 'bar', 'A lively bamboo bar.');
        $world->addRoom($beach);
        $world->addRoom($bar);
        new \Dimx\Link($world, 'Path to Bar', 'path_bar', $beach, $bar, true, 'E');
        $apple = new \Dimx\Item($world, 'Fresh Apple', 'apple', 'A crisp red apple.', null, 'pickable=1,Value=2');
        $apple->type = 'food.apple';
        $apple->move($beach);
        $world->addItem($apple);
    }

    $world->id          = $worldId;
    $world->persistence = $pm;

    // Persist seed to DB so future requests load from DB
    $pm->saveWorld($world, $worldFile);

    return $world;
}

// ---------------------------------------------------------------------------
// 5. Handle command — entire mutation wrapped in critical section
// ---------------------------------------------------------------------------
$cmd      = strtolower($_POST['cmd'] ?? $_GET['cmd'] ?? 'look');
$param    = trim($_POST['param']  ?? $_GET['param']  ?? '');
$targetId = trim($_POST['target'] ?? $_GET['target'] ?? '');

$consoleMessages = [];
$lockError       = false;

// All commands that mutate world state run inside the lock.
// 'say' is also inside the lock because it modifies player console buffers
// that are part of the serialized world.
$mutating = in_array($cmd, ['go', 'pick', 'drop', 'use', 'say', 'look', 'savexit'], true);

if ($pdo !== null && $lock !== null) {
    $result = $lock->executeAtomic($worldId, function (\PDO $pdo) use (
        $pm, $worldId, $worldFile, $baseUrl,
        $playerName, $cmd, $param, $targetId, &$consoleMessages
    ): array {
        // --- Load world from DB (fresh read inside lock) ---
        $world = loadOrSeedWorld($pm, $worldId, $worldFile, $baseUrl);

        // --- Ensure player exists in world ---
        $player = $world->getPlayer($playerName);
        if ($player === null) {
            $player = new \Dimx\Player($world, $playerName, $playerName, 'Adventurer');
            $player->skin = new \Dimx\Skin('default', 'Ocean');
            $startRoom = null;
            foreach ($world->rooms as $r) {
                if ($r->getProperty('DEFAULT')) { $startRoom = $r; break; }
            }
            if ($startRoom === null) { $startRoom = reset($world->rooms) ?: null; }
            if ($startRoom !== null) { $player->move($startRoom); }
            $world->addPlayer($player);
            $player->display("Welcome to <b>{$world->name}</b>, {$player->name}!");
        }

        $currentRoom = $player->getRoom();

        // --- Dispatch command ---
        switch ($cmd) {
            case 'look':
                if (!empty($targetId) && ($to = $world->contents[$targetId] ?? null) instanceof \Dimx\Item) {
                    $player->display("<b>{$to->name}</b>: {$to->evalDescription($player)}");
                } else {
                    $player->display("<b>{$currentRoom->name}</b>: {$currentRoom->evalDescription($player)}");
                }
                break;

            case 'go':
                $dir = $param ?: $targetId;
                if (!empty($dir)) {
                    if ($player->go($dir)) {
                        $player->display("You travel to <b>{$player->getRoom()->name}</b>.");
                    } else {
                        $player->display("You cannot go that way.");
                    }
                }
                break;

            case 'pick':
                if (!empty($targetId) && ($item = $currentRoom->contents[$targetId] ?? null) instanceof \Dimx\Item) {
                    // Re-verify item is still in room (not picked by another player)
                    if ($item->container === $currentRoom && $item->pickable && $item->move($player)) {
                        $player->display("You picked up the <b>{$item->name}</b>.");
                        $world->fireEvent('whenPicked', $item, $player, $player);
                    } else {
                        $player->display("The {$item->name} is no longer available.");
                    }
                }
                break;

            case 'drop':
                if (!empty($targetId) && ($item = $player->contents[$targetId] ?? null) instanceof \Dimx\Item) {
                    if ($item->move($currentRoom)) {
                        $player->display("You dropped the <b>{$item->name}</b>.");
                        $world->fireEvent('whenDropped', $item, $player, $currentRoom);
                    }
                }
                break;

            case 'use':
                if (!empty($targetId)) {
                    $item = $player->contents[$targetId] ?? $currentRoom->contents[$targetId] ?? null;
                    if ($item) {
                        $res = $world->fireEvent('onUse', $item, $player, null);
                        if (!$res) { $player->display("Using {$item->name} had no obvious effect."); }
                    }
                }
                break;

            case 'say':
                // Chat via DB (outside lock for the actual message table, but we still
                // broadcast to the world's player console buffers here, inside lock).
                if (!empty($param)) {
                    // Broadcast to all players in the world via their console buffers
                    foreach ($world->players as $p) {
                        $p->display("<b>{$player->name}</b> says: " . htmlspecialchars($param));
                    }
                    foreach ($currentRoom->getOccupants() as $occ) {
                        if ($occ !== $player) {
                            $world->fireEvent('onHear', $occ, $player, $param);
                        }
                    }
                }
                break;

            case 'savexit':
                $pm->savePlayer($player);
                $player->display("Game saved! You may safely close this window.");
                break;
        }

        // Advance clock tick
        $world->tick();

        // Drain this player's console before saving
        $msgs = $player->drainConsole();

        // Save updated world back to DB
        $pm->saveWorld($world);

        return ['world' => $world, 'player' => $player, 'msgs' => $msgs];
    });

    if ($result === null) {
        $lockError = true;
        $consoleMessages = ["<span style='color:#f87171'>⚠ The game world is busy. Please try again in a moment.</span>"];
        // Load a read-only copy of world/player for rendering (best-effort)
        $world  = $pm->loadWorld($worldId) ?? new World($worldId, 'DimensioneX');
        $world->persistence = $pm;
        $player = $world->getPlayer($playerName);
        if ($player === null) {
            $player = new \Dimx\Player($world, $playerName, $playerName);
        }
    } else {
        $world  = $result['world'];
        $player = $result['player'];
        $consoleMessages = $result['msgs'];
    }

    // Post 'say' messages to the chat table (outside lock — concurrent is fine)
    if ($cmd === 'say' && !empty($param) && !$lockError) {
        $pm->postMessage($worldId, $playerName, htmlspecialchars($param));
    }
} else {
    // No DB — degrade gracefully to session-based (single-player, no concurrency)
    if ($resetWorld || !isset($_SESSION['dimx_world_obj'])) {
        $world = loadOrSeedWorld($pm, $worldId, $worldFile, $baseUrl);
        $_SESSION['dimx_world_obj'] = serialize($world);
    } else {
        $world = unserialize($_SESSION['dimx_world_obj']);
        $world->persistence = $pm;
    }
    $player = $world->getPlayer($playerName);
    if ($player === null) {
        $player = new \Dimx\Player($world, $playerName, $playerName, 'Adventurer');
        $player->skin = new \Dimx\Skin('default', 'Ocean');
        $start = reset($world->rooms) ?: null;
        if ($start) { $player->move($start); }
        $world->addPlayer($player);
        $player->display("Welcome to <b>{$world->name}</b>, {$player->name}!");
    }
    // Minimal command handling in fallback
    if ($cmd === 'look') {
        $r = $player->getRoom();
        $player->display("<b>{$r->name}</b>: {$r->evalDescription($player)}");
    }
    $world->tick();
    $consoleMessages = $player->drainConsole();
    $_SESSION['dimx_world_obj'] = serialize($world);
}

// ---------------------------------------------------------------------------
// 6. Collect render data
// ---------------------------------------------------------------------------
$skin         = $player->skin ?? new Skin();
$currentRoom  = $player->getRoom();
$occupants    = $currentRoom ? $currentRoom->getOccupants() : [];
$roomItems    = $currentRoom ? $currentRoom->getItems()     : [];
$inventory    = $player->contents;
$links        = $currentRoom ? $currentRoom->getLinks()     : [];
$storageLabel = $pm->checkConnection()['type'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($world->name) ?> — DimensioneX</title>
  <style>
    <?= $skin->getCss() ?>
    *{box-sizing:border-box}
    body{margin:0;background:#0a0f1e;color:#e2e8f0;font-family:ui-sans-serif,system-ui,sans-serif}
    .dimx-layout{display:grid;grid-template-columns:1fr 340px;gap:16px;max-width:1280px;margin:20px auto;padding:0 16px}
    .dimx-scene{background:#0f172a;border-radius:8px;overflow:hidden;border:1px solid #334155;position:relative;min-height:350px}
    .dimx-scene-img{width:100%;height:350px;object-fit:cover;display:block}
    .dimx-console{background:#090d16;border:1px solid #334155;border-radius:8px;padding:12px;height:150px;overflow-y:auto;font-size:13px;margin-top:12px;line-height:1.6}
    .dimx-chat-box{background:#090d16;border:1px solid #334155;border-radius:8px;padding:12px;height:150px;overflow-y:auto;font-size:13px;margin-top:12px;line-height:1.6}
    .dimx-chat-input{display:flex;gap:6px;margin-top:6px}
    .dimx-chat-input input{flex:1;padding:6px 10px;background:#1e293b;border:1px solid #475569;border-radius:4px;color:#f8fafc;font-size:13px}
    .dimx-chat-input button{padding:6px 12px;background:#0ea5e9;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px}
    .dimx-sidebar-box{background:#1e293b;border:1px solid #334155;border-radius:8px;padding:14px;margin-bottom:12px}
    .dimx-sidebar-box h4{margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8}
    .dimx-tag{display:inline-flex;align-items:center;gap:4px;background:#334155;padding:3px 8px;border-radius:4px;font-size:12px;margin:2px;cursor:pointer}
    .dimx-tag:hover{background:#475569}
    .dimx-btn{background:#1e40af;color:#f8fafc;border:1px solid #3b82f6;padding:4px 10px;border-radius:4px;cursor:pointer;font-size:12px}
    .dimx-btn:hover{background:#1d4ed8}
    .chat-line-sender{color:#38bdf8;font-weight:600}
    .chat-line-time{color:#64748b;font-size:11px}
    header{background:#0f172a;border-bottom:1px solid #334155;padding:12px 24px;display:flex;justify-content:space-between;align-items:center}
  </style>
</head>
<body>

  <!-- Header -->
  <header>
    <div>
      <h2 style="margin:0;font-size:18px;font-weight:700;color:#38bdf8"><?= htmlspecialchars($world->name) ?></h2>
      <span style="font-size:12px;color:#94a3b8">
        📍 <b><?= htmlspecialchars($currentRoom->name ?? 'Unknown') ?></b> &nbsp;|&nbsp;
        👤 <b><?= htmlspecialchars($player->name) ?></b> (Score: <?= $player->score ?>)
        &nbsp;|&nbsp; 💾 <?= htmlspecialchars($storageLabel) ?>
        <?php if ($lockError): ?><span style="color:#f87171"> ⚠ Lock busy</span><?php endif; ?>
      </span>
    </div>
    <div>
      <a href="maint.php" style="color:#94a3b8;font-size:12px;text-decoration:none;padding:4px 8px;border:1px solid #475569;border-radius:4px">⚙ Maintenance</a>
      &nbsp;
      <a href="?reset=1" style="color:#94a3b8;font-size:12px;text-decoration:none;padding:4px 8px;border:1px solid #475569;border-radius:4px">🔄 Reset World</a>
    </div>
  </header>

  <div class="dimx-layout">

    <!-- Left Column: Scene, Console, Chat -->
    <div>
      <!-- Scene image -->
      <div class="dimx-scene">
        <?php if (!empty($currentRoom->images['N'])): ?>
          <img src="<?= htmlspecialchars($currentRoom->images['N']->src) ?>" class="dimx-scene-img" alt="Scene" />
        <?php else: ?>
          <div style="height:350px;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:16px">
            [<?= htmlspecialchars($currentRoom->name ?? 'Scene') ?>]
          </div>
        <?php endif; ?>

        <!-- Entities overlay -->
        <div style="position:absolute;bottom:12px;left:12px;display:flex;flex-wrap:wrap;gap:6px">
          <?php foreach ($occupants as $char): ?>
            <?php if ($char !== $player): ?>
              <span class="dimx-tag" onclick="sendCmd('look','','<?= htmlspecialchars($char->id) ?>')">👤 <?= htmlspecialchars($char->name) ?></span>
            <?php endif; ?>
          <?php endforeach; ?>
          <?php foreach ($roomItems as $itm): ?>
            <span class="dimx-tag" onclick="sendCmd('pick','','<?= htmlspecialchars($itm->id) ?>')">📦 <?= htmlspecialchars($itm->name) ?></span>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Action console (private to this player) -->
      <div class="dimx-console" id="consoleBox">
        <?php foreach ($consoleMessages as $line): ?>
          <div><?= $line ?></div>
        <?php endforeach; ?>
      </div>

      <!-- Command form (hidden inputs) -->
      <div style="margin-top:12px">
        <form id="cmdForm" method="POST" action="multiplayer.php">
          <input type="hidden" name="cmd"    id="cmdInput"    value="look" />
          <input type="hidden" name="param"  id="paramInput"  value="" />
          <input type="hidden" name="target" id="targetInput" value="" />
          <?= $player->getPanel()?->toHtml($skin) ?>
        </form>
      </div>

      <!-- Chat box (shared, async-polled) -->
      <div style="margin-top:16px">
        <div style="font-size:12px;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">💬 World Chat</div>
        <div class="dimx-chat-box" id="chatBox"></div>
        <div class="dimx-chat-input">
          <input type="text" id="chatInput" placeholder="Say something…" maxlength="256"
                 onkeydown="if(event.key==='Enter'){sendChat();event.preventDefault()}" />
          <button onclick="sendChat()">Send</button>
        </div>
      </div>
    </div>

    <!-- Right Column -->
    <div>
      <!-- Exits -->
      <div class="dimx-sidebar-box">
        <h4>🗺 Exits</h4>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
          <?php foreach ($links as $link): ?>
            <?php $dir = $link->getDirection($currentRoom->id); ?>
            <button type="button" class="dimx-btn" onclick="sendCmd('go','<?= htmlspecialchars($dir) ?>')">
              🚪 [<?= htmlspecialchars($dir) ?>] <?= htmlspecialchars($link->name) ?>
            </button>
          <?php endforeach; ?>
          <?php if (empty($links)): ?>
            <span style="font-size:12px;color:#64748b">No visible exits.</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Inventory -->
      <div class="dimx-sidebar-box">
        <h4>🎒 Inventory</h4>
        <div style="display:flex;flex-direction:column;gap:6px">
          <?php foreach ($inventory as $item): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;background:#0f172a;padding:6px 10px;border-radius:4px;font-size:12px">
              <span><b><?= htmlspecialchars($item->name) ?></b> (vol: <?= $item->volume ?>)</span>
              <div style="display:flex;gap:4px">
                <button type="button" class="dimx-btn" onclick="sendCmd('use','','<?= htmlspecialchars($item->id) ?>')">Use</button>
                <button type="button" class="dimx-btn" style="background:#b91c1c;border-color:#ef4444" onclick="sendCmd('drop','','<?= htmlspecialchars($item->id) ?>')">Drop</button>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (empty($inventory)): ?>
            <span style="font-size:12px;color:#64748b">Empty.</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Stats -->
      <div class="dimx-sidebar-box">
        <h4>📊 Stats</h4>
        <div style="font-size:13px;line-height:1.8">
          <div><b>Health:</b> <?= $player->getProperty('Health') ?? 100 ?></div>
          <div><b>Score:</b> <?= $player->score ?></div>
          <div><b>Carrying:</b> <?= count($player->contents) ?> / <?= $player->capacity ?></div>
        </div>
      </div>

      <!-- Quick actions -->
      <div class="dimx-sidebar-box">
        <h4>⚡ Actions</h4>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
          <button class="dimx-btn" onclick="sendCmd('look')">👁 Look</button>
          <button class="dimx-btn" onclick="sendCmd('savexit')">💾 Save &amp; Exit</button>
        </div>
      </div>
    </div>

  </div>

  <script>
    // ---- Game command submission ----
    function sendCmd(cmd, param = '', target = '') {
      document.getElementById('cmdInput').value   = cmd;
      document.getElementById('paramInput').value  = param;
      document.getElementById('targetInput').value = target;
      document.getElementById('cmdForm').submit();
    }

    // Auto-scroll console
    const cBox = document.getElementById('consoleBox');
    if (cBox) cBox.scrollTop = cBox.scrollHeight;

    // ---- Async chat ----
    const WORLD_ID   = <?= json_encode($worldId) ?>;
    const PLAYER_NAME = <?= json_encode($playerName) ?>;
    let lastMsgId = 0;

    function renderMessage(msg) {
      const div = document.createElement('div');
      div.innerHTML = '<span class="chat-line-time">[' + (msg.time || '') + ']</span> '
                    + '<span class="chat-line-sender">' + escHtml(msg.sender) + ':</span> '
                    + escHtml(msg.text);
      return div;
    }

    function escHtml(s) {
      return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    async function pollChat() {
      try {
        const url = 'chat.php?world_id=' + encodeURIComponent(WORLD_ID)
                  + '&after=' + lastMsgId;
        const resp = await fetch(url);
        if (!resp.ok) return;
        const data = await resp.json();
        if (data.messages && data.messages.length > 0) {
          const box = document.getElementById('chatBox');
          for (const msg of data.messages) {
            box.appendChild(renderMessage(msg));
            lastMsgId = Math.max(lastMsgId, msg.id);
          }
          box.scrollTop = box.scrollHeight;
        }
      } catch (e) { /* network error — silent */ }
    }

    async function sendChat() {
      const input = document.getElementById('chatInput');
      const text  = input.value.trim();
      if (!text) return;
      input.value = '';
      try {
        await fetch('chat.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: 'world_id=' + encodeURIComponent(WORLD_ID)
              + '&sender='   + encodeURIComponent(PLAYER_NAME)
              + '&text='     + encodeURIComponent(text)
        });
        // Poll immediately so sender sees their own message
        await pollChat();
      } catch (e) { /* silent */ }
    }

    // Initial load + recurring poll every 3 seconds
    pollChat();
    setInterval(pollChat, 3000);
  </script>
</body>
</html>
