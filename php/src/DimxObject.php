<?php
declare(strict_types=1);

namespace Dimx;

/**
 * DimxObject
 *
 * Represents any DimensioneX world object (World, Item, Character, Link, Room).
 * Maintains unique ID, name, parent World reference, and child contents dictionary.
 */
class DimxObject extends ManageableObject
{
    public string $id = '';
    public string $name = '';
    public ?World $world = null;

    /** @var string|null Background music URL associated with this object (Room/World) */
    public ?string $backgroundMusic = null;

    /** @var bool Whether background music on this object should loop */
    public bool $backgroundMusicLoop = false;

    /** @var array<string, AdvObject> Contained objects indexed by their unique ID */
    public array $contents = [];

    public function __construct(string $id = '', string $name = '', ?World $world = null)
    {
        parent::__construct();
        $this->id = $id;
        $this->name = $name;
        $this->world = $world;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Log a debug message to GameEngine (debug.log) and World log.
     */
    public function log(mixed $message): void
    {
        if ($this->world !== null) {
            $this->world->log($message);
        } elseif (GameEngine::$activeInstance !== null) {
            GameEngine::$activeInstance->log($message);
        }
    }

    /**
     * Debug alias matching DimensioneX AdvObject.debug().
     */
    public function debug(string $s): void
    {
        $this->log($s);
    }

    /**
     * Display a message to players in or connected to this entity.
     * Default returns false unless overridden by World, Room, or Player.
     */
    public function display(string $msg): bool
    {
        return false;
    }

    /**
     * Display a message in the right column.
     */
    public function displayRight(string $msg): bool
    {
        return false;
    }

    /**
     * Play a sound FX to players in or connected to this entity.
     * Default returns false unless overridden by World, PeopleContainer/Room, or Player.
     */
    public function playSound(string $soundfile): bool
    {
        return false;
    }

    /**
     * Play or set background music on this entity (World, PeopleContainer/Room, or Player).
     * Default returns false unless overridden.
     */
    public function playBackground(string $soundfile, bool $loop = false): bool
    {
        return false;
    }

    /**
     * Retrieve the background music configuration (['src' => string, 'loop' => bool]) if set on this object.
     * @return array{src: string, loop: bool}|null
     */
    public function getBackgroundMusic(): ?array
    {
        $src = $this->backgroundMusic ?? $this->getProperty('backgroundMusic');
        if (!is_string($src) || trim($src) === '') {
            return null;
        }
        $loopVal = $this->backgroundMusicLoop || !empty($this->getProperty('backgroundMusicLoop'));
        $resolved = ($this->world !== null) ? $this->world->resolveSoundUrl($src) : trim($src);
        return [
            'src'  => $resolved,
            'loop' => (bool)$loopVal,
        ];
    }

    /**
     * Add an AdvObject to this object's contents.
     */
    public function addObject(AdvObject $obj): bool
    {
        $this->contents[$obj->id] = $obj;
        $obj->container = $this instanceof AdvObject ? $this : null;
        return true;
    }

    /**
     * Remove an object from this container.
     */
    public function removeObject(AdvObject|string $obj): ?AdvObject
    {
        $id = is_string($obj) ? $obj : $obj->id;
        if (isset($this->contents[$id])) {
            $removed = $this->contents[$id];
            unset($this->contents[$id]);
            if ($removed->container === $this) {
                $removed->container = null;
            }
            return $removed;
        }
        return null;
    }

    /**
     * Search contained objects matching a specific name (case-insensitive).
     * @param string $name
     * @param array<string, AdvObject> &$found
     * @return array<string, AdvObject>
     */
    public function getObjectsByName(string $name, array &$found = []): array
    {
        $lower = strtolower($name);
        foreach ($this->contents as $id => $obj) {
            if (strtolower($obj->name) === $lower) {
                $found[$id] = $obj;
            }
        }
        return $found;
    }

    /**
     * Returns a summary string of contained object names.
     */
    public function printContents(): string
    {
        $names = [];
        foreach ($this->contents as $obj) {
            $names[] = $obj->name;
        }
        return implode(", ", $names);
    }

    public function __toString(): string
    {
        return $this->id . ' (' . $this->name . ')';
    }

    /**
     * Generate HTML snapshot fragment for this object.
     */
    public function htmlAdminSnapshot(): string
    {
        return "<li>" . htmlspecialchars((string)$this) . "</li>\n";
    }
}
