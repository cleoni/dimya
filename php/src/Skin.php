<?php
declare(strict_types=1);

namespace Dimx;

/**
 * Skin
 *
 * Visual theme styling configuration for game interface (colors, fonts, layout styles, icons).
 */
class Skin
{
    /**
     * Hard-coded default filenames for all skin media elements.
     * Every Skin instance initializes with these defaults so no media link is ever missing.
     */
    public const DEFAULT_MEDIA_FILES = [
        'PICPLAYER'    => 'uomo.gif',
        'PICSPACER'    => 'blank.gif',
        'PICFADER'     => 'fadewhite.gif',
        'ICOPLAYER'    => 'icoPlayer.gif',
        'ICOCHARACTER' => 'icouser.gif',
        'ICOITEM'      => 'icoitem.gif',
        'ICOWAY'       => 'icoway.gif',
        'ICOWAYN'      => 'icowayn.gif',
        'ICOWAYS'      => 'icoways.gif',
        'ICOWAYE'      => 'icowaye.gif',
        'ICOWAYW'      => 'icowayw.gif',
        'ICOWAYU'      => 'icowayu.gif',
        'ICOWAYD'      => 'icowayd.gif',
        'ICOROTL'      => 'icorotl.gif',
        'ICOROTR'      => 'icorotr.gif',
        'ICOREV'       => 'reverse.gif',
    ];

    public string $id = 'default';
    public string $name = 'Default';
    public string $skinFolder = '';
    public bool $hasExplicitSkinFolder = false;
    public string $bodyBgColor = '#1e293b';
    public string $panelBgColor = 'SILVER';
    public string $panelBackground = '';
    public string $rawPanelBackground = '';
    public string $panelStyle = '{font-size: 10pt;font-family: Tahoma;color:#600000;}';
    public string $list1BgColor = '#101040';
    public string $list2BgColor = '#313161';
    public string $listStyle = '{font-size: 8pt;font-family: Tahoma;color:#A0B0C0;}';
    public string $textColor = '#f8fafc';
    public string $buttonStyle = '';
    public string $titleStyle = '';
    public string $rawTitleStyle = '';
    public string $stylesheet = '';
    public ?Image $picPlayer = null;
    public ?Image $picSpacer = null;
    public ?Image $picFader = null;

    /** @var array<string, string> Raw media filenames/URLs per directive key */
    protected array $mediaFiles = self::DEFAULT_MEDIA_FILES;

    /** @var array<string, string> Resolved icon URLs */
    public array $icons = [];

    public function __construct(string $id = 'default', string $name = 'Default', ?string $skinFolder = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->mediaFiles = self::DEFAULT_MEDIA_FILES;
        $this->skinFolder = rtrim($skinFolder ?? "/skins/{$id}/", '/') . '/';
        $this->applyMediaFiles();
    }

    /**
     * Resolve a single media filename or URL against this skin's skinFolder.
     */
    protected function resolveMediaUrl(string $val): string
    {
        $val = trim($val);
        if (
            str_starts_with($val, 'http://') ||
            str_starts_with($val, 'https://') ||
            str_starts_with($val, '/')
        ) {
            return $val;
        }
        return $this->skinFolder . $val;
    }

