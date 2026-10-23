<?php
declare(strict_types=1);

/**
 * maint.php
 *
 * DimensioneX Web Administration & Maintenance Page.
 * Allows checking/initializing MySQL database, managing saved player profiles,
 * and inspecting server diagnostics including lock state and chat.
 */

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

require_once __DIR__ . '/autoload.php';

use Dimx\PersistenceManager;

$config      = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
$dbConfig    = $config['db'] ?? [];
$adminPasswd = (string)($config['adminPasswd'] ?? '');
$pm          = new PersistenceManager($dbConfig, __DIR__ . '/data');

$action          = $_POST['action'] ?? $_GET['action'] ?? '';
$inputPasswd     = (string)($_POST['adminPasswd'] ?? $_SESSION['adminPasswd'] ?? '');
$isAuthenticated = ($adminPasswd !== '' && hash_equals($adminPasswd, $inputPasswd));
$message         = '';
$messageType     = 'alert';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($action)) {
    if (!$isAuthenticated) {
        $message     = "Access denied: incorrect or missing admin password. Please enter the valid admin password.";
        $messageType = 'err';
    } else {
        $_SESSION['adminPasswd'] = $inputPasswd;
        switch ($action) {
            case 'init_db':
                if ($pm->isConnected()) {
                    $res     = $pm->initTables();
                    $message = $res ? "Database tables successfully initialized (all 4 tables)." : "Failed to initialize tables.";
                } else {
                    $message     = "Cannot initialize tables: MySQL is not connected.";
                    $messageType = 'err';
                }
                break;

            case 'clear_file_cache':
                $files = array_merge(
                    glob(__DIR__ . '/data/*.json') ?: [],
                    glob(__DIR__ . '/data/*.bin')  ?: [],
                    glob(__DIR__ . '/data/*.jsonl') ?: []
                );
                foreach ($files as $f) { @unlink($f); }
                $message = "Local file cache cleared (" . count($files) . " files removed).";
                break;

            case 'reset_world':
                $worldId = 'main';
                $pm->resetWorld($worldId);
                $message = "The game world has been reset. It will be re-seeded from server configuration on the next player connection.";
                break;

            case 'clear_chat':
                $worldId = 'main';
                if ($pm->isConnected()) {
                    try {
                        $pdo = $pm->getPdo();
                        $pdo->prepare("DELETE FROM dimx_messages WHERE world_id = :w")->execute([':w' => $worldId]);
                        $message = "Chat history cleared for the game world.";
                    } catch (\Throwable $e) {
                        $message     = "Error: " . $e->getMessage();
                        $messageType = 'err';
                    }
                } else {
                    $file = __DIR__ . "/data/chat_{$worldId}.jsonl";
                    if (file_exists($file)) { @unlink($file); }
                    $message = "Local chat history cleared.";
                }
                break;
        }
    }
}

$diag       = $pm->checkConnection();
$savedFiles = array_merge(
    glob(__DIR__ . '/data/*.json')  ?: [],
    glob(__DIR__ . '/data/*.bin')   ?: [],
    glob(__DIR__ . '/data/*.jsonl') ?: []
);

// Row counts for diagnostics
$worlds   = $pm->countRows('dimx_worlds');
$players  = $pm->countRows('dimx_players');
$settings = $pm->countRows('dimx_settings');
$messages = $pm->countRows('dimx_messages');

