<?php
declare(strict_types=1);

namespace Dimx;

/**
 * AdvObject
 *
 * Game's base adventure object. Character, Item, Link, and Room are AdvObjects.
 * Handles spatial positioning, containment, facing, visibility, capacity/volume,
 * and media representation.
 */
class AdvObject extends DimxObject
{
    public const SHOW_OFFSCREEN = 0;
    public const SHOW_ONSCREEN  = 1;
    public const SHOW_ICON      = 2;

    public ?string $icon = null;
    public int $capacity = 5;
    public int $volume = 1;
    public string $description = '';
    public ?AdvObject $container = null;
    public string $defContainer = '';

    /** @var array<string, Image> Direction-indexed Image objects ('N', 'S', 'E', 'W') */
    public array $images = [];

    public int $showmode = self::SHOW_OFFSCREEN;
    public string $facing = 'N';
    public bool $hidden = false;
    public bool $invisible = false;
    public int $showX = -1;
    public int $showY = -1;
    public ?string $showFor = null;

    public function __construct(
        ?World $world = null,
        string $name = '',
        string $id = '',
        string $description = '',
        ?string $icon = null,
        string|array $attrList = '',
        int $capacity = 5,
        int $volume = 1,
        bool $setDefShowpos = true,
        string $defContainer = ''
    ) {
        parent::__construct($id, $name, $world);
        $this->description = $description;
        $this->icon = $icon;
        $this->capacity = $capacity;
        $this->volume = $volume;
        $this->defContainer = $defContainer;

        if ($setDefShowpos) {
            $this->showX = -1;
            $this->showY = -1;
        }

        if (!empty($attrList)) {
            $this->setAttrList($attrList);
        }
    }

    /**
     * Check if this object contains an object with ID $whatId.
     */
    public function contains(string $whatId): bool
    {
        return isset($this->contents[$whatId]);
    }

    /**
     * Locate the enclosing Room of this object by walking up the container hierarchy.
     */
    public function getRoom(): ?Room
    {
        $cur = $this;
        while ($cur !== null) {
            if ($cur instanceof Room) {
                return $cur;
            }
            $cur = $cur->container;
        }
        return null;
    }

    /**
     * Returns true if this object has container capacity.
     */
    public function isContainer(): bool
    {
        return $this->capacity > 0;
    }

    /**
     * Move this object into target container, checking volume and capacity.
     */
    public function move(AdvObject $target): bool
    {
        if ($this->container === $target) {
            return true;
        }

        // Check target container capacity
        $currentVolume = 0;
        foreach ($target->contents as $child) {
            $currentVolume += $child->volume;
        }

        if (($currentVolume + $this->volume) > $target->capacity && $target->capacity > 0) {
            // Target is full
            return false;
        }

        // Remove from current container
        if ($this->container !== null) {
            $this->container->removeObject($this);
        }

        // Add to new container
        $target->addObject($this);
        return true;
    }

    /**
     * Evaluates description. If description starts with '@', invokes dynamic callback/function.
     */
    public function evalDescription(?AdvObject $agent = null): string
    {
        if (str_starts_with($this->description, '@')) {
            $funcExpr = substr($this->description, 1);
            if ($this->world !== null && isset($this->world->events[$funcExpr])) {
                return (string)$this->world->fireEvent($funcExpr, $this, $agent);
            }
        }
        return $this->description;
    }

    public function isaCharacter(): bool
    {
        return false;
    }

    public function isaRoom(): bool
    {
        return false;
    }

    public function isanItem(): bool
    {
        return false;
    }

    public function isLink(): bool
    {
        return false;
    }
}
