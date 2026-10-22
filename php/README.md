# DimensioneX PHP Game Engine & Object Hierarchy

Modern PHP 8.1+ implementation of the **DimensioneX** virtual world and multiplayer interactive fiction engine, designed according to the official **Developer's Reference (v7.1.7)** and the [**DXW Language Specification**](../docs/DXW_SPECIFICATION.md).

---

## 1. Architectural Highlights

* **Pure Native Data Structures & Loose Scalar Typing**:
  * All game scalar values (`int`, `bool`, `string`, `float`) are loosely typed as in standard PHP, allowing dynamic context-dependent coercion without strict typing restrictions.
  * The Java `Dict` / `DictSorted` associative collection has been replaced with PHP's native associative arrays (`array`).
  * The Java `Token` polymorphic type wrapper has been replaced with PHP's native dynamic typing and union types (`mixed`).
* **Web Interfaces & Player Types**:
  * `multiplayer.php`: General frontend gateway that dynamically detects player type:
    * **Human players** receive full graphical HTML with images, layout, action forms, and polling chat via [`UIhtml`](src/UIhtml.php).
    * **Bot / AI players** receive structured JSON responses (room snapshot, items, occupants, exits, messages) via [`UIjson`](src/UIjson.php) acting as a REST API endpoint.
  * `maint.php`: Replaces Java `cleoni.adv.Maint` servlet as an administrative console for checking MySQL connectivity, initializing tables, and managing saved profiles.
* **Separation of Concerns & Concurrency**:
  * [`GameEngine`](src/GameEngine.php): Central processor encapsulating world loading, transaction locking (`WorldLock::executeAtomic`), entity mutations, events, and persistence, completely separated from UI presentation.
  * [`UI`](src/UI.php), [`UIhtml`](src/UIhtml.php), [`UIjson`](src/UIjson.php): Pluggable presentation hierarchy attached to the `Player::$ui` property.
