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
