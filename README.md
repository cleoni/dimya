# DimensioneY (DimY) Multiplayer Game Engine

Modern PHP 8 multiplayer virtual world and interactive adventure game engine, ported from an old project named **DimensioneX**.
In this PHP software, code-named DimensioneY, we will make changes with respect to the original DimensioneX.
DimensioneY should be able to load old files designed to run on DimensioneX, and keep its behaviour, up to a certain extent.

---

## Architecture & Specifications Documentation

Comprehensive technical documentation and formal specifications reside in the [`docs/`](docs/) directory:

- [**docs/DXW_SPECIFICATION.md**](docs/DXW_SPECIFICATION.md) — **Official DimensioneX World Definition (.DXW) Language Specification** derived from the Developer's Reference (pp. 37–70). Covers all sections (`WORLD`, `GUI`, `ROOMS`, `LINKS`, `CHARACTERS`, `ITEMS`, `SETS`, `SCRIPTS`), control panels, attribute semantics, physical simulation, image show modes, and EBNF grammar.
- [**docs/COMMANDS.md**](docs/COMMANDS.md) — **Game Engine Command Specification & API Reference**. Details all core commands (`click`, `go`, `rotate`, `look`, `use`, `use2`, `open`, `close`, `pick`, `drop`, `search`, `hide`, `put`, `give`, `enter`, `exit`, `say`, `save`, `login`, `logout`), event bindings, parameter handling, and custom panel buttons.
- [**docs/CLASS_HIERARCHY.md**](docs/CLASS_HIERARCHY.md) — Complete class hierarchy mapping, Mermaid diagrams, subsystem breakdowns, and catalog of all 46 core engine classes.
- [**docs/uml_explorer.html**](docs/uml_explorer.html) — Standalone, interactive HTML UML explorer with dynamic search, subsystem filters, and class inspector.
- [**docs/uml_specifications.puml**](docs/uml_specifications.puml) — Full PlantUML source code for rendering in PlantUML or IDE UML tools.
- [**docs/dxw-examples/**](docs/dxw-examples/) — Reference `.dxw` world definition files:
  - `world1.dxw` — Minimal introductory world
  - `world2.dxw` — Minimal adventure with linked rooms, 1 character, events
  - `world3net.dxw` — The Beach (full multiplayer environment with multi-directional views)
  - `fourinline.dxw` — Turn-based board game implemented entirely in DXW / SmallBasic

---

## Implementation Principles & Type Handling

- **Loose Scalar Typing**: In accordance with the DimensioneX design and PHP idiomatic behavior, **no scalar values in the game are strictly typed**. Attributes and properties (`int`, `bool`, `string`, `float`) are evaluated and coerced dynamically based on runtime context (e.g., `open=0` coerces cleanly to boolean `false`, `pickable` or `pickable=1` to boolean `true`, and numbers to their appropriate integer or string representations).
- **Native Data Structures**: Java `Dict` / `DictSorted` collections are represented via native PHP associative arrays with case-insensitive property lookup.
- **Persistent State**: Managed by `Dimx\PersistenceManager` with MySQL (PDO) support and transparent JSON file-based fallback.

---

## PHP Engine Quick Start

The PHP engine resides in [`php/`](php/):

```bash
# Direct access via Apache/XAMPP:
http://localhost/dimya/php/

# Or via PHP built-in server:
cd php
php -S localhost:8000
```

- **Game Client**: `http://localhost:8000/` or `http://localhost:8000/multiplayer.php`
- **Maintenance Console**: `http://localhost:8000/maint.php`
- **Configuration**: [`php/config.php`](php/config.php)
