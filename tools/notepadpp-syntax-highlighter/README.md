# DimensioneX / DimensioneY (.DXW & SmallBasic) Syntax Highlighter for Notepad++

This folder provides **User Defined Language (UDL 2.1)** syntax highlighting definition files for Notepad++, covering DimensioneX and DimensioneY world definition files (`.dxw`), skin files (`.dxs`), and embedded SmallBasic script blocks.

---

## Files in this Folder

| File | Description |
|---|---|
| [`dimx_udl_notepadpp.xml`](dimx_udl_notepadpp.xml) | Syntax highlighter configured for **Default / Light Mode** |
| [`dimx_udl_notepadpp_dark.xml`](dimx_udl_notepadpp_dark.xml) | Syntax highlighter configured for **Dark Mode** |

---

## Features

- **Automatic File Association**: Automatically applies syntax highlighting to `.dxw` and `.dxs` files.
- **Code Folding**:
  - Major sections: `GUI`...`END_GUI`, `ROOMS`...`END_ROOMS`, `LINKS`...`END_LINKS`, `ITEMS`...`END_ITEMS`, `CHARACTERS`...`END_CHARACTERS`, `SETS`...`END_SETS`, `SCRIPTS`...`END_SCRIPTS`, `EVENTS`...`END_EVENTS`, `HOOKS`...`END_HOOKS`.
  - Script routines: `EVENT`...`END_EVENT`, `SUB`...`END_SUB`, `FUNCTION`...`END_FUNCTION`.
  - Control blocks: `If`...`Else`...`End_If` (or `End If`), `For`...`Next`.
- **Syntax Coloring Categories**:
  - **Keywords 1 (Declarations & Structure)**: `WORLD`, `GUI`, `ROOMS`, `ROOM`, `LINKS`, `LINK`, `ITEMS`, `ITEM`, `CHARACTERS`, `CHARACTER`, `SETS`, `SET`, `SCRIPTS`, `SOUNDS`, `DEFAULT`, `EVENT`, `SUB`, `FUNCTION`.
  - **Keywords 2 (Object Properties & Directives)**: `NAME`, `DESCRIPTION`, `IMAGE`, `ICON`, `POSITION`, `TYPE`, `ATTRLIST`, `SHOW`, `SHOWAREA`, `ONSCREEN`, `SIZE`, `SCENE`, `SCREEN`, `AUTOLOOK`, `AUTHOR`, `VERSION`, `IMAGESFOLDER_LOCAL`, `IMAGESFOLDER_PUBLIC`, `LOGOSRC`, `SITE`.
  - **Keywords 3 (Statements & Actions)**: `Speak`, `Display`, `Move`, `PlaySound`, `BroadcastSound`, `PlayBackground`, `BroadcastBackground`, `Print`, `PrintRight`, `Debug`, `DropItems`, `Goal`, `Reset`, `RefreshView`, `SendCmd`, `SetPanel`, `Open`, `Close`, `Search`, `Give`.
  - **Keywords 4 (Flow Control & Language Keywords)**: `If`, `Then`, `Else`, `End_If`, `End If`, `For`, `To`, `Step`, `Next`, `Each`, `In`, `Return`, `Call`, `Include`, `REM`, `End`.
  - **Keywords 5 (Special Engine Variables)**: `$OWNER`, `$AGENT`, `$TARGET`, `$WORLD`, `true`, `false`, `null`.
  - **Keywords 6 (Built-in Events)**: `onStart`, `onNew`, `onReceive`, `onLook`, `onReceiveItem`, `onLooseItem`, `onLoose`, `onOpen`, `onClose`, `onLock`, `onUnlock`, `onUse`, `onUseWith`, `onEnter`, `onExit`, `Living`, `beforeOpen`, `beforeClose`, etc.
  - **Keywords 7 (SmallBasic Built-in Functions)**: Complete catalog of all 55 functions (`RndInt`, `RndSet`, `Abs`, `Asc`, `CBool`, `CDate`, `InStr`, `Int`, `LCase`, `Left`, `Len`, `Mid`, `Replace`, `Right`, `Rnd`, `Trim`, `UCase`, etc.).
  - **Keywords 8 (Compass Directions & Common Attributes)**: `N`, `S`, `E`, `W`, `U`, `D`, `NE`, `NW`, `SE`, `SW`, `IN`, `OUT`, `pickable`, `open`, `locked`, `hidden`, `container`, `volume`, `weight`.
  - **Operators & Comments**: Math and logical operators (`=`, `<>`, `<`, `>`, `<=`, `>=`, `+`, `-`, `*`, `/`, `AND`, `OR`, `NOT`, `MOD`), line comments (`'` and `REM`), and double-quoted strings (`"..."`).

---

## How to Install in Notepad++

Choose either of the two installation methods below:

### Method 1: Via the Notepad++ Graphical Interface (Recommended)

1. Open **Notepad++**.
2. From the main menu, navigate to:  
   **Language** → **User Defined Language** → **Define your language...**
3. In the dialog that opens, click the **Import...** button.
4. Browse to this directory and select:
   - [`dimx_udl_notepadpp.xml`](dimx_udl_notepadpp.xml) (if using the standard light interface), or
   - [`dimx_udl_notepadpp_dark.xml`](dimx_udl_notepadpp_dark.xml) (if using Notepad++ Dark Mode).
5. Notepad++ will show a message: *Import successful*.
6. Close the *User Defined Language* dialog.
7. Open or reopen any `.dxw` or `.dxs` file. The language **DimensioneX DXW** will be selected automatically.

### Method 2: Direct File Placement (Manual)

If you prefer placing the file directly without opening the dialog:

1. Open Windows File Explorer or PowerShell and copy the XML file into your Notepad++ user languages directory:
   ```cmd
   copy dimx_udl_notepadpp.xml "%APPDATA%\Notepad++\userDefineLangs\dimx_udl_notepadpp.xml"
   ```
   *(For Dark Mode, copy `dimx_udl_notepadpp_dark.xml` instead).*
2. If Notepad++ is currently running, restart it.
3. Open any `.dxw` file and syntax highlighting will be active.

---

## Customizing Colors

You can easily adjust any colors or fonts to match your personal theme:
1. Go to **Language** → **User Defined Language** → **Define your language...**
2. In the **User language** dropdown at the top, select **DimensioneX DXW** (or **DimensioneX DXW (Dark)**).
3. Switch through the tabs (**Folder & Default**, **Keywords Lists**, **Comment & Number**, **Operators & Delimiters**) and click **Styler** to customize colors, bold, or italic settings. Changes take effect in real time.
