<?php
declare(strict_types=1);

namespace Dimx;

/**
 * GameEngine
 *
 * Core engine coordinator for world execution, concurrency locking, entity mutations,
 * command routing, and event dispatch.
 * Decouples the game processing logic from the presentation layer (UIhtml / UIjson).
 */
class GameEngine
{
    public const ENGINE_VERSION = '1.0.0 (PHP) / DimensioneX 7.1.8-compat';

    public PersistenceManager $pm;
    public string $worldId;
    public ?string $worldFile;
    public string $baseUrl;
    public ?\PDO $pdo = null;
    public ?WorldLock $lock = null;
    public array $config = [];
    public string $serverType = 'public';
    public string $debugTo = 'none';

    /** Optional custom system folder override. Defaults to dirname(__DIR__) (where config.php resides). */
    public static ?string $systemFolder = null;

    /** Active engine instance for fallback context */
    public static ?GameEngine $activeInstance = null;

    /**
     * Get the game engine system folder path (where config.php and debug.log reside).
     */
    public static function getSystemFolder(): string
    {
        return self::$systemFolder ?? dirname(__DIR__);
    }

    /** Current debug log indentation level (0 = root level, incremented per nested call) */
    public int $indentLevel = 0;

    /**
     * Increment debug log indentation by 1 level.
     */
    public function incIndent(): void
    {
        $this->indentLevel++;
    }

    /**
     * Decrement debug log indentation by 1 level (lower bound is 0).
     */
    public function decIndent(): void
    {
        if ($this->indentLevel > 0) {
            $this->indentLevel--;
        }
    }

    /**
     * Log a message to debug.log in the game engine's system folder if debugTo is 'file'.
     *
     * @param mixed $message String expression or message to be logged.
     */
    public function log(mixed $message): void
    {
        if (strtolower((string)$this->debugTo) !== 'file') {
            return;
        }

        $logFile = self::getSystemFolder() . '/debug.log';
        if (is_bool($message)) {
            $entry = $message ? '1' : '0';
        } else {
            $entry = (string)$message;
        }

        $indent = ($this->indentLevel > 0) ? str_repeat("\t", $this->indentLevel) : '';
        if ($indent !== '' && str_contains($entry, "\n")) {
            $lines = explode("\n", $entry);
            $indentedLines = array_map(fn($l) => ($l !== '') ? $indent . $l : $l, $lines);
            $entry = implode("\n", $indentedLines);
        } else {
            $entry = $indent . $entry;
        }

        @file_put_contents($logFile, $entry . "\n", FILE_APPEND | LOCK_EX);
    }

    /** Inactivity timeout for automatically disconnecting idle players (seconds) */
    public int $playerTimeout = 30; // 30 seconds

    public function __construct(
        PersistenceManager $pm,
        string $worldId = 'default',
        ?string $worldFile = null,
        string $baseUrl = '',
        array $config = []
    ) {
        $this->pm        = $pm;
        $this->worldId   = $worldId;
        $this->worldFile = $worldFile;
        $this->baseUrl   = $baseUrl;
        $this->config    = $config;
        self::$activeInstance = $this;

        if (empty($this->config) && file_exists(__DIR__ . '/../config.php')) {
            $loadedConfig = @require __DIR__ . '/../config.php';
            if (is_array($loadedConfig)) {
                $this->config = $loadedConfig;
            }
        }

        if (isset($this->config['player_timeout']) && is_numeric($this->config['player_timeout'])) {
            $this->playerTimeout = max(1, (int)$this->config['player_timeout']);
        } elseif (isset($this->config['playerTimeout']) && is_numeric($this->config['playerTimeout'])) {
            $this->playerTimeout = max(1, (int)$this->config['playerTimeout']);
        }

        if (isset($this->config['serverType']) && is_string($this->config['serverType'])) {
            $st = strtolower(trim($this->config['serverType']));
            if ($st === 'public' || $st === 'local') {
                $this->serverType = $st;
            }
        }

        if (isset($this->config['debugTo']) && is_string($this->config['debugTo'])) {
            $dt = strtolower(trim($this->config['debugTo']));
            if ($dt === 'none' || $dt === 'file') {
                $this->debugTo = $dt;
            }
        }

        $this->pdo  = $pm->getPdo();
        $this->lock = ($this->pdo !== null) ? new WorldLock($this->pdo) : null;

        if ($this->lock !== null) {
            register_shutdown_function(fn() => $this->lock->releaseAll());
        }
    }

    /**
     * Load an existing world from storage or seed it from .dxw / demo fallback.
     */
    public function loadOrSeedWorld(bool $pruneStale = true): World
    {
        // 1. Try loading from persistence
        $world = $this->pm->loadWorld($this->worldId);
        if ($world !== null) {
            $world->persistence = $this->pm;
            $world->engine      = $this;
            unset($world->imagesFolderPublic, $world->imagesFolderLocal);
            if (isset($this->config['tickLength']) && is_numeric($this->config['tickLength'])) {
                $world->tickLength = max(1, (int)$this->config['tickLength']);
            }
            $world->playerTimeout = $this->playerTimeout;
            if ($pruneStale) {
                $this->pruneStalePlayers($world);
            }
            return $world;
        }

        // 2. Not found — seed from .dxw file if specified
        if (!empty($this->worldFile)) {
            $resolvedFile = $this->worldFile;
            $isUrl = str_starts_with($resolvedFile, 'http://') || str_starts_with($resolvedFile, 'https://');
            if (!$isUrl && !file_exists($resolvedFile)) {
                $base = basename($resolvedFile);
                $fallbacks = [
                    dirname(__DIR__) . '/' . $base,
                    dirname(__DIR__, 2) . '/docs/dxw-examples/' . $base,
                    dirname(__DIR__, 2) . '/' . $base,
                ];
                foreach ($fallbacks as $fb) {
                    if (file_exists($fb)) {
                        $resolvedFile = $fb;
                        break;
                    }
                }
            }
            $loader = new DxwLoader($this->baseUrl, $this->serverType);
            try {
                $world = $loader->load($resolvedFile);
            } catch (\Throwable $e) {
                error_log("Failed loading world '{$resolvedFile}': " . $e->getMessage());
            }
        }

        // 3. Fallback demo world
        if ($world === null) {
            $world = new World($this->worldId, 'DimensioneX Demo World');
            $beach = new Room($world, 'Sunny Beach', 'beach', 'A warm sandy beach with gentle waves.');
            $bar   = new Room($world, 'Tiki Bar', 'bar', 'A lively bamboo bar with tropical refreshments.');
            $world->addRoom($beach);
            $world->addRoom($bar);

            new Link($world, 'Path to Bar', 'path_bar', $beach, $bar, true, 'E');
            $apple = new Item($world, 'Fresh Apple', 'apple', 'A crisp red apple.', null, 'pickable=1,Value=2');
            $apple->type = 'food.apple';
            $apple->move($beach);
            $world->addItem($apple);
        }

        $world->id          = $this->worldId;
        $world->persistence = $this->pm;
        $world->engine      = $this;

        if (isset($this->config['tickLength']) && is_numeric($this->config['tickLength'])) {
            $world->tickLength = max(1, (int)$this->config['tickLength']);
        }

        // Trigger onStart event at world initialization (as in DimensioneX World.start())
        $world->fireEvent('onStart', $world, null, null);

        // Persist seed so future requests read from persistence
        $this->pm->saveWorld($world, $this->worldFile);

        return $world;
    }

