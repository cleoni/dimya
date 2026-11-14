# DimensioneX SmallBasic Language Reference Manual

> **Reference Source**: DimensioneX Developer's Reference (v7.1.7), Chapter 3 (pp. 71–100).  
> This specification is the authoritative reference for all SmallBasic language constructs, instructions, functions, types, and event scripts in DimensioneY.

> [!IMPORTANT]
> **DimX vs DimY**: this document is the **DimensioneX (Java) SmallBasic language reference**. DimensioneY (PHP) implements only a **subset** of it. Items that DimY does not fully implement are tagged inline with **[DimX only - not yet implemented in DimY]**, **[DimY: partial - ...]** or **[Dropped in DimY]**. Untagged items are implemented in DimY (verified against `php/src/ActionsRunner.php`, `Event.php`, `World.php`, `DxwLoader.php`).
>
> The DimY interpreter ([ActionsRunner.php](../php/src/ActionsRunner.php)) is line-based: unknown statements are silently ignored (no error).

### DimY implementation status

| Area | Item | DimY status |
|---|---|---|
| Directive | `Include` | Not implemented (DimX only) |
| Structure | `Sub` / `Function` | Fully supported: formal parameter binding, custom functions in expressions, return values, and recursive calls |
| Structure | `input()` set | Partial: passed to the runner but not accessible from scripts |
| Structure | `HOOKS` | Partial: stored as world property `hooks`; dispatch of external commands to scripts not implemented |
| Types | Image / Set / Array objects in scripts | Not implemented (world `SET` declarations are loaded; only `RndSet` reads them) |
| Operators | `^`, `Mod`, `&`, unary `-`, `><`, arithmetic precedence | Not implemented |
| Operators | `+ - * /` | Partial: single binary split on first operator; no precedence/parentheses |
| Operators | `AND`, `OR`, `Not` | Partial: `Not` only as prefix inside conditions; `AND`/`OR` split at first top-level keyword |
| Variables | Script variables (global/local), `Dim` | Not implemented (only `object.property` assignment and reading) |
| Flow control | `For ... Next`, `For Each ... Next` | Not implemented |
| Flow control | `If / Else / End_If` | Implemented (nested; no `ElseIf`, as in DimX) |
| Statements | `AttachEvent`, `Ban`, `Dim`, `Kill`, `NewRoom`, `SaveSetting`, `SetAdd`, `SetRemove`, `SendPage`, `Journal` | Not implemented (silently ignored) |
| Statements | `MoveOutside` | Dropped in DimY (Cluster) |
| Statements | `Call`, `Goal`, `Move`, `Print`, `PrintRight`, `Return`, `SetPanel`, `Reset`, `RefreshView`, `SendCmd` | Partial / Implemented (see inline notes) |
| Statements | `Speak`, `Display`, `PlaySound`, `BroadcastSound`, `PlayBackground`, `BroadcastBackground`, `Debug`, `DropItems` | Implemented |
| Functions | All 55 built-ins except `RndSet`, `RndInt` (2-arg form only), `NOT` (partial) | Not implemented (see per-function tags) |
| Dropped | `Cluster`, `Dict` / `DictSorted` | Dropped in DimY (native PHP arrays are used instead) |
| DimY extras | `Broadcast`, `Open`, `Close`, `Search`, `Give` statements | DimY-only, not part of the DimX reference below |

---

## Table of Contents

