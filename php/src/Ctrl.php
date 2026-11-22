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
            self::TYPE_DROPDOWN => $this->renderDropdown($skin, $world, $owner, $agent, $target),
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
        $hasBackgroundIcon = false;
        if ($skin !== null && trim($skin->buttonStyle) !== '') {
            $rawButtonStyle = trim($skin->buttonStyle);
            if (preg_match('/\{([^}]*)\}/s', $rawButtonStyle, $bsm)) {
                $inlineButtonStyle = trim($bsm[1]);
            } else {
                $inlineButtonStyle = trim($rawButtonStyle, " \t\n\r\0\x0B{}");
            }
            if ($inlineButtonStyle !== '') {
                $hasSkinIconVar = (stripos($inlineButtonStyle, '$SKINICON') !== false);
                $hasIconVar     = (stripos($inlineButtonStyle, '$ICON') !== false);

                $skinFolder = rtrim($skin->skinFolder, '/') . '/';
                $skinIconUrl = 'url(' . $skinFolder . 'pan' . $this->id . '.gif)';
                $inlineButtonStyle = str_ireplace('$SKINICON', $skinIconUrl, $inlineButtonStyle);
                $iconCssUrl = $resolvedIcon !== null ? 'url(' . $resolvedIcon . ')' : '';
                $inlineButtonStyle = str_ireplace('$ICON', $iconCssUrl, $inlineButtonStyle);
                $styleAttr = ' style="' . htmlspecialchars($inlineButtonStyle, ENT_QUOTES) . '"';

                if ($hasSkinIconVar || ($hasIconVar && $resolvedIcon !== null) || stripos($inlineButtonStyle, 'url(') !== false) {
                    $hasBackgroundIcon = true;
                }
            }
        }

        $iconHtml = '';
        if ($resolvedIcon !== null && !$hasBackgroundIcon) {
            $iconHtml = "<img src=\"" . htmlspecialchars($resolvedIcon, ENT_QUOTES) . "\" class=\"dimx-btn-icon\" alt=\"\" /> ";
        }

        return "<button type=\"button\" class=\"dimx-btn\" id=\"btn_{$idEsc}\" data-cmd=\"{$idEsc}\" data-model=\"{$modelEsc}\" title=\"{$titleEsc}\"{$styleAttr} onclick=\"sendCmd('{$idEsc}');\">{$iconHtml}{$captionEsc}</button>\n";
    }

    protected function renderDropdown(
        ?Skin $skin = null,
        ?World $world = null,
        ?DimxObject $owner = null,
        mixed $agent = null,
        mixed $target = null
    ): string {
        $idEsc = htmlspecialchars($this->id, ENT_QUOTES);

        $styleAttr = '';
        if ($skin !== null && trim($skin->listStyle) !== '') {
            $rawListStyle = trim($skin->listStyle);
            if (preg_match('/\{([^}]*)\}/s', $rawListStyle, $lsm)) {
                $inlineListStyle = trim($lsm[1]);
            } else {
                $inlineListStyle = trim($rawListStyle, " \t\n\r\0\x0B{}");
            }
            $bgColor = trim((string)($skin->list2BgColor ?? ''));
            if ($bgColor !== '' && !preg_match('/\bbackground(?:-color)?\s*:/i', $inlineListStyle)) {
                $inlineListStyle = 'background:' . $bgColor . ';' . $inlineListStyle;
            }
            if ($inlineListStyle !== '') {
                $styleAttr = ' style="' . htmlspecialchars($inlineListStyle, ENT_QUOTES) . '"';
            }
        }

        $w = $world
            ?? $this->world
            ?? $owner?->world
            ?? (($agent instanceof DimxObject) ? $agent->world : null)
            ?? GameEngine::$activeInstance?->getWorld();

        $options = $this->options;
        if (empty($options) && $this->event !== '' && $w !== null) {
            try {
                $evalList = $w->evaluateExpression($this->event, $owner, $agent, $target);
                if (is_array($evalList)) {
                    $options = $evalList;
                } elseif (is_string($evalList) && $evalList !== '') {
                    foreach (explode(',', $evalList) as $part) {
                        $part = trim($part);
                        if ($part === '') {
                            continue;
                        }
                        if (str_contains($part, '=')) {
                            [$k, $v] = explode('=', $part, 2);
                            $options[trim($k)] = trim($v);
                        } else {
                            $options[$part] = '';
                        }
                    }
                }
            } catch (\Throwable $e) {
                $options = [];
            }
        }

        $defVal = $this->caption;
        if ($this->caption !== '' && $w !== null) {
            try {
                $evalDef = $w->evaluateExpression($this->caption, $owner, $agent, $target);
                $s = '';
                if ($evalDef instanceof DimxObject) {
                    $s = $evalDef->id;
                } elseif ($evalDef !== null && $evalDef !== false) {
                    $s = (string)$evalDef;
                }
                if ($s !== '') {
                    $defVal = $s;
                }
            } catch (\Throwable $e) {
                // Keep literal $this->caption as fallback
            }
        }

        $autoSubmitAttr = (strcasecmp($this->eventModel, 'AUTOSUBMIT') === 0)
            ? ' onchange="this.form && this.form.submit();"'
            : '';

        $html = "<select id=\"{$idEsc}\" name=\"{$idEsc}\" class=\"dimx-dropdown\"{$styleAttr}{$autoSubmitAttr}>";
        foreach ($options as $val => $lbl) {
            $keyStr = (string)$val;
            if ($lbl instanceof DimxObject) {
                $lblStr = $lbl->getName();
            } else {
                $lblStr = ActionsRunner::formatValue($lbl);
            }
            if ($lblStr === '') {
                $lblStr = $keyStr;
            }
            $selectedAttr = ($defVal !== '' && strcasecmp($keyStr, (string)$defVal) === 0) ? ' selected' : '';
            $valEsc = htmlspecialchars($keyStr, ENT_QUOTES);
            $lblEsc = htmlspecialchars($lblStr, ENT_QUOTES);
            $html .= "<option value=\"{$valEsc}\"{$selectedAttr}>{$lblEsc}</option>";
        }
        $html .= "</select>\n";
        return $html;
    }
}