    /**
     * Reset the game world state, re-seeding it from definition and invalidating player sessions.
     */
    public function resetWorld(bool $purgePlayers = false): World
    {
        $this->log("performing RESTART upon world reset flag");
        $this->pm->resetWorld($this->worldId, $purgePlayers);
        return $this->loadOrSeedWorld();
    }

    /**
     * Remove players that have exceeded the inactivity timeout.
     */
    protected function pruneStalePlayers(World $world): void
    {
        $now = time();
        $playersToCheck = array_values($world->players);
        foreach ($playersToCheck as $p) {
            if (!$p instanceof Player) {
                continue;
            }
            $last = (int)($p->lastInteraction ?: $p->lastActive ?: $p->getProperty('lastInteraction') ?: $p->getProperty('lastActive') ?: 0);
            if ($last > 0 && ($now - $last) >= $this->playerTimeout) {
                $dateStr = date('Y-m-d H:i:s');
                $this->log("Pruning stale player {$p->name} ({$p->id}) - idle for " . ($now - $last) . "s");
                $this->log("{$dateStr} USER: {$p->name} ({$p->id}) | IDLE TIMEOUT (" . ($now - $last) . "s >= {$this->playerTimeout}s) | STATUS: DISCONNECTED");
                $p->die();
                $world->removePlayer($p);
            }
        }
    }

    /**
     * Advance the world clock tick and prune stale players inside concurrency lock.
     */
    public function executeTick(): void
    {
        if ($this->pdo !== null && $this->lock !== null) {
            $this->lock->executeAtomic($this->worldId, function (\PDO $pdo): array {
                $world = $this->loadOrSeedWorld(true);
                $world->tick();
                $this->pm->saveWorld($world);
                return [];
            });
        } else {
            $world = $this->loadOrSeedWorld(true);
            $world->tick();
            $this->pm->saveWorld($world);
        }
    }

    /**
     * Ensure the player exists in the world, setting start room and UI if new.
     * The in-memory player ID lives strictly in memory and changes every time the user logs in.
     */
    public function ensurePlayer(
        World $world,
        string $playerName,
        mixed $ui = null,
        ?int $numericId = null,
        bool $isBot = false,
        bool $isNewLogin = false,
        ?string $existingEngineId = null,
        ?string $skinId = null,
        ?bool $sounds = null,
        ?bool $music = null
    ): Player {
        $player = null;
        $chosenSkin = ($skinId !== null && trim($skinId) !== '') ? $world->getSkin($skinId) : null;

        if (!$isNewLogin) {
            // Ongoing session: find existing in-memory player
            if (!empty($existingEngineId)) {
                $player = $world->getPlayer($existingEngineId);
            }
            if ($player === null) {
                $player = $world->getPlayerByName($playerName);
            }
            if ($player === null && $numericId !== null && (int)$numericId > 0) {
                $candidate = $world->getPlayerByNumericId($numericId);
                if ($candidate !== null && strtolower(trim($candidate->name)) === strtolower(trim($playerName))) {
                    $player = $candidate;
                }
            }
        } else {
            // New login: remove any stale in-memory player instance from a previous session
            $stale = $world->getPlayerByName($playerName);
            if ($stale === null && $numericId !== null && (int)$numericId > 0) {
                $candidate = $world->getPlayerByNumericId($numericId);
                if ($candidate !== null && strtolower(trim($candidate->name)) === strtolower(trim($playerName))) {
                    $stale = $candidate;
                }
            }
            if ($stale !== null) {
                $world->removePlayer($stale);
            }
        }

        if ($player === null) {
            // Player ID in game engine lives in memory and changes every time the user logs in
            $engineId = 'p_' . ($numericId ?? '0') . '_' . bin2hex(random_bytes(4));
            $player   = new Player($world, $playerName, $engineId, 'Adventurer', null, $world->charactersDefaultAttrlist ?? '');
            $player->skin      = $chosenSkin ?? $world->defaultSkin ?? new Skin('default', 'Default');
            $player->numericId = $numericId;
            $player->isBot     = $isBot;
            if ($sounds !== null) {
                $player->setSounds($sounds);
                if ($ui instanceof UI) {
                    $ui->setSounds($sounds);
                }
            } elseif ($ui instanceof UI) {
                $player->setSounds($ui->hasSounds());
            }
            if ($music !== null) {
                $player->setMusic($music);
                if ($ui instanceof UI) {
                    $ui->setMusic($music);
                }
            } elseif ($ui instanceof UI) {
                $player->setMusic($ui->hasMusic());
            }
            if ($player->skin?->picPlayer) {
                $player->images['N'] = clone $player->skin->picPlayer;
            }
            if ($player->skin && !empty($player->skin->icons['ICOPLAYER'])) {
                $player->icon = $player->skin->icons['ICOPLAYER'];
            }
            $player->messagesQueue  = []; // Empty upon connection
            $player->spawnMessageId = !empty($this->pm) ? $this->pm->getLatestMessageId($world->id) : 0;

            if (!empty($world->charactersDefaultAttrlist)) {
                $player->setAttrList($world->charactersDefaultAttrlist);
            }

            // Restore saved profile (score, location, properties, inventory) from persistence
            $this->pm->loadPlayerProfile($world, $player);

            // Apply chosen skin (or saved skinId from profile if no explicit skin choice was supplied)
            if ($chosenSkin !== null) {
                $player->setSkin($chosenSkin);
            } else {
                $savedSkinId = $player->getProperty('skinId');
                $savedSkin = (is_string($savedSkinId) && $savedSkinId !== '') ? $world->getSkin($savedSkinId) : null;
                if ($savedSkin !== null) {
                    $player->setSkin($savedSkin);
                }
            }

            // Ensure avatar images remain properly configured after profile load
            if (empty($player->images['N']) && $player->skin?->picPlayer) {
                $player->images['N'] = clone $player->skin->picPlayer;
            }
            if (empty($player->icon) && !empty($player->skin?->icons['ICOPLAYER'])) {
                $player->icon = $player->skin->icons['ICOPLAYER'];
            }

            // If player doesn't have a room yet, find starting room
            if ($player->getRoom() === null) {
                $startRoom = $world->startRoom ?? null;
                if ($startRoom === null && !empty($world->defaultRoom)) {
                    $startRoom = $world->getRoom($world->defaultRoom);
                }
                if ($startRoom === null) {
                    foreach ($world->rooms as $r) {
                        if ($r->getProperty('DEFAULT')) {
                            $startRoom = $r;
                            break;
                        }
                    }
                }
                if ($startRoom === null) {
                    $startRoom = reset($world->rooms) ?: null;
                }
                if ($startRoom !== null) {
                    $player->move($startRoom);
                }
            }

            $world->addPlayer($player);

            // Re-point any player references in world sets (e.g. players array) to active player instance
            foreach ($world->sets as $sName => $sArr) {
                if (is_array($sArr)) {
                    $changed = false;
                    foreach ($sArr as $sKey => $sVal) {
                        if ($sVal instanceof Player) {
                            if (($player->numericId !== null && $sVal->numericId === $player->numericId) ||
                                strcasecmp($sVal->name, $player->name) === 0) {
                                $sArr[$sKey] = $player;
                                $changed = true;
                            }
                        }
                    }
                    if ($changed) {
                        $world->sets[$sName] = $sArr;
                        $world->setProperty($sName, $sArr);
                    }
                }
            }

            $player->display("Welcome to <b>{$world->name}</b>, {$player->name}!");
            $world->fireEvent('onNew', $world, $player, null);
        } else {
            if ($player->numericId === null && $numericId !== null) {
                $player->numericId = $numericId;
            }
            if ($isBot) {
                $player->isBot = true;
            }
            if ($sounds !== null) {
                $player->setSounds($sounds);
                if ($ui instanceof UI) {
                    $ui->setSounds($sounds);
                }
            } elseif ($player->ui instanceof UI) {
                if ($ui instanceof UI) {
                    $ui->setSounds($player->ui->hasSounds());
                }
                $player->setSounds($player->ui->hasSounds());
            } elseif ($ui instanceof UI) {
                $ui->setSounds($player->sounds || $player->audioSupport);
            }

            if ($music !== null) {
                $player->setMusic($music);
                if ($ui instanceof UI) {
                    $ui->setMusic($music);
                }
            } elseif ($player->ui instanceof UI) {
                if ($ui instanceof UI) {
                    $ui->setMusic($player->ui->hasMusic());
                }
                $player->setMusic($player->ui->hasMusic());
            } elseif ($ui instanceof UI) {
                $ui->setMusic($player->music || $player->musicSupport);
            }
            if ($chosenSkin !== null) {
                $player->setSkin($chosenSkin);
            } elseif ($player->skin === null) {
                $player->skin = $world->defaultSkin ?? new Skin('default', 'Default');
            }
            if (empty($player->images['N']) && $player->skin?->picPlayer) {
                $player->images['N'] = clone $player->skin->picPlayer;
            }
            if (empty($player->icon) && !empty($player->skin?->icons['ICOPLAYER'])) {
                $player->icon = $player->skin->icons['ICOPLAYER'];
            }
        }

        // If numeric ID still unknown, query account table
        if ($player->numericId === null) {
            $acc = $this->pm->getPlayerAccount($this->worldId, $playerName);
            if ($acc !== null) {
                $player->numericId = $acc['id'];
                if (!empty($acc['is_bot'])) {
                    $player->isBot = true;
                }
            }
        }

        // Update player activity timestamp
        $player->recordInteraction();

        if ($ui !== null) {
            $player->setUI($ui);
        }

        return $player;
    }

