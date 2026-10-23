<?php
declare(strict_types=1);

namespace Dimx;

/**
 * ActionsRunner
 *
 * Evaluates and executes game event actions and DimensioneX script instructions
 * (SetPanel, Speak, Broadcast, Move, Goal, property assignments, If/End_If).
 */
class ActionsRunner
{
    protected World $world;
    protected ?DimxObject $owner = null;
    protected mixed $agent = null;
    protected mixed $target = null;
    protected array $input = [];

    public function __construct(
        World $world,
        ?DimxObject $owner = null,
        mixed $agent = null,
        mixed $target = null,
        array $input = []
    ) {
        $this->world = $world;
        $this->owner = $owner;
        $this->agent = $agent;
        $this->target = $target;
        $this->input = $input;
    }

    /**
     * Run a multi-line script string.
     */
    public function run(string $script): mixed
    {
        $rawLines = preg_split('/\r\n|\r|\n/', $script);
        $lines = [];
        foreach ($rawLines as $l) {
            $l = trim($l);
            if ($l === '' || str_starts_with($l, "'")) {
                continue;
            }
            $lines[] = $l;
        }

        $count = count($lines);
        $i = 0;
        $result = true;

        while ($i < $count) {
            $line = $lines[$i];

            // Check for If block
            if (preg_match('/^IF\b\s*(.*)$/i', $line, $m)) {
                $condExpr = trim($m[1]);
                $condVal = $this->evalCondition($condExpr);

                // Collect block up to End_If
                $thenLines = [];
                $elseLines = [];
                $inElse = false;
                $nesting = 1;
                $i++;

                while ($i < $count) {
                    $sub = $lines[$i];
                    if (preg_match('/^IF\b/i', $sub)) {
                        $nesting++;
                    } elseif (preg_match('/^(END_IF|END\s+IF|ENDIF)\b/i', $sub)) {
                        $nesting--;
                        if ($nesting === 0) {
                            $i++;
                            break;
                        }
                    } elseif ($nesting === 1 && preg_match('/^ELSE\b/i', $sub)) {
                        $inElse = true;
                        $i++;
                        continue;
                    }

                    if ($inElse) {
                        $elseLines[] = $sub;
                    } else {
                        $thenLines[] = $sub;
                    }
                    $i++;
                }

                $execLines = $condVal ? $thenLines : $elseLines;
                if (!empty($execLines)) {
                    $subScript = implode("\n", $execLines);
                    $result = $this->run($subScript);
                }
                continue;
            }

            // Normal single statement execution
            $result = $this->executeStatement($line);
            $i++;
        }

        return $result;
    }

    /**
     * Execute a single instruction statement.
     */
    protected function executeStatement(string $line): mixed
    {
        // 1. Property assignment e.g. "$WORLD.mode = 1" or "box.open = 0"
        if (preg_match('/^(\$?[A-Za-z0-9_]+)\.([A-Za-z0-9_]+)\s*=\s*(.*)$/', $line, $m)) {
            $targetName = $m[1];
            $propName   = $m[2];
            $rawVal     = trim($m[3]);
            $val        = $this->evalValue($rawVal);

            $obj = $this->resolveObject($targetName);
            if ($obj !== null) {
                if ($obj instanceof World && property_exists($obj, $propName)) {
                    $obj->{$propName} = $val;
                } elseif (property_exists($obj, $propName)) {
                    $obj->{$propName} = $val;
                } else {
                    $obj->setProperty($propName, $val);
                }
            }
            return true;
        }

        // Tokenize by verb and argument list
        $parts = preg_split('/\s+/', $line, 2);
        $verb = strtoupper($parts[0] ?? '');
        $argString = $parts[1] ?? '';
        $args = $this->parseArguments($argString);

        return match ($verb) {
            'SETPANEL'  => $this->actSetPanel($args),
            'SPEAK'     => $this->actSpeak($args),
            'BROADCAST' => $this->actBroadcast($args),
            'MOVE'      => $this->actMove($args),
            'GOAL'      => $this->actGoal($args),
            default     => true,
        };
    }

    /**
     * SetPanel [destination,] panelId
     */
    protected function actSetPanel(array $args): bool
    {
        if (count($args) === 1) {
            $target = $this->world;
            $panelId = (string)$args[0];
        } else {
            $targetObj = $this->resolveObject((string)($args[0] ?? ''));
            $target = $targetObj ?? $this->world;
            $panelId = (string)($args[1] ?? '');
        }

        $panelId = trim($panelId, "\"' \t");
        if ($target instanceof World || $target instanceof PeopleContainer || $target instanceof Player) {
            return $target->setPanel($panelId);
        }
        return false;
    }

