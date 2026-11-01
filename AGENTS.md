# DimensioneY Multiplayer Game Engine written in PHP

Multiplayer virtual world and interactive adventure game engine.

## Architecture & Class Hierarchy Documentation

The software has been generated starting from a previous multiplayer game engine named DimensioneX.

Detailed documentation and diagrams have been generated and saved to the [`docs/`](docs/) directory:

- [**docs/SMALLBASIC.md**](docs/SMALLBASIC.md) — Definitive SmallBasic language reference (types, operators, statements, and complete catalog of all 55 built-in functions).
- [**docs/COMMANDS.md**](docs/COMMANDS.md) — Complete game engine commands reference, built-in verbs, event models, and execution semantics.
- [**docs/SKINS.md**](docs/SKINS.md) — Complete skins and `.dxs` specification, directive rules, media directories, and attribute reference.
- [**docs/CLASS_HIERARCHY.md**](docs/CLASS_HIERARCHY.md) — Class hierarchy of **DimensioneY (this PHP implementation)**, same structure as the DimX one for comparison.
- [**docs/DIMX_CLASS_HIERARCHY.md**](docs/DIMX_CLASS_HIERARCHY.md), [**docs/uml_explorer.html**](docs/uml_explorer.html), [**docs/uml_specifications.puml**](docs/uml_specifications.puml) - **Historical DimensioneX (Java) only** (not DimensioneY): original class hierarchy, interactive UML explorer and PlantUML source. They do not describe the PHP code.
- [**docs/dimensionex-developers-reference-717.pdf**](docs/dimensionex-developers-reference-717.pdf) — DimensioneX Developer's Reference (v7.1.7).
- [**docs/dimx-classes/**](docs/dimx-classes/) — Original DimensioneX source classes (Java), reference for engine behavior and class mapping.

## Implementation Recommendations & Rules
- NO scalar values in the game have to be strict typed. They all must be handled like PHP does, as int, bool or string depending on the context.
- **Event Model & Special Script Variables (§4.1.3 & §4.1.4, pp. 102–103)**:
  - SmallBasic event scripts rely fundamentally on the four special variables: **`$OWNER`**, **`$AGENT`**, **`$TARGET`**, and **`$WORLD`**.
  - `$OWNER`: Object to which the event is directly associated (e.g. in `surfer.onLook`, surfer is `$OWNER`).
  - `$AGENT`: Object/Character that caused the action (e.g. in `surfer.onLook`, the player looking is `$AGENT`).
  - `$TARGET`: Secondary object, destination link/room, or situational string parameter (e.g. in `key.onUseWith`, the door being unlocked; in `onLook`, `"explicit"` if via Look button or `""` if via click; in `onLooseItem`, the item leaving the container).
  - `$WORLD`: The global world object representing the game world environment and properties (e.g. `$WORLD.mode`, `$WORLD.name`).
  - **Return Values & Action Cancellation (§4.1.4)**: Event scripts default to returning success (`true` / `1`). In cancellable events (`onExit`, `onLoose`, `onLooseItem`, `onLook`, `onSave`, `onSearch`, `beforeOpen`, `Living`, etc.), returning `0` or `false` via `Return` aborts and cancels the action.
- **UIhtml Main Container IDs (`php/src/UIhtml.php`)**:
  - When referring to UI sections, use their HTML `id` selectors:
    - **`#scene`** (formerly `dimx-cell-a`): Main left/top game area containing the title bar and the inner split (`#display` and `#dashboard`).
    - **`#display`** (formerly `dimx-subcell-d`): Left pane inside `#scene`, displaying the room image, on-screen objects/characters, and `#consoleBox` (room description and action output).
    - **`#dashboard`** (formerly `dimx-subcell-e`): Right pane inside `#scene`, containing `#navpad` (directional compass/rotation pad), room items/people lists, inventory, and player stats.
    - **`#ctrls`** (formerly `dimx-cell-b`): Command controls panel area (action buttons, speech textbox `#txtBox`, command prompt `#cmdPrompt`).
    - **`#msgs`** (formerly `dimx-cell-c`): Bottom incoming chat and messages panel (containing `#chatBox`).
- When in doubt regarding SmallBasic syntax and instructions, consult [`docs/SMALLBASIC.md`](docs/SMALLBASIC.md). For base events, commands, etc., consult [`docs/COMMANDS.md`](docs/COMMANDS.md), [`docs/dimensionex-developers-reference-717.pdf`](docs/dimensionex-developers-reference-717.pdf), and the original Java classes in [`docs/dimx-classes/`](docs/dimx-classes/). DimensioneY is derived from DimensioneX, and original behavior must be preserved to a certain extent. Mapping from DimensioneX classes to DimensioneY classes is advisable.
- **Do NOT re-introduce intentionally dropped features**:
  - `Cluster` / clustering features.
  - `Dict` / `DictSorted` classes (replaced with PHP native arrays and built-in types).
  
## Debugging Tools and Recommendations
- In order to inspect the world's inner status it is useful to use the **Snapshot** feature of the maintenance console: HTTP post to `maint.php` while passing as HTTP parameters `action="snapshot"` and `adminPasswd` set to the current Admin password as defined in `config.php`.
- In order to understand the EVENTS code chain it is useful to inspect the **`debug.log`** file that can be found in the game engine folder. If `debug.log` file is missing, then it must be enabled by setting `debugTo=file` in `config.php`.
- A good way to explore a loaded world as a bot is using the **UIjson** interface that provides extra information normally not visible to players (hidden items, internal inspect attributes, etc.). Note that regular bots receive the fair-play **UIbot** view; to get diagnostic UIjson payloads, the bot's account name must be listed in the 'testBots' setting in `config.php`.
- **Automated Test Suite (`test_multiplayer.php`)**: When developing or fixing features, run the regression test suite from the CLI. You can target specific areas without running all 47+ sections:
  - Run filtered tests: `php test_multiplayer.php --filter=<keyword>` (e.g. `--filter=auth`, `--filter=Speak`, `--filter=open`).
  - Run specific sections: `php test_multiplayer.php --section=14` or `--section=15-18`.
  - List all test sections: `php test_multiplayer.php --list`.
- **Standalone Bot Testing (`bot.php`)**: To test end-to-end HTTP bot gameplay and command interactions via cURL, use `php bot.php`. Request and response payloads are logged to `conversation.log`.
- **Maintenance Actions (`maint.php`)**: Other useful maintenance actions via POST with `adminPasswd`:
  - `action="reset_world"`: Invalidates active player sessions and forces re-seeding the world from the `.dxw` file.
  - `action="clear_file_cache"`: Flushes cached world state files (`php/data/*.json`, `*.bin`, `*.jsonl`).
  - `action="clear_chat"`: Clears chat history in DB or `chat_main.jsonl`.


## DimX vs DimY Documentation Principle
- Always keep clear in the docs what is **DimensioneY (implemented in PHP)** and what is **DimensioneX only** (original Java behaviour, target behaviour not implemented yet, or intentionally dropped). Never describe DimX-only features as if DimY supports them.
- When documenting something valid only for DimensioneX, state it explicitly (e.g. "DimX only", "not yet implemented in DimY").
- Historical DimX material lives under the README section "Historical DimensioneX Specifications"; DimY class docs are in `docs/CLASS_HIERARCHY.md`.
