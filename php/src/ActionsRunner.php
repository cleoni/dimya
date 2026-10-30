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
    protected mixed $defaultResult = true;

    public function __construct(
        World $world,
        ?DimxObject $owner = null,
        mixed $agent = null,
        mixed $target = null,
        array $input = [],
        mixed $defaultResult = true
    ) {
        $this->world = $world;
        $this->owner = $owner;
        $this->agent = $agent;
        $this->target = $target;
        $this->input = $input;
        $this->defaultResult = $defaultResult;
    }

    /**
     * Run a multi-line script string.
     */
    public function run(string $script): mixed
    {
        $rawLines = preg_split('/\r\n|\r|\n/', $script);
        $lines = [];
        $accum = '';
        $inQuote = false;

        foreach ($rawLines as $raw) {
            if (!$inQuote) {
                $trimmed = trim($raw);
                if ($trimmed === '' || str_starts_with($trimmed, "'")) {
                    continue;
                }
            }

            $len = strlen($raw);
            $lineContent = '';
            for ($ci = 0; $ci < $len; $ci++) {
                $ch = $raw[$ci];
                if ($ch === '"') {
                    $inQuote = !$inQuote;
                    $lineContent .= $ch;
                } elseif ($ch === "'" && !$inQuote) {
                    break;
                } else {
                    $lineContent .= $ch;
                }
            }

            if ($accum === '') {
                $accum = $lineContent;
            } else {
                $accum .= "\n" . $lineContent;
            }

            if (!$inQuote) {
                $trimmedAccum = trim($accum);
                if ($trimmedAccum !== '') {
                    $lines[] = $trimmedAccum;
                }
                $accum = '';
            }
        }
        if ($accum !== '') {
            $trimmedAccum = trim($accum);
            if ($trimmedAccum !== '') {
                $lines[] = $trimmedAccum;
            }
        }

        $count = count($lines);
        $i = 0;
        $result = $this->defaultResult;

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
                    $subRes = $this->run($subScript);
                    if ($this->hasReturned) {
                        return $this->returnValue;
                    }
                    if ($subRes !== false && $subRes !== null && $subRes !== 0 && $subRes !== '0') {
                        $result = true;
                    }
                }
                continue;
            }

            // Normal single statement execution
            $stmtRes = $this->executeStatement($line);
            if ($this->hasReturned) {
                return $this->returnValue;
            }
            if ($stmtRes !== false && $stmtRes !== null && $stmtRes !== 0 && $stmtRes !== '0') {
                $result = true;
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
                $obj->setProperty($propName, $val);
                if (property_exists($obj, $propName)) {
                    if (is_bool($obj->{$propName})) {
                        $obj->{$propName} = (bool)$val;
                    } elseif (is_int($obj->{$propName})) {
                        $obj->{$propName} = (int)$val;
                    } elseif (is_float($obj->{$propName})) {
                        $obj->{$propName} = (float)$val;
                    } else {
                        $obj->{$propName} = $val;
                    }
                }
            }
            return true;
        }

        // 1b. Property with key assignment e.g. "pathgate.image("E") = "pathgateeo.jpg""
        if (preg_match('/^(\$?[A-Za-z0-9_]+)\.([A-Za-z0-9_]+)\s*\(\s*["\']?([^"\']+)["\']?\s*\)\s*=\s*(.*)$/', $line, $m)) {
            $targetName = $m[1];
            $propName   = $m[2];
            $key        = $m[3];
            $rawVal     = trim($m[4]);
            $val        = $this->evalValue($rawVal);

            $obj = $this->resolveObject($targetName);
            if ($obj !== null) {
                if ($propName === 'image' && property_exists($obj, 'images')) {
                    $imgSrc = (string)$val;
                    if ($this->world !== null && !empty($this->world->imagesFolder) && !str_starts_with($imgSrc, 'http://') && !str_starts_with($imgSrc, 'https://') && !str_starts_with($imgSrc, '/')) {
                        $imgSrc = rtrim($this->world->imagesFolder, '/') . '/' . ltrim($imgSrc, '/');
                    }
                    $imgW = $obj->images[$key]->width ?? 350;
                    $imgH = $obj->images[$key]->height ?? 235;
                    $obj->images[$key] = new Image($imgSrc, $imgW, $imgH);
                    $obj->image = $obj->images[$key];
                } else {
                    $prop = $obj->getProperty($propName) ?? [];
                    if (!is_array($prop)) $prop = [];
                    $prop[$key] = $val;
                    $obj->setProperty($propName, $prop);
                }
            }
            return true;
        }

        // Tokenize by verb and argument list
        $parts = preg_split('/\s+/', $line, 2);
        $verb = strtoupper($parts[0] ?? '');
        $argString = $parts[1] ?? '';

        // Also handle verb followed immediately by '(' e.g. "Debug("test")"
        if (preg_match('/^([A-Za-z0-9_]+)\s*\((.*)\)$/s', $line, $vm) && !str_contains($vm[1], '.')) {
            $verbCandidate = strtoupper($vm[1]);
            if (in_array($verbCandidate, ['DEBUG', 'DISPLAY', 'PRINT', 'PRINTRIGHT', 'SPEAK', 'BROADCAST', 'CALL'], true)) {
                $verb = $verbCandidate;
                $argString = $vm[2];
            }
        }

        if (str_starts_with($argString, '(') && str_ends_with($argString, ')') && strlen($argString) >= 2) {
            $argString = substr($argString, 1, -1);
        }
        $args = $this->parseArguments($argString);

        return match ($verb) {
            'SETPANEL'   => $this->actSetPanel($args),
            'SPEAK'      => $this->actSpeak($args),
            'DISPLAY',
            'PRINT'      => $this->actDisplay($args),
            'PRINTRIGHT' => $this->actDisplay($args, true),
            'CALL'                => $this->actCall($args, $argString),
            'BROADCAST'           => $this->actBroadcast($args),
            'PLAYSOUND'           => $this->actPlaySound($args),
            'BROADCASTSOUND'      => $this->actBroadcastSound($args),
            'PLAYBACKGROUND'      => $this->actPlayBackground($args),
            'BROADCASTBACKGROUND' => $this->actBroadcastBackground($args),
            'MOVE'                => $this->actMove($args),
            'GOAL'       => $this->actGoal($args),
            'RETURN'     => $this->actReturn($args),
            'OPEN'       => $this->actOpen($args),
            'CLOSE'      => $this->actClose($args),
            'SEARCH'     => $this->actSearch($args),
            'GIVE'       => $this->actGive($args),
            'DEBUG'      => $this->actDebug($args),
            default      => true,
        };
    }

    /**
     * Debug instruction (SmallBasic 3.9.5):
     * Sends a debug message to the engine log (debug.log in the system folder).
     */
    protected function actDebug(array $args): bool
    {
        if (empty($args)) {
            $message = '';
        } else {
            $parts = [];
            foreach ($args as $arg) {
                $val = (string)$this->evalValue((string)$arg);
                if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                    $val = substr($val, 1, -1);
                }
                $parts[] = $val;
            }
            $message = implode(' ', $parts);
        }

        if (isset($this->world->engine) && $this->world->engine instanceof GameEngine) {
            $this->world->engine->log($message);
        } elseif (GameEngine::$activeInstance !== null) {
            GameEngine::$activeInstance->log($message);
        }

        return true;
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

    public function actSearch(array $args): bool
    {
        $targetObj = null;
        if (!empty($args)) {
            $targetName = $args[0];
            $targetObj = $this->resolveObject($targetName);
        }
        $agent = ($this->agent instanceof Character) ? $this->agent : null;
        if ($agent !== null) {
            return $agent->actSearch($targetObj);
        }
        return false;
    }

    public function actGive(array $args): bool
    {
        if (count($args) < 2) {
            return false;
        }
        $itemObj = $this->resolveObject((string)$args[0]);
        $recipObj = $this->resolveObject((string)$args[1]);
        if ($itemObj instanceof AdvObject && $recipObj instanceof Character) {
            $agent = ($this->agent instanceof Character) ? $this->agent : null;
            if ($agent !== null) {
                return $agent->actGive($itemObj, $recipObj);
            }
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

        // Determine recipient name / room:
        $recipientName = null;
        $roomId = null;
        if ($destination instanceof Player) {
            $recipientName = $destination->name;
        } elseif ($destination instanceof Character) {
            $recipientName = $destination->name;
        } elseif ($destination instanceof Room) {
            $roomId = $destination->id;
            $recipientName = null;
        } elseif ($destination === null && $this->agent instanceof Player) {
            $recipientName = $this->agent->name;
        }

        // If destination is explicitly World, broadcast to all
        if ($destination instanceof World) {
            $recipientName = null;
            $roomId = null;
        }

        // Spoken messages are inserted into the chat messages queue for the target
        $this->world->postChatMessage($speaker, $chosen, $recipientName, $roomId);

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
     * Display / Print [destination,] msg1 [, msg2, ...]
     * Outputs formatted messages directly to player console(s).
     *
     * As per DimensioneX ActionsRunner.java & SmallBasic Developer's Reference §3.9.6 / §3.9.18:
     * - If destination is omitted, default destination is $AGENT (or $OWNER if agent is null).
     * - If destination is $WORLD, displays to all players connected to the world.
     * - If destination is a Room, displays to all players/characters in that room.
     * - If destination is a Player/Character, displays directly to that player's console via display().
     * - When multiple messages are specified, they cycle sequentially across interactions.
     */
    protected function actDisplay(array $args, bool $rightColumn = false): bool
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
            return $this->world->getObjectExt($t);
        };

        $p0 = $args[0] ?? null;
        $p0Obj = (!$isLiteral($p0)) ? $resolveObj($p0) : null;

        if ($p0Obj === null || count($args) === 1) {
            // Compact syntax: Display msg1 [, msg2, ...]
            // Default destination is $AGENT (fallback to $OWNER or $WORLD)
            $destination = ($this->agent instanceof DimxObject)
                ? $this->agent
                : (($this->owner instanceof DimxObject) ? $this->owner : $this->world);
            $messages = $args;
        } else {
            // Explicit destination: Display destination, msg1 [, msg2, ...]
            $destination = $p0Obj;
            $messages = array_slice($args, 1);
        }

        if (empty($messages)) {
            return false;
        }

        // Sequential message selection across interactions
        $msgKey = '__act_disp_' . md5(implode('||', $messages));
        $lastIdx = ($destination instanceof DimxObject) ? $destination->getProperty($msgKey) : null;
        $nextIdx = ($lastIdx !== null && is_numeric($lastIdx)) ? ((int)$lastIdx + 1) : 0;
        if ($nextIdx >= count($messages)) {
            $nextIdx = 0;
        }
        if ($destination instanceof DimxObject) {
            $destination->setProperty($msgKey, $nextIdx);
        }

        $chosen = (string)$this->evalValue((string)$messages[$nextIdx]);
        if ((str_starts_with($chosen, '"') && str_ends_with($chosen, '"')) ||
            (str_starts_with($chosen, "'") && str_ends_with($chosen, "'"))) {
            $chosen = substr($chosen, 1, -1);
        }

        // Display to destination
        if ($destination instanceof DimxObject) {
            if ($rightColumn && method_exists($destination, 'displayRight')) {
                $destination->displayRight($chosen);
            } else {
                $destination->display($chosen);
            }
        } elseif ($this->agent instanceof Character) {
            $this->agent->display($chosen);
        }

        return true;
    }

    /**
     * Call subroutine, event, or object.event
     * Syntax:
     *   Call object.event
     *   Call subroutineName
     *   Call subroutineName(arg1, arg2, ...)
     */
    protected function actCall(array $args, string $argString = ''): mixed
    {
        $raw = trim($argString);
        if ($raw === '') {
            $raw = implode(' ', $args);
        }
        if ($raw === '') {
            return true;
        }

        $subName = $raw;
        $callArgs = [];
        if (preg_match('/^([A-Za-z0-9_.]+)\s*(?:\((.*)\))?$/', $raw, $m)) {
            $subName = trim($m[1]);
            if (isset($m[2]) && trim($m[2]) !== '') {
                $callArgs = $this->parseArguments($m[2]);
            }
        }

        // 1. If subName has a dot, e.g. "gate1.onOpen"
        if (str_contains($subName, '.')) {
            [$objId, $evtName] = explode('.', $subName, 2);
            $obj = $this->resolveObject($objId);
            return $this->world->fireEvent($evtName, $obj, $this->agent, $this->target, $callArgs, true);
        }

        // 2. Direct event / subroutine in world
        return $this->world->fireEvent($subName, $this->owner, $this->agent, $this->target, $callArgs, true);
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
     * PlaySound [destination,] soundFile
     * Plays a one-shot sound FX to a player, a room/PeopleContainer, or $WORLD.
     *
     * - If destination is omitted, default destination is $AGENT (or $OWNER / $WORLD fallback).
     * - If destination is a Player, that player receives the sound.
     * - If destination is a Room / PeopleContainer, all players in that container receive the sound.
     * - If destination is $WORLD, all players in the world receive the sound.
     */
    protected function actPlaySound(array $args): bool
    {
        if (empty($args)) {
            return false;
        }

        if (count($args) === 1) {
            $destination = ($this->agent instanceof DimxObject)
                ? $this->agent
                : (($this->owner instanceof DimxObject) ? $this->owner : $this->world);
            $rawSound = (string)$args[0];
        } else {
            $p0 = trim((string)$args[0]);
            $destObj = $this->resolveObject($p0);
            if ($destObj === null) {
                $evalDest = $this->evalValue($p0);
                if ($evalDest instanceof DimxObject) {
                    $destObj = $evalDest;
                } elseif (is_string($evalDest) && $evalDest !== '') {
                    $destObj = $this->world->getObjectExt($evalDest);
                }
            }
            if ($destObj !== null) {
                $destination = $destObj;
                $rawSound = (string)$args[1];
            } else {
                $destination = ($this->agent instanceof DimxObject)
                    ? $this->agent
                    : (($this->owner instanceof DimxObject) ? $this->owner : $this->world);
                $rawSound = (string)$args[0];
            }
        }

        $evalSound = $this->evalValue($rawSound);
        $soundFile = ($evalSound !== null && $evalSound !== '') ? (string)$evalSound : $rawSound;
        if ((str_starts_with($soundFile, '"') && str_ends_with($soundFile, '"')) ||
            (str_starts_with($soundFile, "'") && str_ends_with($soundFile, "'"))) {
            $soundFile = substr($soundFile, 1, -1);
        }
        $soundFile = trim($soundFile);
        if ($soundFile === '') {
            return false;
        }

        if ($destination instanceof DimxObject) {
            return $destination->playSound($soundFile);
        }

        return false;
    }

    /**
     * BroadcastSound soundFile
     * Plays a one-shot sound FX to all connected players in the world.
     */
    protected function actBroadcastSound(array $args): bool
    {
        if (empty($args)) {
            return false;
        }
        $rawSound  = (string)$args[0];
        $evalSound = $this->evalValue($rawSound);
        $soundFile = ($evalSound !== null && $evalSound !== '') ? (string)$evalSound : $rawSound;
        if ((str_starts_with($soundFile, '"') && str_ends_with($soundFile, '"')) ||
            (str_starts_with($soundFile, "'") && str_ends_with($soundFile, "'"))) {
            $soundFile = substr($soundFile, 1, -1);
        }
        $soundFile = trim($soundFile);
        if ($soundFile === '') {
            return false;
        }
        return $this->world->playSound($soundFile);
    }

    /**
     * PlayBackground [destination,] soundFile [, loop]
     * Plays background music and stores it as a property of the target Room/PeopleContainer or $WORLD.
     *
     * - If destination is omitted, defaults to the active Room/PeopleContainer ($OWNER if PeopleContainer,
     *   or $AGENT's current room) and notifies the player(s).
     * - If destination is a Room / PeopleContainer, sets backgroundMusic on that container and plays for occupants.
     * - If destination is $WORLD, sets backgroundMusic on $WORLD and plays for all connected players.
     * - If destination is a Player, plays for that player (and sets on their current room if any).
     */
    protected function actPlayBackground(array $args): bool
    {
        if (empty($args)) {
            return false;
        }

        $destObj  = null;
        $rawSound = '';
        $rawLoop  = null;

        if (count($args) === 1) {
            $rawSound = (string)$args[0];
        } else {
            $p0 = trim((string)$args[0]);
            $candidate = $this->resolveObject($p0);
            if ($candidate === null) {
                $evalDest = $this->evalValue($p0);
                if ($evalDest instanceof DimxObject) {
                    $candidate = $evalDest;
                } elseif (is_string($evalDest) && $evalDest !== '' && $evalDest !== $p0) {
                    $candidate = $this->world->getObjectExt($evalDest);
                }
            }

            if ($candidate !== null) {
                $destObj  = $candidate;
                $rawSound = (string)$args[1];
                $rawLoop  = $args[2] ?? null;
            } else {
                // Compact 2-arg form: PlayBackground soundFile, loop
                $rawSound = (string)$args[0];
                $rawLoop  = $args[1];
            }
        }

        $evalSound = $this->evalValue($rawSound);
        $soundFile = ($evalSound !== null && $evalSound !== '') ? (string)$evalSound : $rawSound;
        if ((str_starts_with($soundFile, '"') && str_ends_with($soundFile, '"')) ||
            (str_starts_with($soundFile, "'") && str_ends_with($soundFile, "'"))) {
            $soundFile = substr($soundFile, 1, -1);
        }
        $soundFile = trim($soundFile);

        $loop = false;
        if ($rawLoop !== null) {
            $evalLoop = $this->evalCondition((string)$rawLoop);
            $loop = !empty($evalLoop) && strtolower((string)$evalLoop) !== 'false' && (string)$evalLoop !== '0';
        }

        if ($destObj !== null) {
            if ($destObj instanceof Player && $destObj->getRoom() instanceof PeopleContainer) {
                $destObj->getRoom()->playBackground($soundFile, $loop);
            }
            return $destObj->playBackground($soundFile, $loop);
        }

        // Default destination when omitted: set on the room ($OWNER if PeopleContainer, else $AGENT's room)
        $targetContainer = ($this->owner instanceof PeopleContainer)
            ? $this->owner
            : (($this->agent instanceof Character) ? $this->agent->getRoom() : null);

        if ($targetContainer instanceof PeopleContainer) {
            $targetContainer->playBackground($soundFile, $loop);
            if ($this->agent instanceof Player) {
                $this->agent->playBackground($soundFile, $loop);
            }
            return true;
        }

        if ($this->agent instanceof DimxObject) {
            return $this->agent->playBackground($soundFile, $loop);
        }

        return $this->world->playBackground($soundFile, $loop);
    }

    /**
     * BroadcastBackground soundFile [, loop]
     * Plays background music across the entire world and stores it on $WORLD.
     */
    protected function actBroadcastBackground(array $args): bool
    {
        if (empty($args)) {
            return false;
        }
        $rawSound  = (string)$args[0];
        $evalSound = $this->evalValue($rawSound);
        $soundFile = ($evalSound !== null && $evalSound !== '') ? (string)$evalSound : $rawSound;
        if ((str_starts_with($soundFile, '"') && str_ends_with($soundFile, '"')) ||
            (str_starts_with($soundFile, "'") && str_ends_with($soundFile, "'"))) {
            $soundFile = substr($soundFile, 1, -1);
        }
        $soundFile = trim($soundFile);

        $loop = false;
        if (isset($args[1])) {
            $evalLoop = $this->evalCondition((string)$args[1]);
            $loop = !empty($evalLoop) && strtolower((string)$evalLoop) !== 'false' && (string)$evalLoop !== '0';
        }

        return $this->world->playBackground($soundFile, $loop);
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

        if ($whatObj instanceof AdvObject && $whereObj instanceof AdvObject) {
            $moved = $whatObj->move($whereObj);
            if ($moved && $this->world !== null && ($whatObj instanceof Character || $whatObj->isVehicle())) {
                $this->world->fireEvent('onEnter', $whatObj, $this->agent ?? $whatObj, $whereObj);
                $this->world->fireEvent('onReceive', $whereObj, $this->agent ?? $whatObj, $whatObj);
            }
            return $moved;
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

        // Unwrap outer parentheses if fully enclosed: (expr)
        while (str_starts_with($expr, '(') && str_ends_with($expr, ')') && self::isFullyEnclosedInParens($expr)) {
            $expr = trim(substr($expr, 1, -1));
        }

        // Logical OR (lowest precedence)
        if ($parts = $this->splitTopLevelLogical($expr, 'OR')) {
            return (bool)$this->evalCondition(trim($parts[0])) || (bool)$this->evalCondition(trim($parts[1]));
        }

        // Logical AND
        if ($parts = $this->splitTopLevelLogical($expr, 'AND')) {
            return (bool)$this->evalCondition(trim($parts[0])) && (bool)$this->evalCondition(trim($parts[1]));
        }

        // Logical NOT: Not expr or Not(expr)
        if (preg_match('/^NOT\b\s*(.*)$/i', $expr, $nm)) {
            $inner = trim($nm[1]);
            return !$this->evalCondition($inner);
        }

        // Check operators: <>, <=, >=, =, <, >
        $operators = ['<>', '<=', '>=', '=', '<', '>'];
        foreach ($operators as $op) {
            if (str_contains($expr, $op)) {
                $parts = explode($op, $expr, 2);
                $left  = $this->evalValue(trim($parts[0]));
                $right = $this->evalValue(trim($parts[1]));

                if ($left instanceof DimxObject) {
                    $left = $left->id;
                }
                if ($right instanceof DimxObject) {
                    $right = $right->id;
                }

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
     * Split expression at top-level logical keyword (AND / OR) outside parentheses and quotes.
     */
    protected function splitTopLevelLogical(string $expr, string $keyword): ?array
    {
        $len = strlen($expr);
        $parenDepth = 0;
        $inQuote = false;
        $quoteChar = '';
        $kwLen = strlen($keyword);

        for ($i = 0; $i < $len; $i++) {
            $ch = $expr[$i];

            if ($inQuote) {
                if ($ch === $quoteChar) {
                    $inQuote = false;
                }
                continue;
            }

            if ($ch === '"' || $ch === "'") {
                $inQuote = true;
                $quoteChar = $ch;
                continue;
            }

            if ($ch === '(') {
                $parenDepth++;
                continue;
            }
            if ($ch === ')') {
                if ($parenDepth > 0) {
                    $parenDepth--;
                }
                continue;
            }

            if ($parenDepth === 0) {
                if (strcasecmp(substr($expr, $i, $kwLen), $keyword) === 0) {
                    $beforeOk = ($i === 0) || ctype_space($expr[$i - 1]) || $expr[$i - 1] === ')';
                    $afterPos = $i + $kwLen;
                    $afterOk = ($afterPos >= $len) || ctype_space($expr[$afterPos]) || $expr[$afterPos] === '(';

                    if ($beforeOk && $afterOk) {
                        return [
                            substr($expr, 0, $i),
                            substr($expr, $afterPos),
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Check if string is completely wrapped in balanced matching parentheses.
     */
    protected static function isFullyEnclosedInParens(string $s): bool
    {
        $len = strlen($s);
        if ($len < 2 || $s[0] !== '(' || $s[$len - 1] !== ')') {
            return false;
        }
        $depth = 0;
        for ($i = 0; $i < $len - 1; $i++) {
            if ($s[$i] === '(') {
                $depth++;
            } elseif ($s[$i] === ')') {
                $depth--;
                if ($depth === 0) {
                    return false;
                }
            }
        }
        return $depth === 1;
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

        // Function call: RndSet(set)
        if (preg_match('/^rndSet\s*\(\s*(.+?)\s*\)$/i', $val, $rm)) {
            $arg = trim($rm[1]);
            if (str_starts_with($arg, '$WORLD.')) {
                $setName = substr($arg, 7);
            } else {
                $setName = $arg;
            }
            $set = $this->world->sets[$setName] ?? $this->world->getProperty($setName) ?? null;
            if (is_array($set) && !empty($set)) {
                $keys = array_keys($set);
                $randKey = $keys[array_rand($keys)];
                return is_int($randKey) ? $set[$randKey] : $randKey;
            }
            return '';
        }

        // Function call: RndInt(min, max)
        if (preg_match('/^rndInt\s*\(\s*(.+?)\s*,\s*(.+?)\s*\)$/i', $val, $im)) {
            $min = (int)$this->evalValue($im[1]);
            $max = (int)$this->evalValue($im[2]);
            return rand($min, $max);
        }

        // Property access e.g. "$WORLD.mode", "$OWNER.Health", "box.open"
        if (preg_match('/^(\$?[A-Za-z0-9_]+)\.([A-Za-z0-9_]+)$/', $val, $m)) {
            $obj = $this->resolveObject($m[1]);
            $prop = $m[2];
            if ($obj !== null) {
                $propVal = property_exists($obj, $prop) ? $obj->{$prop} : $obj->getProperty($prop);
                if ($propVal instanceof DimxObject) {
                    return $propVal->id;
                }
                return $propVal;
            }
            return null;
        }

        // Simple binary arithmetic: left [+-*/] right (e.g. "$OWNER.Health + 0.2" or string concat "Text " + var)
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
            } elseif ($op === '+') {
                return (string)$left . (string)$right;
            }
        }

        return $val;
    }
}
