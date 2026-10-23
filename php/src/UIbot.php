<?php
declare(strict_types=1);

namespace Dimx;

/**
 * UIbot
 *
 * REST / JSON API presentation layer for standard bot and AI players.
 *
 * Inherits the full structured JSON API capabilities from UIjson,
 * but omits the 'is_player' diagnostic field from room occupants.
 * This preserves the DimensioneX design where human players, bots,
 * and game-controlled characters (NPCs) appear uniformly as "People"
 * and cannot be distinguished by clients.
 */
class UIbot extends UIjson
{
    public function __construct(?Player $player = null, ?GameEngine $engine = null)
    {
        parent::__construct($player, $engine);
        $this->type = 'bot';
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
}
