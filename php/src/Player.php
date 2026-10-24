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

    /** @var mixed Active UI instance (UIhtml or UIjson) */
    public mixed $ui = null;

    /** @var array<string, mixed> Player cookies */
    public array $cookies = [];

    /** @var array<string> Main game console text buffer (room description, action outputs) */
    public array $clientConsole = [];

    /** @var array<array{id: string, sender: string, text: string, time: int}> Chat/speech messages buffer */
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
     * Deliver a speech or whisper message to this player's message stream.
     */
    public function deliverMessage(string $sender, string $text): void
    {
        $this->messagesQueue[] = [
            'id' => uniqid('msg_', true),
            'sender' => $sender,
            'text' => $text,
            'time' => time(),
        ];
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
     */
    public function getFactorX(): float
    {
        $worldWidth = ($this->world && $this->world->screenwidth > 0) ? $this->world->screenwidth : 800;
        return (float)($this->screenWidth / $worldWidth);
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
