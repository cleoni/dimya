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
                id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                world_id        VARCHAR(64)  NOT NULL,
                name            VARCHAR(128) NOT NULL,
                player_id       VARCHAR(64)  NOT NULL DEFAULT '',
                password        VARCHAR(255) NOT NULL DEFAULT '',
                is_bot          TINYINT(1)   DEFAULT 0,
                score           INT          DEFAULT 0,
                container_id    VARCHAR(64)  NOT NULL DEFAULT '',
                properties_json MEDIUMTEXT,
                items_json      MEDIUMTEXT,
                console_json    MEDIUMTEXT,
                updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_world_name (world_id, name),
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
                message_id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                world_id       VARCHAR(64)  NOT NULL,
                sender_name    VARCHAR(128) NOT NULL,
                recipient_name VARCHAR(128) DEFAULT NULL,
                message_text   TEXT         NOT NULL,
                created_at     TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
                INDEX idx_world_time (world_id, message_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'create_commands' => "CREATE TABLE IF NOT EXISTS dimx_commands (
                command_id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                world_id       VARCHAR(64)  NOT NULL,
                player_name    VARCHAR(128) NOT NULL,
                command_text   VARCHAR(255) NOT NULL,
                created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_world_player (world_id, player_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'create_goals' => "CREATE TABLE IF NOT EXISTS dimx_goals (
                goal_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                world_id      VARCHAR(64)  NOT NULL,
                player_name   VARCHAR(128) NOT NULL,
                score         INT          DEFAULT 0,
                world_version VARCHAR(32)  DEFAULT NULL,
                message       TEXT         DEFAULT NULL,
                created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_world (world_id),
                INDEX idx_world_player (world_id, player_name),
                INDEX idx_world_time (world_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        foreach ($statements as $key => $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (PDOException $e) {
                $errors[$key] = $e->getMessage();
                error_log("PersistenceManager::initTables [{$key}]: " . $e->getMessage());
            }
        }

        // ---- ALTER: add missing columns safely across all MySQL & MariaDB versions ----
        // Avoid "ADD COLUMN IF NOT EXISTS" because standard MySQL (< 8.0.29) throws syntax error 1064.
        $columnAdditions = [
            'dimx_worlds'   => [
                'world_src' => "ALTER TABLE dimx_worlds ADD COLUMN world_src VARCHAR(512) DEFAULT NULL",
            ],
            'dimx_players'  => [
                'console_json' => "ALTER TABLE dimx_players ADD COLUMN console_json MEDIUMTEXT",
                'is_bot'       => "ALTER TABLE dimx_players ADD COLUMN is_bot TINYINT(1) DEFAULT 0",
                'id'           => "ALTER TABLE dimx_players ADD COLUMN id INT UNSIGNED AUTO_INCREMENT UNIQUE FIRST",
            ],
            'dimx_messages' => [
                'recipient_name' => "ALTER TABLE dimx_messages ADD COLUMN recipient_name VARCHAR(128) DEFAULT NULL",
            ],
        ];

        foreach ($columnAdditions as $table => $cols) {
            foreach ($cols as $col => $alterSql) {
                try {
                    $checkStmt = $this->pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
                    if (!$checkStmt->fetch()) {
                        $this->pdo->exec($alterSql);
                    }
                } catch (PDOException $e) {
                    $errors["alter_{$table}_{$col}"] = $e->getMessage();
                    error_log("PersistenceManager::initTables [alter_{$table}_{$col}]: " . $e->getMessage());
                }
            }
        }

        // Modifications that are safe to repeat
        $modifications = [
            'alter_worlds_json'  => "ALTER TABLE dimx_worlds MODIFY COLUMN state_json LONGTEXT",
            'alter_players_pass' => "ALTER TABLE dimx_players MODIFY COLUMN password VARCHAR(255) NOT NULL DEFAULT ''",
        ];

        foreach ($modifications as $key => $sql) {
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

    /**
     * Reset / delete a world record from MySQL and file cache,
     * invalidating active player sessions and resetting player positions.
     */
    public function resetWorld(string $worldId, bool $purgePlayers = false): bool
    {
        $resetToken = (string)time() . '_' . bin2hex(random_bytes(4));
        $this->saveSetting($worldId, 'world_reset_token', $resetToken);

        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("DELETE FROM dimx_worlds WHERE world_id = :wid");
                $stmt->execute([':wid' => $worldId]);
                $stmt = $this->pdo->prepare("DELETE FROM dimx_messages WHERE world_id = :wid");
                $stmt->execute([':wid' => $worldId]);

                if ($purgePlayers) {
                    $stmt = $this->pdo->prepare("DELETE FROM dimx_players WHERE world_id = :wid");
                    $stmt->execute([':wid' => $worldId]);
                } else {
                    $stmt = $this->pdo->prepare("UPDATE dimx_players SET container_id = '', score = 0, items_json = '[]', console_json = '[]', properties_json = '{}' WHERE world_id = :wid");
                    $stmt->execute([':wid' => $worldId]);
                }
            } catch (PDOException $e) {
                error_log("PersistenceManager::resetWorld DB error: " . $e->getMessage());
            }
        }
        $file = "{$this->storageDir}/world_{$worldId}.bin";
        if (file_exists($file)) {
            @unlink($file);
        }
        $msgFile = "{$this->storageDir}/messages_{$worldId}.jsonl";
        if (file_exists($msgFile)) {
            @unlink($msgFile);
        }
        if ($purgePlayers) {
            $playerFiles = glob("{$this->storageDir}/player_{$worldId}_*.json") ?: [];
            foreach ($playerFiles as $pf) {
                @unlink($pf);
            }
        }
        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("DELETE FROM dimx_commands WHERE world_id = :wid");
                $stmt->execute([':wid' => $worldId]);
            } catch (PDOException $e) {}
        }
        $cmdFiles = glob("{$this->storageDir}/cmd_{$worldId}_*.txt") ?: [];
        foreach ($cmdFiles as $cf) {
            @unlink($cf);
        }
        return true;
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
        $isBotVal       = !empty($player->isBot) ? 1 : 0;

        if ($this->isConnected()) {
            try {
                // If numericId is set, update account record by primary key
                if ($player->numericId !== null && (int)$player->numericId > 0) {
                    $stmt = $this->pdo->prepare("
                        UPDATE dimx_players SET
                            player_id       = :pid,
                            name            = :name,
                            is_bot          = :bot,
                            score           = :score,
                            container_id    = :cid,
                            properties_json = :props,
                            items_json      = :items,
                            console_json    = :console
                        WHERE id = :id
                    ");
                    return $stmt->execute([
                        ':pid'     => $player->name,
                        ':name'    => $player->name,
                        ':bot'     => $isBotVal,
                        ':score'   => $player->score,
                        ':cid'     => $containerId,
                        ':props'   => $propertiesJson,
                        ':items'   => $itemsJson,
                        ':console' => $consoleJson,
                        ':id'      => (int)$player->numericId,
                    ]);
                }

                // Otherwise insert or update by unique (world_id, name)
                $stmt = $this->pdo->prepare("
                    INSERT INTO dimx_players
                        (world_id, name, player_id, password, is_bot, score, container_id, properties_json, items_json, console_json)
                    VALUES
                        (:wid, :name, :pid, :pass, :bot, :score, :cid, :props, :items, :console)
                    ON DUPLICATE KEY UPDATE
                        player_id       = VALUES(player_id),
                        name            = VALUES(name),
                        password        = CASE WHEN VALUES(password) != '' THEN VALUES(password) ELSE password END,
                        is_bot          = VALUES(is_bot),
                        score           = VALUES(score),
                        container_id    = VALUES(container_id),
                        properties_json = VALUES(properties_json),
                        items_json      = VALUES(items_json),
                        console_json    = VALUES(console_json)
                ");
                $ok = $stmt->execute([
                    ':wid'     => $worldId,
                    ':name'    => $player->name,
                    ':pid'     => $player->name,
                    ':pass'    => $player->password,
                    ':bot'     => $isBotVal,
                    ':score'   => $player->score,
                    ':cid'     => $containerId,
                    ':props'   => $propertiesJson,
                    ':items'   => $itemsJson,
                    ':console' => $consoleJson,
                ]);

                if ($player->numericId === null) {
                    $idStmt = $this->pdo->prepare("SELECT id FROM dimx_players WHERE world_id = :wid AND LOWER(name) = :name LIMIT 1");
                    $idStmt->execute([':wid' => $worldId, ':name' => strtolower($player->name)]);
                    $idVal = $idStmt->fetchColumn();
                    if ($idVal !== false) {
                        $player->numericId = (int)$idVal;
                    }
                }

                return $ok;
            } catch (PDOException $e) {
                error_log("PersistenceManager::savePlayer DB error: " . $e->getMessage());
            }
        }

        // File-based fallback
        $file = "{$this->storageDir}/player_{$worldId}_{$player->name}.json";
        $existing = file_exists($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
        if ($player->numericId === null) {
            $player->numericId = $existing['numeric_id'] ?? time();
        }
        $data = [
            'numeric_id'  => $player->numericId,
            'player_id'   => $player->id,
            'world_id'    => $worldId,
            'name'        => $player->name,
            'password'    => !empty($player->password) ? $player->password : ($existing['password'] ?? ''),
            'is_bot'      => !empty($player->isBot),
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
     * Restore saved player profile (score, location, properties, inventory) from DB/file
     * into an in-memory Player object.
     */
    public function loadPlayerProfile(World $world, Player $player): bool
    {
        $data = null;

        if ($this->isConnected()) {
            try {
                if ($player->numericId !== null && (int)$player->numericId > 0) {
                    $stmt = $this->pdo->prepare("SELECT * FROM dimx_players WHERE id = :id LIMIT 1");
                    $stmt->execute([':id' => (int)$player->numericId]);
                    $row = $stmt->fetch();
                } else {
                    $stmt = $this->pdo->prepare("SELECT * FROM dimx_players WHERE world_id = :wid AND LOWER(name) = :name LIMIT 1");
                    $stmt->execute([':wid' => $world->id, ':name' => strtolower($player->name)]);
                    $row = $stmt->fetch();
                }
                if ($row) {
                    $data = [
                        'numeric_id'   => (int)$row['id'],
                        'is_bot'       => !empty($row['is_bot']),
                        'name'         => $row['name'],
                        'score'        => (int)$row['score'],
                        'container_id' => $row['container_id'],
                        'properties'   => json_decode($row['properties_json'] ?: '{}', true) ?: [],
                        'items'        => json_decode($row['items_json']       ?: '[]', true) ?: [],
                        'console'      => json_decode($row['console_json']     ?: '[]', true) ?: [],
                    ];
                }
            } catch (PDOException $e) {
                error_log("PersistenceManager::loadPlayerProfile DB error: " . $e->getMessage());
            }
        }

        if ($data === null) {
            $file = "{$this->storageDir}/player_{$world->id}_{$player->name}.json";
            if (!file_exists($file)) {
                $file = "{$this->storageDir}/player_{$world->id}_{$player->id}.json";
            }
            if (file_exists($file)) {
                $data = json_decode((string)file_get_contents($file), true);
            }
        }

        if ($data === null) {
            return false;
        }

        if ($player->numericId === null && isset($data['numeric_id'])) {
            $player->numericId = (int)$data['numeric_id'];
        }
        if (!empty($data['is_bot'])) {
            $player->isBot = true;
        }
        $player->score = $data['score'] ?? 0;
        if (!empty($data['properties'])) {
            $player->setAttrList($data['properties']);
        }

        $targetRoom = !empty($data['container_id']) ? $world->getRoom($data['container_id']) : null;
        if ($targetRoom instanceof Room) {
            $player->move($targetRoom);
        }

        // Restore pending console lines
        foreach ($data['console'] ?? [] as $line) {
            $player->display($line);
        }

        // Auto-restore items
        foreach ($data['items'] ?? [] as $itemData) {
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

        return true;
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
                        'numeric_id'   => isset($row['id']) ? (int)$row['id'] : null,
                        'is_bot'       => !empty($row['is_bot']),
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
        if (!empty($data['password']) && !empty($password)) {
            $storedHash = (string)$data['password'];
            if (!password_verify($password, $storedHash) && $storedHash !== $password) {
                return null;
            }
        }

        $player = $world->getPlayer($playerId) ?? new Player($world, $data['name'], $playerId);
        $player->numericId = $data['numeric_id'] ?? null;
        $player->isBot     = !empty($data['is_bot']);
        $player->password  = $data['password'] ?? '';
        $player->score     = $data['score'] ?? 0;
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

    /**
     * Authenticate player credentials or register a new player account if nonexistent.
     * Passwords are encrypted using bcrypt (password_hash).
     *
     * @param string $worldId
     * @param string $playerName
     * @param string $password
     * @param bool $isBot
     * @return array{
     *   success: bool,
     *   created?: bool,
     *   numeric_id?: int,
     *   player_id?: string,
     *   name?: string,
     *   is_bot?: bool,
     *   error?: string,
     *   code?: string
     * }
     */
    public function authenticateOrCreatePlayer(
        string $worldId,
        string $playerName,
        string $password,
        bool $isBot = false
    ): array {
        $playerName = trim($playerName);
        if ($playerName === '') {
            return ['success' => false, 'error' => 'Player nickname cannot be empty.', 'code' => 'empty_username'];
        }

        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT id, player_id, name, password, is_bot
                    FROM dimx_players
                    WHERE world_id = :wid AND (player_id = :pid OR LOWER(name) = :pname)
                    LIMIT 1
                ");
                $stmt->execute([
                    ':wid'   => $worldId,
                    ':pid'   => $playerName,
                    ':pname' => strtolower($playerName),
                ]);
                $row = $stmt->fetch();

                if ($row) {
                    $hash = (string)$row['password'];
                    $valid = false;
                    if ($hash === '' && $password !== '') {
                        // Unset password account being initialized
                        $valid = true;
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $upStmt = $this->pdo->prepare("UPDATE dimx_players SET password = :p WHERE id = :id");
                        $upStmt->execute([':p' => $newHash, ':id' => $row['id']]);
                    } elseif ($hash === '' && $password === '') {
                        $valid = true;
                    } elseif ($hash !== '' && password_verify($password, $hash)) {
                        $valid = true;
                    } elseif ($hash === $password) {
                        // Legacy unhashed entry — upgrade to password_hash
                        $valid = true;
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $upStmt = $this->pdo->prepare("UPDATE dimx_players SET password = :p WHERE id = :id");
                        $upStmt->execute([':p' => $newHash, ':id' => $row['id']]);
                    }

                    if (!$valid) {
                        return [
                            'success' => false,
                            'error'   => 'Incorrect password for player "' . $playerName . '".',
                            'code'    => 'invalid_password',
                        ];
                    }

                    return [
                        'success'    => true,
                        'created'    => false,
                        'numeric_id' => (int)$row['id'],
                        'player_id'  => (string)$row['player_id'],
                        'name'       => (string)$row['name'],
                        'is_bot'     => !empty($row['is_bot']),
                    ];
                }

                // Account does not exist — create account automatically
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $this->pdo->prepare("
                    INSERT INTO dimx_players
                        (player_id, world_id, name, password, is_bot, score, container_id, properties_json, items_json, console_json)
                    VALUES
                        (:pid, :wid, :name, :pass, :bot, 0, '', '{}', '[]', '[]')
                ");
                $ins->execute([
                    ':pid'  => $playerName,
                    ':wid'  => $worldId,
                    ':name' => $playerName,
                    ':pass' => $hash,
                    ':bot'  => $isBot ? 1 : 0,
                ]);
                $numericId = (int)$this->pdo->lastInsertId();

                return [
                    'success'    => true,
                    'created'    => true,
                    'numeric_id' => $numericId,
                    'player_id'  => $playerName,
                    'name'       => $playerName,
                    'is_bot'     => $isBot,
                ];
            } catch (PDOException $e) {
                error_log("PersistenceManager::authenticateOrCreatePlayer DB error: " . $e->getMessage());
                return ['success' => false, 'error' => 'Database error: ' . $e->getMessage(), 'code' => 'db_error'];
            }
        }

        // File-based fallback
        $file = "{$this->storageDir}/player_{$worldId}_{$playerName}.json";
        if (file_exists($file)) {
            $data = json_decode((string)file_get_contents($file), true) ?: [];
            $hash = (string)($data['password'] ?? '');
            $valid = false;
            if ($hash === '' && $password !== '') {
                $valid = true;
                $data['password'] = password_hash($password, PASSWORD_DEFAULT);
                file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
            } elseif ($hash === '' && $password === '') {
                $valid = true;
            } elseif ($hash !== '' && (password_verify($password, $hash) || $hash === $password)) {
                $valid = true;
            }
            if (!$valid) {
                return [
                    'success' => false,
                    'error'   => 'Incorrect password for player "' . $playerName . '".',
                    'code'    => 'invalid_password',
                ];
            }
            return [
                'success'    => true,
                'created'    => false,
                'numeric_id' => (int)($data['numeric_id'] ?? 1),
                'player_id'  => $playerName,
                'name'       => (string)($data['name'] ?? $playerName),
                'is_bot'     => !empty($data['is_bot']),
            ];
        }

        // Create new account in file fallback
        $hash   = password_hash($password, PASSWORD_DEFAULT);
        $idFile = "{$this->storageDir}/last_player_id.txt";
        $nextId = file_exists($idFile) ? ((int)file_get_contents($idFile) + 1) : 1;
        file_put_contents($idFile, (string)$nextId);

        $data = [
            'numeric_id'  => $nextId,
            'player_id'   => $playerName,
            'world_id'    => $worldId,
            'name'        => $playerName,
            'password'    => $hash,
            'is_bot'      => $isBot,
            'score'       => 0,
            'container_id'=> '',
            'properties'  => [],
            'items'       => [],
            'console'     => [],
            'created_at'  => date('Y-m-d H:i:s'),
        ];
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

        return [
            'success'    => true,
            'created'    => true,
            'numeric_id' => $nextId,
            'player_id'  => $playerName,
            'name'       => $playerName,
            'is_bot'     => $isBot,
        ];
    }

    /**
     * Retrieve basic player account details including numeric ID and bot status.
     *
     * @return array{id: int, player_id: string, name: string, is_bot: bool}|null
     */
    public function getPlayerAccount(string $worldId, string $playerName): ?array
    {
        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT id, player_id, name, is_bot
                    FROM dimx_players
                    WHERE world_id = :wid AND (player_id = :pid OR LOWER(name) = :pname)
                    LIMIT 1
                ");
                $stmt->execute([
                    ':wid'   => $worldId,
                    ':pid'   => $playerName,
                    ':pname' => strtolower($playerName),
                ]);
                $row = $stmt->fetch();
                if ($row) {
                    return [
                        'id'        => (int)$row['id'],
                        'player_id' => (string)$row['player_id'],
                        'name'      => (string)$row['name'],
                        'is_bot'    => !empty($row['is_bot']),
                    ];
                }
            } catch (PDOException $e) {
                error_log("PersistenceManager::getPlayerAccount error: " . $e->getMessage());
            }
        }

        $file = "{$this->storageDir}/player_{$worldId}_{$playerName}.json";
        if (file_exists($file)) {
            $data = json_decode((string)file_get_contents($file), true) ?: [];
            return [
                'id'        => (int)($data['numeric_id'] ?? 1),
                'player_id' => (string)($data['player_id'] ?? $playerName),
                'name'      => (string)($data['name'] ?? $playerName),
                'is_bot'    => !empty($data['is_bot']),
            ];
        }

        return null;
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
     * If $recipientName is provided, the message is privately directed to that player/character.
     */
    public function postMessage(string $worldId, string $senderName, string $text, ?string $recipientName = null): bool
    {
        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO dimx_messages (world_id, sender_name, recipient_name, message_text)
                    VALUES (:wid, :sender, :recip, :text)
                ");
                return $stmt->execute([
                    ':wid'    => $worldId,
                    ':sender' => $senderName,
                    ':recip'  => $recipientName,
                    ':text'   => $text,
                ]);
            } catch (PDOException $e) {
                error_log("PersistenceManager::postMessage error: " . $e->getMessage());
            }
        }

        // File-based fallback (append to JSONL)
        $file = "{$this->storageDir}/chat_{$worldId}.jsonl";
        $line = json_encode([
            'id'        => time(),
            'sender'    => $senderName,
            'recipient' => $recipientName,
            'text'      => $text,
            'time'      => date('Y-m-d H:i:s'),
        ]) . "\n";
        return file_put_contents($file, $line, FILE_APPEND | LOCK_EX) !== false;
    }

    /**
     * Get the highest current message ID in the dimx_messages table for the world.
     */
    public function getLatestMessageId(string $worldId): int
    {
        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("SELECT MAX(message_id) AS max_id FROM dimx_messages WHERE world_id = :wid");
                $stmt->execute([':wid' => $worldId]);
                $val = $stmt->fetchColumn();
                return $val !== false ? (int)$val : 0;
            } catch (PDOException $e) {
                error_log("PersistenceManager::getLatestMessageId error: " . $e->getMessage());
            }
        }
        return 0;
    }

    /**
     * Fetch chat messages newer than $afterId (message_id, not timestamp).
     * If $playerName is provided, includes broadcast messages plus private messages to/from this player.
     * Returns array of ['id', 'sender', 'text', 'time', 'recipient'] sorted ascending.
     *
     * @param string      $worldId
     * @param int         $afterId     Last known message_id (0 = return last $limit messages)
     * @param int         $limit       Max rows to return
     * @param string|null $playerName  Optional player name to filter private recipient messages
     */
    public function getMessages(string $worldId, int $afterId = 0, int $limit = 50, ?string $playerName = null): array
    {
        if ($this->isConnected()) {
            try {
                $recipSql = "";
                $params   = [':wid' => $worldId];
                $limInt   = max(1, (int)$limit);

                if ($playerName !== null && $playerName !== '') {
                    $recipSql = " AND (recipient_name IS NULL OR recipient_name = '' OR recipient_name = :pname1 OR sender_name = :pname2)";
                    $params[':pname1'] = $playerName;
                    $params[':pname2'] = $playerName;
                }

                if ($afterId > 0) {
                    $params[':after'] = (int)$afterId;
                    $stmt = $this->pdo->prepare("
                        SELECT message_id AS id, sender_name AS sender, recipient_name AS recipient,
                               message_text AS text, DATE_FORMAT(created_at, '%H:%i:%s') AS time
                        FROM dimx_messages
                        WHERE world_id = :wid AND message_id > :after {$recipSql}
                        ORDER BY message_id ASC
                        LIMIT {$limInt}
                    ");
                    $stmt->execute($params);
                } else {
                    // Initial load: return last $limit messages
                    $stmt = $this->pdo->prepare("
                        SELECT message_id AS id, sender_name AS sender, recipient_name AS recipient,
                               message_text AS text, DATE_FORMAT(created_at, '%H:%i:%s') AS time
                        FROM (
                            SELECT message_id, sender_name, recipient_name, message_text, created_at
                            FROM dimx_messages
                            WHERE world_id = :wid {$recipSql}
                            ORDER BY message_id DESC
                            LIMIT {$limInt}
                        ) sub
                        ORDER BY message_id ASC
                    ");
                    $stmt->execute($params);
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
        foreach ($lines as $line) {
            $msg = json_decode($line, true);
            if ($msg && $msg['id'] > $afterId) {
                if ($playerName !== null && $playerName !== '') {
                    $recip = $msg['recipient'] ?? null;
                    if ($recip !== null && $recip !== '' && $recip !== $playerName && ($msg['sender'] ?? '') !== $playerName) {
                        continue;
                    }
                }
                $result[] = [
                    'id'        => $msg['id'],
                    'sender'    => $msg['sender'],
                    'recipient' => $msg['recipient'] ?? null,
                    'text'      => $msg['text'],
                    'time'      => $msg['time'] ?? '',
                ];
            }
        }
        return array_slice($result, -$limit);
    }

    /**
     * Check if a specific client command is already queued for a player.
     */
    public function hasCommand(string $worldId, string $playerName, string $command): bool
    {
        $command = trim($command);
        if ($command === '') {
            return false;
        }

        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT 1 FROM dimx_commands
                    WHERE world_id = :wid AND player_name = :pname AND command_text = :cmd
                    LIMIT 1
                ");
                $stmt->execute([
                    ':wid'   => $worldId,
                    ':pname' => $playerName,
                    ':cmd'   => $command,
                ]);
                return (bool)$stmt->fetchColumn();
            } catch (\PDOException $e) {
                error_log("PersistenceManager::hasCommand error: " . $e->getMessage());
            }
        }

        // File-based fallback
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $playerName);
        $file = "{$this->storageDir}/cmd_{$worldId}_{$safeName}.txt";
        if (file_exists($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    if (trim($line) === $command) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Clear a specific client command (or variant) for a player.
     */
    public function clearCommand(string $worldId, string $playerName, string $command): bool
    {
        $command = trim($command);
        if ($command === '') {
            return false;
        }

        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("
                    DELETE FROM dimx_commands
                    WHERE world_id = :wid AND player_name = :pname AND LOWER(command_text) = LOWER(:cmd)
                ");
                return $stmt->execute([
                    ':wid'   => $worldId,
                    ':pname' => $playerName,
                    ':cmd'   => $command,
                ]);
            } catch (\PDOException $e) {
                error_log("PersistenceManager::clearCommand error: " . $e->getMessage());
            }
        }

        // File-based fallback
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $playerName);
        $file = "{$this->storageDir}/cmd_{$worldId}_{$safeName}.txt";
        if (file_exists($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                $filtered = array_filter($lines, function ($line) use ($command) {
                    return strcasecmp(trim($line), $command) !== 0;
                });
                if (empty($filtered)) {
                    @unlink($file);
                } else {
                    file_put_contents($file, implode("\n", $filtered) . "\n", LOCK_EX);
                }
            }
        }
        return true;
    }

    /**
     * Queue a client command for a player (e.g. "refresh!scene", "refresh!ctrls").
     */
    public function queueCommand(string $worldId, string $playerName, string $command): bool
    {
        $command = trim($command);
        if ($command === '') {
            return false;
        }

        if ($this->hasCommand($worldId, $playerName, $command)) {
            return true; // Already queued, avoid duplicates
        }

        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO dimx_commands (world_id, player_name, command_text)
                    VALUES (:wid, :pname, :cmd)
                ");
                return $stmt->execute([
                    ':wid'   => $worldId,
                    ':pname' => $playerName,
                    ':cmd'   => $command,
                ]);
            } catch (\PDOException $e) {
                error_log("PersistenceManager::queueCommand error: " . $e->getMessage());
            }
        }

        // File-based fallback
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $playerName);
        $file = "{$this->storageDir}/cmd_{$worldId}_{$safeName}.txt";
        return file_put_contents($file, $command . "\n", FILE_APPEND | LOCK_EX) !== false;
    }

    /**
     * Read and clear all pending client commands for a player.
     * Formats commands separated by semicolons for clientExecute, e.g. "refresh!scene;".
     */
    public function drainCommands(string $worldId, string $playerName): string
    {
        $commands = [];

        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT command_id, command_text
                    FROM dimx_commands
                    WHERE world_id = :wid AND player_name = :pname
                    ORDER BY command_id ASC
                ");
                $stmt->execute([':wid' => $worldId, ':pname' => $playerName]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    $ids = array_column($rows, 'command_id');
                    $delStmt = $this->pdo->prepare("
                        DELETE FROM dimx_commands
                        WHERE command_id IN (" . implode(',', array_map('intval', $ids)) . ")
                    ");
                    $delStmt->execute();
                    foreach ($rows as $r) {
                        $c = trim((string)($r['command_text'] ?? ''));
                        if ($c !== '' && !in_array($c, $commands, true)) {
                            $commands[] = $c;
                        }
                    }
                }
            } catch (\PDOException $e) {
                error_log("PersistenceManager::drainCommands error: " . $e->getMessage());
            }
        }

        // File-based fallback
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $playerName);
        $file = "{$this->storageDir}/cmd_{$worldId}_{$safeName}.txt";
        if (file_exists($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            @unlink($file);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $c = trim($line);
                    if ($c !== '' && !in_array($c, $commands, true)) {
                        $commands[] = $c;
                    }
                }
            }
        }

        if (empty($commands)) {
            return '';
        }
        return implode(';', $commands) . ';';
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

    // =========================================================================
    // Goal events (Hall of Fame)
    // =========================================================================

    /**
     * Record a goal event achieved by a player.
     */
    public function recordGoal(
        string $worldId,
        string $playerName,
        int $score = 0,
        ?string $worldVersion = null,
        ?string $message = null
    ): bool {
        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO dimx_goals (world_id, player_name, score, world_version, message, created_at)
                    VALUES (:wid, :player, :score, :ver, :msg, NOW())
                ");
                return $stmt->execute([
                    ':wid'    => $worldId,
                    ':player' => $playerName,
                    ':score'  => $score,
                    ':ver'    => $worldVersion,
                    ':msg'    => $message,
                ]);
            } catch (PDOException $e) {
                error_log("PersistenceManager::recordGoal error: " . $e->getMessage());
            }
        }

        // File-based fallback (append to JSONL)
        $file = "{$this->storageDir}/goals_{$worldId}.jsonl";
        $line = json_encode([
            'world_id'      => $worldId,
            'player_name'   => $playerName,
            'score'         => $score,
            'world_version' => $worldVersion,
            'message'       => $message,
            'created_at'    => date('Y-m-d H:i:s'),
        ]) . "\n";
        return file_put_contents($file, $line, FILE_APPEND | LOCK_EX) !== false;
    }

    /**
     * Retrieve goal events for a given world, most recent first.
     *
     * @return array<array{goal_id?: int, world_id: string, player_name: string, score: int, world_version: ?string, message: ?string, created_at: string}>
     */
    public function getGoals(string $worldId, int $limit = 50): array
    {
        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT * FROM dimx_goals
                    WHERE world_id = :wid
                    ORDER BY goal_id DESC
                    LIMIT :lim
                ");
                $stmt->bindValue(':wid', $worldId);
                $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (PDOException $e) {
                error_log("PersistenceManager::getGoals error: " . $e->getMessage());
            }
        }

        $file = "{$this->storageDir}/goals_{$worldId}.jsonl";
        if (!file_exists($file)) {
            return [];
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $goals = [];
        foreach (array_reverse($lines) as $l) {
            $data = json_decode($l, true);
            if (is_array($data)) {
                $goals[] = $data;
                if (count($goals) >= $limit) {
                    break;
                }
            }
        }
        return $goals;
    }

    /**
     * Clear goal records for a given world (useful in tests).
     */
    public function clearGoals(string $worldId): bool
    {
        if ($this->isConnected()) {
            try {
                $stmt = $this->pdo->prepare("DELETE FROM dimx_goals WHERE world_id = :wid");
                $stmt->execute([':wid' => $worldId]);
            } catch (PDOException $e) {
                error_log("PersistenceManager::clearGoals error: " . $e->getMessage());
            }
        }
        $file = "{$this->storageDir}/goals_{$worldId}.jsonl";
        if (file_exists($file)) {
            @unlink($file);
        }
        return true;
    }
}