    /**
     * Process an incoming player command inside the concurrency critical section.
     *
     * @param string $playerName Unique player name / ID
     * @param string $cmd Command verb (look, go, pick, drop, use, say, logout, etc.)
     * @param string $arg0 Primary argument (e.g. clicked object ID, speech text, direction)
     * @param string $arg1 Optional secondary argument (e.g. second object clicked, speech recipient)
     * @param mixed $ui Optional UI instance to attach to player
     * @param int|null $numericId Optional account numeric ID
     * @param bool $isBot Whether player is a bot
     * @param bool $isNewLogin Whether this request represents a new login connection
     * @param string|null $existingEngineId Known in-memory player ID from active session
     * @param string|null $skinId Optional chosen skin ID or name from login/session
     * @return array<string, mixed> Execution outcome context
     */
    public function processCommand(
        string $playerName,
        string $cmd = 'look',
        string $arg0 = '',
        string $arg1 = '',
        mixed $ui = null,
        ?int $numericId = null,
        bool $isBot = false,
        bool $isNewLogin = false,
        ?string $existingEngineId = null,
        ?string $skinId = null,
        ?bool $sounds = null,
        ?bool $music = null,
        string $view = 'scene',
        array $rawInput = []
    ): array {
        $cmd   = strtolower(trim((string)$cmd));
        $arg0  = trim($arg0);
        $arg1  = trim($arg1);
        $view  = trim((string)($view ?: 'scene'));

        if ($cmd === '' && strtolower($view) !== 'msgs') {
            $cmd = 'look';
        }

        // When just 1 parameter is passed, arg0 has to be the argument that gets filled
        if ($arg0 === '' && $arg1 !== '' && $cmd !== 'say') {
            $arg0 = $arg1;
            $arg1 = '';
        }

        $consoleMessages = [];
        $pendingSound    = null;
        $pendingBgMusic  = null;
        $pendingCommands = '';
        $hasNewMsgs      = false;
        $lockError       = false;
        $loggedOut       = in_array($cmd, ['logout'], true);
        $wasReset        = false;

        // Concurrency-controlled execution via WorldLock (MySQL GET_LOCK)
        if ($this->pdo !== null && $this->lock !== null) {
            $result = $this->lock->executeAtomic($this->worldId, function (\PDO $pdo) use (
                $playerName, $cmd, $arg0, $arg1, $ui, $loggedOut, $numericId, $isBot, $isNewLogin, $existingEngineId, $skinId, $sounds, $music, $view, $rawInput
            ): array {
                $world = $this->loadOrSeedWorld(true);

                if ($isNewLogin) {
                    $isNpcCollision = false;
                    foreach ($world->characters as $c) {
                        if (!$c instanceof Player) {
                            if (strcasecmp(trim($c->name), trim($playerName)) === 0 || strcasecmp(trim($c->id), trim($playerName)) === 0) {
                                $isNpcCollision = true;
                                break;
                            }
                        }
                    }

                    $activeInWorld = $world->getPlayerByName($playerName);
                    $isPlayerCollision = false;
                    if ($activeInWorld !== null) {
                        if ($isBot && (int)$activeInWorld->numericId === $numericId && $cmd !== 'login') {
                            $isPlayerCollision = false;
                        } else {
                            $isPlayerCollision = true;
                        }
                    }

                    if ($isNpcCollision || $isPlayerCollision) {
                        $room = $activeInWorld?->getRoom();
                        $roomStr = ($room !== null) ? ($room->name . ' (' . $room->id . ')') : '';
                        $commandStr = $cmd . '(' . $arg0 . ',' . $arg1 . ')';
                        $dateStr = date('Y-m-d H:i:s');
                        $reason = $isNpcCollision ? 'NPC name collision' : 'player name in use';
                        $this->log("{$dateStr} USER: {$playerName} | COMMAND: {$commandStr} | VIEW: {$view} | ROOM: {$roomStr} | STATUS: DISCONNECTED (REFUSAL: {$reason})");

                        $world->tick();
                        $this->pm->saveWorld($world);

                        return [
                            'world'       => $world,
                            'player'      => null,
                            'login_error' => 'Invalid nickname - choose another',
                        ];
                    }
                }

                $player = $this->ensurePlayer($world, $playerName, $ui, $numericId, $isBot, $isNewLogin, $existingEngineId, $skinId, $sounds, $music);

                $userStr    = $playerName . ' (' . $player->id . ')';
                $room       = $player->getRoom();
                $roomStr    = ($room !== null) ? ($room->name . ' (' . $room->id . ')') : '';
                $status     = ($player !== null && !$loggedOut) ? 'CONNECTED' : 'DISCONNECTED';
                $commandStr = $cmd . '(' . $arg0 . ',' . $arg1 . ')';
                $dateStr    = date('Y-m-d H:i:s');
                $this->log("{$dateStr} USER: {$userStr} | COMMAND: {$commandStr} | VIEW: {$view} | ROOM: {$roomStr} | STATUS: {$status}");

                $this->executeCommandAction($world, $player, $cmd, $arg0, $arg1, $view, $rawInput);

                // Advance world clock tick
                $world->tick();

                $wasReset = false;
                if (!empty($world->reset)) {
                    $world = $this->resetWorld();
                    $wasReset = true;
                    $loggedOut = true;
                }

                // Drain console output and pending sound/music/message flags for this player
                $msgs           = $player->drainConsole();
                $pendingSound   = $player->drainSound();
                $pendingBgMusic = $player->drainBackgroundSound();
                $hasNewMsgs     = $player->drainNewMessages();
                $rawMem  = $player->drainCommands();
                $rawPm   = $this->pm->drainCommands($this->worldId, $player->name);
                $rawAll  = array_filter(array_merge(explode(';', $rawMem), explode(';', $rawPm)));
                $cmdList = array_filter($rawAll, function ($c) use ($view) {
                    $t = strtolower(trim($c));
                    if ($t === '') {
                        return false;
                    }
                    if (($t === 'refresh!scene' || $t === 'refresh #scene') && strtolower(trim((string)$view)) === 'scene') {
                        return false;
                    }
                    return true;
                });
                $cmdsToDeliver  = !empty($cmdList) ? implode(';', array_unique($cmdList)) . ';' : '';

                // Save mutated world state back to persistence (only if NOT reset, since resetWorld already seeded fresh world)
                if (!$wasReset) {
                    $this->pm->saveWorld($world);
                }

                return [
                    'world'            => $world,
                    'player'           => $player,
                    'msgs'             => $msgs,
                    'sound'            => $pendingSound,
                    'background_music' => $pendingBgMusic,
                    'new_messages'     => $hasNewMsgs,
                    'commands'         => $cmdsToDeliver,
                    'logged_out'       => $loggedOut,
                    'reset'            => $wasReset,
                ];
            });

            if ($result === null) {
                $lockError = true;
                $consoleMessages = ["<span style='color:#f87171'>⚠ The game world is busy. Please try again in a moment.</span>"];
                $world  = $this->pm->loadWorld($this->worldId) ?? new World($this->worldId, 'DimensioneX');
                $world->persistence = $this->pm;
                $world->engine      = $this;
                $player = ($existingEngineId ? $world->getPlayer($existingEngineId) : null)
                    ?? $world->getPlayerByName($playerName)
                    ?? new Player($world, $playerName, $existingEngineId ?? ('p_' . ($numericId ?? '0') . '_' . bin2hex(random_bytes(4))));
                $player->numericId = $numericId;
                $player->isBot     = $isBot;
                if ($sounds !== null) {
                    $player->setSounds($sounds);
                }
                if ($music !== null) {
                    $player->setMusic($music);
                }
                if ($skinId !== null && $skinId !== '' && ($sk = $world->getSkin($skinId)) !== null) {
                    $player->setSkin($sk);
                }
            } else {
                if (!empty($result['login_error'])) {
                    $storageLabel = $this->pm->checkConnection()['type'];
                    return [
                        'world'            => $result['world'] ?? null,
                        'player'           => null,
                        'room'             => null,
                        'messages'         => [],
                        'chat'             => [],
                        'sound'            => null,
                        'background_music' => null,
                        'new_messages'     => false,
                        'commands'         => '',
                        'lockError'        => false,
                        'login_error'      => $result['login_error'],
                        'success'          => false,
                        'cmd'              => $cmd,
                        'arg0'             => $arg0,
                        'arg1'             => $arg1,
                        'param'            => $arg0,
                        'target'           => $arg1,
                        'storageLabel'     => $storageLabel,
                        'worldId'          => $this->worldId,
                        'status'           => 'login_required',
                        'error'            => $result['login_error'],
                    ];
                }
                $world           = $result['world'];
                $player          = $result['player'];
                $consoleMessages = $result['msgs'];
                $pendingSound    = $result['sound'] ?? null;
                $pendingBgMusic  = $result['background_music'] ?? null;
                $hasNewMsgs      = (bool)($result['new_messages'] ?? false);
                $pendingCommands = (string)($result['commands'] ?? '');
                $loggedOut       = $result['logged_out'] ?? $loggedOut;
                $wasReset        = (bool)($result['reset'] ?? false);
            }
        } else {
            // Graceful degradation when DB is offline (session / memory fallback)
            $world  = $this->loadOrSeedWorld(true);

            if ($isNewLogin) {
                $isNpcCollision = false;
                foreach ($world->characters as $c) {
                    if (!$c instanceof Player) {
                        if (strcasecmp(trim($c->name), trim($playerName)) === 0 || strcasecmp(trim($c->id), trim($playerName)) === 0) {
                            $isNpcCollision = true;
                            break;
                        }
                    }
                }

                $activeInWorld = $world->getPlayerByName($playerName);
                $isPlayerCollision = false;
                if ($activeInWorld !== null) {
                    if ($isBot && (int)$activeInWorld->numericId === $numericId && $cmd !== 'login') {
                        $isPlayerCollision = false;
                    } else {
                        $isPlayerCollision = true;
                    }
                }

                if ($isNpcCollision || $isPlayerCollision) {
                    $room = $activeInWorld?->getRoom();
                    $roomStr = ($room !== null) ? ($room->name . ' (' . $room->id . ')') : '';
                    $commandStr = $cmd . '(' . $arg0 . ',' . $arg1 . ')';
                    $dateStr = date('Y-m-d H:i:s');
                    $reason = $isNpcCollision ? 'NPC name collision' : 'player name in use';
                    $this->log("{$dateStr} USER: {$playerName} | COMMAND: {$commandStr} | VIEW: {$view} | ROOM: {$roomStr} | STATUS: DISCONNECTED (REFUSAL: {$reason})");

                    $world->tick();
                    $this->pm->saveWorld($world);

                    $storageLabel = $this->pm->checkConnection()['type'];
                    return [
                        'world'            => $world,
                        'player'           => null,
                        'room'             => null,
                        'messages'         => [],
                        'chat'             => [],
                        'sound'            => null,
                        'background_music' => null,
                        'new_messages'     => false,
                        'commands'         => '',
                        'lockError'        => false,
                        'login_error'      => 'Invalid nickname - choose another',
                        'success'          => false,
                        'cmd'              => $cmd,
                        'arg0'             => $arg0,
                        'arg1'             => $arg1,
                        'param'            => $arg0,
                        'target'           => $arg1,
                        'storageLabel'     => $storageLabel,
                        'worldId'          => $this->worldId,
                        'status'           => 'login_required',
                        'error'            => 'Invalid nickname - choose another',
                    ];
                }
            }

            $player = $this->ensurePlayer($world, $playerName, $ui, $numericId, $isBot, $isNewLogin, $existingEngineId, $skinId, $sounds, $music);

            $userStr    = $playerName . ' (' . $player->id . ')';
            $room       = $player->getRoom();
            $roomStr    = ($room !== null) ? ($room->name . ' (' . $room->id . ')') : '';
            $status     = ($player !== null && !$loggedOut) ? 'CONNECTED' : 'DISCONNECTED';
            $commandStr = $cmd . '(' . $arg0 . ',' . $arg1 . ')';
            $dateStr    = date('Y-m-d H:i:s');
            $this->log("{$dateStr} USER: {$userStr} | COMMAND: {$commandStr} | VIEW: {$view} | ROOM: {$roomStr} | STATUS: {$status}");

            $this->executeCommandAction($world, $player, $cmd, $arg0, $arg1, $view, $rawInput);

            $world->tick();

            $wasReset = false;
            if (!empty($world->reset)) {
                $world = $this->resetWorld();
                $wasReset = true;
                $loggedOut = true;
            }

            $consoleMessages = $player->drainConsole();
            $pendingSound    = $player->drainSound();
            $pendingBgMusic  = $player->drainBackgroundSound();
            $hasNewMsgs      = $player->drainNewMessages();
            $rawMem  = $player->drainCommands();
            $rawPm   = $this->pm->drainCommands($this->worldId, $player->name);
            $rawAll  = array_filter(array_merge(explode(';', $rawMem), explode(';', $rawPm)));
            $cmdList = array_filter($rawAll, function ($c) use ($view) {
                $t = strtolower(trim($c));
                if ($t === '') {
                    return false;
                }
                if (($t === 'refresh!scene' || $t === 'refresh #scene') && strtolower(trim((string)$view)) === 'scene') {
                    return false;
                }
                return true;
            });
            $pendingCommands = !empty($cmdList) ? implode(';', array_unique($cmdList)) . ';' : '';

            if (!$wasReset) {
                $this->pm->saveWorld($world);
            }
        }

        if ($player !== null && $ui !== null) {
            $player->setUI($ui);
        }

        $storageLabel = $this->pm->checkConnection()['type'];
        $msgRefreshRate = (isset($this->config['msgRefreshRate']) && is_numeric($this->config['msgRefreshRate']) && (int)$this->config['msgRefreshRate'] > 0)
            ? (int)$this->config['msgRefreshRate']
            : 5;

        // Messages box (Cell C) displays the player's 10 most recent incoming messages only (arrival order)
        $chatMessages = $player ? $player->getMessages() : [];

        return [
            'world'            => $world,
            'player'           => $player,
            'room'             => $player?->getRoom(),
            'messages'         => $consoleMessages,
            'chat'             => $chatMessages,
            'sound'            => $pendingSound,
            'background_music' => $pendingBgMusic,
            'new_messages'     => $hasNewMsgs,
            'commands'         => $pendingCommands,
            'lockError'        => $lockError,
            'cmd'              => $cmd,
            'arg0'             => $arg0,
            'arg1'             => $arg1,
            'param'            => $arg0,
            'target'           => $arg1,
            'storageLabel'     => $storageLabel,
            'worldId'          => $this->worldId,
            'msgRefreshRate'   => $msgRefreshRate,
            'logged_out'       => $loggedOut,
            'reset'            => $wasReset,
        ];
    }

