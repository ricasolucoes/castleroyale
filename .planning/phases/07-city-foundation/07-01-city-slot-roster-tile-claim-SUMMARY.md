---
phase: 07-city-foundation
plan: 01
subsystem: api
tags: [game-data, laravel, eloquent, pest, sqlite, postgresql, concurrency]

# Dependency graph
requires:
  - phase: 00-repository-bootstrap
    provides: Modular monolith skeleton, GameDataCatalog adapter, ErrorCode enum
provides:
  - Fixed 18-plot city slot roster as versioned game data (city-slots.json)
  - Starter buildings pinned to named plots, validated at CI time
  - GameDataCatalog::citySlots() strict roster reader
  - Constraint-backed tile claim: unique index is the authority, not the pre-check
  - Legacy data-fix migration mapping pre-roster slot=code rows onto plots
affects: [07-02-city-state-slots-api, 07-03-city-scene-rendering, 09-construction]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Roster-as-data: a fixed addressable set (plots) authored as JSON array, separate from what occupies it (city_buildings rows)"
    - "Constraint-as-authority: pre-check for a friendly refusal, unique index catch for the real guarantee, proven by a model-event-driven interleaving test"

key-files:
  created:
    - packages/game-data/data/city-slots.json
    - apps/api/database/migrations/2026_09_05_000000_map_city_building_slots_to_plot_roster.php
    - apps/api/tests/Feature/City/CitySlotRosterTest.php
    - apps/api/tests/Feature/City/CityTileClaimTest.php
  modified:
    - packages/game-data/data/starter.json
    - packages/game-data/src/index.ts
    - packages/game-data/src/validate.ts
    - apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php
    - apps/api/modules/Player/Application/GameBootstrapService.php

key-decisions:
  - "Roster sized to 18 plots, one per building documented in docs/game-design/buildings.md — Phase 09 adds buildings by editing JSON, never renumbering a slot."
  - "Plot ids are building-agnostic (plot_NN), not building names — which building may occupy which plot is a Phase 09 placement rule."
  - "Empty slots are not database rows; city_buildings holds only occupied slots and unique(world_id, city_id, slot) already guarantees at most one building per slot."
  - "The exists() pre-check on tile claim stays as a cheap, friendly refusal, but a QueryException catch on City::create matching the cities_world_id_x_y_unique index name (and its literal SQLite message form) is the actual authority, translated to ErrorCode::TileOccupied."

patterns-established:
  - "Game-data validator: dataset-specific dangling-reference checks (roster membership) live as a dedicated block in validate.ts alongside the generic per-file loop, when the generic requirements-graph check cannot express the rule."
  - "Race proof without two DB connections: an eloquent.creating listener inserts the rival row between the SELECT pre-check and the INSERT, reproducing the exact interleaving SQLite in-memory otherwise cannot."

requirements-completed: [REQ-01]

# Metrics
duration: 19min
completed: 2026-09-05
---

# Phase 07 Plan 01: City slot roster and constraint-backed tile claim Summary

**18-plot fixed city slot roster authored as validated game data, plus a `QueryException`-catch on `City::create` that makes the `cities_world_id_x_y_unique` index — not the pre-check `SELECT` — the actual authority refusing a double tile claim.**

## Performance

- **Duration:** ~19 min
- **Started:** 2026-09-05T17:14:00Z (approx.)
- **Completed:** 2026-09-05T17:33:00Z
- **Tasks:** 3
- **Files modified:** 9 (4 created, 5 modified)

## Accomplishments

