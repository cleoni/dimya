<?php
declare(strict_types=1);

namespace Dimx;

/**
 * Ctrl
 *
 * Represents an interactive browser UI control (button, textbox, dropdown, label, compass, etc.)
 * inside a DimensioneX command Panel.
 */
class Ctrl extends ManageableObject
{
    public const TYPE_BUTTON   = 'BUTTON';
    public const TYPE_SUBMIT   = 'SUBMIT';
    public const TYPE_TEXTBOX  = 'TEXTBOX';
    public const TYPE_DROPDOWN = 'DROPDOWN';
    public const TYPE_LABEL    = 'LABEL';
    public const TYPE_CR       = 'CR';
    public const TYPE_MAP      = 'MAP';
    public const TYPE_CMD      = 'CMD';

    public string $id = '';
    public string $type = self::TYPE_BUTTON;
    public string $caption = '';
    public string $tooltip = '';
    public string $event = '';
    public ?string $icon = null;
    public string $eventModel = '';
    public ?Image $image = null;
    public array $options = []; // For dropdowns: [value => label]

    public function __construct(
        string $id,
        string $type = self::TYPE_BUTTON,
        string $caption = '',
        string $tooltip = '',
        string $event = '',
        ?string $icon = null,
        string $eventModel = ''
    ) {
        parent::__construct();
        $this->id = $id;
        $this->type = strtoupper($type);
        $this->caption = $caption;
        $this->tooltip = $tooltip;
        $this->event = $event;
        $this->icon = $icon;
        $this->eventModel = $eventModel;
    }

    /**
     * Render the control to HTML.
     */
    public function toHtml(?Skin $skin = null): string
    {
        return match ($this->type) {
            self::TYPE_CR => "<br />\n",
            self::TYPE_LABEL => "<span class=\"dimx-label\">{$this->caption}</span>\n",
            self::TYPE_TEXTBOX => "<input type=\"text\" name=\"" . htmlspecialchars($this->id, ENT_QUOTES) . "\" id=\"" . htmlspecialchars($this->id, ENT_QUOTES) . "\" class=\"dimx-textbox\" placeholder=\"" . htmlspecialchars($this->tooltip, ENT_QUOTES) . "\" onkeydown=\"if(event.key==='Enter'){sendCmd('say');event.preventDefault();}\" />\n",
            self::TYPE_DROPDOWN => $this->renderDropdown(),
            self::TYPE_CMD => "<!-- Hidden CMD {$this->id} -->\n",
            default => $this->renderButton($skin),
        };
    }

    protected function renderButton(?Skin $skin = null): string
    {
        $idEsc = htmlspecialchars($this->id, ENT_QUOTES);
        $titleEsc = htmlspecialchars($this->tooltip ?: $this->caption, ENT_QUOTES);
        $captionEsc = htmlspecialchars($this->caption, ENT_QUOTES);
        $modelEsc = htmlspecialchars($this->eventModel, ENT_QUOTES);

        $iconHtml = '';
        if ($this->icon !== null) {
            $iconHtml = "<img src=\"" . htmlspecialchars($this->icon, ENT_QUOTES) . "\" class=\"dimx-btn-icon\" alt=\"\" /> ";
        }

        return "<button type=\"button\" class=\"dimx-btn\" id=\"btn_{$idEsc}\" data-cmd=\"{$idEsc}\" data-model=\"{$modelEsc}\" title=\"{$titleEsc}\" onclick=\"sendCmd('{$idEsc}');\">{$iconHtml}{$captionEsc}</button>\n";
    }

    protected function renderDropdown(): string
    {
        $idEsc = htmlspecialchars($this->id, ENT_QUOTES);
        $html = "<select name=\"{$idEsc}\" id=\"{$idEsc}\" class=\"dimx-dropdown\">\n";
        foreach ($this->options as $val => $lbl) {
            $valEsc = htmlspecialchars((string)$val, ENT_QUOTES);
            $lblEsc = htmlspecialchars((string)$lbl, ENT_QUOTES);
            $html .= "  <option value=\"{$valEsc}\">{$lblEsc}</option>\n";
        }
        $html .= "</select>\n";
        return $html;
    }
}
