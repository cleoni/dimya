<?php
declare(strict_types=1);

namespace Dimx;

/**
 * Panel
 *
 * Command panel container grouping interactive UI controls (buttons, textboxes, dropdowns).
 */
class Panel
{
    public string $id = '';

    /** @var array<string, Ctrl> Controls collection indexed by control ID */
    public array $buttons = [];

    public ?string $hasFocus = null;

    public function __construct(string $id = 'default')
    {
        $this->id = $id;
    }

    public function addButton(Ctrl $ctrl): void
    {
        $this->buttons[$ctrl->id] = $ctrl;
    }

    public function removeButton(string $id): void
    {
        unset($this->buttons[$id]);
    }

    public function getButton(string $id): ?Ctrl
    {
        return $this->buttons[$id] ?? null;
    }

    /**
     * Renders all panel controls into HTML.
     */
    public function toHtml(?Skin $skin = null): string
    {
        $html = "<div class=\"dimx-panel\" id=\"panel_" . htmlspecialchars($this->id, ENT_QUOTES) . "\">\n";
        foreach ($this->buttons as $ctrl) {
            $html .= $ctrl->toHtml($skin);
        }
        $html .= "</div>\n";
        return $html;
    }

    /**
     * Factory to generate the standard DimensioneX default command panel.
     */
    public static function createDefaultPanel(): self
    {
        $panel = new self('default');
        $panel->addButton(new Ctrl('look', Ctrl::TYPE_BUTTON, 'Look', 'Look around or inspect object', 'onLook'));
        $panel->addButton(new Ctrl('go', Ctrl::TYPE_BUTTON, 'Go', 'Travel in a direction or passage', 'onGo'));
        $panel->addButton(new Ctrl('use', Ctrl::TYPE_BUTTON, 'Use', 'Use an item or activate mechanism', 'onUse'));
        $panel->addButton(new Ctrl('pick', Ctrl::TYPE_BUTTON, 'Pick', 'Pick up item from room', 'whenPicked'));
        $panel->addButton(new Ctrl('drop', Ctrl::TYPE_BUTTON, 'Drop', 'Drop an item to the room', 'whenDropped'));
        $panel->addButton(new Ctrl('open', Ctrl::TYPE_BUTTON, 'Open', 'Open a container or door', 'onOpen'));
        $panel->addButton(new Ctrl('close', Ctrl::TYPE_BUTTON, 'Close', 'Close a container or door', 'onClose'));
        $panel->addButton(new Ctrl('search', Ctrl::TYPE_BUTTON, 'Search', 'Search for hidden items', 'onSearch'));
        $panel->addButton(new Ctrl('txtBox', Ctrl::TYPE_TEXTBOX, '', 'Enter speech or action...'));
        $panel->addButton(new Ctrl('say', Ctrl::TYPE_BUTTON, 'Say', 'Speak to characters in the room', 'onSay'));
        $panel->addButton(new Ctrl('help', Ctrl::TYPE_BUTTON, 'Help', 'Show help instructions', 'onHelp'));
        $panel->addButton(new Ctrl('save', Ctrl::TYPE_BUTTON, 'Save', 'Save current player data', 'onSave'));
        $panel->addButton(new Ctrl('exit', Ctrl::TYPE_BUTTON, 'Exit', 'Logout from the world', 'onQuit'));
        return $panel;
    }
}
