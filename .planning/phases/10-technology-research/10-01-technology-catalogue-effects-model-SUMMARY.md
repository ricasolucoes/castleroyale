---
phase: 10-technology-research
plan: 01
subsystem: game-data
tags: [json-schema, laravel, pest, technology-tree, permille, i18n]

# Dependency graph
requires:
  - phase: 09-buildings-construction
    provides: "The buildings.schema.json requirement/effect/resourceCost $defs vocabulary and the GameDataCatalog/ImportGameDataCommand pattern this plan mirrors"
provides:
  - "packages/game-data/schema/technologies.schema.json — JSON Schema 2020-12 contract for the technology dataset"
  - "packages/game-data/data/technologies.json — 16-technology DAG across all 8 categories, integer permille effects"
  - "technologies.<code> / technologies.<code>_desc translation keys in en, pt-BR, es"
  - "GameDataCatalog::technologies()/technology()/technologyLevel() — the read path 10-03 and 10-04 will call"
  - "game:import-data technology validation (level-sequence, max_level, name/description key convention)"
affects: [10-02-validator-completion, 10-03-research-queue-effects, 10-04-mobile-technology-tree, 10-05-research-command-reconciler]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Technology dataset reuses buildings.schema.json's requirement/effect/resourceCost/wholeUnits $defs byte-for-byte, never a second cost vocabulary"
    - "GameDataCatalog::technologyLevel() mirrors buildingLevel()'s null-vs-throw contract exactly, over the same generic read() loader"
    - "ImportGameDataCommand technology checks follow the building checks' shape: collect problems, name every offending code, single sprintf-built message per rule"

key-files:
  created:
    - packages/game-data/schema/technologies.schema.json
    - packages/game-data/data/technologies.json
    - apps/api/tests/Feature/GameData/TechnologyCatalogueTest.php
  modified:
    - packages/localization/locales/en/mvp.json
    - packages/localization/locales/pt-BR/mvp.json
    - packages/localization/locales/es/mvp.json
    - apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php
    - apps/api/modules/Shared/Interface/Console/ImportGameDataCommand.php

key-decisions:
  - "16 technologies, 2 per category (economy, military, defense, logistics, construction, exploration, alliance, siege), each with 3 levels — the minimum the plan required, kept deliberately small so 10-02/10-03/10-04 exercise a real but reviewable graph"
  - "alliance technologies (diplomatic_relations, trade_agreements) are taxonomy placeholders per 10-CONTEXT.md's Phase 22 deferral, using the plan's suggested inert targets (unit.defense, march.speed) rather than an off-contract alliance.* target"
  - "Only production.* and storage.* effect targets are consumed by a live code path today (GameDataCatalog::effectsForBuildings); build.speed, march.speed, unit.attack, unit.defense, scout.range and siege.damage are authored now and inert until a later phase reads them — not a dangling reference"
  - "Technology level requirements are placed only at a technology's unlocking level (its own level 1); levels 2/3 carry empty requirements arrays, since nothing in the plan or the existing validator needs per-level re-gating the way buildings re-gate against the Palace at every level"

requirements-completed: [REQ-06]

# Metrics
duration: 20min
completed: 2026-09-08
---

# Phase 10 Plan 01: Technology Catalogue & Effects Model Summary

**Authored a 16-technology DAG across all 8 categories as versioned JSON game data, with a JSON Schema 2020-12 contract reusing buildings' requirement/effect vocabulary verbatim, translations in three locales, and a GameDataCatalog/import-command read path mirroring the existing buildings pattern exactly — no gameplay logic, no balance number in PHP.**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-09-08T16:05:00Z (approx.)
- **Completed:** 2026-09-08T16:15:22Z
- **Tasks:** 3
- **Files modified:** 8 (3 created, 5 modified)

