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

    /** Inactivity timeout for automatically disconnecting idle players (seconds) */
    public int $playerTimeout = 1800; // 30 minutes

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

        if (empty($this->config) && file_exists(__DIR__ . '/../config.php')) {
            $loadedConfig = @require __DIR__ . '/../config.php';
            if (is_array($loadedConfig)) {
                $this->config = $loadedConfig;
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
    public function loadOrSeedWorld(): World
    {
        // 1. Try loading from persistence
        $world = $this->pm->loadWorld($this->worldId);
        if ($world !== null) {
            $world->persistence = $this->pm;
            if (isset($this->config['tickLength']) && is_numeric($this->config['tickLength'])) {
                $world->tickLength = max(1, (int)$this->config['tickLength']);
            }
            $this->pruneStalePlayers($world);
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
            $loader = new DxwLoader($this->baseUrl);
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
     * Remove players that have exceeded the inactivity timeout.
     */
    protected function pruneStalePlayers(World $world): void
    {
        $now = time();
        foreach ($world->players as $p) {
            $last = (int)($p->getProperty('lastActive') ?? $now);
            if (($now - $last) > $this->playerTimeout) {
                $world->removePlayer($p);
            }
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
            $player   = new Player($world, $playerName, $engineId, 'Adventurer');
            $player->skin      = $chosenSkin ?? $world->defaultSkin ?? new Skin('default', 'Default');
            $player->numericId = $numericId;
            $player->isBot     = $isBot;
            if ($sounds !== null) {
                $player->setSounds($sounds);
            }
            if ($music !== null) {
                $player->setMusic($music);
            }
            $player->showmode  = AdvObject::SHOW_ONSCREEN;
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

            // Ensure showmode and avatar images remain properly configured after profile load
            $player->showmode = AdvObject::SHOW_ONSCREEN;
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
            $player->display("Welcome to <b>{$world->name}</b>, {$player->name}!");
        } else {
            if ($player->numericId === null && $numericId !== null) {
                $player->numericId = $numericId;
            }
            if ($isBot) {
                $player->isBot = true;
            }
            if ($sounds !== null) {
                $player->setSounds($sounds);
            }
            if ($music !== null) {
                $player->setMusic($music);
            }
            if ($chosenSkin !== null) {
                $player->setSkin($chosenSkin);
            } elseif ($player->skin === null) {
                $player->skin = $world->defaultSkin ?? new Skin('default', 'Default');
            }
            if ($player->showmode === 0) {
                $player->showmode = AdvObject::SHOW_ONSCREEN;
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
        $player->setProperty('lastActive', time());

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
     * @param string $param Secondary parameter or direction
     * @param string $targetId Target item, link, or character ID
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
        string $param = '',
        string $targetId = '',
        mixed $ui = null,
        ?int $numericId = null,
        bool $isBot = false,
        bool $isNewLogin = false,
        ?string $existingEngineId = null,
        ?string $skinId = null,
        ?bool $sounds = null,
        ?bool $music = null
    ): array {
        $cmd      = strtolower(trim($cmd ?: 'look'));
        $param    = trim($param);
        $targetId = trim($targetId);

        $consoleMessages = [];
        $pendingSound    = null;
        $pendingBgMusic  = null;
        $hasNewMsgs      = false;
        $lockError       = false;
        $loggedOut       = in_array($cmd, ['logout'], true);

        // Concurrency-controlled execution via WorldLock (MySQL GET_LOCK)
        if ($this->pdo !== null && $this->lock !== null) {
            $result = $this->lock->executeAtomic($this->worldId, function (\PDO $pdo) use (
                $playerName, $cmd, $param, $targetId, $ui, $loggedOut, $numericId, $isBot, $isNewLogin, $existingEngineId, $skinId, $sounds, $music
            ): array {
                $world  = $this->loadOrSeedWorld();
                $player = $this->ensurePlayer($world, $playerName, $ui, $numericId, $isBot, $isNewLogin, $existingEngineId, $skinId, $sounds, $music);

                $this->executeCommandAction($world, $player, $cmd, $param, $targetId);

                // Advance world clock tick
                $world->tick();

                // Drain console output and pending sound/music/message flags for this player
                $msgs           = $player->drainConsole();
                $pendingSound   = $player->drainSound();
                $pendingBgMusic = $player->drainBackgroundSound();
                $hasNewMsgs     = $player->drainNewMessages();

                // Save mutated world state back to persistence
                $this->pm->saveWorld($world);

                return [
                    'world'            => $world,
                    'player'           => $player,
                    'msgs'             => $msgs,
                    'sound'            => $pendingSound,
                    'background_music' => $pendingBgMusic,
                    'new_messages'     => $hasNewMsgs,
                    'logged_out'       => $loggedOut,
                ];
            });

            if ($result === null) {
                $lockError = true;
                $consoleMessages = ["<span style='color:#f87171'>⚠ The game world is busy. Please try again in a moment.</span>"];
                $world  = $this->pm->loadWorld($this->worldId) ?? new World($this->worldId, 'DimensioneX');
                $world->persistence = $this->pm;
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
                $world           = $result['world'];
                $player          = $result['player'];
                $consoleMessages = $result['msgs'];
                $pendingSound    = $result['sound'] ?? null;
                $pendingBgMusic  = $result['background_music'] ?? null;
                $hasNewMsgs      = (bool)($result['new_messages'] ?? false);
                $loggedOut       = $result['logged_out'] ?? $loggedOut;
            }
        } else {
            // Graceful degradation when DB is offline (session / memory fallback)
            $world  = $this->loadOrSeedWorld();
            $player = $this->ensurePlayer($world, $playerName, $ui, $numericId, $isBot, $isNewLogin, $existingEngineId, $skinId, $sounds, $music);

            $this->executeCommandAction($world, $player, $cmd, $param, $targetId);

            $world->tick();
            $consoleMessages = $player->drainConsole();
            $pendingSound    = $player->drainSound();
            $pendingBgMusic  = $player->drainBackgroundSound();
            $hasNewMsgs      = $player->drainNewMessages();
            $this->pm->saveWorld($world);
        }

        if ($ui !== null) {
            $player->setUI($ui);
        }

        $storageLabel = $this->pm->checkConnection()['type'];

        // Messages box (Cell C) displays the player's 10 most recent incoming messages only (arrival order)
        $chatMessages = $player->getMessages();

        return [
            'world'            => $world,
            'player'           => $player,
            'room'             => $player->getRoom(),
            'messages'         => $consoleMessages,
            'chat'             => $chatMessages,
            'sound'            => $pendingSound,
            'background_music' => $pendingBgMusic,
            'new_messages'     => $hasNewMsgs,
            'lockError'        => $lockError,
            'cmd'          => $cmd,
            'param'        => $param,
            'target'       => $targetId,
            'storageLabel' => $storageLabel,
            'worldId'      => $this->worldId,
            'logged_out'   => $loggedOut,
        ];
    }

    /**
     * Internal dispatcher for player commands mutating game objects.
     */
    public function executeCommandAction(
        World $world,
        Player $player,
        string $cmd,
        string $param,
        string $targetId
    ): void {
        $panel = $player->getPanel() ?? $world->defaultPanel;
        $isCoreCommand = in_array($cmd, [
            'look', 'click', 'go', 'pick', 'take', 'get', 'drop',
            'give', 'use', 'use2', 'usewith', 'open', 'close', 'search', 'say', 'inv',
            'inventory', 'help', 'save', 'enter', 'board', 'exit', 'leave', 'disembark',
            'logout', 'rotl', 'rotr', 'rev', 'rotate'
        ], true);

        if ($panel !== null && ($isCoreCommand || isset($world->events["cmd_{$cmd}"])) && !$panel->isCommandAllowed($cmd)) {
            $player->display("Command '{$cmd}' is not allowed in current panel ({$panel->id}).");
            return;
        }

        $currentRoom = $player->getRoom();

        switch ($cmd) {
            case 'look':
            case 'click':
                $lookTarget = !empty($targetId) ? $targetId : $param;
                if (!empty($lookTarget)) {
                    $targetObj = $this->findAccessibleObject($player, $lookTarget);

                    if ($targetObj instanceof AdvObject) {
                        $desc = $targetObj->evalDescription($player);
                        $msg = !empty($desc) ? $desc : "<b>{$targetObj->name}</b>";
                        $player->display($msg);

                        if ($targetObj->isOpen() && !$targetObj->isVehicle()) {
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

                        $world->fireEvent('onLook', $targetObj, $player, null);
                    } else {
                        $player->display("You do not see that here.");
                    }
                } else {
                    $activeCont = ($player->container instanceof AdvObject && $player->container->isVehicle())
                        ? $player->container
                        : $currentRoom;
                    if ($activeCont !== null) {
                        $player->display("<b>{$activeCont->name}</b>: {$activeCont->evalDescription($player)}");
                        $world->fireEvent('onLook', $activeCont, $player, null);
                    } else {
                        $player->display("You are in the void.");
                    }
                }
                break;

            case 'go':
                $dir = $param ?: $targetId;
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
            case 'take':
            case 'get':
                $pickTarget = !empty($targetId) ? $targetId : $param;
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
                $dropTarget = !empty($targetId) ? $targetId : $param;
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
                $openTarget = !empty($targetId) ? $targetId : $param;
                if (!empty($openTarget)) {
                    $targetObj = $this->findAccessibleObject($player, $openTarget);
                    if ($targetObj instanceof AdvObject) {
                        $this->actOpen($targetObj, $player);
                    } else {
                        $player->display("You do not see that here.");
                    }
                } else {
                    $player->display("Specify what you want to open.");
                }
                break;

            case 'close':
                $closeTarget = !empty($targetId) ? $targetId : $param;
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
                if (!empty($targetId) && !empty($param)) {
                    $this->executeCommandAction($world, $player, 'use2', $param, $targetId);
                    break;
                }
                $useTarget = !empty($targetId) ? $targetId : $param;
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
            case 'usewith':
                $item1Id = '';
                $item2Id = '';

                if (!empty($targetId) && !empty($param)) {
                    $item1Id = $param;
                    $item2Id = $targetId;
                } else {
                    $raw = !empty($param) ? $param : $targetId;
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
                $searchTarget = !empty($targetId) ? $targetId : $param;
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

                if (!empty($targetId) && !empty($param)) {
                    $itemTarget = $param;
                    $toWhoTarget = $targetId;
                } else {
                    $raw = !empty($param) ? $param : $targetId;
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

            case 'say':
                if (!empty($param)) {
                    // Check if an explicit target was specified (say to someone)
                    $targetObj = !empty($targetId)
                        ? ($currentRoom?->getOccupants()[$targetId] ?? $world->getCharacter($targetId) ?? $currentRoom?->contents[$targetId] ?? null)
                        : null;

                    if ($targetObj instanceof Character) {
                        // Spoken directly to someone
                        $player->display("You say to <b>{$targetObj->name}</b>: " . htmlspecialchars($param));
                        if ($targetObj instanceof Player) {
                            $targetObj->display("<b>{$player->name}</b> says to you: " . htmlspecialchars($param));
                        }
                        $world->postChatMessage($player->name, "@{$targetObj->name}: " . $param, $targetObj->name, $currentRoom?->id);
                        $world->fireEvent('onHear', $targetObj, $player, $param);
                    } else {
                        // Broadcast aloud to all players in the room
                        $world->postChatMessage($player->name, $param, null, $currentRoom?->id);
                        foreach ($world->players as $p) {
                            if ($p->getRoom() === $currentRoom) {
                                $p->display("<b>{$player->name}</b> says: " . htmlspecialchars($param));
                            }
                        }
                        // Fire onHear on other occupants in the same room
                        if ($currentRoom !== null) {
                            foreach ($currentRoom->getOccupants() as $occ) {
                                if ($occ !== $player) {
                                    $world->fireEvent('onHear', $occ, $player, $param);
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
            case 'board':
                $enterTarget = !empty($targetId) ? $targetId : $param;
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
            case 'leave':
            case 'disembark':
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
                $msgId = !empty($param) ? $param : (!empty($targetId) ? $targetId : ($_REQUEST['msgid'] ?? ''));
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

            case 'rotate':
                $rotParam = !empty($param) ? $param : (!empty($targetId) ? $targetId : 'r');
                $player->rotate($rotParam);
                break;

            default:
                // Try dynamic custom command event e.g. 'cmd_<name>'
                $handled = $world->fireEvent("cmd_{$cmd}", $player, $player, $targetId, ['param' => $param], false);
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
}
