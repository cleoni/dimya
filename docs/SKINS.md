# DimensioneX / DimensioneY Skins & `.dxs` Specification

This document provides the definitive specification for user interface skins and `.dxs` theme definition files in DimensioneX and DimensioneY. It is transcribed and synthesized from **Chapter 6** (*SKINS - Customizing the user interface*) and **Section 2.15** (*SKINS GUI Directive*) of the *DimensioneX Developer's Reference (v7.1.7)*, combined with the original Java reference implementation ([`SkinLoader.java`](file:///c:/xampp/htdocs/dimya/docs/dimx-classes/SkinLoader.java), [`Skin.java`](file:///c:/xampp/htdocs/dimya/docs/dimx-classes/Skin.java), and [`WorldLoader.java`](file:///c:/xampp/htdocs/dimya/docs/dimx-classes/WorldLoader.java)).

> [!NOTE]
> **DimX vs DimY status.** This specification is based on **DimensioneX** (Java). Items not fully implemented in **DimensioneY** (PHP: `Skin::loadFromDxs` in `Skin.php`, `SKINS` handling in `DxwLoader.php`, usage in `UIhtml.php`) are tagged inline as **[DimX only - not yet implemented in DimY]** or **[DimY: partial - ...]**. Untagged items are implemented.

### DimY implementation status

| Area | DimY status |
|---|---|
| `SKINS` directive in `.dxw` | Implemented - each listed skin (`default`, `<file>.dxs`, or direct `http(s)://` `.dxs` URL) is loaded into `World::$skins`; players can select their preferred skin in the login connect form (`Player::$skin`), while `World::$defaultSkin` remains `default` |
| `SKIN <id>` / `END_SKIN` block, `ID` | Implemented - `SKIN <id>` and `ID <id>` set `Skin::$id` (fallback: descriptor filename stripped of `_net.dxs` / `.dxs`) |
| `NAME`, `SKINFOLDER` / `IMAGESFOLDER` | Implemented - uses `SKINFOLDER` / `IMAGESFOLDER` if defined in `.dxs`; otherwise defaults to `<worldImagesParent>/skins/<skinId>/` |
| `PICPLAYER`, `PICSPACER`, `PICFADER`, `ICOPLAYER`, `ICOCHARACTER`, `ICOITEM`, `ICOWAY*`, `ICOROT*`, `ICOREV` | Implemented (omitted directives fall back to hard-coded default filenames inside `Skin::$skinFolder`) |
| `BODYBGCOLOR`, `PANELBGCOLOR`, `PANELBACKGROUND`, `PANELSTYLE`, `LIST1BGCOLOR`, `LIST2BGCOLOR`, `LISTSTYLE` | Implemented |
| `TITLESTYLE`, `BUTTONSTYLE`, `STYLESHEET` | Partial - parsed into `Skin::$titleStyle`, `Skin::$buttonStyle`, `Skin::$stylesheet` |
| `SNDNEWMSG`, `BODYBACKGROUND`, `BODYSTYLE`, `SYSMSGSTYLE`, `LINKSTYLE`, `ALINKSTYLE`, `ICONLABELSTYLE`, `CTRLBANNER` | Ignored (not parsed) |
| `$SKINFOLDER` / `$IMAGESFOLDER` | Partial - substituted in `TITLESTYLE` |
| `$ICON`, `$SKINICON` | Not implemented |
| `_net` skins | Implemented (via `SKINFOLDER` in `.dxs` and/or direct `http(s)://` `.dxs` URL in `SKINS`) |
| `.txt` descriptor fallback | Not implemented |

---

## 1. Overview & Architecture

A **SKIN** is a cohesive combination of HTML visual settings, CSS style definitions, audio cues, and image assets that define the graphic appearance and layout of the player's web client interface.

### Core Principles:
1. **Isolated `Skin` Instances**: Each `Skin` instance owns its own set of media file URLs and CSS configuration. Loading or updating a skin never overwrites another `Skin` instance.
2. **Hard-Coded Default Media & No Missing Links**:
   - The `default` skin has no `.dxs` file; instead, `Skin` provides hard-coded default filenames for all media elements (`uomo.gif`, `blank.gif`, `fadewhite.gif`, `icoPlayer.gif`, `icouser.gif`, `icoitem.gif`, `icoway.gif`, `icowayn.gif`, `icoways.gif`, `icowaye.gif`, `icowayw.gif`, `icowayu.gif`, `icowayd.gif`, `icorotl.gif`, `icorotr.gif`, `reverse.gif`).
   - Because all `.dxs` directives are optional, any media element omitted in a `.dxs` file retains its default filename resolved against that skin's `SKINFOLDER`. No `Skin` instance ever has a missing/null media reference: if a media file is absent on the server, the rendered link points to the expected URL in `SKINFOLDER` so the author immediately sees it and knows it must be fixed (media files are purely aesthetic and do not affect core engine tasks).
