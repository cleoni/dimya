# DimensioneX World Definition (.DXW) Language Specification

> **Reference Source**: DimensioneX Developer's Reference v7.1.7 (Chapter 2: *DimensioneX DXW Reference*, pp. 37–70) and official reference implementations (`world1.dxw`, `world2.dxw`, `world3net.dxw`, `fourinline.dxw`).

---

## Table of Contents

1. [Overview & General Syntax Rules](#1-overview--general-syntax-rules)
2. [Document Structure](#2-document-structure)
3. [The WORLD Section](#3-the-world-section)
4. [The GUI Section](#4-the-gui-section)
   - [Scene & Display Settings](#41-scene--display-settings)
   - [External Hooks (HOOKS)](#42-external-hooks-hooks)
   - [Panel Definitions (PANEL)](#43-panel-definitions-panel)
   - [Controls (BUTTON, CR, MAP, TEXTBOX, LABEL, DROPDOWN, DELETE, CMD)](#44-controls)
   - [Page Templates (PAGE)](#45-page-templates-page)
5. [The ROOMS Section](#5-the-rooms-section)
   - [Room Definition & Attributes](#51-room-definition--attributes)
   - [Multi-Directional Imagery & Sky Color](#52-multi-directional-imagery--sky-color)
6. [Show Modes & Image Syntax](#6-show-modes--image-syntax)
   - [Show Modes (SHOW)](#61-show-modes-show)
   - [Image Descriptors (IMAGE) & Show Areas](#62-image-descriptors-image--show-areas)
7. [Attributes & Property Semantics (ATTRLIST)](#7-attributes--property-semantics-attrlist)
   - [Syntax & Loose Scalar Typing](#71-syntax--loose-scalar-typing)
   - [Standard Engine Attributes](#72-standard-engine-attributes)
   - [Display Rules (Capital Letter Rule)](#73-display-rules-capital-letter-rule)
   - [Capacity & Volume Physical Simulation](#74-capacity--volume-physical-simulation)
8. [The LINKS Section (LINK & MLINK)](#8-the-links-section-link--mlink)
9. [The CHARACTERS Section](#9-the-characters-section)
   - [Global Character Attributes](#91-global-character-attributes)
   - [Character Definition (NPCs & Agents)](#92-character-definition-npcs--agents)
10. [The ITEMS Section (ITEM & VEHICLE)](#10-the-items-section-item--vehicle)
    - [Interactive Items](#101-interactive-items)
    - [Vehicles & Passenger Transport](#102-vehicles--passenger-transport)
11. [The SETS Section (ARRAY & SET)](#11-the-sets-section-array--set)
12. [The SCRIPTS Section](#12-the-scripts-section)
13. [File Inclusion Directive (Include)](#13-file-inclusion-directive-include)
14. [Reference Parsing Grammar Summary](#14-reference-parsing-grammar-summary)

---

## 1. Overview & General Syntax Rules

A DimensioneX world is defined in a plain-text source file bearing the `.dxw` extension. A single file can describe a full interactive multiplayer world or load modular definitions using `Include` statements.

### Key Syntax Rules:
* **Encoding**: Text files are expected to be encoded in **UTF-8** by default. ANSI ASCII files are supported when `ENCODING ANSI` is specified in the `WORLD` block.
* **Line-Oriented**: Each directive or definition is written on its own line.
* **Case-Insensitive Directives**: Section tags (`WORLD`, `GUI`, `ROOMS`, `LINKS`, `CHARACTERS`, `ITEMS`, `SETS`, `SCRIPTS`), keywords, and property names are case-insensitive in parser interpretation.
* **Whitespace & Indentation**: Leading and trailing tabs or spaces are ignored. Indentation is purely cosmetic.
* **Comments**: Any text following a single quote (`'`) up to the end of the line is treated as a comment and ignored by the parser:
  ```text
  ' This is a comment
  ROOM beach ' Comment after directive
  ```
* **Identifiers**: IDs for rooms, items, characters, links, and panels must consist of alphanumeric characters (`[a-zA-Z0-9_]`) without spaces or special punctuation.
* **Loose Scalar Typing**: In accordance with the DimensioneX runtime architecture, scalar values (integers, booleans, floating-point numbers, and strings) are **not strictly typed**. They behave dynamically according to context:
  * Booleans accept `1`, `0`, `true`, `false`, `yes`, `no`, `on`, `off`.
  * Numeric attributes can be read as strings or integers.
  * Empty attribute flags (e.g. `pickable` without `=`) default to `true` / `1`.

---

## 2. Document Structure

A complete `.dxw` file consists of seven optional inner sections enclosed within a root `WORLD ... END_WORLD` block:

```text
WORLD
    NAME            World Title
    IMAGESFOLDER    http://www.domain.com/pics/
    ...

    GUI
        ...
    END_GUI

    ROOMS
        ...
    END_ROOMS

    LINKS
        ...
    END_LINKS

    CHARACTERS
        ...
    END_CHARACTERS

    ITEMS
        ...
    END_ITEMS

    SETS
        ...
    END_SETS

    SCRIPTS
        ...
    END_SCRIPTS

END_WORLD
```

All inner sections are optional. Each inner section has a matching closing tag (`END_GUI`, `END_ROOMS`, `END_LINKS`, `END_CHARACTERS`, `END_ITEMS`, `END_SETS`, `END_SCRIPTS`).

---

## 3. The WORLD Section

The `WORLD` section defines global metadata, network configuration, and multimedia base paths.

### Syntax:
```text
WORLD
    NAME                  <title>
    IMAGESFOLDER          <url>
    [ IMAGESFOLDER_LOCAL  <local_url> ]
    [ IMAGESFOLDER_PUBLIC <public_url> ]
    [ ENCODING            UTF-8 | ANSI ]
    [ VERSION             <version_string> ]
    [ AUTHOR              <author_name> ]
    [ AUTHOREMAIL         <email> ]
    [ SITE                <website_url> ]
    [ HELP                <help_url> ]
    [ COUNTERHTML         <html_string> ]
    [ INTERPHONE          0 | 1 | 2 ]
    [ SAVEGAME_PERSISTENCE 0 | 1 | 2 ]
    [ MUTING              0 | 1 | 2 | 3 ]
    [ CLUSTER             <cluster_id> ]
    [ Include             "library.dxl" ]
    ...
END_WORLD
```

### Directive Reference:

| Directive | Type | Default | Description |
|---|---|---|---|
| `NAME` | String | *Required* | Title of the world and default identifier. |
| `IMAGESFOLDER` | URL | (none) | Base URL for images and sounds. Must end with a trailing forward slash `/`. Never use Windows backslashes `\`. |
| `IMAGESFOLDER_LOCAL` | URL | (none) | Asset path prioritized when running in local development mode (`serverType=local`). |
| `IMAGESFOLDER_PUBLIC` | URL | (none) | Asset path prioritized when deployed to production (`serverType=public`). |
| `ENCODING` | Keyword | `UTF-8` | Source file character encoding (`UTF-8` or `ANSI`). |
| `VERSION` | String | `1.0` | World script version (independent of engine version). |
| `AUTHOR` | String | `""` | Creator name or organization. |
| `AUTHOREMAIL` | String | `""` | Contact email address. |
| `SITE` | URL | `""` | Official website URL for the game world. |
| `HELP` | URL | `""` | Link opened when a player clicks the "Help" command button. |
| `COUNTERHTML` | HTML | `""` | Optional HTML snippet for visitor analytics or counter scripts. |
| `INTERPHONE` | Integer | `1` | Chat broadcast range: <br>`0` = Same room only.<br>`1` = World-wide, but enclosed items/vehicles provide privacy.<br>`2` = Global world-wide broadcast. |
| `SAVEGAME_PERSISTENCE` | Integer | `2` | Profile validity across restarts: <br>`0` = Reset on world restart.<br>`1` = Reset if `VERSION` changes.<br>`2` = Permanently preserved. |
| `MUTING` | Integer | `2` | Chat moderation filter: <br>`0` = Off.<br>`1` = Censor stopwords in-place.<br>`2` = 1-minute mute on first infraction.<br>`3` = Cumulative penalty (+1 min per infraction). |
| `CLUSTER` | ID | (none) | Multi-area cluster identifier for clustered worlds linked via `MoveOutside`. |

---

## 4. The GUI Section

The `GUI ... END_GUI` block configures the graphical client appearance, screen resolutions, custom control panels, external HTTP hooks, and HTML page templates.

### 4.1 Scene & Display Settings

```text
GUI
    [ SCENE SIZE       350x235 ]
    [ SCREEN SIZE      640x480 ]
    [ SCENE LOOK       1STPERSON | 3RDPERSON ]
    [ LOGOSRC          gamelogo.jpg ]
    [ MAP              gamemap.jpg ]
    [ COMPASS          true | false ]
    [ MSGLISTSIZE      3 ]
    [ SKINS            skin1.dxs,skin2.dxs,default ]
    [ SHOW PROPERTIES  Health,Strength,Cash ]
    [ HOOKS            cmd1=event1,cmd2=event2 ]
    ...
END_GUI
```

* **`SCENE SIZE <W>x<H>`**: Default dimensions in pixels for room scenes (default: `350x235`).
* **`SCREEN SIZE <W>x<H>`**: Target design resolution (e.g. `640x480`, `800x600`, `1024x768`). The engine auto-scales viewport elements proportionally for larger monitors.
* **`SCENE LOOK <mode>`**: 
  * `1STPERSON` (default): Standard view; avatar is not drawn inside the room scene.
  * `3RDPERSON`: Avatar is rendered inside the room scene.
* **`LOGOSRC <filename | URL>`**: Title logo graphic displayed in the header frame.
* **`MAP <filename | URL>`**: Default mini-map image (recommended size: `200x200`).
* **`COMPASS <true | false>`**: If `true`, the navigation compass is permanently shown in the top bar; if `false`, only shown in rooms with multiple view angles.
* **`MSGLISTSIZE <int>`**: Maximum number of speech/event messages retained in the active console box (default: `3`).
* **`SKINS <list>`**: Comma-separated list of available skin definition files (`.dxs`). The first is the default. Built-in skin is referenced as `default`.
* **`SHOW PROPERTIES <list>`**: Explicit list of object properties exposed in the player UI stats panel. (See [Display Rules](#73-display-rules-capital-letter-rule)).

### 4.2 External Hooks (HOOKS)

Enables external web applications or services to interact with the game server via HTTP GET/POST:

```text
GUI
    HOOKS  findobj=doFindObj, score=reportScore
END_GUI
```

* **Invocation URL**: `http://server/dimx/servlet/multiplayer?game=1&cmd=findobj&obj=sword`
* Triggers the corresponding SmallBasic `Function doFindObj(obj)` or `EVENT` and returns outputs produced by `Print`.

---

### 4.3 Panel Definitions (PANEL)

Panels define the command buttons, text boxes, and dropdown menus available to players:

```text
PANEL <panelId> [VERSION OF <parentPanelId>]
    [control definitions ...]
```

#### Pre-Defined Panel IDs:
* **`default`**: Main gameplay panel loaded on world start.
* **`chat`**: Simplified communication panel (speech box + exits + logout).
* **`connect`**: Initial connection / character selection screen.
* **`connecting`**: Password login screen for existing profiles.
* **`exiting`**: Confirmation screen when issuing `exit` (`logout`).

#### Panel Inheritance (`VERSION OF`):
Creates a modified version of an existing panel, inheriting its controls while allowing addition or removal:
```text
PANEL custom_beach VERSION OF default
    DELETE search
    BUTTON dig, "Dig", "Dig into the sand!", onDig
END_GUI
```

---

### 4.4 Controls

Controls are declared inside `PANEL` blocks:

#### 1. BUTTON
```text
' Syntax #1: Standard text button
BUTTON id, "Label", "ToolTipText", Event [, EventModel]

' Syntax #2: Icon-enhanced button
BUTTON id, "BalloonText", "ToolTipText", Event [, EventModel]
    ICON src

' Syntax #3: Image button
BUTTON id, "BalloonText", "ToolTipText", Event [, EventModel]
    IMAGE [widthxheight] src

' Syntax #4: Quick reference to standard default command
BUTTON id
    [ICON src]
```

* **Reserved Built-in Button IDs**: `click`, `go`, `rotate`, `look`, `use`, `use2`, `open`, `close`, `pick`, `drop`, `search`, `hide`, `put`, `give`, `enter`, `exit`, `say`, `save`, `login`, `logout`, `rotr`, `rotl`. See [**COMMANDS.md**](COMMANDS.md) for the full engine command reference and execution semantics.
* **Event Models**:
  * `(none)`: No parameters passed.
  * `O`: 1 object parameter (`$OWNER`).
  * `OO`: 2 object parameters (`$OWNER`, `$TARGET`).
  * `T`: 1 text parameter (`$TARGET`).
  * `TO`: 1 text parameter, 1 object parameter.

#### 2. CR
Carriage return / line break in the control layout.
```text
CR
```

#### 3. MAP
Renders the navigation mini-map in the panel.
```text
MAP
```

#### 4. TEXTBOX
Text entry input field (accessible in scripts via `input("txtBox")` or custom ID):
```text
TEXTBOX [id]
```

#### 5. LABEL
Displays text or custom inline HTML in the control panel:
```text
LABEL "<b>Choose your action:</b>"
```

#### 6. DROPDOWN
Dynamic dropdown select box:
```text
DROPDOWN id, SetExpression, DefaultValue [, WorkingModel]
```
* Example: `DROPDOWN choices, "$OWNER.setChoices", 0001`

#### 7. DELETE
Removes an inherited control from the parent panel:
```text
DELETE _sep3
DELETE search
```

#### 8. CMD
Invisible command handler mapped to client interactions without rendering a UI button (e.g. `CMD click`).
```text
CMD id
```

---

### 4.5 Page Templates (PAGE)

Defines custom full-screen HTML layouts using template placeholders:
```text
PAGE <viewId>, <template_path_or_url>
```

#### Supported Placeholders:
| Placeholder | Replaced With |
|---|---|
| `$scene` | Scene graphic with focus items overlay. |
| `$icons` | Icons list of objects/characters present in the current room. |
| `$navpad` | Directional navigation pad (compass buttons). |
| `$inventory` | Player's inventory list. |
| `$banner` | Title banner of the focused entity. |
| `$properties` | List of player's displayed attributes. |
| `$display` | Player's personal message / narration console. |

---

## 5. The ROOMS Section

The `ROOMS ... END_ROOMS` block defines the geographical spaces in the game world.

### 5.1 Room Definition & Attributes

```text
ROOM <id> [DEFAULT]
    NAME         <Display Name>
    [DESCRIPTION <text | @function($AGENT)>]
    [ATTRLIST    <key=value,...>]
    [PANEL       <panelId>]
    [IMAGE       [orientation] [WxH] [SHOWAREA x1,x2,y] <url>]
    ...
```

* **`id`**: Unique alphanumeric room identifier.
* **`DEFAULT`**: Keyword designating the entry / spawn room for newly connecting players.
* **`NAME`**: User-facing room title.
* **`DESCRIPTION`**: Text displayed on entry or `look`.
  * **Dynamic Descriptions**: Prefixing with `@` executes a function at runtime:
    ```text
    DESCRIPTION @getRoomDesc($AGENT)
    ```
* **`PANEL`**: Assigns a room-specific control panel (overrides the world default).
* **`ATTRLIST`**: Room properties (e.g. `mapx=100,mapy=200` for map position tracking).

### 5.2 Multi-Directional Imagery & Sky Color

Rooms can define multiple scene images mapped to cardinal viewpoints (`N`, `S`, `E`, `W`, `U`, `D`), providing 360-degree rotation:

```text
ROOM pathgate
    NAME        The gate
    DESCRIPTION The path connects a hill with a cliff.
    IMAGE   N   pathgaten.jpg
    IMAGE   S   SHOWAREA 100,-1,-1  pathgates.jpg
    IMAGE   W   pathgatew.jpg
    IMAGE   E   SHOWAREA -1,-1,0    pathgatee.jpg
```

* **Sky Color**: For transparent scene images (GIF/PNG), the background sky color can be configured via `WORLD.bgcolor` (e.g. `bgcolor="#003355"`).

---

## 6. Show Modes & Image Syntax

### 6.1 Show Modes (SHOW)

Controls how items and characters are visualised within their container or room:

```text
SHOW <showMode> [showX,showY [FOR showForUrl]]
```

| Mode | Numeric Code | Typical For | Description |
|---|---|---|---|
| `OFFSCREEN` | `0` | `ITEM` (default) | Represented as an icon in the sidebar / items list. |
| `ONSCREEN` | `1` | `CHARACTER` (default) | Rendered as a full sprite directly over the room scene image. |
| `ICON` | `2` | Interactive props | Rendered as a clickable icon placed directly on the scene image. |

* **Coordinates (`showX, showY`)**: Pixel offset `(X, Y)` where `(0, 0)` is the **bottom-left** corner of the scene picture.
* **`FOR <showForUrl>`**: Restricts sprite placement to a specific scene image file.

---

### 6.2 Image Descriptors (IMAGE) & Show Areas

```text
IMAGE [orientation] [dimensions] [SHOWAREA [x1],[x2],y] <url>
```

* **Orientation**: `N`, `S`, `E`, `W`, `U`, `D`.
* **Dimensions**: Formatted as `WIDTHxHEIGHT` in pixels (e.g. `120x130`, `400x300`).
* **`SHOWAREA x1,x2,y`**: Defines the ground baseline zone for randomly positioned entities:
  * `x1`: Left horizontal boundary (default: `0`).
  * `x2`: Right horizontal boundary (default: picture width).
  * `y`: Vertical baseline height from bottom (default: 10% of image height).
  * Pass `-1` to accept the default value (e.g. `SHOWAREA -1,-1,20`).
* **URL Resolution**:
  * Starts with `http://` or `https://` $\rightarrow$ Absolute remote URL.
  * Starts with `/` $\rightarrow$ Relative to web server root.
  * Relative filename $\rightarrow$ Resolved against `IMAGESFOLDER`.

---

## 7. Attributes & Property Semantics (ATTRLIST)

### 7.1 Syntax & Loose Scalar Typing

`ATTRLIST` specifies a comma-separated list of attributes:

```text
ATTRLIST  pickable, open=0, locked=1, Health=100, Price=15.50
```

* **Flag Syntax**: A key without `=value` defaults to `true` (or `1`).
* **Engine Scalar Typing Rule**: **No scalar values in the game are strictly typed.** All scalar values must be coerced dynamically in PHP context:
  * `open=0` $\rightarrow$ Boolean `false` on boolean properties.
  * `pickable=1` $\rightarrow$ Boolean `true`.
  * `Health=10` $\rightarrow$ Integer `10`.
  * Contextual conversions between `int`, `bool`, and `string` must not trigger PHP `TypeError` exceptions.

---

### 7.2 Standard Engine Attributes

| Attribute | Applies To | Default | Meaning |
|---|---|---|---|
| `open` | Link, Item | `1` (Item `0`) | Whether passage or container is currently open. |
| `openable` | Link, Item | `1` | Whether player can open it (`openable=0` for sealed chests). |
| `locked` | Link, Item | `0` | Whether opening requires unlocking with a key. |
| `pickable` | Item | `0` | If `1` or present, player can take the item into inventory. |
| `hidden` | Item, Character | `0` | If `1`, hidden from normal view until discovered by `search`. |
| `hideable` | Item | `1` | If `0`, cannot be hidden by players (Vehicles default to `0`). |
| `type` | Item, Character | `"generic"` | Type taxonomy in `type.subtype` format (e.g. `weapon.sword`). |
| `mapx`, `mapy` | Room | `0, 0` | Coordinates on the mini-map graphic for player marker tracking. |

---

### 7.3 Display Rules (Capital Letter Rule)

* **Automatic Stats Display**: Any property whose name begins with an **uppercase letter** (e.g. `Health`, `Strength`, `Cash`, `Score`) is automatically rendered in the player's stats sidebar.
* Properties starting with lowercase letters are treated as private/internal variables unless explicitly exposed via `SHOW PROPERTIES`.

---

### 7.4 Capacity & Volume Physical Simulation

Every entity participates in a physical volume/capacity model:

| Object Type | Default CAPACITY | Default VOLUME |
|---|---|---|
| `CHARACTER` / `Player` | `5` units | `10` units |
| `ITEM` | `5` units | `1` unit |
| `VEHICLE` | `10` units | `10` units |

* A player with capacity 5 can carry five 1-unit items, or one 3-unit item and one 2-unit item.
* Setting `volume=0` makes an item weightless/volumeless (ideal for gold coins, keys, spells).
* Containers (chests, closets, vehicles) can define large capacities (e.g. `capacity=1000`).

---

## 8. The LINKS Section (LINK & MLINK)

The `LINKS ... END_LINKS` block defines passages between rooms:

* **`LINK`**: Bidirectional connection (creates passage in both directions).
* **`MLINK`**: Monodirectional / one-way connection.

### Syntax:
```text
LINK  <id> <from_room>-<to_room> [orientation]
    NAME        <name | *>
    [DESCRIPTION <text>]
    [ATTRLIST   <attributes>]
    [ICON       <icon_url>]
    [SHOW       <show_type>]
    [IMAGE      <image_definition>]
```

* **Orientation**: `N`, `S`, `E`, `W`, `U` (Up), `D` (Down).
* **Name Wildcard (`*`)**: Using `*` automatically generates the direction name from the orientation (e.g. "North", "Down").
* **Example**:
  ```text
  LINK door1 beach-bungalow E
      NAME      wooden door
      ATTRLIST  open=0,locked
      ICON      icoDoor.gif
  ```

---

## 9. The CHARACTERS Section

### 9.1 Global Character Attributes
Defining an `ATTRLIST` directly under `CHARACTERS` applies default attributes to **all** characters and connecting players:

```text
CHARACTERS
    ATTRLIST Health=10, Strength=1, Dexterity=1
    ...
END_CHARACTERS
```

### 9.2 Character Definition (NPCs & Agents)

```text
CHARACTER <id> [DEFAULT]
    NAME        <Full Name>
    [DESCRIPTION <text>]
    [POSITION   <room_id>]
    [ATTRLIST   <attributes>]
    [ACCEPTS    <* | item_ids | type_ids>]
    [ICON       <icon_url>]
    [CAPACITY   <int>]
    [VOLUME     <int>]
    [SHOW       <showMode [x,y]>]
    [IMAGE      [dimensions] <url>]
```

* **`ACCEPTS`**: Comma-separated list of accepted items or types. Wildcard `*` accepts anything. By default, NPCs accept no items from players.
* **`DEFAULT`**: Marks this character as the system default announcer voice (`SYS`).

---

## 10. The ITEMS Section (ITEM & VEHICLE)

### 10.1 Interactive Items

```text
ITEM <id>
    NAME         <Display Name>
    [DESCRIPTION <text>]
    [TYPE        <type.subtype>]
    [POSITION    <room_id | container_item_id | character_id>]
    [ATTRLIST    <attributes>]
    [CAPACITY    <int>]
    [VOLUME      <int>]
    [ICON        <icon_url>]
    [PANEL       <panelId>]
    [SHOW        <showMode [x,y]>]
    [IMAGE       [dimensions] <url>]
    [INNER IMAGE [dimensions] <url>]
    [ZOOMIMAGE   [dimensions] <url>]
```

* **Nested Containment**: `POSITION` can point to another `ITEM` (e.g. `apple` inside `box`), a `ROOM`, or a `CHARACTER` (inventory).
* **`INNER IMAGE`**: Visual interior displayed when an entity is inside this container.
* **`ZOOMIMAGE`**: High-detail close-up shown when inspected with `look`.

### 10.2 Vehicles & Passenger Transport

```text
VEHICLE <id>
    NAME         <Display Name>
    ...
```

* **Vehicle Mechanics**:
  * Inherits all `ITEM` properties, but defaults to `CAPACITY 10`, `VOLUME 10`, and `hideable=0`.
  * When a player enters a vehicle, navigation controls forward movement commands to the vehicle itself, driving it through room exits.
  * Transparent areas in the `INNER IMAGE` let players see the room outside while driving.

---

## 11. The SETS Section (ARRAY & SET)

Initializes structured collections in `Dict` storage:

```text
SETS
    ' 1-indexed ordered array (keys auto-generated as 0001, 0002, ...)
    ARRAY arrMusic  intro.mid, Runner.mid, Theme.mid, Chase.mid
    ARRAY players   (empty), (empty)

    ' Key-value dictionary sorted alphabetically by key
    SET setWeapons  sword=10, dagger=4, bow=8
    SET setValuable scroll, book, crown, sword
END_SETS
```

---

## 12. The SCRIPTS Section

Contains SmallBasic event routines, subroutines, and functions:

```text
SCRIPTS
    Include "combat_system.dxl"

    Sub initWorld()
        $WORLD.mode = 1
    End_Sub

    Function doubleVal(x)
        Return x * 2
    End_Function

    EVENT onStart
        Call initWorld
    End_EVENT

    EVENT switch.onUse
        If $WORLD.mode <> 1
            $WORLD.mode = 1
            SetPanel $WORLD, "default"
            Broadcast "Game started!"
        End_If
    End_EVENT
END_SCRIPTS
```

* **Event Parameters (Roles)**:
  * `$OWNER`: Object to which the event belongs.
  * `$AGENT`: Character who initiated the action.
  * `$TARGET`: Secondary target object or direction.
  * `input()`: Key-value dictionary containing submitted form/panel input.

---

## 13. File Inclusion Directive (Include)

The `Include` directive imports modular library files (often with `.dxl` extension):

```text
Include "common_rooms.dxl"
Include "https://www.myserver.com/lib/spells.dxl"
```

* Can appear at root level or within any section.
* Path resolution:
  * Remote URLs (`http://`, `https://`) fetched over HTTP.
  * Relative paths resolved relative to the directory containing the parent `.dxw` file.

---

## 14. Reference Parsing Grammar Summary

```ebnf
WorldFile       ::= 'WORLD' WorldDirectives EndWorld
EndWorld        ::= 'END_WORLD' | EOF

WorldDirectives ::= ( WorldProperty | Include | Section )*
Section         ::= GuiSection | RoomsSection | LinksSection 
                  | CharactersSection | ItemsSection | SetsSection | ScriptsSection

GuiSection      ::= 'GUI' ( GuiDirective | PanelDef | PageDef | Include )* 'END_GUI'
RoomsSection    ::= 'ROOMS' ( RoomDef | Include )* 'END_ROOMS'
LinksSection    ::= 'LINKS' ( LinkDef | MLinkDef | Include )* 'END_LINKS'
CharactersSection ::= 'CHARACTERS' [ AttrListDef ] ( CharacterDef | Include )* 'END_CHARACTERS'
ItemsSection    ::= 'ITEMS' ( ItemDef | VehicleDef | Include )* 'END_ITEMS'
SetsSection     ::= 'SETS' ( ArrayDef | SetDef | Include )* 'END_SETS'
ScriptsSection  ::= 'SCRIPTS' ( EventDef | SubDef | FunctionDef | Include )* 'END_SCRIPTS'

AttrListDef     ::= 'ATTRLIST' AttrItem ( ',' AttrItem )*
AttrItem        ::= Identifier [ '=' LiteralValue ]
LiteralValue    ::= Number | String | Boolean
```
