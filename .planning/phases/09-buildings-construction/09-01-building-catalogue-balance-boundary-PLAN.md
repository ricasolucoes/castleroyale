---
phase: 09-buildings-construction
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - packages/game-data/data/buildings.json
  - packages/game-data/data/starter.json
  - packages/game-data/schema/buildings.schema.json
  - packages/localization/locales/en/mvp.json
  - packages/localization/locales/pt-BR/mvp.json
  - packages/localization/locales/es/mvp.json
  - apps/api/config/game.php
  - apps/api/bootstrap/app.php
  - apps/api/modules/Shared/Interface/Console/ImportGameDataCommand.php
  - apps/api/tests/Feature/GameData/GameDataImportTest.php
  - apps/api/tests/Architecture/ArchitectureTest.php
  - docs/adr/020-game-data-runtime-source.md
  - docs/gsd/DECISIONS.md
autonomous: true
requirements: [REQ-06]

must_haves:
  truths:
    - "All eighteen buildings named in docs/game-design/buildings.md exist in packages/game-data/data/buildings.json and load through GameDataCatalog"
    - "Every building name renders in all three shipped locales, not as a raw key"
    - "The Palace gate is expressed as data: no non-Palace building level can be reached above the Palace's level"
    - "A dataset with a duplicate code, a negative cost, a dangling requirement or a missing Palace gate is refused by `php artisan game:import-data` with the offending code named, and the command exits non-zero"
    - "A cost, duration or effect table added to PHP fails the architecture suite"
  artifacts:
    - path: "packages/game-data/data/buildings.json"
      provides: "The eighteen-building catalogue with per-level cost, build_time_seconds, requirements and effects"
      contains: "\"code\": \"siege_workshop\""
    - path: "packages/game-data/schema/buildings.schema.json"
      provides: "The reviewable JSON Schema for a building definition"
      contains: "build_time_seconds"
    - path: "apps/api/modules/Shared/Interface/Console/ImportGameDataCommand.php"
      provides: "game:import-data — import-time validation of the whole bundle"
      contains: "game:import-data"
    - path: "apps/api/tests/Architecture/ArchitectureTest.php"
      provides: "The balance-tables-stay-out-of-PHP rule"
      contains: "keeps cost, duration and effect tables out of PHP"
    - path: "docs/adr/020-game-data-runtime-source.md"
      provides: "The recorded deviation from ADR-013's 'database is the runtime source of truth' clause"
  key_links:
    - from: "packages/game-data/data/buildings.json"
      to: "packages/localization/locales/*/mvp.json"
      via: "name_key"
      pattern: "buildings\\.(barracks|archery_range|stable|siege_workshop|academy|embassy|marketplace|hospital|walls|watchtower|iron_mine|treasury|tavern)"
    - from: "apps/api/bootstrap/app.php"
      to: "ImportGameDataCommand"
      via: "withCommands registration"
      pattern: "ImportGameDataCommand::class"
---

<objective>
Grow the building catalogue from five placeholder entries to the full eighteen
documented in `docs/game-design/buildings.md`, encode the Palace gate as data,
and close the two boundaries that make Phase 09 success criterion 1 provable:
an import-time validator that names the offending code, and an architecture test
that fails the build if a cost, duration or effect table is ever written in PHP.

Purpose: REQ-06 ("no balance number hardcoded in application code") stops being a
convention and becomes a mechanically enforced rule. Every later phase that adds a
dataset inherits both gates for free.
Output: the eighteen-building dataset, 39 new localization strings, a JSON Schema,
`php artisan game:import-data`, one new architecture test, ADR-020.
</objective>

<execution_context>
@/Users/sierra/.claude/get-shit-done/workflows/execute-plan.md
@/Users/sierra/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/ROADMAP.md
@.planning/STATE.md
@.planning/phases/09-buildings-construction/09-CONTEXT.md
@.planning/codebase/ARCHITECTURE.md
@.planning/codebase/CONVENTIONS.md
@.planning/codebase/TESTING.md
@docs/game-design/buildings.md
@docs/adr/013-data-driven-balancing.md

<interfaces>
<!-- Contracts the executor needs. Do not go looking for these in the codebase. -->

`packages/game-data/src/index.ts` — the authored shape (already exists, unchanged):

