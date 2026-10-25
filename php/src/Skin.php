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
    public string $panelBgColor = '#0f172a';
    public string $textColor = '#f8fafc';
    public string $buttonStyle = '';
    public string $stylesheet = '';
    public ?Image $picPlayer = null;
    public ?Image $picSpacer = null;
    public ?Image $picFader = null;

    /** @var array<string, string> Icon file associations */
    public array $icons = [
        'ICOPLAYER'    => 'pics/player.gif',
        'ICOCHARACTER' => 'pics/character.gif',
        'ICOITEM'      => 'pics/item.gif',
        'ICOWAY'       => 'pics/way.gif',
        'ICOWAYN'      => 'pics/north.gif',
        'ICOWAYS'      => 'pics/south.gif',
        'ICOWAYE'      => 'pics/right.gif',
        'ICOWAYW'      => 'pics/left.gif',
        'ICOWAYU'      => 'pics/up.gif',
        'ICOWAYD'      => 'pics/down.gif',
    ];

    public function __construct(string $id = 'default', string $name = 'Default', ?string $skinFolder = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->skinFolder = $skinFolder ?? "http://localhost/dimx/skins/{$id}/";
        $this->picPlayer = new Image($this->skinFolder . 'avatar.gif', 64, 100);
        $this->icons['ICOPLAYER'] = $this->skinFolder . 'player.gif';
    }

    /**
     * Load skin settings from a .dxs specification file.
     */
    public static function loadFromDxs(string $filePath, ?string $folderUrl = null): ?self
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $id = pathinfo(dirname($filePath), PATHINFO_BASENAME) ?: 'default';
        $skin = new self($id, ucfirst($id), $folderUrl);

        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
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
                case 'PICPLAYER':
                    $url = ($folderUrl ?? $skin->skinFolder) . $val;
                    $skin->picPlayer = new Image($url, 64, 100);
                    break;
                case 'PICSPACER':
                    $skin->picSpacer = new Image(($folderUrl ?? $skin->skinFolder) . $val);
                    break;
                case 'PICFADER':
                    $skin->picFader = new Image(($folderUrl ?? $skin->skinFolder) . $val);
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
                    $skin->icons[$key] = ($folderUrl ?? $skin->skinFolder) . $val;
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
            }
        }

        return $skin;
    }

    public function getIcon(string $name): string
    {
        return $this->icons[strtoupper($name)] ?? 'pics/item.gif';
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