- A city now has a fixed, stable, addressable roster of 18 build plots (`plot_01`..`plot_18`) that exists independently of which buildings are built, authored in `packages/game-data/data/city-slots.json` and validated in CI.
- Every starter building is pinned to a named plot in `starter.json`; `GameDataCatalog::starterBuildings()` now throws on a missing or unknown slot instead of silently aliasing the building code — the bug the phase was named to fix.
- A tile claim that survives the `exists()` pre-check is still refused by the database's `cities_world_id_x_y_unique` constraint, translated to `ErrorCode::TileOccupied` (409) instead of leaking a 500.
- A legacy data-fix migration remaps any pre-roster `city_buildings.slot` value (previously the building code) onto its assigned plot, so no existing row goes invisible under a roster-driven read.
- An architecture test forbids any `plot_NN` literal anywhere under `apps/api/modules/` — the roster's identity lives only in game data.

## Task Commits

Each task was committed atomically:

1. **Task 1: Author the fixed slot roster as game data and validate it** - `cfaacec` (feat)
2. **Task 2: Read the roster in PHP strictly, and migrate legacy slot values** - `24231c8` (feat)
3. **Task 3: Make the unique index — not the pre-check — refuse a double tile claim** - `cd57d2c` (fix)

**Plan metadata:** (this commit, following)

## Files Created/Modified

- `packages/game-data/data/city-slots.json` - The 18-plot roster (`plot_01`..`plot_18`), authored as a JSON array
- `packages/game-data/data/starter.json` - Each of the 5 starter buildings pinned to a named plot
- `packages/game-data/src/index.ts` - `CitySlot` type; `DATASET_NAMES` extended with `'city-slots'`
- `packages/game-data/src/validate.ts` - Dedicated roster shape check plus starter-slot dangling-reference/duplicate checks
- `apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php` - `citySlots()` reader; `starterBuildings()` requires an explicit, roster-valid slot
- `apps/api/database/migrations/2026_09_05_000000_map_city_building_slots_to_plot_roster.php` - Maps the 5 legacy `slot = building_code` rows onto their plots
- `apps/api/tests/Feature/City/CitySlotRosterTest.php` - Roster persistence, roster shape (18 entries, no duplicates), and the `plot_NN`-out-of-PHP architecture rule
- `apps/api/modules/Player/Application/GameBootstrapService.php` - `City::create` wrapped in a `QueryException` catch translating the unique-index violation to `TileOccupied`
- `apps/api/tests/Feature/City/CityTileClaimTest.php` - Pre-check refusal test, and the constraint-race test using an `eloquent.creating` listener to interleave a rival insert

## Decisions Made

- **Roster size (18):** matches the 18 buildings documented in `docs/game-design/buildings.md`; sizing to the documented catalogue means Phase 09 never renumbers a shipped slot.
- **Plot naming (`plot_NN`, not building names):** which building may occupy which plot is a Phase 09 construction-placement rule, not this plan's concern; the UI copy already says "plot" (`city.slot_empty`).
- **No schema change for empty slots:** the roster is pure data; `city_buildings` already enforces `unique(world_id, city_id, slot)`, so "at most one building per slot" needed no new column or table.
- **Pre-check demoted, not removed:** kept for a fast, friendly refusal in the common case; the unique index is now the actual authority, proven by commenting out the pre-check and confirming the race test still passes on the constraint alone (verified during Task 3, then restored).

## Deviations from Plan

None — plan executed exactly as written. All acceptance criteria greps, the typo-injection roster-validation check, the pre-check-removal proof, and every quality gate matched the plan's specification without needing an unplanned fix.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Plan 07-02 (city state / slots API) can now read `GameDataCatalog::citySlots()` for a stable roster order and rely on `starterBuildings()` throwing loudly on bad data instead of aliasing.
- Plan 07-03/07-04 (city scene rendering) can address plots by a stable id that will never be renumbered when Phase 09 adds buildings.
- No blockers. The whole backend suite (136 tests, 1072 assertions), PHPStan, and Pint are green; `npm run typecheck`, `npm run lint`, and `npm test` (repo root) are unaffected and green.

---
*Phase: 07-city-foundation*
*Completed: 2026-09-05*

## Self-Check: PASSED

All created files found on disk; all three task commits (`cfaacec`, `24231c8`, `cd57d2c`) found in git history.