    /**
     * Resolve and populate picPlayer, picSpacer, picFader, icons, and titleStyle
     * from the current skinFolder and mediaFiles map.
     */
    public function applyMediaFiles(): void
    {
        $this->picPlayer = new Image($this->resolveMediaUrl($this->mediaFiles['PICPLAYER']), 64, 100);
        $this->picSpacer = new Image($this->resolveMediaUrl($this->mediaFiles['PICSPACER']));
        $this->picFader  = new Image($this->resolveMediaUrl($this->mediaFiles['PICFADER']));

        $iconKeys = [
            'ICOPLAYER',
            'ICOCHARACTER',
            'ICOITEM',
            'ICOWAY',
            'ICOWAYN',
            'ICOWAYS',
            'ICOWAYE',
            'ICOWAYW',
            'ICOWAYU',
            'ICOWAYD',
            'ICOROTL',
            'ICOROTR',
            'ICOREV',
            'PICSPACER',
        ];

        foreach ($iconKeys as $key) {
            $file = $this->mediaFiles[$key] ?? self::DEFAULT_MEDIA_FILES[$key];
            $this->icons[$key] = $this->resolveMediaUrl($file);
        }

        if ($this->rawPanelBackground !== '') {
            $this->panelBackground = $this->resolveMediaUrl($this->rawPanelBackground);
        } else {
            $this->panelBackground = '';
        }

        if ($this->rawTitleStyle !== '') {
            $this->titleStyle = str_ireplace(
                ['$SKINFOLDER', '$IMAGESFOLDER'],
                rtrim($this->skinFolder, '/'),
                $this->rawTitleStyle
            );
        }
    }

    /**
     * Update the skin folder URL/path and rebase all relative media files and icons
     * belonging to this Skin instance without overwriting custom filenames.
     */
    public function setSkinFolder(string $skinFolder): void
    {
        $this->skinFolder = rtrim($skinFolder, '/') . '/';
        $this->applyMediaFiles();
    }