- [3.1 Introduction](#31-introduction)
- [3.2 Notes about SmallBasic Code — Very Important!](#32-notes-about-smallbasic-code--very-important)
- [3.3 Include](#33-include)
- [3.4 Script Code Structure: Sub, Function, EVENT](#34-script-code-structure-sub-function-event)
  - [3.4.1 Sub](#341-sub)
  - [3.4.2 Function](#342-function)
  - [3.4.3 EVENT](#343-event)
  - [3.4.4 Example & HOOKS](#344-example--hooks)
- [3.5 Types](#35-types)
  - [3.5.1 Simple Types](#351-simple-types)
  - [3.5.2 Complex Types](#352-complex-types)
- [3.6 Operators](#36-operators)
  - [3.6.1 Arithmetical](#361-arithmetical)
  - [3.6.2 String](#362-string)
  - [3.6.3 Comparison](#363-comparison)
  - [3.6.4 Logical](#364-logical)
  - [3.6.5 Operator Precedence](#365-operator-precedence)
- [3.7 Variables and Properties](#37-variables-and-properties)
  - [3.7.1 Defined](#371-defined)
  - [3.7.2 Declaring Variables](#372-declaring-variables)
  - [3.7.3 Special Variables](#373-special-variables)
- [3.8 Constructs & Flow Control](#38-constructs--flow-control)
  - [3.8.1 Assignment](#381-assignment)
  - [3.8.2 If ... Else ... End_If](#382-if--else--end_if)
  - [3.8.3 For ... To ... Next](#383-for--to--next)
  - [3.8.4 For Each ... In ... Next](#384-for-each--in--next)
- [3.9 Instructions (Statements / Actions)](#39-instructions-statements--actions)
  - [3.9.1 AttachEvent](#391-attachevent)
  - [3.9.2 Ban](#392-ban)
  - [3.9.3 Call](#393-call)
  - [3.9.4 Dim](#394-dim)
  - [3.9.5 Debug](#395-debug)
  - [3.9.6 Display](#396-display)
  - [3.9.7 DropItems](#397-dropitems)
  - [3.9.8 For](#398-for)
  - [3.9.9 For Each](#399-for-each)
  - [3.9.10 Goal](#3910-goal)
  - [3.9.11 Journal](#3911-journal)
  - [3.9.12 Kill](#3912-kill)
  - [3.9.13 Move](#3913-move)
  - [3.9.14 MoveOutside](#3914-moveoutside)
  - [3.9.15 NewRoom](#3915-newroom)
  - [3.9.16 PlaySound](#3916-playsound)
  - [3.9.17 PlayBackground](#3917-playbackground)
  - [3.9.18 Print](#3918-print)
  - [3.9.19 PrintRight](#3919-printright)
  - [3.9.20 Reset](#3920-reset)
  - [3.9.21 RefreshView](#3921-refreshview)
  - [3.9.22 Return](#3922-return)
  - [3.9.23 SaveSetting](#3923-savesetting)
  - [3.9.24 SendCmd](#3924-sendcmd)
  - [3.9.25 SetAdd](#3925-setadd)
  - [3.9.26 SetPanel](#3926-setpanel)
  - [3.9.27 SetRemove](#3927-setremove)
  - [3.9.28 SendPage (formerly UseView)](#3928-sendpage-formerly-useview)
  - [3.9.29 Speak](#3929-speak)
- [3.10 Functions](#310-functions)
  - Catalog of 55 Built-in Functions (Abs to UCase)

---

## 3.1 Introduction

SmallBasic is the scripting language used to extend and enhance the DimensioneX game engine's default behaviour.
Scripts are contained in the `SCRIPTS` section of a game world file (`.dxw`) or in included external script files (`.dxl`).

SmallBasic's syntax is very similar to Microsoft VBScript and Visual Basic, but tailored and optimized for interactive multiplayer adventures:
- If you are familiar with VBScript or Visual Basic, you will find no difficulties.
- SmallBasic is even simpler than Visual Basic, making it accessible to beginners while providing powerful constructs for world builders.

---

## 3.2 Notes about SmallBasic Code — Very Important!

1. **One instruction per line**: SmallBasic instructions are written one per line and terminated by a carriage return / newline. Multiple instructions cannot be placed on the same line.
2. **Multi-line strings**: The only exception to single-line instructions is string literals enclosed in quotes, which may contain any number of carriage returns / line breaks.
3. **Comments**: Comments begin with a single quote character (`'`). The rest of the line following `'` is ignored by the parser. It is recommended to place comments on their own line:
   ```basic
   ' This is a SmallBasic comment
   ```

---

## 3.3 Include

> **[DimX only - not yet implemented in DimY]** `Include` directives are not processed by `DxwLoader`.

The `Include` directive loads script code from an external file:

```basic
Include "filename_or_url"
```

- **File parameter**: Must be enclosed in quotes.
  - Can be an absolute URL (e.g. `"https://www.myserver.com/folder/file.dxl"`).
  - Can be a relative path (e.g. `"library.dxl"`), relative to the directory containing the main `.dxw` file.
- External files can contain additional nested `Include` directives (beware of circular recursion).
- *Note*: Script includes are processed during script loading.

---

## 3.4 Script Code Structure: Sub, Function, EVENT

SmallBasic scripts are organized into three block types: **`Sub`**, **`Function`**, and **`EVENT`**.

### 3.4.1 Sub

Defines a custom subroutine/procedure that can be called from scripts via `Call subroutineName` or directly `subroutineName(arg1, arg2)`.

```basic
Sub subroutineName [( parameter1 [, parameter2, ... ] )]
    ' code lines here
End_Sub
```

- Formal parameters are bound to passed positional arguments within the procedure's local variable scope.
- The special variables `$OWNER`, `$AGENT`, and `$TARGET` are implicitly shared with the subroutine.
- Calling syntax:
  ```basic
  Call mySub
  Call mySub(arg1, arg2)
  mySub(arg1, arg2)
  ```

### 3.4.2 Function

Defines a custom function that calculates and returns a value using the `Return` statement. Custom functions can be called directly inside expressions (including arithmetic, logical conditions, and string concatenations) and as standalone statements.

```basic
Function functionName [( parameter1 [, parameter2, ... ] )]
    ' code lines here
    Return result
End_Function
```

- Formal parameters are bound to positional arguments within the function's local scope.
- Return values are evaluated and returned to the caller or enclosing expression.
- Supports recursive calls (e.g. searching hierarchical containers).
- The special variables `$OWNER`, `$AGENT`, and `$TARGET` are implicitly shared with the function.
- Example:
  ```basic
  Function addValues(a, b)
      Return a + b
  End_Function

  EVENT onNew
      Dim retval = addValues(2, 3)
      Display "Result is = " + retval
  End_EVENT
  ```

### 3.4.3 EVENT

Defines script code that is **automatically executed by the game engine** when a specific game event occurs.

```basic
EVENT eventID
    ' code lines here
End_EVENT
```

- `eventID` must be:
  1. A standard engine event (e.g. `onStart`, `onLook`, `onEnter`, `whenPicked`, `Living`, `onDie`).
  2. An object-qualified event (e.g. `switch.onLook`, `apple.whenPicked`, `surfer.onLook`).
  3. A custom panel button event defined in a custom `PANEL` definition.
- The special variables **`$OWNER`**, **`$AGENT`**, and **`$TARGET`** are automatically bound by the engine.
- An input set named **`input()`** is automatically built by the system, containing parameters collected from player input or external queries.

### 3.4.4 Example & HOOKS

> **[DimY: partial]** `HOOKS` is stored only as the world property `hooks`; mapping HTTP commands to script functions is not yet implemented in DimY. `getCharactersIn` used below is also not implemented.

DimensioneX supports `HOOKS` to accept external HTTP commands and trigger event handlers:

```basic
GUI
    HOOKS
        findobj = doFindObj  ' When command "findobj" is received, invoke doFindObj()
END_GUI

SCRIPTS
    Function doFindObj(obj)
        Dim c, found = 0
        For Each c In getItemsIn($WORLD)
            If InStr(c.name, obj)
                found = 1
                Print $AGENT, "<LI>" + c.name + " is in: " + c.container.name
            End_If
        Next
        For Each c In getCharactersIn($WORLD)
            If InStr(c.name, obj)
                found = 1
                Print $AGENT, "<LI>" + c.name + " is in: " + c.container.name
            End_If
        Next
        If Not(found)
            Print "Not found: '" + obj + "'"
        End_If
    End_Function
END_SCRIPTS
```

- External invocation URL:
  `http://localhost:8080/dimx/servlet/multiplayer?game=1&cmd=findobj&obj=sword`

---

## 3.5 Types

SmallBasic is dynamically typed. An expression can be treated as a number, string, or boolean depending on context.

### 3.5.1 Simple Types

- **Numeric**: Integer and floating-point values (e.g. `42`, `3.14159`).
- **String**: Text enclosed in double or single quotes (e.g. `"hello"`, `'world'`).
- **Boolean**: Represented numerically:
  - `1` = `True`
  - `0` = `False`
  - Any non-zero numeric value evaluates to `True`.

### 3.5.2 Complex Types

> **[DimY: partial]** `Image` objects (`NewImage`) are implemented. Collection objects (`NewSet`, `NewArray`, `Split`) are **[DimX only - not yet implemented in DimY]**; only world-level `SET` declarations exist (used by `RndSet`). `Dict`/`DictSorted` were **dropped in DimY** (native PHP arrays).

- **Image**: An image object (created via `NewImage(src, width, height)` or defined in `.dxw`).
- **Set**: Key-value dictionary / collection (created via `NewSet()`, `SET` declarations, or functions like `getItemsIn()`).
- **Array**: Sequential indexed collection (created via `NewArray()`, `Split()`, or `ARRAY` declarations).

---

## 3.6 Operators

### 3.6.1 Arithmetical

> **[DimY: partial - only `+ - * /`]** Single binary split, no precedence. `^`, `Mod` and unary `-` are **[DimX only - not yet implemented in DimY]**.

Binary operators (in descending precedence):
- `^` : Exponentiation (e.g. `2 ^ 3` = `8`).
- `*`, `/`, `Mod` : Multiplication, division, modulus remainder.
- `+`, `-` : Addition, subtraction.

Unary operators:
- `-` : Unary negation (e.g. `a = -1`).

### 3.6.2 String

> **[DimY: partial]** `+` concatenates when an operand is non-numeric. `&` is **[DimX only - not yet implemented in DimY]**.

- `&` : Concatenates two operands as strings, regardless of their types (e.g. `"Score: " & 100` -> `"Score: 100"`).
- `+` : When the first operand is a string, `+` performs string concatenation. If the first operand is numeric, `+` performs arithmetical addition.

### 3.6.3 Comparison

> **[DimY: partial]** `<>`, `<`, `>`, `=`, `<=`, `>=` implemented (loose PHP comparison). `><` is **[DimX only - not yet implemented in DimY]**.

Binary comparison operators returning boolean (`1` or `0`):
- `<` : Less than
- `>` : Greater than
- `=` : Equal to
- `<=` : Less than or equal to
- `>=` : Greater than or equal to
- `<>` or `><` : Not equal to

### 3.6.4 Logical

> **[DimY: partial]** `AND`/`OR` implemented (split at first top-level keyword); `Not` only as a prefix in conditions.

- `AND` : Logical conjunction (`expr1 AND expr2`).
- `OR` : Logical disjunction (`expr1 OR expr2`).
- `Not( expr )` : Logical negation (implemented as a built-in function, requires parentheses).

### 3.6.5 Operator Precedence

> **[DimY: partial]** Only `OR` < `AND` < comparison plus outer-parenthesis unwrapping in conditions; arithmetic precedence and `.` precedence are **[DimX only - not yet implemented in DimY]**.

Precedence hierarchy from lowest (1) to highest (6):
1. `AND`, `OR` (lowest)
2. `=`, `<`, `>`, `<>`, `<=`, `>=`
3. `+`, `-`
4. `*`, `/`, `Mod`, `^`
5. `.` (Property indirection / member access)
6. Unary `-` (highest)

Parentheses `(` and `)` can be used to explicitly override precedence:
```basic
a = 3 + 2 * 5    ' Evaluates to 13 (multiplication first)
a = (3 + 2) * 5  ' Evaluates to 25 (parentheses first)
```

---

## 3.7 Variables and Properties

### 3.7.1 Defined

- **Variables**: Stored values in memory. Global variables can be considered properties of the `$WORLD` object.
- **Properties**: Object attributes (e.g. `surfer.health`, `box.open`).
- Variables and properties do not require pre-declaration; they are created automatically when first assigned.
- Access syntax:
  - Direct variable: `Print mode`
  - Object property: `Print obj.name`

### 3.7.2 Declaring Variables

> **[DimX only - not yet implemented in DimY]** `Dim` and script variables (local/global) are not implemented; only `object.property` can be assigned/read.

- Local variables inside a `Function`, `Sub`, or `EVENT` should be declared with **`Dim`**.
- Local variable scope is restricted to the enclosing block, preventing naming conflicts and protecting recursive calls.
- Syntax:
  ```basic
  Dim temp
  Dim counter = 0
  Dim x = 10, y = 20, label = "Score"
  ```

### 3.7.3 Special Variables

> **[DimY: partial]** `$OWNER`, `$AGENT`, `$TARGET`, `$WORLD`, `True`, `False` implemented. Null-object rules #1/#2 below are **[DimX only - not yet implemented in DimY]** (unresolved objects are silently skipped).

Special read-only variables automatically managed by the engine:

| Variable | Description |
|---|---|
| **`$OWNER`** | The object to which the event is directly associated (e.g. in `switch.onLook`, `$OWNER` is `switch`). |
| **`$AGENT`** | The character or player that caused or triggered the action. |
| **`$TARGET`** | The secondary target object, destination room/link, or situational string parameter. |
| **`$WORLD`** | The global world aggregate object. |
| **`True`** | Boolean constant `1`. |
| **`False`** | Boolean constant `0`. |

> [!IMPORTANT]
> - **Rule #1**: Reading a property from a `NULL` object returns `NULL` and logs a warning.
> - **Rule #2**: Attempting to assign to a property of a `NULL` object produces a runtime error.

---

## 3.8 Constructs & Flow Control

### 3.8.1 Assignment

> **[DimY: partial]** Only `object.property = expr` and `object.property("key") = expr` are supported; plain `variable = expr` is **[DimX only - not yet implemented in DimY]** (ignored).

```basic
[object.]property = expression
variable = expression
```

Examples:
```basic
temp = 1
surfer.health = surfer.health - 1
$AGENT.container.name = "Mystery Island"
```

### 3.8.2 If ... Else ... End_If

Conditional execution block:

```basic
If condition
    ' statements executed if condition is true
Else
    ' optional statements executed if condition is false
End_If
```

*Note*: `ElseIf` is not a single compound keyword in SmallBasic; nested `If ... Else ... End_If` constructs are used.

### 3.8.3 For ... To ... Next

Counter iteration loop:

```basic
For idx = startVal To endVal
    ' code block executed for each value
Next
```

Example:
```basic
For i = 1 To 5
    Print $AGENT, "Step #" + i
Next
```

*Note*: Iteration executes from `startVal` to `endVal` (with optional `Step` increment). When server logging is enabled (`debugTo="file"`), each iteration logs `Looping. Index= <i>` to `debug.log`.

### 3.8.4 For Each ... In ... Next

Collection iteration loop over arrays or sets:

```basic
For Each key In collection
    ' key takes each element/key in collection
Next
```

Example:
```basic
For Each item In getItemsIn($OWNER.container)
    If Not(item.hidden) And item.Cash > 0
        tobepicked = item
        Display $WORLD, $OWNER.name + " sees " + item.name
    End_If
Next
```

---
## 3.9 Instructions (Statements or Actions)

Instructions are statement verbs telling the game engine what action to perform.
Parameters specify *how* the action is performed. Parameter meanings and availability depend closely on the action being executed.

Instructions are presented here in numerical order (3.9.1 to 3.9.29):

---

### 3.9.1 AttachEvent

> **[DimX only - not yet implemented in DimY]** Statement is silently ignored (unknown verbs are no-ops in `ActionsRunner::executeStatement`).

Attaches an event-management script dynamically to an object.

```basic
AttachEvent dest, "eventID", "attachedEventID"
```

- **Parameters**:
  - `dest` *(Required)*: ID of the game object to which the event script is being attached. Must be a valid object (cannot be `$WORLD`).
  - `eventID` *(Required)*: Event ID that will be enabled on the target object (e.g. `"onEnter"`, `"whenPicked"`). Must be an unqualified ID (do not prefix with object name).
  - `attachedEventID` *(Required)*: Event identifier from which the script code is taken. When attaching code to players, a `$WORLD` event must be used. Passing an empty string (`""`) cancels and detaches the existing event handler.
- **Example**:
  ```basic
  ' Dynamically attach sunshine check to a vampire-infected character:
  AttachEvent person, "onEnter", "onEnter_Vampire"

  ' Cancel the event handler:
  AttachEvent person, "onEnter", ""
  ```

---

### 3.9.2 Ban

> **[DimX only - not yet implemented in DimY]** Statement is silently ignored (unknown verbs are no-ops in `ActionsRunner::executeStatement`).

Bans the specified player from the game.

```basic
Ban player
```

- **Parameters**:
  - `player` *(Required)*: ID of the player to be banned.

---

### 3.9.3 Call

Calls a custom `Sub` or `EVENT`, implicitly passing the current `$OWNER`, `$AGENT`, and `$TARGET` context and binding positional arguments to formal parameters.

```basic
Call subroutineName
Call subroutineName( [parameter1, parameter2, ...] )
```

- **Parameters**:
  - `subroutineName` *(Required)*: Identifier of the subroutine or event to call.
  - `parameter1, ...` *(Optional)*: Comma-separated arguments passed to the subroutine.

---

### 3.9.4 Dim

> **[DimX only - not yet implemented in DimY]** Statement is silently ignored (unknown verbs are no-ops in `ActionsRunner::executeStatement`).

Declares and creates one or more local variables in the local variable space of the current `Function`, `Sub`, or `EVENT`.

```basic
Dim variable
Dim variable = value
Dim var1, var2 = value, var3
```

- **Notes**:
  1. Any local variable declared with `Dim` is protected and safe from overwrite across recursive calls.
  2. Local variables are not visible from outside the procedure, nor from called functions/subroutines.
  3. Global variables cannot be declared with `Dim`; to create a global variable, simply assign to it directly without `Dim`.

---

### 3.9.5 Debug

Sends a debug message to the engine log (`debug.log` in the game engine's system folder, where `config.php` is located) by calling the game engine's `log()` method.

```basic
Debug message
```

- **Parameters**:
  - `message` *(Required)*: String expression or message to be logged.

---

### 3.9.6 Display

Synonym for `Print`. Displays a message or image on player consoles.

```basic
Display [dest,] message [, message2, ...]
```

- See [3.9.18 Print](#3918-print) for detailed parameters and behaviors.

---

### 3.9.7 DropItems

> **[DimY: implemented]** Forces the specified character or container object (typically a player or NPC) to drop all carried inventory items onto the floor of their enclosing container/room. Matches DimensioneX Java `ActionsRunner.actDropItems()`.

Forces the specified container object (typically a player or NPC) to drop all carried inventory items onto the floor of their current room.

```basic
DropItems [victim]
```

- **Parameters**:
  - `victim` *(Optional)*: ID or reference of the character/container object. If omitted, defaults to `$AGENT`. When items are dropped from a character or player, the notification `"Everything I was carrying just slipped from my hands!"` is displayed on their console.

---

### 3.9.8 For

Represents an iteration loop over a numeric range.

```basic
For idx = startVal To endVal
    ' loop statements
Next
```

- See [3.8.3 For ... To ... Next](#383-for--to--next).

---

### 3.9.9 For Each

Represents an iteration loop over elements of a set or array.

```basic
For Each key In set
    ' loop statements
Next
```

- See [3.8.4 For Each ... In ... Next](#384-for-each--in--next).

---

### 3.9.10 Goal

> **[DimY: implemented]** Broadcasts the goal message, posts a system chat message, adds 10 to the player's score, sets `$WORLD.mode = 0`, logs `Action: GOAL`, and records the completion event in the database (`dimx_goals` table, replacing DimX's `.hof` text file).

Signals that the game objective has been achieved and the adventure is completed.

```basic
Goal [message]
```

- **Effects**:
  1. Sends a global broadcast message announcing that the game has been completed, when, and by whom (uses default system message if `message` is omitted).
  2. Posts a system chat message announcing completion to all connected players.
  3. Increments the player's score by 10 points.
  4. Sets the global aggregate variable `$WORLD.mode = 0`.
  5. Records the completion event in the database (`dimx_goals` table; fallback to JSONL when offline). In DimX, this was written to a `.hof` (Hall of Fame) text file.
  6. Logs `Action: GOAL` to `debug.log` when debugging is enabled (`debugTo=file`).
- **Tip**: Testing `$WORLD.mode` is a standard way to verify whether an adventure is actively running (`mode = 1`) or in lobby/stopped state (`mode = 0`).

---

### 3.9.11 Journal

> **[DimX only - not yet implemented in DimY]** Statement is silently ignored. The cluster journal part is also **dropped in DimY** (Cluster removed).

Appends an entry to the top of the game or cluster RSS journal file (retaining the 20 most recent entries).

```basic
Journal title, link, text, categories
```

- **Parameters**:
  - `title` *(Required, String)*: Entry title (becomes RSS feed item title).
  - `link` *(Required, String)*: URL associated with the entry.
  - `text` *(Required, String)*: Entry body text. Can contain line breaks encoded as `\n`.
  - `categories` *(Required, String)*: Comma-separated category tags.
- **Example**:
  ```basic
  Journal "Restart", gameInfo("site"), "Game has been restarted.\nYou can connect and play now.", "news,system events"
  ```

---

### 3.9.12 Kill

> **[DimX only - not yet implemented in DimY]** Statement is silently ignored (unknown verbs are no-ops in `ActionsRunner::executeStatement`).

Removes an object or NPC character from the current world.

```basic
Kill [victim]
```

- **Parameters**:
  - `victim` *(Optional)*: ID of the character/object to be removed. Defaults to `$AGENT` if omitted.

---

### 3.9.13 Move

> **[DimY: partial]** Both arguments are required (the one-argument form defaulting `what` to `$AGENT` is not implemented). Only `AdvObject` instances can be moved.

Moves an object (character or item) to a new destination room or container.

```basic
Move [what,] where
```

- **Parameters**:
  - `what` *(Optional)*: Reference or ID of the object to move. Defaults to `$AGENT` if omitted.
  - `where` *(Required)*: Reference or ID of the destination room or item container.
- **Example**:
  ```basic
  Move apple, box
  Move $OWNER, destination
  ```

---

### 3.9.14 MoveOutside

> **[Dropped in DimY]** Depends on multi-world clusters, which were intentionally removed. Statement is silently ignored.

Moves an object to another virtual world within the same multi-world cluster.

```basic
MoveOutside what, areaid
```

- **Parameters**:
  - `what` *(Required)*: Object to move.
  - `areaid` *(Required)*: World ID of the destination area within the cluster.

---

### 3.9.15 NewRoom

> **[DimX only - not yet implemented in DimY]** Statement is silently ignored (unknown verbs are no-ops in `ActionsRunner::executeStatement`).

Dynamically instantiates a new room in the world.

```basic
NewRoom id
```

- **Parameters**:
  - `id` *(Required)*: Unique identifier for the new room. Properties such as `name`, `description`, and `image` are assigned subsequently via property assignments.

---

### 3.9.16 PlaySound

Plays a one-shot audio sound clip on the recipient's client (`UIhtml` embedded `<audio id="sndplayer">` for players who enabled **Sounds** at login; relative filenames are resolved against the World's `IMAGESFOLDER`).

```basic
PlaySound [dest,] soundFile
```

- **Parameters**:
  - `dest` *(Optional)*: Recipient player, room, or `$WORLD`. Defaults to `$AGENT` if omitted.
  - `soundFile` *(Required)*: Sound filename or path.

---

### 3.9.17 PlayBackground

Plays background music or an audio stream (optionally looping) on the recipient's client (`UIhtml` silent `<audio id="bgplayer">` controlled via JavaScript/jQuery `playBackgroundMusic(src, loop)` for players who enabled **Music** at login; relative filenames are resolved against the World's `IMAGESFOLDER`, and the background music is kept as a property of the target `Room`/`PeopleContainer` or `$WORLD`).

```basic
PlayBackground [dest,] soundFile [, loop]
```

- **Parameters**:
  - `dest` *(Optional)*: Target player, room, or `$WORLD`. Defaults to `$AGENT` if omitted.
  - `soundFile` *(Required)*: Audio filename or URL.
  - `loop` *(Optional)*: `1` to loop continuously, `0` to play once. Default is `0`.

---

### 3.9.18 Print

> **[DimY: partial]** Implemented (`Display`/`Print`). Both strings and `NewImage()` image objects are supported (images render to HTML `<img>` tags). Expressions are limited (see operators).

Outputs formatted messages or images directly to player consoles.

```basic
Print [dest,] message [, message2, ...]
```

- **Parameters**:
  - `dest` *(Optional)*: ID or reference of target player, room, or `$WORLD`. Defaults to `$AGENT` if omitted.
  - `message` *(Required)*: Message string or image object (created with `NewImage()`). When multiple messages are specified, they cycle sequentially each time the event executes.
- **Example**:
  ```basic
  Print "Welcome to the game!"
  Print NewImage("hero.gif", 64, 100)
  ```

---

### 3.9.19 PrintRight

> **[DimY: partial]** Routed to `displayRight()` only when the destination object provides it; otherwise falls back to `display()`.

Identical to `Print`, but routes message display into the right-side layout column (`#right_column`).

```basic
PrintRight [dest,] message [, message2, ...]
```

---

### 3.9.20 Reset

> **[Implemented in DimY]** Sets `$world->reset = true`, logged via `GameEngine::log("Action: RESET")`. When handled in `GameEngine::processCommand()`, triggers `GameEngine::resetWorld()`: the persistent world state and cache are cleared, the world definition is re-seeded fresh, and active player sessions are invalidated via reset token so players re-login.

Restarts/resets the game engine world state, re-seeding the world from definition and re-synchronizing connected players. An optional broadcast message may be passed to notify players before the restart.

```basic
Reset
Reset [broadcastMsg]
```

- **`broadcastMsg`** *(Optional)*: Message string to broadcast to connected players in the world before the reset.

---

### 3.9.21 RefreshView

> **[DimY: implemented]** Dispatches `refresh!scene` command to the destination target (room, player, or `$WORLD`). On UIhtml, the client polls `multiplayer.php?view=msgs` or receives `data-commands` to trigger `refreshScene()` and update `#scene` without a full page reload.

Forces an immediate client-side UI view refresh.

```basic
RefreshView [dest]
```

- **Parameters**:
  - `dest` *(Optional)*: Target player, room, or `$WORLD`. Defaults to `$AGENT`.

---

### 3.9.22 Return

> **[DimY: partial]** `Return [expr]` works in events (sets the event result). Returning values from user `Function`s into expressions is not supported (function calls in expressions are not implemented).

Terminates execution of the current procedure or event script and returns a value.

```basic
Return [retvalue]
```

- **Event semantics**:
  - Returning `1` (or `True`) signals successful execution.
  - In cancellable events (`onExit`, `onLooseItem`, `onSave`, `Living`, etc.), returning `0` (or `False`) aborts and cancels the action.

---

### 3.9.23 SaveSetting

> **[DimX only - not yet implemented in DimY]** Statement is silently ignored (unknown verbs are no-ops in `ActionsRunner::executeStatement`).

Persists a key-value setting into the global server profile database.

```basic
SaveSetting key, value
```

- **Parameters**:
  - `key` *(Required, String)*: Lookup key name.
  - `value` *(Required, String)*: Value to store. Retrieved via `getSetting(key)`.
- **Special System Keys**:
  - `hiscore`: Stores the highest score achieved.
  - `hiscoretext`: Summary description for the Hall of Fame view.
- **Example**:
  ```basic
  Dim hiscore = 0 + getSetting("hiscore")
  If king.Score > hiscore
      SaveSetting "hiscore", king.Score
      SaveSetting "hiscoretext", "Best score: " + king.Score + " by " + king.name
      Speak SYS, $WORLD, "The new champion is " + king.name
  End_If
  ```

---

### 3.9.24 SendCmd

> **[DimY: implemented]** Dispatches a client control command to the destination player, room occupants, or `$WORLD`. Handled by client-side `clientExecute` in UIhtml.

Dispatches a low-level engine control command to the player's client interface.

```basic
SendCmd [dest,] command
```

- **Standard commands**:
  - `"silence"`: Stops current background audio.
  - `"refresh!scene"`: Refreshes scene rendering.
  - `"refresh!ctrls"`: Refreshes control panel buttons.
- **Custom commands (3.9.24.1)**:
  - `"custom:popup!url,width=...,height=..."`: Opens a pop-up browser window.
  - `"custom:timed!command,interval"`: Schedules execution of client command after interval (ms).

---

### 3.9.25 SetAdd

> **[DimX only - not yet implemented in DimY]** Statement is silently ignored (unknown verbs are no-ops in `ActionsRunner::executeStatement`).

Appends or assigns a key-value entry to a `set` collection.

```basic
SetAdd set, key, value
```

- *Note*: An equivalent assignment is `set(key) = value`.

---

### 3.9.26 SetPanel

> **[DimY: partial]** Works for `$WORLD`, rooms/containers (`PeopleContainer`) and players; other destination types return false.

Sets the active command control panel for a player, a room, or globally for `$WORLD`.

```basic
SetPanel [destination,] panelId
```

- **Parameters**:
  - `destination` *(Optional)*: Target player, room, or `$WORLD`. If omitted, defaults to `$AGENT`.
  - `panelId` *(Required, String)*: ID of the declared panel (e.g. `"default"`, `"chat"`).
- **Notes**:
  - When targeted to `$WORLD`, triggers a panel refresh on all connected clients.
  - When targeted to a specific player, implicitly refreshes that player's controls.

---

### 3.9.27 SetRemove

> **[DimX only - not yet implemented in DimY]** Statement is silently ignored (unknown verbs are no-ops in `ActionsRunner::executeStatement`).

Removes an element from the specified `set` or `array`.

```basic
SetRemove set, key
```

- **Parameters**:
  - `set` *(Required)*: Collection to modify.
  - `key` *(Required)*: Key name (if set) or index position (if array) to remove.

---

### 3.9.28 SendPage (formerly UseView)

Assigns an alternative graphical view (declared as a `PAGE` in `GUI`) to a player or globally to `$WORLD`.

```basic
SendPage destination, pageid, interval
```

- **Parameters**:
  - `destination` *(Required)*: Target player or `$WORLD`.
  - `pageid` *(Required)*: Identifier of the `PAGE` defined in the `GUI` section.
  - `interval` *(Required)*: Duration in seconds before reverting to standard view (e.g. `1.5`). If `0`, the view remains active indefinitely until replaced.

---

### 3.9.29 Speak

Speaks a dialogue phrase or message sequence into the chat queue.

```basic
Speak [ [speaker], dest ] , message1 [, message2, ... ]
Speak message1 [, message2, ... ]
```

- **Parameters**:
  - `speaker` *(Optional)*: ID of the character sending the speech. If omitted, defaults to `$OWNER`. **If it does not refer to a `CHARACTER` object (e.g. an `Item` or `Room`), the system default character is used (`"a voice..."`, id `SYS`)**.
  - `dest` *(Optional)*: ID of the recipient object. Can be a player (private whisper), a room, or `$WORLD` (broadcast). If omitted, defaults to `$AGENT`.
  - `message` *(Required)*: Message text to display. When multiple messages are provided, they cycle sequentially on successive executions for that recipient.
- **Special Completion Marker `"*"`**:
  - The special message `"*"` signals speech completion. It does not output text to chat; instead, it automatically triggers the **`onSpeechFinish`** event on the `$OWNER` object.
- **Examples**:
  ```basic
  ' Compact form in NPC event (speaker is $OWNER "surfer", target is $AGENT):
  Speak "Hey, why are you staring at me?", "Please look elsewhere!"

  ' Compact form in Item event (speaker is non-character "switch", speaks as "a voice..."):
  Speak "USE this switch to start a new game!"

  ' Explicit broadcast:
  Speak SYS, $WORLD, "Game has begun... GOOD LUCK!"
  ```

---
## 3.10 Functions

Functions are statement expressions that evaluate one or more inputs and return a calculated result.
Unlike Instructions, Functions cannot be called as standalone statements; they must be evaluated within an expression, assigned to a variable, or passed as an argument to an instruction (such as `Print` or `Display`).

```basic
' Correct: Instruction uses the result of a function
Print getTime("HH:mm")

' Incorrect: Function cannot be called as a standalone instruction
getTime("HH:mm")
```

Below is the complete catalog of all 55 built-in SmallBasic functions (3.10.1 to 3.10.55):

---

### 3.10.1 Abs( n )

> **[DimY: implemented]** Returns the absolute value of numeric expression `n`.

Returns the absolute value of numeric expression `n`.

- **Syntax**: `result = Abs( n )`
- **Parameters**: `n` (Numeric).
- **Returns**: Positive numeric value $|n|$.

---

### 3.10.2 Asc( c )

> **[DimY: implemented]** Returns the ASCII integer code of the first character, or `-1` if the string is empty.

Returns the ASCII code of character `c`. If a string is provided, evaluates the first character.

- **Syntax**: `code = Asc( c )`
- **Parameters**: `c` (String).
- **See also**: `Chr()`

---

### 3.10.3 Chr( n )

> **[DimY: implemented]** Returns the character string corresponding to ASCII code `n`.

Returns the character corresponding to ASCII code `n` ($0 \le n \le 255$).

- **Syntax**: `char = Chr( n )`
- **Parameters**: `n` (Integer, $0 \dots 255$).
- **See also**: `Asc()`

---

### 3.10.4 Copy( array_or_set )

> **[DimY: implemented]** Produces a deep, independent copy of an `ARRAY` or `SET` collection.

- **Syntax**: `newCollection = Copy( array_or_set )`
- **Notes**: In SmallBasic, simple assignment `myCopy = myArr` copies the *reference* so mutations affect both. `Copy()` clones the elements individually.

---

### 3.10.5 Exists( objectID )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Checks if an adventure object with the specified identifier exists in the current world.

- **Syntax**: `result = Exists( objectID )`
- **Returns**: Boolean (`1` if exists, `0` otherwise).

---

### 3.10.6 ExistScript( scriptID )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Checks if a custom `Sub`, `Function`, or `EVENT` exists with the given name.

- **Syntax**: `result = ExistScript( scriptID )`
- **Parameters**: `scriptID` (String, must be in quotes).
- **Returns**: Boolean (`1` if defined, `0` otherwise).

---

### 3.10.7 Format( number, strFormat )

> **[DimY: implemented]** Formats a number according to a printf-style format pattern string.

Formats a number according to a format pattern string (following standard `String.format` / `printf` formatting).

- **Syntax**: `str = Format( number, strFormat )`
- **Example**:
  ```basic
  Print Format(12, "%04d")  ' Outputs: 0012
  ```

---

### 3.10.8 gameInfo( variable )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Returns runtime settings and paths of the game engine server.

- **Syntax**: `info = gameInfo( variable )`
- **Parameters**:
  - `"imagesfolder"`: Returns the active images directory URL.
  - `"navigator"`: Returns the main servlet/navigator endpoint URL.
  - `"site"`: Returns the game website (`WORLD.SITE`).
- **Example**:
  ```basic
  Print "Visit game site: " + gameInfo("site")
  ```

---

### 3.10.9 getCharactersIn( object )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Returns a `SET` of all `CHARACTER` objects (NPCs and players) contained inside the specified container/room.

- **Syntax**: `charSet = getCharactersIn( object )`
- **Example**:
  ```basic
  mySet = getCharactersIn($OWNER.container)
  ```

---

### 3.10.10 getLinksFrom( rooms )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Returns a `SET` of all exit links originating from the specified room or collection of rooms.

- **Syntax**: `linkSet = getLinksFrom( rooms )`
- **Parameters**: A room object, a set of rooms, or `$WORLD`.

---

### 3.10.11 getItemsIn( object )

Returns a `SET` of all `ITEM` objects directly contained in the specified container/room/character/world.

- **Syntax**: `itemSet = getItemsIn( object )`
- **Parameters**: `object` (Object reference, object string ID, `$WORLD`, `$AGENT`, `$OWNER`, `$TARGET`, or an `ARRAY`/`SET` of objects/IDs).
- **Returns**: A key-value set (`SET`) where keys are item IDs and values are `Item` objects.
- **Example**:
  ```basic
  myset = getItemsIn($OWNER.container)
  For Each item In myset
      If Not(item.hidden)
          Display $WORLD, $OWNER.name + " sees " + item.name
      End_If
  Next
  ```

---

### 3.10.12 getObject( id )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Finds and returns a reference to the game object with identifier `id`. If `id` is already an object reference, returns it unchanged. Returns `NULL` if not found.

- **Syntax**: `objRef = getObject( id )`

---

### 3.10.13 getObjectsSubtype( container, subtype )

Performs a subtype match search on item types. Returns an associative array (`SET`) of items inside `container` (or within a collection/array of items) whose `type` attribute contains the dotted subtype `subtype` (e.g. `type.subtype`, case-insensitive).

- **Syntax**: `itemSet = getObjectsSubtype( container, subtype )`
- **Parameters**:
  - `container`: Container object, room, character/player, item, container ID string, or collection/array of items.
  - `subtype`: String subtype part to match.
- **Returns**: An associative array (`SET`) of matching items indexed by object ID.

---

### 3.10.14 getObjectsType( container, type )

Searches for items inside `container` (or within an array/collection of items) by primary type prefix. Returns an associative array (`SET`) of matching items whose `type` attribute starts with `type` (case-insensitive).

- **Syntax**: `itemSet = getObjectsType( container, type )`
- **Parameters**:
  - `container`: Container object, room, character/player, item, container ID string, or collection/array of items.
  - `type`: String type prefix to match.
- **Returns**: An associative array (`SET`) of matching items indexed by object ID.
- **Example**:
  ```basic
  Dim items = getItemsIn($AGENT)
  Dim talismans = getObjectsType(items, "talis")
  If SetLen(talismans) > 0
      Display "You have a talisman!"
  End_If
  ```

---

### 3.10.15 getPlayerProperties( user_name )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Retrieves the persisted account properties of the specified user from the database.

- **Syntax**: `propsSet = getPlayerProperties( user_name )`
- **Returns**: A `SET` indexed by attribute names.

---

### 3.10.16 getPlayersIn( object )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Returns a `SET` of connected player characters inside the specified room or vehicle.

- **Syntax**: `playerSet = getPlayersIn( object )`

---

### 3.10.17 getRooms( [setExclusions] )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Returns a `SET` containing all `ROOM` objects in the virtual world, optionally omitting rooms specified in `setExclusions`.

- **Syntax**: `allRooms = getRooms( [setExclusions] )`

---

### 3.10.18 getRoomsFrom( room )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Returns a `SET` of all rooms reachable via exits directly accessible from `room`.

- **Syntax**: `neighborRooms = getRoomsFrom( room )`

---

### 3.10.19 getSetting( key [, default] )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Retrieves a persisted key-value setting previously stored by `SaveSetting`.

- **Syntax**: `val = getSetting( key [, default] )`
- **Parameters**: `key` (String), `default` (Optional fallback value).

---

### 3.10.20 getTime( format )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Returns the current server date/time formatted according to pattern `format`.

- **Pattern symbols**: `HH` (hour 00-23), `mm` (minutes), `ss` (seconds), `MM` (month), `dd` (day), `yyyy` (year).
- **Example**:
  ```basic
  Display SYS, $WORLD, "Time is: " + getTime("HH:mm")
  ```

---

### 3.10.21 HttpFetch( url )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Fetches content from an external HTTP/HTTPS URL and returns the response body as a string.

- **Syntax**: `responseStr = HttpFetch( url )`

---

### 3.10.22 InStr( haystack, needle [, startpos] )

> **[DimY: implemented]** Searches for substring `needle` within `haystack` (case-insensitive). Returns 1-based index of first occurrence, or `0` if not found.

Searches for substring `needle` within `haystack`. Case-insensitive.

- **Syntax**: `pos = InStr( haystack, needle [, startpos] )`
- **Returns**: 1-based index of first occurrence, or `0` if not found.

---

### 3.10.23 InStrCount( haystack, needle )

> **[DimY: implemented]** Counts occurrences of `needle` within `haystack` (case-insensitive, sliding window).

Counts the total number of occurrences of `needle` within `haystack`. Case-insensitive.

- **Syntax**: `count = InStrCount( haystack, needle )`

---

### 3.10.24 Int( n )

> **[DimY: implemented]** Truncates number `n` to its integer part.

Truncates number `n` to its integer part (does not round up).

- **Syntax**: `i = Int( n )`

---

### 3.10.25 IsCharacter( object )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Tests whether the object argument is a character entity (NPC or player).

- **Syntax**: `b = IsCharacter( object )`
- **Returns**: Boolean (`1` or `0`).

---

### 3.10.26 IsPlayer( object )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Tests whether the object argument is a connected human player.

- **Syntax**: `b = IsPlayer( object )`
- **Returns**: Boolean (`1` or `0`).

---

### 3.10.27 IsRoom( object )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Tests whether the object argument is a `ROOM`.

- **Syntax**: `b = IsRoom( object )`
- **Returns**: Boolean (`1` or `0`).

---

### 3.10.28 LCase( string )

> **[DimY: implemented]** Converts a string to lowercase.

Converts a string to lowercase.

- **Syntax**: `lower = LCase( string )`

---

### 3.10.29 Left( string, nchars )

> **[DimY: implemented]** Returns the leftmost `nchars` characters of `string`.

Returns the leftmost `nchars` characters of `string`.

- **Syntax**: `sub = Left( string, nchars )`

---

### 3.10.30 Len( string )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Returns the character length of `string`.

- **Syntax**: `length = Len( string )`

---

### 3.10.31 Log( number )

> **[DimY: implemented]** Returns the natural logarithm (base $e$) of `number`.

Returns the natural logarithm (base $e$) of `number`.

- **Syntax**: `val = Log( number )`

---

### 3.10.32 MainType( object )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Returns the primary type prefix of an item's `TYPE` attribute (e.g. for `weapon.knife`, returns `"weapon"`).

- **Syntax**: `t = MainType( object )`

---

### 3.10.33 Mid( string, start [, nchars] )

> **[DimY: implemented]** Extracts a substring of `nchars` characters starting from 1-based position `start`. If `nchars` is omitted, extracts to the end of string.

Extracts a substring of `nchars` characters starting from 1-based position `start`.

- **Syntax**: `sub = Mid( string, start [, nchars] )`

---

### 3.10.34 NewArray( [string] )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Instantiates a sequential indexed `ARRAY` collection.

- **Syntax**: `arr = NewArray( ["item1, item2, ..."] )`

---

### 3.10.35 NewCharacter( room, name, description, image, attributes )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Dynamically creates an NPC character in the specified room.

- **Syntax**: `char = NewCharacter( room, name, description, image, attributes )`
- **Returns**: Reference to the newly created character.

---

### 3.10.36 NewImage( url, width, height )

> **[DimY: implemented]** Instantiates an `Image` object with explicit dimensions. Relative URLs are automatically absolutized against the world's `IMAGESFOLDER`. When passed to `Display`, `Print`, or string concatenation, it renders an HTML `<img>` tag.

- **Syntax**: `img = NewImage( url, width, height )`
- **Parameters**:
  - `url` *(Required)*: String path or URL to the image file. If relative, prepended with world `IMAGESFOLDER`.
  - `width` *(Required)*: Target display width in pixels.
  - `height` *(Required)*: Target display height in pixels.
- **Returns**: An `Image` object instance.
- **Example**:
  ```basic
  Print NewImage("hero.gif", 64, 100)
  testroom.effect = NewImage("rain.gif", 350, 235)
  pathgate.image("E") = NewImage("pathgateeo.jpg", 350, 235)
  ```

---

### 3.10.37 NewLink( from, to, orientation, bidirectional, name, description, image, attributes )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Dynamically creates a navigational exit link between two rooms.

- **Syntax**: `link = NewLink( from, to, orientation, bidirectional, name, description, image, attributes )`
- **Orientation**: `"N"`, `"S"`, `"E"`, `"W"`, `"U"`, `"D"`.

---

### 3.10.38 NewSet( [string] )

> **[DimY: implemented]** Creates a key-sorted `SET` dictionary.

- **Syntax**: `set = NewSet( ["key1=val1, key2=val2"] )`
- **Escape sequences**: `!1!` for comma (`,`), `!2!` for equals (`=`).

---

### 3.10.39 NewItem( container, name, descr, image, attributes )

Dynamically creates a new `ITEM` inside `container`.

- **Syntax**: `item = NewItem( container, name, descr, image, attributes )`
- **Instruction Syntax**: `NewItem container, name, descr, image, attributes`
- **Return Value**: Returns the newly generated item ID (e.g. `_obj0`).
- **Note**: Creation does not trigger movement events (`whenPicked`, `onReceiveItem`) to avoid recursion. Use `Move` afterwards if events are desired.

---

### 3.10.40 NOT( expr )

> **[DimY: partial]** Handled only as a prefix operator inside conditions (`Not expr` / `Not(expr)`), not as a general expression function.

Logical negation of a boolean or numeric expression.

- **Syntax**: `res = NOT( expr )`

---

### 3.10.41 PanelHtml( panel )

> **[DimX only - not yet implemented in DimY]** Function is not evaluated; the expression is returned as literal text.

Renders the specified panel's controls as HTML markup (used for building context menus).

- **Syntax**: `html = PanelHtml( panel )`

---

### 3.10.42 Replace( haystack, needle, replacement )

> **[DimY: implemented]** Replaces all occurrences of `needle` with `replacement` in `haystack`. Case-insensitive (matching DimX behavior).

Replaces all occurrences of `needle` with `replacement` in `haystack`. Case-insensitive.

- **Syntax**: `res = Replace( haystack, needle, replacement )`

---

### 3.10.43 Right( string, nchars )

> **[DimY: implemented]** Returns the rightmost `nchars` characters of `string`.

Returns the rightmost `nchars` characters of `string`.

- **Syntax**: `sub = Right( string, nchars )`

---

### 3.10.44 RndInt( N )

> **[DimY: partial]** Only the two-argument form `RndInt(min, max)` is implemented; the one-argument `RndInt(N)` form documented here is **not yet implemented in DimY**.

Returns a pseudo-random integer between $1$ and $N$ inclusive ($N \ge 1$).

- **Syntax**: `randVal = RndInt( N )`

---

### 3.10.45 Rnd()

> **[DimY: implemented]** Returns a pseudo-random floating-point number in the range $[0.0, 1.0)$.

Returns a pseudo-random floating-point number in the range $[0.0, 1.0)$.

- **Syntax**: `f = Rnd()`

---

### 3.10.46 RndSet( aSet )

> **[DimY: partial]** Only a literal `RndSet(setName)` (world set / `$WORLD.setName`) is supported, not arbitrary set expressions.

Selects and returns a random element from a `SET` or `ARRAY`.

- **Syntax**: `elem = RndSet( aSet )`

---

### 3.10.47 Round( number, decimals )

> **[DimY: implemented]** Rounds `number` to the specified number of decimal digits (defaults to 0 if omitted).

Rounds `number` to the specified number of decimal digits.

- **Syntax**: `rounded = Round( number, decimals )`
- **Example**: `Round(3.509, 2)` -> `3.51`

---

### 3.10.48 SetContainsKey( set, key )

Checks if `set` (or array/dictionary) contains an entry with key `key` (case-insensitive key comparison).

- **Syntax**: `hasKey = SetContainsKey( set, key )`
- **Parameters**:
  - `set`: An associative set, dictionary, array, or variable containing one. Returns `0`/`false` if not a collection or empty.
  - `key`: A string, integer, or `DimxObject` whose ID/name is tested.
- **Returns**: Boolean (`true` / `1` if key exists, `false` / `0` otherwise).
- **Example**:
  ```basic
  Dim items = getItemsIn($AGENT)
  If SetContainsKey(items, "talisman")
      Display "You possess the talisman."
  End_If
  ```

---

### 3.10.49 SetIndexOf( setarray, value )

> **[DimY: implemented]** Searches a set or array for `value` (case-insensitive) and returns its 1-based index, or `0` if not found.

- **Syntax**: `idx = SetIndexOf( setarray, value )`

---

### 3.10.50 SetKey( set, n )

> **[DimY: implemented]** Returns the key name at 1-based index position `n` within `set`.

- **Syntax**: `keyName = SetKey( set, n )`

---

### 3.10.51 SetKeys( set )

> **[DimY: implemented]** Returns an `ARRAY` containing all the key names in `set`.

- **Syntax**: `keysArr = SetKeys( set )`

---

### 3.10.52 SetLen( set )

Returns the total element count in `set` or `array`. Returns `-1` if the argument is not a collection.

- **Syntax**: `count = SetLen( set )`

---

### 3.10.53 Split( valuestring, separatorstring )

Splits `valuestring` by delimiter `separatorstring` into an `ARRAY`.

- **Syntax**: `arr = Split( valuestring, separatorstring )`
- **Example**:
  ```basic
  possibilities = Split("pick/attack/go/drop/bite", "/")
  ' Produces array: ("pick", "attack", "go", "drop", "bite")
  ```

---

### 3.10.54 Sqr( x )

> **[DimY: implemented]** Returns the square root of `x` ($\sqrt{x}$).

Returns the square root of `x` ($\sqrt{x}$).

- **Syntax**: `root = Sqr( x )`

---

### 3.10.55 UCase( string )

> **[DimY: implemented]** Converts a string to uppercase.

Converts a string to uppercase.

- **Syntax**: `upper = UCase( string )`
- **Example**: `UCase("masterplan")` -> `"MASTERPLAN"`

---

