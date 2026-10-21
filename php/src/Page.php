<?php
declare(strict_types=1);

namespace Dimx;

/**
 * Page
 *
 * Implements HTML template processing for game views.
 * Replaces standard DimensioneX placeholders ($scene, $icons, $inventory, $navpad, $messages)
 * with rendered HTML content.
 */
class Page extends ManageableObject
{
    public string $id = '';
    public string $template = '';

    public function __construct(string $id, string $template = '')
    {
        parent::__construct();
        $this->id = $id;
        $this->template = $template;
    }

    /**
     * Renders template by substituting placeholders with associative replacements.
     * @param array<string, string> $replacements
     */
    public function toHtml(array $replacements): string
    {
        $html = $this->template;
        foreach ($replacements as $placeholder => $content) {
            $tag = str_starts_with($placeholder, '$') ? $placeholder : '$' . $placeholder;
            $html = str_ireplace($tag, (string)$content, $html);
        }
        return $html;
    }
}
