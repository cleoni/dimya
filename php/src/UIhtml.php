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
            *, *:before, *:after { box-sizing: border-box; }
            html, body {
              height: 100%;
              margin: 0;
              padding: 0;
              overflow: hidden;
              background: #000040;
              color: #e2e8f0;
              font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            .dimx-root {
              display: flex;
              flex-direction: column;
              width: 100vw;
              height: 100vh;
              overflow: hidden;
            }
            /* Row 1: occupies all available height */
            .dimx-row-top {
              display: flex;
              flex-direction: row;
              height: 85%;
              min-height: 0;
              width: 100%;
            }
            /* Row 2: occupies 15% of screen height */
            .dimx-row-bottom {
              display: flex;
              height: 15%;
              min-height: 0;
              width: 100%;
            }
            /* Cell A: uses all available width for left cell, grey frame */
            .dimx-cell-a {
              flex: 1 1 auto;
              min-width: 0;
              height: 100%;
              overflow-y: scroll;
              border-right: 2px solid #808080;
              border-bottom: 2px solid #808080;
              display: flex;
              flex-direction: column;
              background: #000040;
            }
            /* Cell B: 300 pixels wide, grey frame */
            .dimx-cell-b {
              width: 300px;
              flex: 0 0 300px;
              height: 100%;
              overflow-y: scroll;
              border-bottom: 2px solid #808080;
              background: #d4d4d4;
              color: #000000;
              padding: 10px;
            }
            /* Cell C: uses all width for a single cell, grey frame */
            .dimx-cell-c {
              width: 100%;
              height: 100%;
              overflow-y: scroll;
              background: #000030;
              color: #e2e8f0;
              padding: 8px 12px;
              font-size: 13px;
              line-height: 1.5;
            }
            /* Title bar across top of Cell A */
            .dimx-title-bar {
              background: #000040;
              color: #ffff00;
              font-weight: bold;
              font-size: 13px;
              padding: 4px 8px;
              border-bottom: 1px solid #444466;
              display: flex;
              justify-content: space-between;
              align-items: center;
              flex: 0 0 auto;
            }
            .dimx-title-bar a {
              color: #94a3b8;
              font-size: 11px;
              text-decoration: none;
              padding: 1px 5px;
              border: 1px solid #444466;
              border-radius: 3px;
            }
            .dimx-title-bar a:hover {
              color: #f8fafc;
              border-color: #64748b;
            }
            /* Inner row in Cell A */
            .dimx-inner-row {
              display: flex;
              flex-direction: row;
              flex: 1 1 auto;
              min-height: 0;
              width: 100%;
            }
            /* Subcell D: remaining width, room image and description below it */
            .dimx-subcell-d {
              flex: 1 1 auto;
              min-width: 0;
              height: 100%;
              overflow-y: scroll;
              padding: 8px;
              display: flex;
              flex-direction: column;
            }
            /* Subcell E: 250 pixels wide, navigation, inventory, stats */
            .dimx-subcell-e {
              width: 250px;
              flex: 0 0 250px;
              height: 100%;
              overflow-y: scroll;
              padding: 8px;
              border-left: 1px solid #444466;
              background: #000040;
            }
            .dimx-scene-container {
              position: relative;
              display: inline-block;
              max-width: 100%;
              margin-bottom: 8px;
            }
            .dimx-scene-img {
              max-width: 100%;
              height: auto;
              display: block;
            }
            .dimx-scene-placeholder {
              min-height: 240px;
              display: flex;
              align-items: center;
              justify-content: center;
              background: #090d16;
              color: #64748b;
              font-size: 14px;
            }
            .dimx-console {
              color: #38bdf8;
              font-size: 13px;
              line-height: 1.5;
              margin-top: 4px;
            }
            .dimx-console-line {
              margin-bottom: 4px;
            }
            .dimx-room-desc {
              color: #38bdf8;
            }
            .dimx-section-title {
              font-size: 12px;
              font-weight: bold;
              margin: 8px 0 4px;
            }
            .dimx-tag {
              display: inline-flex;
              align-items: center;
              gap: 4px;
              background: rgba(51,65,85,0.85);
              padding: 2px 6px;
              border-radius: 4px;
              font-size: 11px;
              cursor: pointer;
            }
            .dimx-tag:hover { background: #475569; }
            .dimx-mini-btn {
              background: #2563eb;
              color: #ffffff;
              border: none;
              border-radius: 3px;
              padding: 2px 6px;
              font-size: 11px;
              cursor: pointer;
            }
            .dimx-mini-btn:hover { opacity: 0.9; }
            /* Cell B controls styling */
            .dimx-cell-b .dimx-panel {
              background: transparent;
              padding: 0;
              display: flex;
              flex-wrap: wrap;
              gap: 6px;
              align-items: center;
            }
            .dimx-cell-b .dimx-textbox {
              width: 100%;
              padding: 6px 8px;
              background: #ffffff;
              border: 1px solid #777777;
              color: #000000;
              border-radius: 2px;
              margin-bottom: 8px;
              font-size: 13px;
              box-sizing: border-box;
            }
            .dimx-cell-b .dimx-btn {
              background: #ececec;
              border: 1px solid #777777;
              color: #000000;
              padding: 4px 10px;
              border-radius: 2px;
              cursor: pointer;
              font-size: 13px;
              box-shadow: 1px 1px 0 #fff inset, 1px 1px 0 #333;
            }
            .dimx-cell-b .dimx-btn:hover { background: #f4f4f4; }
            .dimx-cell-b .dimx-btn:active {
              background: #d8d8d8;
              box-shadow: 1px 1px 0 #333 inset;
            }
            .chat-line-sender { color: #38bdf8; font-weight: 600; margin-right: 4px; }
            .chat-line-time { color: #64748b; font-size: 11px; margin-right: 6px; }
          </style>
        </head>
        <body>

          <div class="dimx-root">

            <!-- FIRST ROW (occupies all available height, divided in cells A and B) -->
            <div class="dimx-row-top">

              <!-- CELL A: Left cell, uses all available width -->
              <div class="dimx-cell-a">
                <!-- Title bar: room name and player name -->
                <div class="dimx-title-bar">
                  <span>📍 <b><?= htmlspecialchars($currentRoom->name ?? 'Unknown') ?></b> (👤 <b><?= htmlspecialchars($player->name) ?></b>)</span>
                  <?php if ($lockError): ?><span style="color:#f87171;font-size:11px">⚠ Lock busy</span><?php endif; ?>
                </div>

                <!-- Inner row split into Subcell D (left) and Subcell E (right) -->
                <div class="dimx-inner-row">

                  <!-- Subcell D: Room image and below it, description / main messages output -->
                  <div class="dimx-subcell-d">
                    <div class="dimx-scene-container">
                      <?php if (!empty($currentRoom->images['N'])): ?>
                        <img src="<?= htmlspecialchars($currentRoom->images['N']->src) ?>" class="dimx-scene-img" alt="Scene" />
                      <?php else: ?>
                        <div class="dimx-scene-placeholder">
                          [<?= htmlspecialchars($currentRoom->name ?? 'Scene') ?>]
                        </div>
                      <?php endif; ?>

                      <!-- Characters & items on scene -->
                      <?php foreach ($occupants as $char): ?>
                        <?php if ($char !== $player): ?>
                          <?php
                            $charImg = $char->images['N'] ?? $char->image ?? null;
                            if ($charImg && !empty($charImg->src)):
                              $posX = ($char->showX >= 0) ? $char->showX . 'px' : '55%';
                              $posY = ($char->showY >= 0) ? $char->showY . 'px' : '40%';
                              $imgW = ($charImg->width > 0) ? "width:{$charImg->width}px;" : '';
                              $imgH = ($charImg->height > 0) ? "height:{$charImg->height}px;" : '';
                          ?>
                            <img src="<?= htmlspecialchars($charImg->src) ?>"
                                 alt="<?= htmlspecialchars($char->name) ?>"
                                 title="<?= htmlspecialchars($char->name) ?>"
                                 style="position:absolute;left:<?= $posX ?>;top:<?= $posY ?>;<?= $imgW ?><?= $imgH ?>cursor:pointer;z-index:10"
                                 onclick="sendCmd('click','','<?= htmlspecialchars($char->id) ?>')" />
                          <?php else: ?>
                            <span class="dimx-tag" style="position:absolute;bottom:8px;left:8px;cursor:pointer;z-index:10" onclick="sendCmd('click','','<?= htmlspecialchars($char->id) ?>')">👤 <?= htmlspecialchars($char->name) ?></span>
                          <?php endif; ?>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>

                    <!-- Description / main messages output -->
                    <div class="dimx-console" id="consoleBox">
                      <?php if (!empty($consoleMessages)): ?>
                        <?php foreach ($consoleMessages as $line): ?>
                          <div class="dimx-console-line"><?= $line ?></div>
                        <?php endforeach; ?>
                      <?php elseif ($currentRoom && !empty($currentRoom->evalDescription($player))): ?>
                        <div class="dimx-room-desc"><?= htmlspecialchars($currentRoom->evalDescription($player)) ?></div>
                      <?php endif; ?>
                    </div>
                  </div>

                  <!-- Subcell E: Navigation controls, inventory, stats (250px wide) -->
                  <div class="dimx-subcell-e">
                    <!-- Navigation / Exits -->
                    <div class="dimx-nav-compass" style="width:50px;height:50px;background:#808080;border:1px solid #999;margin-bottom:8px;display:flex;align-items:center;justify-content:center;color:#222;font-size:16px">
                      🧭
                    </div>

                    <div class="dimx-section-title" style="color:#38bdf8">More ways</div>
                    <div style="display:flex;flex-direction:column;gap:3px;margin-bottom:10px">
                      <?php foreach ($links as $link): ?>
                        <?php $dir = $link->getDirection($currentRoom->id); ?>
                        <div>
                          <a href="javascript:void(0)" onclick="sendCmd('go','<?= htmlspecialchars($dir) ?>')" style="color:#38bdf8;text-decoration:none;font-size:12px;display:inline-flex;align-items:center;gap:4px">
                            <span>⮵</span> <?= htmlspecialchars($link->name ?: $dir) ?>
                          </a>
                        </div>
                      <?php endforeach; ?>
                      <?php if (empty($links)): ?>
                        <span style="font-size:11px;color:#64748b">No visible exits.</span>
                      <?php endif; ?>
                    </div>

                    <!-- Items in room -->
                    <?php if (!empty($roomItems)): ?>
                      <div class="dimx-section-title" style="color:#38bdf8">Items</div>
                      <div style="display:flex;flex-direction:column;gap:3px;margin-bottom:10px">
                        <?php foreach ($roomItems as $itm): ?>
                          <div>
                            <a href="javascript:void(0)" onclick="sendCmd('click','','<?= htmlspecialchars($itm->id) ?>')" style="color:#e2e8f0;text-decoration:none;font-size:12px;cursor:pointer">
                              📦 <?= htmlspecialchars($itm->name) ?>
                            </a>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>

                    <!-- Inventory -->
                    <div class="dimx-section-title" style="color:#94a3b8;text-transform:uppercase">🎒 Inventory</div>
                    <div style="display:flex;flex-direction:column;gap:4px;margin-bottom:10px">
                      <?php foreach ($inventory as $item): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;background:rgba(255,255,255,0.06);padding:3px 6px;border-radius:3px;font-size:12px">
                          <span><b><?= htmlspecialchars($item->name) ?></b></span>
                          <div style="display:flex;gap:3px">
                            <?php if ($activePanel?->isCommandAllowed('use')): ?>
                              <button type="button" class="dimx-mini-btn" onclick="sendCmd('use','','<?= htmlspecialchars($item->id) ?>')">Use</button>
                            <?php endif; ?>
                            <?php if ($activePanel?->isCommandAllowed('drop')): ?>
                              <button type="button" class="dimx-mini-btn" style="background:#b91c1c;color:#fff" onclick="sendCmd('drop','','<?= htmlspecialchars($item->id) ?>')">Drop</button>
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                      <?php if (empty($inventory)): ?>
                        <span style="font-size:11px;color:#64748b">Empty.</span>
                      <?php endif; ?>
                    </div>

                    <!-- Stats -->
                    <div class="dimx-section-title" style="color:#94a3b8;text-transform:uppercase">📊 Stats</div>
                    <div style="font-size:12px;line-height:1.6">
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
                      if (!$hasStats): ?>
                        <span style="font-size:11px;color:#64748b">None.</span>
                      <?php endif; ?>
                    </div>
                  </div>

                </div>
              </div>

              <!-- CELL B: Right cell of top row (300px wide, ACTIONS panel) -->
              <div class="dimx-cell-b" id="cellB">
                <?php $activePanel = $player->getPanel() ?? $world->defaultPanel; ?>
                <form id="cmdForm" method="POST" action="multiplayer.php" style="margin:0">
                  <input type="hidden" name="cmd"    id="cmdInput"    value="look" />
                  <input type="hidden" name="param"  id="paramInput"  value="" />
                  <input type="hidden" name="target" id="targetInput" value="" />
                  <?php if ($activePanel?->getButton('txtBox') !== null): ?>
                    <div style="font-size:12px;color:#111;margin-bottom:4px;font-weight:600">Write here and hit ENTER to speak</div>
                  <?php endif; ?>
                  <?= $activePanel?->toHtml($skin) ?>
                </form>
              </div>

            </div>

            <!-- SECOND ROW: 15% height, single cell C spanning all width -->
            <div class="dimx-row-bottom">
              <!-- CELL C: Messages -->
              <div class="dimx-cell-c" id="cellC">
                <div id="chatBox"></div>
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
                  const cellC = document.getElementById('cellC');
                  if (cellC) cellC.scrollTop = cellC.scrollHeight;
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
