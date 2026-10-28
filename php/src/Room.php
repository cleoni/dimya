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

    /**
     * Generate HTML snapshot fragment for this room.
     */
    public function htmlAdminSnapshot(): string
    {
        $idEsc  = htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8');
        $strEsc = htmlspecialchars((string)$this, ENT_QUOTES, 'UTF-8');
        $sb = "<li><b><a name=\"{$idEsc}\" id=\"{$idEsc}\">{$strEsc}</a></b>\n<ul>\n";
        $sb .= "<li>properties:\n<ul>\n";
        foreach ($this->getProperties() as $k => $v) {
            if (str_starts_with((string)$k, '__')) continue;
            $valStr = is_bool($v) ? ($v ? 'true' : 'false') : (is_array($v) ? json_encode($v) : (string)$v);
            $sb .= "<li><b>" . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . "</b>=" . htmlspecialchars($valStr, ENT_QUOTES, 'UTF-8') . "</li>\n";
        }
        $sb .= "<li><b>image</b>=" . $this->listImages() . "</li>\n";
        $sb .= "<li><b>description</b>=" . htmlspecialchars($this->description, ENT_QUOTES, 'UTF-8') . "</li>\n";
        $sb .= "</ul></li>\n";

        $panel = $this->getPanel();
        if ($panel !== null && strtolower($panel->id) !== 'default') {
            $sb .= "<li>panel: " . htmlspecialchars((string)$panel, ENT_QUOTES, 'UTF-8') . "</li>\n";
        }
        $sb .= "</ul></li>\n";
        return $sb;
    }
}
