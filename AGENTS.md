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
- When in doubt regarding SmallBasic syntax and instructions, consult [`docs/SMALLBASIC.md`](docs/SMALLBASIC.md). For base events, commands, etc., consult [`docs/COMMANDS.md`](docs/COMMANDS.md), [`docs/dimensionex-developers-reference-717.pdf`](docs/dimensionex-developers-reference-717.pdf), and the original Java classes in [`docs/dimx-classes/`](docs/dimx-classes/). DimensioneY is derived from DimensioneX, and original behavior must be preserved to a certain extent. Mapping from DimensioneX classes to DimensioneY classes is advisable.
- **Do NOT re-introduce intentionally dropped features**:
  - `Cluster` / clustering features.
  - `Dict` / `DictSorted` classes (replaced with PHP native arrays and built-in types).

## DimX vs DimY Documentation Principle
- Always keep clear in the docs what is **DimensioneY (implemented in PHP)** and what is **DimensioneX only** (original Java behaviour, target behaviour not implemented yet, or intentionally dropped). Never describe DimX-only features as if DimY supports them.
- When documenting something valid only for DimensioneX, state it explicitly (e.g. "DimX only", "not yet implemented in DimY").
- Historical DimX material lives under the README section "Historical DimensioneX Specifications"; DimY class docs are in `docs/CLASS_HIERARCHY.md`.