3. **`SKINFOLDER` Determination Rule**:
   - A crucial property of every `Skin` instance is its media folder (`SKINFOLDER`, stored in `Skin::$skinFolder`), where its image and media files are located.
   - If `SKINFOLDER` (or legacy alias `IMAGESFOLDER`) is defined in the `.dxs` file, that URL/path is used directly.
   - If `SKINFOLDER` is **not** defined in the skin, it is calculated by default from the World's active `IMAGESFOLDER` (see [Section 3](#3-placement--directory-structure-61)): take the World's `IMAGESFOLDER`, trim out the last `/dir` segment in the URL (typically `"/pics"`), append `"/skins"`, and append `"/" . $skin->id . "/"` (e.g. `"/default/"`). This rule applies uniformly to all skins.

### Key Capabilities:
- **Customizable Look & Feel**: Background colors, background wallpaper textures, font typography, navigation compass arrows, title bars, action consoles, chat message list alternating row colors, and button textures.
- **Portable & Reusable**: Encapsulated into a single `.dxs` descriptor file with associated assets in a dedicated folder, easily shared across worlds and servers.
- **Multi-Skin Support**: Worlds can declare multiple skins in their `.dxw` world definition. Players can choose their preferred skin from the dropdown in the login connect form, and each `Player` instance holds a reference (`$player->skin`) to their chosen `Skin`.
- **Graceful Fallbacks**: If an attribute is omitted, the engine automatically defaults to standard built-in values resolved under the skin's `SKINFOLDER`.

---

## 2. The `SKINS` Directive in `.dxw` (`GUI` Section)

Inside the `GUI ... END_GUI` block of a `.dxw` world definition file, available skins are declared with the `SKINS` directive:

```dxw
GUI
    ...
    SKINS default, forest.dxs, castle.dxs, https://www.dimensionex.net/skins/aqua/aqua_net.dxs
    ...
END_GUI
```

### Semantics & Rules:
1. **Order of Preference**: Skins are listed in order of preference in the login connect form dropdown. (`World::$defaultSkin` is initialized to the `default` skin for backward compatibility when no explicit skin is selected.)
2. **Omission**: If the `SKINS` directive is omitted entirely, the engine loads the internal `default` skin.
3. **Specifiers (`skinspec`)**:
   - `default`: Built-in default theme (no `.dxs` file needed). Uses hard-coded default graphics located under the resolved `<worldImagesParent>/skins/default/`.
   - `filename.dxs`: Skin descriptor file. Searched locally in the world/skins directories, or fetched remotely from `<skinFolderUrl>/<filename>.dxs` when `IMAGESFOLDER` is an HTTP(S) URL.
   - `http://...` / `https://...`: Remote skin descriptor loaded directly over HTTP/HTTPS at world startup (e.g. `https://www.dimensionex.net/skins/aqua/aqua_net.dxs`).
4. **Resolution Logic**:
   - For relative paths, the engine resolves the file against `skins/<skinId>/<filename>.dxs`.
   - The engine validates and registers all loaded skins into `World::$skins` indexed by their `Skin::$id`.

---

## 3. Placement & Directory Structure (§6.1)

A skin is composed of:
1. **One definition file**: A text file named `<skinId>.dxs` (or `<skinId>.txt` if restricted by server mime types **[DimX only - not yet implemented in DimY: only `.dxs` is looked up]**). The `default` skin does not require a `.dxs` file.
2. **A folder of media assets (`SKINFOLDER`)**: Image files (`.gif`, `.jpg`, `.png`), optional CSS stylesheets, and audio clips (`.wav`).

### Default `SKINFOLDER` Calculation Rule
If `SKINFOLDER` (or `IMAGESFOLDER`) is explicitly defined inside a `.dxs` file, that value is used as the skin's media folder.
Otherwise, the skin's `SKINFOLDER` is automatically derived from the World's active `IMAGESFOLDER`:
1. Take the World's `IMAGESFOLDER` URL or path (e.g. `https://www.dimensionex.net/pics/`).
2. Trim the last `/dir` segment in the URL (usually `"/pics"`), leaving the parent root (`https://www.dimensionex.net`).
3. Append `"/skins/"` followed by the skin's ID (e.g. `"default"`, `"aqua"`, `"forest"`), producing `https://www.dimensionex.net/skins/default/`.

