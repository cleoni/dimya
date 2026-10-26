<?php
declare(strict_types=1);

namespace Dimx;

/**
 * Character
 *
 * Implements an animated character or NPC.
 * Capable of autonomous or scripted movement ('go'), speech, inventory holding,
 * item acceptance rules, and responding to system events.
 */
class Character extends AdvObject
{
    /** @var mixed Item acceptance rule: null = accepts nothing, '*' = accepts all, or list of IDs/types */
    public mixed $accepts = null;

    public bool $__clearfocus = false;
    public bool $__clearinfo = false;

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
            $capacity, $volume, true, $defContainer
        );
        $this->showmode = self::SHOW_ONSCREEN;
    }

    public function isaCharacter(): bool
    {
        return true;
    }

    public function isRobot(): bool
    {
        return !($this instanceof Player);
    }

    /**
     * Checks if this character accepts a given item based on the $accepts rule.
     */
    public function canAccept(Item $item): bool
    {
        if ($this->accepts === null || $this->accepts === '') {
            return false;
        }
        if ($this->accepts === '*') {
            return true;
        }

        $acceptedList = is_array($this->accepts)
            ? $this->accepts
            : array_map('trim', explode(',', (string)$this->accepts));

        foreach ($acceptedList as $rule) {
            if ($rule === $item->id || $item->matchesType($rule)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Navigate the character to another room through an exit link.
     */
    public function go(string $directionOrLinkId): bool
    {
        $room = $this->getRoom();
        if ($room === null) {
            return false;
        }

        $link = $room->getWay($directionOrLinkId) ?? $room->getLinkByDirection($directionOrLinkId);
        if ($link === null || !$link->isOpen()) {
            return false;
        }

        $destRoom = $link->getTarget($room->id);
        if ($destRoom === null) {
            return false;
        }

        // Fire onExit and onEnter events if World is available
        if ($this->world !== null) {
            $this->world->fireEvent('onExit', $this, $this, $destRoom);
        }

        $moved = $this->move($destRoom);

        if ($moved && $this->world !== null) {
            $this->world->fireEvent('onEnter', $this, $this, $destRoom);
        }

        return $moved;
    }

    /**
     * Speak a message to the room or world.
     */
    public function say(string $msg, ?DimxObject $target = null): void
    {
        $room = $this->getRoom();
        if ($room !== null) {
            $formatted = "{$this->name}: {$msg}";
            $room->display($formatted);
        }
    }

    /**
     * Open an adventure object (door, chest, container, link).
     */
    public function actOpen(AdvObject $obj): bool
    {
        if (!$obj->isAccessibleFrom($this)) {
            $this->display("You cannot reach that.");
            return false;
        }

        if (!$obj->isOpenable()) {
            $this->display("<b>{$obj->name}</b> cannot be opened.");
            return false;
        }

        if ($obj->isLocked()) {
            $this->display("It's locked");
            return false;
        }

        if ($obj->isOpen()) {
            $this->display("<b>{$obj->name}</b> is already open.");
            return false;
        }

        if ($obj->open($this)) {
            $this->display("You opened <b>{$obj->name}</b>.");
            $contents = $obj->getContents(true);
            if (!empty($contents)) {
                $itemLinks = [];
                foreach ($contents as $it) {
                    $iconImg = !empty($it->icon)
                        ? "<img src=\"" . htmlspecialchars($it->icon) . "\" alt=\"" . htmlspecialchars($it->name) . "\" style=\"width:20px;height:20px;object-fit:contain;vertical-align:middle;margin-right:4px;border-radius:2px\" />"
                        : "📦 ";
                    $itemLinks[] = "<a href=\"javascript:void(0)\" class=\"dimx-onscreen-obj\" data-obj-id=\"" . htmlspecialchars($it->id) . "\" data-obj-type=\"i\" data-obj-name=\"" . htmlspecialchars($it->name) . "\" onclick=\"clickObject(this)\" style=\"color:#38bdf8;text-decoration:none;display:inline-flex;align-items:center;cursor:pointer;\">{$iconImg}<b>" . htmlspecialchars($it->name) . "</b></a>";
                }
                $this->display("Containing: " . implode(', ', $itemLinks));
            }
            return true;
        }

        return false;
    }

    /**
     * Close an opened adventure object.
     */
    public function actClose(AdvObject $obj): bool
    {
        if (!$obj->isAccessibleFrom($this)) {
            $this->display("You cannot reach that.");
            return false;
        }

        if (!$obj->isOpenable()) {
            $this->display("<b>{$obj->name}</b> cannot be closed.");
            return false;
        }

        if (!$obj->isOpen()) {
            $this->display("<b>{$obj->name}</b> is already closed.");
            return false;
        }

        if ($obj->close($this)) {
            $this->display("You closed <b>{$obj->name}</b>.");
            return true;
        }

        return false;
    }

    /**
     * Use an adventure object.
     */
    public function actUse(AdvObject $obj): bool
    {
        if (!$obj->isAccessibleFrom($this)) {
            $this->display("I can only use reachable items.");
            return false;
        }

        if ($this->world !== null) {
            $res = $this->world->fireEvent('onUse', $obj, $this, null, [], null);
            if ($res !== false && $res !== null && $res !== 0 && $res !== '0') {
                $this->display("Done!");
                return true;
            }
        }

        $this->display("Using {$obj->name} had no obvious effect.");
        return false;
    }

    /**
     * Use an adventure object with another object (e.g. key on door/trapdoor, tool on mechanism).
     */
    public function actUseWith(AdvObject $obj1, AdvObject $obj2): bool
    {
        if (!$obj1->isAccessibleFrom($this) || !$obj2->isAccessibleFrom($this)) {
            $this->display("I can only use reachable items.");
            return false;
        }

        if ($this->world !== null) {
            $res = $this->world->fireEvent('onUseWith', $obj1, $this, $obj2, [], null);
            if ($res === null && !isset($this->world->events["{$obj1->id}.onUseWith"]) && isset($this->world->events["{$obj2->id}.onUseWith"])) {
                // Symmetrical fallback if onUseWith was defined on the second object
                $res = $this->world->fireEvent('onUseWith', $obj2, $this, $obj1, [], null);
            }
            if ($res !== false && $res !== null && $res !== 0 && $res !== '0') {
                $this->display("Done!");
                return true;
            }
        }

        $this->display("Nothing happens.");
        return false;
    }

    /**
     * Search an object/container (or the character's current room if null) to reveal hidden objects.
     */
    public function actSearch(?AdvObject $targetObj = null): bool
    {
        if ($targetObj === null) {
            $targetObj = $this->getRoom() ?? $this->container;
        }

        if ($targetObj === null) {
            $this->display("I found nothing new.");
            return false;
        }

        if (!$targetObj->isAccessibleFrom($this) && $targetObj !== $this->container && $targetObj !== $this->getRoom()) {
            $this->display("I could not do that.");
            return false;
        }

        $candidates = [];
        // 1. Contained objects (items, characters)
        foreach ($targetObj->contents as $item) {
            if ($item instanceof AdvObject) {
                $candidates[] = $item;
            }
        }
        // 2. Links/ways if container is a Room
        if ($targetObj instanceof Room) {
            foreach ($targetObj->ways as $link) {
                if ($link instanceof AdvObject) {
                    $candidates[] = $link;
                }
            }
        }

        $found = 0;
        foreach ($candidates as $x) {
            if ($x->isHidden()) {
                if ($x->isAccessibleFrom($this) || ($x instanceof Link && $targetObj instanceof Room)) {
                    $ret = true;
                    if ($this->world !== null) {
                        $ret = $this->world->fireEvent('onSearch', $x, $this, '', [], true);
                    }
                    if ($ret !== false && $ret !== 0 && $ret !== '0') {
                        if (property_exists($x, 'hidden')) {
                            $x->hidden = false;
                        }
                        $x->setProperty('hidden', 0);
                        foreach ($x->properties as $k => $v) {
                            if (strcasecmp((string)$k, 'hidden') === 0) {
                                $x->properties[$k] = 0;
                            }
                        }
                        if (property_exists($x, 'visible')) {
                            $x->visible = true;
                        }
                        $x->setProperty('visible', 1);

                        $name = !empty($x->name) ? $x->name : $x->id;
                        $this->display("I found {$name}!!");
                        $found++;
                    }
                }
            }
        }

        if ($found === 0) {
            $this->display("I found nothing new.");
        }

        return true;
    }

    /**
     * Alias matching DimensioneX Java Character.objectSearch(fullId).
     */
    public function objectSearch(string|AdvObject|null $target = null): bool
    {
        if (is_string($target) && !empty($target)) {
            $obj = $this->world?->getObject($target) ?? $this->world?->contents[$target] ?? null;
            return $this->actSearch($obj);
        }
        return $this->actSearch(is_object($target) ? $target : null);
    }

    /**
     * Give an item to another character / actor.
     */
    public function actGive(AdvObject $item, Character $recipient): bool
    {
        if (!$item->isanItem()) {
            $this->display("I can only give items.");
            return false;
        }

        if (!$item->isAccessibleFrom($this)) {
            $this->display("I can't, they both should be accessible.");
            return false;
        }

        // Recipient must be in the same room (near)
        if ($this->container === null || $recipient->container !== $this->container) {
            $this->display("I can't, they both should be accessible.");
            return false;
        }

        if (property_exists($item, 'pickable') && !$item->pickable) {
            $this->display("Could not move {$item->name}");
            return false;
        }

        if ($item instanceof Item && !$recipient->canAccept($item)) {
            $this->display("Does not accept it.");
            return false;
        }

        $origCont = $item->container;

        if (!$item->move($recipient)) {
            $this->display("There's not enough room.");
            return false;
        }

        // Trigger events
        if ($this->world !== null) {
            if ($origCont !== null) {
                $this->world->fireEvent('onLooseItem', $origCont, $this, $item);
            }
            $this->world->fireEvent('whenGiven', $this, $recipient, $item);
            $this->world->fireEvent('whenReceived', $recipient, $this, $item);
            $this->world->fireEvent('whenPicked', $item, $this, $recipient);
            $this->world->fireEvent('onReceiveItem', $recipient, $this, $item);
        }

        // Notifications
        $recipient->display("{$this->name} gave me {$item->name}!!");
        $this->display("I gave {$item->name} to {$recipient->name}.");

        return true;
    }

    /**
     * Alias matching DimensioneX Java Character.itemGive(fullId, toWhoId).
     */
    public function itemGive(string|AdvObject $item, string|Character $recipient): bool
    {
        $itemObj = is_string($item) ? ($this->world?->getObject($item) ?? $this->world?->contents[$item] ?? null) : $item;
        $recipObj = is_string($recipient) ? ($this->world?->getCharacter($recipient) ?? $this->world?->contents[$recipient] ?? null) : $recipient;
        if (!($itemObj instanceof AdvObject) || !($recipObj instanceof Character)) {
            $this->display("The selected item does not exist anymore.");
            return false;
        }
        return $this->actGive($itemObj, $recipObj);
    }

    /**
     * Display a message directly to this character (e.g. system notification or speech).
     */
    public function display(string $msg): bool
    {
        // By default, NPCs log or discard messages; Player overrides this to queue to client
        if ($this->world !== null) {
            $this->world->logDebug("Msg to [{$this->id}]: {$msg}");
        }
        return true;
    }
}
