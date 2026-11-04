<?php
declare(strict_types=1);

namespace Dimx;

/**
 * Event
 *
 * Encapsulates game events, functions, and subroutines (EVENT, FUNCTION, SUB).
 * Supports execution with the three standard DimensioneX roles ($OWNER, $AGENT, $TARGET)
 * and an associative array of input parameters.
 */
class Event extends ManageableObject
{
    public string $id = '';
    public string $type = 'EVENT'; // 'EVENT', 'FUNCTION', 'SUB'
    public ?string $attachedEventsId = null;
    public array $formalParams = [];
    public ?string $actions = null;
    public string $fileName = '';
    public string $fileShort = '';
    public int $actionsStartLine = 0;

    /** @var callable|null PHP closure or callable executing the event */
    protected $handler = null;

    public function __construct(
        string $id,
        string $type = 'EVENT',
        ?callable $handler = null,
        array $formalParams = [],
        string $fileName = '',
        string $fileShort = '',
        int $actionsStartLine = 0
    ) {
        parent::__construct();
        $this->id = $id;
        $this->type = strtoupper($type);
        $this->handler = $handler;
        $this->formalParams = $formalParams;
        $this->fileName = $fileName;
        $this->fileShort = $fileShort;
        $this->actionsStartLine = $actionsStartLine;
    }

    public function setHandler(callable $handler): void
    {
        $this->handler = $handler;
    }

    /**
     * Closures cannot be serialized in standard PHP; exclude handler if it is a Closure.
     */
    public function __sleep(): array
    {
        $props = ['id', 'type', 'attachedEventsId', 'formalParams', 'actions', 'fileName', 'fileShort', 'actionsStartLine', 'properties'];
        if ($this->handler !== null && !($this->handler instanceof \Closure)) {
            $props[] = 'handler';
        }
        return $props;
    }

    /**
     * Format a varspace array into DimensioneX format: {key1=val1, key2=val2}
     */
    public static function formatVarspace(array $varspace, ?World $world = null): string
    {
        $parts = [];
        foreach ($varspace as $k => $v) {
            $parts[] = $k . '=' . self::formatVarspaceValue($v, $world);
        }
        return '{' . implode(', ', $parts) . '}';
    }

