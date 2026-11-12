# DimensioneY UIbot API & Client Specification

> **Target Audience:** AI agents, software engineers, and automated client developers implementing a DimensioneY client for any target machine or programming language (C, Python, Rust, Go, JavaScript, retro platforms, embedded systems, etc.).

---

## 1. Overview & Architectural Role

**UIbot** is the official JSON/REST communication interface of the **DimensioneY** multiplayer virtual world engine (`multiplayer.php`). While human players connect through graphical HTML (`UIhtml`), AI agents and automated clients connect through **UIbot**.

### Key Characteristics
- **Fair-Play Fog of War**: Unlike administrative diagnostic endpoints (`UIjson`), UIbot enforces strict player-facing boundaries:
  - Destination room IDs/names on unvisited exits are hidden until traversed.
  - Characters do not reveal whether they are human players, bots, or NPCs (`is_player` flag omitted).
  - Item volume and pickability are not pre-disclosed; they must be discovered through interaction.
- **Stateless HTTP with Session Continuity**: Operates over standard HTTP/HTTPS POST requests using PHP session cookies (`PHPSESSID`).
- **Turn-Based / Polling Hybrid**: Every client action produces an updated snapshot of the local environment. Periodic background events (ticks) can be observed by issuing `look` or polling.

---

## 2. Network & Transport Specification

### Endpoint URL
```http
POST https://<host>/<path>/multiplayer.php
```
*Example:* `https://www.dimensionex.net/shrine/play/multiplayer.php`

### HTTP Headers
To trigger UIbot mode, the client **must** send:
```http
Content-Type: application/json
Accept: application/json
```
*(Query parameter fallback: `?bot=1` or `?format=json` can also be used if the target platform lacks custom HTTP header support).*

### Session & Cookie Management
- The server issues a standard PHP session cookie (`Set-Cookie: PHPSESSID=...`) and also returns `"session_id": "..."` directly inside the top-level JSON response.
- **The client must store and send back session identity** on every subsequent request via one of:
  1. Standard HTTP Cookie header (`Cookie: PHPSESSID=<session_id>`).
  2. JSON POST body parameter (`"session_id": "<session_id>"`).
  3. Query string parameter (`?session_id=<session_id>` or `?PHPSESSID=<session_id>`).
  4. Custom header (`X-Session-ID: <session_id>`).
- *Fallback for constrained systems without session tracking*: If session identity cannot be stored, sending `"username"` and `"password"` with gameplay commands will re-authenticate each turn without wiping the active player from the room.

### Concurrency & World Locking (HTTP 503)
DimensioneY guarantees atomic world transactions using transactional locks (`WorldLock`).
- If another player or event is mutating the database at the exact millisecond of the request, the server returns **HTTP status 503** with `{"status": "lock_busy", "lock_error": true}`.
- **Handling requirement**: The client **must** wait 100–250 ms and retry the exact same request.

### Player Inactivity & Idle Timeout
- The server automatically prunes idle player characters when their elapsed inactivity reaches `playerTimeout` (configured on the server in `config.php`, default: 30–90 seconds).
- When pruned for inactivity, the character is removed from the room and marked disconnected. If the client subsequently issues another action, the server treats it as a reconnecting session and re-spawns the player at the world's entrance (flagging `"is_new_login": true`).
- **Interactive & Terminal Clients**: Clients where human input or terminal display takes time (such as retro computers like the Commodore 64) should ensure the server's `playerTimeout` is set to an appropriate duration (e.g. 90–300 seconds).

---

## 3. Authentication & Account Lifecycle

### Login / Registration Request
The client starts by authenticating. If the account does not exist, the engine **automatically registers** it with the provided password:

```json
{
  "cmd": "login",
  "username": "AgentZero",
  "password": "SecretPassword123"
}
```

### Response Check
- **Success (`200 OK`)**: `"success": true` and `"status": "ok"`. Room snapshot and character details are returned.
- **Auth Failure (`401 Unauthorized`)**: `"success": false` and `"status": "login_required"`. The `"error"` field explains the cause (e.g. wrong password for an existing account).

---

## 4. Action Command Protocol

All subsequent gameplay actions are sent as JSON POST payloads to the same endpoint:

