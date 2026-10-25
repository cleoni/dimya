<?php
declare(strict_types=1);

namespace Dimx;

/**
 * DxwLoader
 *
 * Parser capable of reading a DimensioneX .DXW file (from a local file path or remote HTTP/HTTPS URL)
 * and constructing an in-memory World instance with rooms, items, vehicles, links, and characters.
 */
class DxwLoader
{
    protected ?string $baseUrl = null;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = $baseUrl ? rtrim($baseUrl, '/') : null;
    }

    /**
     * Load and parse a .DXW file into a World object.
     *
     * @param string $source File path or HTTP(S) URL
     * @return World
     * @throws \RuntimeException
     */
    public function load(string $source): World
    {
        $content = $this->fetchContent($source);
        return $this->parse($content, $source);
    }

    /**
     * Fetch content from local file or URL.
     */
    protected function fetchContent(string $source): string
    {
        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 15,
                    'user_agent' => 'DimensioneX-PHP-Loader/1.0',
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);
            $data = @file_get_contents($source, false, $context);
            if ($data === false) {
                throw new \RuntimeException("Unable to fetch DXW file from URL: {$source}");
            }
            return $data;
        }

        if (!file_exists($source)) {
            throw new \RuntimeException("DXW file not found: {$source}");
        }

        $data = @file_get_contents($source);
        if ($data === false) {
            throw new \RuntimeException("Unable to read local DXW file: {$source}");
        }
        return $data;
    }

    /**
     * Parse raw DXW string into a World object.
     */
    public function parse(string $rawContent, string $sourceName = ''): World
    {
        // Ensure content is normalized to UTF-8
        if (!mb_check_encoding($rawContent, 'UTF-8')) {
            $rawContent = mb_convert_encoding($rawContent, 'UTF-8', 'ISO-8859-1, Windows-1252, UTF-8');
        }

        $lines = preg_split('/\r\n|\r|\n/', $rawContent);
        $cleanLines = [];
        $accum = '';
        $inQuote = false;

        $totalRaw = count($lines);
        for ($li = 0; $li < $totalRaw; $li++) {
            $rawLine = $lines[$li];

            if (!$inQuote) {
                $trimmed = trim($rawLine);
                // Ignore blank lines and lines starting with comment symbol (') outside quotes
                if ($trimmed === '' || str_starts_with($trimmed, "'")) {
                    continue;
                }
                // If the line starts with DESCRIPTION, read entire line up to end of line
                if (preg_match('/^DESCRIPTION\b/i', $trimmed)) {
                    $len = strlen($trimmed);
                    for ($ci = 0; $ci < $len; $ci++) {
                        if ($trimmed[$ci] === '"') {
                            $inQuote = !$inQuote;
                        }
                    }
                    if ($inQuote) {
                        $accum = $trimmed;
                        continue;
                    } else {
                        $cleanLines[] = $trimmed;
                        continue;
                    }
                }
            }

            // Scan characters of the line, stripping inline comments outside quotes
            $len = strlen($rawLine);
            $lineContent = '';
            for ($ci = 0; $ci < $len; $ci++) {
                $ch = $rawLine[$ci];
                if ($ch === '"') {
                    $inQuote = !$inQuote;
                    $lineContent .= $ch;
                } elseif ($ch === "'" && !$inQuote) {
                    // Inline comment starts here; ignore the rest of this physical line
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

            // An instruction or directive line can span across multiple lines if an unclosed string is open
            if (!$inQuote) {
                $trimmedAccum = trim($accum);
                if ($trimmedAccum !== '') {
                    $cleanLines[] = $trimmedAccum;
                }
                $accum = '';
            }
        }

        if ($accum !== '') {
            $trimmedAccum = trim($accum);
            if ($trimmedAccum !== '') {
                $cleanLines[] = $trimmedAccum;
            }
        }

        $world = new World('world_' . md5($sourceName), 'DimensioneX World');
        $imagesFolder = '';
        $currentSection = 'WORLD';

        $total = count($cleanLines);
        $i = 0;

        while ($i < $total) {
            $line = $cleanLines[$i];
            $tokens = preg_split('/\s+/', $line);
            $directive = strtoupper($tokens[0] ?? '');

            if ($directive === 'WORLD') {
                $currentSection = 'WORLD';
                $i++;
                continue;
            }

            if ($directive === 'END_WORLD') {
                break;
            }

            if ($directive === 'GUI') {
                $currentSection = 'GUI';
                $i++;
                continue;
            }
            if ($directive === 'END_GUI') {
                $currentSection = 'WORLD';
                $i++;
                continue;
            }

            if ($directive === 'ROOMS') {
                $currentSection = 'ROOMS';
                $i++;
                continue;
            }
            if ($directive === 'END_ROOMS') {
                $currentSection = 'WORLD';
                $i++;
                continue;
            }

            if ($directive === 'LINKS') {
                $currentSection = 'LINKS';
                $i++;
                continue;
            }
            if ($directive === 'END_LINKS') {
                $currentSection = 'WORLD';
                $i++;
                continue;
            }

            if ($directive === 'ITEMS') {
                $currentSection = 'ITEMS';
                $i++;
                continue;
            }
            if ($directive === 'END_ITEMS') {
                $currentSection = 'WORLD';
                $i++;
                continue;
            }

            if ($directive === 'CHARACTERS') {
                $currentSection = 'CHARACTERS';
                $i++;
                continue;
            }
            if ($directive === 'END_CHARACTERS') {
                $currentSection = 'WORLD';
                $i++;
                continue;
            }

            if ($directive === 'SCRIPTS' || $directive === 'EVENTS') {
                $currentSection = 'SCRIPTS';
                $i++;
                continue;
            }
            if ($directive === 'END_SCRIPTS' || $directive === 'END_EVENTS') {
                $currentSection = 'WORLD';
                $i++;
                continue;
            }

            if ($directive === 'SETS') {
                $currentSection = 'SETS';
                $i++;
                continue;
            }
            if ($directive === 'END_SETS') {
                $currentSection = 'WORLD';
                $i++;
                continue;
            }

            // Process Section-Specific Blocks
            switch ($currentSection) {
                case 'WORLD':
                    if ($directive === 'NAME') {
                        $world->name = trim(substr($line, strlen($tokens[0])));
                    } elseif ($directive === 'IMAGESFOLDER') {
                        $imagesFolder = trim(substr($line, strlen($tokens[0])));
                        $world->imagesFolder = $imagesFolder;
                    } elseif ($directive === 'IMAGESFOLDER_PUBLIC') {
                        if (empty($imagesFolder)) {
                            $imagesFolder = trim(substr($line, strlen($tokens[0])));
                            $world->imagesFolder = $imagesFolder;
                        }
                    } elseif ($directive === 'IMAGESFOLDER_LOCAL') {
                        if (empty($imagesFolder)) {
                            $imagesFolder = trim(substr($line, strlen($tokens[0])));
                            $world->imagesFolder = $imagesFolder;
                        }
                    } elseif ($directive === 'BGCOLOR') {
                        $world->bgcolor = trim(substr($line, strlen($tokens[0])));
                    } elseif ($directive === 'VERSION') {
                        $world->setProperty('version', trim(substr($line, strlen($tokens[0]))));
                    }
                    $i++;
                    break;

                case 'GUI':
                    if ($directive === 'SCREEN') {
                        if (isset($tokens[1]) && strtoupper($tokens[1]) === 'SIZE' && isset($tokens[2])) {
                            if (preg_match('/^(\d+)x(\d+)$/i', $tokens[2], $dimM)) {
                                $world->screenwidth = (int)$dimM[1];
                                $world->screenheight = (int)$dimM[2];
                            }
                        }
                    } elseif ($directive === 'SCENE') {
                        if (isset($tokens[1]) && strtoupper($tokens[1]) === 'SIZE' && isset($tokens[2])) {
                            if (preg_match('/^(\d+)x(\d+)$/i', $tokens[2], $dimM)) {
                                $world->sceneWidth = (int)$dimM[1];
                                $world->sceneHeight = (int)$dimM[2];
                            }
                        } elseif (isset($tokens[1]) && strtoupper($tokens[1]) === 'LOOK' && isset($tokens[2])) {
                            $world->setProperty('sceneLook', strtoupper($tokens[2]));
                        }
                    } elseif ($directive === 'LOGOSRC') {
                        $world->setProperty('logoSrc', $this->resolveImageUrl(trim(substr($line, strlen($tokens[0]))), $imagesFolder));
                    } elseif ($directive === 'MAP') {
                        $world->setProperty('map', $this->resolveImageUrl(trim(substr($line, strlen($tokens[0]))), $imagesFolder));
                    } elseif ($directive === 'COMPASS') {
                        $world->setProperty('compass', in_array(strtolower($tokens[1] ?? ''), ['true', '1'], true));
                    } elseif ($directive === 'MSGLISTSIZE') {
                        $world->setProperty('msgListSize', (int)($tokens[1] ?? 3));
                    } elseif ($directive === 'SKINS') {
                        $skinsStr = trim(substr($line, strlen($tokens[0])));
                        $world->setProperty('skinslist', $skinsStr);
                        $skinNames = array_map('trim', explode(',', $skinsStr));
                        foreach ($skinNames as $skName) {
                            if ($skName === '') continue;
                            $baseId = preg_replace('/\.dxs$/i', '', $skName);
                            $localDxs = "C:/xampp/htdocs/dimx/skins/{$baseId}/{$baseId}.dxs";
                            $folderUrl = "http://localhost/dimx/skins/{$baseId}/";
                            $skinObj = Skin::loadFromDxs($localDxs, $folderUrl) ?? new Skin($baseId, ucfirst($baseId), $folderUrl);
                            $world->skins[$baseId] = $skinObj;
                        }
                        if (!empty($world->skins)) {
                            $world->defaultSkin = reset($world->skins);
                        }
                    } elseif ($directive === 'SHOW' && isset($tokens[1]) && strtoupper($tokens[1]) === 'PROPERTIES') {
                        $world->setProperty('showproperties', trim(substr($line, strlen($tokens[0]) + strlen($tokens[1]) + 1)));
                    } elseif ($directive === 'HOOKS') {
                        $world->setProperty('hooks', trim(substr($line, strlen($tokens[0]))));
                    }
                    $i++;
                    break;

                case 'ROOMS':
                    if ($directive === 'ROOM') {
                        $roomId = $tokens[1] ?? 'room_' . count($world->rooms);
                        $room = new Room($world, $roomId, $roomId);
                        $isDefault = false;
                        for ($t = 2; $t < count($tokens); $t++) {
                            if (strtoupper($tokens[$t]) === 'DEFAULT') {
                                $isDefault = true;
                                break;
                            }
                        }
                        $i++;
                        while ($i < $total) {
                            $subLine = $cleanLines[$i];
                            $subTokens = preg_split('/\s+/', $subLine);
                            $subDir = strtoupper($subTokens[0] ?? '');

                            if (in_array($subDir, ['ROOM', 'END_ROOMS', 'ITEMS', 'LINKS', 'CHARACTERS'])) {
                                break;
                            }

                            if ($subDir === 'DEFAULT') {
                                $isDefault = true;
                            } elseif ($subDir === 'NAME') {
                                $room->name = trim(substr($subLine, strlen($subTokens[0])));
                            } elseif ($subDir === 'DESCRIPTION') {
                                $room->description = trim(substr($subLine, strlen($subTokens[0])));
                            } elseif ($subDir === 'ATTRLIST') {
                                $room->setAttrList(trim(substr($subLine, strlen($subTokens[0]))));
                            } elseif ($subDir === 'PANEL') {
                                $room->setProperty('panel', trim(substr($subLine, strlen($subTokens[0]))));
                            } elseif ($subDir === 'IMAGE') {
                                $rest = trim(substr($subLine, strlen($subTokens[0])));
                                $imgTokens = preg_split('/\s+/', $rest);
                                $last = end($imgTokens);
                                $imgW = $world->sceneWidth;
                                $imgH = $world->sceneHeight;
                                foreach ($imgTokens as $tok) {
                                    if (preg_match('/^(\d+)x(\d+)$/i', $tok, $dimMatch)) {
                                        $imgW = (int)$dimMatch[1];
                                        $imgH = (int)$dimMatch[2];
                                        break;
                                    }
                                }
                                $room->images['N'] = new Image($this->resolveImageUrl($last, $imagesFolder), $imgW, $imgH);
                            }
                            $i++;
                        }
                        if ($isDefault) {
                            $room->setProperty('DEFAULT', 1);
                            $world->startRoom = $room;
                            $world->defaultRoom = $room->id;
                        } elseif ($world->startRoom === null) {
                            $world->startRoom = $room;
                            $world->defaultRoom = $room->id;
                        }
                        $world->addRoom($room);
                    } else {
                        $i++;
                    }
                    break;

                case 'LINKS':
                    if ($directive === 'LINK' || $directive === 'MLINK') {
                        $linkId = $tokens[1] ?? 'link_' . count($world->links);
                        $fromTo = $tokens[2] ?? '';
                        $dir = $tokens[3] ?? 'N';
                        $isBidir = ($directive === 'LINK');

                        $parts = explode('-', $fromTo);
                        $fromId = $parts[0] ?? '';
                        $toId = $parts[1] ?? '';

                        $fromRoom = $world->getRoom($fromId);
                        $toRoom = $world->getRoom($toId);

                        $link = new Link($world, $linkId, $linkId, $fromRoom, $toRoom, $isBidir, $dir);
                        $i++;
                        while ($i < $total) {
                            $subLine = $cleanLines[$i];
                            $subTokens = preg_split('/\s+/', $subLine);
                            $subDir = strtoupper($subTokens[0] ?? '');

                            if (in_array($subDir, ['LINK', 'MLINK', 'END_LINKS', 'ITEMS', 'ROOMS', 'CHARACTERS'])) {
                                break;
                            }

                            if ($subDir === 'NAME') {
                                $link->name = trim(substr($subLine, strlen($subTokens[0])));
                            } elseif ($subDir === 'DESCRIPTION') {
                                $link->description = trim(substr($subLine, strlen($subTokens[0])));
                            } elseif ($subDir === 'ATTRLIST') {
                                $link->setAttrList(trim(substr($subLine, strlen($subTokens[0]))));
                            } elseif ($subDir === 'ICON') {
                                $link->icon = $this->resolveImageUrl(trim(substr($subLine, strlen($subTokens[0]))), $imagesFolder);
                            } elseif ($subDir === 'IMAGE') {
                                $rest = trim(substr($subLine, strlen($subTokens[0])));
                                $imgTokens = preg_split('/\s+/', $rest);
                                $last = end($imgTokens);
                                $imgW = 0;
                                $imgH = 0;
                                foreach ($imgTokens as $tok) {
                                    if (preg_match('/^(\d+)x(\d+)$/i', $tok, $dimMatch)) {
                                        $imgW = (int)$dimMatch[1];
                                        $imgH = (int)$dimMatch[2];
                                        break;
                                    }
                                }
                                $link->images['N'] = new Image($this->resolveImageUrl($last, $imagesFolder), $imgW, $imgH);
                                $link->image = $link->images['N'];
                            } elseif ($subDir === 'SHOW') {
                                $rest = trim(substr($subLine, strlen($subTokens[0])));
                                if (preg_match('/^(ICON|ONSCREEN|OFFSCREEN)?\s*(-?\d+)\s*,\s*(-?\d+)/i', $rest, $m)) {
                                    $mode = strtoupper($m[1] ?: 'ONSCREEN');
                                    $link->showmode = ($mode === 'ICON') ? AdvObject::SHOW_ICON : (($mode === 'OFFSCREEN') ? AdvObject::SHOW_OFFSCREEN : AdvObject::SHOW_ONSCREEN);
                                    $link->showX = (int)$m[2];
                                    $link->showY = (int)$m[3];
                                } elseif (stripos($rest, 'OFFSCREEN') !== false) {
                                    $link->showmode = AdvObject::SHOW_OFFSCREEN;
                                } elseif (stripos($rest, 'ICON') !== false) {
                                    $link->showmode = AdvObject::SHOW_ICON;
                                    $link->showX = -1;
                                    $link->showY = -1;
                                } elseif (stripos($rest, 'ONSCREEN') !== false) {
                                    $link->showmode = AdvObject::SHOW_ONSCREEN;
                                    $link->showX = -1;
                                    $link->showY = -1;
                                }
                            }
                            $i++;
                        }
                        $world->addLink($link);
                    } else {
                        $i++;
                    }
                    break;

                case 'ITEMS':
                    if ($directive === 'ITEM' || $directive === 'VEHICLE') {
                        $isVehicle = ($directive === 'VEHICLE');
                        $itemId = $tokens[1] ?? 'item_' . count($world->items);
                        $item = $isVehicle ? new Vehicle($world, $itemId, $itemId) : new Item($world, $itemId, $itemId);
                        $position = '';

                        $i++;
                        while ($i < $total) {
                            $subLine = $cleanLines[$i];
                            $subTokens = preg_split('/\s+/', $subLine);
                            $subDir = strtoupper($subTokens[0] ?? '');

                            if (in_array($subDir, ['ITEM', 'VEHICLE', 'END_ITEMS', 'ROOMS', 'LINKS', 'CHARACTERS'])) {
                                break;
                            }

                            if ($subDir === 'NAME') {
                                $item->name = trim(substr($subLine, strlen($subTokens[0])));
                            } elseif ($subDir === 'DESCRIPTION') {
                                $item->description = trim(substr($subLine, strlen($subTokens[0])));
                            } elseif ($subDir === 'POSITION') {
                                $position = trim(substr($subLine, strlen($subTokens[0])));
                            } elseif ($subDir === 'TYPE') {
                                $item->type = trim(substr($subLine, strlen($subTokens[0])));
                            } elseif ($subDir === 'ATTRLIST') {
                                $item->setAttrList(trim(substr($subLine, strlen($subTokens[0]))));
                            } elseif ($subDir === 'ICON') {
                                $item->icon = $this->resolveImageUrl(trim(substr($subLine, strlen($subTokens[0]))), $imagesFolder);
                            } elseif ($subDir === 'IMAGE') {
                                $rest = trim(substr($subLine, strlen($subTokens[0])));
                                $imgTokens = preg_split('/\s+/', $rest);
                                $last = end($imgTokens);
                                $imgW = 0;
                                $imgH = 0;
                                foreach ($imgTokens as $tok) {
                                    if (preg_match('/^(\d+)x(\d+)$/i', $tok, $dimMatch)) {
                                        $imgW = (int)$dimMatch[1];
                                        $imgH = (int)$dimMatch[2];
                                        break;
                                    }
                                }
                                $item->images['N'] = new Image($this->resolveImageUrl($last, $imagesFolder), $imgW, $imgH);
                            } elseif ($subDir === 'SHOW') {
                                $rest = trim(substr($subLine, strlen($subTokens[0])));
                                if (preg_match('/^(ICON|ONSCREEN|OFFSCREEN)?\s*(-?\d+)\s*,\s*(-?\d+)/i', $rest, $m)) {
                                    $mode = strtoupper($m[1] ?: 'ONSCREEN');
                                    $item->showmode = ($mode === 'ICON') ? AdvObject::SHOW_ICON : (($mode === 'OFFSCREEN') ? AdvObject::SHOW_OFFSCREEN : AdvObject::SHOW_ONSCREEN);
                                    $item->showX = (int)$m[2];
                                    $item->showY = (int)$m[3];
                                } elseif (stripos($rest, 'OFFSCREEN') !== false) {
                                    $item->showmode = AdvObject::SHOW_OFFSCREEN;
                                } elseif (stripos($rest, 'ICON') !== false) {
                                    $item->showmode = AdvObject::SHOW_ICON;
                                    $item->showX = -1;
                                    $item->showY = -1;
                                } elseif (stripos($rest, 'ONSCREEN') !== false) {
                                    $item->showmode = AdvObject::SHOW_ONSCREEN;
                                    $item->showX = -1;
                                    $item->showY = -1;
                                }
                            }
                            $i++;
                        }

                        $world->addItem($item);

                        // Position item in room or container if specified
                        if (!empty($position)) {
                            $item->defContainer = $position;
                            $target = $world->getRoom($position) ?? $world->contents[$position] ?? null;
                            if ($target instanceof AdvObject) {
                                $item->move($target);
                            }
                        }
                    } else {
                        $i++;
                    }
                    break;

                case 'CHARACTERS':
                    if ($directive === 'ATTRLIST') {
                        $world->charactersDefaultAttrlist = trim(substr($line, strlen($tokens[0])));
                        $i++;
                        break;
                    }
                    if ($directive === 'CHARACTER') {
                        $charId = $tokens[1] ?? 'char_' . count($world->characters);
                        $isDefault = in_array('DEFAULT', array_map('strtoupper', array_slice($tokens, 2)));
                        $char = new Character($world, $charId, $charId);
                        if (!empty($world->charactersDefaultAttrlist)) {
                            $char->setAttrList($world->charactersDefaultAttrlist);
                        }
                        $position = '';

                        $i++;
                        while ($i < $total) {
                            $subLine = $cleanLines[$i];
                            $subTokens = preg_split('/\s+/', $subLine);
                            $subDir = strtoupper($subTokens[0] ?? '');

                            if (in_array($subDir, ['CHARACTER', 'END_CHARACTERS', 'ITEMS', 'ROOMS', 'LINKS'])) {
                                break;
                            }

                            if ($subDir === 'NAME') {
                                $char->name = trim(substr($subLine, strlen($subTokens[0])));
                            } elseif ($subDir === 'DESCRIPTION') {
                                $char->description = trim(substr($subLine, strlen($subTokens[0])));
                            } elseif ($subDir === 'POSITION') {
                                $position = trim(substr($subLine, strlen($subTokens[0])));
                            } elseif ($subDir === 'ACCEPTS') {
                                $char->accepts = trim(substr($subLine, strlen($subTokens[0])));
                            } elseif ($subDir === 'ATTRLIST') {
                                $char->setAttrList(trim(substr($subLine, strlen($subTokens[0]))));
                            } elseif ($subDir === 'ICON') {
                                $char->icon = $this->resolveImageUrl(trim(substr($subLine, strlen($subTokens[0]))), $imagesFolder);
                            } elseif ($subDir === 'IMAGE') {
                                $rest = trim(substr($subLine, strlen($subTokens[0])));
                                $imgTokens = preg_split('/\s+/', $rest);
                                $last = end($imgTokens);
                                $imgW = 0;
                                $imgH = 0;
                                foreach ($imgTokens as $tok) {
                                    if (preg_match('/^(\d+)x(\d+)$/i', $tok, $dimMatch)) {
                                        $imgW = (int)$dimMatch[1];
                                        $imgH = (int)$dimMatch[2];
                                        break;
                                    }
                                }
                                $char->images['N'] = new Image($this->resolveImageUrl($last, $imagesFolder), $imgW, $imgH);
                            } elseif ($subDir === 'SHOW') {
                                $rest = trim(substr($subLine, strlen($subTokens[0])));
                                if (preg_match('/^(ICON|ONSCREEN|OFFSCREEN)?\s*(-?\d+)\s*,\s*(-?\d+)/i', $rest, $m)) {
                                    $mode = strtoupper($m[1] ?: 'ONSCREEN');
                                    $char->showmode = ($mode === 'ICON') ? AdvObject::SHOW_ICON : (($mode === 'OFFSCREEN') ? AdvObject::SHOW_OFFSCREEN : AdvObject::SHOW_ONSCREEN);
                                    $char->showX = (int)$m[2];
                                    $char->showY = (int)$m[3];
                                } elseif (stripos($rest, 'OFFSCREEN') !== false) {
                                    $char->showmode = AdvObject::SHOW_OFFSCREEN;
                                } elseif (stripos($rest, 'ICON') !== false) {
                                    $char->showmode = AdvObject::SHOW_ICON;
                                    $char->showX = -1;
                                    $char->showY = -1;
                                } elseif (stripos($rest, 'ONSCREEN') !== false) {
                                    $char->showmode = AdvObject::SHOW_ONSCREEN;
                                    $char->showX = -1;
                                    $char->showY = -1;
                                }
                            }
                            $i++;
                        }

                        $world->addCharacter($char);
                        if ($isDefault) {
                            $world->defaultCharacter = $char;
                        }

                        if (!empty($position)) {
                            $char->defContainer = $position;
                            $room = $world->getRoom($position);
                            if ($room !== null) {
                                $char->move($room);
                            }
                        }
                    } else {
                        $i++;
                    }
                    break;

                case 'SETS':
                    if ($directive === 'SET' || $directive === 'ARRAY') {
                        $setId = $tokens[1] ?? '';
                        if ($setId !== '') {
                            $rest = trim(substr($line, strlen($tokens[0]) + strlen($tokens[1]) + 1));
                            $parts = array_map('trim', explode(',', $rest));
                            $setArray = [];
                            foreach ($parts as $p) {
                                if ($p === '') continue;
                                if (str_contains($p, '=')) {
                                    [$k, $v] = explode('=', $p, 2);
                                    $k = trim($k);
                                    $v = trim($v);
                                    if (is_numeric($v)) {
                                        $v = str_contains($v, '.') ? (float)$v : (int)$v;
                                    }
                                    $setArray[$k] = $v;
                                } else {
                                    $setArray[] = $p;
                                }
                            }
                            $world->sets[$setId] = $setArray;
                            $world->setProperty($setId, $setArray);
                        }
                    }
                    $i++;
                    break;

                case 'SCRIPTS':
                    if ($directive === 'EVENT' || $directive === 'SUB' || $directive === 'FUNCTION') {
                        $eventQual = $tokens[1] ?? '';
                        $eventType = $directive;
                        $actionLines = [];
                        $i++;
                        while ($i < $total) {
                            $subLine = $cleanLines[$i];
                            $subTokens = preg_split('/\s+/', $subLine);
                            $subDir = strtoupper($subTokens[0] ?? '');

                            if (in_array($subDir, ['EVENT', 'SUB', 'FUNCTION', 'END_SCRIPTS', 'END_EVENTS', 'ROOMS', 'ITEMS', 'CHARACTERS', 'LINKS'])) {
                                break;
                            }
                            if ($subDir === 'END_EVENT' || $subDir === 'END_SUB' || $subDir === 'END_FUNCTION') {
                                $i++;
                                break;
                            }
                            $actionLines[] = $subLine;
                            $i++;
                        }

                        $actionText = implode("\n", $actionLines);
                        $event = new Event($eventQual, $eventType);
                        $event->actions = $actionText;
                        $event->setHandler(function (?DimxObject $owner, mixed $agent, mixed $target, array $input = []) use ($actionText, $world) {
                            $runner = new ActionsRunner($world, $owner, $agent, $target, $input);
                            return $runner->run($actionText);
                        });
                        $world->registerEvent($event);
                    } else {
                        $i++;
                    }
                    break;

                default:
                    $i++;
                    break;
            }
        }

        // Resolve initial positioning for all items and characters (including rndSet expressions)
        foreach ($world->items as $itm) {
            if ($itm->container === null && !empty($itm->defContainer)) {
                $itm->reset();
            }
        }
        foreach ($world->characters as $chr) {
            if ($chr->container === null && !empty($chr->defContainer)) {
                $chr->reset();
            }
        }

        if ($world->defaultCharacter === null) {
            $world->getDefaultCharacter();
        }

        return $world;
    }

    /**
     * Resolves an image URL relative to IMAGESFOLDER and base URL.
     */
    protected function resolveImageUrl(string $rawUrl, string $imagesFolder): string
    {
        $rawUrl = trim($rawUrl);
        if (str_starts_with($rawUrl, 'http://') || str_starts_with($rawUrl, 'https://')) {
            return $rawUrl;
        }

        if (str_starts_with($rawUrl, '/')) {
            return $this->baseUrl ? $this->baseUrl . $rawUrl : $rawUrl;
        }

        $folder = rtrim($imagesFolder, '/');
        if (!empty($folder)) {
            if (str_starts_with($folder, '/')) {
                return $this->baseUrl ? "{$this->baseUrl}{$folder}/{$rawUrl}" : "{$folder}/{$rawUrl}";
            }
            return "{$folder}/{$rawUrl}";
        }

        return $rawUrl;
    }
}
