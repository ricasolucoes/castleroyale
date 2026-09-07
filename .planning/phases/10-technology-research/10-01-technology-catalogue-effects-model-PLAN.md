---
phase: 10-technology-research
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - packages/game-data/data/technologies.json
  - packages/game-data/schema/technologies.schema.json
  - packages/localization/locales/en/mvp.json
  - packages/localization/locales/pt-BR/mvp.json
  - packages/localization/locales/es/mvp.json
  - apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php
  - apps/api/modules/Shared/Interface/Console/ImportGameDataCommand.php
  - apps/api/tests/Feature/GameData/TechnologyCatalogueTest.php
autonomous: true
requirements: [REQ-06]

must_haves:
  truths:
    - "The technology tree loads from versioned game data — every cost, duration, prerequisite and effect lives in technologies.json, never in PHP"
    - "`php artisan game:import-data` reports the technology count and names any offending technology code when the dataset is bad"
    - "Every technology level's effect is a typed (target, operation, value) descriptor reusing the shape buildings already use"
    - "Percentage effects are expressed as integer permille; no float appears anywhere in the dataset or the reader"
  artifacts:
    - path: "packages/game-data/data/technologies.json"
      provides: "The authored technology tree — 8 categories, a real DAG, integer permille effects"
      contains: "permille"
    - path: "packages/game-data/schema/technologies.schema.json"
      provides: "JSON Schema 2020-12 contract for the technology dataset"
      contains: "$defs"
    - path: "apps/api/tests/Feature/GameData/TechnologyCatalogueTest.php"
      provides: "Proof the catalogue reads technologies and that balance stays out of PHP"
      contains: "technologyLevel"
  key_links:
    - from: "apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php"
      to: "packages/game-data/data/technologies.json"
      via: "GameDataCatalog::technologies() / ::technologyLevel()"
      pattern: "technologies"
---

<objective>
Author the technology tree as versioned game data and teach the existing catalogue
and import command to read it.

This plan writes **no gameplay logic**. It produces the dataset, its schema, its
translation keys, and the read path — the raw material 10-02 validates, 10-03
spends resources against, and 10-04 renders.

Purpose: ROADMAP criterion 1's first half ("the technology tree loads from game
data") and REQ-06 ("no balance number hardcoded in application code"). The Palace
gate in Phase 09 proved the pattern; this applies it to a genuinely graph-shaped
dataset.
</objective>

<context>

<interfaces>
The shapes this plan must reuse rather than reinvent — all verified present:

`packages/game-data/schema/buildings.schema.json` already defines, in `$defs`:

```json
"requirement": {
  "type":"object","additionalProperties":false,
  "required":["type","code","level"],
  "properties":{
    "type":{"enum":["building","technology","nobility","player_level"]},
    "code":{"type":"string","pattern":"^[a-z][a-z0-9_]*$"},
    "level":{"type":"integer","minimum":1}
  }
},
"effect": {
  "type":"object","additionalProperties":false,
  "required":["target","operation","value"],
  "properties":{
    "target":{"type":"string","pattern":"^[a-z][a-z0-9_]*(\\.[a-z][a-z0-9_]*)+$"},
    "operation":{"enum":["add","multiply"]},
    "value":{"type":"integer"}
  }
},
"wholeUnits": {"type":"integer","minimum":0},
"resourceCost": { food/wood/stone/iron/gold, each $ref wholeUnits }
```

Note `requirement.type` **already** includes `"technology"`, and `effect.operation`
**already** includes `"multiply"`. Neither needs adding.

A building entry is shaped:

```json
{"code":"palace","name_key":"buildings.palace","category":"core","max_level":3,
 "levels":[{"level":1,"cost":{},"build_time_seconds":0,"requirements":[],"effects":[]}]}
```

`GameDataCatalog` (apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php)
today exposes `buildingLevel(string $code, int $level): ?array`, `starterValues(string $section): array`
and `effectsForBuildings(Collection $buildings): array`.

`packages/game-data/src/validate.ts` already maps `technologies → 'technology'` in
`REQUIREMENT_TYPE_BY_DATASET` and already checks `research_time_seconds` for
integer/non-negative. It is 10-02's file — **do not edit it here.**
</interfaces>

<canonical_refs>
- `docs/game-design/technology.md` — the 8 categories and what each improves. This is
  the content brief; follow its category table exactly.
- `docs/adr/010-integer-economy.md` — integer permille, truncate downward.
- `.planning/phases/10-technology-research/10-UI-SPEC.md` § Assets — the category icon
  map the client will build. Category codes here must match the eight it names.
</canonical_refs>

