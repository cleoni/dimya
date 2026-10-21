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
