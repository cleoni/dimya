<?php
declare(strict_types=1);

namespace Dimx;

/**
 * Player
 *
 * Implements a human-controlled or bot player character connected via web session/API.
 * Manages player profile, authentication, client display buffers, cookies,
 * active UI instance, and command dispatch.
 */
class Player extends Character
{
    public ?string $remoteAddr = null;
    public string $password = '';
    public mixed $numericId = null;
    public mixed $isBot = false;
    public int $mute = 0;
    public int $score = 0;
    public ?Skin $skin = null;
    public ?Panel $panel = null;
    public ?string $prevPanelId = null;
    public bool $listening = true;

    /** @var int Timestamp of the last time an interaction / request was received */
    public int $lastActive = 0;

    /** @var int Timestamp of the last interaction / request received (alias for lastActive) */
    public int $lastInteraction = 0;

    /** @var bool Whether player enabled Sound FX at login */
    public bool $sounds = false;

    /** @var bool Whether player enabled Music at login */
    public bool $music = false;

    /** @var bool Alias for $sounds (DimensioneX Client.audioSupport compatibility) */
    public bool $audioSupport = false;

    /** @var bool Alias for $music (DimensioneX Client.musicSupport compatibility) */
    public bool $musicSupport = false;

    /** @var string|null Pending one-shot sound FX URL for next UIhtml render */
    public ?string $sound = null;

    /** @var string|null Pending background music URL for next UIhtml render */
    public ?string $backgroundSound = null;

    /** @var bool Whether pending background music should loop */
    public bool $backgroundSoundLoop = false;

    /** @var bool Whether a new incoming chat message arrived since last render */
    public bool $newMessages = false;

    /** @var AdvObject|null Currently focused/inspected object for zoom overlay on next render */
    public ?AdvObject $focus = null;

    /** @var int Client viewport screen width in pixels (default 1280) */
    public int $screenWidth = 1280;

    /** @var int Client viewport screen height in pixels (default 800) */
    public int $screenHeight = 800;

    /** @var bool Whether client is in vertical/portrait orientation (width < height) */
    public bool $isPortrait = false;

    /** @var mixed Active UI instance (UIhtml or UIjson) */
    public mixed $ui = null;

    /** @var array<string, mixed> Player cookies */
    public array $cookies = [];

    /** @var int Highest message ID recorded when player spawned/connected */
    public int $spawnMessageId = 0;

    /** @var array<string> Main game console text buffer (room description, action outputs) */
    public array $clientConsole = [];

    /** @var array<array{id: string, sender: string, text: string, time: string}> Incoming messages queue (max 10, oldest first) */
    public array $messagesQueue = [];

    /** @var string[] Pending client commands queue (e.g. "refresh!scene", "refresh!ctrls") */
    public array $pendingCommands = [];

    public function __construct(
        ?World $world = null,
        string $name = '',
        string $id = '',
        string $description = '',
        ?string $icon = null,
        string|array $attrList = '',
        int $capacity = 5,
        int $volume = 10,
        string $defContainer = ''
    ) {
        if (empty($attrList) && $world !== null && !empty($world->charactersDefaultAttrlist)) {
            $attrList = $world->charactersDefaultAttrlist;
        }
        parent::__construct(
            $world, $name, $id, $description, $icon, $attrList,
            $capacity, $volume, $defContainer
        );
        $this->accepts = '*';
        $this->recordInteraction();
    }

    /**
     * Record timestamp of the last time an interaction occurred (i.e. request received).
     */
    public function recordInteraction(?int $timestamp = null): void
    {
        $t = $timestamp ?? time();
        $this->lastActive = $t;
        $this->lastInteraction = $t;
        $this->properties['lastActive'] = $t;
        $this->properties['lastInteraction'] = $t;
    }

    public function isPlayer(): bool
    {
        return true;
    }

    /**
     * Set the player's active UI handler.
     */
    public function setUI(mixed $ui): void
    {
        $this->ui = $ui;
    }

    /**
     * Assign a Skin instance to this player and update default player avatar/icon accordingly.
     */
    public function setSkin(Skin $skin): void
    {
        $this->skin = $skin;
        $this->setProperty('skinId', $skin->id);
        if ($skin->picPlayer !== null) {
            $this->images['N'] = clone $skin->picPlayer;
        }
        if (!empty($skin->icons['ICOPLAYER'])) {
            $this->icon = $skin->icons['ICOPLAYER'];
        }
    }

