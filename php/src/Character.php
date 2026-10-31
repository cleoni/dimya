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
            $this->world?->log("ERROR: container is NULL");
            return false;
        }

        // If character is inside a vehicle, driving navigates the vehicle through room exits
        if ($this->container instanceof AdvObject && $this->container->isVehicle()) {
            /** @var Vehicle $vehicle */
            $vehicle = $this->container;
            $link = $room->getWay($directionOrLinkId) ?? $room->getLinkByDirection($directionOrLinkId);
            if ($link === null) {
                $this->world?->log("ERROR: LINK {$directionOrLinkId} does not exist - (Player used REFRESH?)");
                return false;
            }
            if (!$link->isOpen()) {
                return false;
            }
            $destRoom = $link->getTarget($room->id);
            if ($destRoom === null) {
                $this->world?->log("ERROR: LINK {$directionOrLinkId} leads to NULL");
                return false;
            }
            if ($this->world !== null) {
                $resExit = $this->world->fireEvent('onExit', $vehicle, $this, $destRoom);
                if ($resExit === false || $resExit === 0 || $resExit === '0') {
                    $this->world->log("Could not move {$this->getName()} away from {$room->id}: action cancelled.");
                    return false;
                }
            }
            $walkDir = $link->getDirection($room->id);
            $moved = $vehicle->move($destRoom);
            if ($moved) {
                $this->facing = $walkDir;
                foreach ($vehicle->contents as $passenger) {
                    if ($passenger instanceof Character) {
                        $passenger->facing = $walkDir;
                    }
                }
                if ($this->world !== null) {
                    $this->world->fireEvent('onEnter', $vehicle, $this, $destRoom);
                    $this->world->fireEvent('onReceive', $destRoom, $this, $vehicle);
                }
            }
            return $moved;
        }

        $link = $room->getWay($directionOrLinkId) ?? $room->getLinkByDirection($directionOrLinkId);
        if ($link === null) {
            $this->world?->log("ERROR: LINK {$directionOrLinkId} does not exist - (Player used REFRESH?)");
            return false;
        }
        if (!$link->isOpen()) {
            return false;
        }

        $destRoom = $link->getTarget($room->id);
        if ($destRoom === null) {
            $this->world?->log("ERROR: LINK {$directionOrLinkId} leads to NULL");
            return false;
        }

        // Fire onExit and onEnter events if World is available
        if ($this->world !== null) {
            $resExit = $this->world->fireEvent('onExit', $this, $this, $destRoom);
            if ($resExit === false || $resExit === 0 || $resExit === '0') {
                $this->world->log("Could not move {$this->getName()} away from {$room->id}: action cancelled.");
                return false;
            }
        }

        $walkDir = $link->getDirection($room->id);
        $moved = $this->move($destRoom);

        if ($moved) {
            // Adjust facing - is there an image in the Link direction?
            if ($destRoom->hasSeveralFaces()) {
                if ($destRoom->getExactImage($walkDir) !== null) {
                    $this->facing = $walkDir;
                }
            } elseif (count($destRoom->images) === 1) {
                $this->facing = (string)array_key_first($destRoom->images);
            }
            if ($this->world !== null) {
                $this->world->fireEvent('onEnter', $this, $this, $destRoom);
                $this->world->fireEvent('onReceive', $destRoom, $this, $this);
            }
        }

        return $moved;
    }

    /**
     * Compute relative direction from observer viewpoint facing $facing.
     */
    public static function getRelativeDirection(string $dir, string $facing): string
    {
        $dir = strtoupper(trim($dir));
        $facing = strtoupper(trim($facing));

        if ($facing === 'N' || empty($facing) || $dir === 'U' || $dir === 'D') {
            return $dir;
        } elseif ($facing === 'S') {
            return match ($dir) {
                'N' => 'S',
                'E' => 'W',
                'S' => 'N',
                'W' => 'E',
                default => $dir,
            };
        } elseif ($facing === 'E') {
            return match ($dir) {
                'N' => 'W',
                'E' => 'N',
                'S' => 'E',
                'W' => 'S',
                default => $dir,
            };
        } elseif ($facing === 'W') {
            return match ($dir) {
                'N' => 'E',
                'E' => 'S',
                'S' => 'W',
                'W' => 'N',
                default => $dir,
            };
        }
        return $dir;
    }

    /**
     * Compute next face in clockwise ('r') or counter-clockwise ('l') rotation.
     */
    public static function nextFace(string $face, string $turn): string
    {
        $face = strtoupper(trim($face));
        $turn = strtolower(trim($turn));
        if ($turn === 'r') {
            return match ($face) {
                'N' => 'E',
                'E' => 'S',
                'S' => 'W',
                default => 'N',
            };
        } else {
            return match ($face) {
                'N' => 'W',
                'W' => 'S',
                'S' => 'E',
                default => 'N',
            };
        }
    }

    /**
     * Get opposite cardinal or vertical direction.
     */
    public static function getOppositeDirection(string $dir): string
    {
        $dir = strtoupper(trim($dir));
        return match ($dir) {
            'N' => 'S',
            'S' => 'N',
            'E' => 'W',
            'W' => 'E',
            'U' => 'D',
            'D' => 'U',
            default => '',
        };
    }

    /**
     * Rotate character orientation: 'l' (left 90°), 'r' (right 90°), or 'b' (back 180°).
     */
    public function rotate(string $direction): void
    {
        $dir = strtolower(trim($direction));
        if ($dir === 'left' || $dir === 'rotl') {
            $turn = 'l';
        } elseif ($dir === 'right' || $dir === 'rotr') {
            $turn = 'r';
        } elseif ($dir === 'back' || $dir === 'reverse' || $dir === 'rev') {
            $turn = 'b';
        } else {
            $turn = $dir;
        }

        $room = $this->getRoom();
        $origFacing = strtoupper($this->facing ?: 'N');
        $facing = $origFacing;

        if ($room !== null && $room->hasSeveralFaces()) {
            if ($turn === 'b') {
                $facing = self::getOppositeDirection($facing);
            } else {
                $facing = self::nextFace($facing, $turn);
                while ($room->getExactImage($facing) === null && $facing !== $origFacing) {
                    $facing = self::nextFace($facing, $turn);
                }
            }
        } else {
            if ($turn === 'b') {
                $facing = self::getOppositeDirection($facing);
            } else {
                $facing = self::nextFace($facing, $turn);
            }
        }

        $this->facing = $facing;
    }

    /**
     * Speak a message to the room or world.
     */
    public function say(string $msg, ?DimxObject $target = null): void
    {
        $this->world?->log("Speaking: {$msg} in room " . ($this->container?->id ?? ''));
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
            $this->display("It's locked.");
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
                $skin = $this->skin ?? $this->world?->skins['default'] ?? $this->world?->defaultSkin;
                foreach ($contents as $it) {
                    $ico = !empty($it->icon) ? $it->icon : ($skin?->getItemIcon() ?? '');
                    $iconImg = !empty($ico)
                        ? "<img src=\"" . htmlspecialchars($ico) . "\" alt=\"" . htmlspecialchars($it->name) . "\" style=\"width:20px;height:20px;object-fit:contain;vertical-align:middle;margin-right:4px;border-radius:2px\" />"
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
            $this->world?->log("itemUse: The item does not exist: {$obj->id}");
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
            $this->world?->log("One of the selected items ({$obj1->id},{$obj2->id}) does not exist");
            $this->display("I can only use reachable items.");
            return false;
        }

        if ($this->world !== null) {
            $res = $this->world->fireEvent('onUseWith', $obj1, $this, $obj2, [], false);
            if ($res === false && !isset($this->world->events["{$obj1->id}.onUseWith"]) && isset($this->world->events["{$obj2->id}.onUseWith"])) {
                // Symmetrical fallback if onUseWith was defined on the second object
                $res = $this->world->fireEvent('onUseWith', $obj2, $this, $obj1, [], false);
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
            if ($obj === null) {
                $this->world?->log("The selected item does not exist: {$target}");
                $this->display("The selected item does not exist.");
                return false;
            }
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

        if (!$item->isPickable()) {
            $this->display("Could not take with me {$item->name}");
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
     * Enter a vehicle.
     */
    public function actEnter(AdvObject $target): bool
    {
        if (!$target->isVehicle()) {
            $this->display("You can only enter vehicles.");
            return false;
        }

        if ($target->isOpenable() && !$target->isOpen()) {
            $this->display("It's closed.");
            return false;
        }

        if ($target->getFreeSpace() < $this->volume) {
            $this->display("There's not enough room.");
            return false;
        }

        $oldContainer = $this->container;
        if ($oldContainer instanceof AdvObject && $this->world !== null) {
            $res = $this->world->fireEvent('onExit', $this, $this, $target);
            if ($res === false || $res === 0 || $res === '0') {
                return false;
            }
        }

        if ($this->move($target)) {
            if ($this->world !== null) {
                $this->world->fireEvent('onEnter', $this, $this, $target);
                $this->world->fireEvent('onReceive', $target, $this, $this);
            }
            $targetDesc = (stripos($target->name, 'the ') === 0 || stripos($target->name, 'a ') === 0) ? $target->name : "the {$target->name}";
            $this->display("You enter {$targetDesc}.");
            if ($oldContainer instanceof PeopleContainer) {
                foreach ($oldContainer->getOccupants() as $occ) {
                    if ($occ !== $this) {
                        $occ->display("<b>{$this->name}</b> enters {$targetDesc}.");
                    }
                }
            }
            return true;
        }

        $this->display("I could not do that.");
        return false;
    }

    /**
     * Exit/disembark from the current vehicle into the containing room.
     */
    public function actExit(): bool
    {
        $vehicle = $this->container;
        if (!($vehicle instanceof AdvObject && $vehicle->isVehicle())) {
            $this->display("You are not inside any vehicle.");
            return false;
        }

        if ($vehicle->isOpenable() && !$vehicle->isOpen()) {
            $this->display("It's closed.");
            return false;
        }

        $destRoom = $vehicle->container;
        if (!($destRoom instanceof AdvObject)) {
            $this->display("You cannot exit right now.");
            return false;
        }

        if ($this->world !== null) {
            $res = $this->world->fireEvent('onExit', $this, $this, $destRoom);
            if ($res === false || $res === 0 || $res === '0') {
                return false;
            }
        }

        if ($this->move($destRoom)) {
            if ($this->world !== null) {
                $this->world->fireEvent('onEnter', $this, $this, $destRoom);
                $this->world->fireEvent('onReceive', $destRoom, $this, $this);
                $this->world->fireEvent('onLoose', $vehicle, $this, $this);
            }
            $vehDesc = (stripos($vehicle->name, 'the ') === 0 || stripos($vehicle->name, 'a ') === 0) ? $vehicle->name : "the {$vehicle->name}";
            $this->display("You exit {$vehDesc}.");
            if ($destRoom instanceof PeopleContainer) {
                foreach ($destRoom->getOccupants() as $occ) {
                    if ($occ !== $this) {
                        $occ->display("<b>{$this->name}</b> steps out of {$vehDesc}.");
                    }
                }
            }
            return true;
        }

        $this->display("I could not do that.");
        return false;
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

    /**
     * Generate HTML snapshot fragment for this character.
     */
    public function htmlAdminSnapshot(): string
    {
        $idEsc  = htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8');
        $strEsc = htmlspecialchars((string)$this, ENT_QUOTES, 'UTF-8');
        $contStr = 'null';
        if ($this->container !== null) {
            $contIdEsc  = htmlspecialchars($this->container->id, ENT_QUOTES, 'UTF-8');
            $contStrEsc = htmlspecialchars((string)$this->container, ENT_QUOTES, 'UTF-8');
            $contStr    = "<a href=\"#{$contIdEsc}\">{$contStrEsc}</a>";
        }

        $sb = "<li><b><a name=\"{$idEsc}\" id=\"{$idEsc}\">{$strEsc}</a></b>\n<ul>\n";
        $sb .= "<li>in {$contStr}</li>\n";
        $sb .= "<li>properties:\n<ul>\n";
        foreach ($this->getProperties() as $k => $v) {
            if (str_starts_with((string)$k, '__')) continue;
            $valStr = is_bool($v) ? ($v ? 'true' : 'false') : (is_array($v) ? json_encode($v) : (string)$v);
            $sb .= "<li><b>" . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . "</b>=" . htmlspecialchars($valStr, ENT_QUOTES, 'UTF-8') . "</li>\n";
        }
        $sb .= "<li><b>image</b>=" . $this->listImages() . "</li>\n";
        $sb .= "<li><b>showmode</b>=" . htmlspecialchars((string)$this->showmode, ENT_QUOTES, 'UTF-8') . "</li>\n";
        $acceptsStr = is_array($this->accepts) ? implode(',', $this->accepts) : (string)($this->accepts ?? '');
        $sb .= "<li><b>accepts</b>=" . htmlspecialchars($acceptsStr, ENT_QUOTES, 'UTF-8') . "</li>\n";
        $sb .= "<li><b>facing</b>=" . htmlspecialchars((string)$this->facing, ENT_QUOTES, 'UTF-8') . "</li>\n";
        $sb .= "</ul></li>\n";

        $sb .= "<li>contents:\n<ul>\n";
        foreach ($this->contents as $item) {
            $itemIdEsc  = htmlspecialchars($item->id, ENT_QUOTES, 'UTF-8');
            $itemStrEsc = htmlspecialchars((string)$item, ENT_QUOTES, 'UTF-8');
            $sb .= "<li><a href=\"#{$itemIdEsc}\">{$itemStrEsc}</a></li>\n";
        }
        $sb .= "</ul></li>\n";

        $sb .= "<li>space: free=" . $this->getFreeSpace() . "/used=" . $this->getUsedSpace() . "</li>\n";

        if ($this instanceof Player) {
            $sb .= "<li>" . count($this->messagesQueue) . " messages</li>\n";
            $sb .= "<li>panel: " . htmlspecialchars($this->panel?->id ?? 'default', ENT_QUOTES, 'UTF-8') . "</li>\n";
            $sb .= "<li>prevpanelids: " . htmlspecialchars((string)($this->prevPanelId ?? ''), ENT_QUOTES, 'UTF-8') . "</li>\n";
        }

        $sb .= "</ul></li>\n";
        return $sb;
    }
}
