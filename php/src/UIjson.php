<?php
declare(strict_types=1);

namespace Dimx;

/**
 * UIjson
 *
 * REST / JSON API presentation layer for bot and AI players.
 * Can serve as an autonomous API endpoint:
 *   - Parses commands and payload from incoming HTTP requests.
 *   - Executes actions on world objects via GameEngine.
 *   - Returns structured JSON room snapshots, inventories, occupants, exits, and action results.
 */
class UIjson extends UI
{
    public function __construct(?Player $player = null, ?GameEngine $engine = null)
    {
        $this->type = 'json';
        $this->player = $player;
        $this->engine = $engine;
    }

    /**
     * Render authentication required response.
     */
    public function renderLogin(?string $errorMessage = null, ?string $defaultUsername = null): void
    {
        $this->sendJson([
            'success' => false,
            'status'  => 'login_required',
            'error'   => $errorMessage ?? "Player authentication required. Please supply 'username' (or 'player') and 'password'.",
            'message' => $errorMessage ?? "Player authentication required. If the account does not exist, it will be created automatically.",
        ], 401);
    }

    /**
     * Render an error response in JSON.
     */
    public function renderError(string $message, int $statusCode = 400): void
    {
        $this->sendJson([
            'success' => false,
            'status'  => 'error',
            'error'   => $message,
        ], $statusCode);
    }

    /**
     * Render the game state / command outcome as a structured JSON record.
     */
    public function render(array $context): void
    {
        /** @var World $world */
        $world  = $context['world'];
        /** @var Player $player */
        $player = $context['player'];
        /** @var Room|null $room */
        $room   = $context['room'] ?? $player->getRoom();
        $lockError = (bool)($context['lockError'] ?? false);
        $loggedOut = (bool)($context['logged_out'] ?? false);

        $status = 'ok';
        if ($lockError) {
            $status = 'lock_busy';
        } elseif ($loggedOut) {
            $status = 'logged_out';
        }

        $response = [
            'success'    => !$lockError,
            'status'     => $status,
            'logged_out' => $loggedOut,
            'lock_error' => $lockError,
            'world'      => [
                'id'          => $world->id,
                'name'        => $world->name,
                'mode'        => $world->mode,
                'tick_length' => $world->tickLength,
            ],
            'player'     => $this->buildPlayerStatus($player),
            'room'       => $loggedOut ? null : $this->buildRoomSnapshot($room, $player),
            'messages'   => $context['messages'] ?? [],
            'command'    => [
                'cmd'    => $context['cmd']    ?? null,
                'param'  => $context['param']  ?? null,
                'target' => $context['target'] ?? null,
            ],
        ];

        if ($lockError) {
            $response['error'] = 'World is temporarily locked by another transaction. Please retry.';
        }

        $this->sendJson($response, $lockError ? 503 : 200);
    }

    /**
     * Build structured room snapshot with description, items, exits, and occupants.
     *
     * @return array<string, mixed>|null
     */
    public function buildRoomSnapshot(?Room $room, Player $player): ?array
    {
        if ($room === null) {
            return null;
        }

        // Room items
        $items = [];
        foreach ($room->getItems() as $item) {
            $items[] = [
                'id'          => $item->id,
                'name'        => $item->name,
                'description' => $item->evalDescription($player),
                'pickable'    => (bool)$item->pickable,
                'volume'      => $item->volume,
                'type'        => $item->type ?? '',
                'icon'        => $item->icon,
            ];
        }

        // Room occupants (other players and NPCs)
        $occupants = [];
        foreach ($room->getOccupants() as $occ) {
            if ($occ === $player) {
                continue;
            }
            $occupants[] = [
                'id'          => $occ->id,
                'name'        => $occ->name,
                'description' => $occ->evalDescription($player),
                'is_player'   => ($occ instanceof Player),
                'icon'        => $occ->icon,
            ];
        }

        // Room exits / links
        $exits = [];
        foreach ($room->getLinks() as $link) {
            $direction = $link->getDirection($room->id);
            $targetRoom = $link->getTarget($room->id);
            $exits[] = [
                'direction'        => $direction,
                'link_id'          => $link->id,
                'name'             => $link->name,
                'target_room_id'   => $targetRoom?->id,
                'target_room_name' => $targetRoom?->name,
                'open'             => $link->isOpen(),
            ];
        }

        $sceneImg = !empty($room->images['N']) ? $room->images['N']->src : null;

        return [
            'id'          => $room->id,
            'name'        => $room->name,
            'description' => $room->evalDescription($player),
            'image'       => $sceneImg,
            'items'       => $items,
            'occupants'   => $occupants,
            'exits'       => $exits,
        ];
    }

    /**
     * Build player status representation.
     *
     * @return array<string, mixed>
     */
    public function buildPlayerStatus(Player $player): array
    {
        $inventory = [];
        foreach ($player->contents as $item) {
            if ($item instanceof Item) {
                $inventory[] = [
                    'id'          => $item->id,
                    'name'        => $item->name,
                    'description' => $item->evalDescription($player),
                    'volume'      => $item->volume,
                    'type'        => $item->type ?? '',
                ];
            }
        }

        return [
            'id'             => $player->id,
            'numeric_id'     => $player->numericId,
            'is_bot'         => (bool)$player->isBot,
            'name'           => $player->name,
            'score'          => $player->score,
            'health'         => $player->getProperty('Health') ?? 100,
            'capacity'       => $player->capacity,
            'volume'         => $player->volume,
            'carrying_count' => count($player->contents),
            'inventory'      => $inventory,
        ];
    }

    /**
     * Dispatch an incoming API request using the supplied GameEngine.
     *
     * @param GameEngine $engine
     * @param array{username?: string, password?: string, cmd?: string, param?: string, target?: string}|null $input
     */
    public function handleApiRequest(GameEngine $engine, ?array $input = null): void
    {
        $this->engine = $engine;
        $params = $input ?? self::parseInput();

        if (empty($params['username'])) {
            $this->renderLogin();
            return;
        }

        $password = $params['password'] ?? '';
        if ($password === '') {
            $this->renderLogin("Password is required for player '{$params['username']}'.");
            return;
        }

        $auth = $engine->pm->authenticateOrCreatePlayer($engine->worldId, $params['username'], $password, true);
        if (!$auth['success']) {
            $this->renderLogin($auth['error'] ?? 'Authentication failed.');
            return;
        }

        $result = $engine->processCommand(
            $params['username'],
            $params['cmd'] ?? 'look',
            $params['param'] ?? '',
            $params['target'] ?? '',
            $this,
            $auth['numeric_id'] ?? null,
            true
        );

        $this->player = $result['player'] ?? null;
        if ($this->player !== null) {
            $this->player->setUI($this);
        }

        $this->render($result);
    }

    /**
     * Helper to send JSON response with appropriate headers and HTTP status code.
     */
    protected function sendJson(array $data, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }
        $encoded = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($encoded === false) {
            echo json_encode([
                'success' => false,
                'status'  => 'error',
                'error'   => 'JSON serialization error: ' . json_last_error_msg(),
            ]);
        } else {
            echo $encoded;
        }
    }
}
