<?php
declare(strict_types=1);

namespace Dimx;

/**
 * Link
 *
 * Implements a connection or passage between rooms (doors, gates, paths, portals).
 * Supports bidirectional routing, cardinal/relative directions, and open/locked state.
 */
class Link extends AdvObject
{
    /** @var array<string, Room> Mapping of starting room ID to destination Room object */
    public array $fromto = [];

    public string $direction = 'N';
    public bool $isBidirectional = true;
    public bool $open = true;
    public bool $openable = true;
    public bool $locked = false;

    public function __construct(
        ?World $world = null,
        string $name = '',
        string $id = '',
        ?Room $fromRoom = null,
        ?Room $toRoom = null,
        bool $bidirectional = true,
        string $direction = 'N',
        string $description = '',
        ?string $icon = null,
        string|array $attrList = ''
    ) {
        parent::__construct(
            $world, $name, $id, $description, $icon, $attrList,
            0, 0, true, ''
        );
        $this->direction = strtoupper(trim($direction));
        $this->isBidirectional = $bidirectional;

        if ($fromRoom !== null && $toRoom !== null) {
            $this->fromto[$fromRoom->id] = $toRoom;
            if ($this->isBidirectional) {
                $this->fromto[$toRoom->id] = $fromRoom;
            }
            $fromRoom->addLink($this);
            if ($this->isBidirectional) {
                $toRoom->addLink($this);
            }
        }

        // Apply open/locked state from attributes if set
        if ($this->varExists('open')) {
            $this->open = (bool)$this->getProperty('open');
        }
        if ($this->varExists('locked')) {
            $this->locked = (bool)$this->getProperty('locked');
        }
    }

    public function isLink(): bool
    {
        return true;
    }

    /**
     * Get exit direction from room with ID $fromId.
     * Reverses direction if coming from destination side of bidirectional link.
     */
    public function getDirection(string $fromId): string
    {
        $keys = array_keys($this->fromto);
        if (!empty($keys) && $keys[0] === $fromId) {
            return $this->direction;
        }
        return self::getOppositeDirection($this->direction);
    }

    /**
     * Get target room reached when traversing link from room $fromId.
     */
    public function getTarget(string $fromId): ?Room
    {
        return $this->fromto[$fromId] ?? null;
    }

    /**
     * Check if passage is currently open for travel.
     */
    public function isOpen(): bool
    {
        return $this->open && !$this->locked;
    }

    public function unlock(): bool
    {
        $this->locked = false;
        $this->open = true;
        return true;
    }

    public function lock(): bool
    {
        $this->locked = true;
        $this->open = false;
        return true;
    }

    /**
     * Helper to compute opposite cardinal orientation.
     */
    public static function getOppositeDirection(string $dir): string
    {
        return match (strtoupper(trim($dir))) {
            'N' => 'S',
            'S' => 'N',
            'E' => 'W',
            'W' => 'E',
            'U' => 'D',
            'D' => 'U',
            default => 'N',
        };
    }
}
