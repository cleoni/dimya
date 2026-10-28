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

    public function setProperty(string $name, mixed $value): mixed
    {
        $res = parent::setProperty($name, $value);
        $lower = strtolower($name);
        if ($lower === 'open') {
            $this->open = (bool)$value;
        } elseif ($lower === 'locked') {
            $this->locked = (bool)$value;
        }
        return $res;
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
     * Check if this link leads to the specified room ID.
     */
    public function leadsTo(string $roomId): bool
    {
        foreach ($this->fromto as $fromId => $toRoom) {
            if ($toRoom instanceof Room && $toRoom->id === $roomId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if this link starts from the specified room ID.
     */
    public function startsFrom(string $roomId): bool
    {
        return isset($this->fromto[$roomId]);
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

    /**
     * Convert direction code (e.g. 'N', 'S', 'U', 'D') to extended title-cased name.
     */
    public static function getExtendedDirectionName(string $dir): string
    {
        $dirUpper = strtoupper(trim($dir));
        $names = [
            'N'          => 'North',
            'S'          => 'South',
            'E'          => 'East',
            'W'          => 'West',
            'NE'         => 'Northeast',
            'NW'         => 'Northwest',
            'SE'         => 'Southeast',
            'SW'         => 'Southwest',
            'NORTH'      => 'North',
            'SOUTH'      => 'South',
            'EAST'       => 'East',
            'WEST'       => 'West',
            'NORTHEAST'  => 'Northeast',
            'NORTHWEST'  => 'Northwest',
            'SOUTHEAST'  => 'Southeast',
            'SOUTHWEST'  => 'Southwest',
            'NORTH-EAST' => 'Northeast',
            'NORTH-WEST' => 'Northwest',
            'SOUTH-EAST' => 'Southeast',
            'SOUTH-WEST' => 'Southwest',
            'U'          => 'Up',
            'UP'         => 'Up',
            'D'          => 'Down',
            'DOWN'       => 'Down',
            'IN'         => 'In',
            'OUT'        => 'Out',
        ];

        return $names[$dirUpper] ?? ucfirst(strtolower($dir));
    }

    /**
     * Resolve user-facing display name for navigation.
     * If the link has no explicit name (or defaults to its ID), return extended direction name.
     */
    public function getDisplayName(?string $fromRoomId = null): string
    {
        $hasCustomName = !empty($this->name) && $this->name !== $this->id && $this->name !== '*';
        if ($hasCustomName) {
            return $this->name;
        }

        $dir = ($fromRoomId !== null) ? $this->getDirection($fromRoomId) : $this->direction;
        return self::getExtendedDirectionName($dir);
    }

    public function __toString(): string
    {
        $sb = $this->id . ' (' . $this->name . ') {';
        $conns = [];
        foreach ($this->fromto as $fromId => $toRoom) {
            $toId = $toRoom instanceof Room ? $toRoom->id : (string)$toRoom;
            $dir = $this->getDirection((string)$fromId);
            $conns[] = "{$fromId}-- {$dir} -->{$toId}";
        }
        $sb .= implode('; ', $conns) . '}';
        return $sb;
    }

    /**
     * Generate HTML snapshot fragment for this link.
     */
    public function htmlAdminSnapshot(): string
    {
        $sb = "<li> " . htmlspecialchars((string)$this, ENT_QUOTES, 'UTF-8') . "\n<ul>\n";
        $sb .= "<li>properties:\n<ul>\n";
        foreach ($this->getProperties() as $k => $v) {
            if (str_starts_with((string)$k, '__')) continue;
            $valStr = is_bool($v) ? ($v ? 'true' : 'false') : (is_array($v) ? json_encode($v) : (string)$v);
            $sb .= "<li><b>" . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . "</b>=" . htmlspecialchars($valStr, ENT_QUOTES, 'UTF-8') . "</li>\n";
        }
        $sb .= "<li><b>image</b>=" . $this->listImages() . "</li>\n";
        $sb .= "<li><b>showmode</b>=" . htmlspecialchars((string)$this->showmode, ENT_QUOTES, 'UTF-8') . "</li>\n";
        $sb .= "</ul></li>\n";
        $sb .= "</ul></li>\n";
        return $sb;
    }
}
