<?php
declare(strict_types=1);

namespace Dimx;

/**
 * PeopleContainer
 *
 * Base class for objects capable of containing people (Rooms and Vehicles/Items).
 * Provides occupant discovery, message broadcast to contained players, and UI panel binding.
 */
class PeopleContainer extends AdvObject
{
    public ?Panel $panel = null;

    public function __construct(
        ?World $world = null,
        string $name = '',
        string $id = '',
        string $description = '',
        ?string $icon = null,
        string|array $attrList = '',
        int $capacity = 1000,
        int $volume = 0,
        bool $setDefShowpos = false,
        string $defContainer = ''
    ) {
        parent::__construct(
            $world, $name, $id, $description, $icon, $attrList,
            $capacity, $volume, $setDefShowpos, $defContainer
        );
    }

    /**
     * Broadcasts a message to all characters/players inside this container.
     */
    public function display(string $msg): bool
    {
        if ($this->world !== null) {
            $this->world->log("Displaying: {$msg} in room {$this->id}");
        }

        foreach ($this->contents as $obj) {
            if ($obj instanceof Character) {
                $obj->display($msg);
            }
        }
        return true;
    }

    /**
     * Broadcast speech to all players inside this container.
     * Matches DimensioneX PeopleContainer.speak().
     */
    public function speak(string $msg, ?Character $from = null): bool
    {
        if ($msg !== '') {
            $this->world?->log("Speaking: {$msg} in room {$this->id}");
            foreach ($this->contents as $obj) {
                if ($obj instanceof Character) {
                    $obj->display($msg);
                }
            }
        }
        return true;
    }

    /**
     * Broadcasts a one-shot sound FX to all players inside this container.
     */
    public function playSound(string $soundfile): bool
    {
        if ($this->world !== null) {
            $this->world->log("Sounding: {$soundfile} in room {$this->id}");
        }

        foreach ($this->contents as $obj) {
            if ($obj instanceof Character) {
                $obj->playSound($soundfile);
            }
        }
        return true;
    }

    /**
     * Sets the background music property on this container/room and broadcasts it to all players inside.
     */
    public function playBackground(string $soundfile, bool $loop = false): bool
    {
        $soundfile = trim($soundfile);
        $resolved  = ($soundfile !== '' && $this->world !== null)
            ? $this->world->resolveSoundUrl($soundfile)
            : $soundfile;

        $this->backgroundMusic = ($resolved !== '') ? $resolved : null;
        $this->backgroundMusicLoop = $loop;
        $this->setProperty('backgroundMusic', $this->backgroundMusic);
        $this->setProperty('backgroundMusicLoop', $loop);

        if ($this->world !== null) {
            $this->world->log("Setting background sound: {$soundfile} in room {$this->id}");
        }

        foreach ($this->contents as $obj) {
            if ($obj instanceof Character) {
                $obj->playBackground($soundfile, $loop);
            }
        }
        return true;
    }

    /**
     * Send command to all players inside this container.
     * Matches DimensioneX PeopleContainer.sendCmd().
     */
    public function sendCmd(string $cmd): bool
    {
        if ($this->isaRoom() && $this->world !== null) {
            $this->world->log("Sending: {$cmd} in {$this->id}");
        }
        $notified = [];
        foreach ($this->contents as $obj) {
            if ($obj instanceof Player) {
                $obj->sendCmd($cmd);
                $notified[$obj->name] = true;
            }
        }
        if ($this->world !== null && is_array($this->world->players)) {
            foreach ($this->world->players as $p) {
                if ($p instanceof Player && !isset($notified[$p->name])) {
                    if ($p->container === $this || ($p->getRoom() && $p->getRoom()->id === $this->id)) {
                        $p->sendCmd($cmd);
                        $notified[$p->name] = true;
                    }
                }
            }
        }
        return true;
    }

    /**
     * Retrieve current UI panel.
     */
    public function getPanel(): ?Panel
    {
        return $this->panel;
    }

    /**
     * Set active UI command panel.
     */
    public function setPanel(string|Panel|null $panel): bool
    {
        $panelId = is_string($panel) ? $panel : ($panel?->id ?? '');
        $this->world?->log("Setting panel: {$panelId} in: {$this->id}");

        if (is_string($panel)) {
            $panel = $this->world?->getPanel($panel);
        }
        $this->panel = $panel;
        return true;
    }

    /**
     * Returns all Character and Player objects currently inside this container.
     * @return array<string, Character>
     */
    public function getOccupants(): array
    {
        $occupants = [];
        foreach ($this->contents as $id => $obj) {
            if ($obj instanceof Character) {
                $occupants[$id] = $obj;
            }
        }
        return $occupants;
    }

    /**
     * Returns all Item objects currently inside this container.
     * @return array<string, Item>
     */
    public function getItems(): array
    {
        $items = [];
        foreach ($this->contents as $id => $obj) {
            if ($obj instanceof Item) {
                $items[$id] = $obj;
            }
        }
        return $items;
    }

    /**
     * Returns all Vehicle objects currently inside this container.
     * @return array<string, Vehicle>
     */
    public function getVehicles(): array
    {
        $vehicles = [];
        foreach ($this->contents as $id => $obj) {
            if ($obj instanceof Vehicle || ($obj instanceof Item && $obj->isVehicle())) {
                $vehicles[$id] = $obj;
            }
        }
        return $vehicles;
    }
}