    /**
     * Get the player's active UI handler.
     */
    public function getUI(): mixed
    {
        return $this->ui;
    }

    /**
     * Prevent serializing runtime UI and transient focus instances into DB or persistent storage.
     */
    public function __sleep(): array
    {
        $vars = array_keys(get_object_vars($this));
        return array_values(array_diff($vars, ['ui', 'focus']));
    }

    /**
     * Return all dynamic properties, keeping any defined 'Score' attribute in sync with score.
     *
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        $props = parent::getProperties();
        if (isset($props['Score'])) {
            $props['Score'] = $this->score;
        }
        return $props;
    }

    /**
     * Enable or disable Sound FX support for this player.
     */
    public function setSounds(bool $enabled): void
    {
        $this->sounds = $enabled;
        $this->audioSupport = $enabled;
    }

    /**
     * Enable or disable Music support for this player.
     */
    public function setMusic(bool $enabled): void
    {
        $this->music = $enabled;
        $this->musicSupport = $enabled;
    }

    /**
     * Queue a one-shot sound FX for this player if Sounds are enabled.
     * The sound filename is resolved relative to the World's IMAGESFOLDER.
     */
    public function playSound(string $soundfile): bool
    {
        if (!$this->sounds && !$this->audioSupport) {
            return false;
        }
        $soundfile = trim($soundfile);
        if ($soundfile === '') {
            return false;
        }
        $this->world?->log("Sounding: {$soundfile} to {$this->id}");
        $this->sound = ($this->world !== null)
            ? $this->world->resolveSoundUrl($soundfile)
            : $soundfile;
        return true;
    }

    /**
     * Read and clear any pending one-shot sound FX URL for this player.
     */
    public function drainSound(): ?string
    {
        $s = $this->sound;
        $this->sound = null;
        return $s;
    }

    /**
     * Queue background music for this player if Music is enabled.
     * The sound filename is resolved relative to the World's IMAGESFOLDER.
     */
    public function playBackground(string $soundfile, bool $loop = false): bool
    {
        if (!$this->music && !$this->musicSupport) {
            return false;
        }
        $soundfile = trim($soundfile);
        if ($soundfile === '') {
            $this->backgroundSound = null;
            $this->backgroundSoundLoop = false;
            return false;
        }
        $this->backgroundSound = ($this->world !== null)
            ? $this->world->resolveSoundUrl($soundfile)
            : $soundfile;
        $this->backgroundSoundLoop = $loop;
        return true;
    }

    /**
     * Read and clear any pending background music command for this player.
     * @return array{src: string, loop: bool}|null
     */
    public function drainBackgroundSound(): ?array
    {
        if ($this->backgroundSound === null || $this->backgroundSound === '') {
            return null;
        }
        $res = [
            'src'  => $this->backgroundSound,
            'loop' => $this->backgroundSoundLoop,
        ];
        $this->backgroundSound = null;
        $this->backgroundSoundLoop = false;
        return $res;
    }

    /**
     * Read and clear the new-messages notification flag for this player.
     */
    public function drainNewMessages(): bool
    {
        $has = $this->newMessages;
        $this->newMessages = false;
        return $has;
    }

    /**
     * Send client command to this player.
     * Matches DimensioneX Player.sendCmd().
     */
    public function sendCmd(string $cmd): bool
    {
        $cmd = trim($cmd);
        if ($cmd === '') {
            return false;
        }
        $this->world?->log("Sending: {$cmd} to player {$this->id}");
        if (!in_array($cmd, $this->pendingCommands, true)) {
            $this->pendingCommands[] = $cmd;
        }
        if ($this->world !== null && $this->world->persistence !== null) {
            $this->world->persistence->queueCommand($this->world->id, $this->name, $cmd);
        }
        return true;
    }

    /**
     * Read and clear pending commands formatted for clientExecute, e.g. "refresh!scene;".
     */
    public function drainCommands(): string
    {
        if (empty($this->pendingCommands)) {
            return '';
        }
        $cmds = implode(';', array_unique($this->pendingCommands)) . ';';
        $this->pendingCommands = [];
        return $cmds;
    }

