# DimensioneX / DimensioneY Skins & `.dxs` Specification

This document provides the definitive specification for user interface skins and `.dxs` theme definition files in DimensioneX and DimensioneY. It is transcribed and synthesized from **Chapter 6** (*SKINS - Customizing the user interface*) and **Section 2.15** (*SKINS GUI Directive*) of the *DimensioneX Developer's Reference (v7.1.7)*, combined with the original Java reference implementation ([`SkinLoader.java`](file:///c:/xampp/htdocs/dimya/docs/dimx-classes/SkinLoader.java), [`Skin.java`](file:///c:/xampp/htdocs/dimya/docs/dimx-classes/Skin.java), and [`WorldLoader.java`](file:///c:/xampp/htdocs/dimya/docs/dimx-classes/WorldLoader.java)).

> [!NOTE]
> **DimX vs DimY status.** This specification is based on **DimensioneX** (Java). Items not fully implemented in **DimensioneY** (PHP: `Skin::loadFromDxs` in `Skin.php`, `SKINS` handling in `DxwLoader.php`, usage in `UIhtml.php`) are tagged inline as **[DimX only - not yet implemented in DimY]** or **[DimY: partial - ...]**. Untagged items are implemented.

### DimY implementation status

| Area | DimY status |
|---|---|
| `SKINS` directive in `.dxw` | Partial - each listed skin is loaded into `World::$skins`; the default is always the skin `default` (not the first listed); no per-player skin choice; direct `http(s)://` skinspecs not supported |
| `SKIN <id>` / `END_SKIN` block, `ID` | Ignored - file is parsed line by line; skin id = containing folder name |
| `NAME`, `SKINFOLDER` / `IMAGESFOLDER` | Implemented |
| `PICPLAYER`, `PICFADER`, `ICOPLAYER`, `ICOCHARACTER`, `ICOITEM`, `ICOWAY*`, `ICOROT*`, `ICOREV` | Implemented (built-in default file names differ from the table) |
| `PICSPACER` | Partial - built-in default only; not read from `.dxs` |
| `BODYBGCOLOR`, `PANELBGCOLOR` | Implemented |
| `TITLESTYLE`, `BUTTONSTYLE` | Partial - parsed but never emitted into the page |
| `SNDNEWMSG`, `BODYBACKGROUND`, `BODYSTYLE`, `SYSMSGSTYLE`, `LINKSTYLE`, `ALINKSTYLE`, `ICONLABELSTYLE`, `PANELBACKGROUND`, `PANELSTYLE`, `LIST1BGCOLOR`, `LIST2BGCOLOR`, `LISTSTYLE`, `CTRLBANNER`, `STYLESHEET` | Ignored (not parsed) |
| `$SKINFOLDER` / `$IMAGESFOLDER` | Partial - substituted in `TITLESTYLE` only (not rendered) |
| `$ICON`, `$SKINICON` | Not implemented |
| `_net` skins, `.txt` descriptor fallback | Not implemented |

---

## 1. Overview & Architecture

A **SKIN** is a cohesive combination of HTML visual settings, CSS style definitions, audio cues, and image assets that define the graphic appearance and layout of the player's web client interface.

### Key Capabilities:
- **Customizable Look & Feel**: Background colors, background wallpaper textures, font typography, navigation compass arrows, title bars, action consoles, chat message list alternating row colors, and button textures.
- **Portable & Reusable**: Encapsulated into a single `.dxs` descriptor file with associated assets in a dedicated folder, easily shared across worlds and servers.
- **Multi-Skin Support**: Worlds can declare multiple skins in their `.dxw` world definition. Players can choose their preferred skin at login, or the game can enforce a theme. **[DimY: partial - multiple skins are loaded, but players always get the `default` skin; no login selection]**
- **Graceful Fallbacks**: If an attribute is omitted, the engine automatically defaults to standard built-in DimensioneX values.

---

## 2. The `SKINS` Directive in `.dxw` (`GUI` Section)

Inside the `GUI ... END_GUI` block of a `.dxw` world definition file, available skins are declared with the `SKINS` directive:

```dxw
GUI
    ...
    SKINS default, forest.dxs, castle.dxs, https://example.com/skins/scifi.dxs
    ...
END_GUI
```

