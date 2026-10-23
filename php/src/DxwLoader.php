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

        foreach ($lines as $line) {
            $line = trim($line);
            // Ignore blank lines and lines starting with comment symbol (')
            if ($line === '' || str_starts_with($line, "'")) {
                continue;
            }
            // If the line starts with DESCRIPTION, read entire line up to end of line
            if (preg_match('/^DESCRIPTION\b/i', $line)) {
                $cleanLines[] = $line;
                continue;
            }
            // Strip inline comments only if outside double quotes
            if (str_contains($line, "'")) {
                $inQuote = false;
                $commentPos = -1;
                $len = strlen($line);
                for ($ci = 0; $ci < $len; $ci++) {
                    $ch = $line[$ci];
                    if ($ch === '"') {
                        $inQuote = !$inQuote;
                    } elseif ($ch === "'" && !$inQuote) {
                        $commentPos = $ci;
                        break;
                    }
                }
                if ($commentPos !== -1) {
                    $line = trim(substr($line, 0, $commentPos));
                    if ($line === '') continue;
                }
            }
            $cleanLines[] = $line;
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

            // Process Section-Specific Blocks
            switch ($currentSection) {
                case 'WORLD':
                    if ($directive === 'NAME') {
                        $world->name = trim(substr($line, strlen($tokens[0])));
                    } elseif ($directive === 'IMAGESFOLDER') {
                        $imagesFolder = trim(substr($line, strlen($tokens[0])));
                    } elseif ($directive === 'BGCOLOR') {
                        $world->bgcolor = trim(substr($line, strlen($tokens[0])));
                    } elseif ($directive === 'VERSION') {
                        $world->setProperty('version', trim(substr($line, strlen($tokens[0]))));
                    }
                    $i++;
                    break;

                case 'ROOMS':
                    if ($directive === 'ROOM') {
                        $roomId = $tokens[1] ?? 'room_' . count($world->rooms);
                        $room = new Room($world, $roomId, $roomId);
                        $i++;
                        while ($i < $total) {
                            $subLine = $cleanLines[$i];
                            $subTokens = preg_split('/\s+/', $subLine);
                            $subDir = strtoupper($subTokens[0] ?? '');

                            if (in_array($subDir, ['ROOM', 'END_ROOMS', 'ITEMS', 'LINKS', 'CHARACTERS'])) {
                                break;
                            }

                            if ($subDir === 'NAME') {
                                $room->name = trim(substr($subLine, strlen($subTokens[0])));
                            } elseif ($subDir === 'DESCRIPTION') {
                                $room->description = trim(substr($subLine, strlen($subTokens[0])));
                            } elseif ($subDir === 'ATTRLIST') {
                                $room->setAttrList(trim(substr($subLine, strlen($subTokens[0]))));
                            } elseif ($subDir === 'IMAGE') {
                                $imgSrc = $this->resolveImageUrl(trim(substr($subLine, strlen($subTokens[0]))), $imagesFolder);
                                $room->images['N'] = new Image($imgSrc, 400, 300);
                            }
                            $i++;
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
                                if (preg_match('/^(ICON|ONSCREEN|OFFSCREEN)?\s*(\d+)\s*,\s*(\d+)/i', $rest, $m)) {
                                    $mode = strtoupper($m[1] ?: 'ONSCREEN');
                                    $item->showmode = ($mode === 'ICON') ? AdvObject::SHOW_ICON : AdvObject::SHOW_ONSCREEN;
                                    $item->showX = (int)$m[2];
                                    $item->showY = (int)$m[3];
                                } elseif (stripos($rest, 'OFFSCREEN') !== false) {
                                    $item->showmode = AdvObject::SHOW_OFFSCREEN;
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
                                if (preg_match('/^(ICON|ONSCREEN|OFFSCREEN)?\s*(\d+)\s*,\s*(\d+)/i', $rest, $m)) {
                                    $mode = strtoupper($m[1] ?: 'ONSCREEN');
                                    $char->showmode = ($mode === 'ICON') ? AdvObject::SHOW_ICON : AdvObject::SHOW_ONSCREEN;
                                    $char->showX = (int)$m[2];
                                    $char->showY = (int)$m[3];
                                } elseif (stripos($rest, 'OFFSCREEN') !== false) {
                                    $char->showmode = AdvObject::SHOW_OFFSCREEN;
                                }
                            }
                            $i++;
                        }

                        $world->addCharacter($char);

                        if (!empty($position)) {
                            $room = $world->getRoom($position);
                            if ($room !== null) {
                                $char->move($room);
                            }
                        }
                    } else {
                        $i++;
                    }
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