```ts
export type ResourceCost = { food?: number; wood?: number; stone?: number; iron?: number; gold?: number };
export type Effect = { target: string; operation: 'add' | 'multiply'; value: number };
export type Requirement = { type: 'building' | 'technology' | 'nobility' | 'player_level'; code: string; level: number };
export type BuildingLevel = { level: number; cost: ResourceCost; build_time_seconds: number; requirements: Requirement[]; effects: Effect[]; capacity?: number };
export type Building = { code: string; name_key: string; category: string; max_level: number; levels: BuildingLevel[] };
```

`Game\Shared\Infrastructure\GameData\GameDataCatalog` (already exists, unchanged):

```php
public function starter(): array;
public function buildings(): array;                      // list<array<string,mixed>>
public function building(string $code): ?array;
public function buildingLevel(string $code, int $level): ?array;
public function citySlots(): list<string>;               // plot_01 .. plot_18
public function starterBuildings(): list<array{slot:string,code:string,level:int}>;
public function units(): array;
private function read(string $file): array;              // config('game.data_path').'/data/'.$file
```

`Game\World\Interface\Console\GenerateWorldCommand` is the only existing command and
is registered in `apps/api/bootstrap/app.php` via `->withCommands([...])`.
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Author the eighteen-building catalogue, the Palace gate and its 39 localization strings</name>

  <read_first>
    - packages/game-data/data/buildings.json (the five existing entries — the exact formatting and key order to imitate)
    - packages/game-data/data/starter.json (the starter city's five buildings and their plots)
    - packages/game-data/src/validate.ts (the rules the dataset must already satisfy)
    - docs/game-design/buildings.md (the eighteen names and "The Palace gate" section)
    - packages/localization/locales/en/mvp.json (the `buildings` block — five keys today)
  </read_first>

  <files>
    packages/game-data/data/buildings.json,
    packages/game-data/data/starter.json,
    packages/localization/locales/en/mvp.json,
    packages/localization/locales/pt-BR/mvp.json,
    packages/localization/locales/es/mvp.json
  </files>

  <action>
**Do not change any existing cost, build_time_seconds or effect value.** Phase 08's
tests assert exact post-upgrade balances (`MvpGameplayTest` expects wood 390 /
stone 450 after a farm level-2 upgrade); changing a placeholder cost silently
breaks them. The only edits to the five existing entries are the `requirements`
arrays described below.

**1a. Add the Palace gate to the four existing non-Palace buildings.**

`docs/game-design/buildings.md` § The Palace gate: *"No building may exceed the
Palace level."* Encode it as data, one requirement per level, so 09-04 evaluates it
generically instead of special-casing the Palace in PHP.

For `farm`, `lumber_mill`, `quarry` and `warehouse`, replace the empty
`"requirements": []` on **level 2** with:

```json
"requirements": [{ "type": "building", "code": "palace", "level": 2 }]
```

and on **level 3** with:

```json
"requirements": [{ "type": "building", "code": "palace", "level": 3 }]
```

Level 1 keeps `"requirements": []` (a level-1 building is the starting state, not
an upgrade). `palace` keeps `"requirements": []` at every level — it is the spine,
it gates itself against nothing.

**1b. Raise the starter Palace to level 3** in `packages/game-data/data/starter.json`:

```json
{ "code": "palace", "level": 3, "slot": "plot_01" },
```

Rationale to carry into the commit body: with the Palace gate live, a level-1
Palace would refuse every farm/lumber_mill/quarry/warehouse upgrade and
retroactively invalidate the progression Phases 07 and 08 already test
(`MvpGameplayTest`, `EconomyConcurrencyTest`, `CityEconomyFoundationTest`). The
Palace has no `effects` at any level, so no balance, capacity or production
assertion moves. It also makes `BUILDING_MAX_LEVEL` reachable in one request,
which 09-03 uses. No test asserts the starter Palace's level today (verified by
grep: only `CityFoundationTest` asserts `data.slots.0.building.code === 'palace'`).

**1c. Append the thirteen missing buildings**, in this order, after `warehouse`.
These are the exact values — do not derive, round or "improve" them. Every
building has `max_level: 3`; level 1 is always free and instant; levels 2 and 3
carry the Palace gate requirement for that level.