## Accomplishments
- `packages/game-data/schema/technologies.schema.json` — JSON Schema 2020-12, `resourceCost`/`wholeUnits`/`requirement`/`effect` `$defs` copied byte-identical from `buildings.schema.json` (proven by an assertion diff during verification), category enum locked to the eight documented values
- `packages/game-data/data/technologies.json` — 16 technologies, 3 levels each, a genuine DAG: `logistics_core` requires both `agriculture` L2 and `masonry` L1 (two-prerequisite case), `siege_engineering` requires `masonry` L3 (cross-category construction→siege edge); every tier-1 technology's level-1 cost is affordable from the starter grant alone and under 200 of any single resource
- Translation keys (`technologies.<code>` / `technologies.<code>_desc`) added to `en`, `pt-BR` and `es` `mvp.json`, genuinely translated (verified pt-BR differs from en)
- `GameDataCatalog::technologies()` / `technology()` / `technologyLevel()` added, mirroring `buildingLevel()`'s null-vs-throw contract over the same generic `read()` loader
- `game:import-data` now validates and reports technologies alongside buildings: contiguous `levels[]` sequence, `max_level` agreement, and `name_key`/`description_key` convention, each failure naming the offending code
- `TechnologyCatalogueTest.php` — 4 new Pest tests, all passing

## Task Commits

Each task was committed atomically:

1. **Task 1: The technologies schema** - `2d3181f` (feat)
2. **Task 2: The authored technology tree and its translation keys** - `c8908ba` (feat)
3. **Task 3: The catalogue reads technologies, and the import command counts them** - `92b4d10` (feat)

**Plan metadata:** pending (this commit)

## Files Created/Modified
- `packages/game-data/schema/technologies.schema.json` - JSON Schema 2020-12 contract for the technology dataset, reusing buildings' shared `$defs`
- `packages/game-data/data/technologies.json` - the authored 16-technology DAG
- `packages/localization/locales/en/mvp.json` - new `technologies` namespace, English names/descriptions
- `packages/localization/locales/pt-BR/mvp.json` - new `technologies` namespace, Portuguese translations
- `packages/localization/locales/es/mvp.json` - new `technologies` namespace, Spanish translations
- `apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php` - `technologies()`, `technology()`, `technologyLevel()`
- `apps/api/modules/Shared/Interface/Console/ImportGameDataCommand.php` - three new technology validation rules and the `technologies: N definitions, M levels` report line
- `apps/api/tests/Feature/GameData/TechnologyCatalogueTest.php` - read path, null contract, import success and import failure tests (reuses `gameDataImportTempBundle()`/`gameDataImportCleanTempBundle()` from `GameDataImportTest.php` rather than reinventing the temp-bundle technique)

## Decisions Made
- Kept the catalogue to exactly 16 technologies (2 per category) rather than authoring more — satisfies every `must_haves` acceptance criterion (≥16, all 8 categories, two-prerequisite case, cross-category edge) without inflating the dataset 10-02/10-03/10-04 must also handle.
- Placed technology-type requirements only on the unlocking level of a gated technology (e.g., `logistics_core` level 1, `tactics` level 1), leaving levels 2/3 requirement-free — buildings re-gate every level against the Palace because the Palace is a single shared gate; technologies don't have an equivalent single shared gate, so re-checking the same prerequisite at every level would be redundant, and nothing in the plan or `validate.ts` requires it.
- Alliance technologies use `unit.defense` and `march.speed` (the plan's own suggested inert targets) rather than inventing an `alliance.*` vocabulary, per 10-CONTEXT.md's explicit Phase 22 deferral of the alliance *system*, not the taxonomy.

## Deviations from Plan

None — plan executed exactly as written. All acceptance criteria in the plan (schema `$defs` identity check, category enumeration, dataset shape/DAG properties, translation resolution, `npm run gamedata:validate`, the four Pest tests, `game:import-data` output, architecture test, PHPStan, Pint) were verified directly and pass.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- 10-02 (validator completion) can extend `packages/game-data/src/validate.ts`'s existing `technologies → 'technology'` mapping against a real, non-trivial dataset (two-prerequisite node, cross-category edge, multiple independent tier-1 entry points).
- 10-03 (research queue) has a `GameDataCatalog::technologyLevel()` read path with the exact null contract `BuildingRequirementEvaluator` already depends on, so requirement evaluation can be reused rather than reimplemented.
- 10-04 (mobile tree screen) has all eight category codes populated (2 technologies each) to render against the UI-SPEC's category icon map, plus real chip-worthy prerequisite chains (two-prerequisite, cross-category) to exercise the detail sheet's rendering logic.
- No blockers.

---
*Phase: 10-technology-research*
*Completed: 2026-09-08*

## Self-Check: PASSED

All created files verified present on disk; all three task commit hashes (2d3181f, c8908ba, 92b4d10) verified present in `git log`.