// Active MySQL advisory locks (information_schema)
$activeLocks = [];
if ($pm->isConnected()) {
    try {
        $pdo  = $pm->getPdo();
        $stmt = $pdo->query("
            SELECT SUBSTRING_INDEX(LOCK_NAME, ':', 1) AS lock_name, LOCK_STATUS
            FROM performance_schema.metadata_locks
            WHERE LOCK_TYPE = 'USER LEVEL LOCK' AND LOCK_STATUS = 'GRANTED'
        ");
        $activeLocks = $stmt ? $stmt->fetchAll() : [];
    } catch (\Throwable $e) {
        $activeLocks = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>DimensioneX — Maintenance Console</title>
  <style>
    body{background:#0f172a;color:#f8fafc;font-family:ui-sans-serif,system-ui,sans-serif;margin:0;padding:24px}
    .maint-card{background:#1e293b;border:1px solid #334155;border-radius:8px;padding:20px;margin-bottom:20px;max-width:900px}
    .maint-card h3{margin-top:0;color:#38bdf8}
    .btn{background:#2563eb;color:#fff;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;font-weight:500}
    .btn:hover{background:#1d4ed8}
    .btn-danger{background:#dc2626}.btn-danger:hover{background:#b91c1c}
    .status-badge{display:inline-block;padding:4px 10px;border-radius:4px;font-weight:600;font-size:13px}
    .badge-ok{background:#065f46;color:#34d399}.badge-warn{background:#854d0e;color:#fde047}
    .alert{padding:12px;border-radius:6px;margin-bottom:16px;background:#1e40af;color:#dbeafe}
    .alert-err{background:#991b1b;color:#fecaca}
    table{width:100%;border-collapse:collapse;font-size:13px}
    th,td{padding:6px 10px;text-align:left;border-bottom:1px solid #334155}
    th{color:#94a3b8;text-transform:uppercase;font-size:11px;letter-spacing:.05em}
    input[type=text]{background:#0f172a;border:1px solid #475569;border-radius:4px;color:#f8fafc;padding:5px 8px;font-size:13px}
    .stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
    .stat-box{background:#0f172a;border-radius:6px;padding:12px;text-align:center}
    .stat-num{font-size:28px;font-weight:700;color:#38bdf8}
    .stat-label{font-size:11px;color:#94a3b8;text-transform:uppercase;margin-top:4px}
  </style>
</head>
<body>
<div style="max-width:900px;margin:0 auto">

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="margin:0;font-size:22px">⚙ DimensioneX Maintenance Console</h1>
    <a href="multiplayer.php" class="btn" style="text-decoration:none;font-size:13px">▶ Return to Game</a>
  </div>

  <?php if (!empty($message)): ?>
    <div class="alert <?= $messageType === 'err' ? 'alert-err' : '' ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <!-- Admin Authentication Card -->
  <div class="maint-card" style="border-left:4px solid #38bdf8">
    <h3>🔑 Administrator Authentication</h3>
    <p style="font-size:13px;color:#94a3b8">Enter the <code>adminPasswd</code> configured in <code>config.php</code> to authorize world reset and maintenance operations.</p>
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <label for="adminPasswd" style="font-size:13px;font-weight:600">Admin Password:</label>
      <input type="password" id="adminPasswd" name="adminPasswd" value="<?= htmlspecialchars($inputPasswd) ?>" placeholder="Enter admin password…" style="padding:6px 10px;background:#0f172a;border:1px solid #475569;border-radius:4px;color:#fff;font-size:14px;width:240px" oninput="updateAllFormPasswords(this.value)" />
      <?php if ($isAuthenticated): ?>
        <span class="status-badge badge-ok">✔ Authenticated</span>
      <?php else: ?>
        <span class="status-badge badge-warn">🔒 Authentication Required</span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Storage diagnostics -->
  <div class="maint-card">
    <h3>Storage &amp; Database</h3>
    <p>
      Status:
      <?php if ($diag['connected']): ?>
        <span class="status-badge badge-ok">✔ <?= $diag['type'] ?></span>
      <?php else: ?>
        <span class="status-badge badge-warn">⚠ <?= $diag['type'] ?></span>
      <?php endif; ?>
    </p>
    <p style="font-size:14px;color:#94a3b8"><?= htmlspecialchars($diag['message']) ?></p>

    <?php if ($diag['connected']): ?>
    <!-- Row count stats -->
    <div class="stat-grid" style="margin:16px 0">
      <div class="stat-box"><div class="stat-num">Active</div><div class="stat-label">Single World</div></div>
      <div class="stat-box"><div class="stat-num"><?= $players >= 0 ? $players : '?' ?></div><div class="stat-label">Players</div></div>
      <div class="stat-box"><div class="stat-num"><?= $settings >= 0 ? $settings : '?' ?></div><div class="stat-label">Settings</div></div>
      <div class="stat-box"><div class="stat-num"><?= $messages >= 0 ? $messages : '?' ?></div><div class="stat-label">Chat Messages</div></div>
    </div>
    <?php endif; ?>

    <form method="POST" style="display:inline" onsubmit="return syncPasswd(this)">
      <input type="hidden" name="action" value="init_db" />
      <input type="hidden" name="adminPasswd" class="form-admin-passwd" value="<?= htmlspecialchars($inputPasswd) ?>" />
      <button type="submit" class="btn">Initialize / Upgrade MySQL Tables</button>
    </form>
  </div>

  <!-- Active world locks -->
  <div class="maint-card">
    <h3>🔐 Active World Locks</h3>
    <?php if (empty($activeLocks)): ?>
      <p style="color:#64748b;font-size:14px">No world locks currently held. (Requires performance_schema access.)</p>
    <?php else: ?>
      <table>
        <tr><th>Lock Name</th><th>Status</th></tr>
        <?php foreach ($activeLocks as $lk): ?>
          <tr>
            <td><?= htmlspecialchars($lk['lock_name'] ?? '') ?></td>
            <td><?= htmlspecialchars($lk['LOCK_STATUS'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>

  <!-- World management -->
  <div class="maint-card">
    <h3>🌍 World Management</h3>
    <p style="font-size:13px;color:#94a3b8">Reset the game world. On the next player connection, the authoritative state will be re-seeded from the server's configured definition file. All in-game modifications (moved items, player positions) will be reset.</p>
    <form method="POST" onsubmit="return confirmAction(this, 'Reset the game world? All in-game state will be lost.')">
      <input type="hidden" name="action" value="reset_world" />
      <input type="hidden" name="adminPasswd" class="form-admin-passwd" value="<?= htmlspecialchars($inputPasswd) ?>" />
      <button type="submit" class="btn btn-danger">🔄 Reset Game World</button>
    </form>
  </div>

  <!-- Chat management -->
  <div class="maint-card">
    <h3>💬 Chat Management</h3>
    <p style="font-size:13px;color:#94a3b8">Clear the world chat log history.</p>
    <form method="POST" onsubmit="return confirmAction(this, 'Clear all chat messages for the world?')">
      <input type="hidden" name="action" value="clear_chat" />
      <input type="hidden" name="adminPasswd" class="form-admin-passwd" value="<?= htmlspecialchars($inputPasswd) ?>" />
      <button type="submit" class="btn btn-danger">Clear Chat History</button>
    </form>
  </div>

  <!-- Local file cache -->
  <div class="maint-card">
    <h3>📂 Local File Cache</h3>
    <p style="font-size:14px;color:#94a3b8">Found <?= count($savedFiles) ?> files in <code>php/data/</code> (used as fallback when MySQL is unavailable).</p>
    <?php if (!empty($savedFiles)): ?>
      <ul style="font-size:12px;color:#cbd5e1;font-family:monospace;max-height:180px;overflow-y:auto">
        <?php foreach ($savedFiles as $file): ?>
          <li><?= htmlspecialchars(basename($file)) ?> (<?= number_format(filesize($file)) ?> B)</li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <form method="POST" style="margin-top:10px"
          onsubmit="return confirmAction(this, 'Erase all local cache files?')">
      <input type="hidden" name="action" value="clear_file_cache" />
      <input type="hidden" name="adminPasswd" class="form-admin-passwd" value="<?= htmlspecialchars($inputPasswd) ?>" />
      <button type="submit" class="btn btn-danger">Clear Local File Cache</button>
    </form>
  </div>

  <!-- Config reference -->
  <div class="maint-card">
    <h3>Configuration Reference</h3>
    <pre style="background:#090d16;padding:12px;border-radius:6px;font-size:12px;color:#38bdf8;overflow-x:auto">// php/config.php
return [
    'db' => [
        'host'   => '127.0.0.1',
        'port'   => 3306,
        'dbname' => 'dimya',
        'user'   => 'root',
        'pass'   => 'secret',
    ],
    'adminPasswd' => 'admin123',
    'world_file'  => __DIR__ . '/../docs/dxw-examples/world2.dxw',
    'base_url'    => 'http://localhost',
];</pre>
  </div>

</div>

<script>
  function updateAllFormPasswords(val) {
    document.querySelectorAll('.form-admin-passwd').forEach(function(el) {
      el.value = val;
    });
  }

  function syncPasswd(form) {
    var pwdInput = document.getElementById('adminPasswd');
    var pwd = pwdInput ? pwdInput.value : '';
    var hidden = form.querySelector('.form-admin-passwd');
    if (hidden) hidden.value = pwd;
    if (!pwd) {
      alert('Please enter the admin password first.');
      if (pwdInput) pwdInput.focus();
      return false;
    }
    return true;
  }

  function confirmAction(form, msg) {
    if (!syncPasswd(form)) return false;
    return confirm(msg);
  }
</script>
</body>
</html>