### Semantics & Rules:
1. **Order of Preference**: Skins are listed in order of preference. The **first skin in the list is the default skin** used for the world and proposed first to connecting players. **[DimY: partial - the default is always the skin id `default` (auto-created if not listed), regardless of order]**
2. **Omission**: If the `SKINS` directive is omitted entirely, the engine loads the internal `default` skin.
3. **Specifiers (`skinspec`)**:
   - `default`: Built-in default theme. Assumes standard graphics located under `<imagesFolder>/skins/default/`.
   - `filename.dxs`: Local skin descriptor file. Searched in the current world directory or `<imagesFolder>/skins/<skinName>/<filename>.dxs`.
   - `http://...` / `https://...`: Remote skin descriptor loaded over HTTP at world startup. **[DimY: partial - a full URL as skinspec is not supported; remote `.dxs` is only fetched as `<skinFolderUrl>/<id>.dxs` when the resolved skin folder is an HTTP URL]**
4. **Resolution Logic**:
   - For relative paths, the engine resolves the file against `skins/<skinId>/<filename>.dxs`.
   - The engine validates and registers all loaded skins into `World::$skins` indexed by their `Skin::$id`.

---

## 3. Placement & Directory Structure (§6.1)

A skin is composed of:
1. **One definition file**: A text file named `<skinId>.dxs` (or `<skinId>.txt` if restricted by server mime types **[DimX only - not yet implemented in DimY: only `.dxs` is looked up]**).
2. **A folder of media assets**: Image files (`.gif`, `.jpg`, `.png`) and audio clips (`.wav`).

### Directory Layout
The folder structure follows the rule that the skin directory name must match the skin ID:

```text
dimx/
├── pics/                        <-- IMAGESFOLDER (World pictures)
│   ├── beach.jpg
│   └── apple.gif
└── skins/                       <-- SKINS ROOT
    ├── default/                 <-- Skin ID: default
    │   ├── default.dxs
    │   ├── player.gif
    │   └── blank.gif
    ├── forest/                  <-- Skin ID: forest
    │   ├── forest.dxs
    │   ├── avatar.gif
    │   ├── vine-001.jpg
    │   └── stylesheet.css
    └── castle/                  <-- Skin ID: castle
        ├── castle.dxs
        └── stone.jpg
```

> [!IMPORTANT]
> **CDN / Remote Media Hosting**:  
> If game images are hosted on a separate media server, the `skins/` folder on that media server must be parallel to the `IMAGESFOLDER`.  
> *Example*: If world images are at `https://cdn.example.com/dimx/pics/`, the engine assumes skin assets reside at `https://cdn.example.com/dimx/skins/<skinId>/`.

---

## 4. `.DXS` Syntax & File Structure (§6.2)

A `.dxs` file is a plain text file using the standard DimensioneX keyword-value grammar:
- Enclosed in a `SKIN <id> ... END_SKIN` block. **[DimY: ignored - `SKIN`/`END_SKIN` lines are skipped as unknown keys; the skin id is taken from the parent folder name. Lines starting with `#` are also treated as comments]**
- Comments start with an apostrophe `'`.
- Keywords are case-insensitive.
- Each property is specified as `KEYWORD <value>`.

### Complete Example (`forest.dxs`):

```dxw
SKIN forest
    ' Version 1.0 - by Cris
    NAME            Forest
    PICPLAYER       avatar.gif
    PICSPACER       blank.gif
    PICFADER        fadewhite.gif
    ICOPLAYER       player.gif
    ICOCHARACTER    character.gif
    ICOITEM         item.gif
    ICOWAY          way.gif
    ICOWAYN         north.gif
    ICOWAYS         south.gif
    ICOWAYE         right.gif
    ICOWAYW         left.gif
    ICOWAYU         up.gif
    ICOWAYD         down.gif
    ICOROTL         icorotl.gif
    ICOROTR         icorotr.gif
    ICOREV          reverse.gif

    SNDNEWMSG       notify4.wav

    BODYBGCOLOR     #206020
    BODYBACKGROUND  
    BODYSTYLE       {font-size:10pt;font-family:Courier New;color:#00FFFF}
    SYSMSGSTYLE     {font-size:10pt;font-weight:bold;font-family:Courier New;color:#FF1111}
    LINKSTYLE       {font-size:10pt;font-family:Lucida Sans;color:#005000}
    ALINKSTYLE      {font-size:10pt;font-family:Lucida Sans;color:#FF0000;text-decoration:none}
    ICONLABELSTYLE  {font-size:8pt;font-family:Lucida Sans;color:#DDDDDD}
    PANELBGCOLOR    SILVER
    PANELBACKGROUND vine-001.jpg
    PANELSTYLE      {font-size:10pt;font-family:Lucida Sans;color:#FFFFFF}
    BUTTONSTYLE     {background-color:#00CC00;color:black;cursor:hand;font-family:Tahoma;font-size:8pt;height:20px;border-top:2px outset #00FF00;border-left:2px outset #00EE00;}
    TITLESTYLE      {font-size:10pt;font-family:Lucida Sans;font-weight:bold;color:#FFFF20;background-color:#009000}
    LIST1BGCOLOR    #004000
    LIST2BGCOLOR    #316131
    LISTSTYLE       {font-size:8pt;font-family:Courier New;color:#DDDDDD}
    STYLESHEET      stylesheet.css
END_SKIN
```