| code | category | L2 cost | L2 secs | L3 cost | L3 secs | effects |
|---|---|---|---|---|---|---|
| `barracks` | military | food 150, wood 200, stone 120 | 35 | food 380, wood 500, stone 300, iron 60 | 70 | `[]` |
| `archery_range` | military | food 120, wood 240, stone 100 | 35 | food 300, wood 600, stone 250, iron 60 | 70 | `[]` |
| `stable` | military | food 260, wood 180, stone 100 | 40 | food 650, wood 450, stone 250, iron 80 | 80 | `[]` |
| `siege_workshop` | military | wood 300, stone 220, iron 120 | 45 | wood 750, stone 550, iron 300 | 90 | `[]` |
| `academy` | support | food 180, wood 220, stone 200 | 40 | food 450, wood 550, stone 500, iron 100 | 80 | `[]` |
| `embassy` | support | food 140, wood 200, stone 160 | 35 | food 350, wood 500, stone 400, gold 60 | 70 | `[]` |
| `marketplace` | support | food 160, wood 260, stone 140 | 35 | food 400, wood 650, stone 350, gold 80 | 70 | `[]` |
| `hospital` | support | food 200, wood 180, stone 160 | 35 | food 500, wood 450, stone 400, iron 60 | 70 | `[]` |
| `walls` | defense | wood 150, stone 300 | 40 | wood 380, stone 750, iron 100 | 80 | `[]` |
| `watchtower` | defense | wood 200, stone 180 | 30 | wood 500, stone 450, iron 60 | 60 | `[]` |
| `iron_mine` | economy | food 120, wood 160, stone 140 | 25 | food 300, wood 400, stone 350 | 50 | `production.iron` add 1 at every level |
| `treasury` | economy | food 150, wood 200, stone 260 | 30 | food 380, wood 500, stone 650 | 60 | `production.gold` add 1 at every level |
| `tavern` | support | food 220, wood 200, gold 60 | 30 | food 550, wood 500, gold 150 | 60 | `[]` |

Two deliberate choices, both to be repeated in the SUMMARY:

- **`iron_mine` and `treasury` use `value: 1` at every level**, exactly matching the
  four existing producers. `GameDataCatalog::effectsForBuildings()` reads only the
  *current* level's effects, so those buildings do not scale with level today. That
  is pre-existing placeholder balance owned by Phase 46 (Economy Balance Pass);
  authoring a scaling curve here would make the new producers inconsistent with the
  four already shipped and change nothing observable, since neither building exists
  in a starter city.
- **Every non-producer gets `"effects": []`**, not a speculative target string.
  `effectsForBuildings()` silently ignores unknown targets, so an invented
  `training.infantry_slots` would look implemented and do nothing. Troop, defence,
  trade and hero effects are authored by the phases that consume them (11, 12, 19,
  20, 22, 27).

Shape of each new entry, `barracks` written out in full as the template — the other
twelve are identical modulo the table above:

```json
  {
    "code": "barracks",
    "name_key": "buildings.barracks",
    "category": "military",
    "max_level": 3,
    "levels": [
      { "level": 1, "cost": {}, "build_time_seconds": 0, "requirements": [], "effects": [] },
      {
        "level": 2,
        "cost": { "food": 150, "wood": 200, "stone": 120 },
        "build_time_seconds": 35,
        "requirements": [{ "type": "building", "code": "palace", "level": 2 }],
        "effects": []
      },
      {
        "level": 3,
        "cost": { "food": 380, "wood": 500, "stone": 300, "iron": 60 },
        "build_time_seconds": 70,
        "requirements": [{ "type": "building", "code": "palace", "level": 3 }],
        "effects": []
      }
    ]
  },
```

and `iron_mine`'s effects (same array on all three levels):

```json
"effects": [{ "target": "production.iron", "operation": "add", "value": 1 }]
```

Absent cost keys mean zero — do not write `"iron": 0`.

**1d. Add the thirteen building names to all three locale catalogues**, inside the
existing `buildings` object in `packages/localization/locales/{en,pt-BR,es}/mvp.json`.
Keep the five existing entries untouched and append in catalogue order:

