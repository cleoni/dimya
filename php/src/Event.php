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

    /** @var callable|null PHP closure or callable executing the event */
    protected $handler = null;

    public function __construct(string $id, string $type = 'EVENT', ?callable $handler = null, array $formalParams = [])
    {
        parent::__construct();
        $this->id = $id;
        $this->type = strtoupper($type);
        $this->handler = $handler;
        $this->formalParams = $formalParams;
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
        $props = ['id', 'type', 'attachedEventsId', 'formalParams', 'actions', 'properties'];
        if ($this->handler !== null && !($this->handler instanceof \Closure)) {
            $props[] = 'handler';
        }
        return $props;
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
        if ($this->handler !== null) {
            return ($this->handler)($owner, $agent, $target, $input);
        }
        if (!empty($this->actions)) {
            $world = ($owner instanceof World)
                ? $owner
                : (($owner instanceof DimxObject && $owner->world instanceof World)
                    ? $owner->world
                    : $worldFallback);
            if ($world instanceof World) {
                $runner = new ActionsRunner($world, $owner, $agent, $target, $input);
                return $runner->run($this->actions);
            }
        }
        return $defaultResult;
    }
}