---

## 5. Complete Catalog of Skin Attributes

| Attribute | Mandatory | Default Value | Description |
|---|:---:|---|---|
| **`ID`** | **Yes** | — | Unique identifier of the skin (e.g. `forest`, `dark`, `default`). Special value `default` overrides the built-in system default theme. **[DimY: ignored - id is the parent folder name]** |
| **`NAME`** | **Yes** | *(same as ID)* | Human-readable name displayed in login drop-down menus and player settings. |
| **`SKINFOLDER`** / **`IMAGESFOLDER`** | No | `dimx/skins/<ID>/` | Path or URL to the folder containing image assets for this skin. If specified manually, it must end with a trailing slash (`/`). |
| **`PICPLAYER`** | No | `player.gif` | Main avatar graphic representing human players on the scene (standard size: 64×100 px). |
| **`PICSPACER`** | No | `blank.gif` | 1×1 transparent GIF used for table/grid layout spacing. **[DimY: partial - not read from `.dxs`; always `<skinFolder>/blank.gif`]** |
| **`PICFADER`** | No | `fader.gif` | Semi-transparent overlay graphic used to dim the background scene when examining objects. |
| **`ICOPLAYER`** | No | `player.gif` | Small thumbnail icon for other players in room occupant listings. |
| **`ICOCHARACTER`** | No | `character.gif` | Default icon for computer-controlled NPCs. |
| **`ICOITEM`** | No | `item.gif` | Default icon for items when no specific item icon is defined. |
| **`ICOWAY`** | No | `way.gif` | Default icon for directional links without an explicit direction. |
| **`ICOWAYN`** | No | `north.gif` | Icon for North exit. |
| **`ICOWAYS`** | No | `south.gif` | Icon for South exit. |
| **`ICOWAYE`** | No | `east.gif` | Icon for East exit. |
| **`ICOWAYW`** | No | `west.gif` | Icon for West exit. |
| **`ICOWAYU`** | No | `up.gif` | Icon for Up exit. |
| **`ICOWAYD`** | No | `down.gif` | Icon for Down exit. |
| **`ICOROTL`** | No | `left.gif` | Icon/button to rotate view left in panoramic rooms. |
| **`ICOROTR`** | No | `right.gif` | Icon/button to rotate view right in panoramic rooms. |
| **`ICOREV`** | No | `reverse.gif` | Icon/button to turn around (180°) in panoramic rooms. |
| **`SNDNEWMSG`** | No | `newmsg.wav` | Audio notification clip played when an incoming chat message arrives. **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`BODYBGCOLOR`** | No | `#000040` | Background color for the browser viewport. |
| **`BODYBACKGROUND`** | No | `""` *(none)* | Background image texture for the main browser window. **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`BODYSTYLE`** | No | `{font-size:10pt;font-family:Tahoma;color:#00FFFF;margin:0;}` | CSS inline style for main text elements. **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`SYSMSGSTYLE`** | No | `{font-size:10pt;font-family:Tahoma;color:#FF1111;}` | CSS style for system notices and engine error messages. **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`LINKSTYLE`** | No | `{font-family:Tahoma;color:#EE30EE;}` | CSS style for hypertext links (`<a>`). **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`ALINKSTYLE`** | No | `{font-family:Tahoma;color:#EE30EE;}` | CSS style for active / hovered hyperlinks. **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`ICONLABELSTYLE`** | No | `{font-size:10pt;font-family:Tahoma;color:#DDDDDD;}` | CSS style for caption text displayed underneath on-screen icons. **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`PANELBGCOLOR`** | No | `SILVER` | Background color for command button panels. |
| **`PANELBACKGROUND`** | No | `""` *(none)* | Background wallpaper image for the command panel bar. **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`PANELSTYLE`** | No | `{font-size:10pt;font-family:Tahoma;color:#600000;}` | CSS style for labels and text inside command panels. **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`BUTTONSTYLE`** | No | `""` | CSS style applied to buttons. Supports `$ICON` and `$SKINICON` substitutions. **[DimY: partial - parsed into `Skin::$buttonStyle` but not rendered; `$ICON`/`$SKINICON` not substituted]** |
| **`TITLESTYLE`** | No | `{font-size:10pt;font-family:Tahoma;color:#FFFF10;background-color:#000090;}` | CSS style for top title bar and header frames. Supports `$SKINFOLDER`. **[DimY: partial - parsed with `$SKINFOLDER`/`$IMAGESFOLDER` substitution into `Skin::$titleStyle`, but not rendered]** |
| **`LIST1BGCOLOR`** | No | `#000000` | Background color for odd-numbered rows in the chat / log frame. **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`LIST2BGCOLOR`** | No | `#414141` | Background color for even-numbered rows in the chat / log frame. **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`LISTSTYLE`** | No | `{font-size:8pt;font-family:Tahoma;color:#A0B0C0;}` | Typography style for messages inside the chat / log queue. **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`CTRLBANNER`** | No | `""` *(none)* | Header image tag or game logo rendered at the top of the command panel. **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`STYLESHEET`** | No | `""` *(none)* | Filename of an external CSS stylesheet residing inside the skin folder. **[DimX only - not yet implemented in DimY: directive is not parsed]** |