    /**
     * Get array of pending commands.
     * @return string[]
     */
    public function getPendingCommands(): array
    {
        return $this->pendingCommands;
    }

    /**
     * Display a system message or text output on the player's main console.
     */
    public function display(string $msg): bool
    {
        $this->world?->log("Displaying to {$this->id}: {$msg}");
        $this->clientConsole[] = $msg;
        return true;
    }

    /**
     * Deliver an incoming message to this player's message stream.
     * Trims the queue to at most 10 messages, discarding older messages from the front.
     *
     * @return array{id: string, sender: string, text: string, time: string, recipient?: ?string}
     */
    public function deliverMessage(string $sender, string $text, ?string $id = null, ?string $time = null, ?string $recipient = null): array
    {
        // When speaking to a room or world broadcast, a player does not deliver the message to himself
        if ($recipient === null && (
            ($this->name !== '' && strcasecmp($this->name, $sender) === 0) ||
            ($this->id !== '' && strcasecmp($this->id, $sender) === 0)
        )) {
            return [];
        }

        $msg = [
            'id'        => $id ?? ('m_' . bin2hex(random_bytes(4))),
            'sender'    => $sender,
            'recipient' => $recipient,
            'text'      => $text,
            'time'      => $time ?? date('H:i:s'),
        ];
        $this->messagesQueue[] = $msg;
        while (count($this->messagesQueue) > 10) {
            array_shift($this->messagesQueue);
        }
        $this->newMessages = true;
        return $msg;
    }

    /**
     * Remove / discard a message by its ID from the player's message queue.
     *
     * @param string|int $msgId
     * @return array|null The popped message if found, null otherwise.
     */
    public function popMessage(string|int $msgId): ?array
    {
        $strId = (string)$msgId;
        foreach ($this->messagesQueue as $idx => $m) {
            if ((string)($m['id'] ?? '') === $strId) {
                $popped = $m;
                unset($this->messagesQueue[$idx]);
                $this->messagesQueue = array_values($this->messagesQueue);
                return $popped;
            }
        }
        return null;
    }

    /**
     * Get the player's current messages queue (arrival order, oldest first, up to 10).
     *
     * @return array<array{id: string, sender: string, text: string, time: string}>
     */
    public function getMessages(): array
    {
        return array_values($this->messagesQueue);
    }

    /**
     * Retrieve a cookie stored on player profile.
     */
    public function getCookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Save a cookie to player profile.
     */
    public function saveCookie(string $key, mixed $value): void
    {
        $this->world?->log("Saving player's data...");
        $this->cookies[$key] = $value;
    }

    /**
     * Get player's active command panel (or container's panel if unset).
     */
    public function getPanel(): ?Panel
    {
        if ($this->panel !== null) {
            return $this->panel;
        }
        $container = $this->container;
        if ($container instanceof PeopleContainer && $container->panel !== null) {
            return $container->panel;
        }
        return $this->world?->defaultPanel ?? null;
    }

    /**
     * Set player's active command panel.
     */
    public function setPanel(string|Panel|null $panel): bool
    {
        $panelId = is_string($panel) ? $panel : ($panel?->id ?? 'default');
        $this->world?->log("Setting panel: {$panelId} for player: {$this->id}");

        if (is_string($panel)) {
            $panel = $this->world?->getPanel($panel);
        }
        $this->panel = $panel;
        return true;
    }

    /**
     * Print text or image at specified (x,y) screen coordinates.
     */
    public function printXY(string|Image $stuff, int $x, int $y): void
    {
        $html = is_string($stuff) ? $stuff : $stuff->toHtml();
        $this->clientConsole[] = "<div style=\"position:absolute; left:{$x}px; top:{$y}px;\">{$html}</div>";
    }

    /**
     * Read and drain pending console output messages.
     * @return array<string>
     */
    public function drainConsole(): array
    {
        $out = $this->clientConsole;
        $this->clientConsole = [];
        return $out;
    }


    /**
     * Read and drain pending incoming chat messages.
     * @return array<array{id: string, sender: string, text: string, time: int}>
     */
    public function drainMessages(): array
    {
        $out = $this->messagesQueue;
        $this->messagesQueue = [];
        return $out;
    }
}
