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