### Generic Request Payload Schema
```json
{
  "cmd": "<command>",
  "param": "<arg0>",
  "target": "<arg1>"
}
```
*(Note: `arg0` is an alias for `param`; `arg1` is an alias for `target`).*

### Command Reference Table

| Category | Command (`cmd`) | Parameter (`param`) | Target (`target`) | Description | Example Payload |
|---|---|---|---|---|---|
| **Look / Refresh** | `look` | `""` or `[id]` | `""` | Inspect current room, vehicle, or a specific object. | `{"cmd": "look"}` |
| **Movement** | `go` | `[direction]` or `[link_id]` | `""` | Move through an exit (`N`,`S`,`E`,`W`,`U`,`D` or exit ID). | `{"cmd": "go", "param": "N"}` |
| **Rotation** | `rotl`, `rotr`, `rev` | `""` | `""` | Rotate 90° left, right, or 180° in panoramic rooms. | `{"cmd": "rotr"}` |
| **Inventory** | `inv` | `""` | `""` | Check items currently carried. | `{"cmd": "inv"}` |
| **Search** | `search` | `""` or `[id]` | `""` | Search the room or a container for hidden items. | `{"cmd": "search"}` |
| **Pick up** | `pick` | `[item_id]` | `""` | Pick up an item from the floor into inventory. | `{"cmd": "pick", "param": "key1"}` |
| **Drop** | `drop` | `[item_id]` | `""` | Drop a carried item onto the room floor. | `{"cmd": "drop", "param": "key1"}` |
| **Open / Close** | `open`, `close` | `[id]` | `""` | Open or close a door, chest, or container. | `{"cmd": "open", "param": "chest"}` |
| **Use Object** | `use` | `[id]` | `""` | Operate/trigger an interactive item or switch. | `{"cmd": "use", "param": "switch1"}` |
| **Use With** | `use2` | `[tool_id]` | `[target_id]` | Combine/use an item on another object (alias: `usewith`). | `{"cmd": "use2", "param": "key1", "target": "door"}` |
| **Give** | `give` | `[item_id]` | `[actor_id]` | Hand an item to an NPC or another player. | `{"cmd": "give", "param": "coin", "target": "guard"}` |
| **Vehicles** | `enter` | `[vehicle_id]` | `""` | Board or enter a vehicle/elevator. | `{"cmd": "enter", "param": "car1"}` |
| **Vehicle Exit** | `exit` | `""` | `""` | Disembark from the current vehicle back to the room. | `{"cmd": "exit"}` |
| **Speech** | `say` | `[message]` | `""` | Say something to everyone present in the room. | `{"cmd": "say", "param": "Hello!"}` |
| **Save State** | `save` | `""` | `""` | Explicitly persist player location and inventory. | `{"cmd": "save"}` |
| **Disconnect** | `logout` | `""` | `""` | Gracefully save and end the session. | `{"cmd": "logout"}` |

> **Dynamic Permission List**: Each server response contains a `"commands"` array listing the exact verbs permitted in the current world state. Clients can query this array to adapt command availability dynamically.

---

## 5. Server Response Payload Specification (JSON Schema)

A standard UIbot response conforms to the following schema:

```jsonc
{
  "success": true,                // boolean: true if command processed successfully
  "status": "ok",                 // string: "ok" | "login_required" | "lock_busy" | "logged_out" | "error"
  "reset": false,                 // boolean: true if the world was reset
  "logged_out": false,            // boolean: true if player has logged out or session ended
  "lock_error": false,            // boolean: true if concurrent lock collision occurred
  "world": {
    "name": "The Beach v.2.30",   // string: Name of the loaded world
    "tick_length": 15             // integer: Background tick interval in seconds
  },
  "commands": [                   // array<string>: Verbs allowed in this world
    "go", "look", "pick", "drop", "open", "close", "search",
    "give", "use", "use2", "say", "inv", "enter", "exit", "logout"
  ],
  "facing": "N",                  // string: Character's current compass facing ("N", "E", "S", "W")
  "stats": {                      // map<string, mixed>: Player stats (capitalized properties)
    "Score": 10,
    "Health": 100
  },
  "room": {                       // object | null: Snapshot of the player's immediate location
    "id": "bungalow",             // string: Unique room or vehicle ID
    "name": "Bungalow Bedroom",   // string: Human-readable location title
    "description": "A sunny room with a wooden door leading outside.", // string: Description text
    "image": "https://.../bg.jpg",// string | null: Room background image URL
    "facing": "N",                // string: Direction of the depicted view
    "has_several_faces": false,   // boolean: true if 360° panoramic rotation is supported
    "items": [                    // array: Visible objects on the floor or in open containers
      {
        "id": "key_brass",        // string: Item ID for use in commands
        "name": "a brass key",    // string: Display name
        "description": "An old key.", // string: Description (if evaluated)
        "open": true,             // boolean (optional): true if container is open
        "container_id": "chest",  // string (optional): present if nested inside another container
        "icon": "key.gif"         // string (optional): icon filename
      }
    ],
    "occupants": [                // array: Other characters and NPCs present
      {
        "id": "surfer",           // string: Character ID
        "name": "the surfer",     // string: Display name
        "icon": "surfer.gif"      // string (optional): icon filename
      }
    ],
    "exits": [                    // array: Traversal links out of this location
      {
        "direction": "N",         // string: Cardinal direction ("N", "S", "E", "W", "U", "D")
        "link_id": "door_outside",// string: Unique exit/door ID
        "name": "outside",        // string: Display name of the exit
        "open": true              // boolean: true if open, false if closed/locked
      }
    ]
  },
  "messages": [                   // array<string>: Feedback produced during this specific turn
    "You picked up the brass key."
  ],
  "chat": [                       // array<object>: Recent chat and dialog history
    {
      "sender": "the surfer",
      "text": "Cowabunga! Ready to catch some waves?"
    }
  ],
  "command": {                    // object: Echo of the command evaluated by the server
    "cmd": "pick",
    "param": "key_brass",
    "target": ""
  }
}
```

---

## 6. Client Implementation Blueprint

Reference implementation based on `tools/InteractiveClient.php`.

### A. Terminal / Input Processing Loop
1. **Tokenization**:
   Split user input string by spaces into a maximum of 3 tokens:
   - Token 0: `cmd`
   - Token 1: `param` (or empty)
   - Token 2: `target` (or empty)
2. **Local Client Commands**:
   - `/help`: Display engine commands and local commands.
   - `/m`: Display accumulated server messages and chat log.
   - `logout` or `exit`: Initiate clean disconnect.

### B. Response Display Order
When rendering each turn to the player or console:
1. **Turn Feedback**: Print all strings from `messages[]` (e.g., action results, script prints).
2. **Incoming Dialogue**: Print new items from `chat[]`. (Maintain a hash table or set of seen messages to avoid printing backlog duplicates).
3. **Location Banner**: `=== <room.name> [<room.id>] ===`
4. **Description**: Display `room.description`.
5. **Items List**: List `room.items[]` with ID and open/closed state.
6. **Characters List**: List `room.occupants[]` with ID and display name.
7. **Exits List**: List `room.exits[]` with direction, link ID, name, and open/closed status.

### C. Vehicle Handling
- When inside a vehicle (after `enter <vehicle_id>`), the `room` object represents the **vehicle interior**:
  - `room.id` is the vehicle ID.
  - `room.items` lists items inside the vehicle.
  - `room.exits` is empty.
- To leave the vehicle, send `{"cmd": "exit"}`.

### D. Chat & Message Deduplication
The server `chat` array returns the recent chat buffer. A client should track message hashes or IDs to only display new chat lines on subsequent turns.

---

## 7. Minimal Implementation Checklist for Any Platform

An AI agent implementing a client on a target architecture needs to fulfill:

1. [ ] **HTTP POST capability**: Send JSON strings over HTTP/HTTPS.
2. [ ] **Cookie persistence**: Store `PHPSESSID` from HTTP response headers and include it in request headers.
3. [ ] **JSON Serialization / Deserialization**:
   - Send: `{"cmd": "...", "param": "...", "target": "..."}`
   - Receive: Parse `success`, `status`, `messages`, `chat`, and `room` (`id`, `name`, `items`, `occupants`, `exits`).
4. [ ] **Retry on 503**: If status is 503 or `status === "lock_busy"`, pause 150ms and resend once.
5. [ ] **Graceful exit**: Terminate loop when `logged_out === true` or on user logout command.
