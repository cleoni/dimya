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

    /** @var array<string, string> Icon file associations */
    public array $icons = [
        'ICOPLAYER'     => 'pics/player.gif',
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

    public function __construct(string $id = 'default', string $name = 'Default')
    {
        $this->id = $id;
        $this->name = $name;
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