| key | en | pt-BR | es |
|---|---|---|---|
| `barracks` | Barracks | Quartel | Cuartel |
| `archery_range` | Archery Range | Campo de Tiro | Campo de Tiro |
| `stable` | Stable | Estábulo | Establo |
| `siege_workshop` | Siege Workshop | Oficina de Cerco | Taller de Asedio |
| `academy` | Academy | Academia | Academia |
| `embassy` | Embassy | Embaixada | Embajada |
| `marketplace` | Marketplace | Mercado | Mercado |
| `hospital` | Hospital | Hospital | Hospital |
| `walls` | Walls | Muralhas | Murallas |
| `watchtower` | Watchtower | Torre de Vigia | Torre de Vigía |
| `iron_mine` | Iron Mine | Mina de Ferro | Mina de Hierro |
| `treasury` | Treasury | Tesouraria | Tesorería |
| `tavern` | Tavern | Taverna | Taberna |

Finish with `npx prettier --write packages/game-data/data/buildings.json
packages/game-data/data/starter.json 'packages/localization/locales/*/mvp.json'` —
the repository's prettier config (printWidth 100) owns the exact whitespace, not
this plan.
  </action>

  <acceptance_criteria>
    - `python3 -c "import json;d=json.load(open('packages/game-data/data/buildings.json'));print(len(d))"` prints `18`
    - `python3 -c "import json;d=json.load(open('packages/game-data/data/buildings.json'));print(sorted(b['code'] for b in d))"` prints exactly `['academy', 'archery_range', 'barracks', 'embassy', 'farm', 'hospital', 'iron_mine', 'lumber_mill', 'marketplace', 'palace', 'quarry', 'siege_workshop', 'stable', 'tavern', 'treasury', 'walls', 'warehouse', 'watchtower']`
    - `python3 -c "import json;d=json.load(open('packages/game-data/data/buildings.json'));print(all(len(b['levels'])==b['max_level']==3 for b in d))"` prints `True`
    - Palace gate present on every non-Palace level ≥ 2: `python3 -c "import json;d=json.load(open('packages/game-data/data/buildings.json'));print(all(any(r=={'type':'building','code':'palace','level':l['level']} for r in l['requirements']) for b in d if b['code']!='palace' for l in b['levels'] if l['level']>=2))"` prints `True`
    - Palace itself is ungated: `python3 -c "import json;d=json.load(open('packages/game-data/data/buildings.json'));p=[b for b in d if b['code']=='palace'][0];print(all(l['requirements']==[] for l in p['levels']))"` prints `True`
    - `grep -c '"level": 3, "slot": "plot_01"' packages/game-data/data/starter.json` is ≥ 1 after prettier, or `python3 -c "import json;s=json.load(open('packages/game-data/data/starter.json'));print([b for b in s['city']['buildings'] if b['code']=='palace'][0]['level'])"` prints `3`
    - Existing costs untouched: `git diff packages/game-data/data/buildings.json | grep -E '^-.*"(cost|build_time_seconds|effects)"'` prints nothing
    - All three catalogues carry 18 building names: `for l in en pt-BR es; do python3 -c "import json;print('$l',len(json.load(open('packages/localization/locales/$l/mvp.json'))['buildings']))"; done` prints `18` for each
    - `npm run gamedata:validate` exits 0 and prints `Game data OK`
  </acceptance_criteria>

  <verify>
    <automated>npm run gamedata:validate &amp;&amp; npm run typecheck &amp;&amp; cd apps/api &amp;&amp; ./vendor/bin/pest</automated>
  </verify>

  <done>
    buildings.json holds all eighteen documented buildings with per-level cost,
    duration, Palace-gate requirements and effects; the starter Palace is level 3;
    all three locales resolve every `name_key`; the existing suite (pest 158,
    jest 13 suites) is still green.
  </done>
</task>

<task type="auto">
  <name>Task 2: JSON Schema and `php artisan game:import-data` — validation at import time</name>

  <read_first>
    - packages/game-data/src/validate.ts (the CI-side rules; the command mirrors them and adds three)
    - apps/api/modules/World/Interface/Console/GenerateWorldCommand.php (the command style and namespace layout to imitate)
    - apps/api/bootstrap/app.php (the `->withCommands([...])` registration site)
    - apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php (how datasets are read; `config('game.data_path')`)
    - apps/api/config/game.php (§ Game data source — where the new `localization_path` key goes)
    - .planning/codebase/CONVENTIONS.md (§ PHP)
  </read_first>

  <files>
    packages/game-data/schema/buildings.schema.json,
    apps/api/config/game.php,
    apps/api/modules/Shared/Interface/Console/ImportGameDataCommand.php,
    apps/api/bootstrap/app.php,
    apps/api/tests/Feature/GameData/GameDataImportTest.php
  </files>

  <action>
