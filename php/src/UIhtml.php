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
        $activePanel  = $player->getPanel() ?? $world->defaultPanel;

        // Viewport resolution scaling factor (factorx = clientWidth / worldDesignWidth)
        $factorx = method_exists($player, 'getFactorX') ? $player->getFactorX() : 1.6;
        $roomW   = (!empty($currentRoom?->images['N']?->width)) ? $currentRoom->images['N']->width : ($world->sceneWidth ?? 350);
        $roomH   = (!empty($currentRoom?->images['N']?->height)) ? $currentRoom->images['N']->height : ($world->sceneHeight ?? 235);
        $scaledRoomW = (int)round($factorx * $roomW);
        $scaledRoomH = (int)round($factorx * $roomH);

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
              margin-bottom: 8px;
              overflow: hidden;
            }
            .dimx-scene-img {
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

            .dimx-subcell-e .dimx-btn {
              background: #1e40af;
              color: #f8fafc;
              border: 1px solid #3b82f6;
              padding: 4px 8px;
              border-radius: 4px;
              cursor: pointer;
              font-size: 12px;
              display: inline-flex;
              align-items: center;
              gap: 4px;
            }
            .dimx-subcell-e .dimx-btn:hover {
              background: #1d4ed8;
              border-color: #60a5fa;
            }
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
            .dimx-cell-b .dimx-btn.dimx-btn-active {
              background: #2563eb !important;
              color: #ffffff !important;
              border-color: #1d4ed8 !important;
              box-shadow: inset 0 2px 4px rgba(0,0,0,0.3) !important;
              font-weight: bold;
            }
            .dimx-cmd-prompt {
              font-size: 12px;
              color: #1e3a8a;
              background: #dbeafe;
              border: 1px solid #93c5fd;
              border-radius: 4px;
              padding: 5px 8px;
              margin-bottom: 8px;
              display: none;
              cursor: pointer;
              line-height: 1.3;
            }
            .dimx-cmd-prompt.dimx-prompt-visible {
              display: block;
            }
            .dimx-onscreen-obj {
              transition: filter 0.15s ease, transform 0.15s ease;
            }
            .dimx-onscreen-obj:hover {
              filter: brightness(1.2) drop-shadow(0 0 3px #38bdf8);
            }
            .chat-line-sender { color: #38bdf8; font-weight: 600; margin-right: 4px; }
            .chat-line-time { color: #64748b; font-size: 11px; margin-right: 6px; }
          </style>
          <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
                    <div class="dimx-scene-container" style="position:relative;width:<?= $scaledRoomW ?>px;height:<?= $scaledRoomH ?>px;background-color:<?= htmlspecialchars($world->bgcolor ?? '#000040') ?>;">
                      <?php if (!empty($currentRoom->images['N'])): ?>
                        <img src="<?= htmlspecialchars($currentRoom->images['N']->src) ?>"
                             class="dimx-scene-img"
                             alt="Scene"
                             width="<?= $scaledRoomW ?>"
                             height="<?= $scaledRoomH ?>"
                             style="position:absolute;left:0;top:0;width:<?= $scaledRoomW ?>px;height:<?= $scaledRoomH ?>px;z-index:2;display:block;" />
                      <?php else: ?>
                        <div class="dimx-scene-placeholder" style="width:<?= $scaledRoomW ?>px;height:<?= $scaledRoomH ?>px;">
                          [<?= htmlspecialchars($currentRoom->name ?? 'Scene') ?>]
                        </div>
                      <?php endif; ?>

                      <!-- Characters on scene -->
                      <?php foreach ($occupants as $char): ?>
                        <?php if ($char !== $player): ?>
                          <?php
                            $charImg = $char->images['N'] ?? $char->image ?? null;
                            if ($charImg && !empty($charImg->src)):
                              $isIcon = ($char->showmode === \Dimx\AdvObject::SHOW_ICON);
                              $imgSrc = ($isIcon && !empty($char->icon)) ? $char->icon : $charImg->src;
                              $baseW = $isIcon ? ($world->iconSize ?? 32) : (!empty($charImg->width) ? $charImg->width : ($world->iconSize ?? 32));
                              $baseH = $isIcon ? ($world->iconSize ?? 32) : (!empty($charImg->height) ? $charImg->height : ($world->iconSize ?? 32));

                              if ($char->showX >= 0 && $char->showY >= 0) {
                                  $cartX = $char->showX;
                                  $cartY = $char->showY;
                                  $screenX = 1 + $cartX;
                                  $screenY = $roomH - $cartY - $baseH;
                                  $posX = (int)round($factorx * $screenX) . 'px';
                                  $posY = (int)round($factorx * $screenY) . 'px';
                                  $stackpos = 15 + (int)round((($roomH - $cartY) / ($roomH ?: 1)) * (204 - 15));
                              } else {
                                  $posX = '55%';
                                  $posY = '40%';
                                  $stackpos = 100;
                              }
                              $dispW = (int)round($factorx * $baseW);
                              $dispH = (int)round($factorx * $baseH);
                          ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>"
                                 alt="<?= htmlspecialchars($char->name) ?>"
                                 title="<?= htmlspecialchars($char->name) ?>"
                                 class="dimx-onscreen-obj"
                                 data-obj-id="<?= htmlspecialchars($char->id) ?>"
                                 data-obj-type="c"
                                 data-obj-name="<?= htmlspecialchars($char->name) ?>"
                                 style="position:absolute;left:<?= $posX ?>;top:<?= $posY ?>;width:<?= $dispW ?>px;height:<?= $dispH ?>px;cursor:pointer;z-index:<?= $stackpos ?>"
                                 onclick="clickObject(this)" />
                          <?php else: ?>
                            <span class="dimx-tag dimx-onscreen-obj"
                                  data-obj-id="<?= htmlspecialchars($char->id) ?>"
                                  data-obj-type="c"
                                  data-obj-name="<?= htmlspecialchars($char->name) ?>"
                                  style="position:absolute;bottom:8px;left:8px;cursor:pointer;z-index:100"
                                  onclick="clickObject(this)">👤 <?= htmlspecialchars($char->name) ?></span>
                          <?php endif; ?>
                        <?php endif; ?>
                      <?php endforeach; ?>

                      <!-- Items on scene (positioned) -->
                      <?php foreach ($roomItems as $itm): ?>
                        <?php
                          if ($itm->showmode !== \Dimx\AdvObject::SHOW_OFFSCREEN && $itm->showX >= 0 && $itm->showY >= 0):
                            $isIcon = ($itm->showmode === \Dimx\AdvObject::SHOW_ICON);
                            $imgSrc = ($isIcon && !empty($itm->icon))
                              ? $itm->icon
                              : ($itm->images['N']->src ?? $itm->icon ?? null);
                            if ($imgSrc):
                              $baseW = $isIcon
                                ? ($world->iconSize ?? 32)
                                : (!empty($itm->images['N']->width) ? $itm->images['N']->width : ($world->iconSize ?? 32));
                              $baseH = $isIcon
                                ? ($world->iconSize ?? 32)
                                : (!empty($itm->images['N']->height) ? $itm->images['N']->height : ($world->iconSize ?? 32));

                              // Cartesian coordinate conversion (0,0 is lower-left of room scene)
                              $cartX = $itm->showX;
                              $cartY = $itm->showY;
                              $screenX = 1 + $cartX;
                              $screenY = $roomH - $cartY - $baseH;

                              $posX = (int)round($factorx * $screenX);
                              $posY = (int)round($factorx * $screenY);
                              $dispW = (int)round($factorx * $baseW);
                              $dispH = (int)round($factorx * $baseH);

                              // Stacking depth order (layers 15..204)
                              $stackpos = 15 + (int)round((($roomH - $cartY) / ($roomH ?: 1)) * (204 - 15));
                        ?>
                          <img src="<?= htmlspecialchars($imgSrc) ?>"
                               alt="<?= htmlspecialchars($itm->name) ?>"
                               title="<?= htmlspecialchars($itm->name) ?>"
                               class="dimx-onscreen-obj"
                               data-obj-id="<?= htmlspecialchars($itm->id) ?>"
                               data-obj-type="i"
                               data-obj-name="<?= htmlspecialchars($itm->name) ?>"
                               style="position:absolute;left:<?= $posX ?>px;top:<?= $posY ?>px;width:<?= $dispW ?>px;height:<?= $dispH ?>px;cursor:pointer;z-index:<?= $stackpos ?>"
                               onclick="clickObject(this)" />
                        <?php endif; endif; ?>
                      <?php endforeach; ?>
                    </div>

                    <!-- Description / main messages output -->
                    <div class="dimx-console" id="consoleBox">
                      <?php if (!empty($consoleMessages)): ?>
                        <?php foreach ($consoleMessages as $line): ?>
                          <div class="dimx-console-line"><?= nl2br($line) ?></div>
                        <?php endforeach; ?>
                      <?php elseif ($currentRoom && !empty($currentRoom->evalDescription($player))): ?>
                        <div class="dimx-room-desc"><?= nl2br(htmlspecialchars($currentRoom->evalDescription($player))) ?></div>
                      <?php endif; ?>
                    </div>
                  </div>

                  <!-- Subcell E: Navigation controls, inventory, stats (250px wide) -->
                  <div class="dimx-subcell-e">
                    <!-- Navigation / Exits -->
                    <div class="dimx-section-title" style="color:#38bdf8;margin-top:0">🗺 Exits</div>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px">
                      <?php foreach ($links as $link): ?>
                        <?php
                          $dir = $link->getDirection($currentRoom->id);
                          $dirUpper = strtoupper(trim($dir));
                          $dirIcon = match($dirUpper) {
                            'N' => '⬆',
                            'S' => '⬇',
                            'E' => '➡',
                            'W' => '⬅',
                            'NE' => '↗',
                            'NW' => '↖',
                            'SE' => '↘',
                            'SW' => '↙',
                            'U', 'UP' => '⮵',
                            'D', 'DOWN' => '⮶',
                            default => '🚪',
                          };
                        ?>
                        <button type="button" class="dimx-btn dimx-onscreen-obj" data-obj-id="<?= htmlspecialchars($dir) ?>" data-obj-type="w" data-obj-name="<?= htmlspecialchars($link->name) ?>" onclick="sendCmd('go','<?= htmlspecialchars($dir) ?>')">
                          <?= $dirIcon ?> [<?= htmlspecialchars($dir) ?>] <?= htmlspecialchars($link->name) ?>
                        </button>
                      <?php endforeach; ?>
                      <?php if (empty($links)): ?>
                        <span style="font-size:12px;color:#64748b">No visible exits.</span>
                      <?php endif; ?>
                    </div>

                    <!-- Items in room -->
                    <?php if (!empty($roomItems)): ?>
                      <div class="dimx-section-title" style="color:#38bdf8">Items</div>
                      <div style="display:flex;flex-direction:column;gap:4px;margin-bottom:10px">
                        <?php foreach ($roomItems as $itm): ?>
                          <div>
                            <a href="javascript:void(0)"
                               class="dimx-onscreen-obj"
                               data-obj-id="<?= htmlspecialchars($itm->id) ?>"
                               data-obj-type="i"
                               data-obj-name="<?= htmlspecialchars($itm->name) ?>"
                               onclick="clickObject(this)"
                               style="color:#e2e8f0;text-decoration:none;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px">
                               <?php if (!empty($itm->icon)): ?>
                                 <img src="<?= htmlspecialchars($itm->icon) ?>" alt="<?= htmlspecialchars($itm->name) ?>" style="width:20px;height:20px;object-fit:contain;vertical-align:middle;border-radius:2px" />
                               <?php else: ?>
                                 <span>📦</span>
                               <?php endif; ?>
                               <span><?= htmlspecialchars($itm->name) ?></span>
                            </a>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                    <!-- Inventory -->
                    <div class="dimx-section-title" style="color:#94a3b8;text-transform:uppercase">🎒 Inventory</div>
                    <div style="display:flex;flex-direction:column;gap:4px;margin-bottom:10px">
                      <?php foreach ($inventory as $item): ?>
                        <div style="background:rgba(255,255,255,0.06);padding:3px 6px;border-radius:3px;font-size:12px">
                          <a href="javascript:void(0)"
                             class="dimx-onscreen-obj"
                             data-obj-id="<?= htmlspecialchars($item->id) ?>"
                             data-obj-type="i"
                             data-obj-name="<?= htmlspecialchars($item->name) ?>"
                             onclick="clickObject(this)"
                             style="color:#e2e8f0;text-decoration:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px"
                             title="Click to select this item">
                            <?php if (!empty($item->icon)): ?>
                              <img src="<?= htmlspecialchars($item->icon) ?>" alt="<?= htmlspecialchars($item->name) ?>" style="width:18px;height:18px;object-fit:contain;vertical-align:middle;border-radius:2px" />
                            <?php endif; ?>
                            <b><?= htmlspecialchars($item->name) ?></b>
                          </a>
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
                <div id="cmdPrompt" class="dimx-cmd-prompt" title="Click to cancel pending command" onclick="clearPendingCmd()"></div>
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
                <div id="chatBox">
                  <?php foreach (($context['chat'] ?? []) as $chatMsg): ?>
                    <div>
                      <span class="chat-line-time">[<?= htmlspecialchars($chatMsg['time'] ?? '') ?>]</span>
                      <span class="chat-line-sender"><?= htmlspecialchars($chatMsg['sender'] ?? '') ?>:</span>
                      <?= htmlspecialchars($chatMsg['text'] ?? '') ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

          </div>

          <script>
            // ---- Command definitions and argument models ----
            const COMMAND_DEFS = {
              'use': {
                noArgs: 1,
                model: 'O',
                allowDouble: false,
                desc: 'Use ...',
                prompt: '👉 Click an object on screen to USE (or click another command to cancel)'
              },
              'look': {
                noArgs: 1,
                model: 'O',
                allowDouble: true,
                desc: 'Look ...',
                prompt: '👉 Click an object to LOOK at (or click Look again to inspect the room)'
              },
              'click': {
                noArgs: 1,
                model: 'O',
                allowDouble: false,
                desc: 'Click ...'
              },
              'pick': {
                noArgs: 1,
                model: 'O',
                allowDouble: true,
                desc: 'Pick ...',
                prompt: '👉 Click an item to PICK up (or click Pick again to pick everything)'
              },
              'drop': {
                noArgs: 1,
                model: 'O',
                allowDouble: false,
                desc: 'Drop ...',
                prompt: '👉 Click an item in inventory to DROP'
              },
              'open': {
                noArgs: 1,
                model: 'O',
                allowDouble: false,
                desc: 'Open ...',
                prompt: '👉 Click an object or door to OPEN'
              },
              'close': {
                noArgs: 1,
                model: 'O',
                allowDouble: false,
                desc: 'Close ...',
                prompt: '👉 Click an object or door to CLOSE'
              },
              'search': { noArgs: 0, model: '',  desc: 'Search' },
              'help':   { noArgs: 0, model: '',  desc: 'Help' },
              'save':   { noArgs: 0, model: 'T', desc: 'Save' },
              'exit':   { noArgs: 0, model: '',  desc: 'Exit' },
              'clear':  { noArgs: 0, model: '',  desc: 'Clear' },
              'say':    { noArgs: 0, model: 'T', desc: 'Say' }
            };

            // Pending command state
            let pendingCmd   = '';
            let pendingArgs  = [];
            let pendingNames = [];

            /**
             * Identify an on-screen object from a DOM element, jQuery wrapper, or DimensioneX string ref.
             * Returns { id: string, type: string, name: string, fullId: string } or null.
             */
            function identifyObject(target) {
              if (!target) return null;
              let type = 'i';
              let id = '';
              let name = '';

              if (typeof target === 'string') {
                // DimensioneX string format: "type!id|name" or "type!id" or just "id"
                const bang = target.indexOf('!');
                const pipe = target.indexOf('|', bang + 1);
                if (bang >= 0) {
                  type = target.substring(0, bang);
                  if (pipe > bang) {
                    id = target.substring(bang + 1, pipe);
                    name = target.substring(pipe + 1);
                  } else {
                    id = target.substring(bang + 1);
                    name = id;
                  }
                } else {
                  id = target;
                  name = target;
                }
              } else if (typeof target === 'object') {
                // DOM element or jQuery object
                const el = (target.jquery ? target[0] : target);
                const host = (el.closest ? el.closest('.dimx-onscreen-obj') : null) || el;
                id = host.dataset?.objId || host.dataset?.id || host.getAttribute('data-obj-id') || host.getAttribute('data-id') || '';
                type = host.dataset?.objType || host.dataset?.type || host.getAttribute('data-obj-type') || 'i';
                name = host.dataset?.objName || host.dataset?.name || host.getAttribute('data-obj-name') || host.title || host.alt || host.innerText || id;
              }

              id = String(id).trim();
              if (!id) return null;

              return {
                id: id,
                type: String(type).trim().toLowerCase(),
                name: String(name).trim(),
                fullId: type + '!' + id
              };
            }

            let lastClickedObjTime = 0;
            let lastClickedObjId = null;

            /**
             * Handle interaction with any on-screen object (character, room item, inventory, link).
             * Works for objects placed anywhere on screen: scene, console messages, cell B, or subcell E.
             */
            function clickObject(target, fallbackParam = '') {
              const now = Date.now();
              const obj = identifyObject(target);
              if (!obj) {
                return;
              }

              // Debounce duplicate invocations from inline onclick + delegated listener
              if (obj.id === lastClickedObjId && (now - lastClickedObjTime) < 150) {
                return;
              }
              lastClickedObjTime = now;
              lastClickedObjId = obj.id;

              // 1. If no command is currently awaiting target:
              if (!pendingCmd) {
                if (obj.type === 'w') {
                  sendCmd('go', fallbackParam || obj.id);
                } else {
                  // Direct click on object inspects it (click / onLook)
                  sendCmd('click', '', obj.id);
                }
                return;
              }

              // 2. A command is currently awaiting a target:
              // DimensioneX special rule: 'use' on a link/way waypoint translates to 'go'
              if (pendingCmd === 'use' && obj.type === 'w') {
                const dir = fallbackParam || obj.id;
                clearPendingCmd();
                sendCmd('go', dir);
                return;
              }

              // Collect target argument
              pendingArgs.push(obj.id);
              pendingNames.push(obj.name);

              const def = COMMAND_DEFS[pendingCmd] || { noArgs: 1 };
              if (pendingArgs.length >= (def.noArgs || 1)) {
                // All required arguments acquired -> execute command!
                const cmdToSubmit = pendingCmd;
                const targetObjId = pendingArgs[0];
                const secondArg   = pendingArgs.length > 1 ? pendingArgs[1] : '';
                clearPendingCmd();
                submitCmd(cmdToSubmit, secondArg, targetObjId);
              } else {
                updatePrompt();
              }
            }

            /**
             * Generic command button handler for cell B panel buttons.
             */
            function handleCommand(cmd) {
              const def = COMMAND_DEFS[cmd] || { noArgs: 0, allowDouble: false };

              // Double-click check: if user presses the same button twice
              if (pendingCmd === cmd && def.allowDouble) {
                clearPendingCmd();
                if (cmd === 'look') {
                  // Look around current room
                  submitCmd('look', '', '');
                  return;
                } else if (cmd === 'pick') {
                  // Pick everything
                  submitCmd('pick', '', '*');
                  return;
                }
              }

              // If another command was pending, reset before switching
              if (pendingCmd && pendingCmd !== cmd) {
                clearPendingCmd();
              }

              // Special command: clear cancels pending command
              if (cmd === 'clear') {
                clearPendingCmd();
                return;
              }

              // If command requires 0 arguments (instant execution)
              if (!def.noArgs || def.noArgs === 0) {
                clearPendingCmd();
                if (cmd === 'say') {
                  const tb = document.getElementById('txtBox');
                  const text = (tb && tb.value) ? tb.value : '';
                  submitCmd('say', text);
                } else {
                  submitCmd(cmd);
                }
                return;
              }

              // Command requires 1 or more arguments (e.g. 'use', 'look') -> await on-screen target
              pendingCmd   = cmd;
              pendingArgs  = [];
              pendingNames = [];

              highlightButton(cmd, true);
              updatePrompt();
            }

            function highlightButton(cmd, isActive) {
              document.querySelectorAll('.dimx-cell-b .dimx-btn').forEach(btn => {
                btn.classList.remove('dimx-btn-active');
              });

              if (isActive && cmd) {
                const btn = document.getElementById('btn_' + cmd);
                if (btn) {
                  btn.classList.add('dimx-btn-active');
                }
              }
            }

            function updatePrompt() {
              const pBox = document.getElementById('cmdPrompt');
              if (!pBox) return;

              if (!pendingCmd) {
                pBox.innerText = '';
                pBox.classList.remove('dimx-prompt-visible');
                return;
              }

              const def = COMMAND_DEFS[pendingCmd] || {};
              let msg = def.prompt || ('Select target object for ' + pendingCmd.toUpperCase() + ' (Click to cancel)');
              if (pendingNames.length > 0) {
                msg = pendingCmd.toUpperCase() + ' ' + pendingNames.join(', ') + '...';
              }
              pBox.innerText = msg;
              pBox.classList.add('dimx-prompt-visible');
            }

            function clearPendingCmd() {
              if (pendingCmd) {
                highlightButton(pendingCmd, false);
              }
              pendingCmd   = '';
              pendingArgs  = [];
              pendingNames = [];
              updatePrompt();
            }

            // Keyboard shortcut: Escape cancels pending command
            document.addEventListener('keydown', function(e) {
              if (e.key === 'Escape' && pendingCmd) {
                clearPendingCmd();
              }
            });

            // Delegated click handler: point-and-click system works for objects anywhere on screen (cell B, console, subcell E, scene)
            $(document).on('click', '.dimx-onscreen-obj, [data-obj-id]', function(e) {
              if (this.tagName === 'BUTTON' && this.hasAttribute('data-cmd') && !this.hasAttribute('data-obj-id')) {
                return;
              }
              clickObject(this);
              e.preventDefault();
            });

            // ---- Game command submission ----
            function sendCmd(cmd, param = '', target = '') {
              // If target or param is already explicitly supplied (e.g. from exit button or inventory button)
              if (param !== '' || target !== '') {
                clearPendingCmd();
                submitCmd(cmd, param, target);
                return;
              }

              // Called without target or param (e.g. from cell B button click)
              handleCommand(cmd);
            }

            function submitCmd(cmd, param = '', target = '') {
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

            // Global window exports
            window.identifyObject  = identifyObject;
            window.clickObject     = clickObject;
            window.handleCommand   = handleCommand;
            window.sendCmd         = sendCmd;
            window.submitCmd       = submitCmd;
            window.clearPendingCmd = clearPendingCmd;

            // Auto-scroll console
            const cBox = document.getElementById('consoleBox');
            if (cBox) cBox.scrollTop = cBox.scrollHeight;

            // ---- Async chat ----
            const WORLD_ID   = <?= json_encode($worldId) ?>;
            const PLAYER_NAME = <?= json_encode($playerName) ?>;
            <?php
            $initialChat = $context['chat'] ?? [];
            $initLastId = 0;
            if (!empty($initialChat)) {
                $lastItem = end($initialChat);
                $initLastId = (int)($lastItem['id'] ?? 0);
            }
            ?>
            let lastMsgId = <?= $initLastId ?>;

            const cellC = document.getElementById('cellC');
            if (cellC) cellC.scrollTop = cellC.scrollHeight;

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
                          + '&player=' + encodeURIComponent(PLAYER_NAME)
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
