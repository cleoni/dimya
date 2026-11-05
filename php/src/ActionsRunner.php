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
    public array $localVars = [];

    protected bool $hasReturned = false;
    protected mixed $returnValue = true;
    protected mixed $defaultResult = true;

    public string $fileShort = '';
    public int $startLine = 0;
    public int $currLine = 0;
    public string $fileName = '';
    public int $stackLevel = 0;

    public function __construct(
        World $world,
        ?DimxObject $owner = null,
        mixed $agent = null,
        mixed $target = null,
        array $input = [],
        mixed $defaultResult = true,
        string $fileShort = '',
        int $startLine = 0,
        string $fileName = '',
        int $stackLevel = 0
    ) {
        $this->world = $world;
        $this->owner = $owner;
        $this->agent = $agent;
        $this->target = $target;
        $this->input = $input;
        $this->defaultResult = $defaultResult;
        $this->fileShort = $fileShort;
        $this->startLine = $startLine;
        $this->currLine = $startLine;
        $this->fileName = $fileName;
        $this->stackLevel = $stackLevel;
    }

    public function setLocalVar(string $name, mixed $val): void
    {
        $this->localVars[$name] = $val;
    }

    public function getLocalVar(string $name): mixed
    {
        return $this->localVars[$name] ?? null;
    }

    public function setVariable(string $name, mixed $val, bool $isDim = false): void
    {
        if ($isDim || array_key_exists($name, $this->localVars)) {
            $this->localVars[$name] = $val;
            return;
        }

        if ($this->world !== null) {
            $this->world->setProperty($name, $val);
            if (property_exists($this->world, $name)) {
                $this->world->{$name} = $val;
            }
        }
    }

    public function getVariable(string $name): mixed
    {
        if (array_key_exists($name, $this->localVars)) {
            return $this->localVars[$name];
        }
        if ($this->world !== null) {
            if ($this->world->hasProperty($name)) {
                return $this->world->getProperty($name);
            }
            if (isset($this->world->sets[$name])) {
                return $this->world->sets[$name];
            }
            if (property_exists($this->world, $name)) {
                return $this->world->{$name};
            }
        }
        if ($this->owner instanceof DimxObject) {
            if ($this->owner->hasProperty($name)) {
                return $this->owner->getProperty($name);
            }
            if (property_exists($this->owner, $name)) {
                return $this->owner->{$name};
            }
        }
        return null;
    }

    /**
     * Log a debug/diagnostic message to game engine log (debug.log) and World log.
     */
    public function log(mixed $message): void
    {
        if ($this->world !== null) {
            $this->world->log($message);
        } elseif (GameEngine::$activeInstance !== null) {
            GameEngine::$activeInstance->log($message);
        }
    }

    public function incIndent(): void
    {
        if ($this->world !== null) {
            $this->world->incIndent();
        } elseif (GameEngine::$activeInstance !== null) {
            GameEngine::$activeInstance->incIndent();
        }
    }

    public function decIndent(): void
    {
        if ($this->world !== null) {
            $this->world->decIndent();
        } elseif (GameEngine::$activeInstance !== null) {
            GameEngine::$activeInstance->decIndent();
        }
    }

    public function setArrayElement(string $name, int $index, mixed $val): void
    {
        $idx0 = max(0, $index - 1);

        if (array_key_exists($name, $this->localVars) && is_array($this->localVars[$name])) {
            $this->localVars[$name][$idx0] = $val;
            return;
        }

        if ($this->world !== null) {
            $arr = $this->world->sets[$name] ?? $this->world->getProperty($name);
            if (!is_array($arr)) {
                $arr = [];
            }
            $arr[$idx0] = $val;
            $this->world->sets[$name] = $arr;
            $this->world->setProperty($name, $arr);
            return;
        }

        $this->localVars[$name][$idx0] = $val;
    }

    public function getArrayElement(string $name, int $index): mixed
    {
        $idx0 = max(0, $index - 1);
        $arr = $this->getVariable($name);
        if (is_array($arr)) {
            return $arr[$idx0] ?? $arr[$index] ?? null;
        }
        return null;
    }

    /**
     * Run a multi-line script string.
     */
    public function run(string $script): mixed
    {
        $this->hasReturned = false;
        $this->returnValue = $this->defaultResult;

        if ($this->stackLevel > 0) {
            $this->incIndent();
        }
        if ($this->fileShort !== '' && $this->currLine > 0) {
            $this->log("{$this->fileShort}({$this->currLine}): running");
        }

        try {
            $lines = $this->parseScriptToLines($script);
            return $this->executeLinesList($lines);
        } finally {
            if ($this->stackLevel > 0) {
                $this->decIndent();
            }
        }
    }

    /**
     * Run a pre-parsed list of line items (text + line number) within this runner instance.
     */
    public function runParsedLines(array $lines): mixed
    {
        $this->hasReturned = false;
        $this->returnValue = $this->defaultResult;

        $this->incIndent();
        if ($this->fileShort !== '' && $this->currLine > 0) {
            $this->log("{$this->fileShort}({$this->currLine}): running");
        }

        try {
            return $this->executeLinesList($lines);
        } finally {
            $this->decIndent();
        }
    }

    /**
     * Parse raw script into array of line items with accurate source line numbers.
     *
     * @return array<int, array{text: string, line: int}>
     */
    protected function parseScriptToLines(string $script): array
    {
        $rawLines = preg_split('/\r\n|\r|\n/', $script);
        $lines = [];
        $accum = '';
        $inQuote = false;
        $accumStartLine = 0;

        foreach ($rawLines as $rawIdx => $raw) {
            $rawLineNum = ($this->startLine > 0 ? $this->startLine : 1) + $rawIdx;

            if (!$inQuote) {
                $trimmed = trim($raw);
                if ($trimmed === '' || str_starts_with($trimmed, "'")) {
                    continue;
                }
                $accumStartLine = $rawLineNum;
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
                    $lines[] = [
                        'text' => $trimmedAccum,
                        'line' => $accumStartLine,
                    ];
                }
                $accum = '';
            }
        }
        if ($accum !== '') {
            $trimmedAccum = trim($accum);
            if ($trimmedAccum !== '') {
                $lines[] = [
                    'text' => $trimmedAccum,
                    'line' => $accumStartLine,
                ];
            }
        }

        return $lines;
    }

    /**
     * Execute a sequence of pre-parsed line items.
     *
     * @param array<int, array{text: string, line: int}> $lines
     */
    protected function executeLinesList(array $lines): mixed
    {
        $count = count($lines);
        $i = 0;
        $result = $this->defaultResult;

        while ($i < $count && !$this->hasReturned) {
            $lineItem = $lines[$i];
            $line = $lineItem['text'];
            $this->currLine = $lineItem['line'];

            // Check for If block
            if (preg_match('/^IF\b\s*(.*)$/i', $line, $m)) {
                $condExpr = trim($m[1]);
                $condClean = preg_replace('/\s+THEN$/i', '', $condExpr);

                // Java: world.logger.debug("IF " + lookupElement(setTabSpace).strVal() + "...");
                $firstWord = '';
                if (preg_match('/^([^\s.]+)/', $condClean, $cm)) {
                    $firstWord = $cm[1];
                } else {
                    $firstWord = $condClean;
                }
                $this->log("IF {$firstWord}...");

                $condVal = $this->evalCondition($condClean);
                // Java: world.logger.debug(expr.boolVal() ? "TRUE" : "FALSE");
                if ($condVal) {
                    $this->log("TRUE");
                } else {
                    $this->log("FALSE");
                }

                // Collect block up to End_If
                $thenLines = [];
                $elseLines = [];
                $inElse = false;
                $nesting = 1;
                $endIfLine = $this->currLine;
                $i++;

                while ($i < $count) {
                    $subItem = $lines[$i];
                    $sub = $subItem['text'];
                    $subLine = $subItem['line'];

                    if (preg_match('/^IF\b/i', $sub)) {
                        $nesting++;
                    } elseif (preg_match('/^(END_IF|END\s+IF|ENDIF)\b/i', $sub)) {
                        $nesting--;
                        if ($nesting === 0) {
                            $endIfLine = $subLine;
                            $i++;
                            break;
                        }
                    } elseif ($nesting === 1 && preg_match('/^ELSE\b/i', $sub)) {
                        $inElse = true;
                        $i++;
                        continue;
                    }

                    if ($inElse) {
                        $elseLines[] = $subItem;
                    } else {
                        $thenLines[] = $subItem;
                    }
                    $i++;
                }

                $execLines = $condVal ? $thenLines : $elseLines;
                if (!empty($execLines)) {
                    $blockStartLine = $execLines[0]['line'];
                    $subRunner = new ActionsRunner(
                        $this->world,
                        $this->owner,
                        $this->agent,
                        $this->target,
                        $this->input,
                        $this->defaultResult,
                        $this->fileShort,
                        $blockStartLine,
                        $this->fileName,
                        $this->stackLevel + 1
                    );
                    $subRunner->localVars =& $this->localVars;
                    $subRes = $subRunner->runParsedLines($execLines);
                    if ($subRunner->hasReturned) {
                        $this->hasReturned = true;
                        $this->returnValue = $subRunner->returnValue;
                        return $this->returnValue;
                    }
                    if ($subRes !== false && $subRes !== null && $subRes !== 0 && $subRes !== '0') {
                        $result = true;
                    }
                }

                // Java: if (world.logger.on) world.logger.debug(fileShort + "(" + currLine + "): " + actionverb.toUpperCase() + " executed");
                if ($this->fileShort !== '' && $endIfLine > 0) {
                    $this->log("{$this->fileShort}({$endIfLine}): IF executed");
                }
                $this->currLine = $endIfLine;
                continue;
            }

            // Check for For Each loop: For Each var In collection ... Next
            if (preg_match('/^FOR\s+EACH\b\s+([A-Za-z0-9_]+)\s+IN\s+(.+)$/i', $line, $fem)) {
                $varName = $fem[1];
                $collExpr = trim($fem[2]);
                $collection = $this->evalValue($collExpr);
                if (!is_array($collection)) {
                    $collection = $this->getVariable($collExpr);
                }
                if (!is_array($collection)) {
                    $collection = [];
                }

                $loopLines = [];
                $nesting = 1;
                $nextEndLine = $this->currLine;
                $i++;

                while ($i < $count) {
                    $subItem = $lines[$i];
                    $sub = $subItem['text'];
                    $subLine = $subItem['line'];
                    if (preg_match('/^FOR\b/i', $sub)) {
                        $nesting++;
                    } elseif (preg_match('/^NEXT\b/i', $sub)) {
                        $nesting--;
                        if ($nesting === 0) {
                            $nextEndLine = $subLine;
                            $i++;
                            break;
                        }
                    }
                    $loopLines[] = $subItem;
                    $i++;
                }

                $elemIdx = 0;
                foreach ($collection as $item) {
                    $this->log("Looping. Set element # " . $elemIdx);
                    $this->setVariable($varName, $item);
                    $this->executeLinesList($loopLines);
                    if ($this->hasReturned) {
                        return $this->returnValue;
                    }
                    $elemIdx++;
                }
                if ($this->fileShort !== '' && $nextEndLine > 0) {
                    $this->log("{$this->fileShort}({$nextEndLine}): FOR executed");
                }
                $this->currLine = $nextEndLine;
                continue;
            }

            // Check for For loop: For var = start To end [Step s]
            if (preg_match('/^FOR\b\s+([A-Za-z0-9_]+)\s*=\s*(.+?)\s+TO\s+(.+?)(\s+STEP\s+(.+?))?$/i', $line, $fm)) {
                $varName = $fm[1];
                $startVal = (int)$this->evalValue($fm[2]);
                $endVal = (int)$this->evalValue($fm[3]);
                $step = isset($fm[5]) ? (int)$this->evalValue($fm[5]) : 1;

                $loopLines = [];
                $nesting = 1;
                $nextEndLine = $this->currLine;
                $i++;

                while ($i < $count) {
                    $subItem = $lines[$i];
                    $sub = $subItem['text'];
                    $subLine = $subItem['line'];
                    if (preg_match('/^FOR\b/i', $sub)) {
                        $nesting++;
                    } elseif (preg_match('/^NEXT\b/i', $sub)) {
                        $nesting--;
                        if ($nesting === 0) {
                            $nextEndLine = $subLine;
                            $i++;
                            break;
                        }
                    }
                    $loopLines[] = $subItem;
                    $i++;
                }

                for ($val = $startVal; ($step > 0 ? $val <= $endVal : $val >= $endVal); $val += $step) {
                    $this->log("Looping. Index= " . $val);
                    $this->setVariable($varName, $val);
                    $this->executeLinesList($loopLines);
                    if ($this->hasReturned) {
                        return $this->returnValue;
                    }
                }
                if ($this->fileShort !== '' && $nextEndLine > 0) {
                    $this->log("{$this->fileShort}({$nextEndLine}): FOR executed");
                }
                $this->currLine = $nextEndLine;
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
                if ($propName === 'image' && property_exists($obj, 'images')) {
                    if ($val instanceof Image) {
                        $obj->images['N'] = $val;
                    } elseif (is_string($val)) {
                        $imgSrc = $val;
                        if ($this->world !== null && !empty($this->world->imagesFolder) && !str_starts_with($imgSrc, 'http://') && !str_starts_with($imgSrc, 'https://') && !str_starts_with($imgSrc, '/')) {
                            $imgSrc = rtrim($this->world->imagesFolder, '/') . '/' . ltrim($imgSrc, '/');
                        }
                        $imgW = $obj->images['N']->width ?? 350;
                        $imgH = $obj->images['N']->height ?? 235;
                        $obj->images['N'] = new Image($imgSrc, $imgW, $imgH);
                    }
                    if (property_exists($obj, 'image')) {
                        $obj->image = $obj->images['N'] ?? $val;
                    }
                } else {
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
                    if ($val instanceof Image) {
                        $obj->images[$key] = $val;
                        $obj->image = $val;
                    } else {
                        $imgSrc = (string)$val;
                        if ($this->world !== null && !empty($this->world->imagesFolder) && !str_starts_with($imgSrc, 'http://') && !str_starts_with($imgSrc, 'https://') && !str_starts_with($imgSrc, '/')) {
                            $imgSrc = rtrim($this->world->imagesFolder, '/') . '/' . ltrim($imgSrc, '/');
                        }
                        $imgW = $obj->images[$key]->width ?? 350;
                        $imgH = $obj->images[$key]->height ?? 235;
                        $obj->images[$key] = new Image($imgSrc, $imgW, $imgH);
                        $obj->image = $obj->images[$key];
                    }
                } else {
                    $prop = $obj->getProperty($propName) ?? [];
                    if (!is_array($prop)) $prop = [];
                    $prop[$key] = $val;
                    $obj->setProperty($propName, $prop);
                }
            }
            return true;
        }

        // 1c. Array element assignment e.g. "score(i) = 0" or "players(i) = person"
        if (preg_match('/^([A-Za-z0-9_]+)\s*\(\s*(.+?)\s*\)\s*=\s*(.*)$/', $line, $m) && !str_contains($m[1], '.')) {
            $arrName = $m[1];
            $idx = (int)$this->evalValue($m[2]);
            $val = $this->evalValue($m[3]);
            $this->setArrayElement($arrName, $idx, $val);
            return true;
        }

        // 1d. Variable assignment with or without Dim e.g. "Dim slot = addPlayer($AGENT)" or "mode = 'pre'"
        if (preg_match('/^(?:DIM\s+)?([A-Za-z0-9_]+)\s*=\s*(.*)$/i', $line, $m) && !str_contains($m[1], '.')) {
            $varName = $m[1];
            $val = $this->evalValue($m[2]);
            $isDim = str_starts_with(strtoupper($line), 'DIM');
            $this->setVariable($varName, $val, $isDim);
            return true;
        }

        // 1e. Variable declaration without assignment: "Dim i"
        if (preg_match('/^DIM\s+([A-Za-z0-9_]+)$/i', $line, $m)) {
            $this->localVars[$m[1]] = null;
            return true;
        }

        // Tokenize by verb and argument list
        $parts = preg_split('/\s+/', $line, 2);
        $verb = strtoupper($parts[0] ?? '');
        $argString = $parts[1] ?? '';

        // Also handle verb followed immediately by '(' e.g. "Debug("test")"
        if (preg_match('/^([A-Za-z0-9_]+)\s*\((.*)\)$/s', $line, $vm) && !str_contains($vm[1], '.')) {
            $verbCandidate = strtoupper($vm[1]);
            if (in_array($verbCandidate, ['DEBUG', 'DISPLAY', 'PRINT', 'PRINTRIGHT', 'SPEAK', 'BROADCAST', 'CALL', 'DROPITEMS', 'RESET'], true)) {
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
            'DROPITEMS'           => $this->actDropItems($args),
            'GOAL'       => $this->actGoal($args),
            'RETURN'     => $this->actReturn($args, $argString),
            'OPEN'       => $this->actOpen($args),
            'CLOSE'      => $this->actClose($args),
            'SEARCH'     => $this->actSearch($args),
            'REFRESHVIEW'         => $this->actRefreshView($args),
            'SENDCMD'             => $this->actSendCmd($args),
            'NEWITEM'             => $this->actNewItem($args),
            'RESET'      => $this->actReset($args),
            'GIVE'       => $this->actGive($args),
            'DEBUG'      => $this->actDebug($args),
            default      => true,
        };
    }

    /**
     * Reset [broadcastMsg] (SmallBasic 3.9.20)
     * Resets the game engine world state, re-seeding the world and re-synchronizing players.
     */
    protected function actReset(array $args = []): bool
    {
        if ($this->world !== null) {
            $this->world->reset = true;
            if (!empty($args)) {
                $rawMsg = (string)$args[0];
                $msg = (string)$this->evalValue($rawMsg);
                if ((str_starts_with($msg, '"') && str_ends_with($msg, '"')) ||
                    (str_starts_with($msg, "'") && str_ends_with($msg, "'"))) {
                    $msg = substr($msg, 1, -1);
                }
                if ($msg !== '') {
                    $this->world->broadcast($msg);
                }
            }
            if ($this->world->engine !== null) {
                $this->world->engine->log("Action: RESET");
            } elseif (GameEngine::$activeInstance !== null) {
                GameEngine::$activeInstance->log("Action: RESET");
            }
        }
        return true;
    }

    /**
     * RefreshView [dest] (SmallBasic 3.9.21)
     * Forces an immediate client-side UI view refresh (dispatches "refresh!scene" to target).
     */
    protected function actRefreshView(array $args): bool
    {
        $dest = !empty($args) ? $this->resolveObject((string)$args[0]) : null;
        $target = $dest ?? ($this->agent instanceof DimxObject ? $this->agent : null) ?? $this->world;
        if ($target !== null && method_exists($target, 'sendCmd')) {
            return $target->sendCmd('refresh!scene');
        }
        return true;
    }

    /**
     * SendCmd [dest,] command (SmallBasic 3.9.24)
     * Dispatches a client command to the player's client interface.
     */
    protected function actSendCmd(array $args): bool
    {
        if (empty($args)) {
            return false;
        }
        if (count($args) === 1) {
            $target = ($this->agent instanceof DimxObject ? $this->agent : null) ?? $this->world;
            $cmd = (string)$this->evalValue((string)$args[0]);
        } else {
            $dest = $this->resolveObject((string)$args[0]);
            $target = $dest ?? ($this->agent instanceof DimxObject ? $this->agent : null) ?? $this->world;
            $cmd = (string)$this->evalValue((string)$args[1]);
        }
        $cmd = trim($cmd, "\"' \t");
        if ($target !== null && method_exists($target, 'sendCmd')) {
            return $target->sendCmd($cmd);
        }
        return true;
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

        $this->log($message);

        return true;
    }

    /**
     * NewItem instruction (SmallBasic 3.10.39):
     * Dynamically creates a new item inside container.
     */
    protected function actNewItem(array $args): bool
    {
        $this->createNewItem(
            $args[0] ?? '',
            $args[1] ?? '',
            $args[2] ?? '',
            $args[3] ?? '',
            $args[4] ?? ''
        );
        return true;
    }

    /**
     * Create a new dynamic item in a container.
     * NewItem( container, name, descr, image, attributes ) (SmallBasic 3.10.39)
     */
    public function createNewItem(
        mixed $rawContainer,
        mixed $rawName,
        mixed $rawDescr,
        mixed $rawImage,
        mixed $rawAttrs
    ): ?string {
        $evalContainer = is_string($rawContainer) ? $this->evalValue($rawContainer) : $rawContainer;
        $opos = null;
        if ($evalContainer instanceof AdvObject) {
            $opos = $evalContainer;
        } elseif (is_string($evalContainer) && $evalContainer !== '') {
            $opos = $this->resolveObject($evalContainer) ?? $this->world?->getObjectExt($evalContainer);
        }

        $evalName = is_string($rawName) ? $this->evalValue($rawName) : $rawName;
        $name = is_string($evalName) ? trim($evalName, "\"'") : (string)$evalName;

        $evalDescr = ($rawDescr === null || strcasecmp(trim((string)$rawDescr), 'null') === 0)
            ? ''
            : (is_string($rawDescr) ? $this->evalValue($rawDescr) : $rawDescr);
        $descr = ($evalDescr !== null && strcasecmp((string)$evalDescr, 'null') !== 0) ? (is_string($evalDescr) ? trim($evalDescr, "\"'") : (string)$evalDescr) : '';

        $evalImg = ($rawImage === null || strcasecmp(trim((string)$rawImage), 'null') === 0)
            ? null
            : (is_string($rawImage) ? $this->evalValue($rawImage) : $rawImage);
        $image = null;
        if ($evalImg instanceof Image) {
            $image = $evalImg;
        } elseif (is_string($evalImg) && $evalImg !== '' && strcasecmp($evalImg, 'null') !== 0) {
            $imgSrc = trim($evalImg, "\"'");
            if ($this->world !== null && !empty($this->world->imagesFolder)
                && !str_starts_with($imgSrc, 'http://') && !str_starts_with($imgSrc, 'https://') && !str_starts_with($imgSrc, '/')) {
                $imgSrc = rtrim($this->world->imagesFolder, '/') . '/' . ltrim($imgSrc, '/');
            }
            $image = new Image($imgSrc, 0, 0);
        }

        $evalAttrs = ($rawAttrs === null || strcasecmp(trim((string)$rawAttrs), 'null') === 0)
            ? ''
            : (is_string($rawAttrs) ? $this->evalValue($rawAttrs) : $rawAttrs);
        if (is_string($evalAttrs)) {
            $evalAttrs = trim($evalAttrs, "\"'");
        }

        $posId = $opos?->id ?? (is_string($evalContainer) ? $evalContainer : '');
        $id = $this->world?->getNextId() ?? ('_obj' . uniqid());

        $item = new Item($this->world, $name, $id, $descr, null, $evalAttrs, 5, 1, $posId);
        if ($image !== null) {
            $item->setImage('N', $image);
            $item->image = $image;
        }

        if ($this->world !== null) {
            $this->world->addItem($item);
        }

        if ($opos !== null) {
            while ($opos->container !== null && $opos->getFreeSpace() < $item->volume) {
                $opos = $opos->container;
            }
            $opos->addObject($item);
        }

        return $item->id;
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

    protected function actReturn(array $args, string $argString = ''): mixed
    {
        $raw = trim($argString) !== '' ? trim($argString) : implode(' ', $args);
        $val = ($raw !== '') ? $this->evalCondition($raw) : true;
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
            $speakerId = ($speakerObj instanceof DimxObject) ? $speakerObj->id : (($p0 !== null) ? (string)$p0 : 'null');
            $this->log("Warning: invalid speaker: {$speakerId}");
            $speakerObj = $this->world->getDefaultCharacter();
        }
        $speaker = ($speakerObj instanceof ManageableObject && !empty($speakerObj->name))
            ? $speakerObj->name
            : 'a voice...';

        if ($destination === null) {
            $this->log("WARNING: NULL destination");
            return true;
        }

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

        $paramIdx = 2 + $nextIdx;
        if (!isset($messages[$nextIdx])) {
            $this->log("actSpeak: par{$paramIdx} is a null message - won't speak");
            return true;
        }

        $evalRes = $this->evalValue((string)$messages[$nextIdx]);
        if ($evalRes instanceof Image) {
            $chosen = $evalRes->toHtml();
        } else {
            $chosen = (string)$evalRes;
            $chosen = trim($chosen, "\"'");
        }

        $destStr = ($destination instanceof DimxObject) ? (string)$destination : (($this->agent instanceof Character) ? (string)$this->agent : 'world');
        $this->log("Speaking parameter #{$paramIdx} to {$destStr}: {$chosen}");

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

        $evalRes = $this->evalValue((string)$messages[$nextIdx]);
        if ($evalRes instanceof Image) {
            $chosen = $evalRes->toHtml();
        } else {
            $chosen = (string)$evalRes;
            if ((str_starts_with($chosen, '"') && str_ends_with($chosen, '"')) ||
                (str_starts_with($chosen, "'") && str_ends_with($chosen, "'"))) {
                $chosen = substr($chosen, 1, -1);
            }
        }
		
        $destId = ($destination instanceof DimxObject) ? $destination->id : (($this->agent instanceof Character) ? $this->agent->id : 'unknown');
        $this->log("Displaying to {$destId}: {$chosen}");

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
                $rawArgs = $this->parseArguments($m[2]);
                foreach ($rawArgs as $a) {
                    $callArgs[] = $this->evalValue($a);
                }
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
            $moved = $whatObj->move($whereObj, false);
            if ($moved && $this->world !== null && ($whatObj instanceof Character || $whatObj->isVehicle())) {
                $this->world->fireEvent('onEnter', $whatObj, $this->agent ?? $whatObj, $whereObj);
                $this->world->fireEvent('onReceive', $whereObj, $this->agent ?? $whatObj, $whatObj);
            }
            return $moved;
        }

        return false;
    }

    /**
     * DropItems [victim]
     * Drops all items carried by the target object into its enclosing container/room.
     * Matches DimensioneX ActionsRunner.actDropItems() & SmallBasic §3.9.7.
     */
    protected function actDropItems(array $args): bool
    {
        $targetName = !empty($args[0]) ? (string)$args[0] : '';
        $obj = null;
        if ($targetName !== '') {
            $obj = $this->resolveObject($targetName);
        }
        if ($obj === null) {
            $obj = ($this->agent instanceof AdvObject)
                ? $this->agent
                : (($this->owner instanceof AdvObject) ? $this->owner : null);
        }

        if ($obj === null) {
            $this->world?->log("DropItems: cannot resolve target: >{$targetName}<");
            return false;
        }

        $dest = $obj->container ?? ($obj instanceof Character ? $obj->getRoom() : null);
        if (!($dest instanceof AdvObject)) {
            $this->world?->log("DropItems: target {$obj->id} has no container to drop items into");
            return false;
        }

        $contents = array_values($obj->contents);
        if (!empty($contents)) {
            foreach ($contents as $what) {
                if ($what instanceof AdvObject) {
                    $what->move($dest, false);
                }
            }
            if ($obj instanceof Character) {
                $obj->display("Everything I was carrying just slipped from my hands!");
            }
        }

        return true;
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
     * Parse comma-separated arguments preserving quoted strings and parentheses.
     */
    protected function parseArguments(string $str, bool $stripQuotes = false): array
    {
        $str = trim($str);
        if ($str === '') {
            return [];
        }

        $args = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = '';
        $parenDepth = 0;
        $len = strlen($str);

        for ($i = 0; $i < $len; $i++) {
            $char = $str[$i];
            if ($inQuotes) {
                if ($char === $quoteChar) {
                    $inQuotes = false;
                }
                $current .= $char;
            } elseif ($char === '"' || $char === "'") {
                $inQuotes = true;
                $quoteChar = $char;
                $current .= $char;
            } elseif ($char === '(') {
                $parenDepth++;
                $current .= $char;
            } elseif ($char === ')') {
                if ($parenDepth > 0) {
                    $parenDepth--;
                }
                $current .= $char;
            } elseif ($char === ',' && $parenDepth === 0) {
                $args[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }
        if (trim($current) !== '') {
            $args[] = trim($current);
        }

        if (!$stripQuotes) {
            return $args;
        }

        return array_map(function ($arg) {
            if (self::isSingleQuotedString($arg)) {
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

        if (array_key_exists($name, $this->localVars)) {
            $lv = $this->localVars[$name];
            if ($lv instanceof DimxObject) {
                return $lv;
            }
            if (is_string($lv) && $lv !== '') {
                $obj = $this->world->getObjectExt($lv);
                if ($obj !== null) {
                    return $obj;
                }
            }
        }
        if ($this->world !== null) {
            $wv = $this->world->getProperty($name);
            if ($wv instanceof DimxObject) {
                return $wv;
            }
            if (is_string($wv) && $wv !== '') {
                $obj = $this->world->getObjectExt($wv);
                if ($obj !== null) {
                    return $obj;
                }
            }
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
        if ($parts = $this->splitTopLevelBinary($expr, $operators)) {
            $left  = $this->evalValue(trim($parts[0]));
            $op    = $parts[1];
            $right = $this->evalValue(trim($parts[2]));

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

    protected function splitTopLevelBinary(string $expr, array $operators): ?array
    {
        $len = strlen($expr);
        $parenDepth = 0;
        $inQuote = false;
        $quoteChar = '';

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
                foreach ($operators as $op) {
                    $opLen = strlen($op);
                    if (substr($expr, $i, $opLen) === $op) {
                        if (($op === '+' || $op === '-') && $i === 0) {
                            continue;
                        }
                        return [
                            substr($expr, 0, $i),
                            $op,
                            substr($expr, $i + $opLen),
                        ];
                    }
                }
            }
        }

        return null;
    }

    public static function isSingleQuotedString(string $val): bool
    {
        $len = strlen($val);
        if ($len < 2) {
            return false;
        }
        $quote = $val[0];
        if ($quote !== '"' && $quote !== "'") {
            return false;
        }
        if ($val[$len - 1] !== $quote) {
            return false;
        }
        for ($i = 1; $i < $len - 1; $i++) {
            if ($val[$i] === '\\') {
                $i++;
                continue;
            }
            if ($val[$i] === $quote) {
                return false;
            }
        }
        return true;
    }

    /**
     * Evaluate expression or literal into a PHP scalar value.
     */
    protected function evalValue(string $val): mixed
    {
        $val = trim($val);

        // Unwrap outer parentheses if fully enclosed: (expr)
        while (str_starts_with($val, '(') && str_ends_with($val, ')') && self::isFullyEnclosedInParens($val)) {
            $val = trim(substr($val, 1, -1));
        }

        if (self::isSingleQuotedString($val)) {
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
            return $this->world;
        }
        if ($upper === '$OWNER') {
            return $this->owner;
        }
        if ($upper === '$AGENT') {
            return $this->agent;
        }
        if ($upper === '$TARGET') {
            return $this->target;
        }

        // Top-level binary arithmetic & string concatenation outside quotes/parentheses
        if ($bin = $this->splitTopLevelBinary($val, ['+', '-', '*', '/'])) {
            $left = $this->evalValue($bin[0]);
            $op = $bin[1];
            $right = $this->evalValue($bin[2]);
            if (is_numeric($left) && is_numeric($right)) {
                return match ($op) {
                    '+' => $left + $right,
                    '-' => $left - $right,
                    '*' => $left * $right,
                    '/' => $right != 0 ? $left / $right : 0,
                    default => $val,
                };
            } elseif ($op === '+') {
                $leftStr = ($left instanceof Image) ? $left->toHtml() : ($left instanceof DimxObject ? ($left->name ?: $left->id) : (string)$left);
                $rightStr = ($right instanceof Image) ? $right->toHtml() : ($right instanceof DimxObject ? ($right->name ?: $right->id) : (string)$right);
                return $leftStr . $rightStr;
            }
        }

        // Function call or array indexing: func(...) or array(...)
        if (preg_match('/^([A-Za-z0-9_]+)\s*\((.*)\)$/s', $val, $fm)) {
            $fn = $fm[1];
            $argStr = trim($fm[2]);

            // Built-in SetLen( collection )
            if (strcasecmp($fn, 'SetLen') === 0) {
                $argVal = $this->evalValue($argStr);
                if (is_array($argVal)) {
                    return count($argVal);
                }
                $arr = $this->getVariable($argStr);
                if (is_array($arr)) {
                    return count($arr);
                }
                return -1;
            }

            // Built-in Split( valuestring, separatorstring )
            if (strcasecmp($fn, 'Split') === 0) {
                $args = $this->parseArguments($argStr);
                $str = (string)$this->evalValue($args[0] ?? '');
                $sep = isset($args[1]) ? (string)$this->evalValue($args[1]) : ',';
                if ($sep === '') {
                    return [$str];
                }
                return explode($sep, $str);
            }

            // Built-in Exists
            if (strcasecmp($fn, 'Exists') === 0) {
                $targetObj = $this->evalValue($argStr);
                if ($targetObj === null || $targetObj === '' || $targetObj === '(empty)') {
                    return false;
                }
                if ($targetObj instanceof DimxObject) {
                    return true;
                }
                if (is_string($targetObj)) {
                    return ($this->world->getObjectExt($targetObj) !== null);
                }
                return false;
            }

            // Built-in RndInt
            if (strcasecmp($fn, 'RndInt') === 0) {
                $args = $this->parseArguments($argStr);
                if (count($args) === 1) {
                    $max = (int)$this->evalValue($args[0]);
                    return rand(1, max(1, $max));
                }
                $min = (int)$this->evalValue($args[0] ?? 1);
                $max = (int)$this->evalValue($args[1] ?? 1);
                return rand($min, $max);
            }

            // Built-in RndSet
            if (strcasecmp($fn, 'RndSet') === 0) {
                $arg = trim($argStr);
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

            // Built-in NewImage
            if (strcasecmp($fn, 'NewImage') === 0) {
                $niArgs = $this->parseArguments($argStr, false);
                $rawUrl = $niArgs[0] ?? '';
                $evalUrl = $this->evalValue($rawUrl);
                $url = is_string($evalUrl) ? trim($evalUrl, "\"'") : (string)$evalUrl;
                $width = isset($niArgs[1]) ? (int)$this->evalValue($niArgs[1]) : 0;
                $height = isset($niArgs[2]) ? (int)$this->evalValue($niArgs[2]) : 0;
                if ($this->world !== null && !empty($this->world->imagesFolder)
                    && !str_starts_with($url, 'http://') && !str_starts_with($url, 'https://') && !str_starts_with($url, '/') && $url !== '') {
                    $url = rtrim($this->world->imagesFolder, '/') . '/' . ltrim($url, '/');
                }
                return new Image($url, $width, $height);
            }

            // Built-in NewItem( container, name, descr, image, attributes )
            if (strcasecmp($fn, 'NewItem') === 0) {
                $niArgs = $this->parseArguments($argStr, false);
                return $this->createNewItem(
                    $niArgs[0] ?? '',
                    $niArgs[1] ?? '',
                    $niArgs[2] ?? '',
                    $niArgs[3] ?? '',
                    $niArgs[4] ?? ''
                );
            }

            // Check if $fn is an array / set
            $arr = $this->getVariable($fn);
            if (is_array($arr)) {
                $idx = (int)$this->evalValue($argStr);
                return $this->getArrayElement($fn, $idx);
            }

            // Check if $fn is a custom script Function/Sub in world events
            if ($this->world !== null) {
                $ev = $this->world->getEvent($fn);
                if ($ev !== null || isset($this->world->events[$fn])) {
                    $rawArgs = ($argStr !== '') ? $this->parseArguments($argStr) : [];
                    $evalArgs = [];
                    foreach ($rawArgs as $a) {
                        $evalArgs[] = $this->evalValue($a);
                    }
                    return $this->world->fireEvent($fn, $this->owner, $this->agent, $this->target, $evalArgs, null);
                }
                // Case-insensitive check on events
                foreach ($this->world->events as $ek => $ev) {
                    if (strcasecmp($ek, $fn) === 0) {
                        $rawArgs = ($argStr !== '') ? $this->parseArguments($argStr) : [];
                        $evalArgs = [];
                        foreach ($rawArgs as $a) {
                            $evalArgs[] = $this->evalValue($a);
                        }
                        return $this->world->fireEvent($ek, $this->owner, $this->agent, $this->target, $evalArgs, null);
                    }
                }
            }
        }

        // Property access e.g. "$WORLD.mode", "$OWNER.Health", "box.open", "x.name", "players(nowplays).name"
        if (preg_match('/^(\$?[A-Za-z0-9_]+(?:\([^\)]*\))?)\.([A-Za-z0-9_]+)$/', $val, $m)) {
            $targetExpr = $m[1];
            $prop = $m[2];
            $obj = $this->resolveObject($targetExpr);
            if ($obj === null) {
                $evalTarget = $this->evalValue($targetExpr);
                if ($evalTarget instanceof DimxObject) {
                    $obj = $evalTarget;
                } elseif (is_string($evalTarget) && $evalTarget !== '') {
                    $obj = $this->world?->getObjectExt($evalTarget);
                }
            }
            if ($obj !== null) {
                $propVal = property_exists($obj, $prop) ? $obj->{$prop} : $obj->getProperty($prop);
                return $propVal;
            }
            return null;
        }

        // Variable lookup in local variables or world properties
        if (array_key_exists($val, $this->localVars)) {
            return $this->localVars[$val];
        }
        $varVal = $this->getVariable($val);
        if ($varVal !== null) {
            return $varVal;
        }

        return $val;
    }
}
