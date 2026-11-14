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

    /**
     * Log an error message and treat it like a Display instruction to output
     * the error description to the appropriate console (e.g. $AGENT or $OWNER).
     */
    public function displayError(string $errMsg): void
    {
        $logMsg = $errMsg . ($this->fileShort !== '' && $this->currLine > 0 ? " near line {$this->currLine} in {$this->fileShort}" : "");
        $this->log($logMsg);

        $destination = ($this->agent instanceof DimxObject)
            ? $this->agent
            : (($this->owner instanceof DimxObject) ? $this->owner : $this->world);

        if ($destination instanceof DimxObject && method_exists($destination, 'display')) {
            $destination->display($errMsg);
        } elseif ($destination instanceof Room) {
            foreach ($destination->getOccupants() as $occ) {
                if (method_exists($occ, 'display')) {
                    $occ->display($errMsg);
                }
            }
        } elseif ($destination instanceof World) {
            $destination->broadcast($errMsg);
        }
    }

    public function setArrayElement(string $name, int $index, mixed $val): void
    {
        if (array_key_exists($name, $this->localVars) && is_array($this->localVars[$name])) {
            $key = array_key_exists(0, $this->localVars[$name]) ? max(0, $index - 1) : $index;
            $this->localVars[$name][$key] = $val;
            return;
        }

        if ($this->world !== null) {
            $arr = $this->world->sets[$name] ?? $this->world->getProperty($name);
            if (!is_array($arr)) {
                $arr = [];
            }
            $key = array_key_exists(0, $arr) ? max(0, $index - 1) : $index;
            $arr[$key] = $val;
            $this->world->sets[$name] = $arr;
            $this->world->setProperty($name, $arr);
            return;
        }

        if (!isset($this->localVars[$name]) || !is_array($this->localVars[$name])) {
            $this->localVars[$name] = [];
        }
        $key = array_key_exists(0, $this->localVars[$name]) ? max(0, $index - 1) : $index;
        $this->localVars[$name][$key] = $val;
    }

    public function setSetElement(string $name, string $key, mixed $val): void
    {
        if (array_key_exists($name, $this->localVars)) {
            if (!is_array($this->localVars[$name])) {
                $this->localVars[$name] = [];
            }
            $targetKey = $key;
            foreach (array_keys($this->localVars[$name]) as $k) {
                if (strcasecmp((string)$k, $key) === 0) {
                    $targetKey = $k;
                    break;
                }
            }
            $this->localVars[$name][$targetKey] = $val;
            return;
        }

        if ($this->world !== null) {
            $arr = $this->world->sets[$name] ?? $this->world->getProperty($name);
            if (!is_array($arr)) {
                $arr = [];
            }
            $targetKey = $key;
            foreach (array_keys($arr) as $k) {
                if (strcasecmp((string)$k, $key) === 0) {
                    $targetKey = $k;
                    break;
                }
            }
            $arr[$targetKey] = $val;
            $this->world->sets[$name] = $arr;
            $this->world->setProperty($name, $arr);
            return;
        }

        if (!isset($this->localVars[$name]) || !is_array($this->localVars[$name])) {
            $this->localVars[$name] = [];
        }
        $this->localVars[$name][$key] = $val;
    }

    public function getArrayElement(string $name, int $index): mixed
    {
        $arr = $this->getVariable($name);
        if (is_array($arr)) {
            if (array_key_exists(0, $arr)) {
                $idx0 = max(0, $index - 1);
                return $arr[$idx0] ?? $arr[$index] ?? null;
            }
            return $arr[$index] ?? null;
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

                try {
                    $condVal = $this->evalCondition($condClean);
                } catch (\Throwable $e) {
                    $this->displayError("ERROR: " . $e->getMessage());

                    // Skip the entire IF block to matching END_IF and proceed to the next instruction
                    $nesting = 1;
                    $i++;
                    while ($i < $count) {
                        $sub = $lines[$i]['text'];
                        if (preg_match('/^IF\b/i', $sub)) {
                            $nesting++;
                        } elseif (preg_match('/^(END_IF|END\s+IF|ENDIF)\b/i', $sub)) {
                            $nesting--;
                            if ($nesting === 0) {
                                $this->currLine = $lines[$i]['line'];
                                $i++;
                                break;
                            }
                        }
                        $i++;
                    }
                    continue;
                }

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
                try {
                    $collection = $this->evalValue($collExpr);
                } catch (\Throwable $e) {
                    $this->displayError("ERROR: " . $e->getMessage());

                    // Skip the entire FOR block to matching NEXT and proceed to next instruction
                    $nesting = 1;
                    $i++;
                    while ($i < $count) {
                        $sub = $lines[$i]['text'];
                        if (preg_match('/^FOR\b/i', $sub)) {
                            $nesting++;
                        } elseif (preg_match('/^NEXT\b/i', $sub)) {
                            $nesting--;
                            if ($nesting === 0) {
                                $this->currLine = $lines[$i]['line'];
                                $i++;
                                break;
                            }
                        }
                        $i++;
                    }
                    continue;
                }
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
                try {
                    $startVal = (int)$this->evalValue($fm[2]);
                    $endVal = (int)$this->evalValue($fm[3]);
                    $step = isset($fm[5]) ? (int)$this->evalValue($fm[5]) : 1;
                } catch (\Throwable $e) {
                    $this->displayError("ERROR: " . $e->getMessage());

                    // Skip the entire FOR block to matching NEXT and proceed to next instruction
                    $nesting = 1;
                    $i++;
                    while ($i < $count) {
                        $sub = $lines[$i]['text'];
                        if (preg_match('/^FOR\b/i', $sub)) {
                            $nesting++;
                        } elseif (preg_match('/^NEXT\b/i', $sub)) {
                            $nesting--;
                            if ($nesting === 0) {
                                $this->currLine = $lines[$i]['line'];
                                $i++;
                                break;
                            }
                        }
                        $i++;
                    }
                    continue;
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
            try {
                $stmtRes = $this->executeStatement($line);
            } catch (\Throwable $e) {
                $this->displayError("ERROR: " . $e->getMessage());
                $i++;
                continue;
            }
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
                        } elseif (is_string($obj->{$propName})) {
                            $obj->{$propName} = self::formatValue($val);
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

        // 1c. Array/Set element assignment e.g. "score(i) = 0" or "s('key') = val"
        if (preg_match('/^([A-Za-z0-9_]+)\s*\(\s*(.+?)\s*\)\s*=\s*(.*)$/', $line, $m) && !str_contains($m[1], '.')) {
            $arrName = $m[1];
            $rawKey = $this->evalValue($m[2]);
            $val = $this->evalValue($m[3]);
            if (is_int($rawKey) || ctype_digit((string)$rawKey)) {
                $this->setArrayElement($arrName, (int)$rawKey, $val);
            } else {
                $strKey = is_string($rawKey) ? trim($rawKey, "\"'") : (string)$rawKey;
                $this->setSetElement($arrName, $strKey, $val);
            }
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
            if (in_array($verbCandidate, ['DEBUG', 'DISPLAY', 'PRINT', 'PRINTRIGHT', 'SPEAK', 'BROADCAST', 'CALL', 'DROPITEMS', 'RESET', 'GOAL'], true)) {
                $verb = $verbCandidate;
                $argString = $vm[2];
            } elseif ($this->world?->getEvent($vm[1]) !== null) {
                return $this->actCall([], $line);
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
            default      => ($this->world?->getEvent($parts[0] ?? '') !== null) ? $this->actCall([], $line) : true,
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
                $msg = self::formatValue($this->evalValue($rawMsg));
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
            $cmd = self::formatValue($this->evalValue((string)$args[0]));
        } else {
            $dest = $this->resolveObject((string)$args[0]);
            $target = $dest ?? ($this->agent instanceof DimxObject ? $this->agent : null) ?? $this->world;
            $cmd = self::formatValue($this->evalValue((string)$args[1]));
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
                $val = self::formatValue($this->evalValue((string)$arg));
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
            $chosen = self::formatValue($evalRes);
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
            $chosen = self::formatValue($evalRes);
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
        $msg = self::formatValue($this->evalValue((string)$rawMsg));
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
     * Goal [message] (SmallBasic 3.9.10)
     * Signals that the game objective has been achieved and the adventure is completed.
     */
    protected function actGoal(array $args): bool
    {
        $agentName = ($this->agent instanceof ManageableObject)
            ? $this->agent->name
            : (is_string($this->agent) && $this->agent !== '' ? $this->agent : 'A player');

        $score = 0;
        if ($this->agent instanceof Player) {
            $this->agent->score += 10;
            $score = $this->agent->score;
        }

        $msgArg = !empty($args[0])
            ? self::formatValue($this->evalValue((string)$args[0]))
            : null;

        return $this->world->goal($agentName, $msgArg, $score);
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
     * Helper to collect items directly contained inside an object (or world) into a result set.
     * Matches DimensioneX DimxParser.innerGetItemsIn().
     *
     * @param mixed $o Object, world, container ID, or collection element
     * @param array<string, AdvObject> &$newSet Result set indexed by item ID
     */
    protected function innerGetItemsIn(mixed $o, array &$newSet): void
    {
        if ($o === null) {
            return;
        }
        if (is_array($o)) {
            foreach ($o as $sub) {
                $this->innerGetItemsIn($sub, $newSet);
            }
            return;
        }
        if (is_string($o)) {
            $t = trim($o);
            if ($t === '') {
                return;
            }
            $resolved = $this->resolveObject($t);
            if ($resolved === null && $this->world !== null) {
                $resolved = $this->world->getObjectExt($t);
            }
            $o = $resolved;
            if ($o === null) {
                return;
            }
        }
        if ($o instanceof World) {
            if (isset($o->items) && is_array($o->items)) {
                foreach ($o->items as $item) {
                    if ($item instanceof Item || (method_exists($item, 'isanItem') && $item->isanItem())) {
                        $newSet[$item->id] = $item;
                    }
                }
            }
            if (isset($o->contents) && is_array($o->contents)) {
                foreach ($o->contents as $item) {
                    if ($item instanceof Item || (method_exists($item, 'isanItem') && $item->isanItem())) {
                        $newSet[$item->id] = $item;
                    }
                }
            }
            return;
        }
        if ($o instanceof AdvObject) {
            $contents = $o->getContents();
            foreach ($contents as $dxo) {
                if ($dxo instanceof Item || (method_exists($dxo, 'isanItem') && $dxo->isanItem())) {
                    $newSet[$dxo->id] = $dxo;
                }
            }
        }
    }

    /**
     * Searches for objects inside container by primary type prefix or subtype.
     * Matches DimensioneX funGetObjectsType (§3.10.14).
     *
     * @param mixed $container Object instance, array of items/objects, room, or container ID
     * @param string $type The type prefix or subtype to match
     * @param bool $matchSubtype If true, matches dotted subtype parts; if false, prefix match
     * @return array<string, DimxObject>
     */
    protected function funGetObjectsType(mixed $container, string $type, bool $matchSubtype = false): array
    {
        $newSet = [];
        if ($type === '' || $container === null) {
            return $newSet;
        }

        $checkObject = function(mixed $dxo) use (&$newSet, $type, $matchSubtype): void {
            if (!($dxo instanceof DimxObject)) {
                if (is_string($dxo) && $dxo !== '' && $this->world !== null) {
                    $dxo = $this->world->getObjectExt($dxo);
                }
            }
            if ($dxo instanceof DimxObject) {
                $objType = (string)($dxo->getProperty('type') ?? (property_exists($dxo, 'type') ? $dxo->type : ''));
                if ($matchSubtype) {
                    $parts = explode('.', $objType);
                    foreach ($parts as $p) {
                        if (strcasecmp(trim($p), $type) === 0) {
                            $newSet[$dxo->id] = $dxo;
                            break;
                        }
                    }
                } else {
                    if (stripos($objType, $type) === 0) {
                        $newSet[$dxo->id] = $dxo;
                    }
                }
            }
        };

        if (is_array($container)) {
            foreach ($container as $elem) {
                $checkObject($elem);
            }
            return $newSet;
        }

        if (is_string($container)) {
            $resolved = $this->resolveObject($container);
            if ($resolved === null && $this->world !== null) {
                $resolved = $this->world->getObjectExt($container);
            }
            $container = $resolved;
        }

        if ($container instanceof AdvObject) {
            $contents = $container->getContents();
            foreach ($contents as $dxo) {
                $checkObject($dxo);
            }
        } elseif ($container instanceof World) {
            if (isset($container->items) && is_array($container->items)) {
                foreach ($container->items as $dxo) {
                    $checkObject($dxo);
                }
            }
        }

        return $newSet;
    }

    /**
     * Checks if a string has unbalanced parentheses outside of quoted string literals.
     */
    protected function hasUnbalancedParens(string $s): bool
    {
        $len = strlen($s);
        $depth = 0;
        $inQuote = false;
        $quoteChar = '';
        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];
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
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth < 0) {
                    return true;
                }
            }
        }
        return ($depth !== 0);
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

            if ($op === '=' || $op === '<>') {
                $isEqual = self::compareEquality($left, $right);
                return ($op === '=') ? $isEqual : !$isEqual;
            }

            if ($left instanceof DimxObject) {
                $left = $left->id;
            }
            if ($right instanceof DimxObject) {
                $right = $right->id;
            }

            return match ($op) {
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
     * Format an array into a SmallBasic string representation: [key1=val,key2=val,...]
     */
    public static function formatArray(array $arr, int $depth = 0): string
    {
        if ($depth > 10) {
            return '[...]';
        }
        $parts = [];
        foreach ($arr as $k => $v) {
            $parts[] = $k . '=' . self::formatValue($v, $depth + 1);
        }
        return '[' . implode(',', $parts) . ']';
    }

    /**
     * Format any value into its SmallBasic string representation.
     */
    public static function formatValue(mixed $val, int $depth = 0): string
    {
        if ($val === null) {
            return '';
        }
        if (is_bool($val)) {
            return $val ? 'true' : 'false';
        }
        if (is_array($val)) {
            return self::formatArray($val, $depth);
        }
        if (is_object($val)) {
            if ($val instanceof Image) {
                return $val->toHtml();
            }
            if ($val instanceof DimxObject) {
                return $val->name ?: $val->id;
            }
            if (method_exists($val, '__toString')) {
                return (string)$val;
            }
            return get_class($val);
        }
        return (string)$val;
    }

    /**
     * Compare equality of two operands taking into account DimxObject instances,
     * player account IDs (numericId / nickname), and scalar values.
     */
    public static function compareEquality(mixed $left, mixed $right): bool
    {
        if ($left === $right) {
            return true;
        }

        if ($left === null || $right === null) {
            return $left === $right;
        }

        // Both are DimxObjects
        if ($left instanceof DimxObject && $right instanceof DimxObject) {
            // Same object ID
            if ($left->id !== '' && $left->id === $right->id) {
                return true;
            }
            // For Player instances, compare database account numericId or nickname
            if ($left instanceof Player && $right instanceof Player) {
                if ($left->numericId !== null && $right->numericId !== null && (int)$left->numericId > 0) {
                    if ((int)$left->numericId === (int)$right->numericId) {
                        return true;
                    }
                }
                if ($left->name !== '' && $right->name !== '') {
                    if (strcasecmp($left->name, $right->name) === 0) {
                        return true;
                    }
                }
            }
            // Same class and same non-empty name
            if (get_class($left) === get_class($right) && $left->name !== '' && $right->name !== '') {
                if (strcasecmp($left->name, $right->name) === 0) {
                    return true;
                }
            }
            return false;
        }

        // One is DimxObject and the other is a scalar
        if ($left instanceof DimxObject && is_scalar($right)) {
            return self::compareObjectToScalar($left, $right);
        }
        if ($right instanceof DimxObject && is_scalar($left)) {
            return self::compareObjectToScalar($right, $left);
        }

        // Numeric comparison
        if (is_numeric($left) && is_numeric($right)) {
            return (float)$left == (float)$right;
        }

        // Boolean comparison
        if (is_bool($left) || is_bool($right)) {
            $toBool = function ($v) {
                if (is_bool($v)) return $v;
                if (is_numeric($v)) return (int)$v !== 0;
                if (is_string($v)) {
                    $u = strtoupper(trim($v));
                    if ($u === 'FALSE' || $u === '0' || $u === '') return false;
                    if ($u === 'TRUE' || $u === '1') return true;
                }
                return (bool)$v;
            };
            return $toBool($left) === $toBool($right);
        }

        // String comparison
        return (string)$left === (string)$right;
    }

    public static function compareObjectToScalar(DimxObject $obj, mixed $val): bool
    {
        $str = trim((string)$val, "\"' \t");
        if ($obj->id !== '' && strcasecmp($obj->id, $str) === 0) {
            return true;
        }
        if ($obj->name !== '' && strcasecmp($obj->name, $str) === 0) {
            return true;
        }
        if ($obj instanceof Player && is_numeric($val) && $obj->numericId !== null && (int)$obj->numericId > 0) {
            return (int)$obj->numericId === (int)$val;
        }
        return false;
    }

    /**
     * Evaluate expression or literal into a PHP scalar value.
     */
    protected function evalValue(string $val): mixed
    {
        $val = trim($val);

        if ($this->hasUnbalancedParens($val)) {
            throw new \RuntimeException("Syntax error: unbalanced parentheses in expression '{$val}'");
        }

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

        // Top-level logical OR / AND outside parentheses and quotes (for expressions and assignments: res = res Or ...)
        if ($parts = $this->splitTopLevelLogical($val, 'OR')) {
            return (bool)$this->evalCondition(trim($parts[0])) || (bool)$this->evalCondition(trim($parts[1]));
        }
        if ($parts = $this->splitTopLevelLogical($val, 'AND')) {
            return (bool)$this->evalCondition(trim($parts[0])) && (bool)$this->evalCondition(trim($parts[1]));
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
                $leftStr = self::formatValue($left);
                $rightStr = self::formatValue($right);
                return $leftStr . $rightStr;
            }
        }

        // Function call or array indexing: func(...) or array(...)
        if (preg_match('/^([A-Za-z0-9_]+)\s*\((.*)\)$/s', $val, $fm)) {
            $fn = $fm[1];
            $argStr = trim($fm[2]);

            // Built-in Asc( c )
            if (strcasecmp($fn, 'Asc') === 0) {
                $args = $this->parseArguments($argStr);
                $s = isset($args[0]) ? self::formatValue($this->evalValue($args[0])) : '';
                return ($s !== '') ? ord($s[0]) : -1;
            }

            // Built-in Chr( n )
            if (strcasecmp($fn, 'Chr') === 0) {
                $args = $this->parseArguments($argStr);
                $n = isset($args[0]) ? (int)$this->evalValue($args[0]) : 0;
                return chr($n & 0xFF);
            }

            // Built-in LCase( string )
            if (strcasecmp($fn, 'LCase') === 0) {
                $args = $this->parseArguments($argStr);
                $s = isset($args[0]) ? self::formatValue($this->evalValue($args[0])) : '';
                return strtolower($s);
            }

            // Built-in UCase( string )
            if (strcasecmp($fn, 'UCase') === 0) {
                $args = $this->parseArguments($argStr);
                $s = isset($args[0]) ? self::formatValue($this->evalValue($args[0])) : '';
                return strtoupper($s);
            }

            // Built-in Left( string, nchars )
            if (strcasecmp($fn, 'Left') === 0) {
                $args = $this->parseArguments($argStr);
                $s = isset($args[0]) ? self::formatValue($this->evalValue($args[0])) : '';
                $len = isset($args[1]) ? (int)$this->evalValue($args[1]) : 0;
                if ($len <= 0) {
                    return '';
                }
                if ($len >= strlen($s)) {
                    return $s;
                }
                return substr($s, 0, $len);
            }

            // Built-in Right( string, nchars )
            if (strcasecmp($fn, 'Right') === 0) {
                $args = $this->parseArguments($argStr);
                $s = isset($args[0]) ? self::formatValue($this->evalValue($args[0])) : '';
                $len = isset($args[1]) ? (int)$this->evalValue($args[1]) : 0;
                if ($len <= 0) {
                    return '';
                }
                if ($len >= strlen($s)) {
                    return $s;
                }
                return substr($s, -$len);
            }

            // Built-in Mid( string, start [, nchars] )
            if (strcasecmp($fn, 'Mid') === 0) {
                $args = $this->parseArguments($argStr);
                $s = isset($args[0]) ? self::formatValue($this->evalValue($args[0])) : '';
                $start = isset($args[1]) ? (int)$this->evalValue($args[1]) : 1;
                $startIdx = $start - 1; // 1-based index in SmallBasic
                if ($startIdx < 0) {
                    $startIdx = 0;
                }
                if ($startIdx >= strlen($s)) {
                    return '';
                }
                if (isset($args[2])) {
                    $nchars = (int)$this->evalValue($args[2]);
                    if ($nchars <= 0) {
                        return '';
                    }
                    return substr($s, $startIdx, $nchars);
                }
                return substr($s, $startIdx);
            }

            // Built-in Replace( haystack, needle, replacement )
            if (strcasecmp($fn, 'Replace') === 0) {
                $args = $this->parseArguments($argStr);
                $haystack = isset($args[0]) ? self::formatValue($this->evalValue($args[0])) : '';
                $arg1 = isset($args[1]) ? $this->evalValue($args[1]) : '';
                if (is_array($arg1)) {
                    foreach ($arg1 as $search => $replace) {
                        if ((string)$search !== '') {
                            $haystack = str_ireplace((string)$search, self::formatValue($replace), $haystack);
                        }
                    }
                    return $haystack;
                }
                $needle = self::formatValue($arg1);
                $replacement = isset($args[2]) ? self::formatValue($this->evalValue($args[2])) : '';
                if ($needle === '') {
                    return $haystack;
                }
                // DimensioneX funReplace uses case-insensitive string replacement (Utils.stringReplace ignorecase=true)
                return str_ireplace($needle, $replacement, $haystack);
            }

            // Built-in Format( number, strFormat )
            if (strcasecmp($fn, 'Format') === 0) {
                $args = $this->parseArguments($argStr);
                $num = isset($args[0]) ? $this->evalValue($args[0]) : 0;
                $fmt = isset($args[1]) ? (string)$this->evalValue($args[1]) : '';
                if ($fmt === '') {
                    return (string)$num;
                }
                try {
                    return sprintf($fmt, $num);
                } catch (\Throwable $e) {
                    return (string)$num;
                }
            }

            // Built-in InStr( haystack, needle [, startpos] )
            if (strcasecmp($fn, 'InStr') === 0) {
                $args = $this->parseArguments($argStr);
                $haystack = isset($args[0]) ? self::formatValue($this->evalValue($args[0])) : '';
                $needle = isset($args[1]) ? self::formatValue($this->evalValue($args[1])) : '';
                if ($needle === '') {
                    return 0;
                }
                $startPos = isset($args[2]) ? (int)$this->evalValue($args[2]) : 1;
                $startIdx = $startPos - 1; // 1-based index in SmallBasic
                if ($startIdx < 0) {
                    $startIdx = 0;
                }
                if ($startIdx >= strlen($haystack)) {
                    return 0;
                }
                $pos = stripos($haystack, $needle, $startIdx);
                return ($pos !== false) ? ($pos + 1) : 0;
            }

            // Built-in InStrCount( haystack, needle )
            if (strcasecmp($fn, 'InStrCount') === 0) {
                $args = $this->parseArguments($argStr);
                $haystack = isset($args[0]) ? self::formatValue($this->evalValue($args[0])) : '';
                $needle = isset($args[1]) ? self::formatValue($this->evalValue($args[1])) : '';
                $tl = strlen($haystack);
                $sl = strlen($needle);
                if ($sl === 0 || $tl < $sl) {
                    return 0;
                }
                $count = 0;
                for ($i = 0; $i <= $tl - $sl; $i++) {
                    if (strcasecmp(substr($haystack, $i, $sl), $needle) === 0) {
                        $count++;
                    }
                }
                return $count;
            }

            // Built-in Abs( n )
            if (strcasecmp($fn, 'Abs') === 0) {
                $args = $this->parseArguments($argStr);
                $n = isset($args[0]) ? $this->evalValue($args[0]) : 0;
                return is_numeric($n) ? abs($n + 0) : 0;
            }

            // Built-in Int( n )
            if (strcasecmp($fn, 'Int') === 0) {
                $args = $this->parseArguments($argStr);
                $n = isset($args[0]) ? $this->evalValue($args[0]) : 0;
                return (int)$n;
            }

            // Built-in Log( number )
            if (strcasecmp($fn, 'Log') === 0) {
                $args = $this->parseArguments($argStr);
                $n = isset($args[0]) ? (float)$this->evalValue($args[0]) : 0.0;
                return ($n > 0) ? log($n) : 0.0;
            }

            // Built-in Rnd()
            if (strcasecmp($fn, 'Rnd') === 0) {
                return (float)mt_rand() / (float)mt_getrandmax();
            }

            // Built-in Round( number [, decimals] )
            if (strcasecmp($fn, 'Round') === 0) {
                $args = $this->parseArguments($argStr);
                $n = isset($args[0]) ? (float)$this->evalValue($args[0]) : 0.0;
                $decimals = isset($args[1]) ? (int)$this->evalValue($args[1]) : 0;
                $res = round($n, $decimals);
                return ($decimals <= 0) ? (int)$res : $res;
            }

            // Built-in Sqr( x )
            if (strcasecmp($fn, 'Sqr') === 0) {
                $args = $this->parseArguments($argStr);
                $n = isset($args[0]) ? (float)$this->evalValue($args[0]) : 0.0;
                return ($n >= 0) ? sqrt($n) : 0.0;
            }

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

            // Built-in SetContainsKey( set, key )
            if (strcasecmp($fn, 'SetContainsKey') === 0) {
                $args = $this->parseArguments($argStr);
                $rawSet = isset($args[0]) ? $this->evalValue($args[0]) : null;
                if (!is_array($rawSet) && isset($args[0])) {
                    $arr = $this->getVariable($args[0]);
                    if (is_array($arr)) {
                        $rawSet = $arr;
                    }
                }
                if (!is_array($rawSet)) {
                    return false;
                }

                $keyVal = isset($args[1]) ? $this->evalValue($args[1]) : '';
                if ($keyVal instanceof DimxObject) {
                    $searchKey = (string)$keyVal->id;
                } elseif ($keyVal === null) {
                    $searchKey = '';
                } else {
                    $searchKey = (string)$keyVal;
                }

                if (array_key_exists($searchKey, $rawSet)) {
                    return true;
                }
                foreach (array_keys($rawSet) as $k) {
                    if (strcasecmp((string)$k, $searchKey) === 0) {
                        return true;
                    }
                }
                return false;
            }

            // Built-in Split( valuestring, separatorstring )
            if (strcasecmp($fn, 'Split') === 0) {
                $args = $this->parseArguments($argStr);
                $str = (string)$this->evalValue($args[0] ?? '');
                $sep = isset($args[1]) ? (string)$this->evalValue($args[1]) : ',';
                if ($sep === '') {
                    return [1 => $str];
                }
                $exploded = explode($sep, $str);
                $res = [];
                $idx = 1;
                foreach ($exploded as $item) {
                    $res[$idx++] = $item;
                }
                return $res;
            }

            // Built-in NewArray( [string] )
            if (strcasecmp($fn, 'NewArray') === 0) {
                if ($argStr === '') {
                    return [];
                }
                $raw = $this->evalValue($argStr);
                $str = is_string($raw) ? trim($raw, "\"'") : (string)$raw;
                if ($str === '') {
                    return [];
                }
                $parts = array_map('trim', explode(',', $str));
                $res = [];
                $idx = 1;
                foreach ($parts as $p) {
                    $res[$idx++] = $p;
                }
                return $res;
            }

            // Built-in Copy( array_or_set )
            if (strcasecmp($fn, 'Copy') === 0) {
                $rawVal = $this->evalValue($argStr);
                if (!is_array($rawVal)) {
                    $arr = $this->getVariable($argStr);
                    if (is_array($arr)) {
                        $rawVal = $arr;
                    }
                }
                if (!is_array($rawVal)) {
                    return $rawVal;
                }
                $deepCopy = function(array $in) use (&$deepCopy): array {
                    $out = [];
                    foreach ($in as $k => $v) {
                        $out[$k] = is_array($v) ? $deepCopy($v) : $v;
                    }
                    return $out;
                };
                return $deepCopy($rawVal);
            }

            // Built-in NewSet( [string] )
            if (strcasecmp($fn, 'NewSet') === 0) {
                if ($argStr === '' || $argStr === '""' || $argStr === "''") {
                    return [];
                }
                $raw = $this->evalValue($argStr);
                $str = is_string($raw) ? trim($raw, "\"'") : (string)$raw;
                if ($str === '') {
                    return [];
                }
                $parts = explode(',', $str);
                $res = [];
                foreach ($parts as $part) {
                    if ($part === '') {
                        continue;
                    }
                    if (str_contains($part, '=')) {
                        [$k, $v] = explode('=', $part, 2);
                        $k = str_replace(['!1!', '!2!'], [',', '='], trim($k));
                        $v = str_replace(['!1!', '!2!'], [',', '='], trim($v));
                        if (is_numeric($v)) {
                            $v = str_contains($v, '.') ? (float)$v : (int)$v;
                        }
                        $res[$k] = $v;
                    } else {
                        $k = str_replace(['!1!', '!2!'], [',', '='], trim($part));
                        $res[$k] = '';
                    }
                }
                uksort($res, 'strcasecmp');
                return $res;
            }

            // Built-in SetIndexOf( setarray, value )
            if (strcasecmp($fn, 'SetIndexOf') === 0) {
                $args = $this->parseArguments($argStr);
                $rawColl = isset($args[0]) ? $this->evalValue($args[0]) : null;
                if (!is_array($rawColl) && isset($args[0])) {
                    $arr = $this->getVariable($args[0]);
                    if (is_array($arr)) {
                        $rawColl = $arr;
                    }
                }
                if (!is_array($rawColl)) {
                    return 0;
                }
                $searchVal = isset($args[1]) ? $this->evalValue($args[1]) : null;
                $searchStr = ($searchVal instanceof DimxObject) ? (string)$searchVal->id : (string)$searchVal;
                $idx = 1;
                foreach ($rawColl as $elem) {
                    $elemStr = ($elem instanceof DimxObject) ? (string)$elem->id : (string)$elem;
                    if (strcasecmp($searchStr, $elemStr) === 0) {
                        return $idx;
                    }
                    $idx++;
                }
                return 0;
            }

            // Built-in SetKey( set, n )
            if (strcasecmp($fn, 'SetKey') === 0) {
                $args = $this->parseArguments($argStr);
                $rawSet = isset($args[0]) ? $this->evalValue($args[0]) : null;
                if (!is_array($rawSet) && isset($args[0])) {
                    $arr = $this->getVariable($args[0]);
                    if (is_array($arr)) {
                        $rawSet = $arr;
                    }
                }
                if (!is_array($rawSet)) {
                    return '';
                }
                $n = isset($args[1]) ? (int)$this->evalValue($args[1]) : 0;
                $keys = array_keys($rawSet);
                if ($n >= 1 && $n <= count($keys)) {
                    return (string)$keys[$n - 1];
                }
                return '';
            }

            // Built-in SetKeys( set )
            if (strcasecmp($fn, 'SetKeys') === 0) {
                $rawSet = $this->evalValue($argStr);
                if (!is_array($rawSet)) {
                    $arr = $this->getVariable($argStr);
                    if (is_array($arr)) {
                        $rawSet = $arr;
                    }
                }
                if (!is_array($rawSet)) {
                    return [];
                }
                $keys = array_keys($rawSet);
                $res = [];
                $idx = 1;
                foreach ($keys as $k) {
                    $res[$idx++] = (string)$k;
                }
                return $res;
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

            // Built-in getItemsIn( object_or_collection )
            if (strcasecmp($fn, 'getItemsIn') === 0) {
                if ($argStr === '') {
                    return [];
                }
                $rawArgs = $this->parseArguments($argStr);
                $targetExpr = $rawArgs[0] ?? $argStr;
                $argVal = $this->evalValue($targetExpr);
                if (!is_array($argVal)) {
                    $arr = $this->getVariable($targetExpr);
                    if (is_array($arr)) {
                        $argVal = $arr;
                    }
                }
                $newSet = [];
                if (is_array($argVal)) {
                    foreach ($argVal as $elem) {
                        $this->innerGetItemsIn($elem, $newSet);
                    }
                } else {
                    $this->innerGetItemsIn($argVal, $newSet);
                }
                return $newSet;
            }

            // Built-in getObjectsType( container, type ) and getObjectsSubtype( container, subtype )
            if (strcasecmp($fn, 'getObjectsType') === 0 || strcasecmp($fn, 'getObjectsSubtype') === 0) {
                $isSubtype = (strcasecmp($fn, 'getObjectsSubtype') === 0);
                $args = $this->parseArguments($argStr);
                $targetExpr = $args[0] ?? '';
                $typeFilter = isset($args[1]) ? (string)$this->evalValue($args[1]) : '';
                if ($typeFilter === '') {
                    return [];
                }

                $contVal = $this->evalValue($targetExpr);
                if (!is_array($contVal)) {
                    $arr = $this->getVariable($targetExpr);
                    if (is_array($arr)) {
                        $contVal = $arr;
                    }
                }

                return $this->funGetObjectsType($contVal, $typeFilter, $isSubtype);
            }

            // Check if $fn is an array / set
            $arr = $this->getVariable($fn);
            if (is_array($arr)) {
                $evalKey = $this->evalValue($argStr);
                if (is_int($evalKey) || ctype_digit((string)$evalKey)) {
                    return $this->getArrayElement($fn, (int)$evalKey);
                }
                $keyStr = (string)$evalKey;
                if (array_key_exists($keyStr, $arr)) {
                    return $arr[$keyStr];
                }
                foreach ($arr as $k => $v) {
                    if (strcasecmp((string)$k, $keyStr) === 0) {
                        return $v;
                    }
                }
                return null;
            }

            // Check if $fn is a custom script Function/Sub in world events
            if ($this->world !== null) {
                $ev = $this->world->getEvent($fn);
                if ($ev !== null) {
                    $rawArgs = ($argStr !== '') ? $this->parseArguments($argStr) : [];
                    $evalArgs = [];
                    foreach ($rawArgs as $a) {
                        $evalArgs[] = $this->evalValue($a);
                    }
                    return $this->world->fireEvent($ev->id, $this->owner, $this->agent, $this->target, $evalArgs, null);
                }
            }

            // Unknown function or array error: throw exception so it is NOT treated as a literal
            throw new \RuntimeException("Unknown Function or Array: {$fn}");
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