<trap>
**Do not add a fifth resource shape or a new cost vocabulary.** Technology costs use the
identical `resourceCost` `$defs` block buildings use. A designer who has to learn two cost
formats will eventually author one in the wrong shape and the validator will not catch it,
because it will be structurally valid under the other schema.
</trap>
</context>

<tasks>

<task type="auto" tdd="false">
  <name>Task 1: The technologies schema</name>

  <read_first>
    - packages/game-data/schema/buildings.schema.json (the `$defs` blocks to reuse verbatim, and the file's overall structure/`$schema` line)
    - docs/game-design/technology.md (the 8 categories)
  </read_first>

  <files>packages/game-data/schema/technologies.schema.json</files>

  <behavior>
    - The schema validates an array of technology entries
    - A technology has: `code`, `name_key`, `description_key`, `category`, `max_level`, `levels[]`
    - `category` is constrained to exactly the eight documented values
    - A level has: `level`, `cost`, `research_time_seconds`, `requirements[]`, `effects[]`
    - `additionalProperties` is `false` at every object level — an unknown key is an authoring mistake, not an extension point
    - Costs, `research_time_seconds` and `level` are integers with a minimum
  </behavior>

  <action>
Create `packages/game-data/schema/technologies.schema.json` using JSON Schema
2020-12 (`"$schema": "https://json-schema.org/draft/2020-12/schema"`, matching
buildings.schema.json's own declaration — read it and copy the exact URI it uses).

Copy the `resourceCost`, `wholeUnits`, `requirement` and `effect` `$defs` blocks
**verbatim** from `buildings.schema.json`. Do not retype them from the summary in
this plan; read the real file, because it is the source of truth and may carry
detail this plan omits.

Root:

```json
{
  "$schema": "<same URI buildings.schema.json uses>",
  "$id": "technologies.schema.json",
  "title": "Technology catalogue",
  "type": "array",
  "items": { "$ref": "#/$defs/technology" },
  "$defs": {
    "technology": {
      "type": "object",
      "additionalProperties": false,
      "required": ["code","name_key","description_key","category","max_level","levels"],
      "properties": {
        "code": { "type": "string", "pattern": "^[a-z][a-z0-9_]*$" },
        "name_key": { "type": "string", "pattern": "^technologies\\.[a-z][a-z0-9_]*$" },
        "description_key": { "type": "string", "pattern": "^technologies\\.[a-z][a-z0-9_]*_desc$" },
        "category": { "enum": ["economy","military","defense","logistics","construction","exploration","alliance","siege"] },
        "max_level": { "type": "integer", "minimum": 1 },
        "levels": { "type": "array", "minItems": 1, "items": { "$ref": "#/$defs/technologyLevel" } }
      }
    },
    "technologyLevel": {
      "type": "object",
      "additionalProperties": false,
      "required": ["level","cost","research_time_seconds","requirements","effects"],
      "properties": {
        "level": { "type": "integer", "minimum": 1 },
        "cost": { "$ref": "#/$defs/resourceCost" },
        "research_time_seconds": { "$ref": "#/$defs/wholeUnits" },
        "requirements": { "type": "array", "items": { "$ref": "#/$defs/requirement" } },
        "effects": { "type": "array", "items": { "$ref": "#/$defs/effect" } }
      }
    },
    "resourceCost": { <verbatim from buildings.schema.json> },
    "wholeUnits":   { <verbatim from buildings.schema.json> },
    "requirement":  { <verbatim from buildings.schema.json> },
    "effect":       { <verbatim from buildings.schema.json> }
  }
}
```

`description_key` is new relative to buildings: the UI-SPEC's detail sheet shows a
one-line description per technology, which buildings never needed.
  </action>

  <acceptance_criteria>
    - `test -f packages/game-data/schema/technologies.schema.json`
    - `python3 -c "import json;json.load(open('packages/game-data/schema/technologies.schema.json'))"` exits 0
    - `grep -c '"additionalProperties": false' packages/game-data/schema/technologies.schema.json` is ≥ 4
    - `grep -c '"multiply"' packages/game-data/schema/technologies.schema.json` is 1 (inherited from the copied effect def)
    - The eight category strings `economy`, `military`, `defense`, `logistics`, `construction`, `exploration`, `alliance`, `siege` each appear exactly once
    - `python3 -c "import json;a=json.load(open('packages/game-data/schema/buildings.schema.json'))['\$defs'];b=json.load(open('packages/game-data/schema/technologies.schema.json'))['\$defs'];assert a['requirement']==b['requirement'] and a['effect']==b['effect'] and a['resourceCost']==b['resourceCost'] and a['wholeUnits']==b['wholeUnits']"` exits 0 — the shared defs are byte-identical, not merely similar
  </acceptance_criteria>

  <verify>
    <automated>python3 -c "import json;json.load(open('packages/game-data/schema/technologies.schema.json'))"</automated>
  </verify>

  <done>
    The technology dataset has a schema contract that reuses the building vocabulary
    exactly, so a designer authoring a cost or a requirement writes the same shape in
    both files.
  </done>
</task>

<task type="auto" tdd="false">
  <name>Task 2: The authored technology tree and its translation keys</name>

  <read_first>
    - docs/game-design/technology.md (categories and what each improves)
    - packages/game-data/data/buildings.json (authoring style, formatting, key order — match it exactly; Phase 09 had to restore formatting after a reflow broke the diff)
    - packages/game-data/data/starter.json (starter resources, to keep tier-1 costs reachable)
    - packages/localization/locales/en/mvp.json (the namespace convention and where to insert)
    - .planning/phases/10-technology-research/10-UI-SPEC.md § Assets (the category icon map — categories must line up)
  </read_first>

  <files>
    packages/game-data/data/technologies.json,
    packages/localization/locales/en/mvp.json,
    packages/localization/locales/pt-BR/mvp.json,
    packages/localization/locales/es/mvp.json
  </files>

  <behavior>
    - At least 16 technologies spanning all eight categories, each with 3 levels
    - The dependency graph is a real DAG with at least one cross-category edge and at least one technology with two prerequisites — a tree that is only a set of independent chains would make 10-02's cycle detector untestable against real data and 10-04's cross-category chip rendering unexercised
    - Tier-1 technologies have no technology prerequisite, so a fresh player can start something
    - Every technology carries at least one effect; every percentage effect uses `multiply` with an integer permille value where 1000 means "no change"
    - Every `name_key` and `description_key` resolves in all three locale catalogues
    - Costs rise with level; `research_time_seconds` rises with level
  </behavior>

  <action>
Author `packages/game-data/data/technologies.json` as a JSON array, formatted to
match `buildings.json` exactly (2-space indent, same key ordering within an entry:
`code`, `name_key`, `description_key`, `category`, `max_level`, `levels`).

**Effect conventions — apply these consistently:**

- `operation: "add"` — a flat addition to an existing accumulator, in the same unit
  the target already uses. Example: `{"target":"storage.food","operation":"add","value":500}`.
- `operation: "multiply"` — an integer **permille** multiplier where `1000` is the
  identity. `1100` is +10%, `1250` is +25%. Never a fraction, never a percentage
  integer like `10`. Truncation is downward (ADR-010) and is 10-03's concern.

**Targets.** Use dotted targets matching what the server already accumulates or will
accumulate: `production.food`, `production.wood`, `production.stone`, `production.iron`,
`production.gold`, `storage.<resource>`, `build.speed`, `research.speed`,
`march.speed`, `unit.attack`, `unit.defense`, `scout.range`, `siege.damage`.
Only `production.*` and `storage.*` are consumed by a live code path today
(`GameDataCatalog::effectsForBuildings`); the rest are authored now so the dataset is
complete and are inert until the phase that reads them. That is deliberate and must be
noted in the SUMMARY — an inert target is not a dangling reference.

**Required graph properties.** Include, at minimum:
- `agriculture` (economy, tier 1, no tech prerequisite) → effects `multiply production.food`
  at 1100 / 1200 / 1300 across levels 1–3.
- `masonry` (construction, tier 1) → `multiply build.speed`.
- `logistics_core` (logistics, tier 2) requiring `agriculture` level 2 **and**
  `masonry` level 1 — the two-prerequisite case.
- `siege_engineering` (siege, tier 3) requiring `masonry` level 3 (a cross-category
  edge, construction → siege) — this is the case 10-04's detail-sheet chips must
  label with the prerequisite's own category.
- Fill the remaining categories (military, defense, exploration, alliance) with at
  least two technologies each so the UI-SPEC's eight category sections are all
  non-empty.

**Level-1 cost budget.** The starter city holds 500 food / 500 wood / 500 stone /
250 iron / 100 gold (read `starter.json` to confirm before authoring). Every tier-1
level-1 cost must be affordable from that grant alone, or 10-03's happy-path test
cannot start a research without a resource grant fixture. Keep tier-1 level-1 under
200 of any single resource.

**Durations.** Tier 1 level 1 is 30 seconds, rising with level and tier. Keep every
tier-1 duration under 120 seconds so 10-03's tests can advance a frozen clock past
them without absurd numbers.

**Translation keys.** For every technology add to all three of
`packages/localization/locales/{en,pt-BR,es}/mvp.json`:
- `technologies.<code>` — the display name
- `technologies.<code>_desc` — a one-line description of what it does

Insert them into the existing `technologies` namespace if one exists, otherwise create
it, following the file's existing nesting convention exactly (read en/mvp.json first —
Phase 09's locale additions are the model). Translate genuinely into pt-BR and es; do
not copy the English string into all three.
  </action>

  <acceptance_criteria>
    - `python3 -c "import json;d=json.load(open('packages/game-data/data/technologies.json'));assert isinstance(d,list) and len(d)>=16"` exits 0
    - All eight categories appear: `python3 -c "import json;d=json.load(open('packages/game-data/data/technologies.json'));c={t['category'] for t in d};assert c=={'economy','military','defense','logistics','construction','exploration','alliance','siege'}, c"` exits 0
    - Every technology has 3 levels: `python3 -c "import json;d=json.load(open('packages/game-data/data/technologies.json'));assert all(len(t['levels'])==3 for t in d)"` exits 0
    - No float anywhere: `grep -E '[0-9]+\.[0-9]+' packages/game-data/data/technologies.json` returns nothing
    - At least one two-prerequisite level: `python3 -c "import json;d=json.load(open('packages/game-data/data/technologies.json'));assert any(len(l['requirements'])>=2 for t in d for l in t['levels'])"` exits 0
    - At least one cross-category dependency: `python3 -c "import json;d=json.load(open('packages/game-data/data/technologies.json'));cat={t['code']:t['category'] for t in d};assert any(r['type']=='technology' and cat.get(r['code']) not in (None,t['category']) for t in d for l in t['levels'] for r in l['requirements'])"` exits 0
    - Every name_key and description_key resolves in all three locales: `python3 -c "
import json
d=json.load(open('packages/game-data/data/technologies.json'))
for loc in ['en','pt-BR','es']:
    m=json.load(open(f'packages/localization/locales/{loc}/mvp.json'))
    def has(k):
        cur=m
        for part in k.split('.'):
            if not isinstance(cur,dict) or part not in cur: return False
            cur=cur[part]
        return True
    missing=[k for t in d for k in (t['name_key'],t['description_key']) if not has(k)]
    assert not missing, (loc, missing[:5])
"` exits 0
    - The three locale files stay valid JSON and pt-BR differs from en for at least one technology name (proving real translation, not a copy)
    - `npm run gamedata:validate` exits 0 — the existing validator already checks cycles, duplicates, negatives and dangling refs, so the authored tree must be clean under it before 10-02 extends it
  </acceptance_criteria>

  <verify>
    <automated>npm run gamedata:validate</automated>
  </verify>

  <done>
    A designer-authored technology tree exists with all eight categories, a genuine
    DAG including a two-prerequisite node and a cross-category edge, integer permille
    effects, and complete translations in three languages.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: The catalogue reads technologies, and the import command counts them</name>

  <read_first>
    - apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php (the whole file — `buildingLevel`, `effectsForBuildings`, `starterValues` and however the file caches/loads a dataset; mirror that mechanism exactly rather than inventing a second loader)
    - apps/api/modules/Shared/Interface/Console/ImportGameDataCommand.php (Phase 09's 7 validation rules and its output format — "buildings: 18 definitions, 54 levels")
    - apps/api/tests/Feature/GameData/GameDataImportTest.php (the test conventions to follow)
    - apps/api/tests/Architecture/ArchitectureTest.php (the "keeps cost, duration and effect tables out of PHP" test — confirm technologies.json does not need it widened, and widen it if it does)
  </read_first>

  <files>
    apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php,
    apps/api/modules/Shared/Interface/Console/ImportGameDataCommand.php,
    apps/api/tests/Feature/GameData/TechnologyCatalogueTest.php
  </files>

  <behavior>
    - `GameDataCatalog::technologies(): array` returns every authored technology
    - `GameDataCatalog::technologyLevel(string $code, int $level): ?array` returns one level, or null for an unknown code or an out-of-range level — mirroring `buildingLevel`'s contract exactly, including returning null rather than throwing
    - `php artisan game:import-data` reports the technology count in the same format it reports buildings
    - The import command fails, naming the offending code, when a technology level is missing from its `levels[]` sequence, when `max_level` disagrees with the number of levels, or when a `name_key`/`description_key` does not match the `technologies.<code>` convention
    - No cost, duration or effect value appears in PHP — the existing architecture test still passes
  </behavior>

  <action>
**3a. `GameDataCatalog`.** Add `technologies()` and `technologyLevel()` mirroring the
existing building methods. Read the file first: whatever mechanism `buildingLevel`
uses to load and memoise `buildings.json`, reuse it for `technologies.json` rather
than adding a second, differently-behaved loader. If the existing loader is generic
over a filename, call it with `'technologies'`; if it is hardcoded to buildings,
generalise it and keep `buildingLevel`'s signature and behaviour unchanged.

`technologyLevel` returns the level array (`level`, `cost`, `research_time_seconds`,
`requirements`, `effects`) or `null`. Match `buildingLevel`'s null-vs-throw behaviour
exactly — 09-04's `BuildingRequirementEvaluator` depends on `null` meaning "no such
level", and 10-03 will reuse that evaluator against technologies.

**3b. `ImportGameDataCommand`.** Add technology validation alongside the existing
building rules, in the same style (collect problems, name every offending code, fail
with a non-zero exit). Add these three rules:

1. `levels[]` must be a contiguous 1..N sequence — report
   `technology "<code>" has levels [1,2,4]; expected a contiguous 1..3` when it is not.
2. `max_level` must equal `count(levels)` — report
   `technology "<code>" declares max_level 3 but authors 2 levels`.
3. `name_key` must equal `technologies.<code>` and `description_key` must equal
   `technologies.<code>_desc` — report
   `technology "<code>" has name_key "<actual>"; expected "technologies.<code>"`.

Extend the success output to report technologies the way buildings are reported, e.g.
`technologies: 16 definitions, 48 levels`. Read the existing output line and match its
exact phrasing and punctuation.

**3c. Tests** — `apps/api/tests/Feature/GameData/TechnologyCatalogueTest.php`:

- *"reads every authored technology from game data"* — `app(GameDataCatalog::class)->technologies()`
  returns ≥ 16 entries and every one has a `code`, `category` and 3 `levels`.
- *"returns null for an unknown technology or an out-of-range level"* — asserts
  `technologyLevel('does_not_exist', 1)` and `technologyLevel('agriculture', 99)` are
  both `null`, matching `buildingLevel`'s contract.
- *"imports the technology catalogue and reports its size"* — runs
  `Artisan::call('game:import-data')`, asserts exit code 0 and that
  `Artisan::output()` contains `technologies:`. (Use `Artisan::call`/`Artisan::output`,
  not `$this->artisan()->expectsOutputToContain()` — Phase 09 found the latter cannot
  verify two substrings on one output line, a Mockery limitation.)
- *"names the technology whose levels do not match its max_level"* — write a temporary
  malformed dataset to a temp path, point `config(['game.data_path' => ...])` at it,
  run the command, assert non-zero exit and that the output contains the offending
  code. Restore config afterwards. Read how `GameDataImportTest` does its own
  bad-dataset case and follow it exactly rather than inventing a second technique.
  </action>

  <acceptance_criteria>
    - `grep -c "function technologies" apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php` is 1
    - `grep -c "function technologyLevel" apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php` is 1
    - `grep -cE "[0-9]{2,}" apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php` shows no balance table — manually confirm no cost, duration or permille literal was added
    - `docker compose exec -T api php artisan game:import-data` exits 0 and its output contains `technologies:`
    - `docker compose exec -T api ./vendor/bin/pest --filter=TechnologyCatalogue` reports 4 passing tests
    - `docker compose exec -T api ./vendor/bin/pest --group=arch` is green — in particular "keeps cost, duration and effect tables out of PHP" still passes
    - `docker compose exec -T api ./vendor/bin/phpstan analyse --memory-limit=1G` reports 0 errors
    - `docker compose exec -T api ./vendor/bin/pint --test` is clean
  </acceptance_criteria>

  <verify>
    <automated>docker compose exec -T api ./vendor/bin/pest --filter=TechnologyCatalogue &amp;&amp; docker compose exec -T api php artisan game:import-data</automated>
  </verify>

  <done>
    The server reads the technology tree from versioned game data through the same
    catalogue that reads buildings, the import command validates and counts it, and
    no balance number entered PHP.
  </done>
</task>

</tasks>

<verification>
- `npm run gamedata:validate` — exits 0
- `npm run typecheck && npm run lint` — clean
- `docker compose exec -T api ./vendor/bin/pest` — green, ≥ 4 new tests
- `docker compose exec -T api ./vendor/bin/phpstan analyse --memory-limit=1G` — 0 errors
- `docker compose exec -T api ./vendor/bin/pint --test` — clean
- `docker compose exec -T api php artisan game:import-data` — exits 0, reports both buildings and technologies
- No file under `apps/api/modules/` contains a technology cost, duration or permille literal
</verification>