    /**
     * Format a single variable value into DimensioneX Token representation.
     */
    public static function formatVarspaceValue(mixed $v, ?World $world = null): string
    {
        if ($v instanceof DimxObject) {
            $namePart = $v->name !== '' ? " ({$v->name})" : '';
            return "(O|{$v->id}{$namePart}|0.0)";
        }
        if (is_string($v) && $world !== null) {
            $resolved = $world->getObjectExt($v);
            if ($resolved instanceof DimxObject) {
                $namePart = $resolved->name !== '' ? " ({$resolved->name})" : '';
                return "(O|{$resolved->id}{$namePart}|0.0)";
            }
        }
        if (is_array($v)) {
            $inner = [];
            foreach ($v as $ik => $iv) {
                if (is_int($ik)) {
                    $inner[] = self::formatVarspaceValue($iv, $world);
                } else {
                    $inner[] = $ik . '=' . self::formatVarspaceValue($iv, $world);
                }
            }
            return '{' . implode(', ', $inner) . '}';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if ($v === null) {
            return 'null';
        }
        return (string)$v;
    }

    /**
     * Trigger and execute the event handler.
     *
     * @param DimxObject|null $owner The object owning the event ($OWNER)
     * @param mixed $agent The object/character initiating the action ($AGENT)
     * @param mixed $target The secondary destination or target ($TARGET)
     * @param array<string, mixed> $input Input parameters from user or command ($input)
     * @param mixed $defaultResult Value returned if no handler is registered
     * @return mixed
     */
    public function fire(
        ?DimxObject $owner = null,
        mixed $agent = null,
        mixed $target = null,
        array $input = [],
        mixed $defaultResult = true,
        ?World $worldFallback = null
    ): mixed {
        $world = ($owner instanceof World)
            ? $owner
            : (($owner instanceof DimxObject && $owner->world instanceof World)
                ? $owner->world
                : $worldFallback);
        if ($world === null && GameEngine::$activeInstance !== null) {
            $world = GameEngine::$activeInstance->world;
        }

        // Separate positional arguments from associative input dictionary
        $cleanInput = [];
        $posArgs = [];
        foreach ($input as $k => $v) {
            if (is_int($k)) {
                $posArgs[$k] = $v;
            } else {
                $cleanInput[$k] = $v;
            }
        }

        // Build varspace (local variables for formal parameters + input)
        $varspace = ['input' => $cleanInput];
        if (!empty($this->formalParams)) {
            $idx = 0;
            foreach ($this->formalParams as $paramName) {
                if (array_key_exists($paramName, $cleanInput)) {
                    $varspace[$paramName] = $cleanInput[$paramName];
                } elseif (array_key_exists($idx, $posArgs)) {
                    $varspace[$paramName] = $posArgs[$idx];
                } elseif (array_key_exists($idx, $input)) {
                    $varspace[$paramName] = $input[$idx];
                } else {
                    $varspace[$paramName] = null;
                }
                $idx++;
            }
        }

        // Trace event/function execution using standard DimensioneX format:
        // "Calling FUNCTION addPlayer(person) - demoturnbased.dxw(117) with varspace: {input={}, person=(O|_p0 (Cris)|0.0)}"
        // Or for events: "EVENT onLook executing"
        $varspaceStr = self::formatVarspace($varspace, $world);
        if ($this->type === 'EVENT' || str_starts_with($this->id, 'on')) {
            $callMessage = "EVENT {$this->id} executing";
        } else {
            $callMessage = "Calling " . (string)$this . " with varspace: " . $varspaceStr;
        }
        if ($world instanceof World) {
            $world->log($callMessage);
        } elseif (GameEngine::$activeInstance !== null) {
            GameEngine::$activeInstance->log($callMessage);
        }

        if ($world instanceof World) {
            $world->incIndent();
        } elseif (GameEngine::$activeInstance !== null) {
            GameEngine::$activeInstance->incIndent();
        }

        $res = null;
        try {
            if ($this->handler !== null) {
                $res = ($this->handler)($owner, $agent, $target, $input, $defaultResult);
            } elseif (!empty($this->actions)) {
                $runner = new ActionsRunner(
                    $world,
                    $owner,
                    $agent,
                    $target,
                    $input,
                    $defaultResult,
                    $this->fileShort,
                    $this->actionsStartLine,
                    $this->fileName,
                    0
                );
                foreach ($varspace as $k => $v) {
                    $runner->setLocalVar($k, $v);
                }
                $res = $runner->run($this->actions);
            } else {
                $res = $defaultResult;
            }
            return $res;
        } finally {
            if ($world instanceof World) {
                $world->decIndent();
            } elseif (GameEngine::$activeInstance !== null) {
                GameEngine::$activeInstance->decIndent();
            }
            $returnVal = $res ?? $defaultResult;
            $returnStr = self::formatVarspaceValue($returnVal, $world);
            $retMessage = "Returning: " . $returnStr;
            if ($world instanceof World) {
                $world->log($retMessage);
            } elseif (GameEngine::$activeInstance !== null) {
                GameEngine::$activeInstance->log($retMessage);
            }
        }
    }

    public function __toString(): string
    {
        $ret = $this->type . ' ' . $this->id;
        if (!empty($this->formalParams)) {
            $ret .= '(' . implode(',', $this->formalParams) . ')';
        }
        if ($this->fileShort !== '' && $this->actionsStartLine > 0) {
            $ret .= ' - ' . $this->fileShort . '(' . $this->actionsStartLine . ')';
        }
        return $ret;
    }

    /**
     * Generate HTML snapshot fragment for this event.
     */
    public function htmlAdminSnapshot(): string
    {
        return "<li> " . htmlspecialchars((string)$this, ENT_QUOTES, 'UTF-8') . "</li>\n";
    }
}
