# DimensioneY Multiplayer Game Engine written in PHP

Multiplayer virtual world and interactive adventure game engine.

## Architecture & Class Hierarchy Documentation

The software has been generated starting from a Java multiplayer game engine named DimensioneX

Detailed documentation and diagrams have been generated and saved to the [`docs/`](docs/) directory:

- [**docs/CLASS_HIERARCHY.md**](docs/CLASS_HIERARCHY.md) — Complete class hierarchy mapping, Mermaid diagrams, subsystem breakdowns, and catalog of all 46 classes with line references.
- [**docs/uml_explorer.html**](docs/uml_explorer.html) — Standalone, interactive HTML UML explorer with dynamic search, subsystem filters, and class inspector.
- [**docs/uml_specifications.puml**](docs/uml_specifications.puml) — Full PlantUML source code for rendering in PlantUML or IDE UML tools.

## Source Code Location
All Java source files reside in package `cleoni.adv`:
- [`WEB-INF/classes/cleoni/adv/`](WEB-INF/classes/cleoni/adv/)

## Implementation Recommendations
- NO scalar values in the game have to be strict typed. They all must be handled like PHP does, as int, bool or string depending on the context.