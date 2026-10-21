<?php
declare(strict_types=1);

namespace Dimx;

/**
 * Room
 *
 * Represents a virtual location/scene in the game world.
 * Holds exits/ways to other rooms, coordinates on the world map, background imagery,
 * weather overlay effects, and occupants.
 */
class Room extends PeopleContainer
{
    /** @var array<string, Link> Links/ways exiting from this room */
    public array $ways = [];

    public int $mapx = 0;
    public int $mapy = 0;
    public ?string $map = null;
    public ?Image $effect = null;

    public function __construct(
        ?World $world = null,
        string $name = '',
        string $id = '',
        string $description = '',
        ?string $icon = null,
        string|array $attrList = ''
    ) {
        parent::__construct(
            $world, $name, $id, $description, $icon, $attrList,
            30000, 0, false, ''
        );
    }

    public function isaRoom(): bool
    {
        return true;
    }

    public function getRoom(): Room
    {
        return $this;
    }

    /**
     * Connect an exit link to this room.
     */
    public function addLink(Link $link): void
    {
        $this->ways[$link->id] = $link;
    }

    /**
     * Get a link by ID.
     */
    public function getWay(string $wayId): ?Link
    {
        return $this->ways[$wayId] ?? null;
    }

    /**
     * Get all outgoing links.
     * @return array<string, Link>
     */
    public function getLinks(): array
    {
        return $this->ways;
    }

    /**
     * Get link towards a specific direction ('N', 'S', 'E', 'W', 'U', 'D').
     */
    public function getLinkByDirection(string $direction): ?Link
    {
        $dirUpper = strtoupper($direction);
        foreach ($this->ways as $link) {
            if (strtoupper($link->getDirection($this->id)) === $dirUpper) {
                return $link;
            }
        }
        return null;
    }
}
