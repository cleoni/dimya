<?php
declare(strict_types=1);

namespace Dimx;

/**
 * UIbot
 *
 * REST / JSON API presentation layer for standard bot and AI players.
 *
 * Inherits the structured JSON API capabilities from UIjson,
 * but applies player-facing uniformity:
 *  - 'is_player' omitted from room occupants (preserving DimensioneX uniformity
 *    between human players, bots, and NPCs).
 *  - 'target_room_id' and 'target_room_name' omitted from room exits (preventing
 *    premature knowledge of unseen target rooms).
 *  - 'panel' structure replaced by top-level 'commands' list.
 *  - 'world' section displays only 'name' and 'tick_length'.
 *  - 'player' structure replaced by 'stats' structure, showing only capitalized attributes
 *    defined in ATTRLIST (matching UIhtml STATS panel).
 */
class UIbot extends UIjson
{
    public function __construct(?Player $player = null, ?GameEngine $engine = null)
    {
        parent::__construct($player, $engine);
        $this->type = 'bot';
    }

    /**
     * Build player stats representation for bots, exposing only attributes
     * beginning with an uppercase letter (matching UIhtml STATS box).
     *
     * @return array<string, mixed>
     */
    public function buildPlayerStats(Player $player): array
    {
        $stats = [];
        foreach ($player->getProperties() as $key => $val) {
            if (is_string($key) && $key !== '' && $key[0] >= 'A' && $key[0] <= 'Z') {
                $stats[$key] = $val;
            }
        }
        return $stats;
    }

    /**
     * Build bot-specific response payload:
     *  - Filters 'world' to only 'name' and 'tick_length'.
     *  - Replaces internal 'panel' structure with a simplified top-level 'commands' list.
     *  - Replaces 'player' structure with 'stats' (capitalized attributes only).
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function buildResponsePayload(
        array $context,
        World $world,
        Player $player,
        ?Room $room,
        ?Panel $panel,
        string $status,
        bool $loggedOut,
        bool $lockError
    ): array {
        $payload = parent::buildResponsePayload($context, $world, $player, $room, $panel, $status, $loggedOut, $lockError);

        $commands = $payload['panel']['commands'] ?? ($panel?->getAvailableCommands() ?? []);
        if (!in_array('popmsg', $commands, true)) {
            $commands[] = 'popmsg';
        }

        $botPayload = [];
        foreach ($payload as $key => $value) {
            if ($key === 'world') {
                $botPayload['world'] = [
                    'name'        => $world->name,
                    'tick_length' => $world->tickLength,
                ];
            } elseif ($key === 'panel') {
                $botPayload['commands'] = $commands;
            } elseif ($key === 'player') {
                $botPayload['stats'] = $this->buildPlayerStats($player);
                $botPayload['facing'] = $player->facing ?? 'N';
            } else {
                $botPayload[$key] = $value;
            }
        }

        return $botPayload;
    }

    /**
     * Build single occupant snapshot without revealing whether character is a player,
     * omitting UI presentation properties (such as 'showmode'),
     * hiding premature character description and null icons, and exposing images.
     *
     * @return array<string, mixed>
     */
    protected function buildOccupantSnapshot(Character $occ, Player $player): array
    {
        $data = [
            'id'   => $occ->id,
            'name' => $occ->name,
        ];

        if ($occ->icon !== null && $occ->icon !== '') {
            $data['icon'] = $occ->icon;
        }

        $img = $occ->images[$occ->facing] ?? ($occ->images['N'] ?? (reset($occ->images) ?: null));
        if ($img instanceof Image && !empty($img->src)) {
            $data['image'] = [
                'url'    => $img->src,
                'width'  => $img->width,
                'height' => $img->height,
            ];
        }

        return $data;
    }

