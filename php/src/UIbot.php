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
            } else {
                $botPayload[$key] = $value;
            }
        }

        return $botPayload;
    }

    /**
     * Build single occupant snapshot without revealing whether character is a player,
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
}
