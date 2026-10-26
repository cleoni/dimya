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
     * Sets an image for a specific direction face.
     */
    public function setImage(string $face, Image $image): bool
    {
        $face = strtoupper(trim($face)) ?: 'N';
        $this->images[$face] = $image;
        return true;
    }

    /**
     * Check if this object has multiple directional images/faces.
     */
    public function hasSeveralFaces(): bool
    {
        return count($this->images) > 1;
    }

    /**
     * Returns the exact image for the specified face, or null if undefined.
     */
    public function getExactImage(string $face): ?Image
    {
        $face = strtoupper(trim($face));
        return $this->images[$face] ?? null;
    }

    /**
     * Returns the image for the specified facing direction, falling back to 'N' or first image.
     */
    public function getImageForFacing(string $face): ?Image
    {
        if (empty($this->images)) {
            return null;
        }
        $face = strtoupper(trim($face));
        if (isset($this->images[$face])) {
            return $this->images[$face];
        }
        return $this->images['N'] ?? reset($this->images) ?: null;
    }

    /**
     * Check if this object can be opened and closed (has 'open' declared or dynamic property, or has 'locked').
     */
    public function isOpenable(): bool
    {
        return property_exists($this, 'open') || $this->getProperty('open') !== null || $this->isLocked() || $this->getProperty('locked') !== null;
    }

    /**
     * Check if this object can be picked up.
     * Items are not pickable unless explicitly declared with pickable=1 or pickable in attributes.
     */
    public function isPickable(): bool
    {
        if (property_exists($this, 'pickable')) {
            return (bool)$this->pickable;
        }
        $val = $this->getProperty('pickable');
        if ($val === null) {
            return false;
        }
        if (is_bool($val)) {
            return $val;
        }
        if (is_numeric($val)) {
            return (int)$val !== 0;
        }
        return !empty($val) && strtolower((string)$val) !== 'false';
    }

    /**
     * Check if this object is currently open. Non-openable objects are open by default.
     */
    public function isOpen(): bool
    {
        if ($this->isOpenable()) {
            $val = property_exists($this, 'open') ? $this->open : $this->getProperty('open');
            if (is_bool($val)) {
                return $val;
            }
            if (is_numeric($val)) {
                return (int)$val !== 0;
            }
            return !empty($val) && strtolower((string)$val) !== 'false';
        }
        return true;
    }

    /**
     * Check if this object is currently locked.
     */
    public function isLocked(): bool
    {
        $val = property_exists($this, 'locked') ? $this->locked : $this->getProperty('locked');
        if ($val === null) {
            $val = $this->getProperty('locked');
        }
        if ($val === null) {
            return false;
        }
        if (is_bool($val)) {
            return $val;
        }
        if (is_numeric($val)) {
            return (int)$val !== 0;
        }
        return !empty($val) && strtolower((string)$val) !== 'false';
    }

    /**
     * Check if this object is currently hidden.
     * Objects are visible by default unless explicitly specified with hidden=1 (or hidden flag in ATTRLIST).
     */
    public function isHidden(): bool
    {
        if (property_exists($this, 'hidden') && $this->hidden === true) {
            return true;
        }

        $val = $this->getProperty('hidden');
        if ($val === null || $val === false || $val === 0 || $val === '0' || $val === '') {
            return false;
        }
        if (is_numeric($val)) {
            return (int)$val > 0;
        }
        if (is_bool($val)) {
            return $val;
        }
        return strtolower((string)$val) === 'true';
    }

    /**
     * Check if this object is currently invisible.
     */
    public function isInvisible(): bool
    {
        $val = property_exists($this, 'invisible') ? $this->invisible : $this->getProperty('invisible');
        if ($val === null) {
            $val = $this->getProperty('invisible');
        }
        if ($val === null) {
            return false;
        }
        if (is_bool($val)) {
            return $val;
        }
        if (is_numeric($val)) {
            return (int)$val !== 0;
        }
        return !empty($val) && strtolower((string)$val) !== 'false';
    }

    /**
     * Check if this object is physically accessible to the specified entity.
     */
    public function isAccessibleFrom(AdvObject $from): bool
    {
        if (isset($from->contents[$this->id])) {
            return true;
        }
        $myCont = $from->container;
        $cont   = $this->container;
        if ($myCont !== null && ($myCont === $cont || $myCont === $this)) {
            return true;
        }
        if ($cont instanceof AdvObject && $cont->isanItem()) {
            if ($cont->isOpen() && ($cont->container === $myCont || $cont->container === $from)) {
                return true;
            }
        }
        if ($this->isLink() && $this instanceof Link) {
            $fromRoom = $from->getRoom();
            if ($fromRoom !== null && (isset($this->fromto[$fromRoom->id]) || $fromRoom->getWay($this->id) !== null || ($this->isBidirectional && $this->leadsTo($fromRoom->id)))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Open this object. Fires beforeOpen and onOpen events.
     */
    public function open(?Character $agent = null): bool
    {
        if (!$this->isOpenable()) {
            return false;
        }

        if ($agent !== null && $this->world !== null) {
            $allow = $this->world->fireEvent('beforeOpen', $this, $agent, null);
            if ($allow === false || $allow === 0 || $allow === '0') {
                return false;
            }
        }

        if ($this->isOpen()) {
            return false;
        }

        if ($this->isLocked()) {
            if ($agent !== null) {
                $agent->display("It's locked.");
            }
            return false;
        }

        if (property_exists($this, 'open')) {
            $this->open = true;
        }
        $this->setProperty('open', 1);

        if ($this->world !== null) {
            $this->world->fireEvent('onOpen', $this, $agent, null);
        }

        return true;
    }

    /**
     * Close this object. Fires onClose event.
     */
    public function close(?Character $agent = null): bool
    {
        if (!$this->isOpenable()) {
            return false;
        }

        if (!$this->isOpen()) {
            return false;
        }

        if (property_exists($this, 'open')) {
            $this->open = false;
        }
        $this->setProperty('open', 0);

        if ($this->world !== null) {
            $this->world->fireEvent('onClose', $this, $agent, null);
        }

        return true;
    }

    /**
     * Get contents, optionally hiding contents of closed containers.
     *
     * @return array<string, AdvObject>
     */
    public function getContents(bool $checkOpen = false): array
    {
        if ($checkOpen && $this->isanItem() && !$this->isOpen()) {
            return [];
        }
        return $this->contents;
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

        if ($this instanceof Character && $target instanceof Room) {
            if ($target->hasSeveralFaces()) {
                if ($target->getExactImage($this->facing) === null) {
                    $this->facing = (string)array_key_first($target->images);
                }
            } elseif (count($target->images) === 1) {
                $this->facing = (string)array_key_first($target->images);
            }
        }
        return true;
    }

    /**
     * Resets this object to its initial defined container (defContainer).
     * If defContainer is an expression (e.g. rndSet(setBeach)), evaluates it.
     */
    public function reset(): void
    {
        if (empty($this->defContainer) || $this->world === null) {
            return;
        }

        $targetId = trim($this->defContainer);

        // Check if defContainer is an expression like rndSet(...) or RndSet(...)
        if (preg_match('/^rndSet\s*\(\s*(.+?)\s*\)$/i', $targetId, $m)) {
            $setName = trim($m[1]);
            if (str_starts_with($setName, '$WORLD.')) {
                $setName = substr($setName, 7);
            }
            $set = $this->world->sets[$setName] ?? $this->world->getProperty($setName) ?? null;
            if (is_array($set) && !empty($set)) {
                $keys = array_keys($set);
                $randKey = $keys[array_rand($keys)];
                $targetId = is_int($randKey) ? (string)$set[$randKey] : (string)$randKey;
            }
        }

        $target = $this->world->getRoom($targetId)
            ?? $this->world->getItem($targetId)
            ?? $this->world->contents[$targetId]
            ?? null;

        if ($target instanceof AdvObject) {
            $this->move($target);
        } elseif (!empty($this->world->defaultRoom)) {
            $defRoom = $this->world->getRoom($this->world->defaultRoom);
            if ($defRoom instanceof AdvObject) {
                $this->move($defRoom);
            }
        }
    }

    /**
     * Evaluates description. If description starts with '@', invokes dynamic callback/function.
     * Openable items append open/closed state as a hint to the player.
     */
    public function evalDescription(?AdvObject $agent = null): string
    {
        $desc = $this->description;
        if (str_starts_with($desc, '@')) {
            $funcExpr = substr($desc, 1);
            if ($this->world !== null && isset($this->world->events[$funcExpr])) {
                $desc = (string)$this->world->fireEvent($funcExpr, $this, $agent);
            }
        }

        if ($this->isOpenable()) {
            $name = ($this instanceof Link && $agent instanceof Character && ($room = $agent->getRoom()) !== null)
                ? $this->getDisplayName($room->id)
                : (!empty($this->name) ? $this->name : $this->id);
            $status = $this->isOpen() ? 'is open.' : 'is closed.';
            if (!empty($desc)) {
                $desc .= "\n";
            }
            $desc .= "{$name} {$status}";
        }

        return $desc;
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