**2a. Write `packages/game-data/schema/buildings.schema.json`** — a JSON Schema
2020-12 document describing exactly the `Building` type already declared in
`packages/game-data/src/index.ts`. It is the reviewable contract a designer reads
and an editor can validate against; it introduces no runtime dependency.

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "https://castleroyale.game/schema/buildings.schema.json",
  "title": "Building catalogue",
  "type": "array",
  "items": { "$ref": "#/$defs/building" },
  "$defs": {
    "resourceCost": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "food": { "$ref": "#/$defs/wholeUnits" },
        "wood": { "$ref": "#/$defs/wholeUnits" },
        "stone": { "$ref": "#/$defs/wholeUnits" },
        "iron": { "$ref": "#/$defs/wholeUnits" },
        "gold": { "$ref": "#/$defs/wholeUnits" }
      }
    },
    "wholeUnits": { "type": "integer", "minimum": 0 },
    "requirement": {
      "type": "object",
      "additionalProperties": false,
      "required": ["type", "code", "level"],
      "properties": {
        "type": { "enum": ["building", "technology", "nobility", "player_level"] },
        "code": { "type": "string", "pattern": "^[a-z][a-z0-9_]*$" },
        "level": { "type": "integer", "minimum": 1 }
      }
    },
    "effect": {
      "type": "object",
      "additionalProperties": false,
      "required": ["target", "operation", "value"],
      "properties": {
        "target": { "type": "string", "pattern": "^[a-z][a-z0-9_]*(\\.[a-z][a-z0-9_]*)+$" },
        "operation": { "enum": ["add", "multiply"] },
        "value": { "type": "integer" }
      }
    },
    "level": {
      "type": "object",
      "additionalProperties": false,
      "required": ["level", "cost", "build_time_seconds", "requirements", "effects"],
      "properties": {
        "level": { "type": "integer", "minimum": 1 },
        "cost": { "$ref": "#/$defs/resourceCost" },
        "build_time_seconds": { "$ref": "#/$defs/wholeUnits" },
        "requirements": { "type": "array", "items": { "$ref": "#/$defs/requirement" } },
        "effects": { "type": "array", "items": { "$ref": "#/$defs/effect" } },
        "capacity": { "$ref": "#/$defs/wholeUnits" }
      }
    },
    "building": {
      "type": "object",
      "additionalProperties": false,
      "required": ["code", "name_key", "category", "max_level", "levels"],
      "properties": {
        "code": { "type": "string", "pattern": "^[a-z][a-z0-9_]*$" },
        "name_key": { "type": "string", "pattern": "^buildings\\.[a-z][a-z0-9_]*$" },
        "category": { "enum": ["core", "economy", "military", "defense", "support"] },
        "max_level": { "type": "integer", "minimum": 1 },
        "levels": { "type": "array", "minItems": 1, "items": { "$ref": "#/$defs/level" } }
      }
    }
  }
}
```

**2b. Add one config key** to `apps/api/config/game.php`, immediately under
`data_path` in the "Game data source" block, so the command can reach the locale
catalogues without a hardcoded relative path:

```php
    'localization_path' => env('GAME_LOCALIZATION_PATH', base_path('../../packages/localization')),
