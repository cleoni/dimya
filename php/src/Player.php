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
        parent::__construct(
            $world, $name, $id, $description, $icon, $attrList,
            $capacity, $volume, $defContainer
        );
        $this->showmode = self::SHOW_ONSCREEN;
        $this->accepts = '*';
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
     * Get the player's active UI handler.
     */
    public function getUI(): mixed
    {
        return $this->ui;
    }

    /**
     * Prevent serializing the runtime UI instance into DB or persistent storage.
     */
    public function __sleep(): array
    {
        $vars = array_keys(get_object_vars($this));
        return array_values(array_diff($vars, ['ui']));
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
     * Display a system message or text output on the player's main console.
     */
    public function display(string $msg): bool
    {
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
     * Compute horizontal viewport scaling factor (factorx).
     *
     * Caps effective width to 1024px (enlargement factor max 1.6) so that
     * widescreen desktop displays (>1024px) do not over-magnify the scene picture.
     * This preserves the ideal 560x376 scene dimensions, prevents icons from
     * falling out of the viewport, and leaves ample space for console messages.
     */
    public function getFactorX(): float
    {
        $worldWidth = ($this->world && $this->world->screenwidth > 0) ? $this->world->screenwidth : 800;
        $width = ($this->screenWidth > 0) ? $this->screenWidth : 800;

        // If client width > 1024, cap effective calculation width to 1024
        // for worlds designed at <=640px, and cap enlargement factor to 1.6 max.
        $effectiveWidth = $width;
        if ($effectiveWidth > 1024 && $worldWidth <= 640) {
            $effectiveWidth = 1024;
        }

        $factor = (float)($effectiveWidth / $worldWidth);
        return min($factor, 1.6);
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
