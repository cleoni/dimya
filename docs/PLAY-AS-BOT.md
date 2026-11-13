# PLAY-AS-BOT.md: Guide to Exploring world3net via UIbot

This guide provides instructions for connecting an external script, automated agent, or AI bot to the **DimensioneY** multiplayer engine using the **UIbot** REST/JSON interface.

The UIbot interface exposes a fair, structured JSON perspective of the world designed specifically for bots:
- **Fair play**: Exits do not reveal the unseen destination room beforehand; other players and NPCs are uniform (`is_player` omitted); and internal object attributes (like volume or pickability) must be discovered through interaction.
- **Stateful HTTP**: Communicates over HTTPS using standard JSON payloads and session cookies (`PHPSESSID`).

---

## 1. Accessing the Bot Interface (HTTPS Requests)

### Endpoint URL
All requests are sent to the multiplayer entrypoint:
```
POST https://<host>/dimya/php/multiplayer.php
```
*(Replace `<host>` and path with your server's actual host and deployment path, e.g., `https://example.com/multiplayer.php`).*

### Request Headers
To instruct the server to use the **UIbot** JSON API rather than the human HTML interface, include the following headers on all requests:
```http
Content-Type: application/json
Accept: application/json
```
Alternatively, you may append `?bot=1` or `?format=json` as a URL query parameter.

### Session Management & Cookies
DimensioneY uses standard PHP sessions (`PHPSESSID`).
- **Always store and re-send cookies** across subsequent requests.
- On your first login request, the server sets a session cookie (e.g. `Set-Cookie: PHPSESSID=...; Path=/; HttpOnly`).
- Supplying this cookie with later requests maintains your authenticated session without needing to re-send the password on every move.

---

## 2. Login Procedure & Verification

### Step 1: Send the Login Request
Send a `POST` request with a JSON body containing your bot's `username` and `password`. If `cmd` is omitted, it defaults to `"login"`.

```json
{
  "username": "BotExplorer",
  "password": "MyBotPassword123"
}
```

> **Account Auto-Creation**:
> If the `username` does not yet exist, DimensioneY automatically creates a new bot account with the provided password and logs in immediately. If it already exists, the password must match the existing account.

### Step 2: Verify the Login Response
A successful login returns HTTP status `200` with `"success": true` and `"status": "ok"`:

```json
{
  "success": true,
  "status": "ok",
  "logged_out": false,
  "lock_error": false,
  "world": {
    "name": "The Beach v.2.30",
    "tick_length": 15
  },
  "commands": [
    "go", "look", "pick", "drop", "open", "close", "search",
    "give", "use", "use2", "say", "inv", "help", "save",
    "enter", "exit", "logout", "rotl", "rotr", "rev"
  ],
  "facing": "N",
  "stats": {},
  "room": {
    "id": "bungalowStanza",
    "name": "the bungalow's bedroom",
    "description": "A nice room. A door leads outside, while another door leads to the bathroom.",
    "image": "https://www.dimensionex.net/pics/bungalow.jpg",
    "facing": "N",
    "has_several_faces": false,
    "items": [
      {
        "id": "tele1",
        "name": "the teletransporter",
        "description": "It looks like one of those things in Star Trek."
      }
    ],
    "occupants": [],
    "exits": [
      {
        "direction": "N",
        "link_id": "w3",
        "name": "outside",
        "open": true
      },
      {
        "direction": "W",
        "link_id": "w4",
        "name": "bathroom",
        "open": true
      }
    ]
  },
  "console": [
    "Welcome to The Beach v.2.30, BotExplorer!"
  ],
  "msgs": [],
  "command": {
    "cmd": "login",
    "param": "",
    "target": ""
  }
}
```

### Handling Login Failures
- **Wrong Password or Missing Password**:
  Response returns `"success": false`, `"status": "login_required"`, and an `"error"` message.
- **Server Concurrency Lock Busy**:
  Response returns HTTP `503` with `"status": "lock_busy"` and `"lock_error": true`. If this occurs, retry after a short delay (e.g. 50-100 ms).

---

## 3. Executing Game Steps

Every action within the world is sent as a `POST` request with a JSON payload containing:
- `"cmd"`: The command verb (string, case-insensitive).
- `"param"`: Primary parameter (direction, item ID, or first object).
- `"target"`: Secondary parameter (target object, recipient, or second object).

### Request Structure
```json
{
  "cmd": "<command>",
  "param": "<first_parameter>",
  "target": "<second_parameter>"
}
```

---

### Command Reference

The available commands for your character at any moment are provided in the `"commands"` array of each response. Some commands listed in sections 1,2,3 below may not be available at a given game step, or other commands may be available. Take the three sections below like an example of most frequent situation.

#### 1. Zero-Parameter Commands
Commands that take no arguments (`"param"` and `"target"` can be omitted or empty string `""`):

| Command | Purpose | Example Payload |
| :--- | :--- | :--- |
| `look` | Describe the current room or current vehicle interior. | `{"cmd": "look"}` |
| `inv` | Check carried inventory items. | `{"cmd": "inv"}` |
| `rotl` | Rotate camera 90° left (in 360° multi-face rooms). | `{"cmd": "rotl"}` |
| `rotr` | Rotate camera 90° right (in 360° multi-face rooms). | `{"cmd": "rotr"}` |
| `rev` | Turn camera 180° / reverse view (in multi-face rooms). | `{"cmd": "rev"}` |
| `exit` | Disembark / exit the vehicle you are currently inside. | `{"cmd": "exit"}` |
| `save` | Save game progress without disconnecting. | `{"cmd": "save"}` |
| `logout` | Disconnect and safely log out of the world. | `{"cmd": "logout"}` |

#### 2. One-Parameter Commands
Commands that target a direction, item, vehicle, or actor. Use `"param"` (or `"target"`):

| Command | Parameter (`"param"`) | Description | Example Payload |
| :--- | :--- | :--- | :--- |
| `go` | Direction (`N`, `S`, `E`, `W`, `U`, `D`) or `link_id` | Move in that direction or through an exit link. | `{"cmd": "go", "param": "N"}` |
| `look` | Object ID (`id` of item, actor, or vehicle) | Inspect an object in detail. | `{"cmd": "look", "param": "tele1"}` |
| `click` | Object ID | Inspect/click an on-screen object. | `{"cmd": "click", "param": "switch2"}` |
| `pick` | Item ID | Pick up an item from the floor/vehicle into inventory. | `{"cmd": "pick", "param": "screwd"}` |
| `drop` | Item ID | Drop a carried inventory item into the room/vehicle. | `{"cmd": "drop", "param": "apple"}` |
| `use` | Item ID or interactable object ID | Operate/activate an object. | `{"cmd": "use", "param": "switch2"}` |
| `open` | Container ID or door/link ID | Open a container or door. | `{"cmd": "open", "param": "box"}` |
| `close` | Container ID or door/link ID | Close a container or door. | `{"cmd": "close", "param": "box"}` |
| `enter` | Vehicle ID | Board/enter a vehicle (e.g. `tele1`). | `{"cmd": "enter", "param": "tele1"}` |
| `search` | Object ID or empty for room | Search an object or room for hidden items. | `{"cmd": "search", "param": "sand"}` |
| `say` | Message string | Speak aloud to all occupants in the current room. | `{"cmd": "say", "param": "Hello everyone!"}` |

#### 3. Two-Parameter Commands
Commands that combine two objects or an item and a recipient. Use `"param"` for the primary item and `"target"` for the secondary item or recipient:

| Command | `"param"` | `"target"` | Description | Example Payload |
| :--- | :--- | :--- | :--- | :--- |
| `use2` | Tool / item to use | Object being operated on | Use two items together (e.g. screwdriver on switch). | `{"cmd": "use2", "param": "screwd", "target": "switch2"}` |
| `give` | Item ID | Character ID | Give a carried item to an NPC or another player. | `{"cmd": "give", "param": "apple", "target": "surfer"}` |

*(Note: `usewith` is supported as an alias for `use2`).*

---

### Output Interpretation (The JSON Response Schema)

Every step response contains the updated state of the world from your bot's perspective:

```jsonc
{
  "success": true,              // True if the command succeeded
  "status": "ok",               // "ok", "logged_out", or "lock_busy"
  "logged_out": false,          // True if session ended
  "lock_error": false,          // True if concurrency lock encountered
  "world": {
    "name": "The Beach v.2.30", // Name of the world
    "tick_length": 15           // World event tick length in seconds
  },
  "commands": [ ... ],          // Array of currently permitted command strings
  "facing": "N",                // Player's camera compass facing ('N', 'E', 'S', 'W')
  "stats": { ... },             // Capitalized player attributes (e.g. "Health", "Score")
  "room": {                     // Current environment (room or vehicle interior)
    "id": "bungalowStanza",     // Room / vehicle ID
    "name": "the bedroom",      // Room / vehicle display name
    "description": "...",       // Textual room description
    "image": "...",             // Background scene image URL
    "facing": "N",              // Facing view of this scene
    "has_several_faces": false, // True if panoramic room (requires rotl/rotr/rev)
    "items": [                  // Visible items on floor or vehicle interior
      {
        "id": "switch2",        // ID to use in pick, look, use, etc.
        "name": "a switch",     // Display name
        "description": "...",   // Description if already looked at
        "open": true,           // (Optional) Present if container is openable
        "container_id": "box"   // (Optional) Present if inside a container
      }
    ],
    "occupants": [              // Other actors in the same room/vehicle
      {
        "id": "surfer",         // Character ID for interaction (give, look)
        "name": "the surfer"    // Display name
      }
    ],
    "exits": [                  // Available exits
      {
        "direction": "N",       // Compass direction ('N', 'S', 'E', 'W', 'U', 'D')
        "link_id": "w3",        // Unique link ID
        "name": "outside",      // Exit name
        "open": true            // True if open, false if closed door
      }
    ]
  },
  "console": [                  // Game engine console logs and action results (#console)
    "You enter the teletransporter."
  ],
  "msgs": [],                   // Recent incoming chat/dialogue messages (#msgs)
  "command": {                  // Echo of the executed command
    "cmd": "enter",
    "param": "tele1",
    "target": ""
  }
}
```

#### Key Rules When Interpreting Output:
1. **Always read `console` and often read `msgs`**: The `console` array contains the direct consequence of your action (e.g. *"You picked up the screwdriver."*, *"The main control seems to be broken."*), while `msgs` contains incoming speech and dialogue.
2. **Vehicle Interiors**: When you enter a vehicle (e.g. `enter tele1`), `room.id` becomes the vehicle ID (`tele1`), `room.items` lists items inside the vehicle (e.g. `switch2`), and `room.exits` is empty. To exit back to the room, issue `{"cmd": "exit"}`.
3. **Multi-Face Orientation**: If `room.has_several_faces` is `true`, forward movement via `go` follows the current `facing`. Use `rotl`, `rotr`, or `rev` to turn.

---

## 4. Logging Out

When your bot finishes its exploration or session, send a logout command:

```json
{
  "cmd": "logout"
}
```

### Verifying Logout
A successful logout response returns:
- `"success": true`
- `"status": "logged_out"`
- `"logged_out": true`
- `"room": null`
- `"console": ["Your game was saved and you have safely left The Beach v.2.30."]`

The session is terminated and your character's position and inventory are persisted on the server.

---

## 5. End-to-End Automation Examples

### Example A: cURL Script

Save the following shell script to test the full lifecycle using `curl` with a persistent cookie jar:

```bash
#!/bin/bash
HOST="https://example.com/dimya/php/multiplayer.php"
COOKIE_JAR="bot_session.txt"

# 1. Login
curl -s -c $COOKIE_JAR -b $COOKIE_JAR \
     -H "Content-Type: application/json" -H "Accept: application/json" \
     -d '{"username":"Agent007","password":"TopSecretPassword"}' \
     "$HOST" | jq .

# 2. Look around
curl -s -c $COOKIE_JAR -b $COOKIE_JAR \
     -H "Content-Type: application/json" -H "Accept: application/json" \
     -d '{"cmd":"look"}' \
     "$HOST" | jq .

# 3. Enter vehicle 'tele1'
curl -s -c $COOKIE_JAR -b $COOKIE_JAR \
     -H "Content-Type: application/json" -H "Accept: application/json" \
     -d '{"cmd":"enter","param":"tele1"}' \
     "$HOST" | jq .

# 4. Use two items together (repair switch2 with screwd)
curl -s -c $COOKIE_JAR -b $COOKIE_JAR \
     -H "Content-Type: application/json" -H "Accept: application/json" \
     -d '{"cmd":"use2","param":"screwd","target":"switch2"}' \
     "$HOST" | jq .

# 5. Activate the fixed switch
curl -s -c $COOKIE_JAR -b $COOKIE_JAR \
     -H "Content-Type: application/json" -H "Accept: application/json" \
     -d '{"cmd":"use","param":"switch2"}' \
     "$HOST" | jq .

# 6. Logout
curl -s -c $COOKIE_JAR -b $COOKIE_JAR \
     -H "Content-Type: application/json" -H "Accept: application/json" \
     -d '{"cmd":"logout"}' \
     "$HOST" | jq .

rm -f $COOKIE_JAR
```

---

### Example B: Python Script (`requests`)

A complete automated Python runner using `requests.Session`:

```python
import requests
import json
import time

BASE_URL = "https://example.com/dimya/php/multiplayer.php"

class DimxBot:
    def __init__(self, username: str, password: str, url: str = BASE_URL):
        self.url = url
        self.username = username
        self.password = password
        self.session = requests.Session()
        self.session.headers.update({
            "Content-Type": "application/json",
            "Accept": "application/json"
        })

    def send_cmd(self, cmd: str, param: str = "", target: str = "") -> dict:
        payload = {"cmd": cmd, "param": param, "target": target}
        resp = self.session.post(self.url, json=payload, timeout=10)
        
        # Handle lock concurrency with retry
        if resp.status_code == 503 or resp.json().get("status") == "lock_busy":
            time.sleep(0.1)
            resp = self.session.post(self.url, json=payload, timeout=10)

        data = resp.json()
        print(f"\n>> [{cmd.upper()} {param} {target}]")
        for msg in data.get("console", []):
            print(f"  [Console] {msg}")
        return data

    def login(self) -> dict:
        payload = {
            "username": self.username,
            "password": self.password,
            "cmd": "login"
        }
        resp = self.session.post(self.url, json=payload, timeout=10)
        data = resp.json()
        if not data.get("success"):
            raise RuntimeError(f"Login failed: {data.get('error')}")
        print(f"Logged in as {self.username}! In room: {data['room']['name']}")
        return data

    def logout(self) -> dict:
        return self.send_cmd("logout")


if __name__ == "__main__":
    bot = DimxBot(username="NavigatorBot", password="secret_bot_pass")
    
    # 1. Login
    state = bot.login()
    
    # 2. Check current room items and exits
    print("Items here:", [i["id"] for i in state["room"]["items"]])
    print("Exits:", [e["direction"] for e in state["room"]["exits"]])
    
    # 3. Enter vehicle tele1 if present
    if any(i["id"] == "tele1" for i in state["room"]["items"]):
        state = bot.send_cmd("enter", "tele1")
        # In vehicle: inspect inner switch2
        state = bot.send_cmd("look", "switch2")
        # Exit back to room
        state = bot.send_cmd("exit")
        
    # 4. Save and disconnect
    bot.send_cmd("save")
    bot.logout()
```