    /**
     * Speak msg1 [, msg2, ...]
     * Picks one random message if multiple provided, and outputs to agent.
     */
    protected function actSpeak(array $args): bool
    {
        if (empty($args)) {
            return false;
        }

        // Random selection if multiple messages provided (dialogue variation)
        $chosen = $args[array_rand($args)];
        $chosen = (string)$this->evalValue((string)$chosen);

        $speaker = ($this->owner instanceof ManageableObject) ? $this->owner->name : 'Someone';
        $message = "<b>{$speaker}</b>: {$chosen}";

        if ($this->agent instanceof Player) {
            $this->agent->display($message);
            return true;
        }

        // Fallback: broadcast to current room
        $room = ($this->owner instanceof AdvObject) ? $this->owner->getRoom() : null;
        if ($room !== null) {
            $room->display($message);
            return true;
        }

        $this->world->broadcast($message);
        return true;
    }

    /**
     * Broadcast message to all connected players in the world.
     */
    protected function actBroadcast(array $args): bool
    {
        $rawMsg = $args[0] ?? '';
        $msg = (string)$this->evalValue((string)$rawMsg);
        return $this->world->broadcast($msg);
    }

    /**
     * Move what, where
     */
    protected function actMove(array $args): bool
    {
        if (count($args) < 2) {
            return false;
        }

        $whatObj = $this->resolveObject((string)$args[0]);
        $whereObj = $this->resolveObject((string)$args[1]);

        if ($whatObj instanceof AdvObject && $whereObj !== null) {
            return $whatObj->move($whereObj);
        }

        return false;
    }

    /**
     * Goal [message]
     */
    protected function actGoal(array $args): bool
    {
        $agentName = ($this->agent instanceof ManageableObject) ? $this->agent->name : 'A player';
        if ($this->agent instanceof Player) {
            $this->agent->score += 10;
        }

        $msg = !empty($args[0])
            ? (string)$this->evalValue((string)$args[0])
            : "<b>{$agentName}</b> has reached the goal!";

        return $this->world->broadcast($msg);
    }

    /**
     * Parse comma-separated arguments preserving quoted strings.
     */
    protected function parseArguments(string $str): array
    {
        $str = trim($str);
        if ($str === '') {
            return [];
        }

        $args = [];
        $current = '';
        $inQuotes = false;
        $len = strlen($str);

        for ($i = 0; $i < $len; $i++) {
            $char = $str[$i];
            if ($char === '"') {
                $inQuotes = !$inQuotes;
                $current .= $char;
            } elseif ($char === ',' && !$inQuotes) {
                $args[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }
        if (trim($current) !== '') {
            $args[] = trim($current);
        }

        return array_map(function ($arg) {
            if (str_starts_with($arg, '"') && str_ends_with($arg, '"') && strlen($arg) >= 2) {
                return substr($arg, 1, -1);
            }
            return $arg;
        }, $args);
    }

    /**
     * Resolve object identifier like "$WORLD", "surfer", "box", "apple", "$AGENT", etc.
     */
    protected function resolveObject(string $name): ?DimxObject
    {
        $name = trim($name);
        $upper = strtoupper($name);

        if ($upper === '$WORLD' || $upper === 'WORLD') {
            return $this->world;
        }
        if ($upper === '$OWNER' && $this->owner instanceof DimxObject) {
            return $this->owner;
        }
        if ($upper === '$AGENT' && $this->agent instanceof DimxObject) {
            return $this->agent;
        }
        if ($upper === '$TARGET' && $this->target instanceof DimxObject) {
            return $this->target;
        }

        return $this->world->contents[$name]
            ?? $this->world->rooms[$name]
            ?? $this->world->items[$name]
            ?? $this->world->characters[$name]
            ?? null;
    }

    /**
     * Evaluate condition expression like "$WORLD.mode <> 1" or "open = 0"
     */
    protected function evalCondition(string $expr): bool
    {
        $expr = trim($expr);

        // Check operators: <>, <=, >=, =, <, >
        $operators = ['<>', '<=', '>=', '=', '<', '>'];
        foreach ($operators as $op) {
            if (str_contains($expr, $op)) {
                $parts = explode($op, $expr, 2);
                $left  = $this->evalValue(trim($parts[0]));
                $right = $this->evalValue(trim($parts[1]));

                return match ($op) {
                    '<>' => ($left != $right),
                    '='  => ($left == $right),
                    '<=' => ($left <= $right),
                    '>=' => ($left >= $right),
                    '<'  => ($left < $right),
                    '>'  => ($left > $right),
                    default => false,
                };
            }
        }

        return (bool)$this->evalValue($expr);
    }

    /**
     * Evaluate expression or literal into a PHP scalar value.
     */
    protected function evalValue(string $val): mixed
    {
        $val = trim($val);

        if (str_starts_with($val, '"') && str_ends_with($val, '"') && strlen($val) >= 2) {
            return substr($val, 1, -1);
        }

        if (is_numeric($val)) {
            return str_contains($val, '.') ? (float)$val : (int)$val;
        }

        // Property access e.g. "$WORLD.mode" or "box.open"
        if (preg_match('/^(\$?[A-Za-z0-9_]+)\.([A-Za-z0-9_]+)$/', $val, $m)) {
            $obj = $this->resolveObject($m[1]);
            $prop = $m[2];
            if ($obj !== null) {
                if (property_exists($obj, $prop)) {
                    return $obj->{$prop};
                }
                return $obj->getProperty($prop);
            }
            return null;
        }

        return $val;
    }
}