---

## 6. Dynamic Placeholders & Variable Substitutions

In `BUTTONSTYLE` and `TITLESTYLE`, the engine performs automatic token substitution at runtime:

1. **`$SKINFOLDER`** and **`$IMAGESFOLDER`**: **[DimY: partial - only substituted in `TITLESTYLE`, which is not rendered]**
   - Replaced by the absolute URL or resolved relative path to the skin's asset directory.
   - Example in `TITLESTYLE`:
     ```text
     TITLESTYLE {background-image: url($SKINFOLDER/banner.png); font-family: Tahoma;}
     ```
2. **`$ICON`**: **[DimX only - not yet implemented in DimY]**
   - In `BUTTONSTYLE`, replaced by the button's individual icon URL if defined in the panel definition.
3. **`$SKINICON`**: **[DimX only - not yet implemented in DimY]**
   - Automatically resolves to `<skinFolder>/pan<cmdId>.gif`.
   - Allows skin creators to supply customized icon textures for standard commands (e.g., `panlook.gif`, `pango.gif`, `pansay.gif`).

---

## 7. CSS Integration & Custom Classes (§6.2.1)

When a `STYLESHEET` is declared, the engine links `<skinFolder>/<stylesheet>` into the client HTML. **[DimX only - not yet implemented in DimY: `STYLESHEET` is ignored; DimY emits built-in CSS via `Skin::getCss()` and `UIhtml.php`.]** Standard DimensioneX interface selectors include:

| Selector | Type | Purpose |
|---|:---:|---|
| `#scene` | ID | Main game area container (title bar + `#display` and `#dashboard`). |
| `#display` | ID | Left sub-pane inside `#scene` (room visual canvas and `#consoleBox`). |
| `#dashboard` | ID | Right sub-pane inside `#scene` (includes `#navpad`, room items, inventory, and stats). |
| `#ctrls` | ID | Command controls panel container (action buttons and speech input). |
| `#msgs` | ID | Bottom chat/messages area container (wraps `#chatBox`). |
| `#navpad` | ID | The table container for directional navigation buttons (compass / pad). |
| `#consoleBox` | ID | Action feedback console box. |
| `#chatBox` | ID | Multi-user chat history box. |
| `.dimx-panel` | Class | Outer container for command panels. |
| `.dimx-btn` | Class | Interactive action buttons. |
| `.dimx-onscreen-obj` | Class | Clickable point-and-click target objects on scene canvas. |

---

## 8. Distribution, Packaging & Troubleshooting (§6.2.2 – §6.2.4)

### Packaging for Distribution
To export and share a custom skin with other developers:
1. Place `<skinId>.dxs` directly inside the `skins/<skinId>/` directory alongside all referenced media files.
2. Compress the entire `skins/<skinId>/` directory into a `.zip` archive (e.g. `forest.zip`).
3. To install, unzip directly into the target server's `skins/` directory.

### `_net` Remote Skins (§6.2.3) **[DimX only - not yet implemented in DimY: no special `_net` handling]**
Skins with names ending in `_net` (e.g. `forest_net.dxs`) are configured to point their `SKINFOLDER` to the central DimensioneX CDN. This allows rapid testing without downloading and hosting heavy graphic bundles locally.

### Troubleshooting
- **Missing Images**: Verify that `skins/<skinId>/` exists and is parallel to `IMAGESFOLDER`.
- **MIME Types**: If `.dxs` files fail to load from a web server due to strict MIME types, rename the descriptor to `.txt` (e.g. `forest.txt`) and reference it accordingly. **[DimX only - not yet implemented in DimY]**
- **Malformed Paths**: Ensure any manually specified `SKINFOLDER` includes the mandatory trailing slash `/`.