    /**
     * Internal dispatcher for player commands mutating game objects.
     */
    public function executeCommandAction(
        World $world,
        Player $player,
        string $cmd,
        string $arg0,
        string $arg1,
        string $view = 'scene',
        array $rawInput = []
    ): void {
        $cmd  = trim($cmd);
        if ($cmd === '') {
            return;
        }
        $arg0 = trim($arg0);
        $arg1 = trim($arg1);

        // When just 1 parameter is passed, arg0 has to be the argument that gets filled
        if ($arg0 === '' && $arg1 !== '' && $cmd !== 'say') {
            $arg0 = $arg1;
            $arg1 = '';
        }

        $panel = $player->getPanel() ?? $world->defaultPanel;
        $isCoreCommand = in_array($cmd, [
            'look', 'click', 'go', 'pick', 'drop',
            'give', 'put', 'insert', 'use', 'use2', 'usewith', 'open', 'close', 'search', 'say', 'help', 'save', 'enter', 'exit', 
            'logout', 'rotl', 'rotr', 'rev'
        ], true);

        $btn = $panel?->getButton($cmd);
        $hasCustomEvent = ($btn !== null && !empty($btn->event)) || isset($world->events["cmd_{$cmd}"]);
        if ($panel !== null && ($isCoreCommand || $hasCustomEvent || $btn !== null) && !$panel->isCommandAllowed($cmd)) {
            $player->display("Command '{$cmd}' is not allowed in current panel ({$panel->id}).");
            return;
        }

        $currentRoom = $player->getRoom();
        $player->focus = null;

        switch ($cmd) {
            case 'look':
            case 'click':
                $lookTarget = !empty($arg0) ? $arg0 : $arg1;
                $inputData = [
                    'arg0'   => $arg0 !== '' ? $arg0 : $arg1,
                    'arg1'   => ($arg0 !== '' && $arg1 !== '') ? $arg1 : '',
                    'cmd'    => $cmd,
                    'txtBox' => $rawInput['txtBox'] ?? '',
                    'view'   => $view ?: 'scene',
                ];
                if (!empty($lookTarget)) {
                    $targetObj = $this->findAccessibleObject($player, $lookTarget);

                    if ($targetObj instanceof AdvObject) {
                        $player->focus = $targetObj;
                        $desc = $targetObj->evalDescription($player);
                        if ($targetObj->isaRoom()) {
                            if (!empty($desc)) {
                                $player->display($desc);
                            }
                        } else {
                            $msg = !empty($desc) ? $desc : "<b>{$targetObj->name}</b>";
                            $player->display($msg);
                        }

                        if ($targetObj->isanItem() && !$targetObj->isaRoom() && $targetObj->isOpen() && !$targetObj->isVehicle()) {
                            $contents = $targetObj->getContents(true);
                            if (!empty($contents)) {
                                $itemLinks = [];
                                $skin = $player->skin ?? $world->skins['default'] ?? $world->defaultSkin;
                                foreach ($contents as $it) {
                                    $ico = !empty($it->icon) ? $it->icon : ($skin?->getItemIcon() ?? '');
                                    $iconImg = !empty($ico)
                                        ? "<img src=\"" . htmlspecialchars($ico) . "\" alt=\"" . htmlspecialchars($it->name) . "\" style=\"width:20px;height:20px;object-fit:contain;vertical-align:middle;margin-right:4px;border-radius:2px\" />"
                                        : "📦 ";
                                    $itemLinks[] = "<a href=\"javascript:void(0)\" class=\"dimx-onscreen-obj\" data-obj-id=\"" . htmlspecialchars($it->id) . "\" data-obj-type=\"i\" data-obj-name=\"" . htmlspecialchars($it->name) . "\" onclick=\"clickObject(this)\" style=\"color:#38bdf8;text-decoration:none;display:inline-flex;align-items:center;cursor:pointer;\">{$iconImg}<b>" . htmlspecialchars($it->name) . "</b></a>";
                                }
                                $player->display("Containing: " . implode(', ', $itemLinks));
                            }
                        }

                        $world->fireEvent('onLook', $targetObj, $player, '', $inputData);
                    } else {
                        $player->display("You do not see that here.");
                    }
                } else {
                    $activeCont = ($player->container instanceof AdvObject && $player->container->isVehicle())
                        ? $player->container
                        : $currentRoom;
                    $player->focus = $activeCont;
                    if ($activeCont !== null) {
                        $desc = $activeCont->evalDescription($player);
                        if (!empty($desc)) {
                            $player->display($desc);
                        }
                        $world->fireEvent('onLook', $activeCont, $player, '', $inputData);
                    } else {
                        $player->display("You are in the void.");
                    }
                }
                break;

            case 'go':
                $dir = $arg0 ?: $arg1;
                if (!empty($dir)) {
                    $link = $currentRoom ? ($currentRoom->getWay($dir) ?? $currentRoom->getLinkByDirection($dir) ?? $this->findAccessibleObject($player, $dir)) : null;
                    if ($link instanceof Link && !$link->isOpen()) {
                        $linkName = $link->getDisplayName($currentRoom->id);
                        $player->display("{$linkName} is closed.");
                    } elseif ($player->go($dir)) {
                        $newRoom = $player->getRoom();
                        if ($newRoom !== null) {
                            $desc = $newRoom->evalDescription($player);
                            if ($desc !== '') {
                                $player->display($desc);
                            }
                        }
                    } else {
                        $player->display("You cannot go that way.");
                    }
                } else {
                    $player->display("Specify a direction to travel.");
                }
                break;

            case 'pick':
                $pickTarget = !empty($arg0) ? $arg0 : $arg1;
                if (!empty($pickTarget) && $currentRoom !== null) {
                    $item = $this->findAccessibleObject($player, $pickTarget);
                    if ($item instanceof Item && $item->isAccessibleFrom($player)) {
                        if ($item->isPickable()) {
                            if ($item->move($player)) {
                                $player->display("You picked up the <b>{$item->name}</b>.");
                                $world->fireEvent('whenPicked', $item, $player, $player);
                            } else {
                                $player->display("You cannot carry the {$item->name} (inventory full).");
                            }
                        } else {
                            $player->display("Could not take with me " . $item->name);
                        }
                    } else {
                        $player->display("The item is no longer available.");
                    }
                } else {
                    $player->display("Specify an item to pick up.");
                }
                break;

            case 'drop':
                $dropTarget = !empty($arg0) ? $arg0 : $arg1;
                $dropDest = ($player->container instanceof AdvObject && $player->container->isVehicle())
                    ? $player->container
                    : $currentRoom;
                if (!empty($dropTarget) && $dropDest !== null) {
                    $item = $player->contents[$dropTarget] ?? null;
                    if ($item instanceof Item && $item->move($dropDest)) {
                        $player->display("You dropped the <b>{$item->name}</b>.");
                        $world->fireEvent('whenDropped', $item, $player, $dropDest);
                    } else {
                        $player->display("You are not carrying that item.");
                    }
                } else {
                    $player->display("Specify an item to drop.");
                }
                break;

            case 'open':
                $openTarget = !empty($arg0) ? $arg0 : $arg1;
                if (!empty($openTarget)) {
                    $targetObj = $this->findAccessibleObject($player, $openTarget);
                    if ($targetObj instanceof AdvObject) {
                        if ($this->actOpen($targetObj, $player)) {
                            if (!$targetObj->isLink() && $targetObj->showmode !== AdvObject::SHOW_OFFSCREEN) {
                                $player->focus = $targetObj;
                            }
                        }
                    } else {
                        $player->display("You do not see that here.");
                    }
                } else {
                    $player->display("Specify what you want to open.");
                }
                break;

            case 'close':
                $closeTarget = !empty($arg0) ? $arg0 : $arg1;
                if (!empty($closeTarget)) {
                    $targetObj = $this->findAccessibleObject($player, $closeTarget);
                    if ($targetObj instanceof AdvObject) {
                        $this->actClose($targetObj, $player);
                    } else {
                        $player->display("You do not see that here.");
                    }
                } else {
                    $player->display("Specify what you want to close.");
                }
                break;

            case 'use':
                if (!empty($arg1) && !empty($arg0)) {
                    $this->executeCommandAction($world, $player, 'use2', $arg0, $arg1);
                    break;
                }
                $useTarget = !empty($arg0) ? $arg0 : $arg1;
                if (!empty($useTarget)) {
                    $clean = preg_replace('/\bwith\b/i', ' ', $useTarget);
                    $tokens = preg_split('/[,\s]+/', trim($clean));
                    if (count($tokens) >= 2) {
                        $this->executeCommandAction($world, $player, 'use2', $tokens[0], $tokens[1]);
                        break;
                    }
                    $item = $this->findAccessibleObject($player, $useTarget);
                    if ($item instanceof AdvObject) {
                        $this->actUse($item, $player);
                    } else {
                        $player->display("Item not found.");
                    }
                } else {
                    $player->display("Specify an item to use.");
                }
                break;

            case 'use2':
                $item1Id = '';
                $item2Id = '';

                if (!empty($arg1) && !empty($arg0)) {
                    $item1Id = $arg0;
                    $item2Id = $arg1;
                } else {
                    $raw = !empty($arg0) ? $arg0 : $arg1;
                    $raw = preg_replace('/\bwith\b/i', ' ', $raw);
                    $tokens = preg_split('/[,\s]+/', trim($raw));
                    $item1Id = $tokens[0] ?? '';
                    $item2Id = $tokens[1] ?? '';
                }

                if (empty($item1Id) || empty($item2Id)) {
                    $player->display("Specify two objects to use together.");
                    break;
                }

                $obj1 = $this->findAccessibleObject($player, $item1Id);
                $obj2 = $this->findAccessibleObject($player, $item2Id);

                if ($obj1 === null || $obj2 === null) {
                    $player->display("One of the selected items does not exist anymore.");
                    break;
                }

                $this->actUseWith($obj1, $obj2, $player);
                break;

            case 'search':
                $searchTarget = !empty($arg0) ? $arg0 : $arg1;
                if (!empty($searchTarget)) {
                    $targetObj = $this->findAccessibleObject($player, $searchTarget);
                    if ($targetObj instanceof AdvObject) {
                        $this->actSearch($targetObj, $player);
                    } else {
                        $player->display("The selected item does not exist anymore.");
                    }
                } else {
                    $currentRoom = $player->getRoom();
                    if ($currentRoom instanceof AdvObject) {
                        $this->actSearch($currentRoom, $player);
                    } else {
                        $player->display("I found nothing new.");
                    }
                }
                break;

            case 'give':
                $itemTarget = '';
                $toWhoTarget = '';

                if (!empty($arg1) && !empty($arg0)) {
                    $itemTarget = $arg0;
                    $toWhoTarget = $arg1;
                } else {
                    $raw = !empty($arg0) ? $arg0 : $arg1;
                    $raw = preg_replace('/\bto\b/i', ' ', $raw);
                    $tokens = preg_split('/[,\s]+/', trim($raw));
                    $itemTarget = $tokens[0] ?? '';
                    $toWhoTarget = $tokens[1] ?? '';
                }

                if (empty($itemTarget) || empty($toWhoTarget)) {
                    $player->display("Specify an item to give and a recipient.");
                    break;
                }

                $obj1 = $this->findAccessibleObject($player, $itemTarget);
                $obj2 = $this->findAccessibleObject($player, $toWhoTarget);

                if ($obj1 === null || $obj2 === null) {
                    $player->display("The selected item does not exist anymore.");
                    break;
                }

                // If user selected recipient first and item second, swap them
                if (!($obj1 instanceof Item) && ($obj2 instanceof Item)) {
                    $tmp = $obj1;
                    $obj1 = $obj2;
                    $obj2 = $tmp;
                }

                if (!($obj1 instanceof Item)) {
                    $player->display("I can only give items.");
                    break;
                }

                if (!($obj2 instanceof Character)) {
                    $player->display("I can only give objects to other actors.");
                    break;
                }

                $this->actGive($obj1, $obj2, $player);
                break;

            case 'put':
            case 'insert':
                $itemTarget = '';
                $containerTarget = '';

                if (!empty($arg1) && !empty($arg0)) {
                    $itemTarget = $arg0;
                    $containerTarget = $arg1;
                } else {
                    $raw = !empty($arg0) ? $arg0 : $arg1;
                    $raw = preg_replace('/\b(in|into)\b/i', ' ', $raw);
                    $tokens = preg_split('/[,\s]+/', trim($raw));
                    $itemTarget = $tokens[0] ?? '';
                    $containerTarget = $tokens[1] ?? '';
                }

                if (empty($itemTarget) || empty($containerTarget)) {
                    $player->display("Specify an item to put and a container.");
                    break;
                }

                $obj1 = $this->findAccessibleObject($player, $itemTarget);
                $obj2 = $this->findAccessibleObject($player, $containerTarget);

                if ($obj1 === null || $obj2 === null) {
                    $player->display("One of the selected items does not exist anymore.");
                    break;
                }

                $this->actPut($obj1, $obj2, $player);
                break;

            case 'say':
                if (!empty($arg0)) {
                    // Check if an explicit target was specified (say to someone)
                    $isSpeakAloud = empty($arg1) || $arg1 === '*';
                    $targetObj = null;

                    if (!$isSpeakAloud) {
                        $targetObj = $currentRoom?->getOccupants()[$arg1]
                            ?? $world->getCharacter($arg1)
                            ?? $world->getPlayer($arg1)
                            ?? $currentRoom?->contents[$arg1]
                            ?? null;

                        if ($targetObj === null && $currentRoom !== null) {
                            $targetLower = strtolower($arg1);
                            foreach ($currentRoom->getOccupants() as $occ) {
                                if (strtolower($occ->name) === $targetLower || strtolower($occ->id) === $targetLower) {
                                    $targetObj = $occ;
                                    break;
                                }
                            }
                        }
                    }

                    if (!$isSpeakAloud && $targetObj instanceof Character) {
                        // Spoken directly to someone
                        $player->display("You say to <b>{$targetObj->name}</b>: " . htmlspecialchars($arg0));
                        if ($targetObj instanceof Player) {
                            $targetObj->display("<b>{$player->name}</b> says to you: " . htmlspecialchars($arg0));
                        }
                        $world->postChatMessage($player->name, "@{$targetObj->name}: " . $arg0, $targetObj->name, $currentRoom?->id);
                        $world->fireEvent('onHear', $targetObj, $player, $arg0);
                    } else {
                        // Broadcast aloud to all players in the room (target is '*' or speaking aloud)
                        $world->postChatMessage($player->name, $arg0, null, $currentRoom?->id);
                        foreach ($world->players as $p) {
                            if ($p->getRoom() === $currentRoom) {
                                $p->display("<b>{$player->name}</b> says: " . htmlspecialchars($arg0));
                            }
                        }
                        // Fire onHear only on other Players in the same room (computer controlled characters excluded)
                        if ($currentRoom !== null) {
                            foreach ($currentRoom->getOccupants() as $occ) {
                                if ($occ !== $player && $occ instanceof Player) {
                                    $world->fireEvent('onHear', $occ, $player, $arg0);
                                }
                            }
                        }
                    }
                }
                break;

            case 'inv':
            case 'inventory':
                if (empty($player->contents)) {
                    $player->display("Your inventory is empty.");
                } else {
                    $list = [];
                    foreach ($player->contents as $itm) {
                        $list[] = $itm->name;
                    }
                    $player->display("You are carrying: " . implode(', ', $list));
                }
                break;

            case 'logout':
                // Notify other occupants in room & drop carried inventory to the floor
                if ($currentRoom !== null) {
                    foreach ($player->contents as $item) {
                        if ($item instanceof Item) {
                            $item->move($currentRoom);
                            $world->fireEvent('whenDropped', $item, $player, null);
                        }
                    }

                    foreach ($currentRoom->getOccupants() as $occ) {
                        if ($occ !== $player) {
                            $occ->display("<b>{$player->name}</b> has left the game.");
                        }
                    }
                }

                $world->fireEvent('onQuit', $player, $player, null);
                if ($currentRoom !== null) {
                    $world->fireEvent('onExit', $player, $player, null);
                }

                $player->display("You have logged out of <b>{$world->name}</b>. Goodbye!");

                // Remove player from active world and room contents
                $world->removePlayer($player);
                break;

            case 'enter':
                $enterTarget = !empty($arg0) ? $arg0 : $arg1;
                if (!empty($enterTarget)) {
                    $targetObj = $this->findAccessibleObject($player, $enterTarget);
                    if ($targetObj === null) {
                        $player->display("The selected item does not exist anymore.");
                        break;
                    }
                    $player->actEnter($targetObj);
                } else {
                    $player->display("Specify a vehicle to enter.");
                }
                break;

            case 'exit':
                $player->actExit();
                break;

            case 'save':
                $this->pm->savePlayer($player);
                $player->display("Game saved!");
                break;

            case 'login':
                // Initial login / connection handshake: show current room description
                if ($currentRoom !== null) {
                    $player->display("<b>{$currentRoom->name}</b>: {$currentRoom->evalDescription($player)}");
                }
                break;

            case 'popmsg':
                $msgId = !empty($arg0) ? $arg0 : (!empty($arg1) ? $arg1 : ($_REQUEST['msgid'] ?? ''));
                if ($msgId !== '') {
                    $player->popMessage((string)$msgId);
                }
                break;

            case 'rotl':
                $player->rotate('l');
                break;

            case 'rotr':
                $player->rotate('r');
                break;

            case 'rev':
                $player->rotate('b');
                break;

            default:
                // Check if the current panel defines a custom button/command event
                $btn = $panel?->getButton($cmd);
                $handled = false;
                if ($btn !== null && !empty($btn->event)) {
                    $handled = (bool)$world->fireEvent($btn->event, $world, $player, $arg1, ['param' => $arg0, 'arg0' => $arg0, 'arg1' => $arg1], false);
                }
                if (!$handled) {
                    $handled = (bool)$world->fireEvent("cmd_{$cmd}", $player, $player, $arg1, ['param' => $arg0, 'arg0' => $arg0, 'arg1' => $arg1], false);
                }
                if (!$handled) {
                    $player->display("Unknown command: {$cmd}");
                }
                break;
        }
    }