    /**
     * Build single item snapshot record for standard bots.
     * Omits internal physical properties ('pickable', 'volume', 'type')
     * so bots must attempt interaction in order to discover them.
     *
     * @return array<string, mixed>
     */
    protected function buildItemSnapshot(Item $item, Player $player): array
    {
        $data = [
            'id'          => $item->id,
            'name'        => $item->name,
            'description' => $item->evalDescription($player),
        ];

        if ($item->isOpenable()) {
            $data['open'] = $item->isOpen();
        }
        if ($item->container instanceof AdvObject && $item->container->isanItem()) {
            $data['container_id'] = $item->container->id;
        }

        if ($item->icon !== null && $item->icon !== '') {
            $data['icon'] = $item->icon;
        }

        $img = $item->images[$item->facing] ?? ($item->images['N'] ?? (reset($item->images) ?: null));
        if ($img instanceof Image && !empty($img->src)) {
            $data['image'] = [
                'url'    => $img->src,
                'width'  => $img->width,
                'height' => $img->height,
            ];
        }

        return $data;
    }

    /**
     * Build exit snapshot record without revealing target room information.
     *
     * @return array<string, mixed>
     */
    protected function buildExitSnapshot(Link $link, Room $room, Player $player): array
    {
        $direction = $link->getDirection($room->id);

        return [
            'direction' => $direction,
            'link_id'   => $link->id,
            'name'      => $link->name,
            'open'      => $link->isOpen(),
        ];
    }

    /**
     * Build structured room snapshot for bots, filtering out hidden items,
     * occupants, and exits.
     *
     * @return array<string, mixed>|null
     */
    public function buildRoomSnapshot(?Room $room, Player $player): ?array
    {
        if ($room === null) {
            return null;
        }

        $isInsideVehicle = ($player->container instanceof AdvObject && $player->container->isVehicle());
        $activeContainer = $isInsideVehicle ? $player->container : $room;

        // Room items (excluding hidden objects, including accessible items inside open containers)
        $items = [];
        foreach ($activeContainer->getItems() as $item) {
            if ($item->isHidden()) {
                continue;
            }
            $items[] = $this->buildItemSnapshot($item, $player);
            if ($item->isOpen() && !$item->isVehicle()) {
                $this->collectVisibleContents($item, $player, $items);
            }
        }

        // Room occupants (excluding hidden and self)
        $occupants = [];
        foreach ($activeContainer->getOccupants() as $occ) {
            if ($occ === $player || $occ->isHidden()) {
                continue;
            }
            $occupants[] = $this->buildOccupantSnapshot($occ, $player);
        }

        // Room exits / links (excluding hidden) - no room exits when inside vehicle
        $exits = [];
        if (!$isInsideVehicle) {
            foreach ($room->getLinks() as $link) {
                if ($link->isHidden()) {
                    continue;
                }
                $exits[] = $this->buildExitSnapshot($link, $room, $player);
            }
        }

        $imgObj = $room->getImageForFacing($player->facing ?? 'N');
        $sceneImg = $imgObj ? $imgObj->src : null;

        return [
            'id'                => $activeContainer->id,
            'name'              => $activeContainer->name,
            'description'       => $activeContainer->evalDescription($player),
            'image'             => ($isInsideVehicle && !empty($activeContainer->innerImage?->src)) ? $activeContainer->innerImage->src : $sceneImg,
            'facing'            => $player->facing ?? 'N',
            'has_several_faces' => !$isInsideVehicle && $room->hasSeveralFaces(),
            'items'             => $items,
            'occupants'         => $occupants,
            'exits'             => $exits,
        ];
    }

    /**
     * Recursively collect items inside open containers, skipping hidden items.
     *
     * @param array<int, array<string, mixed>> $items
     */
    protected function collectVisibleContents(AdvObject $container, Player $player, array &$items): void
    {
        foreach ($container->getContents(true) as $contained) {
            if ($contained instanceof Item) {
                if ($contained->isHidden()) {
                    continue;
                }
                $items[] = $this->buildItemSnapshot($contained, $player);
                if ($contained->isOpen()) {
                    $this->collectVisibleContents($contained, $player, $items);
                }
            }
        }
    }
}
