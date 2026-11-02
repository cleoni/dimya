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
    public int $lastTick = 0; // Timestamp of last executed clock tick
    public mixed $mode = 0; // 0 = stopped / lobby / won (Goal), 1 = adventure running, or string like "pre", "play"
    public int $savegamePersistence = 2; // 0=restart only, 1=version only, 2=permanent
    public bool $reset = false;
    public mixed $logger = null;

    // Screen and viewport design dimensions
    public int $screenwidth = 800; // Target design screen display size (default 800)
    public int $screenheight = 600; // Target design screen display size (default 600)
    public int $sceneWidth = 350; // Default room scene width (default 350)
    public int $sceneHeight = 235; // Default room scene height (default 235)
    public int $iconSize = 16; // Default on-screen icon size (default 16)
    public string $imagesFolder = ''; // Remote or local base images folder

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

    /** @var array<string, Panel> Available command panels */
    public array $panels = [];

    /** @var string|null Default attributes string for characters and players defined under CHARACTERS section */
    public ?string $charactersDefaultAttrlist = null;

    public ?Character $defaultCharacter = null;
    public ?Panel $defaultPanel = null;
    public ?Room $startRoom = null;
    public ?string $defaultRoom = null;
    public ?PersistenceManager $persistence = null;
    public ?GameEngine $engine = null;

    /** @var array<string, array> Sets and arrays defined in SETS section */
    public array $sets = [];

    /** @var array<string, Skin> Available skins indexed by skin id */
    public mixed $skins = [];
    public mixed $skinslist = '';

    /** @var Skin|null Default skin for connected players */
    public ?Skin $defaultSkin = null;

    /** @var array<string> Runtime debug execution log */
    public array $log = [];

    public function getSet(string $name): ?array
    {
        return $this->sets[$name] ?? $this->getProperty($name) ?? null;
    }

    public function initLogger(): void
    {
        $this->logger = new class($this) {
            private World $world;
            public function __construct(World $world)
            {
                $this->world = $world;
            }
            public function debug(mixed $msg): void
            {
                $this->world->log($msg);
            }
            public function info(mixed $msg): void
            {
                $this->world->log($msg);
            }
            public function warn(mixed $msg): void
            {
                $this->world->log($msg);
            }
            public function error(mixed $msg): void
            {
                $this->world->log($msg);
            }
        };
    }

    public function __construct(string $id = 'world', string $name = 'DimensioneX World')
    {
        parent::__construct($id, $name, null);
        $this->world = $this;
        $this->initLogger();
        $this->instanceId = uniqid('dimx_inst_', true);

        // Build prebuilt DimensioneY panels (default and chat)
        $this->panels['default'] = Panel::createDefaultPanel();
        $this->panels['chat']    = Panel::createChatPanel();
        $this->defaultPanel      = $this->panels['default'];
        $this->lastTick          = time();

        // Initialize default skin
        $defaultSkin = new Skin('default', 'Default', '/skins/default/');
        $this->skins['default'] = $defaultSkin;
        $this->defaultSkin = $defaultSkin;

        $this->log("World created with id=" . $this->id);
    }

    /**
     * Retrieve a Skin instance from this world by its ID or Name (case-insensitive).
     */
    public function getSkin(?string $idOrName): ?Skin
    {
        if ($idOrName === null) {
            return null;
        }
        $target = strtolower(trim($idOrName));
        if ($target === '') {
            return null;
        }
        foreach ($this->skins as $key => $skin) {
            if (strtolower((string)$key) === $target || strtolower($skin->id) === $target || strtolower($skin->name) === $target) {
                return $skin;
            }
        }
        return null;
    }

    public function getPanel(string $id): ?Panel
    {
        return $this->panels[strtolower(trim($id))] ?? null;
    }

    public function addPanel(Panel $panel): void
    {
        $this->panels[strtolower($panel->id)] = $panel;
    }

    public function setPanel(string|Panel $panel): bool
    {
        $panelId = is_string($panel) ? $panel : $panel->id;
        $this->log("Setting panel: {$panelId} in whole world");

        $p = is_string($panel) ? $this->getPanel($panel) : $panel;
        if ($p === null) {
            return false;
        }
        $this->defaultPanel = $p;
        foreach ($this->players as $player) {
            $player->setPanel($p);
        }
        return true;
    }

    public function broadcast(string $msg): bool
    {
        $this->log("Displaying: {$msg} in whole world");
        return $this->display($msg);
    }

    /** @var array<array{id: int|string, sender: string, text: string, time: string}> In-memory recent chat messages queue */
    public array $chatQueue = [];

    /**
     * Post a chat message from a player, character, or the system to the world message queue
     * and delivers it directly to each relevant connected player's incoming message queue.
     *
     * @param string      $sender    Name of character, player, or system voice speaking
     * @param string      $text      The spoken text
     * @param string|null $recipient Optional specific recipient player name
     * @param string|null $roomId    Optional specific room ID to broadcast speech within
     */
    public function postChatMessage(string $sender, string $text, ?string $recipient = null, ?string $roomId = null): bool
    {
        $text = trim($text);
        if ($text === '') {
            return false;
        }

        $msgId   = (string)(int)(microtime(true) * 1000);
        $msgTime = date('H:i:s');

        $msg = [
            'id'        => $msgId,
            'sender'    => $sender,
            'recipient' => $recipient,
            'text'      => $text,
            'time'      => $msgTime,
        ];
        $this->chatQueue[] = $msg;

        // Deliver directly to target player(s) incoming message queue
        if ($recipient !== null && $recipient !== '') {
            $p = $this->getPlayerByName($recipient);
            if ($p instanceof Player) {
                $p->deliverMessage($sender, $text, $msgId, $msgTime, $recipient);
            }
        } elseif ($roomId !== null && $roomId !== '') {
            $room = $this->getRoom($roomId);
            if ($room instanceof Room) {
                foreach ($room->getOccupants() as $occ) {
                    if ($occ instanceof Player) {
                        $occ->deliverMessage($sender, $text, $msgId, $msgTime, null);
                    }
                }
            }
        } else {
            foreach ($this->players as $p) {
                if ($p instanceof Player) {
                    $p->deliverMessage($sender, $text, $msgId, $msgTime, null);
                }
            }
        }

        if ($this->persistence !== null) {
            $this->persistence->postMessage($this->id, $sender, $text, $recipient);
        }

        return true;
    }

    /**
     * Prevent serializing runtime service instances (like PersistenceManager/PDO).
     */
    public function __sleep(): array
    {
        $vars = array_keys(get_object_vars($this));
        return array_values(array_diff($vars, ['persistence', 'engine', 'logger']));
    }

    public function __wakeup(): void
    {
        $this->initLogger();
        if (!is_array($this->players)) {
            $this->players = [];
        } else {
            foreach ($this->players as $k => $p) {
                if (!$p instanceof Player) {
                    unset($this->players[$k]);
                }
            }
        }
    }

    public function setProperty(string $name, mixed $value): mixed
    {
        $lower = strtolower($name);
        // Protect core internal collections from being overwritten by SmallBasic script variables/arrays (e.g. ARRAY players)
        if (in_array($lower, ['players', 'rooms', 'characters', 'items', 'links', 'contents', 'sets', 'events', 'skins'], true)) {
            $this->properties[$name] = $value;
            return $value;
        }
        return parent::setProperty($name, $value);
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

    /**
     * Retrieve or instantiate the world's default system speaker character ("SYS", "a voice...").
     */
    public function getDefaultCharacter(): Character
    {
        if ($this->defaultCharacter === null) {
            if (isset($this->characters['SYS'])) {
                $this->defaultCharacter = $this->characters['SYS'];
            } else {
                $sys = new Character($this, 'a voice...', 'SYS');
                $sys->showmode = AdvObject::SHOW_OFFSCREEN;
                $this->addCharacter($sys);
                $this->defaultCharacter = $sys;
            }
        }
        return $this->defaultCharacter;
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
     * Retrieve an active in-memory player by their database numerical account ID.
     */
    public function getPlayerByNumericId(mixed $numericId): ?Player
    {
        if ($numericId === null || $numericId === '' || $numericId === 0 || $numericId === '0') {
            return null;
        }
        foreach ($this->players as $p) {
            if ((string)$p->numericId === (string)$numericId) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Retrieve an active in-memory player by their display nickname.
     */
    public function getPlayerByName(string $name): ?Player
    {
        $target = strtolower(trim($name));
        if ($target === '') {
            return null;
        }
        foreach ($this->players as $p) {
            if (strtolower(trim($p->name)) === $target) {
                return $p;
            }
        }
        return null;
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

    /**
     * Resolves an object by ID across all world collections (rooms, items, characters, players, links, contents).
     */
    public function getObjectExt(string $id): ?DimxObject
    {
        $id = trim($id);
        if ($id === '' || strcasecmp($id, 'world') === 0 || strcasecmp($id, '$world') === 0) {
            return $this;
        }
        if (strcasecmp($id, 'sys') === 0) {
            return $this->getDefaultCharacter();
        }
        return $this->contents[$id]
            ?? $this->rooms[$id]
            ?? $this->items[$id]
            ?? $this->characters[$id]
            ?? $this->players[$id]
            ?? $this->links[$id]
            ?? null;
    }

    public function registerEvent(Event $event): void
    {
        $this->events[$event->id] = $event;
    }

    public function getEvent(string $id): ?Event
    {
        if (isset($this->events[$id])) {
            return $this->events[$id];
        }
        foreach ($this->events as $k => $ev) {
            if (strcasecmp($k, $id) === 0) {
                return $ev;
            }
        }
        return null;
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
        $agentId = $agent instanceof DimxObject ? $agent->id : (is_scalar($agent) ? (string)$agent : 'null');
        $targetId = $target instanceof DimxObject ? $target->id : (is_scalar($target) ? (string)$target : 'null');
        $this->log("world.fireEvent: {$eventId}(\$OWNER=" . ($owner?->id ?? 'null') . ",\$AGENT={$agentId},\$TARGET={$targetId})");

        // 1. Try qualified event: owner.eventId (e.g., 'altar.onLook')
        if ($owner !== null) {
            $qualifiedId = "{$owner->id}.{$eventId}";
            if (isset($this->events[$qualifiedId])) {
                return $this->events[$qualifiedId]->fire($owner, $agent, $target, $input, $defaultResult, $this);
            }
            foreach ($this->events as $k => $ev) {
                if (strcasecmp($k, $qualifiedId) === 0) {
                    return $ev->fire($owner, $agent, $target, $input, $defaultResult, $this);
                }
            }
        }

        // 2. Try generic event: eventId (e.g., 'onLook')
        if (isset($this->events[$eventId])) {
            return $this->events[$eventId]->fire($owner, $agent, $target, $input, $defaultResult, $this);
        }
        foreach ($this->events as $k => $ev) {
            if (strcasecmp($k, $eventId) === 0) {
                return $ev->fire($owner, $agent, $target, $input, $defaultResult, $this);
            }
        }

        return $defaultResult;
    }

    /**
     * Executes the global world clock tick:
     * Checks if time passed since the last tick is >= tickLength.
     * If so (or if $force is true):
     * 1. Updates lastTick timestamp.
     * 2. Fires global 'onTick' event.
     * 3. Fires 'Living' event for each Character to evaluate NPC AI / survival.
     *
     * @param bool $force If true, bypasses the elapsed time check.
     * @return bool True if tick was executed, false if skipped.
     */
    public function tick(bool $force = false): bool
    {
        $now = time();

        if (!$force) {
            if ($this->lastTick === 0) {
                $this->lastTick = $now;
                return false;
            }

            if (($now - $this->lastTick) < $this->tickLength) {
                return false;
            }
        }

        $this->lastTick = $now;
        $nextTickStr = date('Y-m-d H:i:s', $now + $this->tickLength);
        $this->log("*Tick* - next at {$nextTickStr}");
        $this->log("Checking survival...");

        // Fire onTick
        $this->fireEvent('onTick', $this, null, null);

        // Fire Living for all characters
        foreach ($this->characters as $char) {
            $lives = $this->fireEvent('Living', $char, $char, null, [], true);
            if ($lives === false || $lives === 0 || $lives === '0') {
                $this->log("{$char->name} dies because EVENT Living returned false:");
                $this->log("Character {$char->id} definitely dies.");
                if ($char->container !== null) {
                    $char->container->removeObject($char);
                }
            }
        }

        return true;
    }

    /**
     * Broadcast message to all connected players in the world.
     */
    public function display(string $msg): bool
    {
        $this->log("Displaying: {$msg} in whole world");
        foreach ($this->players as $player) {
            $player->display($msg);
        }
        return true;
    }

    /**
     * Broadcast a one-shot sound FX to all connected players in the world.
     */
    public function playSound(string $soundfile): bool
    {
        $this->log("Sounding: {$soundfile} in the whole world");
        foreach ($this->players as $player) {
            $player->playSound($soundfile);
        }
        return true;
    }

    /**
     * Sets the background music property on the world and broadcasts it to all connected players.
     */
    public function playBackground(string $soundfile, bool $loop = false): bool
    {
        $soundfile = trim($soundfile);
        $resolved  = ($soundfile !== '') ? $this->resolveSoundUrl($soundfile) : '';

        $this->backgroundMusic = ($resolved !== '') ? $resolved : null;
        $this->backgroundMusicLoop = $loop;
        $this->setProperty('backgroundMusic', $this->backgroundMusic);
        $this->setProperty('backgroundMusicLoop', $loop);

        $this->log("Playing background sound: {$soundfile} in the whole world");
        foreach ($this->players as $player) {
            $player->playBackground($soundfile, $loop);
        }
        return true;
    }

    /**
     * Resolve a sound filename against the world's IMAGESFOLDER.
     */
    public function resolveSoundUrl(string $soundfile): string
    {
        $soundfile = trim($soundfile);
        if ($soundfile === '') {
            return '';
        }
        if (
            str_starts_with($soundfile, 'http://') ||
            str_starts_with($soundfile, 'https://') ||
            str_starts_with($soundfile, '/')
        ) {
            return $soundfile;
        }
        $folder = rtrim($this->imagesFolder, '/');
        if ($folder !== '') {
            if (str_starts_with($soundfile, $folder . '/')) {
                return $soundfile;
            }
            return $folder . '/' . $soundfile;
        }
        return $soundfile;
    }

    public function log(mixed $message): void
    {
        $line = "[" . date('H:i:s') . "] " . (is_scalar($message) ? (string)$message : json_encode($message));
        $this->log[] = $line;

        if ($this->engine instanceof GameEngine) {
            $this->engine->log($message);
        } elseif (GameEngine::$activeInstance !== null) {
            GameEngine::$activeInstance->log($message);
        }
    }

    public function logDebug(string $msg): void
    {
        $this->log($msg);
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

    /**
     * Generate complete HTML snapshot of the world and engine state.
     */
    public function htmlAdminSnapshot(): string
    {
        $engineVersion = GameEngine::ENGINE_VERSION;
        $worldVersion  = (string)($this->getProperty('version') ?? '1.0');
        $serverTime    = date('D M d H:i:s T Y');

        $roomsCount = count($this->rooms);
        $charCount  = count($this->characters);
        $eventCount = count($this->events);
        $waysCount  = count($this->links);
        $itemsCount = count($this->items);
        $panelCount = count($this->panels);

        $sb = "<a name=\"top\" id=\"top\"></a>\n";
        $sb .= "<div class=\"snapshot-container\">\n";
        $sb .= "Engine version: " . htmlspecialchars($engineVersion, ENT_QUOTES, 'UTF-8') . "<br>\n";
        $sb .= "World: " . htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8') . " v." . htmlspecialchars($worldVersion, ENT_QUOTES, 'UTF-8') . "<br>\n";
        $sb .= "Images folder: " . htmlspecialchars($this->imagesFolder, ENT_QUOTES, 'UTF-8') . "<br>\n";
        $sb .= "Server time is now: " . htmlspecialchars($serverTime, ENT_QUOTES, 'UTF-8') . "<br><br>\n";

        $sb .= "Players: ";
        if (empty($this->players)) {
            $sb .= "(none)<br>\n";
        } else {
            $pLinks = [];
            foreach ($this->players as $p) {
                $pIdEsc   = htmlspecialchars($p->id, ENT_QUOTES, 'UTF-8');
                $pNameEsc = htmlspecialchars((string)$p, ENT_QUOTES, 'UTF-8');
                $pLinks[] = "<a href=\"#{$pIdEsc}\">{$pNameEsc}</a>";
            }
            $sb .= implode(', ', $pLinks) . "<br>\n";
        }

        $sb .= "<ul>\n";
        $sb .= "<li><a href=\"#rooms\">{$roomsCount} rooms</a></li>\n";
        $sb .= "<li><a href=\"#characters\">{$charCount} characters</a></li>\n";
        $sb .= "<li><a href=\"#events\">({$eventCount}) events</a></li>\n";
        $sb .= "<li><a href=\"#ways\">{$waysCount} links</a></li>\n";
        $sb .= "<li><a href=\"#items\">{$itemsCount} items</a></li>\n";
        $sb .= "<li><a href=\"#properties\">World properties</a></li>\n";
        $sb .= "<li><a href=\"#panels\">{$panelCount} panels</a></li>\n";
        $sb .= "</ul>\n";

        // Rooms section
        $sb .= "<p><a name=\"rooms\" id=\"rooms\"></a></p>\n<h1>{$roomsCount} rooms</h1>\n<ul>\n";
        foreach ($this->rooms as $room) {
            $sb .= $room->htmlAdminSnapshot();
        }
        $sb .= "</ul>\n<p><a href=\"#top\">Summary</a></p>\n";

        // Characters section
        $sb .= "<p><a name=\"characters\" id=\"characters\"></a></p>\n<h1>{$charCount} characters</h1>\n<ul>\n";
        foreach ($this->characters as $char) {
            $sb .= $char->htmlAdminSnapshot();
        }
        $sb .= "</ul>\n<p><a href=\"#top\">Summary</a></p>\n";

        // Events section
        $sb .= "<p><a name=\"events\" id=\"events\"></a></p>\n<h1>({$eventCount}) events</h1>\n<ul>\n";
        foreach ($this->events as $event) {
            $sb .= $event->htmlAdminSnapshot();
        }
        $sb .= "</ul>\n<p><a href=\"#top\">Summary</a></p>\n";

        // Links / Ways section (supports both #ways and #links anchors)
        $sb .= "<p><a name=\"ways\" id=\"ways\"></a><a name=\"links\" id=\"links\"></a></p>\n<h1>{$waysCount} links</h1>\n<ul>\n";
        foreach ($this->links as $link) {
            $sb .= $link->htmlAdminSnapshot();
        }
        $sb .= "</ul>\n<p><a href=\"#top\">Summary</a></p>\n";

        // Items section
        $sb .= "<p><a name=\"items\" id=\"items\"></a></p>\n<h1>Items</h1>\n<ul>\n";
        foreach ($this->items as $item) {
            $sb .= $item->htmlAdminSnapshot();
        }
        $sb .= "<li>Items count={$itemsCount}</li>\n";
        $sb .= "</ul>\n<p><a href=\"#top\">Summary</a></p>\n";

        // World properties section
        $sb .= "<p><a name=\"properties\" id=\"properties\"></a></p>\n<h1>World properties</h1>\n<ul>\n";
        foreach ($this->getProperties() as $k => $v) {
            if (str_starts_with((string)$k, '__')) continue;
            if (is_bool($v)) {
                $valStr = $v ? 'true' : 'false';
            } elseif (is_array($v)) {
                $valStr = json_encode($v, JSON_UNESCAPED_SLASHES);
            } else {
                $valStr = (string)$v;
            }
            $sb .= "<li><b>" . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . "</b>=" . htmlspecialchars($valStr, ENT_QUOTES, 'UTF-8') . "</li>\n";
        }
        $sb .= "</ul>\n<p><a href=\"#top\">Summary</a></p>\n";

        // Panels section
        $sb .= "<p><a name=\"panels\" id=\"panels\"></a></p>\n<h1>{$panelCount} panels</h1>\n<ul>\n";
        foreach ($this->panels as $panel) {
            $sb .= $panel->htmlAdminSnapshot();
        }
        $sb .= "</ul>\n<p><a href=\"#top\">Summary</a></p>\n";

        $sb .= "</div>\n";
        return $sb;
    }
}