    /**
     * Find an object accessible to the player (in inventory, room, open containers, or room links).
     */
    public function findAccessibleObject(Player $player, string $targetId): ?AdvObject
    {
        if (preg_match('/^[a-z]!(.+)$/i', $targetId, $m)) {
            $targetId = $m[1];
        }

        // 1. Carried in player inventory
        if (isset($player->contents[$targetId])) {
            return $player->contents[$targetId];
        }

        // If player is inside a vehicle:
        if ($player->container instanceof AdvObject && $player->container->isVehicle()) {
            $vehicle = $player->container;
            if ($vehicle->id === $targetId) {
                return $vehicle;
            }
            if (isset($vehicle->contents[$targetId])) {
                return $vehicle->contents[$targetId];
            }
            // Inside open container in vehicle or player inventory
            foreach ($vehicle->contents as $cont) {
                if ($cont instanceof AdvObject && $cont->isOpen() && !$cont->isVehicle() && isset($cont->contents[$targetId])) {
                    return $cont->contents[$targetId];
                }
            }
            foreach ($player->contents as $cont) {
                if ($cont instanceof AdvObject && $cont->isOpen() && !$cont->isVehicle() && isset($cont->contents[$targetId])) {
                    return $cont->contents[$targetId];
                }
            }
            // Global world lookup checked via isAccessibleFrom
            $world = $player->world ?? null;
            if ($world !== null) {
                $globalObj = $world->contents[$targetId] ?? $world->getCharacter($targetId) ?? $world->getItem($targetId) ?? null;
                if ($globalObj instanceof AdvObject && $globalObj->isAccessibleFrom($player)) {
                    return $globalObj;
                }
            }
            return null;
        }

        $currentRoom = $player->getRoom();
        // 2. Directly in current room
        if ($currentRoom !== null && isset($currentRoom->contents[$targetId])) {
            return $currentRoom->contents[$targetId];
        }
        // 3. Inside an open container in the room or player inventory (excluding vehicles)
        if ($currentRoom !== null) {
            foreach ($currentRoom->contents as $cont) {
                if ($cont instanceof AdvObject && $cont->isOpen() && !$cont->isVehicle() && isset($cont->contents[$targetId])) {
                    return $cont->contents[$targetId];
                }
            }
        }
        foreach ($player->contents as $cont) {
            if ($cont instanceof AdvObject && $cont->isOpen() && !$cont->isVehicle() && isset($cont->contents[$targetId])) {
                return $cont->contents[$targetId];
            }
        }
        $targetLower = strtolower(trim($targetId));
        foreach ($player->contents as $id => $it) {
            if ($it instanceof AdvObject && (strtolower((string)$id) === $targetLower || strtolower($it->name ?? '') === $targetLower)) {
                return $it;
            }
        }
        if ($currentRoom !== null) {
            foreach ($currentRoom->contents as $id => $it) {
                if ($it instanceof AdvObject && (strtolower((string)$id) === $targetLower || strtolower($it->name ?? '') === $targetLower)) {
                    return $it;
                }
            }
        }
        // 4. Directional way or link in the room
        if ($currentRoom !== null) {
            $way = $currentRoom->getWay($targetId);
            if ($way !== null) {
                return $way;
            }
            $targetUpper = strtoupper(trim($targetId));
            $matchingLinks = [];
            foreach ($currentRoom->getLinks() as $lnk) {
                if (strtoupper($lnk->getDirection($currentRoom->id)) === $targetUpper) {
                    $matchingLinks[] = $lnk;
                }
            }
            if (!empty($matchingLinks)) {
                // If any matching link is openable or locked, prefer it (e.g. door vs open passage in same direction)
                foreach ($matchingLinks as $ml) {
                    if ($ml->isOpenable() || $ml->isLocked() || !empty($ml->icon)) {
                        return $ml;
                    }
                }
                return $matchingLinks[0];
            }
            $way = $currentRoom->getLinkByDirection($targetId);
            if ($way !== null) {
                return $way;
            }
            $targetLower = strtolower(trim($targetId));
            foreach ($currentRoom->getLinks() as $lnk) {
                if ($lnk instanceof Link) {
                    $displayName = strtolower($lnk->getDisplayName($currentRoom->id));
                    $rawName = strtolower($lnk->name ?? '');
                    if ($displayName === $targetLower || $rawName === $targetLower
                        || ltrim($displayName, 'the ') === ltrim($targetLower, 'the ')
                        || ltrim($rawName, 'the ') === ltrim($targetLower, 'the ')
                        || str_contains($displayName, $targetLower)
                        || str_contains($rawName, $targetLower)) {
                        return $lnk;
                    }
                }
            }
        }
        // 5. Global world occupants or items or links
        $world = $player->world ?? null;
        if ($world !== null) {
            $globalObj = $world->contents[$targetId] ?? $world->getCharacter($targetId) ?? $world->getItem($targetId) ?? $world->getLink($targetId) ?? null;
            if ($globalObj !== null && $globalObj->isAccessibleFrom($player)) {
                return $globalObj;
            }
        }
        return null;
    }

