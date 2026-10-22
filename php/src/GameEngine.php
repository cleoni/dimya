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
    public PersistenceManager $pm;
    public string $worldId;
    public ?string $worldFile;
    public string $baseUrl;
    public ?\PDO $pdo = null;
    public ?WorldLock $lock = null;

    public function __construct(
        PersistenceManager $pm,
        string $worldId = 'default',
        ?string $worldFile = null,
        string $baseUrl = ''
    ) {
        $this->pm        = $pm;
        $this->worldId   = $worldId;
        $this->worldFile = $worldFile;
        $this->baseUrl   = $baseUrl;

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
            return $world;
        }

        // 2. Not found — seed from .dxw file if specified
        if (!empty($this->worldFile)) {
            $loader = new DxwLoader($this->baseUrl);
            try {
                $world = $loader->load($this->worldFile);
            } catch (\Throwable $e) {
                error_log("Failed loading world '{$this->worldFile}': " . $e->getMessage());
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

        // Persist seed so future requests read from persistence
        $this->pm->saveWorld($world, $this->worldFile);

        return $world;
    }

    /**
     * Ensure the player exists in the world, setting start room and UI if new.
     */
    public function ensurePlayer(World $world, string $playerName, mixed $ui = null): Player
    {
        $player = $world->getPlayer($playerName);
        if ($player === null) {
            $player = new Player($world, $playerName, $playerName, 'Adventurer');
            $player->skin = new Skin('default', 'Ocean');

            // Find starting room: one marked DEFAULT or the first room
            $startRoom = null;
            foreach ($world->rooms as $r) {
                if ($r->getProperty('DEFAULT')) {
                    $startRoom = $r;
                    break;
                }
            }
            if ($startRoom === null) {
                $startRoom = reset($world->rooms) ?: null;
            }
            if ($startRoom !== null) {
                $player->move($startRoom);
            }

            $world->addPlayer($player);
            $player->display("Welcome to <b>{$world->name}</b>, {$player->name}!");
        }

        if ($ui !== null) {
            $player->setUI($ui);
        }

        return $player;
    }

    /**
     * Process an incoming player command inside the concurrency critical section.
     *
     * @param string $playerName Unique player name / ID
     * @param string $cmd Command verb (look, go, pick, drop, use, say, etc.)
     * @param string $param Secondary parameter or direction
     * @param string $targetId Target item, link, or character ID
     * @param mixed $ui Optional UI instance to attach to player
     * @return array<string, mixed> Execution outcome context
     */
    public function processCommand(
        string $playerName,
        string $cmd = 'look',
        string $param = '',
        string $targetId = '',
        mixed $ui = null
    ): array {
        $cmd      = strtolower(trim($cmd ?: 'look'));
        $param    = trim($param);
        $targetId = trim($targetId);

        $consoleMessages = [];
        $lockError       = false;

        // Concurrency-controlled execution via WorldLock (MySQL GET_LOCK)
        if ($this->pdo !== null && $this->lock !== null) {
            $result = $this->lock->executeAtomic($this->worldId, function (\PDO $pdo) use (
                $playerName, $cmd, $param, $targetId, $ui
            ): array {
                $world  = $this->loadOrSeedWorld();
                $player = $this->ensurePlayer($world, $playerName, $ui);

                $this->executeCommandAction($world, $player, $cmd, $param, $targetId);

                // Advance world clock tick
                $world->tick();

                // Drain console output for this player
                $msgs = $player->drainConsole();

                // Save mutated world state back to persistence
                $this->pm->saveWorld($world);

                return [
                    'world'  => $world,
                    'player' => $player,
                    'msgs'   => $msgs,
                ];
            });

            if ($result === null) {
                $lockError = true;
                $consoleMessages = ["<span style='color:#f87171'>⚠ The game world is busy. Please try again in a moment.</span>"];
                $world  = $this->pm->loadWorld($this->worldId) ?? new World($this->worldId, 'DimensioneX');
                $world->persistence = $this->pm;
                $player = $world->getPlayer($playerName) ?? new Player($world, $playerName, $playerName);
            } else {
                $world           = $result['world'];
                $player          = $result['player'];
                $consoleMessages = $result['msgs'];
            }

            // Post chat messages outside the world lock
            if ($cmd === 'say' && !empty($param) && !$lockError) {
                $this->pm->postMessage($this->worldId, $playerName, htmlspecialchars($param));
            }
        } else {
            // Graceful degradation when DB is offline (session / memory fallback)
            $world  = $this->loadOrSeedWorld();
            $player = $this->ensurePlayer($world, $playerName, $ui);

            $this->executeCommandAction($world, $player, $cmd, $param, $targetId);

            $world->tick();
            $consoleMessages = $player->drainConsole();
            $this->pm->saveWorld($world);
        }

        if ($ui !== null) {
            $player->setUI($ui);
        }

        $storageLabel = $this->pm->checkConnection()['type'];

        return [
            'world'        => $world,
            'player'       => $player,
            'room'         => $player->getRoom(),
            'messages'     => $consoleMessages,
            'lockError'    => $lockError,
            'cmd'          => $cmd,
            'param'        => $param,
            'target'       => $targetId,
            'storageLabel' => $storageLabel,
            'worldId'      => $this->worldId,
        ];
    }

    /**
     * Internal dispatcher for player commands mutating game objects.
     */
    protected function executeCommandAction(
        World $world,
        Player $player,
        string $cmd,
        string $param,
        string $targetId
    ): void {
        $currentRoom = $player->getRoom();

        switch ($cmd) {
            case 'look':
            case 'examine':
                if (!empty($targetId)) {
                    $targetObj = $currentRoom?->contents[$targetId]
                              ?? $player->contents[$targetId]
                              ?? $world->contents[$targetId]
                              ?? null;

                    if ($targetObj instanceof AdvObject) {
                        $player->display("<b>{$targetObj->name}</b>: {$targetObj->evalDescription($player)}");
                        $world->fireEvent('onLook', $targetObj, $player, null);
                    } else {
                        $player->display("You do not see that here.");
                    }
                } else {
                    if ($currentRoom !== null) {
                        $player->display("<b>{$currentRoom->name}</b>: {$currentRoom->evalDescription($player)}");
                        $world->fireEvent('onLook', $currentRoom, $player, null);
                    } else {
                        $player->display("You are in the void.");
                    }
                }
                break;

            case 'go':
                $dir = $param ?: $targetId;
                if (!empty($dir)) {
                    if ($player->go($dir)) {
                        $newRoom = $player->getRoom();
                        $player->display("You travel to <b>{$newRoom->name}</b>.");
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
                if (!empty($targetId) && $currentRoom !== null) {
                    $item = $currentRoom->contents[$targetId] ?? null;
                    if ($item instanceof Item && $item->container === $currentRoom && $item->pickable) {
                        if ($item->move($player)) {
                            $player->display("You picked up the <b>{$item->name}</b>.");
                            $world->fireEvent('whenPicked', $item, $player, $player);
                        } else {
                            $player->display("You cannot carry the {$item->name} (inventory full).");
                        }
                    } else {
                        $player->display("The item is no longer available.");
                    }
                } else {
                    $player->display("Specify an item to pick up.");
                }
                break;

            case 'drop':
                if (!empty($targetId) && $currentRoom !== null) {
                    $item = $player->contents[$targetId] ?? null;
                    if ($item instanceof Item && $item->move($currentRoom)) {
                        $player->display("You dropped the <b>{$item->name}</b>.");
                        $world->fireEvent('whenDropped', $item, $player, $currentRoom);
                    } else {
                        $player->display("You are not carrying that item.");
                    }
                } else {
                    $player->display("Specify an item to drop.");
                }
                break;

            case 'use':
                if (!empty($targetId)) {
                    $item = $player->contents[$targetId] ?? $currentRoom?->contents[$targetId] ?? null;
                    if ($item instanceof Item) {
                        $res = $world->fireEvent('onUse', $item, $player, null);
                        if (!$res) {
                            $player->display("Using {$item->name} had no obvious effect.");
                        }
                    } else {
                        $player->display("Item not found.");
                    }
                } else {
                    $player->display("Specify an item to use.");
                }
                break;

            case 'say':
            case 'talk':
                if (!empty($param)) {
                    // Broadcast to all players in the world
                    foreach ($world->players as $p) {
                        $p->display("<b>{$player->name}</b> says: " . htmlspecialchars($param));
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

            case 'savexit':
            case 'save':
                $this->pm->savePlayer($player);
                $player->display("Game saved! You may safely close this window.");
                break;

            case 'login':
                // Initial login / connection handshake: show current room description
                if ($currentRoom !== null) {
                    $player->display("<b>{$currentRoom->name}</b>: {$currentRoom->evalDescription($player)}");
                }
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
}
