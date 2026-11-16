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
    public const TYPE_GHOST    = 'GHOST';

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
    public function toHtml(
        ?Skin $skin = null,
        ?World $world = null,
        ?DimxObject $owner = null,
        mixed $agent = null,
        mixed $target = null
    ): string {
        return match ($this->type) {
            self::TYPE_CR => "<br />\n",
            self::TYPE_LABEL => $this->renderLabel($world, $owner, $agent, $target),
            self::TYPE_TEXTBOX => "<input type=\"text\" name=\"" . htmlspecialchars($this->id, ENT_QUOTES) . "\" id=\"" . htmlspecialchars($this->id, ENT_QUOTES) . "\" class=\"dimx-textbox\" placeholder=\"" . htmlspecialchars($this->tooltip, ENT_QUOTES) . "\" onkeydown=\"if(event.key==='Enter'){sendCmd('say', this.value, '*');event.preventDefault();}\" />\n",
            self::TYPE_DROPDOWN => $this->renderDropdown(),
            self::TYPE_CMD => "<!-- Hidden CMD {$this->id} -->\n",
            self::TYPE_GHOST => '',
            default => $this->renderButton($skin),
        };
    }

    protected function renderLabel(
        ?World $world = null,
        ?DimxObject $owner = null,
        mixed $agent = null,
        mixed $target = null
    ): string {
        $caption = $this->caption;
        if (str_starts_with($caption, '@')) {
            $w = $world
                ?? $this->world
                ?? $owner?->world
                ?? (($agent instanceof DimxObject) ? $agent->world : null)
                ?? GameEngine::$activeInstance?->getWorld();
            if ($w !== null) {
                $val = $w->evaluateExpression(substr($caption, 1), $owner, $agent, $target);
                $caption = ActionsRunner::formatValue($val);
            }
        }
        return "<span class=\"dimx-label\">{$caption}</span>\n";
    }

    protected function renderButton(?Skin $skin = null): string
    {
        $idEsc = htmlspecialchars($this->id, ENT_QUOTES);
        $titleEsc = htmlspecialchars($this->tooltip ?: $this->caption, ENT_QUOTES);
        $captionEsc = htmlspecialchars($this->caption, ENT_QUOTES);
        $modelEsc = htmlspecialchars($this->eventModel, ENT_QUOTES);

        if ($this->image !== null) {
            $wAttr = $this->image->width > 0 ? ' width="' . (int)$this->image->width . '"' : '';
            $hAttr = $this->image->height > 0 ? ' height="' . (int)$this->image->height . '"' : '';
            $imgSrcEsc = htmlspecialchars($this->image->src, ENT_QUOTES);
            return "<a href=\"#\" class=\"dimx-img-btn\" id=\"btn_{$idEsc}\" data-cmd=\"{$idEsc}\" data-model=\"{$modelEsc}\" title=\"{$titleEsc}\" onclick=\"sendCmd('{$idEsc}');return false;\"><img src=\"{$imgSrcEsc}\"{$wAttr}{$hAttr} alt=\"{$captionEsc}\" title=\"{$titleEsc}\" style=\"border:0;vertical-align:middle\" /></a>\n";
        }

        $resolvedIcon = null;
        if ($this->icon !== null && $this->icon !== '') {
            if (str_starts_with($this->icon, 'http://') || str_starts_with($this->icon, 'https://') || str_starts_with($this->icon, '/')) {
                $resolvedIcon = $this->icon;
            } elseif ($skin !== null && $skin->skinFolder !== '') {
                $resolvedIcon = rtrim($skin->skinFolder, '/') . '/' . ltrim($this->icon, '/');
            } else {
                $resolvedIcon = $this->icon;
            }
        }

        $styleAttr = '';
        if ($skin !== null && trim($skin->buttonStyle) !== '') {
            $rawButtonStyle = trim($skin->buttonStyle);
            if (preg_match('/\{([^}]*)\}/s', $rawButtonStyle, $bsm)) {
                $inlineButtonStyle = trim($bsm[1]);
            } else {
                $inlineButtonStyle = trim($rawButtonStyle, " \t\n\r\0\x0B{}");
            }
            if ($inlineButtonStyle !== '') {
                $skinFolder = rtrim($skin->skinFolder, '/') . '/';
                $skinIconUrl = 'url(' . $skinFolder . 'pan' . $this->id . '.gif)';
                $inlineButtonStyle = str_ireplace('$SKINICON', $skinIconUrl, $inlineButtonStyle);
                $iconCssUrl = $resolvedIcon !== null ? 'url(' . $resolvedIcon . ')' : '';
                $inlineButtonStyle = str_ireplace('$ICON', $iconCssUrl, $inlineButtonStyle);
                $styleAttr = ' style="' . htmlspecialchars($inlineButtonStyle, ENT_QUOTES) . '"';
            }
        }

        $iconHtml = '';
        if ($resolvedIcon !== null) {
            $iconHtml = "<img src=\"" . htmlspecialchars($resolvedIcon, ENT_QUOTES) . "\" class=\"dimx-btn-icon\" alt=\"\" /> ";
        }

        return "<button type=\"button\" class=\"dimx-btn\" id=\"btn_{$idEsc}\" data-cmd=\"{$idEsc}\" data-model=\"{$modelEsc}\" title=\"{$titleEsc}\"{$styleAttr} onclick=\"sendCmd('{$idEsc}');\">{$iconHtml}{$captionEsc}</button>\n";
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