    /**
     * Load skin settings from a .dxs specification file or URL.
     *
     * @param string        $filePath       Local file path or HTTP(S) URL to the .dxs file
     * @param string|null   $folderUrl      Optional fallback skinFolder URL
     * @param callable|null $folderResolver Optional callback fn(string $skinId): string to compute default SKINFOLDER from World's IMAGESFOLDER
     */
    public static function loadFromDxs(string $filePath, ?string $folderUrl = null, ?callable $folderResolver = null): ?self
    {
        $isUrl = str_starts_with($filePath, 'http://') || str_starts_with($filePath, 'https://');
        $content = null;

        if ($isUrl) {
            $ctx = stream_context_create([
                'http'  => ['timeout' => 5],
                'https' => ['timeout' => 5],
                'ssl'   => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $content = @file_get_contents($filePath, false, $ctx);
            if ($content === false) {
                return null;
            }
        } elseif (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            if ($content === false) {
                return null;
            }
        } else {
            return null;
        }

        $urlPath  = (string)(parse_url($filePath, PHP_URL_PATH) ?: $filePath);
        $baseName = pathinfo($urlPath, PATHINFO_FILENAME);
        $id       = preg_replace('/_net$/i', '', $baseName) ?: (pathinfo(dirname($urlPath), PATHINFO_BASENAME) ?: 'default');

        $parsedName         = null;
        $explicitSkinFolder = null;
        $customMedia        = [];
        $bodyBgColor        = null;
        $panelBgColor       = null;
        $rawPanelBackground = null;
        $panelStyle         = null;
        $list1BgColor       = null;
        $list2BgColor       = null;
        $listStyle          = null;
        $buttonStyle        = null;
        $rawTitleStyle      = null;
        $stylesheet         = null;

        $lines = preg_split('/\r\n|\r|\n/', $content);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, "'") || str_starts_with($line, '#')) {
                    continue;
                }

                // Strip inline comment starting with ' unless inside braces/styles
                $tokens = preg_split('/\s+/', $line, 2);
                $key = strtoupper($tokens[0]);
                $val = isset($tokens[1]) ? trim($tokens[1]) : '';

                switch ($key) {
                    case 'SKIN':
                    case 'ID':
                        if ($val !== '') {
                            $idTokens = preg_split('/\s+/', $val);
                            if (!empty($idTokens[0])) {
                                $id = trim($idTokens[0]);
                            }
                        }
                        break;
                    case 'END_SKIN':
                        break;
                    case 'NAME':
                        if ($val !== '') {
                            $parsedName = $val;
                        }
                        break;
                    case 'SKINFOLDER':
                    case 'IMAGESFOLDER':
                        if ($val !== '') {
                            $explicitSkinFolder = $val;
                        }
                        break;
                    case 'PICPLAYER':
                    case 'PICSPACER':
                    case 'PICFADER':
                    case 'ICOPLAYER':
                    case 'ICOCHARACTER':
                    case 'ICOITEM':
                    case 'ICOWAY':
                    case 'ICOWAYN':
                    case 'ICOWAYS':
                    case 'ICOWAYE':
                    case 'ICOWAYW':
                    case 'ICOWAYU':
                    case 'ICOWAYD':
                    case 'ICOROTL':
                    case 'ICOROTR':
                    case 'ICOREV':
                        if ($val !== '') {
                            $customMedia[$key] = $val;
                        }
                        break;
                    case 'BODYBGCOLOR':
                        if ($val !== '') {
                            $bodyBgColor = $val;
                        }
                        break;
                    case 'PANELBGCOLOR':
                        if ($val !== '') {
                            $panelBgColor = $val;
                        }
                        break;
                    case 'PANELBACKGROUND':
                        $rawPanelBackground = $val;
                        break;
                    case 'PANELSTYLE':
                        if ($val !== '') {
                            $panelStyle = $val;
                        }
                        break;
                    case 'LIST1BGCOLOR':
                        if ($val !== '') {
                            $list1BgColor = $val;
                        }
                        break;
                    case 'LIST2BGCOLOR':
                        if ($val !== '') {
                            $list2BgColor = $val;
                        }
                        break;
                    case 'LISTSTYLE':
                        if ($val !== '') {
                            $listStyle = $val;
                        }
                        break;
                    case 'BUTTONSTYLE':
                        $buttonStyle = $val;
                        break;
                    case 'TITLESTYLE':
                        $rawTitleStyle = $val;
                        break;
                    case 'STYLESHEET':
                        if ($val !== '') {
                            $stylesheet = $val;
                        }
                        break;
                }
            }
        }

        // Determine SKINFOLDER:
        // 1. If SKINFOLDER (or IMAGESFOLDER) is defined in the .dxs file, use it directly.
        // 2. Otherwise calculate default SKINFOLDER from World's IMAGESFOLDER and the skin's ID.
        if ($explicitSkinFolder !== null && $explicitSkinFolder !== '') {
            $resolvedFolder = $explicitSkinFolder;
        } elseif ($folderResolver !== null) {
            $resolvedFolder = (string)$folderResolver($id);
        } elseif ($folderUrl !== null && $folderUrl !== '') {
            $resolvedFolder = $folderUrl;
        } else {
            $resolvedFolder = "/skins/{$id}/";
        }

        $skin = new self($id, $parsedName ?? ucfirst($id), $resolvedFolder);
        $skin->hasExplicitSkinFolder = ($explicitSkinFolder !== null && $explicitSkinFolder !== '');

        foreach ($customMedia as $k => $v) {
            $skin->mediaFiles[$k] = $v;
        }
        if ($bodyBgColor !== null) {
            $skin->bodyBgColor = $bodyBgColor;
        }
        if ($panelBgColor !== null) {
            $skin->panelBgColor = $panelBgColor;
        }
        if ($rawPanelBackground !== null) {
            $skin->rawPanelBackground = $rawPanelBackground;
        }
        if ($panelStyle !== null) {
            $skin->panelStyle = $panelStyle;
        }
        if ($list1BgColor !== null) {
            $skin->list1BgColor = $list1BgColor;
        }
        if ($list2BgColor !== null) {
            $skin->list2BgColor = $list2BgColor;
        }
        if ($listStyle !== null) {
            $skin->listStyle = $listStyle;
        }
        if ($buttonStyle !== null) {
            $skin->buttonStyle = $buttonStyle;
        }
        if ($rawTitleStyle !== null) {
            $skin->rawTitleStyle = $rawTitleStyle;
        }
        if ($stylesheet !== null) {
            $skin->stylesheet = $stylesheet;
        }

        $skin->applyMediaFiles();

        return $skin;
    }

    public function getIcon(string $name): string
    {
        return $this->icons[strtoupper($name)] ?? ($this->skinFolder . self::DEFAULT_MEDIA_FILES['ICOITEM']);
    }

    public function getItemIcon(): string
    {
        return $this->icons['ICOITEM'] ?? ($this->skinFolder . self::DEFAULT_MEDIA_FILES['ICOITEM']);
    }

    public function getCharacterIcon(): string
    {
        return $this->icons['ICOCHARACTER'] ?? ($this->skinFolder . self::DEFAULT_MEDIA_FILES['ICOCHARACTER']);
    }

    public function getPlayerIcon(): string
    {
        return $this->icons['ICOPLAYER'] ?? ($this->skinFolder . self::DEFAULT_MEDIA_FILES['ICOPLAYER']);
    }

    public function getWayIcon(string $direction): string
    {
        $dir = strtoupper(trim($direction));
        return match ($dir) {
            'N'        => $this->icons['ICOWAYN'] ?? ($this->skinFolder . self::DEFAULT_MEDIA_FILES['ICOWAYN']),
            'S'        => $this->icons['ICOWAYS'] ?? ($this->skinFolder . self::DEFAULT_MEDIA_FILES['ICOWAYS']),
            'E'        => $this->icons['ICOWAYE'] ?? ($this->skinFolder . self::DEFAULT_MEDIA_FILES['ICOWAYE']),
            'W'        => $this->icons['ICOWAYW'] ?? ($this->skinFolder . self::DEFAULT_MEDIA_FILES['ICOWAYW']),
            'U', 'UP'  => $this->icons['ICOWAYU'] ?? ($this->skinFolder . self::DEFAULT_MEDIA_FILES['ICOWAYU']),
            'D', 'DOWN'=> $this->icons['ICOWAYD'] ?? ($this->skinFolder . self::DEFAULT_MEDIA_FILES['ICOWAYD']),
            default    => $this->icons['ICOWAY']  ?? ($this->skinFolder . self::DEFAULT_MEDIA_FILES['ICOWAY']),
        };
    }

    public function getSpacerIcon(): string
    {
        return (!empty($this->picSpacer?->src))
            ? $this->picSpacer->src
            : ($this->icons['PICSPACER'] ?? ($this->skinFolder . self::DEFAULT_MEDIA_FILES['PICSPACER']));
    }

    public function getRotLIcon(): string
    {
        return $this->icons['ICOROTL'] ?? ($this->skinFolder . self::DEFAULT_MEDIA_FILES['ICOROTL']);
    }

    public function getRotRIcon(): string
    {
        return $this->icons['ICOROTR'] ?? ($this->skinFolder . self::DEFAULT_MEDIA_FILES['ICOROTR']);
    }

    public function getRevIcon(): string
    {
        return $this->icons['ICOREV'] ?? ($this->skinFolder . self::DEFAULT_MEDIA_FILES['ICOREV']);
    }

    /**
     * Generate inline CSS stylesheet rules from this skin configuration.
     */
    public function getCss(): string
    {
        return <<<CSS
body {
    background-color: {$this->bodyBgColor};
    color: {$this->textColor};
    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    margin: 0;
    padding: 0;
}
.dimx-panel {
    background-color: transparent;
    padding: 10px;
    border-radius: 8px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
}
.dimx-btn {
    background-color: #2563eb;
    color: #ffffff;
    border: none;
    border-radius: 4px;
    padding: 6px 12px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.dimx-btn:hover {
    background-color: #1d4ed8;
}
.dimx-textbox {
    padding: 6px 10px;
    border-radius: 4px;
    border: 1px solid #475569;
    background: #0f172a;
    color: #f8fafc;
    font-size: 13px;
    flex-grow: 1;
}
.dimx-label {
    font-size: 13px;
    font-weight: 600;
}
CSS;
    }
}
