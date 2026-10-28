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
            // In the HTML presentation layer, clicking an object/character triggers the click action directly;
            // an explicit 'Click' button is redundant for human players.
            if (strtolower($ctrl->id) === 'click') {
                continue;
            }
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
        $panel->addButton(new Ctrl('look', Ctrl::TYPE_BUTTON, 'Look', 'Look around or inspect object', 'onLook', null, 'O'));
        $panel->addButton(new Ctrl('click', Ctrl::TYPE_BUTTON, 'Click', 'Inspect or interact with object', 'onLook', null, 'O'));
        $panel->addButton(new Ctrl('go', Ctrl::TYPE_BUTTON, 'Go', 'Travel in a direction or passage', 'onGo', null, 'O'));
        $panel->addButton(new Ctrl('use', Ctrl::TYPE_BUTTON, 'Use', 'Use an item or activate mechanism', 'onUse', null, 'O'));
        $panel->addButton(new Ctrl('use2', Ctrl::TYPE_BUTTON, 'Use with', 'Use an item with another object', 'onUseWith', null, 'OO'));
        $panel->addButton(new Ctrl('pick', Ctrl::TYPE_BUTTON, 'PickUp', 'Pick up item from room', 'whenPicked', null, 'O'));
        $panel->addButton(new Ctrl('drop', Ctrl::TYPE_BUTTON, 'Drop', 'Drop an item to the room', 'whenDropped', null, 'O'));
        $panel->addButton(new Ctrl('open', Ctrl::TYPE_BUTTON, 'Open', 'Open a container or door', 'onOpen', null, 'O'));
        $panel->addButton(new Ctrl('close', Ctrl::TYPE_BUTTON, 'Close', 'Close a container or door', 'onClose', null, 'O'));
        $panel->addButton(new Ctrl('search', Ctrl::TYPE_BUTTON, 'Search', 'Search for hidden items', 'onSearch', null, ''));
        $panel->addButton(new Ctrl('give', Ctrl::TYPE_BUTTON, 'Give', 'Give an item to another character', 'onReceiveItem', null, 'OO'));
        $panel->addButton(new Ctrl('enter', Ctrl::TYPE_BUTTON, 'Enter', 'Enter vehicle', 'onEnter', null, 'O'));
        $panel->addButton(new Ctrl('exit', Ctrl::TYPE_BUTTON, 'Exit', 'Exit vehicle', 'onExit', null, ''));
        $panel->addButton(new Ctrl('txtBox', Ctrl::TYPE_TEXTBOX, '', 'Enter speech or action...'));
        $panel->addButton(new Ctrl('say', Ctrl::TYPE_BUTTON, 'Say', 'Speak to characters in the room', 'onSay', null, 'TO'));
        $panel->addButton(new Ctrl('help', Ctrl::TYPE_BUTTON, 'Help', 'Show help instructions', 'onHelp', null, ''));
        $panel->addButton(new Ctrl('save', Ctrl::TYPE_BUTTON, 'Save', 'Save current player data', 'onSave', null, 'T'));
        $panel->addButton(new Ctrl('logout', Ctrl::TYPE_BUTTON, 'Disconnect', 'Logout from the world', 'onQuit', null, ''));
        return $panel;
    }

    /**
     * Factory to generate the DimensioneX prebuilt 'chat' command panel.
     * Allows Look, Click, Use, speech box (txtBox), Say, Save, and Disconnect (logout) commands.
     */
    public static function createChatPanel(): self
    {
        $panel = new self('chat');
        $panel->addButton(new Ctrl('look', Ctrl::TYPE_BUTTON, 'Look', 'Look around or inspect object', 'onLook', null, 'O'));
        $panel->addButton(new Ctrl('click', Ctrl::TYPE_BUTTON, 'Click', 'Inspect or interact with object', 'onLook', null, 'O'));
        $panel->addButton(new Ctrl('use', Ctrl::TYPE_BUTTON, 'Use', 'Use an item or activate mechanism', 'onUse', null, 'O'));
        $panel->addButton(new Ctrl('txtBox', Ctrl::TYPE_TEXTBOX, '', 'Enter speech...'));
        $panel->addButton(new Ctrl('say', Ctrl::TYPE_BUTTON, 'Say', 'Speak to characters in the room', 'onSay', null, 'TO'));
        $panel->addButton(new Ctrl('save', Ctrl::TYPE_BUTTON, 'Save', 'Save current player data', 'onSave', null, 'T'));
        $panel->addButton(new Ctrl('logout', Ctrl::TYPE_BUTTON, 'Disconnect', 'Logout from the world', 'onQuit', null, ''));
        return $panel;
    }

    /**
     * Returns the list of command identifiers accepted by this panel.
     * Buttons and commands (excluding pure UI controls like labels/textboxes) are commands.
     *
     * @return array<string>
     */
    public function getAvailableCommands(): array
    {
        $cmds = [];
        foreach ($this->buttons as $ctrl) {
            if ($ctrl->type === Ctrl::TYPE_BUTTON || $ctrl->type === Ctrl::TYPE_SUBMIT || $ctrl->type === Ctrl::TYPE_CMD) {
                $cmdId = strtolower($ctrl->id);
                if (!empty($cmdId)) {
                    $cmds[] = $cmdId;
                    if ($cmdId === 'enter') {
                        $cmds[] = 'board';
                    }
                    if ($cmdId === 'exit') {
                        $cmds[] = 'leave';
                        $cmds[] = 'disembark';
                    }
                    if ($cmdId === 'look') {
                        $cmds[] = 'click';
                    }
                    if ($cmdId === 'use2') {
                        $cmds[] = 'usewith';
                    }
                    if ($cmdId === 'click') {
                        $cmds[] = 'look';
                    }
                    if ($cmdId === 'pick') {
                        $cmds[] = 'take';
                        $cmds[] = 'get';
                    }
                }
            }
        }
        // Move / navigation / rotation commands are always valid in DimensioneX
        foreach (['go', 'rotl', 'rotr', 'rev'] as $navCmd) {
            if (!in_array($navCmd, $cmds, true)) {
                $cmds[] = $navCmd;
            }
        }
        return array_values(array_unique($cmds));
    }

    /**
     * Check if a specific command is allowed by this panel.
     */
    public function isCommandAllowed(string $cmd): bool
    {
        $cmd = strtolower(trim($cmd));
        // System connection handshake commands like 'login' are always permitted
        if ($cmd === 'login') {
            return true;
        }
        // Move / rotation commands are always valid and produce effect whenever there is a valid link/face
        if ($cmd === 'go' || in_array($cmd, ['n', 's', 'e', 'w', 'u', 'd', 'north', 'south', 'east', 'west', 'up', 'down', 'rotl', 'rotr', 'rev', 'rotate'], true)) {
            return true;
        }
        // Look and click commands are always valid to inspect objects
        if (in_array($cmd, ['look', 'click'], true)) {
            return true;
        }
        $allowed = $this->getAvailableCommands();
        return in_array($cmd, $allowed, true);
    }

    public function __toString(): string
    {
        $btnIds = array_map(fn($b) => $b->id, $this->buttons);
        return $this->id . ': ' . implode(', ', $btnIds);
    }

    /**
     * Generate HTML snapshot fragment for this panel.
     */
    public function htmlAdminSnapshot(): string
    {
        return "<li> " . htmlspecialchars((string)$this, ENT_QUOTES, 'UTF-8') . "</li>\n";
    }
}
