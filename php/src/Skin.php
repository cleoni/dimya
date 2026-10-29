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

        $this->icons = [
            'ICOPLAYER'    => $this->skinFolder . 'icoPlayer.gif',
            'ICOCHARACTER' => $this->skinFolder . 'icouser.gif',
            'ICOITEM'      => $this->skinFolder . 'icoitem.gif',
            'ICOWAY'       => $this->skinFolder . 'icoway.gif',
            'ICOWAYN'      => $this->skinFolder . 'icowayn.gif',
            'ICOWAYS'      => $this->skinFolder . 'icoways.gif',
            'ICOWAYE'      => $this->skinFolder . 'icowaye.gif',
            'ICOWAYW'      => $this->skinFolder . 'icowayw.gif',
            'ICOWAYU'      => $this->skinFolder . 'icowayu.gif',
            'ICOWAYD'      => $this->skinFolder . 'icowayd.gif',
            'ICOROTL'      => $this->skinFolder . 'icorotl.gif',
            'ICOROTR'      => $this->skinFolder . 'icorotr.gif',
            'ICOREV'       => $this->skinFolder . 'reverse.gif',
            'PICSPACER'    => $this->skinFolder . 'blank.gif',
        ];
    }

    /**
     * Update the skin folder URL/path and rebase default images and icons.
     */
    public function setSkinFolder(string $skinFolder): void
    {
        $oldFolder = $this->skinFolder;
        $this->skinFolder = rtrim($skinFolder, '/') . '/';
        $this->picPlayer = new Image($this->skinFolder . 'uomo.gif', 64, 100);
        $this->picSpacer = new Image($this->skinFolder . 'blank.gif');
        $this->picFader = new Image($this->skinFolder . 'fadewhite.gif');

        $defaultNames = [
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

        foreach ($defaultNames as $key => $filename) {
            if (!isset($this->icons[$key]) || empty($oldFolder) || str_starts_with($this->icons[$key], $oldFolder)) {
                $this->icons[$key] = $this->skinFolder . $filename;
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

    public function getIcon(string $name): string
    {
        return $this->icons[strtoupper($name)] ?? ($this->skinFolder . 'icoitem.gif');
    }

    public function getItemIcon(): string
    {
        return $this->icons['ICOITEM'] ?? ($this->skinFolder . 'icoitem.gif');
    }

    public function getCharacterIcon(): string
    {
        return $this->icons['ICOCHARACTER'] ?? ($this->skinFolder . 'icouser.gif');
    }

    public function getPlayerIcon(): string
    {
        return $this->icons['ICOPLAYER'] ?? ($this->skinFolder . 'icoPlayer.gif');
    }

    public function getWayIcon(string $direction): string
    {
        $dir = strtoupper(trim($direction));
        return match ($dir) {
            'N'        => $this->icons['ICOWAYN'] ?? ($this->skinFolder . 'icowayn.gif'),
            'S'        => $this->icons['ICOWAYS'] ?? ($this->skinFolder . 'icoways.gif'),
            'E'        => $this->icons['ICOWAYE'] ?? ($this->skinFolder . 'icowaye.gif'),
            'W'        => $this->icons['ICOWAYW'] ?? ($this->skinFolder . 'icowayw.gif'),
            'U', 'UP'  => $this->icons['ICOWAYU'] ?? ($this->skinFolder . 'icowayu.gif'),
            'D', 'DOWN'=> $this->icons['ICOWAYD'] ?? ($this->skinFolder . 'icowayd.gif'),
            default    => $this->icons['ICOWAY'] ?? ($this->skinFolder . 'icoway.gif'),
        };
    }

    public function getSpacerIcon(): string
    {
        return $this->picSpacer?->url ?? ($this->icons['PICSPACER'] ?? ($this->skinFolder . 'blank.gif'));
    }

    public function getRotLIcon(): string
    {
        return $this->icons['ICOROTL'] ?? ($this->skinFolder . 'icorotl.gif');
    }

    public function getRotRIcon(): string
    {
        return $this->icons['ICOROTR'] ?? ($this->skinFolder . 'icorotr.gif');
    }

    public function getRevIcon(): string
    {
        return $this->icons['ICOREV'] ?? ($this->skinFolder . 'reverse.gif');
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
