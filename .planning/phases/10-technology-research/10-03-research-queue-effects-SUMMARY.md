---
phase: 10-technology-research
plan: 03
subsystem: api
tags: [laravel, pest, eloquent, permille, graph-algorithm, effects, sqlite, postgresql]

# Dependency graph
requires:
  - phase: 10-technology-research
    provides: "10-01's technologies.json dataset, GameDataCatalog::technologies()/technology()/technologyLevel() read path"
provides:
  - "player_technologies / research_orders tables with a database-level one-open-research-per-player invariant"
  - "Game\\Technology\\Domain\\EffectResolver — the pure add-then-multiply permille resolver shared by buildings, technologies and (Phase 13) hero bonuses"
  - "Game\\Technology\\Domain\\TechnologyGraph — tier (topological depth) and level-1 prerequisites, the data the 10-04 tree screen renders lanes from"
  - "Game\\Technology\\Application\\ResearchCompletionService — idempotent research completion, mirroring ConstructionCompletionService"
  - "GameDataCatalog::effectsFor()/effectsForTechnologies() and CityEconomyService now folding a city owner's researched technologies into rates/capacity"
affects: [10-05-research-command-reconciler, 10-04-mobile-technology-tree, 13-heroes]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "EffectResolver::resolve(baseline, effectSets) is the one place any target/operation/value effect descriptor becomes a number — buildings and technologies both funnel through GameDataCatalog::effectsFor(), never a second accumulator"
    - "TechnologyGraph is constructed from a raw catalogue array (not GameDataCatalog itself), memoises tier() per instance, and throws an anonymous DomainException subclass on a cycle rather than importing a second exception class"
    - "Game\\Technology\\Application and Game\\Technology\\Infrastructure joined the architecture test's framework-purity ignore list (mirroring every other module); Game\\Technology\\Domain deliberately did not, and both EffectResolver.php and TechnologyGraph.php import at most one framework-adjacent class"
    - "Research completion mirrors construction completion exactly: whereNull('completed_at') + lockForUpdate, one CityStateChanged broadcast per completed order via the order's city channel (no player-scoped channel exists yet)"

key-files:
  created:
    - apps/api/database/migrations/2026_09_07_000100_create_player_technologies_tables.php
    - apps/api/modules/Technology/Infrastructure/PlayerTechnology.php
    - apps/api/modules/Technology/Infrastructure/ResearchOrder.php
    - apps/api/modules/Technology/Domain/EffectResolver.php
    - apps/api/modules/Technology/Domain/TechnologyGraph.php
    - apps/api/modules/Technology/Application/ResearchCompletionService.php
    - apps/api/tests/Feature/Technology/EffectResolverTest.php
    - apps/api/tests/Feature/Technology/TechnologyGraphTest.php
    - apps/api/tests/Feature/Technology/ResearchCompletionServiceTest.php
  modified:
    - apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php
    - apps/api/modules/Economy/Application/CityEconomyService.php
    - apps/api/tests/Architecture/ArchitectureTest.php

key-decisions:
  - "research_orders_one_open_per_player is an unconditional partial unique index (no driver guard) — verified directly that SQLite (the default Pest suite's driver) and PostgreSQL (Docker's actual DB_CONNECTION=pgsql) both accept CREATE UNIQUE INDEX ... WHERE completed_at IS NULL, so the invariant is a real database authority on both, not PostgreSQL-only"
  - "TechnologyGraph.prerequisites() filters to type==='technology' requirements only — a building-gated technology has no technology prerequisite and is tier 0, and prerequisites() is deliberately not the general BuildingRequirementEvaluator-style unlock check (that generalisation, hinted at in the plan's interfaces block, is not part of this plan's actual task list and was left untouched)"
  - "Cycle detection throws an anonymous class extending Game\\Shared\\Domain\\Exception\\DomainException at the throw site instead of a second named exception class, satisfying the plan's own constraint that TechnologyGraph.php import at most one class"
  - "Added a ResearchCompletionServiceTest.php not listed in the plan's files_modified — the plan's own trap section explicitly requires proving the completion guard against the exact code path it protects, and no test file was assigned to that behavior; manually verified during development that removing whereNull('completed_at') makes the double-call test fail (2 broadcasts instead of 1) before restoring the guard"

