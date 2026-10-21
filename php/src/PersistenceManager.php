<?php
declare(strict_types=1);

namespace Dimx;

use PDO;
use PDOException;

/**
 * PersistenceManager
 *
 * Handles game persistence using MySQL via PDO, with transparent fallback to
 * JSON file storage if MySQL is unconfigured or temporarily unavailable.
 *
 * Key design:
 *  - World state is stored as a full PHP-serialized + base64-encoded blob in
 *    dimx_worlds.state_json.  This makes save/load trivially round-trip the
 *    entire object graph (rooms, items, links, characters, events, players).
 *  - Player console messages pending delivery are stored per-player in
 *    dimx_players.console_json so they survive across HTTP requests.
 *  - Chat messages live in dimx_messages and are written/read WITHOUT holding
 *    the world lock (fully concurrent).
 */
class PersistenceManager
{
    protected ?PDO $pdo = null;
    protected bool $dbConnected = false;
    protected string $storageDir = '';
    protected array $dbConfig = [];

    public function __construct(array $dbConfig = [], string $storageDir = __DIR__ . '/../data')
    {
        $this->dbConfig   = $dbConfig;
        $this->storageDir = rtrim($storageDir, '/\\');

        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0777, true);
        }

        $this->connect();
    }

    /**
     * Prevent serializing the live PDO instance.
     */
    public function __sleep(): array
    {
        return ['dbConfig', 'storageDir', 'dbConnected'];
    }

    public function __wakeup(): void
    {
        $this->connect();
    }

    // =========================================================================
    // Connection
    // =========================================================================

    /**
     * Connect to MySQL database or switch to file storage fallback.
     */
    public function connect(): bool
    {
        if (empty($this->dbConfig['dbname'])) {
            $this->dbConnected = false;
            return false;
        }

        $host   = $this->dbConfig['host']   ?? '127.0.0.1';
        $port   = $this->dbConfig['port']   ?? 3306;
        $dbname = $this->dbConfig['dbname'];
        $user   = $this->dbConfig['user']   ?? 'root';
        $pass   = $this->dbConfig['pass']   ?? '';

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $this->dbConnected = true;
            $this->initTables();
            return true;
        } catch (PDOException $e) {
            $this->pdo         = null;
            $this->dbConnected = false;
            return false;
        }
    }

    public function isConnected(): bool
    {
        return $this->dbConnected && $this->pdo !== null;
    }

    /** Return the raw PDO handle (needed by WorldLock). */
    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    // =========================================================================
    // Schema
    // =========================================================================

    /**
     * Initialize required MySQL schema if not present.
     */
    public function initTables(): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        // Each DDL statement runs in its own try/catch so a failure on one
        // (e.g. column already exists, or unsupported syntax on older MariaDB)
        // never prevents the rest from executing.
        $errors = [];

        $statements = [
            // ---- CREATE tables (safe to repeat) ----
            'create_worlds' => "CREATE TABLE IF NOT EXISTS dimx_worlds (
                world_id    VARCHAR(64)  PRIMARY KEY,
                name        VARCHAR(128) NOT NULL,
                instance_id VARCHAR(64)  NOT NULL DEFAULT '',
                mode        INT          DEFAULT 1,
                state_json  LONGTEXT,
                world_src   VARCHAR(512) DEFAULT NULL,
                updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'create_players' => "CREATE TABLE IF NOT EXISTS dimx_players (
                player_id       VARCHAR(64)  NOT NULL,
                world_id        VARCHAR(64)  NOT NULL,
                name            VARCHAR(128) NOT NULL,
                password        VARCHAR(128) NOT NULL DEFAULT '',
                score           INT          DEFAULT 0,
                container_id    VARCHAR(64)  NOT NULL DEFAULT '',
                properties_json MEDIUMTEXT,
                items_json      MEDIUMTEXT,
                console_json    MEDIUMTEXT,
                updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (world_id, player_id),
                INDEX idx_world (world_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'create_settings' => "CREATE TABLE IF NOT EXISTS dimx_settings (
                world_id      VARCHAR(64)  NOT NULL,
                setting_key   VARCHAR(128) NOT NULL,
                setting_value MEDIUMTEXT,
                updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (world_id, setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'create_messages' => "CREATE TABLE IF NOT EXISTS dimx_messages (
                message_id   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                world_id     VARCHAR(64)  NOT NULL,
                sender_name  VARCHAR(128) NOT NULL,
                message_text TEXT         NOT NULL,
                created_at   TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
                INDEX idx_world_time (world_id, message_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // ---- ALTER: add missing columns (each in its own statement) ----
            'alter_worlds_src'     => "ALTER TABLE dimx_worlds ADD COLUMN IF NOT EXISTS world_src VARCHAR(512) DEFAULT NULL",
            'alter_worlds_json'    => "ALTER TABLE dimx_worlds MODIFY COLUMN state_json LONGTEXT",
            'alter_players_console'=> "ALTER TABLE dimx_players ADD COLUMN IF NOT EXISTS console_json MEDIUMTEXT",
        ];

        foreach ($statements as $key => $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (PDOException $e) {
                $errors[$key] = $e->getMessage();
                error_log("PersistenceManager::initTables [{$key}]: " . $e->getMessage());
            }
        }

        // Return true if no errors, or only ALTER errors (which often just mean
        // the column already exists in the right form — not a real problem).
        $createErrors = array_filter($errors, fn($k) => str_starts_with($k, 'create_'), ARRAY_FILTER_USE_KEY);
        return empty($createErrors);
    }

    // =========================================================================
    // World state (full serialized object graph)
    // =========================================================================

    /**
     * Save the complete world object to MySQL (or file fallback).
     *
     * The world is serialized with PHP serialize() and base64-encoded so it
     * survives round-trips through TEXT columns without encoding issues.
     * The PersistenceManager reference is excluded via World::__sleep().
     */
    public function saveWorld(World $world, ?string $worldSrc = null): bool
    {
        // Detach persistence before serializing (PDO is not serializable).
        $pm = $world->persistence;
        $world->persistence = null;
        $blob = base64_encode(serialize($world));
        $world->persistence = $pm;

        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO dimx_worlds (world_id, name, instance_id, mode, state_json, world_src)
                    VALUES (:wid, :name, :inst, :mode, :json, :src)
                    ON DUPLICATE KEY UPDATE
                        name        = VALUES(name),
                        instance_id = VALUES(instance_id),
                        mode        = VALUES(mode),
                        state_json  = VALUES(state_json),
                        world_src   = COALESCE(VALUES(world_src), world_src)
                ");
                return $stmt->execute([
                    ':wid'  => $world->id,
                    ':name' => $world->name,
                    ':inst' => $world->instanceId,
                    ':mode' => $world->mode,
                    ':json' => $blob,
                    ':src'  => $worldSrc,
                ]);
            } catch (PDOException $e) {
                error_log("PersistenceManager::saveWorld DB error: " . $e->getMessage());
            }
        }

        // File-based fallback
        $file = "{$this->storageDir}/world_{$world->id}.bin";
        return file_put_contents($file, $blob) !== false;
    }

    /**
     * Load the complete world object from MySQL (or file fallback).
     *
     * Returns the deserialized World on success, or null if not found.
     */
    public function loadWorld(string $worldId): ?World
    {
        $blob = null;

        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("SELECT state_json FROM dimx_worlds WHERE world_id = :wid");
                $stmt->execute([':wid' => $worldId]);
                $row = $stmt->fetchColumn();
                if ($row !== false && $row !== null && $row !== '') {
                    $blob = $row;
                }
            } catch (PDOException $e) {
                error_log("PersistenceManager::loadWorld DB error: " . $e->getMessage());
            }
        }

        if ($blob === null) {
            $file = "{$this->storageDir}/world_{$worldId}.bin";
            if (file_exists($file)) {
                $blob = file_get_contents($file);
            }
        }

        if ($blob === null || $blob === '') {
            return null;
        }

        $world = @unserialize(base64_decode($blob));
        if (!$world instanceof World) {
            error_log("PersistenceManager::loadWorld — deserialization failed for world '{$worldId}'");
            return null;
        }

        return $world;
    }

    /**
     * Get the stored world_src for a world (the .dxw URL/path used to seed it).
     */
    public function getWorldSrc(string $worldId): ?string
    {
        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("SELECT world_src FROM dimx_worlds WHERE world_id = :wid");
                $stmt->execute([':wid' => $worldId]);
                $val = $stmt->fetchColumn();
                return ($val !== false && $val !== null) ? (string)$val : null;
            } catch (PDOException $e) {
                // Fallthrough
            }
        }
        return null;
    }

    // =========================================================================
    // Player persistence (score, location, properties, inventory)
    // =========================================================================

    /**
     * Save player profile (properties, inventory, score, location, pending console lines).
     */
    public function savePlayer(Player $player): bool
    {
        $worldId = $player->world?->id ?? 'default';

        $itemIds = [];
        foreach ($player->contents as $item) {
            if ($item instanceof Item) {
                $itemIds[] = [
                    'id'         => $item->id,
                    'type'       => $item->type,
                    'name'       => $item->name,
                    'volume'     => $item->volume,
                    'properties' => $item->getProperties(),
                ];
            }
        }

        $containerId    = $player->container?->id ?? '';
        $propertiesJson = json_encode($player->getProperties());
        $itemsJson      = json_encode($itemIds);
        // Drain any pending console lines and persist them
        $console        = $player->drainConsole();
        $consoleJson    = json_encode($console);

        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO dimx_players
                        (player_id, world_id, name, password, score, container_id, properties_json, items_json, console_json)
                    VALUES
                        (:pid, :wid, :name, :pass, :score, :cid, :props, :items, :console)
                    ON DUPLICATE KEY UPDATE
                        name            = VALUES(name),
                        password        = VALUES(password),
                        score           = VALUES(score),
                        container_id    = VALUES(container_id),
                        properties_json = VALUES(properties_json),
                        items_json      = VALUES(items_json),
                        console_json    = VALUES(console_json)
                ");
                return $stmt->execute([
                    ':pid'     => $player->id,
                    ':wid'     => $worldId,
                    ':name'    => $player->name,
                    ':pass'    => $player->password,
                    ':score'   => $player->score,
                    ':cid'     => $containerId,
                    ':props'   => $propertiesJson,
                    ':items'   => $itemsJson,
                    ':console' => $consoleJson,
                ]);
            } catch (PDOException $e) {
                error_log("PersistenceManager::savePlayer DB error: " . $e->getMessage());
            }
        }

        // File-based fallback
        $file = "{$this->storageDir}/player_{$worldId}_{$player->id}.json";
        $data = [
            'player_id'   => $player->id,
            'world_id'    => $worldId,
            'name'        => $player->name,
            'password'    => $player->password,
            'score'       => $player->score,
            'container_id'=> $containerId,
            'properties'  => $player->getProperties(),
            'items'       => $itemIds,
            'console'     => $console,
            'updated_at'  => date('Y-m-d H:i:s'),
        ];
        return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }

    /**
     * Load player profile from DB or JSON file and restore into world.
     */
    public function loadPlayer(World $world, string $playerId, string $password = ''): ?Player
    {
        $data = null;

        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT * FROM dimx_players WHERE world_id = :wid AND player_id = :pid
                ");
                $stmt->execute([':wid' => $world->id, ':pid' => $playerId]);
                $row = $stmt->fetch();
                if ($row) {
                    $data = [
                        'player_id'    => $row['player_id'],
                        'name'         => $row['name'],
                        'password'     => $row['password'],
                        'score'        => (int)$row['score'],
                        'container_id' => $row['container_id'],
                        'properties'   => json_decode($row['properties_json'] ?: '{}', true) ?: [],
                        'items'        => json_decode($row['items_json']       ?: '[]', true) ?: [],
                        'console'      => json_decode($row['console_json']     ?: '[]', true) ?: [],
                    ];
                }
            } catch (PDOException $e) {
                error_log("PersistenceManager::loadPlayer DB error: " . $e->getMessage());
            }
        }

        if ($data === null) {
            $file = "{$this->storageDir}/player_{$world->id}_{$playerId}.json";
            if (file_exists($file)) {
                $data = json_decode((string)file_get_contents($file), true);
            }
        }

        if ($data === null) {
            return null;
        }

        // Validate password if supplied
        if (!empty($data['password']) && !empty($password) && $data['password'] !== $password) {
            return null;
        }

        $player = $world->getPlayer($playerId) ?? new Player($world, $data['name'], $playerId);
        $player->password = $data['password'] ?? '';
        $player->score    = $data['score'] ?? 0;
        if (!empty($data['properties'])) {
            $player->setAttrList($data['properties']);
        }

        $targetRoom = $world->getRoom($data['container_id'] ?? '') ?? reset($world->rooms);
        if ($targetRoom instanceof Room) {
            $player->move($targetRoom);
        }

        $world->addPlayer($player);

        // Restore pending console lines
        foreach ($data['console'] ?? [] as $line) {
            $player->display($line);
        }

        // Auto-restore items
        foreach ($data['items'] as $itemData) {
            $item = $world->getItem($itemData['id']);
            if ($item === null) {
                foreach ($world->items as $candidate) {
                    if ($candidate->matchesType($itemData['type'] ?? '') && $candidate->container instanceof Room) {
                        $item = $candidate;
                        break;
                    }
                }
            }
            if ($item !== null) {
                $item->restoreTo($player, $world);
            }
        }

        return $player;
    }

    // =========================================================================
    // Settings
    // =========================================================================

    public function saveSetting(string $worldId, string $key, mixed $value): bool
    {
        $valStr = is_string($value) ? $value : json_encode($value);

        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO dimx_settings (world_id, setting_key, setting_value)
                    VALUES (:wid, :k, :v)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                return $stmt->execute([':wid' => $worldId, ':k' => $key, ':v' => $valStr]);
            } catch (PDOException $e) {
                error_log("PersistenceManager::saveSetting error: " . $e->getMessage());
            }
        }

        $file     = "{$this->storageDir}/settings_{$worldId}.json";
        $settings = file_exists($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
        $settings[$key] = $value;
        return file_put_contents($file, json_encode($settings, JSON_PRETTY_PRINT)) !== false;
    }

    public function getSetting(string $worldId, string $key, mixed $default = null): mixed
    {
        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT setting_value FROM dimx_settings WHERE world_id = :wid AND setting_key = :k
                ");
                $stmt->execute([':wid' => $worldId, ':k' => $key]);
                $val = $stmt->fetchColumn();
                if ($val !== false) {
                    return $val;
                }
            } catch (PDOException $e) {
                error_log("PersistenceManager::getSetting error: " . $e->getMessage());
            }
        }

        $file = "{$this->storageDir}/settings_{$worldId}.json";
        if (file_exists($file)) {
            $settings = json_decode((string)file_get_contents($file), true);
            if (isset($settings[$key])) {
                return $settings[$key];
            }
        }

        return $default;
    }

    // =========================================================================
    // Chat messages (fully concurrent — no world lock required)
    // =========================================================================

    /**
     * Post a chat message from a player. No lock needed — INSERT is atomic.
     */
    public function postMessage(string $worldId, string $senderName, string $text): bool
    {
        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO dimx_messages (world_id, sender_name, message_text)
                    VALUES (:wid, :sender, :text)
                ");
                return $stmt->execute([':wid' => $worldId, ':sender' => $senderName, ':text' => $text]);
            } catch (PDOException $e) {
                error_log("PersistenceManager::postMessage error: " . $e->getMessage());
            }
        }

        // File-based fallback (append to JSONL)
        $file = "{$this->storageDir}/chat_{$worldId}.jsonl";
        $line = json_encode([
            'id'      => time(),
            'sender'  => $senderName,
            'text'    => $text,
            'time'    => date('Y-m-d H:i:s'),
        ]) . "\n";
        return file_put_contents($file, $line, FILE_APPEND | LOCK_EX) !== false;
    }

    /**
     * Fetch chat messages newer than $afterId (message_id, not timestamp).
     * Returns array of ['id', 'sender', 'text', 'time'] sorted ascending.
     *
     * @param string $worldId
     * @param int    $afterId  Last known message_id (0 = return last $limit messages)
     * @param int    $limit    Max rows to return
     */
    public function getMessages(string $worldId, int $afterId = 0, int $limit = 50): array
    {
        if ($this->isConnected()) {
            try {
                if ($afterId > 0) {
                    $stmt = $this->pdo->prepare("
                        SELECT message_id AS id, sender_name AS sender, message_text AS text,
                               DATE_FORMAT(created_at, '%H:%i:%s') AS time
                        FROM dimx_messages
                        WHERE world_id = :wid AND message_id > :after
                        ORDER BY message_id ASC
                        LIMIT :lim
                    ");
                    $stmt->execute([':wid' => $worldId, ':after' => $afterId, ':lim' => $limit]);
                } else {
                    // Initial load: return last $limit messages
                    $stmt = $this->pdo->prepare("
                        SELECT message_id AS id, sender_name AS sender, message_text AS text,
                               DATE_FORMAT(created_at, '%H:%i:%s') AS time
                        FROM (
                            SELECT message_id, sender_name, message_text, created_at
                            FROM dimx_messages
                            WHERE world_id = :wid
                            ORDER BY message_id DESC
                            LIMIT :lim
                        ) sub
                        ORDER BY message_id ASC
                    ");
                    $stmt->execute([':wid' => $worldId, ':lim' => $limit]);
                }
                return $stmt->fetchAll();
            } catch (PDOException $e) {
                error_log("PersistenceManager::getMessages error: " . $e->getMessage());
            }
        }

        // File-based fallback
        $file = "{$this->storageDir}/chat_{$worldId}.jsonl";
        if (!file_exists($file)) {
            return [];
        }
        $lines  = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $result = [];
        $i      = 0;
        foreach ($lines as $line) {
            $msg = json_decode($line, true);
            if ($msg && $msg['id'] > $afterId) {
                $result[] = ['id' => $msg['id'], 'sender' => $msg['sender'], 'text' => $msg['text'], 'time' => $msg['time'] ?? ''];
            }
        }
        return array_slice($result, -$limit);
    }

    // =========================================================================
    // Diagnostics
    // =========================================================================

    public function checkConnection(): array
    {
        if ($this->isConnected()) {
            return [
                'connected' => true,
                'type'      => 'MySQL (PDO)',
                'message'   => "Connected to MySQL database '{$this->dbConfig['dbname']}' on {$this->dbConfig['host']}.",
            ];
        }
        return [
            'connected' => false,
            'type'      => 'JSON File Storage',
            'message'   => "MySQL is unconfigured or unreachable. Using file-based storage in {$this->storageDir}.",
        ];
    }

    /**
     * Count rows in a table for diagnostics.
     */
    public function countRows(string $table): int
    {
        if (!$this->isConnected()) {
            return -1;
        }
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM `{$table}`");
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return -1;
        }
    }
}
