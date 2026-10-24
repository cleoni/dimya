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

    protected bool $hasReturned = false;
    protected mixed $returnValue = true;

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

        while ($i < $count && !$this->hasReturned) {
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
                    if ($this->hasReturned) {
                        return $this->returnValue;
                    }
                }
                continue;
            }

            // Normal single statement execution
            $result = $this->executeStatement($line);
            if ($this->hasReturned) {
                return $this->returnValue;
            }
            $i++;
        }

        return $this->hasReturned ? $this->returnValue : $result;
    }

    /**
     * Execute a single instruction statement.
     */
    protected function executeStatement(string $line): mixed
    {
        // Check for Return statement (e.g. "Return 0", "Return False", "Return $OWNER.Health > 0")
        if (preg_match('/^RETURN(\s+(.*))?$/i', $line, $m)) {
            $expr = trim($m[2] ?? '');
            $val = ($expr === '') ? true : $this->evalCondition($expr);
            $this->hasReturned = true;
            $this->returnValue = $val;
            return $val;
        }

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
            'RETURN'    => $this->actReturn($args),
            'OPEN'      => $this->actOpen($args),
            'CLOSE'     => $this->actClose($args),
            default     => true,
        };
    }

    public function actOpen(array $args): bool
    {
        if (empty($args)) {
            return false;
        }
        $targetName = $args[0];
        $obj = $this->resolveObject($targetName);
        if ($obj instanceof AdvObject) {
            $agent = ($this->agent instanceof Character) ? $this->agent : null;
            if ($agent !== null) {
                return $agent->actOpen($obj);
            }
            return $obj->open(null);
        }
        return false;
    }

    public function actClose(array $args): bool
    {
        if (empty($args)) {
            return false;
        }
        $targetName = $args[0];
        $obj = $this->resolveObject($targetName);
        if ($obj instanceof AdvObject) {
            $agent = ($this->agent instanceof Character) ? $this->agent : null;
            if ($agent !== null) {
                return $agent->actClose($obj);
            }
            return $obj->close(null);
        }
        return false;
    }

    protected function actReturn(array $args): mixed
    {
        $val = !empty($args) ? $this->evalCondition(implode(' ', $args)) : true;
        $this->hasReturned = true;
        $this->returnValue = $val;
        return $val;
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
     * Speak [speaker,] [destination,] msg1 [, msg2, ...]
     * As per DimensioneX ActionsRunner semantics & Developer's Reference §3.9.29:
     * - If destination is omitted, default destination is $AGENT.
     * - If speaker is omitted, default speaker is $OWNER.
     * - Only a Character (NPC or Player) can speak. If the speaker is not a Character
     *   (e.g. an Item, Room, or null), the speaker defaults to the world's default
     *   character ("a voice...", id "SYS").
     * - Phrases cycle sequentially across multiple calls for the recipient, wrapping around.
     * - A phrase "*" signals onSpeechFinish without outputting text to chat.
     */
    protected function actSpeak(array $args): bool
    {
        if (empty($args)) {
            return false;
        }

        $isLiteral = function(mixed $s): bool {
            if (!is_string($s)) {
                return true;
            }
            $t = trim($s);
            if ($t === '') {
                return true;
            }
            return str_starts_with($t, '"') || str_starts_with($t, "'") || is_numeric($t);
        };

        $resolveObj = function(mixed $s): ?DimxObject {
            if (!is_string($s)) {
                return ($s instanceof DimxObject) ? $s : null;
            }
            $t = trim($s);
            $upper = strtoupper($t);
            if ($upper === '$WORLD' || $upper === 'WORLD') {
                return $this->world;
            }
            if ($upper === '$AGENT' || $upper === 'AGENT') {
                return ($this->agent instanceof DimxObject) ? $this->agent : null;
            }
            if ($upper === '$OWNER' || $upper === 'OWNER') {
                return ($this->owner instanceof DimxObject) ? $this->owner : null;
            }
            if ($upper === '$TARGET' || $upper === 'TARGET') {
                return ($this->target instanceof DimxObject) ? $this->target : null;
            }
            if ($upper === 'SYS') {
                return $this->world->getDefaultCharacter();
            }
            return $this->world->getObjectExt($t);
        };

        $p0 = $args[0] ?? null;
        $p0Obj = (!$isLiteral($p0)) ? $resolveObj($p0) : null;

        if ($p0Obj === null) {
            // Compact syntax: Speak msg1 [, msg2, ...]
            // Default speaker is $OWNER, default destination is $AGENT
            $speakerObj = $this->owner;
            $destination = ($this->agent instanceof DimxObject) ? $this->agent : $this->world;
            $messages = $args;
        } else {
            // p0 is an object ID/reference. Check p1:
            $p1 = $args[1] ?? null;
            $p1Obj = ($p1 !== null && !$isLiteral($p1)) ? $resolveObj($p1) : null;

            if ($p1Obj === null) {
                // DimensioneX ActionsRunner.java:
                // "p0 is an id - assume it is dest"
                // "from is missing - insert default speaker"
                $speakerObj = $this->owner;
                $destination = $p0Obj;
                $messages = array_slice($args, 1);
            } else {
                // Explicit speaker and destination: Speak speaker, destination, msg1 [, msg2, ...]
                $speakerObj = $p0Obj;
                $destination = $p1Obj;
                $messages = array_slice($args, 2);
            }
        }

        if (empty($messages)) {
            return false;
        }

        // Validate speaker: only a Character (or Player) can speak!
        // Inanimate objects (Item, Room, etc.) cannot speak; fallback to world default character ("a voice...")
        if (!($speakerObj instanceof Character)) {
            $speakerObj = $this->world->getDefaultCharacter();
        }
        $speaker = ($speakerObj instanceof ManageableObject && !empty($speakerObj->name))
            ? $speakerObj->name
            : 'a voice...';

        // Sequential message selection across dialogue interactions (stored on destination target)
        $msgKey = '__act_speak_' . md5(implode('||', $messages));
        $lastIdx = ($destination instanceof DimxObject) ? $destination->getProperty($msgKey) : null;
        $nextIdx = ($lastIdx !== null && is_numeric($lastIdx)) ? ((int)$lastIdx + 1) : 0;
        if ($nextIdx >= count($messages)) {
            $nextIdx = 0;
        }
        if ($destination instanceof DimxObject) {
            $destination->setProperty($msgKey, $nextIdx);
        }

        $chosen = (string)$this->evalValue((string)$messages[$nextIdx]);
        $chosen = trim($chosen, "\"'");

        // Special marker "*": signals speech finished
        if ($chosen === '*') {
            $destId = ($destination instanceof DimxObject) ? $destination->id : '';
            $this->world->fireEvent('onSpeechFinish', $speakerObj, $destination, $destId);
            return true;
        }

        // Determine recipient name: if destination is $AGENT or a Player/Character, deliver privately
        $recipientName = null;
        if ($destination instanceof Player) {
            $recipientName = $destination->name;
        } elseif ($destination instanceof Character) {
            $recipientName = $destination->name;
        } elseif ($destination === null && $this->agent instanceof Player) {
            $recipientName = $this->agent->name;
        }

        // If destination is explicitly World, broadcast to all
        if ($destination instanceof World) {
            $recipientName = null;
        }

        // Spoken messages are inserted into the chat messages queue for the target
        $this->world->postChatMessage($speaker, $chosen, $recipientName);

        // Fire onHear on destination if applicable (avoid cascading onHear)
        static $speaking = false;
        if (!$speaking) {
            $speaking = true;
            try {
                if ($destination instanceof ManageableObject && $destination !== $speakerObj) {
                    $this->world->fireEvent('onHear', $destination, $speakerObj, $chosen);
                } elseif ($destination instanceof Room) {
                    foreach ($destination->getOccupants() as $occ) {
                        if ($occ !== $speakerObj) {
                            $this->world->fireEvent('onHear', $occ, $speakerObj, $chosen);
                        }
                    }
                }
            } finally {
                $speaking = false;
            }
        }

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
        if ($upper === '$OWNER') {
            if ($this->owner instanceof DimxObject) {
                return $this->owner;
            }
            if (is_string($this->owner) && $this->owner !== '') {
                return $this->world->getObjectExt($this->owner);
            }
            return null;
        }
        if ($upper === '$AGENT') {
            if ($this->agent instanceof DimxObject) {
                return $this->agent;
            }
            if (is_string($this->agent) && $this->agent !== '') {
                return $this->world->getObjectExt($this->agent);
            }
            return null;
        }
        if ($upper === '$TARGET') {
            if ($this->target instanceof DimxObject) {
                return $this->target;
            }
            if (is_string($this->target) && $this->target !== '') {
                return $this->world->getObjectExt($this->target);
            }
            return null;
        }

        return $this->world->getObjectExt($name);
    }

    /**
     * Evaluate condition expression like "$WORLD.mode <> 1" or "open = 0"
     */
    protected function evalCondition(string $expr): mixed
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

        return $this->evalValue($expr);
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

        if (str_starts_with($val, "'") && str_ends_with($val, "'") && strlen($val) >= 2) {
            return substr($val, 1, -1);
        }

        if (is_numeric($val)) {
            return str_contains($val, '.') ? (float)$val : (int)$val;
        }

        $upper = strtoupper($val);
        if ($upper === 'TRUE') {
            return true;
        }
        if ($upper === 'FALSE') {
            return false;
        }
        if ($upper === 'NULL') {
            return null;
        }

        // Special system variables: $WORLD, $OWNER, $AGENT, $TARGET
        if ($upper === '$WORLD') {
            return $this->world->id;
        }
        if ($upper === '$OWNER') {
            if ($this->owner instanceof DimxObject) {
                return $this->owner->id ?? $this->owner->name;
            }
            return (string)($this->owner ?? '');
        }
        if ($upper === '$AGENT') {
            if ($this->agent instanceof DimxObject) {
                return $this->agent->id ?? $this->agent->name;
            }
            return (string)($this->agent ?? '');
        }
        if ($upper === '$TARGET') {
            if ($this->target instanceof DimxObject) {
                return $this->target->id ?? $this->target->name;
            }
            return (string)($this->target ?? '');
        }

        // Property access e.g. "$WORLD.mode", "$OWNER.Health", "box.open"
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

        // Simple binary arithmetic: left [+-*/] right (e.g. "$OWNER.Health + 0.2")
        if (preg_match('/^(.+?)\s*([\+\-\*\/])\s*(.+)$/', $val, $am)) {
            $left = $this->evalValue($am[1]);
            $op = $am[2];
            $right = $this->evalValue($am[3]);
            if (is_numeric($left) && is_numeric($right)) {
                return match ($op) {
                    '+' => $left + $right,
                    '-' => $left - $right,
                    '*' => $left * $right,
                    '/' => $right != 0 ? $left / $right : 0,
                    default => $val,
                };
            }
        }

        return $val;
    }
}