requirements-completed: [REQ-06, REQ-09]

# Metrics
duration: 16min
completed: 2026-09-08
---

# Phase 10 Plan 03: Research Queue, Timers and Effect Application Summary

**Research persistence with a database-enforced one-open-research-per-player invariant, a pure add-then-multiply permille EffectResolver now shared by buildings and technologies, a memoised TechnologyGraph computing tier and level-1 prerequisites, and an idempotent ResearchCompletionService — all with an unresearched empire producing byte-identical economy numbers to before this plan.**

## Performance

- **Duration:** ~16 min
- **Started:** 2026-09-08T16:16:58Z (approx., immediately after 10-01)
- **Completed:** 2026-09-08T16:32:12Z
- **Tasks:** 4
- **Files modified:** 12 (9 created, 3 modified)

## Accomplishments
- `player_technologies` / `research_orders` tables, with `research_orders_one_open_per_player` — a partial unique index proven to work on both SQLite (Pest's default driver) and PostgreSQL (Docker's actual database)
- `Game\Technology\Domain\EffectResolver`: add-before-multiply, permille surplus stacking additively (not multiplicatively), `intdiv` truncation per ADR-010, zero framework imports
- `GameDataCatalog::effectsFor()` replaces the old inline accumulator (which silently dropped any effect target the baseline hadn't seeded) — `effectsForBuildings()`'s signature survives unchanged, and the Economy test suite proves the refactor produced no numeric drift
- `CityEconomyService` now folds a city's owner's researched technologies into `ratesPerHour()` and `syncDerivedStatsLocked()`
- `Game\Technology\Domain\TechnologyGraph`: memoised `tier()` (topological depth over technology-type requirements only) and `prerequisites()` (always reads level 1, so a maxed technology keeps its unlock chain) — cyclic data throws naming both codes instead of recursing
- `Game\Technology\Application\ResearchCompletionService`: completes overdue research exactly once, guarded on `completed_at IS NULL`, one broadcast per completed order
- 14 new Pest tests (6 EffectResolver, 6 TechnologyGraph, 2 ResearchCompletionService), all passing; full suite 209 passed; PHPStan 0 errors; Pint clean

## Task Commits

Each task was committed atomically:

1. **Task 1: Persistence — researched technologies and research orders** - `4ed252a` (feat)
2. **Task 2: The shared effect resolver — add, then multiply by permille** - `0d1365b` (feat)
3. **Task 3: The technology graph — tier, and prerequisites that survive max level** - `770974b` (feat)
4. **Task 4: Completion — raising the level exactly once** - `820d8f8` (feat)

**Plan metadata:** pending (this commit)

## Files Created/Modified
- `apps/api/database/migrations/2026_09_07_000100_create_player_technologies_tables.php` - both tables, world-scoped, plus the partial unique index making "one research at a time" a database invariant
- `apps/api/modules/Technology/Infrastructure/PlayerTechnology.php` / `ResearchOrder.php` - Eloquent models mirroring `CityBuilding`/`ConstructionOrder` conventions
- `apps/api/modules/Technology/Domain/EffectResolver.php` - the pure resolver
- `apps/api/modules/Technology/Domain/TechnologyGraph.php` - tier and prerequisites
- `apps/api/modules/Technology/Application/ResearchCompletionService.php` - idempotent completion + broadcast
- `apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php` - `effectsFor()`/`effectsForTechnologies()`, `effectsForBuildings()` kept as a thin wrapper
- `apps/api/modules/Economy/Application/CityEconomyService.php` - `effects()` now also loads `PlayerTechnology` rows for the city's owner
- `apps/api/tests/Architecture/ArchitectureTest.php` - registered `Game\Technology\Application`/`Infrastructure`/`Interface` in the framework-purity ignore list
- `apps/api/tests/Feature/Technology/EffectResolverTest.php`, `TechnologyGraphTest.php`, `ResearchCompletionServiceTest.php` - the new coverage

## Decisions Made
- Ran the partial unique index unconditionally rather than guarding it on the database driver, after confirming both SQLite and PostgreSQL accept the exact `CREATE UNIQUE INDEX ... WHERE completed_at IS NULL` syntax (`migrate:fresh` against Docker's PostgreSQL, and the full Pest suite against SQLite, both green).
- Kept `TechnologyGraph::prerequisites()` scoped to `type === 'technology'` requirements, matching the task's explicit "ignores building requirements when computing tier" behavior; did not generalise `BuildingRequirementEvaluator` (mentioned in the plan's interfaces block as a hint for a future plan) since Task 3's actual action/files/acceptance-criteria never asked for it.
- Used an anonymous class extending `DomainException` at the cycle-detection throw site rather than adding a second named exception class, satisfying the "at most one `use` statement" constraint on `TechnologyGraph.php` literally.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Registered the Technology module in the architecture test's framework-purity ignore list**
- **Found during:** Task 1 (persistence)
- **Issue:** `PlayerTechnology`/`ResearchOrder` extend `Illuminate\Database\Eloquent\Model`, which the `domain layer stays free of the framework` architecture test rejects for any `Game\*` namespace not explicitly ignored — every other module's `Application`/`Infrastructure`/`Interface` layers are already in that ignore list, but `Game\Technology\*` was not yet, since this is the module's first plan to introduce framework-touching code.
- **Fix:** Added `Game\Technology\Application`, `Game\Technology\Infrastructure`, `Game\Technology\Interface` to the ignore list, mirroring every other module's entries exactly. `Game\Technology\Domain` was deliberately left covered by the rule.
- **Files modified:** `apps/api/tests/Architecture/ArchitectureTest.php`
- **Verification:** `pest --group=arch` green (16 passed); full suite green afterward.
- **Committed in:** `4ed252a` (Task 1 commit)

**2. [Rule 2 - Missing critical test coverage] Added ResearchCompletionServiceTest.php**
- **Found during:** Task 4 (completion)
- **Issue:** The plan's `files_modified` list for Task 4 names only `ResearchCompletionService.php` and `EffectResolverTest.php` — no test file exercises the completion service's own idempotency/broadcast behavior. But the plan's `must_haves` truth ("Completing a research raises the level exactly once, guarded on `completed_at IS NULL`") and its explicit trap warning (falsification must target the guard the test actually exercises, referencing 09-03's mistake of testing a job that short-circuits before reaching the service) both demand direct proof against `ResearchCompletionService` itself, which nothing in the assigned files would provide.
- **Fix:** Added `apps/api/tests/Feature/Technology/ResearchCompletionServiceTest.php` with two tests: calling the service twice completes once and broadcasts once, and completion respects `world_id`/`player_id` scoping. Manually verified the falsification by temporarily removing `whereNull('completed_at')` from the service — the double-call test failed (2 broadcasts instead of 1), unlike 09-03's original job-test attempt — then restored the guard.
- **Files modified:** `apps/api/tests/Feature/Technology/ResearchCompletionServiceTest.php`
- **Verification:** Guard-removed run failed as expected; guard-restored run passes (2 tests, 6 assertions); full suite green (209 passed).
- **Committed in:** `820d8f8` (Task 4 commit)

---

**Total deviations:** 2 auto-fixed (1 blocking, 1 missing critical test coverage)
**Impact on plan:** Both were necessary for the plan's own stated correctness and verification requirements. No scope creep — no file outside the Technology module's test/architecture surface was touched.

## Issues Encountered
None beyond the two deviations above.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- 10-05 (research command/reconciler) has `ResearchCompletionService::completeOverdueLocked()` to call before deciding whether a player is busy, `research_orders`/`player_technologies` tables with the one-open-research-per-player invariant already enforced by the database, and `EffectResolver`/`GameDataCatalog::effectsFor()` to resolve a technology's effect on completion.
- 10-04 (mobile technology tree) has `TechnologyGraph::tier()`/`tiers()`/`prerequisites()` ready to compute the `tier` and `prerequisites` fields `10-UI-SPEC.md`'s Data Contract requires, over the real 16-technology catalogue (max tier observed: 2, via `supply_lines` → `logistics_core` → {`agriculture`, `masonry`}).
- No blockers.

---
*Phase: 10-technology-research*
*Completed: 2026-09-08*

## Self-Check: PASSED

All 13 created/modified files verified present on disk; all four task commit hashes
(4ed252a, 0d1365b, 770974b, 820d8f8) verified present in `git log`.
