<?php
declare(strict_types=1);

namespace Dimx;

/**
 * PeopleContainer
 *
 * Base class for objects capable of containing people (Rooms and Vehicles/Items).
 * Provides occupant discovery, message broadcast to contained players, and UI panel binding.
 */
class PeopleContainer extends AdvObject
{
    public ?Panel $panel = null;

    public function __construct(
        ?World $world = null,
        string $name = '',
        string $id = '',
        string $description = '',
        ?string $icon = null,
        string|array $attrList = '',
        int $capacity = 1000,
        int $volume = 0,
        bool $setDefShowpos = false,
        string $defContainer = ''
    ) {
        parent::__construct(
            $world, $name, $id, $description, $icon, $attrList,
            $capacity, $volume, $setDefShowpos, $defContainer
        );
    }

    /**
     * Broadcasts a message to all characters/players inside this container.
     */
    public function display(string $msg): bool
    {
        if ($this->world !== null) {
            $this->world->logDebug("Displaying '{$msg}' in container {$this->id}");
        }

        foreach ($this->contents as $obj) {
            if ($obj instanceof Character) {
                $obj->display($msg);
            }
        }
        return true;
    }

    /**
     * Retrieve current UI panel.
     */
    public function getPanel(): ?Panel
    {
        return $this->panel;
    }

    /**
     * Set active UI command panel.
     */
    public function setPanel(?Panel $panel): void
    {
        $this->panel = $panel;
    }

    /**
     * Returns all Character and Player objects currently inside this container.
     * @return array<string, Character>
     */
    public function getOccupants(): array
    {
        $occupants = [];
        foreach ($this->contents as $id => $obj) {
            if ($obj instanceof Character) {
                $occupants[$id] = $obj;
            }
        }
        return $occupants;
    }

    /**
     * Returns all Item objects currently inside this container.
     * @return array<string, Item>
     */
    public function getItems(): array
    {
        $items = [];
        foreach ($this->contents as $id => $obj) {
            if ($obj instanceof Item && !$obj->isVehicle()) {
                $items[$id] = $obj;
            }
        }
        return $items;
    }
}