This rule applies to **all** skins, including `default`.

### Directory Layout
The folder structure follows the rule that the skin directory name matches the skin ID:

```text
dimx/
├── pics/                        <-- IMAGESFOLDER (World pictures)
│   ├── beach.jpg
│   └── apple.gif
└── skins/                       <-- SKINS ROOT (parallel to pics/)
    ├── default/                 <-- Skin ID: default (hard-coded media names, no .dxs needed)
    │   ├── uomo.gif
    │   ├── blank.gif
    │   └── icowayn.gif
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
> If game images are hosted on a separate media server, the `skins/` folder on that media server must be parallel to the `IMAGESFOLDER` (unless `SKINFOLDER` is explicitly set in `.dxs`).  
> *Example*: If world images are at `https://cdn.example.com/dimx/pics/`, the engine assumes skin assets reside at `https://cdn.example.com/dimx/skins/<skinId>/`.

---

## 4. `.DXS` Syntax & File Structure (§6.2)

A `.dxs` file is a plain text file using the standard DimensioneX keyword-value grammar:
- Enclosed in a `SKIN <id> ... END_SKIN` block, where `<id>` sets the skin's unique identifier (`Skin::$id`).
- Comments start with an apostrophe `'` (or `#` in DimY).
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

| Attribute | Mandatory | Default Value (DimX / DimY built-in) | Description |
|---|:---:|---|---|
| **`SKIN <id>`** / **`ID`** | **Yes** | — | Unique identifier of the skin (e.g. `forest`, `aqua`, `default`). Used to compute the default `SKINFOLDER` when `SKINFOLDER` is omitted. |
| **`NAME`** | **Yes** | *(same as ID)* | Human-readable name displayed in the login connect form drop-down menu (`<select name="skin">`). |
| **`SKINFOLDER`** / **`IMAGESFOLDER`** | No | `<worldImagesParent>/skins/<ID>/` | Path or URL to the folder containing media assets for this skin. If omitted, calculated from the World's `IMAGESFOLDER` by replacing its last `/dir` segment with `/skins/<ID>/`. Trailing `/` is ensured automatically. |
| **`PICPLAYER`** | No | `player.gif` (`uomo.gif` in DimY) | Main avatar graphic representing human players on the scene (standard size: 64×100 px). |
| **`PICSPACER`** | No | `blank.gif` | 1×1 transparent GIF used for table/grid layout spacing. |
| **`PICFADER`** | No | `fader.gif` (`fadewhite.gif` in DimY) | Semi-transparent overlay graphic used to dim the background scene when examining objects. |
| **`ICOPLAYER`** | No | `player.gif` (`icoPlayer.gif` in DimY) | Small thumbnail icon for other players in room occupant listings. |
| **`ICOCHARACTER`** | No | `character.gif` (`icouser.gif` in DimY) | Default icon for computer-controlled NPCs. |
| **`ICOITEM`** | No | `item.gif` (`icoitem.gif` in DimY) | Default icon for items when no specific item icon is defined. |
| **`ICOWAY`** | No | `way.gif` (`icoway.gif` in DimY) | Default icon for directional links without an explicit direction. |
| **`ICOWAYN`** | No | `north.gif` (`icowayn.gif` in DimY) | Icon for North exit in `#navpad`. |
| **`ICOWAYS`** | No | `south.gif` (`icoways.gif` in DimY) | Icon for South exit in `#navpad`. |
| **`ICOWAYE`** | No | `east.gif` (`icowaye.gif` in DimY) | Icon for East exit in `#navpad`. |
| **`ICOWAYW`** | No | `west.gif` (`icowayw.gif` in DimY) | Icon for West exit in `#navpad`. |
| **`ICOWAYU`** | No | `up.gif` (`icowayu.gif` in DimY) | Icon for Up exit in `#navpad`. |
| **`ICOWAYD`** | No | `down.gif` (`icowayd.gif` in DimY) | Icon for Down exit in `#navpad`. |
| **`ICOROTL`** | No | `left.gif` (`icorotl.gif` in DimY) | Icon/button to rotate view left in panoramic rooms. |
| **`ICOROTR`** | No | `right.gif` (`icorotr.gif` in DimY) | Icon/button to rotate view right in panoramic rooms. |
| **`ICOREV`** | No | `reverse.gif` | Icon/button to turn around (180°) in panoramic rooms. |
| **`SNDNEWMSG`** | No | `newmsg.wav` | Audio notification clip played when an incoming chat message arrives. **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`BODYBGCOLOR`** | No | `#000040` | Background color for the browser viewport. |
| **`BODYBACKGROUND`** | No | `""` *(none)* | Background image texture for the main browser window. **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`BODYSTYLE`** | No | `{font-size:10pt;font-family:Tahoma;color:#00FFFF;margin:0;}` | CSS inline style for main text elements. **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`SYSMSGSTYLE`** | No | `{font-size:10pt;font-family:Tahoma;color:#FF1111;}` | CSS style for system notices and engine error messages. **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`LINKSTYLE`** | No | `{font-family:Tahoma;color:#EE30EE;}` | CSS style for hypertext links (`<a>`). **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`ALINKSTYLE`** | No | `{font-family:Tahoma;color:#EE30EE;}` | CSS style for active / hovered hyperlinks. **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`ICONLABELSTYLE`** | No | `{font-size:10pt;font-family:Tahoma;color:#DDDDDD;}` | CSS style for caption text displayed underneath on-screen icons. **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`PANELBGCOLOR`** | No | `SILVER` | Background color for command button panels (`#ctrls` and `#navpad`). |
| **`PANELBACKGROUND`** | No | `""` *(none)* | Background wallpaper image for the command panel bar (`#ctrls`), resolved against `SKINFOLDER`. |
| **`PANELSTYLE`** | No | `{font-size:10pt;font-family:Tahoma;color:#600000;}` | CSS style in `{ }` applied to controls and buttons inside `#ctrls` (`#ctrls .dimx-btn`). |
| **`BUTTONSTYLE`** | No | `""` | CSS style applied to buttons. Supports `$ICON` and `$SKINICON` substitutions. **[DimY: partial - parsed into `Skin::$buttonStyle` but not rendered; `$ICON`/`$SKINICON` not substituted]** |
| **`TITLESTYLE`** | No | `{font-size:10pt;font-family:Tahoma;color:#FFFF10;background-color:#000090;}` | CSS style for top title bar and header frames. Supports `$SKINFOLDER`. **[DimY: partial - parsed with `$SKINFOLDER`/`$IMAGESFOLDER` substitution into `Skin::$titleStyle`, but not rendered]** |
| **`LIST1BGCOLOR`** | No | `#000000` (`#101040` in DimY) | Background color for odd-numbered rows in the chat / messages frame (`#msgs`). |
| **`LIST2BGCOLOR`** | No | `#414141` (`#313161` in DimY) | Background color for even-numbered rows in the chat / messages frame (`#msgs`). |
| **`LISTSTYLE`** | No | `{font-size:8pt;font-family:Tahoma;color:#A0B0C0;}` | Typography style between `{ }` applied to `#chatBox` inside `#msgs`. |
| **`CTRLBANNER`** | No | `""` *(none)* | Header image tag or game logo rendered at the top of the command panel. **[DimX only - not yet implemented in DimY: directive is not parsed]** |
| **`STYLESHEET`** | No | `""` *(none)* | Filename of an external CSS stylesheet residing inside the skin folder. **[DimY: partial - parsed into `Skin::$stylesheet`, not yet linked in HTML]** |

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