```

**2c. Write `apps/api/modules/Shared/Interface/Console/ImportGameDataCommand.php`**,
namespace `Game\Shared\Interface\Console`, `final` class extending
`Illuminate\Console\Command`, `$signature = 'game:import-data'`, description
"Load and validate the versioned game-data bundle". Inject `GameDataCatalog`
through `handle(GameDataCatalog $catalog): int`.

It collects problems into a `list<string>`, prints them all (never stops at the
first), and returns `self::FAILURE` when any exist, `self::SUCCESS` otherwise.
Every message names the offending code — per ADR-013, *"'invalid dataset' is not
an acceptable failure message for a designer shipping a tuning pass."*

Rules, in this order. The first four mirror `validate.ts` (ADR-013: validation runs
in CI **and again on import**); the last three are new and exist only here because
they cross dataset and catalogue boundaries the JS validator does not read:

1. **Shape** — every building has `code`, `name_key`, `category`, `max_level`,
   `levels`; `category` is one of `core|economy|military|defense|support`;
   `name_key` equals `'buildings.'.$code`.
2. **Levels** — `count(levels) === max_level`; level numbers are exactly
   `1..max_level`, each once; every `cost` key is one of
   `food|wood|stone|iron|gold` with an integer value `>= 0`; `build_time_seconds`
   is an integer `>= 0`.
3. **Duplicates** — no repeated `code`.
4. **Dangling references** — every `requirements[]` entry with
   `type === 'building'` names a code present in the dataset, at a `level` no
   greater than that building's `max_level`.
5. **Palace gate** — for every building except `palace`, every level `L >= 2`
   contains `{type: 'building', code: 'palace', level: L}`; `palace` itself has no
   `building` requirement at any level. Message on failure:
   `"buildings: \"{code}\" level {L} is missing the palace gate (requires palace level {L})"`.
6. **Translations** — for each of `en`, `pt-BR`, `es`, read
   `config('game.localization_path').'/locales/'.$locale.'/mvp.json'` and assert
   the dotted `name_key` resolves to a non-empty string. Message:
   `"buildings: \"{code}\" has no {locale} translation for \"{name_key}\""`.
7. **Starter integrity** — every entry of `$catalog->starterBuildings()` names a
   code present in the catalogue at a `level` between 1 and that building's
   `max_level`, and its `slot` is in `$catalog->citySlots()`.

On success print one line per dataset, e.g.:

```
buildings: 18 definitions, 54 levels
city-slots: 18 plots
starter: 5 buildings, 1 unit stack
```

Use `$this->line()` / `$this->error()`; no `dd`, `dump` or `sleep` (the
architecture suite forbids them). No cost, duration or effect *value* may appear
in this file — only rule names and structural bounds.

**2d. Register it** in `apps/api/bootstrap/app.php`:

```php
->withCommands([GenerateWorldCommand::class, ImportGameDataCommand::class])
```

**2e. Write `apps/api/tests/Feature/GameData/GameDataImportTest.php`** with three
tests:

- *"imports the shipped bundle without a single problem"* —
  `$this->artisan('game:import-data')->expectsOutputToContain('buildings: 18 definitions')->assertExitCode(0)`.
- *"names the building whose palace gate is missing"* — copy the real
  `packages/game-data/data` tree into a temp directory
  (`sys_get_temp_dir().'/gamedata-'.uniqid()`), strip the `requirements` array from
  `barracks` level 2, point `config(['game.data_path' => $tmp])` at it, and assert
  the command exits 1 with output containing `barracks` and `palace gate`.
- *"names the building with an untranslated name_key"* — same temp-directory trick,
  but add a building `{"code":"observatory","name_key":"buildings.observatory",...}`
  and assert the failure output contains `observatory` and `en`.

Clean the temp directory in an `afterEach`.
  </action>

  <acceptance_criteria>
    - `cd apps/api && php artisan game:import-data` exits 0 and prints a line matching `^buildings: 18 definitions, 54 levels$`
    - `grep -c "game:import-data" apps/api/modules/Shared/Interface/Console/ImportGameDataCommand.php` is ≥ 1
    - `grep -c "ImportGameDataCommand::class" apps/api/bootstrap/app.php` is `1`
    - `grep -c "localization_path" apps/api/config/game.php` is `1`
    - `python3 -c "import json;json.load(open('packages/game-data/schema/buildings.schema.json'))"` exits 0
    - `grep -cE "(palace gate|no [a-zA-Z-]+ translation)" apps/api/modules/Shared/Interface/Console/ImportGameDataCommand.php` is ≥ 2
    - No balance literal in the command: `grep -nE "['\"](food|wood|stone|iron|gold)['\"][[:space:]]*=>[[:space:]]*[0-9]+" apps/api/modules/Shared/Interface/Console/ImportGameDataCommand.php` prints nothing
    - `cd apps/api && ./vendor/bin/pest --filter=GameDataImport` reports 3 passing tests
  </acceptance_criteria>

  <verify>
    <automated>cd apps/api &amp;&amp; php artisan game:import-data &amp;&amp; ./vendor/bin/pest --filter=GameDataImport &amp;&amp; ./vendor/bin/phpstan analyse --memory-limit=1G &amp;&amp; ./vendor/bin/pint --test</automated>
  </verify>

  <done>
    `game:import-data` accepts the shipped bundle and refuses a dataset with a
    missing Palace gate or an untranslated `name_key`, naming the offending code
    and exiting non-zero; the JSON Schema documents the authored shape; PHPStan
    level 8 and Pint are clean.
  </done>
</task>

<task type="auto">
  <name>Task 3: Architecture test forbidding balance tables in PHP, plus ADR-020</name>

  <read_first>
    - apps/api/tests/Architecture/ArchitectureTest.php (the two existing `RecursiveDirectoryIterator` file-walk tests at the end of the file — copy their structure exactly)
    - docs/adr/013-data-driven-balancing.md (the rule this test enforces, and the "structural limits are different" paragraph)
    - docs/adr/019-world-grid-coordinate-indexing.md (ADR file format and front-matter style)
    - docs/gsd/DECISIONS.md (the entry format: heading, Type, What, Why, Impact, ADR)
    - apps/api/config/game.php (§ Structural limits and § Time — the two knobs that must NOT be flagged)
  </read_first>

  <files>
    apps/api/tests/Architecture/ArchitectureTest.php,
    docs/adr/020-game-data-runtime-source.md,
    docs/gsd/DECISIONS.md
  </files>

  <action>
**3a. Append one test** to `apps/api/tests/Architecture/ArchitectureTest.php`,
following the file's existing `it(...)->group('arch')` file-walk style:

```php
it('keeps cost, duration and effect tables out of PHP', function (): void {
    // ADR-013: balance is authored in packages/game-data and imported; PHP may
    // read it but never restate it. What lives in config/game.php is deliberately
    // different — `limits.max_build_queue_slots` bounds the server's worst case
    // and `time_scale` is a local development knob. Neither is a balance *table*,
    // so neither pattern below can match them: the patterns look for a resource
    // name mapped to a number, a named duration column mapped to a number, or a
    // constant named for a cost/duration/capacity. A structural ceiling keyed on
    // a limit name is not any of those.
    $patterns = [
        'a resource cost table' => '/[\'"](food|wood|stone|iron|gold)[\'"]\s*=>\s*[0-9]+/',
        'a hardcoded build, research or training duration' => '/\b(build_time_seconds|research_time_seconds|training_time_seconds)\s*=>\s*[0-9]+/',
        'a balance constant' => '/\bconst\s+[A-Z_]*(COST|DURATION|CAPACITY|PRODUCTION|UPKEEP|BUILD_TIME)[A-Z_]*\s*=\s*[0-9]/',
        'an effect table' => '/[\'"](target|operation)[\'"]\s*=>\s*[\'"](production|storage|combat|training)\./',
    ];

    $offenders = [];
    $roots = [base_path('modules'), base_path('config')];

    foreach ($roots as $root) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents((string) $file->getPathname());

            foreach ($patterns as $label => $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $offenders[] = $label.' in '.$file->getPathname();
                }
            }
        }
    }

    expect($offenders)->toBe([]);
})->group('arch');
```

This is already known green: running each pattern by hand over `modules/` and
`config/` today returns zero matches, and `max_build_queue_slots => 4` and
`time_scale` match none of them. Confirm that before committing — a rule that was
never red is not evidence.

**3b. Prove the rule bites.** Temporarily append
`private const FARM_COST = ['wood' => 120];` to any file under `apps/api/modules/`,
run `./vendor/bin/pest --group=arch`, confirm the new test **fails and names the
file**, then remove the line and confirm green again. Record both observations in
the SUMMARY. Do not commit the temporary line.

**3c. Write `docs/adr/020-game-data-runtime-source.md`.**

09-CONTEXT.md § Game data starts here calls for `php artisan game:import-data`,
and ADR-013 states *"imported into the database by `php artisan game:import-data`.
The database is the runtime source of truth."* This project shipped Phases 05–08
against `GameDataCatalog`, which reads the JSON bundle from disk at request time;
seven services depend on it. 09-CONTEXT.md's own escape hatch applies: *"If one is
genuinely unworkable, write an ADR and record it in `docs/gsd/DECISIONS.md` rather
than quietly designing around it."*

Status Accepted, dated today, structured like ADR-019 (Context / Decision /
Alternatives / Consequences). It must say:

- **Decision.** The versioned JSON bundle stays the runtime source in Phase 09.
  `game:import-data` is real and load-bearing — it *loads and validates* the
  bundle, is the import-time half of ADR-013's "validated in CI and again on
  import", and keeps its name so the database path can be added behind it later
  without renaming anything. Balance still never appears in PHP; that half of
  ADR-013 is enforced harder than before, by the architecture test added in this
  plan.
- **Why now.** Making the database the runtime source means a migration, an
  importer, a rewrite of `GameDataCatalog`'s seven consumers and new bootstrapping
  for every `RefreshDatabase` test — none of which is required by any Phase 09
  success criterion, and all of which would rewrite read paths Phases 05–08
  already prove.
- **When it changes.** The database becomes the runtime source when a phase
  actually needs content to ship without a deploy or to be staged from the back
  office — Phase 31 (Events & LiveOps) or Phase 34 (Admin), whichever lands first.
  Until then a DB table nobody reads would be exactly the ceremonial structure
  ADR-001 rejects.
- **Consequences.** Content changes still require a deploy until then; the trade is
  recorded, not silent. `config/game.php`'s "Game data source" comment must be
  amended to say the bundle is read at runtime and validated by
  `game:import-data`, with the database import deferred per ADR-020.

Amend that comment in `config/game.php` as part of this task.

**3d. Append the matching entry to `docs/gsd/DECISIONS.md`**, following the file's
existing format exactly (`### {date} — Phase 09 — {title}`, then **Type:** Change,
**What:**, **Why:**, **Impact:**, **ADR:** ADR-020).
  </action>

  <acceptance_criteria>
    - `grep -c "keeps cost, duration and effect tables out of PHP" apps/api/tests/Architecture/ArchitectureTest.php` is `1`
    - `cd apps/api && ./vendor/bin/pest --group=arch` is green and reports at least one more test than before this task
    - The rule is proven to bite: the SUMMARY records the temporary `const FARM_COST = ['wood' => 120];` run failing with the offending filename named, and the file is clean afterwards (`git status --porcelain apps/api/modules` prints nothing)
    - `test -f docs/adr/020-game-data-runtime-source.md` succeeds and `grep -c "ADR-013" docs/adr/020-game-data-runtime-source.md` is ≥ 1
    - `grep -c "ADR-020" docs/gsd/DECISIONS.md` is ≥ 1
    - `grep -c "ADR-020" apps/api/config/game.php` is ≥ 1
    - `cd apps/api && ./vendor/bin/pint --test` is clean
  </acceptance_criteria>

  <verify>
    <automated>cd apps/api &amp;&amp; ./vendor/bin/pest &amp;&amp; ./vendor/bin/phpstan analyse --memory-limit=1G &amp;&amp; ./vendor/bin/pint --test</automated>
  </verify>

  <done>
    A cost table, a hardcoded build duration or a balance constant written into
    `apps/api/modules/` or `apps/api/config/` fails `pest --group=arch` naming the
    file, `time_scale` and `max_build_queue_slots` are unaffected, and the
    deviation from ADR-013's runtime-source clause is recorded in ADR-020 and
    DECISIONS.md.
  </done>
