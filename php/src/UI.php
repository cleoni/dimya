<?php
declare(strict_types=1);

namespace Dimx;

/**
 * UI
 *
 * Abstract base class for DimensioneX player presentation layers.
 * Concrete implementations:
 *   - UIhtml: Human browser presentation with HTML, images, and panels.
 *   - UIjson: Bot / AI API endpoint with structured JSON snapshots and command execution.
 */
abstract class UI
{
    protected ?Player $player = null;
    protected ?GameEngine $engine = null;
    protected string $type = 'generic';

    /**
     * Get the UI type identifier ('html' or 'json').
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Bind the player instance to this UI.
     */
    public function setPlayer(?Player $player): void
    {
        $this->player = $player;
    }

    /**
     * Get the bound player instance.
     */
    public function getPlayer(): ?Player
    {
        return $this->player;
    }

    /**
     * Bind the game engine to this UI.
     */
    public function setEngine(?GameEngine $engine): void
    {
        $this->engine = $engine;
    }

    /**
     * Get the bound game engine.
     */
    public function getEngine(): ?GameEngine
    {
        return $this->engine;
    }

    /**
     * Render the active player/world view.
     *
     * @param array{
     *   world: World,
     *   player: Player,
     *   room?: ?Room,
     *   messages: array<string>,
     *   lockError?: bool,
     *   storageLabel?: string,
     *   worldId?: string
     * } $context
     */
    abstract public function render(array $context): void;

    /**
     * Render a login screen or authentication prompt.
     */
    abstract public function renderLogin(?string $errorMessage = null, ?string $defaultUsername = null, ?string $selectedSkin = null): void;

    /**
     * Render an error message.
     */
    abstract public function renderError(string $message, int $statusCode = 400): void;

    /**
     * Detect whether the incoming request is from a bot (JSON) or a human browser (HTML).
     */
    public static function detectClientType(): string
    {
        // 1. Explicit query or post parameters
        $format = strtolower((string)($_GET['format'] ?? $_POST['format'] ?? ''));
        $ui     = strtolower((string)($_GET['ui']     ?? $_POST['ui']     ?? ''));
        $bot    = $_GET['bot'] ?? $_POST['bot'] ?? null;
        $api    = $_GET['api'] ?? $_POST['api'] ?? null;

        if ($format === 'json' || $ui === 'json' || $format === 'bot' || $ui === 'bot') {
            return 'json';
        }
        if ($bot !== null && $bot !== '' && $bot !== '0' && $bot !== 'false') {
            return 'json';
        }
        if ($api !== null && $api !== '' && $api !== '0' && $api !== 'false') {
            return 'json';
        }

        // 2. HTTP Content-Type header (e.g. application/json POST body)
        $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
        if (stripos($contentType, 'application/json') !== false) {
            return 'json';
        }

        // 3. HTTP Accept header
        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        if (stripos($accept, 'application/json') !== false && stripos($accept, 'text/html') === false) {
            return 'json';
        }

        return 'html';
    }

    /**
     * Parse and normalize input parameters from GET, POST, or JSON body.
     * The game world is single per installation and cannot be specified by the client.
     *
     * @return array{
     *   username: string,
     *   password: string,
     *   cmd: string,
     *   arg0: string,
     *   arg1: string,
     *   param: string,
     *   target: string,
     *   reset: bool
     * }
     */
    public static function parseInput(): array
    {
        $data = [];

        // Parse JSON body if present
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $data = $json;
            }
        }

        // Merge standard request arrays (POST, then GET take priority for URL overrides)
        $merged = array_merge($data, $_POST, $_GET);

        // Extract normalized fields
        $username = trim((string)($merged['username'] ?? $merged['player'] ?? $merged['player_id'] ?? ''));
        $password = trim((string)($merged['password'] ?? $merged['pass'] ?? ''));
        $rawCmd   = $merged['cmd'] ?? $merged['command'] ?? $merged['action'] ?? null;
        $arg0     = trim((string)($merged['arg0'] ?? $merged['param'] ?? $merged['direction'] ?? $merged['dir'] ?? $merged['value'] ?? $merged['msgid'] ?? $merged['item1'] ?? ''));
        $arg1     = trim((string)($merged['arg1'] ?? $merged['target'] ?? $merged['target_id'] ?? $merged['arg2'] ?? $merged['item2'] ?? $merged['target2'] ?? $merged['item'] ?? $merged['with'] ?? ''));

        if (($rawCmd === null || trim((string)$rawCmd) === '') && isset($merged['txtBox']) && trim((string)$merged['txtBox']) !== '') {
            $cmd = 'say';
            if ($arg0 === '') {
                $arg0 = trim((string)$merged['txtBox']);
            }
            if ($arg1 === '') {
                $arg1 = '*';
            }
        } else {
            $cmd = ($rawCmd !== null && trim((string)$rawCmd) !== '') ? strtolower(trim((string)$rawCmd)) : 'login';
        }

        // If command is say and txtBox is present but arg0 is empty
        if ($cmd === 'say') {
            if ($arg0 === '' && isset($merged['txtBox']) && trim((string)$merged['txtBox']) !== '') {
                $arg0 = trim((string)$merged['txtBox']);
            }
            if ($arg1 === '') {
                $arg1 = '*';
            }
        } elseif ($arg0 === '' && $arg1 !== '') {
            // When just 1 parameter is passed, arg0 has to be the argument that gets filled
            $arg0 = $arg1;
            $arg1 = '';
        }
        $skin     = trim((string)($merged['skin'] ?? ''));
        $reset    = isset($merged['reset']) && ($merged['reset'] === '1' || $merged['reset'] === 1 || $merged['reset'] === 'true' || $merged['reset'] === true);

        $hasSounds = array_key_exists('sounds', $merged) || array_key_exists('audio', $merged);
        $rawSounds = $merged['sounds'] ?? $merged['audio'] ?? null;
        $sounds    = ($rawSounds === '1' || $rawSounds === 1 || $rawSounds === 'true' || $rawSounds === true || $rawSounds === 'on');

        $hasMusic = array_key_exists('music', $merged);
        $rawMusic = $merged['music'] ?? null;
        $music    = ($rawMusic === '1' || $rawMusic === 1 || $rawMusic === 'true' || $rawMusic === true || $rawMusic === 'on');

        $view = trim((string)($merged['view'] ?? 'scene'));
        if ($view === '') {
            $view = 'scene';
        }

        return [
            'username'   => $username,
            'password'   => $password,
            'cmd'        => $cmd,
            'arg0'       => $arg0,
            'arg1'       => $arg1,
            'param'      => $arg0,
            'target'     => $arg1,
            'view'       => $view,
            'skin'       => $skin,
            'sounds'     => $sounds,
            'has_sounds' => $hasSounds,
            'music'      => $music,
            'has_music'  => $hasMusic,
            'reset'      => $reset,
            'raw'        => $merged,
        ];
    }
}
