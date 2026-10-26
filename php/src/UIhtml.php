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
              <input type="hidden" name="width" id="clientWidth" value="1280">
              <input type="hidden" name="height" id="clientHeight" value="800">

              <label for="username">Nickname</label>
              <input type="text" id="username" name="username" placeholder="Your nickname…" required autofocus maxlength="32" value="<?= $valUser ?>" />

              <label for="password">Password</label>
              <input type="password" id="password" name="password" placeholder="Your password…" required />

              <button type="submit">Play</button>
            </form>
            <script>
              (function() {
                var w = window.innerWidth || (window.screen ? window.screen.width : 0);
                var h = window.innerHeight || (window.screen ? window.screen.height : 0);
                if (w > 0) document.getElementById('clientWidth').value = w;
                if (h > 0) document.getElementById('clientHeight').value = h;
              })();
            </script>
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

        $skin         = $world->skins['default'] ?? $world->defaultSkin ?? $player->skin ?? new Skin('default', 'Default');
        $occupants    = $currentRoom ? array_values(array_filter($currentRoom->getOccupants(), fn($c) => !$c->isHidden())) : [];
        $roomItems    = $currentRoom ? array_values(array_filter($currentRoom->getItems(), fn($i) => !$i->isHidden()))     : [];
        $inventory    = array_values(array_filter($player->contents, fn($i) => !$i->isHidden()));
        $links        = $currentRoom ? array_values(array_filter($currentRoom->getLinks(), fn($l) => !$l->isHidden()))     : [];
        $activePanel  = $player->getPanel() ?? $world->defaultPanel;

        // Portrait / vertical display detection (width < height at login or client)
        $isPortrait   = !empty($player->isPortrait) || ($player->screenWidth > 0 && $player->screenHeight > 0 && $player->screenWidth < $player->screenHeight);

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
            #navpad {
              border-collapse: separate;
              border-spacing: 2px;
              margin: 0 auto 10px auto;
              background-color: <?= htmlspecialchars($skin->panelBgColor ?: 'SILVER') ?>;
              border: 1px solid #94a3b8;
              padding: 2px;
              border-radius: 4px;
            }
            #navpad td {
              width: 20px;
              height: 20px;
              padding: 0;
              text-align: center;
              vertical-align: middle;
            }
            .dimx-nav-btn {
              display: inline-flex;
              align-items: center;
              justify-content: center;
              width: 20px;
              height: 20px;
              border: 1px outset #cbd5e1;
              border-radius: 2px;
              background-color: #e2e8f0;
              box-sizing: border-box;
              cursor: pointer;
              text-decoration: none;
              padding: 0;
              transition: all 0.15s ease;
            }
            .dimx-nav-btn:hover {
              background-color: #ffffff;
              border-color: #0284c7;
              filter: brightness(1.1) drop-shadow(0 0 2px #38bdf8);
            }
            .chat-line {
              margin: 0;
              padding: 3px 6px;
              line-height: 1.4;
              border-radius: 2px;
            }
            .chat-line:nth-child(odd) {
              background-color: #101040;
            }
            .chat-line:nth-child(even) {
              background-color: #313161;
            }
            .chat-pop-btn {
              color: #f87171;
              text-decoration: none;
              font-weight: bold;
              font-size: 11px;
              cursor: pointer;
              margin-right: 6px;
              display: inline-block;
              line-height: 1;
              padding: 1px 4px;
              border: 1px solid rgba(248, 113, 113, 0.4);
              border-radius: 3px;
              background: rgba(248, 113, 113, 0.1);
              vertical-align: middle;
            }
            .chat-pop-btn:hover {
              background: rgba(239, 68, 68, 0.35);
              color: #ffffff;
              border-color: #ef4444;
            }
            .chat-line-sender { color: #38bdf8; font-weight: 600; margin-right: 4px; }

            /* Vertical / Portrait Layout (*, 35%, 15% share for cells A, B, C) */
            .dimx-portrait {
              height: 100vh !important;
              max-height: 100vh !important;
              overflow: hidden !important;
            }
            .dimx-portrait .dimx-row-top {
              display: flex !important;
              flex-direction: column !important;
              height: 85vh !important;
              flex: 1 1 85vh !important;
              min-height: 0 !important;
              width: 100% !important;
            }
            /* Cell A (Scene frame): gets dynamic remaining height (*) */
            .dimx-portrait .dimx-cell-a {
              width: 100% !important;
              flex: 1 1 auto !important;
              min-height: 0 !important;
              height: auto !important;
              border-right: none !important;
              border-bottom: 2px solid #808080 !important;
              overflow: hidden !important;
              display: flex !important;
              flex-direction: column !important;
            }
            /* Cell B (Controls frame): gets approx 35% of total height */
            .dimx-portrait .dimx-cell-b {
              width: 100% !important;
              flex: 0 0 35vh !important;
              height: 35vh !important;
              max-height: 35vh !important;
              min-height: 0 !important;
              border-right: none !important;
              border-bottom: 2px solid #808080 !important;
              overflow-y: scroll !important;
            }
            /* Row 2 / Cell C (Messages frame): gets approx 15% of total height */
            .dimx-portrait .dimx-row-bottom {
              height: 15vh !important;
              flex: 0 0 15vh !important;
              min-height: 0 !important;
              width: 100% !important;
              display: flex !important;
            }
            .dimx-portrait .dimx-cell-c {
              width: 100% !important;
              height: 100% !important;
              overflow-y: scroll !important;
            }
            /* Subcells D (left) and E (right) stay in a row */
            .dimx-portrait .dimx-inner-row {
              display: flex !important;
              flex-direction: row !important;
              flex: 1 1 auto !important;
              min-height: 0 !important;
              width: 100% !important;
            }
            .dimx-portrait .dimx-subcell-d {
              flex: 1 1 60% !important;
              min-width: 0 !important;
              height: 100% !important;
              overflow-y: scroll !important;
              overflow-x: auto !important;
              padding: 8px !important;
              display: flex !important;
              flex-direction: column !important;
            }
            .dimx-portrait .dimx-subcell-e {
              flex: 0 0 40% !important;
              width: 40% !important;
              max-width: 40% !important;
              min-width: 120px !important;
              height: 100% !important;
              overflow-y: scroll !important;
              padding: 8px !important;
              border-left: 1px solid #444466 !important;
              border-top: none !important;
              background: #000040 !important;
            }

            @media (orientation: portrait) {
              .dimx-root {
                height: 100vh !important;
                max-height: 100vh !important;
                overflow: hidden !important;
              }
              .dimx-row-top {
                display: flex !important;
                flex-direction: column !important;
                height: 85vh !important;
                flex: 1 1 85vh !important;
                min-height: 0 !important;
                width: 100% !important;
              }
              .dimx-cell-a {
                width: 100% !important;
                flex: 1 1 auto !important;
                min-height: 0 !important;
                height: auto !important;
                border-right: none !important;
                border-bottom: 2px solid #808080 !important;
                overflow: hidden !important;
                display: flex !important;
                flex-direction: column !important;
              }
              .dimx-cell-b {
                width: 100% !important;
                flex: 0 0 35vh !important;
                height: 35vh !important;
                max-height: 35vh !important;
                min-height: 0 !important;
                border-right: none !important;
                border-bottom: 2px solid #808080 !important;
                overflow-y: scroll !important;
              }
              .dimx-row-bottom {
                height: 15vh !important;
                flex: 0 0 15vh !important;
                min-height: 0 !important;
                width: 100% !important;
                display: flex !important;
              }
              .dimx-cell-c {
                width: 100% !important;
                height: 100% !important;
                overflow-y: scroll !important;
              }
              .dimx-inner-row {
                display: flex !important;
                flex-direction: row !important;
                flex: 1 1 auto !important;
                min-height: 0 !important;
                width: 100% !important;
              }
              .dimx-subcell-d {
                flex: 1 1 60% !important;
                min-width: 0 !important;
                height: 100% !important;
                overflow-y: scroll !important;
                overflow-x: auto !important;
                padding: 8px !important;
                display: flex !important;
                flex-direction: column !important;
              }
              .dimx-subcell-e {
                flex: 0 0 40% !important;
                width: 40% !important;
                max-width: 40% !important;
                min-width: 120px !important;
                height: 100% !important;
                overflow-y: scroll !important;
                padding: 8px !important;
                border-left: 1px solid #444466 !important;
                border-top: none !important;
                background: #000040 !important;
              }
            }

            /* Narrow landscape screen fallback (< 800px width in landscape) */
            @media (max-width: 800px) and (orientation: landscape) {
              .dimx-root {
                height: auto;
                min-height: 100vh;
                overflow-y: auto;
              }
              .dimx-row-top {
                flex-direction: column;
                height: auto;
                min-height: 0;
              }
              .dimx-cell-a {
                border-right: none;
                height: auto;
                overflow-y: visible;
              }
              .dimx-cell-b {
                width: 100%;
                flex: 0 0 auto;
                height: auto;
              }
              .dimx-inner-row {
                flex-direction: column;
              }
              .dimx-subcell-e {
                width: 100%;
                flex: 0 0 auto;
                border-left: none;
                border-top: 1px solid #444466;
              }
              .dimx-row-bottom {
                height: auto;
                min-height: 120px;
              }
            }
          </style>
          <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        </head>
        <body>

          <div class="dimx-root <?= $isPortrait ? 'dimx-portrait' : '' ?>">

            <!-- FIRST ROW (occupies all available height, divided in cells A and B) -->
            <div class="dimx-row-top">

              <!-- CELL A: Left cell, uses all available width -->
              <div class="dimx-cell-a">
                <!-- Title bar: room name and player name -->
                <div class="dimx-title-bar">
                  <span>📍 <b><?= htmlspecialchars($currentRoom->name ?? 'Unknown') ?><?= ($currentRoom && $currentRoom->hasSeveralFaces()) ? ' - ' . htmlspecialchars(\Dimx\Link::getExtendedDirectionName($player->facing ?? 'N')) . ' view' : '' ?></b> (👤 <b><?= htmlspecialchars($player->name) ?></b>)</span>
                  <?php if ($lockError): ?><span style="color:#f87171;font-size:11px">⚠ Lock busy</span><?php endif; ?>
                </div>

                <!-- Inner row split into Subcell D (left) and Subcell E (right) -->
                <div class="dimx-inner-row">

                  <!-- Subcell D: Room image and below it, description / main messages output -->
                  <div class="dimx-subcell-d">
                    <div class="dimx-scene-container" style="position:relative;width:<?= $scaledRoomW ?>px;height:<?= $scaledRoomH ?>px;background-color:<?= htmlspecialchars($world->bgcolor ?? '#000040') ?>;">
                      <?php
                        $sceneImg = $currentRoom ? $currentRoom->getImageForFacing($player->facing ?? 'N') : null;
                        $saX1 = ($sceneImg && $sceneImg->showareaX1 >= 0) ? $sceneImg->showareaX1 : 0;
                        $saX2 = ($sceneImg && $sceneImg->showareaX2 >= 0 && $sceneImg->showareaX2 > $saX1) ? $sceneImg->showareaX2 : $roomW;
                        $saBaseline = ($sceneImg && $sceneImg->showareaY >= 0)
                          ? $sceneImg->showareaY
                          : (($sceneImg && $sceneImg->baseline >= 0) ? $sceneImg->baseline : (int)round(0.10 * $roomH));
                      ?>
                      <?php if (!empty($sceneImg)): ?>
                        <img src="<?= htmlspecialchars($sceneImg->src) ?>"
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
                        <?php if ($char !== $player && !$char->isHidden()): ?>
                          <?php
                            if ($char->showmode === \Dimx\AdvObject::SHOW_OFFSCREEN) {
                              continue;
                            }
                            $charImg = $char->images['N'] ?? $char->image ?? $char->skin?->picPlayer ?? $skin->picPlayer ?? $world->defaultSkin?->picPlayer ?? null;
                            if (!$charImg && !empty($world->imagesFolder)) {
                              $charImg = new \Dimx\Image(rtrim(str_replace('/pics/', '/skins/aqua/', $world->imagesFolder), '/') . '/avatar.gif', 64, 100);
                            }
                            // Check SHOW FOR restriction
                            if (!empty($char->showFor) && $sceneImg !== null) {
                              $sceneUrl = $sceneImg->src;
                              if (!str_ends_with($sceneUrl, $char->showFor)) {
                                $faceMatches = false;
                                foreach ($currentRoom->images ?? [] as $fImg) {
                                  if (str_ends_with($fImg->src, $char->showFor)) {
                                    $faceMatches = true;
                                    break;
                                  }
                                }
                                if ($faceMatches) {
                                  continue;
                                }
                              }
                            }

                            if ($charImg && !empty($charImg->src)):
                              $isIcon = ($char->showmode === \Dimx\AdvObject::SHOW_ICON);
                              $imgSrc = ($isIcon && !empty($char->icon)) ? $char->icon : $charImg->src;
                              $baseW = $isIcon ? ($world->iconSize ?? 32) : (!empty($charImg->width) ? $charImg->width : ($world->iconSize ?? 32));
                              $baseH = $isIcon ? ($world->iconSize ?? 32) : (!empty($charImg->height) ? $charImg->height : ($world->iconSize ?? 32));

                              // Cartesian coordinate conversion (0,0 is lower-left of room scene)
                              $xValid = ($char->showX >= 0 && $char->showX < $roomW);
                              if ($xValid) {
                                $cartX = $char->showX;
                              } else {
                                $range = ($saX2 - $saX1) - $baseW;
                                $cartX = ($range > 0) ? ($saX1 + mt_rand(0, $range)) : $saX1;
                              }

                              $yValid = ($char->showY >= 0 && $char->showY < $roomH);
                              if ($yValid) {
                                $cartY = $char->showY;
                              } else {
                                $cartY = $isIcon ? (int)round($saBaseline / 2) : $saBaseline;
                              }

                              $screenX = 1 + $cartX;
                              $screenY = $roomH - $cartY - $baseH;
                              $dispW = $isIcon ? ($world->iconSize ?? 32) : (int)round($factorx * $baseW);
                              $dispH = $isIcon ? ($world->iconSize ?? 32) : (int)round($factorx * $baseH);
                              $rawPosX = (int)round($factorx * $screenX);
                              $rawPosY = (int)round($factorx * $screenY);
                              $posX = max(0, min($rawPosX, max(0, $scaledRoomW - $dispW)));
                              $posY = max(0, min($rawPosY, max(0, $scaledRoomH - $dispH)));
                              $stackpos = 15 + (int)round((($roomH - $cartY) / ($roomH ?: 1)) * (204 - 15));
                          ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>"
                                 alt="<?= htmlspecialchars($char->name) ?>"
                                 title="<?= htmlspecialchars($char->name) ?>"
                                 class="dimx-onscreen-obj"
                                 data-obj-id="<?= htmlspecialchars($char->id) ?>"
                                 data-obj-type="c"
                                 data-obj-name="<?= htmlspecialchars($char->name) ?>"
                                 style="position:absolute;left:<?= $posX ?>px;top:<?= $posY ?>px;width:<?= $dispW ?>px;height:<?= $dispH ?>px;cursor:pointer;z-index:<?= $stackpos ?>"
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

                      <!-- Links on scene (positioned) -->
                      <?php foreach ($links as $lnk): ?>
                        <?php
                          if ($lnk->showmode !== \Dimx\AdvObject::SHOW_OFFSCREEN && !$lnk->isHidden()):
                            // Check SHOW FOR restriction
                            if (!empty($lnk->showFor) && $sceneImg !== null) {
                              $sceneUrl = $sceneImg->src;
                              if (!str_ends_with($sceneUrl, $lnk->showFor)) {
                                continue;
                              }
                            }

                            $isIcon = ($lnk->showmode === \Dimx\AdvObject::SHOW_ICON);
                            $imgSrc = ($isIcon && !empty($lnk->icon))
                              ? $lnk->icon
                              : ($lnk->images['N']->src ?? $lnk->image->src ?? $lnk->icon ?? null);
                            if ($imgSrc):
                              $baseW = $isIcon
                                ? ($world->iconSize ?? 32)
                                : (!empty($lnk->images['N']->width) ? $lnk->images['N']->width : (!empty($lnk->image->width) ? $lnk->image->width : ($world->iconSize ?? 32)));
                              $baseH = $isIcon
                                ? ($world->iconSize ?? 32)
                                : (!empty($lnk->images['N']->height) ? $lnk->images['N']->height : (!empty($lnk->image->height) ? $lnk->image->height : ($world->iconSize ?? 32)));

                              // Cartesian coordinate conversion (0,0 is lower-left of room scene)
                              $xValid = ($lnk->showX >= 0 && $lnk->showX < $roomW);
                              if ($xValid) {
                                $cartX = $lnk->showX;
                              } else {
                                $range = ($saX2 - $saX1) - $baseW;
                                $cartX = ($range > 0) ? ($saX1 + mt_rand(0, $range)) : $saX1;
                              }

                              $yValid = ($lnk->showY >= 0 && $lnk->showY < $roomH);
                              if ($yValid) {
                                $cartY = $lnk->showY;
                              } else {
                                $cartY = $isIcon ? (int)round($saBaseline / 2) : $saBaseline;
                              }

                              $screenX = 1 + $cartX;
                              $screenY = $roomH - $cartY - $baseH;

                              $dispW = $isIcon ? ($world->iconSize ?? 32) : (int)round($factorx * $baseW);
                              $dispH = $isIcon ? ($world->iconSize ?? 32) : (int)round($factorx * $baseH);
                              $rawPosX = (int)round($factorx * $screenX);
                              $rawPosY = (int)round($factorx * $screenY);
                              $posX = max(0, min($rawPosX, max(0, $scaledRoomW - $dispW)));
                              $posY = max(0, min($rawPosY, max(0, $scaledRoomH - $dispH)));

                              // DimX way layers 6..14 (base layer for room features / portals)
                              $stackpos = 6 + (int)round((($roomH - $cartY) / ($roomH ?: 1)) * (14 - 6));
                              $lnkName = ($lnk instanceof \Dimx\Link) ? $lnk->getDisplayName($currentRoom->id) : ($lnk->name ?? $lnk->id);
                              $lnkDir = $lnk->getDirection($currentRoom->id);
                        ?>
                          <img src="<?= htmlspecialchars($imgSrc) ?>"
                               alt="<?= htmlspecialchars($lnkName) ?>"
                               title="<?= htmlspecialchars($lnkName) ?>"
                               class="dimx-onscreen-obj"
                               data-obj-id="<?= htmlspecialchars($lnk->id) ?>"
                               data-obj-type="w"
                               data-obj-name="<?= htmlspecialchars($lnkName) ?>"
                               data-dir="<?= htmlspecialchars($lnkDir) ?>"
                               style="position:absolute;left:<?= $posX ?>px;top:<?= $posY ?>px;width:<?= $dispW ?>px;height:<?= $dispH ?>px;cursor:pointer;z-index:<?= $stackpos ?>"
                               onclick="clickObject(this)" />
                        <?php endif; endif; ?>
                      <?php endforeach; ?>

                      <!-- Items on scene (positioned) -->
                      <?php foreach ($roomItems as $itm): ?>
                        <?php
                          if ($itm !== $player->container && $itm->showmode !== \Dimx\AdvObject::SHOW_OFFSCREEN && !$itm->isHidden()):
                            // Check SHOW FOR restriction
                            if (!empty($itm->showFor) && $sceneImg !== null) {
                              $sceneUrl = $sceneImg->src;
                              if (!str_ends_with($sceneUrl, $itm->showFor)) {
                                $faceMatches = false;
                                foreach ($currentRoom->images ?? [] as $fImg) {
                                  if (str_ends_with($fImg->src, $itm->showFor)) {
                                    $faceMatches = true;
                                    break;
                                  }
                                }
                                if ($faceMatches) {
                                  continue;
                                }
                              }
                            }

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
                              // If x or y is -1 (or outside bounds), fall back to SHOWAREA random position
                              $xValid = ($itm->showX >= 0 && $itm->showX < $roomW);
                              if ($xValid) {
                                $cartX = $itm->showX;
                              } else {
                                $range = ($saX2 - $saX1) - $baseW;
                                $cartX = ($range > 0) ? ($saX1 + mt_rand(0, $range)) : $saX1;
                              }

                              $yValid = ($itm->showY >= 0 && $itm->showY < $roomH);
                              if ($yValid) {
                                $cartY = $itm->showY;
                              } else {
                                $cartY = $isIcon ? (int)round($saBaseline / 2) : $saBaseline; // Item icons sit on lower half-baseline (5%)
                              }

                              $screenX = 1 + $cartX;
                              $screenY = $roomH - $cartY - $baseH;

                              $dispW = $isIcon ? ($world->iconSize ?? 32) : (int)round($factorx * $baseW);
                              $dispH = $isIcon ? ($world->iconSize ?? 32) : (int)round($factorx * $baseH);
                              $rawPosX = (int)round($factorx * $screenX);
                              $rawPosY = (int)round($factorx * $screenY);
                              $posX = max(0, min($rawPosX, max(0, $scaledRoomW - $dispW)));
                              $posY = max(0, min($rawPosY, max(0, $scaledRoomH - $dispH)));

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

                      <!-- Vehicle interior overlay (Layer 201) -->
                      <?php
                        $playerVehicle = ($player->container instanceof \Dimx\AdvObject && $player->container->isVehicle()) ? $player->container : null;
                        if ($playerVehicle && !empty($playerVehicle->innerImage?->src)):
                          $innerImg = $playerVehicle->innerImage;
                          $innerW = (!empty($innerImg->width)) ? (int)round($factorx * $innerImg->width) : $scaledRoomW;
                          $innerH = (!empty($innerImg->height)) ? (int)round($factorx * $innerImg->height) : $scaledRoomH;
                      ?>
                        <div class="vehicle_interiors" style="left:0;top:0;position:absolute;z-index:201;pointer-events:none;">
                          <img src="<?= htmlspecialchars($innerImg->src) ?>"
                               alt="<?= htmlspecialchars($playerVehicle->name) ?>"
                               title="<?= htmlspecialchars($playerVehicle->name) ?>"
                               width="<?= $innerW ?>"
                               height="<?= $innerH ?>"
                               style="width:<?= $innerW ?>px;height:<?= $innerH ?>px;border:0;display:block;" />
                        </div>
                      <?php endif; ?>
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
                    <!-- Navigation / Exits (Navpad) -->
                    <?php
                      $isMultiface = $currentRoom && $currentRoom->hasSeveralFaces();
                      if ($currentRoom !== null) {
                          if ($currentRoom->hasSeveralFaces()) {
                              if ($currentRoom->getExactImage($player->facing) === null) {
                                  $player->facing = (string)array_key_first($currentRoom->images);
                              }
                          } elseif (count($currentRoom->images) === 1) {
                              $player->facing = (string)array_key_first($currentRoom->images);
                          }
                      }
                      $facing = strtoupper($player->facing ?? 'N');
                      $compassLinks = [
                          'N' => null,
                          'W' => null,
                          'E' => null,
                          'S' => null,
                      ];
                      $otherLinks = [];
                      $forwardLink = null;
                      $turnLeftFace = null;
                      $turnRightFace = null;
                      $revFace = null;

                      if ($isMultiface) {
                          $waysPool = $links;
                          // 1. Forward link in player's current facing
                          foreach ($waysPool as $idx => $link) {
                              $linkDir = strtoupper(trim($link->getDirection($currentRoom->id)));
                              if ($linkDir === $facing) {
                                  $forwardLink = $link;
                                  unset($waysPool[$idx]);
                                  break;
                              }
                          }
                          // Remove links facing left, right, reverse if room has an image in that face
                          $turnLeftFace = \Dimx\Character::nextFace($facing, 'l');
                          $turnRightFace = \Dimx\Character::nextFace($facing, 'r');
                          $revFace = \Dimx\Character::getOppositeDirection($facing);

                          foreach ($waysPool as $idx => $link) {
                              $linkDir = strtoupper(trim($link->getDirection($currentRoom->id)));
                              if ($linkDir === $turnLeftFace && $currentRoom->getExactImage($turnLeftFace) !== null) {
                                  unset($waysPool[$idx]);
                              } elseif ($linkDir === $turnRightFace && $currentRoom->getExactImage($turnRightFace) !== null) {
                                  unset($waysPool[$idx]);
                              } elseif ($linkDir === $revFace && $currentRoom->getExactImage($revFace) !== null) {
                                  unset($waysPool[$idx]);
                              }
                          }
                          $otherLinks = array_values($waysPool);
                      } else {
                          foreach ($links as $link) {
                              $linkDir = strtoupper(trim($link->getDirection($currentRoom->id)));
                              $relDir = \Dimx\Character::getRelativeDirection($linkDir, $facing);
                              if (in_array($relDir, ['N', 'W', 'E', 'S'], true)) {
                                  if ($compassLinks[$relDir] === null) {
                                      $compassLinks[$relDir] = $link;
                                  } elseif (empty($compassLinks[$relDir]->icon) && (!empty($link->icon) || $link->isOpenable())) {
                                      $otherLinks[] = $compassLinks[$relDir];
                                      $compassLinks[$relDir] = $link;
                                  } else {
                                      $otherLinks[] = $link;
                                  }
                              } else {
                                  $otherLinks[] = $link;
                              }
                          }
                          // If cell N is free and an Up link exists, place it in cell N
                          if ($compassLinks['N'] === null) {
                              foreach ($otherLinks as $idx => $link) {
                                  $d = strtoupper(trim($link->getDirection($currentRoom->id)));
                                  if ($d === 'U' || $d === 'UP') {
                                      $compassLinks['N'] = $link;
                                      unset($otherLinks[$idx]);
                                      break;
                                  }
                              }
                          }
                          // If cell S is free and a Down link exists (e.g. w6 in bar), place it in cell S
                          if ($compassLinks['S'] === null) {
                              foreach ($otherLinks as $idx => $link) {
                                  $d = strtoupper(trim($link->getDirection($currentRoom->id)));
                                  if ($d === 'D' || $d === 'DOWN') {
                                      $compassLinks['S'] = $link;
                                      unset($otherLinks[$idx]);
                                      break;
                                  }
                              }
                          }
                          $otherLinks = array_values($otherLinks);
                      }
                    ?>
                    <table id="navpad" border="0" cellpadding="2" cellspacing="0" bgcolor="<?= htmlspecialchars($skin->panelBgColor ?: 'SILVER') ?>" style="background-color:<?= htmlspecialchars($skin->panelBgColor ?: 'SILVER') ?>">
                      <tbody>
                        <!-- Row 1: Cells 1, 2, 3 -->
                        <tr>
                          <!-- Cell 1: Empty -->
                          <td><img src="<?= htmlspecialchars($skin->getSpacerIcon()) ?>" width="16" height="16" alt="" style="display:block;border:0" /></td>
                          <!-- Cell 2: Forward exit in multi-face, or North/Up exit in compass -->
                          <td>
                            <?php if ($isMultiface): ?>
                              <?php if (!empty($forwardLink)): ?>
                                <?php
                                  $actualDir = $forwardLink->getDirection($currentRoom->id);
                                  $linkDisplayName = ($forwardLink instanceof \Dimx\Link)
                                    ? $forwardLink->getDisplayName($currentRoom->id)
                                    : (!empty($forwardLink->name) && $forwardLink->name !== ($forwardLink->id ?? '') ? $forwardLink->name : \Dimx\Link::getExtendedDirectionName($actualDir));
                                  // In multiface navpad, forward direction arrow is always North pointing arrow (straight ahead)
                                  $linkIcon = !empty($forwardLink->icon) ? $forwardLink->icon : $skin->getWayIcon('N');
                                ?>
                                <a href="#" class="dimx-nav-btn dimx-onscreen-obj" data-obj-id="<?= htmlspecialchars(!empty($forwardLink->id) ? $forwardLink->id : $actualDir) ?>" data-obj-type="w" data-obj-name="<?= htmlspecialchars($linkDisplayName) ?>" data-dir="<?= htmlspecialchars($actualDir) ?>" title="<?= htmlspecialchars($linkDisplayName) ?>" onclick="sendCmd('go','<?= htmlspecialchars(!empty($forwardLink->id) ? $forwardLink->id : $actualDir) ?>');if(window.event){window.event.stopPropagation();}return false;">
                                  <img src="<?= htmlspecialchars($linkIcon) ?>" width="16" height="16" alt="<?= htmlspecialchars($linkDisplayName) ?>" title="<?= htmlspecialchars($linkDisplayName) ?>" style="border:0;display:block" />
                                </a>
                              <?php else: ?>
                                <img src="<?= htmlspecialchars($skin->getSpacerIcon()) ?>" width="16" height="16" alt="" style="display:block;border:0" />
                              <?php endif; ?>
                            <?php else: ?>
                              <?php if (!empty($compassLinks['N'])): ?>
                                <?php
                                  $link = $compassLinks['N'];
                                  $actualDir = $link->getDirection($currentRoom->id);
                                  $linkDisplayName = ($link instanceof \Dimx\Link)
                                    ? $link->getDisplayName($currentRoom->id)
                                    : (!empty($link->name) && $link->name !== ($link->id ?? '') ? $link->name : \Dimx\Link::getExtendedDirectionName($actualDir));
                                  $linkIcon = !empty($link->icon) ? $link->icon : (in_array(strtoupper($actualDir), ['U', 'UP'], true) ? $skin->getWayIcon('U') : $skin->getWayIcon('N'));
                                ?>
                                <a href="#" class="dimx-nav-btn dimx-onscreen-obj" data-obj-id="<?= htmlspecialchars(!empty($link->id) ? $link->id : $actualDir) ?>" data-obj-type="w" data-obj-name="<?= htmlspecialchars($linkDisplayName) ?>" data-dir="<?= htmlspecialchars($actualDir) ?>" title="<?= htmlspecialchars($linkDisplayName) ?>" onclick="sendCmd('go','<?= htmlspecialchars(!empty($link->id) ? $link->id : $actualDir) ?>');if(window.event){window.event.stopPropagation();}return false;">
                                  <img src="<?= htmlspecialchars($linkIcon) ?>" width="16" height="16" alt="<?= htmlspecialchars($linkDisplayName) ?>" title="<?= htmlspecialchars($linkDisplayName) ?>" style="border:0;display:block" />
                                </a>
                              <?php else: ?>
                                <img src="<?= htmlspecialchars($skin->getSpacerIcon()) ?>" width="16" height="16" alt="" style="display:block;border:0" />
                              <?php endif; ?>
                            <?php endif; ?>
                          </td>
                          <!-- Cell 3: Empty -->
                          <td><img src="<?= htmlspecialchars($skin->getSpacerIcon()) ?>" width="16" height="16" alt="" style="display:block;border:0" /></td>
                        </tr>
                        <!-- Row 2: Cells 4, 5 (Center), 6 -->
                        <tr>
                          <!-- Cell 4: Rotate Left in multi-face, or West exit in compass -->
                          <td>
                            <?php if ($isMultiface): ?>
                              <?php if ($currentRoom->getExactImage($turnLeftFace) !== null): ?>
                                <a href="#" class="dimx-nav-btn" title="Rotate Left" onclick="sendCmd('rotl','');return false;">
                                  <img src="<?= htmlspecialchars($skin->getRotLIcon()) ?>" width="16" height="16" alt="Rotate Left" title="Rotate Left" style="border:0;display:block" />
                                </a>
                              <?php else: ?>
                                <img src="<?= htmlspecialchars($skin->getSpacerIcon()) ?>" width="16" height="16" alt="" style="display:block;border:0" />
                              <?php endif; ?>
                            <?php else: ?>
                              <?php if (!empty($compassLinks['W'])): ?>
                                <?php
                                  $link = $compassLinks['W'];
                                  $actualDir = $link->getDirection($currentRoom->id);
                                  $linkDisplayName = ($link instanceof \Dimx\Link)
                                    ? $link->getDisplayName($currentRoom->id)
                                    : (!empty($link->name) && $link->name !== ($link->id ?? '') ? $link->name : \Dimx\Link::getExtendedDirectionName($actualDir));
                                  $linkIcon = !empty($link->icon) ? $link->icon : $skin->getWayIcon('W');
                                ?>
                                <a href="#" class="dimx-nav-btn dimx-onscreen-obj" data-obj-id="<?= htmlspecialchars(!empty($link->id) ? $link->id : $actualDir) ?>" data-obj-type="w" data-obj-name="<?= htmlspecialchars($linkDisplayName) ?>" data-dir="<?= htmlspecialchars($actualDir) ?>" title="<?= htmlspecialchars($linkDisplayName) ?>" onclick="sendCmd('go','<?= htmlspecialchars(!empty($link->id) ? $link->id : $actualDir) ?>');if(window.event){window.event.stopPropagation();}return false;">
                                  <img src="<?= htmlspecialchars($linkIcon) ?>" width="16" height="16" alt="<?= htmlspecialchars($linkDisplayName) ?>" title="<?= htmlspecialchars($linkDisplayName) ?>" style="border:0;display:block" />
                                </a>
                              <?php else: ?>
                                <img src="<?= htmlspecialchars($skin->getSpacerIcon()) ?>" width="16" height="16" alt="" style="display:block;border:0" />
                              <?php endif; ?>
                            <?php endif; ?>
                          </td>
                          <!-- Cell 5: Center (Empty) -->
                          <td><img src="<?= htmlspecialchars($skin->getSpacerIcon()) ?>" width="16" height="16" alt="" style="display:block;border:0" /></td>
                          <!-- Cell 6: Rotate Right in multi-face, or East exit in compass -->
                          <td>
                            <?php if ($isMultiface): ?>
                              <?php if ($currentRoom->getExactImage($turnRightFace) !== null): ?>
                                <a href="#" class="dimx-nav-btn" title="Rotate Right" onclick="sendCmd('rotr','');return false;">
                                  <img src="<?= htmlspecialchars($skin->getRotRIcon()) ?>" width="16" height="16" alt="Rotate Right" title="Rotate Right" style="border:0;display:block" />
                                </a>
                              <?php else: ?>
                                <img src="<?= htmlspecialchars($skin->getSpacerIcon()) ?>" width="16" height="16" alt="" style="display:block;border:0" />
                              <?php endif; ?>
                            <?php else: ?>
                              <?php if (!empty($compassLinks['E'])): ?>
                                <?php
                                  $link = $compassLinks['E'];
                                  $actualDir = $link->getDirection($currentRoom->id);
                                  $linkDisplayName = ($link instanceof \Dimx\Link)
                                    ? $link->getDisplayName($currentRoom->id)
                                    : (!empty($link->name) && $link->name !== ($link->id ?? '') ? $link->name : \Dimx\Link::getExtendedDirectionName($actualDir));
                                  $linkIcon = !empty($link->icon) ? $link->icon : $skin->getWayIcon('E');
                                ?>
                                <a href="#" class="dimx-nav-btn dimx-onscreen-obj" data-obj-id="<?= htmlspecialchars(!empty($link->id) ? $link->id : $actualDir) ?>" data-obj-type="w" data-obj-name="<?= htmlspecialchars($linkDisplayName) ?>" data-dir="<?= htmlspecialchars($actualDir) ?>" title="<?= htmlspecialchars($linkDisplayName) ?>" onclick="sendCmd('go','<?= htmlspecialchars(!empty($link->id) ? $link->id : $actualDir) ?>');if(window.event){window.event.stopPropagation();}return false;">
                                  <img src="<?= htmlspecialchars($linkIcon) ?>" width="16" height="16" alt="<?= htmlspecialchars($linkDisplayName) ?>" title="<?= htmlspecialchars($linkDisplayName) ?>" style="border:0;display:block" />
                                </a>
                              <?php else: ?>
                                <img src="<?= htmlspecialchars($skin->getSpacerIcon()) ?>" width="16" height="16" alt="" style="display:block;border:0" />
                              <?php endif; ?>
                            <?php endif; ?>
                          </td>
                        </tr>
                        <!-- Row 3: Cells 7, 8, 9 -->
                        <tr>
                          <!-- Cell 7: Empty -->
                          <td><img src="<?= htmlspecialchars($skin->getSpacerIcon()) ?>" width="16" height="16" alt="" style="display:block;border:0" /></td>
                          <!-- Cell 8: Reverse in multi-face, or South/Down exit in compass -->
                          <td>
                            <?php if ($isMultiface): ?>
                              <?php if ($currentRoom->getExactImage($revFace) !== null): ?>
                                <a href="#" class="dimx-nav-btn" title="Reverse" onclick="sendCmd('rev','');return false;">
                                  <img src="<?= htmlspecialchars($skin->getRevIcon()) ?>" width="16" height="16" alt="Reverse" title="Reverse" style="border:0;display:block" />
                                </a>
                              <?php else: ?>
                                <img src="<?= htmlspecialchars($skin->getSpacerIcon()) ?>" width="16" height="16" alt="" style="display:block;border:0" />
                              <?php endif; ?>
                            <?php else: ?>
                              <?php if (!empty($compassLinks['S'])): ?>
                                <?php
                                  $link = $compassLinks['S'];
                                  $actualDir = $link->getDirection($currentRoom->id);
                                  $linkDisplayName = ($link instanceof \Dimx\Link)
                                    ? $link->getDisplayName($currentRoom->id)
                                    : (!empty($link->name) && $link->name !== ($link->id ?? '') ? $link->name : \Dimx\Link::getExtendedDirectionName($actualDir));
                                  $linkIcon = !empty($link->icon) ? $link->icon : (in_array(strtoupper($actualDir), ['D', 'DOWN'], true) ? $skin->getWayIcon('D') : $skin->getWayIcon('S'));
                                ?>
                                <a href="#" class="dimx-nav-btn dimx-onscreen-obj" data-obj-id="<?= htmlspecialchars(!empty($link->id) ? $link->id : $actualDir) ?>" data-obj-type="w" data-obj-name="<?= htmlspecialchars($linkDisplayName) ?>" data-dir="<?= htmlspecialchars($actualDir) ?>" title="<?= htmlspecialchars($linkDisplayName) ?>" onclick="sendCmd('go','<?= htmlspecialchars(!empty($link->id) ? $link->id : $actualDir) ?>');if(window.event){window.event.stopPropagation();}return false;">
                                  <img src="<?= htmlspecialchars($linkIcon) ?>" width="16" height="16" alt="<?= htmlspecialchars($linkDisplayName) ?>" title="<?= htmlspecialchars($linkDisplayName) ?>" style="border:0;display:block" />
                                </a>
                              <?php else: ?>
                                <img src="<?= htmlspecialchars($skin->getSpacerIcon()) ?>" width="16" height="16" alt="" style="display:block;border:0" />
                              <?php endif; ?>
                            <?php endif; ?>
                          </td>
                          <!-- Cell 9: Empty -->
                          <td><img src="<?= htmlspecialchars($skin->getSpacerIcon()) ?>" width="16" height="16" alt="" style="display:block;border:0" /></td>
                        </tr>
                      </tbody>
                    </table>

                    <!-- Other remaining exits (e.g. non-compass or duplicate directions) -->
                    <?php if (!empty($otherLinks)): ?>
                      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;justify-content:center">
                        <?php foreach ($otherLinks as $link): ?>
                          <?php
                            $dir = $link->getDirection($currentRoom->id);
                            $dirExtended = \Dimx\Link::getExtendedDirectionName($dir);
                            $otherIcon = !empty($link->icon) ? $link->icon : $skin->getWayIcon($dir);
                            $linkDisplayName = ($link instanceof \Dimx\Link)
                              ? $link->getDisplayName($currentRoom->id)
                              : (!empty($link->name) && $link->name !== ($link->id ?? '') ? $link->name : $dirExtended);
                          ?>
                          <button type="button" class="dimx-btn dimx-onscreen-obj" data-obj-id="<?= htmlspecialchars(!empty($link->id) ? $link->id : $dir) ?>" data-obj-type="w" data-obj-name="<?= htmlspecialchars($linkDisplayName) ?>" title="<?= htmlspecialchars($dirExtended) ?>" onclick="sendCmd('go','<?= htmlspecialchars(!empty($link->id) ? $link->id : $dir) ?>');if(window.event){window.event.stopPropagation();}">
                            <img src="<?= htmlspecialchars($otherIcon) ?>" width="16" height="16" alt="<?= htmlspecialchars($dirExtended) ?>" title="<?= htmlspecialchars($dirExtended) ?>" style="vertical-align:middle;border:0" />
                            <?= htmlspecialchars($linkDisplayName) ?>
                          </button>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                    <?php if (empty($links)): ?>
                      <div style="font-size:12px;color:#64748b;text-align:center;margin-bottom:12px">No visible exits.</div>
                    <?php endif; ?>

                    <!-- People in room (offscreen characters) -->
                    <?php
                      $offscreenChars = array_filter($occupants, fn($c) => $c !== $player && $c->showmode === \Dimx\AdvObject::SHOW_OFFSCREEN);
                    ?>
                    <?php if (!empty($offscreenChars)): ?>
                      <div class="dimx-section-title" style="color:#a78bfa">People</div>
                      <div style="display:flex;flex-direction:column;gap:4px;margin-bottom:10px">
                        <?php foreach ($offscreenChars as $oc): ?>
                          <div>
                            <a href="javascript:void(0)"
                               class="dimx-onscreen-obj"
                               data-obj-id="<?= htmlspecialchars($oc->id) ?>"
                               data-obj-type="c"
                               data-obj-name="<?= htmlspecialchars($oc->name) ?>"
                               onclick="clickObject(this)"
                               style="color:#e2e8f0;text-decoration:none;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px">
                               <?php if (!empty($oc->icon)): ?>
                                 <img src="<?= htmlspecialchars($oc->icon) ?>" alt="<?= htmlspecialchars($oc->name) ?>" style="width:20px;height:20px;object-fit:contain;vertical-align:middle;border-radius:2px" />
                               <?php else: ?>
                                 <span>👤</span>
                               <?php endif; ?>
                               <span><?= htmlspecialchars($oc->name) ?></span>
                            </a>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>

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
                    <div id="msg_<?= htmlspecialchars((string)($chatMsg['id'] ?? '')) ?>" class="chat-line">
                      <a href="javascript:void(0)" class="chat-pop-btn" onclick="popMsg('<?= htmlspecialchars((string)($chatMsg['id'] ?? '')) ?>')" title="Dismiss message">✕</a>
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
              'use2': {
                noArgs: 2,
                model: 'OO',
                allowDouble: false,
                desc: 'Use ... With ...',
                prompt: '👉 Click an item to USE, then click an object to use it WITH'
              },
              'usewith': {
                noArgs: 2,
                model: 'OO',
                allowDouble: false,
                desc: 'Use ... With ...',
                prompt: '👉 Click an item to USE, then click an object to use it WITH'
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
                desc: 'PickUp ...',
                prompt: '👉 Click an item to PICK UP (or click PickUp again to pick everything)'
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
              'give': {
                noArgs: 2,
                model: 'OO',
                allowDouble: false,
                desc: 'Give ... To ...',
                prompt: '👉 Click an item to GIVE, then click a character to give it TO'
              },
              'enter': {
                noArgs: 1,
                model: 'O',
                allowDouble: false,
                desc: 'Enter ...',
                prompt: '👉 Click a vehicle to ENTER'
              },
              'help':   { noArgs: 0, model: '',  desc: 'Help' },
              'save':   { noArgs: 0, model: 'T', desc: 'Save' },
              'logout': { noArgs: 0, model: '',  desc: 'Disconnect' },
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
                  const host = (typeof target === 'object' && target) ? ((target.jquery ? target[0] : target).closest?.('.dimx-onscreen-obj') || target) : null;
                  const dir = host?.dataset?.dir || host?.getAttribute?.('data-dir') || fallbackParam || obj.id;
                  sendCmd('go', obj.id || dir);
                } else {
                  // Direct click on object inspects it (click / onLook)
                  sendCmd('click', '', obj.id);
                }
                return;
              }

              // 2. A command is currently awaiting a target:
              // DimensioneX special rule: 'use' on a link/way waypoint translates to 'go'
              if (pendingCmd === 'use' && obj.type === 'w') {
                const host = (typeof target === 'object' && target) ? ((target.jquery ? target[0] : target).closest?.('.dimx-onscreen-obj') || target) : null;
                const dir = host?.dataset?.dir || host?.getAttribute?.('data-dir') || fallbackParam || obj.id;
                clearPendingCmd();
                sendCmd('go', obj.id || dir);
                return;
              }

              // Collect target argument
              pendingArgs.push(obj.id);
              pendingNames.push(obj.name);

              const def = COMMAND_DEFS[pendingCmd] || { noArgs: 1 };
              if (pendingArgs.length >= (def.noArgs || 1)) {
                // All required arguments acquired -> execute command!
                const cmdToSubmit = pendingCmd;
                const firstArg    = pendingArgs[0];
                const secondArg   = pendingArgs.length > 1 ? pendingArgs[1] : '';
                clearPendingCmd();
                if ((def.noArgs || 1) === 1) {
                  submitCmd(cmdToSubmit, firstArg, '');
                } else {
                  submitCmd(cmdToSubmit, firstArg, secondArg);
                }
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
                if (pendingCmd === 'use2' || pendingCmd === 'usewith') {
                  msg = '👉 Use ' + pendingNames[0] + ' WITH ... (click target object)';
                } else if (pendingCmd === 'give') {
                  msg = '👉 Give ' + pendingNames[0] + ' TO ... (click recipient character)';
                } else {
                  msg = pendingCmd.toUpperCase() + ' ' + pendingNames.join(', ') + '...';
                }
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
              if (this.getAttribute('onclick')?.includes('sendCmd')) {
                return;
              }
              clickObject(this);
              e.preventDefault();
            });

            // ---- Game command submission ----
            function sendCmd(cmd, param = '', target = '') {
              const now = Date.now();
              const targetId = target || param;
              if (targetId) {
                lastClickedObjId = targetId;
                lastClickedObjTime = now;
              }

              // If a command is currently pending (e.g. open, usewith, etc.), target this object/exit!
              if (pendingCmd && (cmd === 'go' || cmd === 'click')) {
                // DimensioneX: if pendingCmd is 'use', 'use' on a link translates to 'go'
                if (pendingCmd === 'use') {
                  clearPendingCmd();
                  submitCmd(cmd, param, target);
                  return;
                }
                pendingArgs.push(targetId);
                const def = COMMAND_DEFS[pendingCmd] || { noArgs: 1 };
                if (pendingArgs.length >= (def.noArgs || 1)) {
                  const cmdToSubmit = pendingCmd;
                  const firstArg = pendingArgs[0];
                  const secondArg = pendingArgs.length > 1 ? pendingArgs[1] : '';
                  clearPendingCmd();
                  if ((def.noArgs || 1) === 1) {
                    submitCmd(cmdToSubmit, firstArg, '');
                  } else {
                    submitCmd(cmdToSubmit, firstArg, secondArg);
                  }
                } else {
                  updatePrompt();
                }
                return;
              }

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
            $initLastId = (int)($player->spawnMessageId ?? 0);
            if (!empty($initialChat)) {
                $lastItem = end($initialChat);
                if (isset($lastItem['id']) && is_numeric($lastItem['id'])) {
                    $initLastId = max($initLastId, (int)$lastItem['id']);
                }
            }
            ?>
            let lastMsgId = <?= $initLastId ?>;

            const cellC = document.getElementById('cellC');
            if (cellC) cellC.scrollTop = cellC.scrollHeight;

            function popMsg(msgId) {
              const el = document.getElementById('msg_' + msgId);
              if (el) {
                el.remove();
              }
              // Issue popmsg command to engine
              fetch('multiplayer.php?format=json', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'cmd=popmsg&param=' + encodeURIComponent(msgId)
              }).catch(function() {});
            }

            function renderMessage(msg) {
              const div = document.createElement('div');
              div.id = 'msg_' + msg.id;
              div.className = 'chat-line';
              div.innerHTML = '<a href="javascript:void(0)" class="chat-pop-btn" onclick="popMsg(\'' + escHtml(msg.id) + '\')" title="Dismiss message">✕</a> '
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
                    if (msg.id && !isNaN(msg.id)) {
                      lastMsgId = Math.max(lastMsgId, Number(msg.id));
                    }
                  }
                  // Cap message box display to 10 most recent messages
                  while (box.children.length > 10) {
                    box.removeChild(box.firstChild);
                  }
                  const cellC = document.getElementById('cellC');
                  if (cellC) cellC.scrollTop = cellC.scrollHeight;
                }
              } catch (e) { /* network error — silent */ }
            }

            // Initial load + recurring poll every 3 seconds
            pollChat();
            setInterval(pollChat, 3000);

            // Responsive orientation adjustment on resize / rotate
            function updateOrientationClass() {
              const root = document.querySelector('.dimx-root');
              if (!root) return;
              if (window.innerWidth < window.innerHeight) {
                root.classList.add('dimx-portrait');
              } else if (!<?= $isPortrait ? 'true' : 'false' ?>) {
                root.classList.remove('dimx-portrait');
              }
            }
            window.addEventListener('resize', updateOrientationClass);
            window.addEventListener('orientationchange', updateOrientationClass);
          </script>
        </body>
        </html>
        <?php
    }
}
