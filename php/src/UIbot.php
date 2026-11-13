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
    /**
     * Whether this UIbot instance is HTML-tolerant.
     * When false (default), HTML tags are stripped from #console output before sending to the client.
     * When true, HTML tags in #console output are preserved.
     */
    protected bool $htmlTolerant = false;

    /** @var bool|null Tracks whether htmlTolerant was explicitly modified during the current request */
    protected ?bool $explicitHtmlTolerant = null;

    public function __construct(?Player $player = null, ?GameEngine $engine = null)
    {
        parent::__construct($player, $engine);
        $this->type = 'bot';
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['dimx_html_tolerant'])) {
            $this->htmlTolerant = (bool)$_SESSION['dimx_html_tolerant'];
        }
    }

    public function __sleep(): array
    {
        return array_merge(parent::__sleep(), ['htmlTolerant']);
    }

    public function isHtmlTolerant(): bool
    {
        return $this->htmlTolerant;
    }

    public function getExplicitHtmlTolerant(): ?bool
    {
        return $this->explicitHtmlTolerant;
    }

    public function setHtmlTolerant(bool $htmlTolerant, bool $explicit = true): void
    {
        $this->htmlTolerant = $htmlTolerant;
        if ($explicit) {
            $this->explicitHtmlTolerant = $htmlTolerant;
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['dimx_html_tolerant'] = $htmlTolerant;
            }
        }
        if ($this->player !== null && $this->player->ui instanceof self && $this->player->ui !== $this) {
            $this->player->ui->htmlTolerant = $htmlTolerant;
        }
    }

    /**
     * Parse a mixed value ('on', 'off', '1', '0', 'true', 'false', bool) into a boolean or null.
     */
    public static function parseHtmlTolerantFlag(mixed $val): ?bool
    {
        if (is_bool($val)) {
            return $val;
        }
        if (is_int($val)) {
            return $val !== 0;
        }
        if (is_string($val)) {
            $v = strtolower(trim($val));
            if (in_array($v, ['1', 'true', 'on', 'yes', 'enable', 'enabled'], true)) {
                return true;
            }
            if (in_array($v, ['0', 'false', 'off', 'no', 'disable', 'disabled'], true)) {
                return false;
            }
        }
        return null;
    }

    /**
     * Intercept UIbot-specific calls (such as toggling htmlTolerant on or off)
     * before dispatching to GameEngine.
     *
     * Supported command calls:
     *   - {"cmd": "html", "param": "on"|"off"|"1"|"0"|"true"|"false"|"toggle"}
     *   - {"cmd": "html_tolerant", "param": "on"|"off"|...}
     *   - {"cmd": "/html", "param": "on"|"off"|...}
     * Also supports inline payload flag:
     *   - {"html_tolerant": true|false}
     *
     * @param array<string, mixed> $input Normalized input from UI::parseInput()
     * @return bool True if the request was a standalone UIbot call and was fully handled/rendered
     */
    public function interceptCall(
        array $input,
        ?GameEngine $engine = null,
        ?string $playerName = null,
        ?int $numericId = null,
        ?string $sessionEngineId = null
    ): bool {
        $raw = is_array($input['raw'] ?? null) ? $input['raw'] : $input;

        if (array_key_exists('html_tolerant', $raw) || array_key_exists('htmlTolerant', $raw)) {
            $rawVal = $raw['html_tolerant'] ?? $raw['htmlTolerant'];
            $parsed = self::parseHtmlTolerantFlag($rawVal);
            if ($parsed !== null) {
                $this->setHtmlTolerant($parsed, true);
            }
        }

        $cmd  = strtolower(trim((string)($input['cmd'] ?? '')));
        $arg0 = strtolower(trim((string)($input['arg0'] ?? $input['param'] ?? '')));
        $arg1 = trim((string)($input['arg1'] ?? $input['target'] ?? ''));

        if (!in_array($cmd, ['html', '/html', 'html_tolerant', 'htmltolerant'], true)) {
            return false;
        }

        $engine = $engine ?? $this->engine;
        $world  = $engine !== null ? $engine->loadOrSeedWorld(false) : new World('main', 'DimensioneX');
        $player = null;

        if ($engine !== null && $playerName !== null && $playerName !== '') {
            $player = $engine->ensurePlayer(
                $world,
                $playerName,
                $this,
                $numericId,
                true,
                false,
                $sessionEngineId
            );
        } elseif ($this->player !== null) {
            $player = $this->player;
        } else {
            $player = new Player($world, $playerName ?: 'Bot', 'p_bot');
        }

        $parsed = self::parseHtmlTolerantFlag($arg0);
        if ($arg0 === 'toggle') {
            $parsed = !$this->htmlTolerant;
        }

        if ($parsed !== null) {
            $this->setHtmlTolerant($parsed, true);
            $statusStr = $this->htmlTolerant ? 'ON' : 'OFF';
            $msg = "UIbot HTML tolerance set to {$statusStr}.";
        } else {
            $statusStr = $this->htmlTolerant ? 'ON' : 'OFF';
            $msg = "UIbot HTML tolerance is currently {$statusStr}. Use 'html on' or 'html off'.";
        }

        $player->setUI($this);
        $this->setPlayer($player);

        if ($engine !== null && $engine->pm !== null) {
            $engine->pm->saveWorld($world);
        }

        $this->render([
            'world'        => $world,
            'player'       => $player,
            'room'         => $player->getRoom(),
            'messages'     => [$msg],
            'chat'         => $player->getMessages(),
            'cmd'          => 'html',
            'arg0'         => $arg0,
            'arg1'         => $arg1,
            'param'        => $arg0,
            'target'       => $arg1,
            'logged_out'   => false,
            'lockError'    => false,
            'is_new_login' => false,
            'reset'        => false,
        ]);

        return true;
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
        if (!in_array('html', $commands, true)) {
            $commands[] = 'html';
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
            } elseif ($key === 'messages' || $key === 'console') {
                $console = is_array($value) ? $value : [];
                if (!$this->htmlTolerant) {
                    $console = array_values(array_map(
                        static fn($msg): string => strip_tags((string)$msg),
                        $console
                    ));
                }
                $botPayload['console'] = $console;
            } elseif ($key === 'chat' || $key === 'msgs') {
                $botPayload['msgs'] = $value;
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
