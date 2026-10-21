<?php
declare(strict_types=1);

namespace Dimx;

/**
 * Vehicle
 *
 * Implements a VEHICLE. Special case of ITEM that can carry passengers and move
 * between rooms. When driven, it forwards room navigation links to its passengers.
 */
class Vehicle extends Item
{
    public function __construct(
        ?World $world = null,
        string $name = '',
        string $id = '',
        string $description = '',
        ?string $icon = null,
        string|array $attrList = '',
        int $capacity = 10,
        int $volume = 10,
        string $defContainer = ''
    ) {
        parent::__construct(
            $world, $name, $id, $description, $icon, $attrList,
            $capacity, $volume, $defContainer
        );
        $this->hideable = false;
        $this->showPolicyPeople = 1; // Show passengers inside
    }

    public function isVehicle(): bool
    {
        return true;
    }

    /**
     * When inside a vehicle, passengers use the navigation links of the room
     * where the vehicle is currently located.
     * @return array<string, Link>
     */
    public function getLinks(): array
    {
        $room = $this->getRoom();
        if ($room !== null) {
            return $room->getLinks();
        }
        return [];
    }

    /**
     * Drive the vehicle through an exit link to another room.
     */
    public function drive(string $directionOrLinkId): bool
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

        return $this->move($destRoom);
    }
}
