<?php
declare(strict_types=1);

namespace Dimx;

/**
 * Item
 *
 * Represents an interactive game item. Can be held in player inventory, placed in rooms,
 * or stored inside other containers. Supports DimX type.subtype hierarchy.
 */
class Item extends PeopleContainer
{
    public string $type = 'generic';
    public bool $pickable = false;
    public bool $hideable = true;
    public ?Image $innerImage = null;
    public ?Image $zoomImage = null;
    public int $showPolicyPeople = 0; // 0=OFFSCREEN (icons), 1=ONSCREEN (drawn in scene)

    public function __construct(
        ?World $world = null,
        string $name = '',
        string $id = '',
        string $description = '',
        ?string $icon = null,
        string|array $attrList = '',
        int $capacity = 5,
        int $volume = 1,
        string $defContainer = ''
    ) {
        parent::__construct(
            $world, $name, $id, $description, $icon, $attrList,
            $capacity, $volume, true, $defContainer
        );
        //$this->showmode = self::SHOW_ICON;
    }

    public function isanItem(): bool
    {
        return true;
    }

    public function isVehicle(): bool
    {
        return false;
    }

    /**
     * Extracts main type from 'type.subtype' string (e.g. 'weapon' from 'weapon.sword').
     */
    public function getMainType(): string
    {
        $parts = explode('.', $this->type, 2);
        return $parts[0] ?? $this->type;
    }

    /**
     * Extracts subtype from 'type.subtype' string (e.g. 'sword' from 'weapon.sword').
     */
    public function getSubtype(): string
    {
        $parts = explode('.', $this->type, 2);
        return $parts[1] ?? '';
    }

    /**
     * Checks if this item matches a given type query (e.g. 'weapon' matches 'weapon.sword').
     */
    public function matchesType(string $typeQuery): bool
    {
        $typeQuery = strtolower(trim($typeQuery));
        $myType = strtolower($this->type);

        if ($myType === $typeQuery) {
            return true;
        }

        return str_starts_with($myType, $typeQuery . '.');
    }

    /**
     * Attempts to restore current item to the specified player upon login/restore.
     */
    public function restoreTo(Player $player, ?World $targetWorld = null): bool
    {
        $w = $this->world ?? $targetWorld;
        if ($w === null) {
            return false;
        }

        $w->log("Trying to restore item: {$this->id}");

        // Check if item is already in someone's inventory or locked container
        if ($this->container instanceof Player && $this->container !== $player) {
            // Already owned by someone else
            return false;
        }

        return $this->move($player);
    }

    /**
     * Move item to another World (e.g. multi-area/clustering).
     */
    public function worldChange(World $toWorld, string $newId, ?string $defContainer = null): void
    {
        if ($this->container !== null) {
            $this->container->removeObject($this);
        }
        $this->id = $newId;
        $this->world = $toWorld;
        $this->defContainer = $defContainer ?? '';

        if (!empty($defContainer)) {
            $target = $toWorld->getRoom($defContainer) ?? $toWorld->contents[$defContainer] ?? null;
            if ($target instanceof AdvObject) {
                $target->addObject($this);
            }
        }
    }

    /**
     * Generate HTML snapshot fragment for this item.
     */
    public function htmlAdminSnapshot(): string
    {
        $idEsc  = htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8');
        $strEsc = htmlspecialchars((string)$this, ENT_QUOTES, 'UTF-8');
        $contStr = 'NULL';
        if ($this->container !== null) {
            $contIdEsc  = htmlspecialchars($this->container->id, ENT_QUOTES, 'UTF-8');
            $contStrEsc = htmlspecialchars((string)$this->container, ENT_QUOTES, 'UTF-8');
            $contStr    = "<a href=\"#{$contIdEsc}\">{$contStrEsc}</a>";
        }

        $veh = $this->isVehicle() ? 'VEHICLE ' : '';
        $sb = "<li>" . $veh . "<a name=\"{$idEsc}\" id=\"{$idEsc}\"><b>{$strEsc}</b></a> in {$contStr}\n<ul>\n";
        $sb .= "<li>properties:\n<ul>\n";
        foreach ($this->getProperties() as $k => $v) {
            if (str_starts_with((string)$k, '__')) continue;
            $valStr = is_bool($v) ? ($v ? 'true' : 'false') : (is_array($v) ? json_encode($v) : (string)$v);
            $sb .= "<li><b>" . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . "</b>=" . htmlspecialchars($valStr, ENT_QUOTES, 'UTF-8') . "</li>\n";
        }
        $sb .= "<li><b>capacity</b>=" . $this->capacity . "</li>\n";
        $sb .= "<li><b>volume</b>=" . $this->volume . "</li>\n";
        $sb .= "<li><b>container</b>=" . $contStr . "</li>\n";
        $sb .= "<li><b>showmode</b>=" . htmlspecialchars((string)$this->showmode, ENT_QUOTES, 'UTF-8') . "</li>\n";
        $sb .= "<li><b>icon</b>=" . htmlspecialchars((string)($this->icon ?? ''), ENT_QUOTES, 'UTF-8') . "</li>\n";
        $sb .= "<li><b>image</b>=" . $this->listImages() . "</li>\n";
        $sb .= "<li><b>world</b>=" . htmlspecialchars($this->world?->name ?? '', ENT_QUOTES, 'UTF-8') . "</li>\n";
        $sb .= "</ul></li>\n";
        $sb .= "</ul></li>\n";
        return $sb;
    }
}
