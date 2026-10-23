<?php
declare(strict_types=1);

namespace Dimx;

/**
 * UIhtml
 *
 * Full HTML / graphical web client presentation layer for human players.
 * Renders the room scene images, entity tags, action console, player command panel,
 * inventory controls, live chat, and directional exit buttons.
 */
class UIhtml extends UI
{
    public function __construct(?Player $player = null, ?GameEngine $engine = null)
    {
        $this->type = 'html';
        $this->player = $player;
        $this->engine = $engine;
    }

    /**
     * Render the login form for human players.
     */
    public function renderLogin(?string $errorMessage = null, ?string $defaultUsername = null): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        $valUser = htmlspecialchars($defaultUsername ?? '');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <title>DimensioneX — Login</title>
          <style>
            body{background:#0f172a;color:#f8fafc;font-family:system-ui,-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
            .box{background:#1e293b;padding:36px 40px;border-radius:12px;border:1px solid #334155;width:340px;box-shadow:0 10px 25px rgba(0,0,0,0.5)}
            h2{margin:0 0 8px;color:#38bdf8}
            p.sub{margin:0 0 18px;font-size:13px;color:#94a3b8}
            .err{background:rgba(239,68,68,0.15);border:1px solid #ef4444;color:#fca5a5;padding:10px 12px;border-radius:6px;font-size:13px;margin-bottom:16px}
            label{display:block;font-size:12px;color:#cbd5e1;font-weight:600;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px}
            input{width:100%;padding:10px 12px;background:#0f172a;border:1px solid #475569;border-radius:6px;color:#f8fafc;font-size:14px;box-sizing:border-box;margin-bottom:16px}
            input:focus{outline:none;border-color:#38bdf8;box-shadow:0 0 0 2px rgba(56,189,248,0.25)}
            button{width:100%;padding:11px;background:#2563eb;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;font-size:14px;transition:background 0.2s}
            button:hover{background:#1d4ed8}
            .hint{font-size:11px;color:#64748b;margin-top:14px;text-align:center;line-height:1.4}
          </style>
        </head>
        <body>
          <div class="box">
            <h2>Enter the World</h2>
            <p class="sub">Log in with your nickname or create a new account.</p>
            <?php if (!empty($errorMessage)): ?>
              <div class="err"><?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>
            <form method="POST">
              <label for="username">Nickname</label>
              <input type="text" id="username" name="username" placeholder="Your nickname…" required autofocus maxlength="32" value="<?= $valUser ?>" />

              <label for="password">Password</label>
              <input type="password" id="password" name="password" placeholder="Your password…" required />

              <button type="submit">Play</button>
            </form>
            <div class="hint">If the nickname does not exist, an account will be created automatically.</div>
          </div>
        </body>
        </html>
        <?php
    }

    /**
     * Render an error page.
     */
    public function renderError(string $message, int $statusCode = 400): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: text/html; charset=utf-8');
        }
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="UTF-8">
          <title>DimensioneX — Error</title>
          <style>
            body{background:#0f172a;color:#f8fafc;font-family:system-ui;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
            .box{background:#1e293b;padding:30px;border-radius:8px;border:1px solid #ef4444;max-width:400px;text-align:center}
            h3{color:#f87171;margin-top:0}
            a{color:#38bdf8;text-decoration:none}
          </style>
        </head>
        <body>
          <div class="box">
            <h3>Notice</h3>
            <p><?= htmlspecialchars($message) ?></p>
            <p><a href="multiplayer.php">Back to Game</a></p>
          </div>
        </body>
        </html>
        <?php
    }

    /**
     * Render the full interactive web player view.
     */
    public function render(array $context): void
    {
        /** @var World $world */
        $world           = $context['world'];
        /** @var Player $player */
        $player          = $context['player'];
        /** @var Room|null $currentRoom */
        $currentRoom     = $context['room'] ?? $player->getRoom();
        $consoleMessages = $context['messages'] ?? [];
        $lockError       = (bool)($context['lockError'] ?? false);
        $worldId         = $context['worldId'] ?? $world->id;
        $playerName      = $player->name;

        // Render departure / logged-out screen
        if (!empty($context['logged_out'])) {
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=utf-8');
            }
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
              <meta charset="UTF-8">
              <meta name="viewport" content="width=device-width, initial-scale=1.0">
              <title><?= htmlspecialchars($world->name) ?> — Logged Out</title>
              <style>
                body{background:#0f172a;color:#f8fafc;font-family:system-ui;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
                .box{background:#1e293b;padding:36px 40px;border-radius:12px;border:1px solid #334155;width:340px;text-align:center;box-shadow:0 10px 25px rgba(0,0,0,0.5)}
                h2{margin:0 0 16px;color:#38bdf8}
                p{font-size:14px;color:#94a3b8;line-height:1.6}
                .btn{display:inline-block;margin-top:16px;padding:10px 20px;background:#2563eb;color:#fff;border-radius:4px;text-decoration:none;font-weight:600}
                .btn:hover{background:#1d4ed8}
              </style>
            </head>
            <body>
              <div class="box">
                <h2>Goodbye, <?= htmlspecialchars($player->name) ?></h2>
                <p>Your game was saved and you have safely left <b><?= htmlspecialchars($world->name) ?></b>.</p>
                <a href="multiplayer.php" class="btn">Play Again</a>
              </div>
            </body>
            </html>
            <?php
            return;
        }

        $skin         = $player->skin ?? new Skin();
        $occupants    = $currentRoom ? $currentRoom->getOccupants() : [];
        $roomItems    = $currentRoom ? $currentRoom->getItems()     : [];
        $inventory    = $player->contents;
        $links        = $currentRoom ? $currentRoom->getLinks()     : [];

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            .dimx-sidebar-box{background:#1e293b;border:1px solid #334155;border-radius:8px;padding:14px;margin-bottom:12px}
            .dimx-sidebar-box h4{margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8}
            .dimx-sidebar-box .dimx-panel{background:transparent;padding:0;display:flex;flex-wrap:wrap;gap:6px;align-items:center}
            .dimx-sidebar-box .dimx-textbox{flex:1 1 160px;min-width:120px;padding:6px 10px;border-radius:4px;border:1px solid #475569;background:#0f172a;color:#f8fafc;font-size:13px;box-sizing:border-box}
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
                👤 <b><?= htmlspecialchars($player->name) ?></b>
                <?php if ($lockError): ?><span style="color:#f87171"> &nbsp;|&nbsp; ⚠ Lock busy</span><?php endif; ?>
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
                      <span class="dimx-tag" onclick="sendCmd('click','','<?= htmlspecialchars($char->id) ?>')">👤 <?= htmlspecialchars($char->name) ?></span>
                    <?php endif; ?>
                  <?php endforeach; ?>
                  <?php foreach ($roomItems as $itm): ?>
                    <span class="dimx-tag" onclick="sendCmd('click','','<?= htmlspecialchars($itm->id) ?>')">📦 <?= htmlspecialchars($itm->name) ?></span>
                  <?php endforeach; ?>
                </div>
              </div>

              <!-- Action console (private to this player) -->
              <div class="dimx-console" id="consoleBox">
                <?php foreach ($consoleMessages as $line): ?>
                  <div><?= $line ?></div>
                <?php endforeach; ?>
              </div>

              <!-- Chat box (shared, async-polled) -->
              <div style="margin-top:16px">
                <div style="font-size:12px;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">💬 World Chat</div>
                <div class="dimx-chat-box" id="chatBox"></div>
              </div>
            </div>

            <!-- Right Column -->
            <div>
              <?php $activePanel = $player->getPanel() ?? $world->defaultPanel; ?>
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
                        <?php if ($activePanel?->isCommandAllowed('use')): ?>
                          <button type="button" class="dimx-btn" onclick="sendCmd('use','','<?= htmlspecialchars($item->id) ?>')">Use</button>
                        <?php endif; ?>
                        <?php if ($activePanel?->isCommandAllowed('drop')): ?>
                          <button type="button" class="dimx-btn" style="background:#b91c1c;border-color:#ef4444" onclick="sendCmd('drop','','<?= htmlspecialchars($item->id) ?>')">Drop</button>
                        <?php endif; ?>
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
                  <?php
                  $hasStats = false;
                  foreach ($player->getProperties() as $statKey => $statVal) {
                      if (is_string($statKey) && $statKey !== '' && $statKey[0] >= 'A' && $statKey[0] <= 'Z') {
                          $hasStats = true;
                          if (is_bool($statVal)) {
                              $valDisplay = $statVal ? '1' : '0';
                          } elseif (is_scalar($statVal)) {
                              $valDisplay = (string)$statVal;
                          } else {
                              $valDisplay = json_encode($statVal);
                          }
                          ?>
                          <div><b><?= htmlspecialchars($statKey) ?>:</b> <?= htmlspecialchars($valDisplay) ?></div>
                          <?php
                      }
                  }
                  if (!$hasStats) {
                      ?>
                      <span style="font-size:12px;color:#64748b">None.</span>
                      <?php
                  }
                  ?>
                </div>
              </div>

              <!-- Actions -->
              <div class="dimx-sidebar-box">
                <h4>⚡ Actions</h4>
                <form id="cmdForm" method="POST" action="multiplayer.php" style="margin:0">
                  <input type="hidden" name="cmd"    id="cmdInput"    value="look" />
                  <input type="hidden" name="param"  id="paramInput"  value="" />
                  <input type="hidden" name="target" id="targetInput" value="" />
                  <?= $activePanel?->toHtml($skin) ?>
                </form>
              </div>
            </div>

          </div>

          <script>
            // ---- Game command submission ----
            function sendCmd(cmd, param = '', target = '') {
              if (cmd === 'say' && !param) {
                const tb = document.getElementById('txtBox');
                if (tb && tb.value) {
                  param = tb.value;
                }
              }
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

            // Initial load + recurring poll every 3 seconds
            pollChat();
            setInterval(pollChat, 3000);
          </script>
        </body>
        </html>
        <?php
    }
}