    /**
     * Use an adventure object for the given player.
     */
    public function actUse(AdvObject $targetObj, Player $player): bool
    {
        return $player->actUse($targetObj);
    }

    /**
     * Use two adventure objects together for the given player.
     */
    public function actUseWith(AdvObject $obj1, AdvObject $obj2, Player $player): bool
    {
        return $player->actUseWith($obj1, $obj2);
    }

    /**
     * Open an adventure object for the given player.
     */
    public function actOpen(AdvObject $targetObj, Player $player): bool
    {
        return $player->actOpen($targetObj);
    }

    /**
     * Close an adventure object for the given player.
     */
    public function actClose(AdvObject $targetObj, Player $player): bool
    {
        return $player->actClose($targetObj);
    }

    /**
     * Search an adventure object or container (or current room) for the given player.
     */
    public function actSearch(?AdvObject $targetObj, Player $player): bool
    {
        return $player->actSearch($targetObj);
    }

    /**
     * Give an item to another character for the given player.
     */
    public function actGive(Item $item, Character $recipient, Player $player): bool
    {
        return $player->actGive($item, $recipient);
    }

    /**
     * Put an item into a container for the given player.
     */
    public function actPut(AdvObject $what, AdvObject $where, Player $player): bool
    {
        return $player->actPut($what, $where);
    }
}