* **Persistent Storage**:
  * Handled by [`PersistenceManager`](src/PersistenceManager.php) using **MySQL via PDO**.
  * Implements transparent automatic fallback to structured JSON file storage if MySQL is unconfigured or offline (matching DimensioneX's fallback on `onDbDown`).

---

## 2. PHP Class Hierarchy

All core game entities reside in the `Dimx\` namespace under [`src/`](src/):

```
                       ManageableObject
        ┌──────────────────────┼───────────────────────┐
    DimxObject               Image                   Page
        │                      │
   ┌────┴────────────────┐   Event
   │                     │
 World               AdvObject
             ┌───────────┴───────────┐
      PeopleContainer               Link          Character
        ┌────┴────┐                                   │
      Room      Item                               Player
                  │
               Vehicle
```

### Class Responsibilities:
1. [`ManageableObject`](src/ManageableObject.php): Dynamic property and method dispatch table (`getProperty()`, `setProperty()`, `execMethod()`, `hasMethod()`).
2. [`DimxObject`](src/DimxObject.php): Base entity with `id`, `name`, `world`, and associative array `contents`.
3. [`AdvObject`](src/AdvObject.php): Spatial adventure object with `container`, `capacity`, `volume`, `facing`, `hidden`, `images`, `showmode`, and `move()`.
4. [`PeopleContainer`](src/PeopleContainer.php): Base container capable of holding people/characters (`Room` and `Item`/`Vehicle`), broadcasts messages to all occupants.
5. [`Room`](src/Room.php): Virtual location in the world with `ways` (associative array of exits), map coordinates, and weather overlay effects (`effect`).
6. [`Item`](src/Item.php): Interactive object with `type.subtype`, `pickable`, `restoreTo()`, and `worldChange()`.
7. [`Vehicle`](src/Vehicle.php): Movable item that transports passengers and forwards room navigation links to drivers.
8. [`Link`](src/Link.php): Passages/exits between rooms supporting bidirectional routing, orientations (`N`, `S`, `E`, `W`, `U`, `D`), and lock states.
9. [`Character`](src/Character.php): Computer-controlled actors / NPCs with `accepts` rules, `go()`, and `say()`.
10. [`Player`](src/Player.php): Human player web session with credentials, cookies, score, inventory, and client console buffers.
11. [`World`](src/World.php): Game environment aggregate root running clock ticks (`onTick`, `Living`), entity indices, and event dispatching (`$OWNER`, `$AGENT`, `$TARGET`).
12. [`Image`](src/Image.php): Media asset descriptor rendering responsive `<img>` tags.
13. [`Event`](src/Event.php): Script hook executing game event logic.
14. [`Page`](src/Page.php): HTML template processor with macro substitution (`$scene`, `$icons`, `$inventory`, `$navpad`, `$messages`).
15. [`Ctrl`](src/Ctrl.php) & [`Panel`](src/Panel.php): Interactive UI controls and command button bars.
16. [`Skin`](src/Skin.php): Theme colors, fonts, and CSS styling.
17. [`PersistenceManager`](src/PersistenceManager.php): PDO MySQL database repository with JSON fallback.

---

## 3. Database Setup (MySQL)

If using MySQL, create a database and import [`schema.sql`](schema.sql):

```bash
mysql -u root -p dimx_game < schema.sql
```

Configure your credentials in `multiplayer.php` and `maint.php`:
```php
$dbConfig = [
    'host' => '127.0.0.1',
    'port' => 3306,
    'dbname' => 'dimx_game',
    'user' => 'root',
    'pass' => 'your_password',
];
```
*(If MySQL is not reachable, the engine will automatically store files in `php/data/`)*.

---

## 4. Running the Web Application

With PHP installed, launch the built-in development server from the `php/` directory:

```bash
cd php
php -S localhost:8000
```

Then visit:
* Game Client: `http://localhost:8000/multiplayer.php`
* Admin Console: `http://localhost:8000/maint.php`

---

## 5. Bot & AI Player API Usage

Bot players or AI agents can interact with the engine via `multiplayer.php` without HTML overhead:

### Request Parameters
Supply any of the following to activate the JSON API endpoint:
* Query/POST flag: `?format=json` or `?ui=json` or `?bot=1` or `?api=1`
* Header: `Accept: application/json` or `Content-Type: application/json`

### Example Commands
* **Login & Look**:
  ```bash
  curl -s -X POST "http://localhost:8000/multiplayer.php?ui=json" \
    -d "username=BotAlpha&cmd=look"
  ```
* **Navigate / Go**:
  ```bash
  curl -s -X POST "http://localhost:8000/multiplayer.php?ui=json" \
    -d "username=BotAlpha&cmd=go&param=E"
  ```
* **Pick Up Item**:
  ```bash
  curl -s -X POST "http://localhost:8000/multiplayer.php?ui=json" \
    -d "username=BotAlpha&cmd=pick&target=apple"
  ```
* **Drop Item**:
  ```bash
  curl -s -X POST "http://localhost:8000/multiplayer.php?ui=json" \
    -d "username=BotAlpha&cmd=drop&target=apple"
  ```
* **Chat / Speech**:
  ```bash
  curl -s -X POST "http://localhost:8000/multiplayer.php?ui=json" \
    -d "username=BotAlpha&cmd=say&param=Hello+world!"
  ```

### JSON Response Format
```json
{
  "success": true,
  "status": "ok",
  "lock_error": false,
  "world": { "id": "default", "name": "DimensioneX Demo World", "mode": 1, "tick_length": 30 },
  "player": {
    "id": "BotAlpha",
    "name": "BotAlpha",
    "score": 0,
    "health": 100,
    "capacity": 5,
    "volume": 10,
    "carrying_count": 0,
    "inventory": []
  },
  "room": {
    "id": "beach",
    "name": "Sunny Beach",
    "description": "A warm sandy beach with gentle waves.",
    "image": null,
    "items": [
      { "id": "apple", "name": "Fresh Apple", "pickable": true, "volume": 1, "type": "food.apple" }
    ],
    "occupants": [],
    "exits": [
      { "direction": "E", "link_id": "path_bar", "name": "Path to Bar", "target_room_id": "bar", "open": true }
    ]
  },
  "messages": ["Welcome to DimensioneX Demo World, BotAlpha!"],
  "command": { "cmd": "look", "param": "", "target": "" }
}
```