</task>

</tasks>

<verification>
- `npm run gamedata:validate` — exits 0
- `cd apps/api && php artisan game:import-data` — exits 0, reports 18 definitions / 54 levels
- `cd apps/api && ./vendor/bin/pest` — green, ≥ 161 tests (158 baseline + 3 new)
- `cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G` — 0 errors
- `cd apps/api && ./vendor/bin/pint --test` — clean
- `npm run typecheck && npm run lint && npm test` — green (13 jest suites, 74 tests)
</verification>

<success_criteria>
- Eighteen buildings load from `packages/game-data/data/buildings.json`, one per
  row of `docs/game-design/buildings.md`'s catalogue table
- The Palace gate is data, not a PHP branch, and `palace` itself is ungated
- All eighteen `name_key`s resolve in `en`, `pt-BR` and `es`
- `game:import-data` refuses a bundle with a missing Palace gate or an
  untranslated name, naming the offending code, and exits non-zero
- A cost, duration or effect table added anywhere under `apps/api/modules/` or
  `apps/api/config/` fails the architecture suite; `time_scale` and
  `max_build_queue_slots` do not
- ADR-020 and a DECISIONS.md entry record why the database import is deferred
- Zero pre-existing tests changed behaviour
</success_criteria>

<output>
After completion, create `.planning/phases/09-buildings-construction/09-01-SUMMARY.md`
</output>
</content>
</invoke>
