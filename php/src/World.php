<?php
declare(strict_types=1);

namespace Dimx;

/**
 * World
 *
 * Central game environment singleton and aggregate root.
 * Manages all rooms, active players, NPCs, items, exits/links, global clock ticks,
 * event dispatching ($OWNER, $AGENT, $TARGET), and persistence.
 */
class World extends DimxObject
{
    public string $site = '';
    public string $instanceId = '';
    public string $bgcolor = '#9DB9E9';
    public int $interphone = 1; // 0=room only, 1=world with vehicle privacy, 2=global
    public int $tickLength = 30; // Seconds between clock ticks
    public int $mode = 1; // 1 = running, 0 = game won (Goal)
    public int $savegamePersistence = 2; // 0=restart only, 1=version only, 2=permanent
    public ?string $clusterId = null;

    /** @var array<string> Blacklisted client IPs */
    public array $bannedClients = [];

    /** @var array<string, Room> World rooms index */
    public array $rooms = [];

    /** @var array<string, Character> Non-player characters index */
    public array $characters = [];

    /** @var array<string, Player> Connected players index */
    public array $players = [];

    /** @var array<string, Item> Items index */
    public array $items = [];

    /** @var array<string, Link> Links/ways index */
    public array $links = [];

    /** @var array<string, Event> Registered script events, functions, and hooks */
    public array $events = [];

    public ?Panel $defaultPanel = null;
    public ?PersistenceManager $persistence = null;

    /** @var array<string> Runtime debug execution log */
    public array $log = [];

    public function __construct(string $id = 'world', string $name = 'DimensioneX World')
    {
        parent::__construct($id, $name, null);
        $this->world = $this;
        $this->instanceId = uniqid('dimx_inst_', true);
        $this->defaultPanel = Panel::createDefaultPanel();
    }

    /**
     * Prevent serializing runtime service instances (like PersistenceManager/PDO).
     */
    public function __sleep(): array
    {
        $vars = array_keys(get_object_vars($this));
        return array_values(array_diff($vars, ['persistence']));
    }

    public function addRoom(Room $room): void
    {
        $room->world = $this;
        $this->rooms[$room->id] = $room;
        $this->contents[$room->id] = $room;
    }

    public function getRoom(string $id): ?Room
    {
        return $this->rooms[$id] ?? null;
    }

    public function addCharacter(Character $character): void
    {
        $character->world = $this;
        $this->characters[$character->id] = $character;
        $this->contents[$character->id] = $character;
    }

    public function getCharacter(string $id): ?Character
    {
        return $this->characters[$id] ?? null;
    }

    public function addPlayer(Player $player): void
    {
        $player->world = $this;
        $this->players[$player->id] = $player;
        $this->characters[$player->id] = $player;
        $this->contents[$player->id] = $player;
    }

    public function getPlayer(string $id): ?Player
    {
        return $this->players[$id] ?? null;
    }

    /**
     * Remove a player from world registry and their current room/container.
     */
    public function removePlayer(Player|string $player): ?Player
    {
        $id = is_string($player) ? $player : $player->id;
        $p  = $this->players[$id] ?? null;
        if ($p !== null) {
            unset($this->players[$id]);
            unset($this->characters[$id]);
            unset($this->contents[$id]);
            if ($p->container !== null) {
                $p->container->removeObject($p);
            }
        }
        return $p;
    }

    public function addItem(Item $item): void
    {
        $item->world = $this;
        $this->items[$item->id] = $item;
        $this->contents[$item->id] = $item;
    }

    public function getItem(string $id): ?Item
    {
        return $this->items[$id] ?? null;
    }

    public function addLink(Link $link): void
    {
        $link->world = $this;
        $this->links[$link->id] = $link;
        $this->contents[$link->id] = $link;
    }

    public function getLink(string $id): ?Link
    {
        return $this->links[$id] ?? null;
    }

    public function registerEvent(Event $event): void
    {
        $this->events[$event->id] = $event;
    }

    /**
     * Dispatch an event to registered script handlers using standard DimensioneX event model.
     * Looks first for an object-qualified handler (e.g. 'sword.onUse'), then generic ('onUse').
     *
     * @param string $eventId Name of event (e.g. 'onLook', 'onEnter', 'whenPicked')
     * @param DimxObject|null $owner The $OWNER object
     * @param mixed $agent The $AGENT initiator
     * @param mixed $target The $TARGET secondary object
     * @param array<string, mixed> $input Dynamic input parameters
     * @param mixed $defaultResult Default return value
     * @return mixed
     */
    public function fireEvent(
        string $eventId,
        ?DimxObject $owner = null,
        mixed $agent = null,
        mixed $target = null,
        array $input = [],
        mixed $defaultResult = true
    ): mixed {
        $this->logDebug("fireEvent: {$eventId} (owner=" . ($owner?->id ?? 'null') . ")");

        // 1. Try qualified event: owner.eventId (e.g., 'altar.onLook')
        if ($owner !== null) {
            $qualifiedId = "{$owner->id}.{$eventId}";
            if (isset($this->events[$qualifiedId])) {
                return $this->events[$qualifiedId]->fire($owner, $agent, $target, $input, $defaultResult);
            }
        }

        // 2. Try generic event: eventId (e.g., 'onLook')
        if (isset($this->events[$eventId])) {
            return $this->events[$eventId]->fire($owner, $agent, $target, $input, $defaultResult);
        }

        return $defaultResult;
    }

    /**
     * Executes the global world clock tick:
     * 1. Fires global 'onTick' event.
     * 2. Fires 'Living' event for each Character to evaluate NPC AI / survival.
     */
    public function tick(): void
    {
        $this->logDebug("World clock tick at " . date('Y-m-d H:i:s'));

        // Fire onTick
        $this->fireEvent('onTick', $this, null, null);

        // Fire Living for all characters
        foreach ($this->characters as $char) {
            $lives = $this->fireEvent('Living', $char, $char, null, [], true);
            if ($lives === false || $lives === 0) {
                $this->logDebug("Character {$char->id} died during Living check");
                if ($char->container !== null) {
                    $char->container->removeObject($char);
                }
            }
        }
    }

    /**
     * Broadcast message to all connected players in the world.
     */
    public function display(string $msg): bool
    {
        foreach ($this->players as $player) {
            $player->display($msg);
        }
        return true;
    }

    public function logDebug(string $msg): void
    {
        $line = "[" . date('H:i:s') . "] {$msg}";
        $this->log[] = $line;
    }

    /**
     * Persist world state via the configured persistence manager.
     */
    public function save(): bool
    {
        if ($this->persistence !== null) {
            return $this->persistence->saveWorld($this);
        }
        return false;
    }

    /**
     * Restore world state via the configured persistence manager.
     */
    public function load(): bool
    {
        if ($this->persistence !== null) {
            return $this->persistence->loadWorld($this);
        }
        return false;
    }
}
