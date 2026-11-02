<?php
declare(strict_types=1);

namespace Dimx;

/**
 * Image
 *
 * Represents an image or animation asset used in rooms, items, actors, and UI elements.
 */
class Image extends ManageableObject
{
    public string $src = '';
    public int $width = 0;
    public int $height = 0;
    public int $baseline = -1;
    public int $showareaX1 = 0;
    public int $showareaX2 = -1;
    public int $showareaY = -1;

    public function __construct(string $src = '', int $width = 0, int $height = 0)
    {
        parent::__construct();
        $this->src = $src;
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * Renders an HTML <img> tag with dimensions and optional alt/title.
     */
    public function toHtml(?string $title = null, ?Player $player = null, string $class = '', string $style = ''): string
    {
        $alt = $title !== null ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : '';
        $src = htmlspecialchars($this->src, ENT_QUOTES, 'UTF-8');
        $widthAttr = $this->width > 0 ? " width=\"{$this->width}\"" : '';
        $heightAttr = $this->height > 0 ? " height=\"{$this->height}\"" : '';
        $classAttr = !empty($class) ? " class=\"" . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . "\"" : '';
        $styleAttr = !empty($style) ? " style=\"" . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . "\"" : '';

        return "<img src=\"{$src}\" alt=\"{$alt}\" title=\"{$alt}\"{$widthAttr}{$heightAttr}{$classAttr}{$styleAttr} border=\"0\" />";
    }

    public function toDXW(): string
    {
        $sa = '';
        if ($this->showareaX2 !== -1 || $this->baseline !== -1) {
            $sa = " SHOWAREA {$this->showareaX1},{$this->showareaX2},{$this->baseline}";
        }
        return "IMAGE {$this->width}x{$this->height}{$sa} {$this->src}";
    }

    public function html(?string $title = null, ?Player $player = null): string
    {
        return $this->toHtml($title, $player);
    }

    public function getProperty(string $name): mixed
    {
        $lower = strtolower($name);
        if ($lower === 'url' || $lower === 'src') {
            return $this->src;
        }
        if ($lower === 'width') {
            return $this->width;
        }
        if ($lower === 'height') {
            return $this->height;
        }
        return parent::getProperty($name);
    }

    public function __get(string $name): mixed
    {
        $lower = strtolower($name);
        if ($lower === 'url' || $lower === 'src') {
            return $this->src;
        }
        if ($lower === 'width') {
            return $this->width;
        }
        if ($lower === 'height') {
            return $this->height;
        }
        return parent::__get($name);
    }

    public function __toString(): string
    {
        return "NewImage(\"{$this->src}\", {$this->width}, {$this->height})";
    }
}