When a `STYLESHEET` is declared, the engine links `<skinFolder>/<stylesheet>` into the client HTML. **[DimY: partial - `STYLESHEET` is parsed into `Skin::$stylesheet`; DimY currently emits built-in CSS via `Skin::getCss()` and `UIhtml.php`.]** Standard DimensioneX interface selectors include:

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

### `_net` Remote Skins (§6.2.3)
Skins with names ending in `_net` (e.g. `aqua_net.dxs` at `https://www.dimensionex.net/skins/aqua/aqua_net.dxs`) explicitly set `SKINFOLDER` to the central DimensioneX CDN (`http://www.dimensionex.net/skins/aqua/`). This allows rapid testing and remote skin loading without downloading and hosting graphic bundles locally.

### Troubleshooting
- **Missing Images**: Verify that `skins/<skinId>/` exists and is parallel to `IMAGESFOLDER` (or that `SKINFOLDER` is explicitly defined in `.dxs`).
- **MIME Types**: If `.dxs` files fail to load from a web server due to strict MIME types, rename the descriptor to `.txt` (e.g. `forest.txt`) and reference it accordingly. **[DimX only - not yet implemented in DimY]**
- **Malformed Paths**: Any manually specified `SKINFOLDER` is normalized with a trailing slash `/` in DimensioneY (`Skin::setSkinFolder`).
