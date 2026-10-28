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
    public const DEFAULT_ICONS = [
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
        'PICSPACER'    => 'blank.gif',
    ];

    public string $id = 'default';
    public string $name = 'Default';
    public string $skinFolder = '';
    public string $bodyBgColor = '#1e293b';
    public string $panelBgColor = 'SILVER';
    public string $textColor = '#f8fafc';
    public string $buttonStyle = '';
    public string $titleStyle = '';
    public string $stylesheet = '';
    public ?Image $picPlayer = null;
    public ?Image $picSpacer = null;
    public ?Image $picFader = null;

    /** @var array<string, string> Icon file associations */
    public array $icons = [];

    public function __construct(string $id = 'default', string $name = 'Default', ?string $skinFolder = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->skinFolder = rtrim($skinFolder ?? "/skins/{$id}/", '/') . '/';
        $this->picPlayer = new Image($this->skinFolder . 'uomo.gif', 64, 100);
        $this->picSpacer = new Image($this->skinFolder . 'blank.gif');
        $this->picFader = new Image($this->skinFolder . 'fadewhite.gif');

        $this->icons = [];
        foreach (self::DEFAULT_ICONS as $key => $file) {
            $this->icons[$key] = $this->skinFolder . $file;
        }
    }

    /**
     * Update the skin folder URL/path and rebase default images and icons.
     */
    public function setSkinFolder(string $skinFolder): void
    {
        $oldFolder = $this->skinFolder;
        $this->skinFolder = rtrim($skinFolder, '/') . '/';
        $this->picPlayer = new Image($this->skinFolder . 'player.gif', 64, 100);
        $this->picSpacer = new Image($this->skinFolder . 'blank.gif');
        $this->picFader = new Image($this->skinFolder . 'fadewhite.gif');

        foreach (self::DEFAULT_ICONS as $key => $filename) {
            $current = $this->icons[$key] ?? '';
            // If unset, empty, referencing old folder, containing localhost, or is relative (e.g. pics/north.gif):
            if (
                empty($current) ||
                empty($oldFolder) ||
                str_starts_with($current, $oldFolder) ||
                str_starts_with($current, 'pics/') ||
                (!str_starts_with($current, 'http://') && !str_starts_with($current, 'https://') && !str_starts_with($current, '/'))
            ) {
                $this->icons[$key] = $this->skinFolder . $filename;
            } elseif (str_contains($current, 'localhost') && !str_contains($this->skinFolder, 'localhost')) {
                $this->icons[$key] = $this->skinFolder . basename($current);
            }
        }
    }

    /**
     * Load skin settings from a .dxs specification file or URL.
     */
    public static function loadFromDxs(string $filePath, ?string $folderUrl = null): ?self
    {
        $isUrl = str_starts_with($filePath, 'http://') || str_starts_with($filePath, 'https://');
        $content = null;

        if ($isUrl) {
            $ctx = stream_context_create([
                'http'  => ['timeout' => 3],
                'https' => ['timeout' => 3],
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

        $id = pathinfo(dirname($filePath), PATHINFO_BASENAME) ?: 'default';
        $skin = new self($id, ucfirst($id), $folderUrl);

        $lines = preg_split('/\r\n|\r|\n/', $content);
        if ($lines === false) {
            return $skin;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, "'") || str_starts_with($line, '#')) {
                continue;
            }

            $tokens = preg_split('/\s+/', $line, 2);
            $key = strtoupper($tokens[0]);
            $val = isset($tokens[1]) ? trim($tokens[1]) : '';

            switch ($key) {
                case 'NAME':
                    $skin->name = $val;
                    break;
                case 'SKINFOLDER':
                case 'IMAGESFOLDER':
                    if ($val !== '') {
                        $skin->setSkinFolder($val);
                        $folderUrl = $skin->skinFolder;
                    }
                    break;
                case 'PICPLAYER':
                    $url = (str_starts_with($val, 'http://') || str_starts_with($val, 'https://'))
                        ? $val
                        : (($folderUrl ?? $skin->skinFolder) . $val);
                    $skin->picPlayer = new Image($url, 64, 100);
                    break;
                case 'PICSPACER':
                    $url = (str_starts_with($val, 'http://') || str_starts_with($val, 'https://'))
                        ? $val
                        : (($folderUrl ?? $skin->skinFolder) . $val);
                    $skin->picSpacer = new Image($url);
                    break;
                case 'PICFADER':
                    $url = (str_starts_with($val, 'http://') || str_starts_with($val, 'https://'))
                        ? $val
                        : (($folderUrl ?? $skin->skinFolder) . $val);
                    $skin->picFader = new Image($url);
                    break;
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
                    $url = (str_starts_with($val, 'http://') || str_starts_with($val, 'https://'))
                        ? $val
                        : (($folderUrl ?? $skin->skinFolder) . $val);
                    $skin->icons[$key] = $url;
                    break;
                case 'BODYBGCOLOR':
                    if ($val !== '') $skin->bodyBgColor = $val;
                    break;
                case 'PANELBGCOLOR':
                    if ($val !== '') $skin->panelBgColor = $val;
                    break;
                case 'BUTTONSTYLE':
                    $skin->buttonStyle = $val;
                    break;
                case 'TITLESTYLE':
                    $skin->titleStyle = str_ireplace(
                        ['$SKINFOLDER', '$IMAGESFOLDER'],
                        rtrim($folderUrl ?? $skin->skinFolder, '/'),
                        $val
                    );
                    break;
            }
        }

        return $skin;
    }

    /**
     * Resolves an icon path/URL, normalizing relative paths (e.g. 'pics/...')
     * and rebasing against the skin folder without altering icon filenames.
     */
    public function resolveIconUrl(?string $icon, string $defaultFilename): string
    {
        $folder = !empty($this->skinFolder) ? rtrim($this->skinFolder, '/') . '/' : ('/skins/' . $this->id . '/');
        if (empty($icon)) {
            return $folder . $defaultFilename;
        }
        $trimmed = trim($icon);
        $basename = basename($trimmed);

        // If it starts with pics/ or is a relative path without leading slash / scheme
        if (str_starts_with($trimmed, 'pics/')) {
            return $folder . $basename;
        }
        if (!str_starts_with($trimmed, 'http://') && !str_starts_with($trimmed, 'https://') && !str_starts_with($trimmed, '/')) {
            return $folder . $basename;
        }
        // If it contains localhost but skinFolder is on a public host:
        if ((str_contains($trimmed, 'localhost') || str_contains($trimmed, '127.0.0.1')) && !str_contains($folder, 'localhost') && !str_contains($folder, '127.0.0.1')) {
            return $folder . $basename;
        }

        return $trimmed;
    }

    public function getIcon(string $name): string
    {
        $key = strtoupper($name);
        $defaultFile = self::DEFAULT_ICONS[$key] ?? 'icoitem.gif';
        return $this->resolveIconUrl($this->icons[$key] ?? null, $defaultFile);
    }

    public function getItemIcon(): string
    {
        return $this->resolveIconUrl($this->icons['ICOITEM'] ?? null, self::DEFAULT_ICONS['ICOITEM'] ?? 'icoitem.gif');
    }

    public function getCharacterIcon(): string
    {
        return $this->resolveIconUrl($this->icons['ICOCHARACTER'] ?? null, self::DEFAULT_ICONS['ICOCHARACTER'] ?? 'icouser.gif');
    }

    public function getPlayerIcon(): string
    {
        return $this->resolveIconUrl($this->icons['ICOPLAYER'] ?? null, self::DEFAULT_ICONS['ICOPLAYER'] ?? 'icoPlayer.gif');
    }

    public function getWayIcon(string $direction): string
    {
        $dir = strtoupper(trim($direction));
        $key = match ($dir) {
            'N'         => 'ICOWAYN',
            'S'         => 'ICOWAYS',
            'E'         => 'ICOWAYE',
            'W'         => 'ICOWAYW',
            'U', 'UP'   => 'ICOWAYU',
            'D', 'DOWN' => 'ICOWAYD',
            default     => 'ICOWAY',
        };
        $defaultFile = self::DEFAULT_ICONS[$key] ?? 'icoway.gif';
        return $this->resolveIconUrl($this->icons[$key] ?? null, $defaultFile);
    }

    public function getSpacerIcon(): string
    {
        if ($this->picSpacer?->url) {
            return $this->resolveIconUrl($this->picSpacer->url, 'blank.gif');
        }
        return $this->resolveIconUrl($this->icons['PICSPACER'] ?? null, 'blank.gif');
    }

    public function getRotLIcon(): string
    {
        return $this->resolveIconUrl($this->icons['ICOROTL'] ?? null, self::DEFAULT_ICONS['ICOROTL'] ?? 'icorotl.gif');
    }

    public function getRotRIcon(): string
    {
        return $this->resolveIconUrl($this->icons['ICOROTR'] ?? null, self::DEFAULT_ICONS['ICOROTR'] ?? 'icorotr.gif');
    }

    public function getRevIcon(): string
    {
        return $this->resolveIconUrl($this->icons['ICOREV'] ?? null, self::DEFAULT_ICONS['ICOREV'] ?? 'reverse.gif');
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
    background-color: {$this->panelBgColor};
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
