<?php
declare(strict_types=1);

namespace Dimx;

/**
 * UIbot
 *
 * REST / JSON API presentation layer for standard bot and AI players.
 *
 * Inherits the full structured JSON API capabilities from UIjson,
 * but omits:
 *  - 'is_player' from room occupants (preserving DimensioneX uniformity
 *    between human players, bots, and NPCs).
 *  - 'target_room_id' and 'target_room_name' from room exits (preventing
 *    premature knowledge of unseen target rooms).
 *  - 'panel' structure (simplifying output to a top-level 'commands' list
 *    so panel internals like panel id remain hidden).
 *  - 'id', 'numeric_id', 'is_bot', 'capacity', 'volume', and 'carrying_count'
 *    from the 'player' object (keeping player status clean and simple).
 */
class UIbot extends UIjson
{
    public function __construct(?Player $player = null, ?GameEngine $engine = null)
    {
        parent::__construct($player, $engine);
        $this->type = 'bot';
    }

    /**
     * Build player status representation for bots without exposing internal
     * IDs, bot flags, or capacity/volume constraints.
     *
     * @return array<string, mixed>
     */
    public function buildPlayerStatus(Player $player): array
    {
        $status = parent::buildPlayerStatus($player);

        unset(
            $status['id'],
            $status['numeric_id'],
            $status['is_bot'],
            $status['capacity'],
            $status['volume'],
            $status['carrying_count']
        );

        return $status;
    }

    /**
     * Build bot-specific response payload:
     * Replaces the internal 'panel' structure with a simplified top-level 'commands' list.
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

        // Replace 'panel' key with 'commands' at the same position in payload
        $botPayload = [];
        foreach ($payload as $key => $value) {
            if ($key === 'panel') {
                $botPayload['commands'] = $commands;
            } else {
                $botPayload[$key] = $value;
            }
        }

        return $botPayload;
    }

    /**
     * Build single occupant snapshot without revealing whether character is a player.
     *
     * @return array<string, mixed>
     */
    protected function buildOccupantSnapshot(Character $occ, Player $player): array
    {
        return [
            'id'          => $occ->id,
            'name'        => $occ->name,
            'description' => $occ->evalDescription($player),
            'icon'        => $occ->icon,
        ];
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
