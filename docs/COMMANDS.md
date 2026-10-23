# DimensioneX Game Engine Command Specification & Reference

This document defines the authoritative command catalog and execution model for the **DimensioneX** game engine. All commands are processed through [`GameEngine::processCommand()`](file:///c:/xampp/htdocs/dimya/php/src/GameEngine.php) within concurrency-guarded critical sections, dispatching events to game objects and formatting output through [`UIhtml`](file:///c:/xampp/htdocs/dimya/php/src/UIhtml.php), [`UIbot`](file:///c:/xampp/htdocs/dimya/php/src/UIbot.php), or [`UIjson`](file:///c:/xampp/htdocs/dimya/php/src/UIjson.php).

---

## 1. Command Execution Lifecycle

Every command cycle follows this processing pipeline:

```
[Player / Bot Request]
        │
        ▼
[UI::parseInput()] (Extracts cmd, param, target)
        │
        ▼
[GameEngine::processCommand()]
        │
 ┌──────┴───────────────────────────────────────────────────────┐
 │ Concurrency Guard: WorldLock::executeAtomic (MySQL GET_LOCK) │
 │                                                              │
 │ 1. Load or Seed World                                        │
 │ 2. Ensure Player in starting room                            │
 │ 3. Dispatch Command Action (Core or Custom BUTTON Handler)   │
 │ 4. Fire Script Events ($OWNER, $AGENT, $TARGET)              │
 │ 5. World Clock Tick ($world->tick() & Living checks)         │
 │ 6. Drain Player Console Buffer                               │
 │ 7. Persist Mutated World State                               │
 └──────┬───────────────────────────────────────────────────────┘
        │
        ▼
[UI::render()] ──► HTML Browser (UIhtml) OR JSON Record (UIjson)
```

---

## 2. Built-in Core Commands Catalog

### Summary Table

| Command | Arguments | Aliases | Description / Primary Effect | Events Fired |
| :--- | :--- | :--- | :--- | :--- |
| **`click`** | `target` | - | Context-sensitive click on an object | `onClick` (defaults to `go` for Links, `look` for others) |
| **`go`** | `link_or_dir` | `walk`, `move` | Traverses a Link or direction (`N`, `S`, `E`, `W`, `U`, `D`) | `onExit`, `onEnter` |
| **`rotate`** | `[direction]` | `rotr`, `rotl` | Rotates player facing orientation (N ➔ E ➔ S ➔ W) | `onRotate` |
| **`look`** | `[target]` | `l` | Inspects an object or current room, returning description and items | `onLook` |
| **`use`** | `target` | - | Uses a single item | `item.onUse`, `onUse` |
| **`use2`** | `item1`, `item2` | `usewith`, `combine`| Uses item1 with item2 (tool with target) | `item1.onUseWith(item2)` |
| **`open`** | `target` | - | Opens a container, door, or link | `onOpen` |
| **`close`** | `target` | - | Closes an opened container, door, or link | `onClose` |
| **`pick`** | `target` | `take`, `get` | Picks up an item from room into player inventory | `whenPicked` |
| **`drop`** | `target` | - | Drops carried item into current room | `whenDropped` |
| **`search`** | - | - | Reveals hidden objects in container (`visible = 1`) | `onSearch` |
| **`hide`** | `target` | - | Conceals an object (`visible = 0`) | `onHide` |
| **`put`** | `item`, `dest` | `insert` | Puts an item into a destination container | `whenPut` |
| **`give`** | `item`, `character`| - | Offers an item to an NPC or another player | `whenGiven`, `whenReceived` |
| **`enter`** | `vehicle` | `board` | Enters a vehicle item | `onEnterVehicle` |
| **`exit`** | - | `leave`, `disembark` | Disembarks vehicle into the surrounding room | `onExitVehicle` |
| **`say`** | `text`, `[target]` | - | Speaks aloud to room/world, or directly to a specific person if target provided | `onHear` |
| **`save`** | - | - | Saves current player data (location, inventory, properties, score) | `onSave` |
| **`login`** | `[password]` | - | Authenticates player and displays initial room snapshot | `onLogin` |
| **`exit`** | - | `logout` | Exits virtual world (logout); carried items drop to the floor | `onQuit`, `onExit` |

> [!IMPORTANT]
> **Legacy DimensioneX `savexit` Removed**: The old combined `savexit` ("Save & Exit") command from DimensioneX has been completely removed. It is replaced by two distinct, independent commands:
> - **`save`**: Saves current player data to the database without disconnecting.
> - **`exit`** (or **`logout`**): Disconnects from the world, dropping carried inventory to the room floor.

---

## 3. Detailed Command Behaviors

### 1. `click (object)`
* **Intent**: Universal point-and-click interaction default.
* **Mechanism**:
  1. Looks up `target` in room, inventory, or world.
  2. If the object defines an `onClick` event, executes it.
  3. If no `onClick` event is registered:
     * If object is a **`Link`**: automatically defaults to `go (link)`.
     * For all other objects: automatically defaults to `look (object)`.

### 2. `go (link)`
* **Intent**: Navigation between rooms through passage links.
* **Parameters**: Direction string (`N`, `S`, `E`, `W`, `U`, `D`) or link identifier.
* **Checks**:
  * Link must exist in current room.
  * Link must be open (`$link->isOpen()`). If closed or locked, movement is prevented.
* **Events**:
  * Fires `onExit` on current room / character.
  * Fires `onEnter` on target destination room.

### 3. `rotate`
* **Intent**: Changes player compass facing angle.
* **Parameters**: Optional direction or rotation step (`rotr` for clockwise, `rotl` for counter-clockwise).
* **Behavior**: Updates `$player->facing` through `N` ➔ `E` ➔ `S` ➔ `W` (or sets facing directly).

### 4. `look [object]`
* **Intent**: Inspect room surroundings or specific items/characters.
* **Behavior**:
  * If no target specified: evaluates room description and lists visible occupants and items.
  * If target specified: evaluates object description and lists any contained items if it is an open container.
* **Events**: Fires `onLook` on the target object.

### 5. `use (object)`
* **Intent**: Single-object interaction (e.g. drinking potion, pulling lever).
* **Location**: Target item must be in player inventory or in the current room.
* **Events**: Fires `[item.id].onUse`, followed by generic `onUse`.

### 6. `use2 (object1), (object2)`
* **Intent**: Dual-object interaction (e.g. key on chest, flint on torch).
* **Location**: `object1` is usually held by player; `object2` is held or in the room.
* **Events**: Fires `[object1.id].onUseWith` with `$TARGET = object2`.

### 7. `open (object)` & `close (object)`
* **Intent**: Manipulate state of doors, chests, links, and vehicles.
* **Behavior**: Sets `open = 1` or `open = 0`. For containers, closing conceals contained items; opening reveals them.
* **Events**: Fires `onOpen` / `onClose`.

### 8. `pick (object)` & `drop (object)`
* **Intent**: Inventory management.
* **`pick`**: Verifies item is in current room, item is pickable (`pickable = 1`), and player has remaining capacity and volume. Moves item to player contents. Fires `whenPicked`.
* **`drop`**: Moves item from player inventory into current room. Fires `whenDropped`.

### 9. `search` & `hide (object)`
* **Intent**: Concealment and discovery.
* **`search`**: Reveals all hidden items and links in the current room by setting `hidden = 0` / `visible = 1`. Fires `onSearch`.
* **`hide`**: Conceals target object in room or inventory by setting `hidden = 1` / `visible = 0`. Fires `onHide`.

### 10. `put (object), (destination)`
* **Intent**: Placing an item into a container or shelf.
* **Checks**: Destination must be a container, not full, and accessible to player.
* **Events**: Fires `whenPut`.

### 11. `give (object), (character)`
* **Intent**: Handing an item to an NPC or another player in the room.
* **Checks**: Character must accept the item per its `accepts` filter rule.
* **Events**: Fires `whenGiven` on giver, `whenReceived` on recipient.

### 12. `enter (object)` & `exit`
* **Intent**: Boarding and disembarking vehicles.
* **`enter`**: Moves player into vehicle container (`$player->move($vehicle)`).
* **`exit`**: Moves player out of vehicle back into the room containing the vehicle.
* **Events**: Fires `onEnterVehicle` / `onExitVehicle`.

### 13. `say (text), [target]`
* **Intent**: Speech and communication.
* **Behavior**:
  * If `target` is omitted: broadcasts speech to current room or world (subject to world interphone settings).
  * If `target` is a room ID: delivers message to that room.
  * If `target` is a player/character name: whispers privately.
  * Speech messages from chat controls (`say`, speech textbox) and script instructions (`Speak`) are delivered directly to the chat messages queue (`dimx-cell-c` / `chat.php` / UIjson `'chat'`) and do not pollute the room description / console space.
* **Events**: Fires `onHear` on each receiving occupant.

### 14. `save`
* **Intent**: Saves current player data to the database without leaving the game.
* **Effect**: Persists score, room location, properties/attributes, and carried inventory items into `dimx_players`.
* **Events**: Fires `onSave`.
* **Output**: Displays confirmation message `"Game saved!"`.

### 15. `login [password]`
* **Intent**: Connects player or bot character to world.
* **Credentials & Storage**:
  * **Nickname**: Plaintext display name stored in `dimx_players.name`.
  * **Database Account ID (`id`)**: Permanent auto-increment integer (`id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`) indexing the account in the database.
  * **Game Engine Player ID (`$player->id`)**: Ephemeral string identifier living only in engine memory (e.g. `p_17_859e69cf`) that is generated anew on every login session and changes each time the user connects.
  * **Password**: One-way encrypted with cryptographically secure bcrypt (`password_hash($pass, PASSWORD_DEFAULT)`). Never stored or readable in plaintext.
* **Dual-Mode Behavior**:
  * **Existing Account**: If the nickname is already registered, verifies supplied password using `password_verify()`. If incorrect, rejects connection (HTML error alert or JSON `401 Unauthorized` with status `login_required`).
  * **New Account**: If the nickname does not exist, registers and creates the account automatically, stores the hashed password and bot flag (`is_bot`), generates the unique numeric ID, and connects player to the world.
* **Presentation Modes**:
  * **Human Browser (`UIhtml`)**: Displays login form with dedicated Nickname and Password fields (password masked via `<input type="password">`). On failure, displays inline error alert and pre-fills nickname.
  * **Bot API (`UIjson`)**: Accepts credentials via JSON body or query parameters (`username`, `password`). Returns structured JSON snapshot with player's `numeric_id` and `is_bot` boolean flag.
* **Effect**: Validates credentials, places player in start room, and generates initial room snapshot. Fires `onLogin`.

### 16. `exit` (alias `logout`)
* **Intent**: Exits the virtual world (logout).
* **Aliases**: `logout`.
* **Effect**:
  * **Drops all carried items in inventory onto the current room floor** (firing `whenDropped` for each item).
  * Notifies other room occupants that the player has left the game.
  * Fires `onQuit` and `onExit`.
  * Removes player from active world memory and clears the active session.
* **Legacy Replacement**: Completely replaces the old legacy DimensioneX combined `savexit` ("Save & Exit") command. Saving progress is an independent action performed via `save`.

---

## 4. Prebuilt Panels & Command Sets

Panels define the authoritative command sets accepted by the engine and exposed to human/bot players. In DimensioneY, two prebuilt panels are maintained:

1. **`default` PANEL**:
   - The full adventure gameplay command set.
   - Includes: `look`, `go`, `use`, `pick`, `drop`, `open`, `close`, `search`, `say` (`txtBox`), `help`, `save`, and `exit`.
2. **`chat` PANEL**:
   - A communication, inspection, and session command set.
   - Includes: `look`, `click`, `use`, speech box (`txtBox`), `say`, `save`, and `exit` (movement `go` is also always valid).
   - Inventory-altering actions (`pick`, `drop`, `open`, `close`, `search`, etc.) are disallowed and rejected while this panel is active.

### Script Instruction: `SetPanel`
Scripts can switch panels globally for the world or for specific containers/players:
```dxw
SetPanel $WORLD, "chat"      ' Enforces reduced chat-only command set globally
SetPanel $WORLD, "default"   ' Restores normal adventure gameplay command set
```

## 5. Custom Panel Button Commands

World developers can define new buttons and commands in `.dxw` world definitions:

```dxw
PANEL lab_controls
    BUTTON scan, "Scan Room", "Run bio-scanner", onScan
    BUTTON teleport, "Teleport", "Jump to sanctuary", onTeleport
END PANEL
```

When a player or bot issues a custom command (e.g. `cmd=scan`):
1. `GameEngine` checks built-in commands first.
2. If not a core command, it searches for registered script event hooks:
   - `world.events["cmd_scan"]`
   - `world.events["onScan"]`
3. Dispatches event with `$OWNER = $player`, `$AGENT = $player`, `$TARGET = $targetId`, and dynamic parameters passed in `$param`.

---

## 6. The Event Model & Special Script Variables (Developer's Reference §4.1.3 & §4.1.4)

SmallBasic scripts in DimensioneX and DimensioneY rely fundamentally on the system event model and four special variables: **`$OWNER`**, **`$AGENT`**, **`$TARGET`**, and **`$WORLD`**.

### 6.1 Event Parameter Roles (§4.1.3, Developer's Reference pp. 102–103)

Whenever an event fires (e.g. `onLook`, `onUseWith`, `onEnter`, `whenPicked`, `Living`), the engine automatically instantiates and binds the participating objects into predefined roles:

| Special Variable | Role Description | Concrete Examples |
|:---|:---|:---|
| **`$OWNER`** | The object that **owns** the event (the object to which the event is directly associated). | In `surfer.onLook`, the surfer is `$OWNER`. In `altar.onUseWith`, the altar is `$OWNER`. In container `chapel.onLooseItem`, the chapel is `$OWNER`. |
| **`$AGENT`** | The object (usually a `Player` or `Character`) that **caused** the action or triggered the event. | When a player clicks or looks at the surfer, the interacting player is `$AGENT`. When a player drops an apple, that player is `$AGENT`. When a character moves through a link, that character is `$AGENT`. |
| **`$TARGET`** | The secondary object, destination link/room, or situational argument involved in the interaction. | In `key.onUseWith`, the door being unlocked is `$TARGET`. In `onLook`, `$TARGET` is `"explicit"` if the Look command was invoked via button/command, or `""` (empty string) if triggered by clicking the object. In `onLooseItem`, `$TARGET` is the item leaving the container. In `onSpeechFinish`, `$TARGET` is the character who received the speech. |
| **`$WORLD`** | The global `World` instance representing the environment. | Provides direct access to world properties and configuration (e.g. `$WORLD.mode`, `$WORLD.name`, `$WORLD.tick_length`), world-wide broadcasting (`Broadcast`), and panel switching (`SetPanel $WORLD, "chat"`). |

#### Summary of Standard Roles by Event:

| Event | $OWNER | $AGENT | $TARGET | Cancellable? (Return 0) | Notes |
|:---|:---|:---|:---|:---:|:---|
| `onLook` | Object looked at | Character/Player looking | `"explicit"` (via Look button) or `""` (via click) | Yes | Returning `0` / `false` cancels description display |
| `onUse` | Item being used | Character using item | Target or `null` | No | Single item activation |
| `onUseWith` | First item / tool | Character using | Second item / target object | No | Combines or applies tool on target |
| `beforeOpen` | Item being opened | Character opening item | Unused | Yes | Returning `0` / `false` keeps item locked/closed |
| `onOpen` | Item being opened | Character opening item | Unused | No | Post-open trigger |
| `onClose` | Item being closed | Character closing item | Unused | No | Post-close trigger |
| `whenPicked` | Item being picked | Character picking up item | Character receiving item | No | Executes right before `onReceiveItem` |
| `whenDropped`| Item being dropped | Character dropping item | Container receiving item | No | Executes right before `onLooseItem` |
| `onEnter` | Object entering container | Character moving object | Container entered | No | Precedes `onReceive` |
| `onExit` | Object exiting container | Character moving object | Container exited | Yes | Returning `0` / `false` aborts movement |
| `onReceive` | Container receiving | Character causing move | Object entering container | Yes | Default `true`; returning `false` rejects receipt |
| `onLoose` | Container losing character | Character causing move | Destination Link or object | Yes | Returning `0` / `false` aborts departure |
| `onReceiveItem`| Container receiving item | Character causing move | Item being received | Yes | Triggers right after `whenPicked` |
| `onLooseItem` | Container losing item | Character causing move | Item being lost | Yes | Returning `0` / `false` cancels pickup/loss |
| `onSave` | Player saving | Player | Player (if Save) or `""` (if exit) | Yes | Returning `0` / `false` aborts save operation |
| `onSearch` | Item to be found | Character searching | Unused | Yes | Returning `0` / `false` leaves item hidden |
| `Living` | Character evaluated | Same character | Same character | Yes | Evaluated each tick; returning `0` / `false` causes NPC death |
| `onSpeechFinish`| Character speaking | Character speaking | Character receiving speech | No | Triggered when NPC finishes `Speak "*"` sequence |

---

### 6.2 Script Return Values & Action Cancellation (§4.1.4, Developer's Reference p. 103)

1. **Default Success Semantics**:
   All SmallBasic event scripts implicitly return success (`true` / `1`) when execution completes without an explicit `Return` statement.
2. **Action Cancellation**:
   For cancellable events (listed in the table above), scripts can return `0` or `false` using the `Return` instruction:
   ```dxw
   EVENT Living
   ACTIONS
       ' Restores health slightly each clock tick
       $OWNER.Health = $OWNER.Health + 0.2
       If $OWNER.Health > 10
           $OWNER.Health = 10
       End_If
       ' Return non-zero to keep character alive; return 0/false causes character to die
       Return $OWNER.Health > 0
   END_EVENT
   ```
   ```dxw
   EVENT chest.beforeOpen
   ACTIONS
       If $AGENT.has_key <> 1
           Display "The chest is firmly locked! You need a golden key."
           Return 0    ' Cancels open action; chest remains closed
       End_If
       Return 1        ' Chest unlocks and opens
   END_EVENT
   ```
3. **Execution Termination**:
   Executing `Return <expression>` immediately terminates execution of the event script, returning the evaluated boolean or numeric scalar back to the engine caller.

