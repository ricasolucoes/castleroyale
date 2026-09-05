---
phase: 08-resources-economy
plan: 01
subsystem: api
tags: [openapi, contracts, economy, php, laravel, typescript]

# Dependency graph
requires:
  - phase: 07-city-foundation
    provides: CityStateService, CityEconomyService.balances/capacities, GET /game/city
provides:
  - "ResourceRate OpenAPI schema, CityResources.rate required on every city read"
  - "CityEconomyService::ratesPerHour() deriving signed integer units/hour from game-data production effects"
  - "GET /api/v1/game/city now returns data.resources.rate alongside current/capacity"
  - "Test proof that the published hourly rate equals a real hour of ledgered accrual, cap included"
affects: [08-05-mobile-resource-bar]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Wire unit conversion (perSecond * 3600) lives in the Application layer, never changes the game-data authored unit"
    - "Read accessors (balances/capacities/ratesPerHour) grouped together as the three public query methods on CityEconomyService"

key-files:
  created:
    - apps/api/tests/Feature/Economy/ProductionRateTest.php
  modified:
    - packages/contracts/openapi.yaml
    - packages/contracts/src/generated/api.ts
    - apps/api/modules/Economy/Application/CityEconomyService.php
    - apps/api/modules/City/Application/CityStateService.php
    - docs/game-design/economy.md

key-decisions:
  - "Wire rate is signed integer units per hour (perSecond * 3600); game-data production effect stays per-second untouched, per locked 08-CONTEXT.md decision"
  - "ratesPerHour() computed after accrueLocked() and city->refresh() in CityStateService so it reflects any building that just completed on this same read"
  - "Reflowed docs/game-design/economy.md's new paragraph so 'units per second' is not split across a markdown line wrap, matching the plan's literal acceptance grep"

patterns-established:
  - "Signed ResourceRate schema (no minimum) declared now even though every value is >=0 until Phase 12 ships upkeep, to avoid a future contract rewrite"

requirements-completed: [REQ-02]

# Metrics
duration: 10min
completed: 2026-09-05
---

# Phase 08 Plan 01: Production Rate Contract Summary

**Published `CityResources.rate` (signed integer units/hour) on the OpenAPI contract and `GET /game/city`, derived from game-data production effects via `CityEconomyService::ratesPerHour()`, with a test tying the published figure to a real hour of ledgered accrual.**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-09-05T23:20:00Z (approx)
- **Completed:** 2026-09-05T23:30:00Z
- **Tasks:** 3 completed
- **Files modified:** 6 (1 new test file, 5 modified)

## Accomplishments
- `ResourceRate` schema added to `packages/contracts/openapi.yaml`; `CityResources.rate` is now required and the generated TypeScript matches byte-for-byte (`npm run contracts:check` green)
- `CityEconomyService::ratesPerHour()` derives the wire rate as an exact integer (`perSecond * 3600`), never touching the game-data-authored per-second value
- `GET /api/v1/game/city` returns `data.resources.rate` with all five resource keys, computed after accrual so it reflects buildings completed on the same read
- New `ProductionRateTest.php` proves: (1) a starter city's rate is `3600` for each level-1 producer and `0` otherwise, (2) an hour of real accrual sums (via the ledger, `amount + overflow_amount`) to exactly the published rate even when the warehouse cap discards overflow, and (3) the HTTP payload carries both `rate` and game-data-derived `capacity`
- Full repository gate suite confirmed green: 146 Pest tests / 1110 assertions, 0 PHPStan errors, Pint clean, `npm run typecheck`/`lint`/`test` clean (11 suites / 58 tests), `contracts:check` clean

## Task Commits

Each task was committed atomically (Task 2 used TDD: RED then GREEN):

1. **Task 1: Add ResourceRate to the OpenAPI contract and regenerate the TS types** - `aae3613` (feat)
2. **Task 2 (RED): Add failing ProductionRateTest** - `e20ca22` (test)
2. **Task 2 (GREEN): Derive ratesPerHour and publish it on the city read** - `59c2f26` (feat)
3. **Task 3: Run the full gate** - no code changes required; every gate passed on first run after Task 2's GREEN commit

## Files Created/Modified
- `packages/contracts/openapi.yaml` - `ResourceRate` schema (signed, no minimum), `CityResources.required: [current, capacity, rate]`
- `packages/contracts/src/generated/api.ts` - regenerated from the YAML (ADR-017, never hand-edited)
- `apps/api/modules/Economy/Application/CityEconomyService.php` - `SECONDS_PER_HOUR` constant, `ratesPerHour()` public method placed alongside `balances()`/`capacities()`
- `apps/api/modules/City/Application/CityStateService.php` - `resources.rate` added to the `handle()` response, computed after `accrueLocked()`
- `apps/api/tests/Feature/Economy/ProductionRateTest.php` - three tests: unit-rate correctness, hour-of-accrual equivalence via the ledger, and the HTTP contract
- `docs/game-design/economy.md` - documents the per-second (game-data) vs per-hour (wire) unit split

## Decisions Made
- Kept the wire unit exactly as locked in `08-CONTEXT.md`/`08-UI-SPEC.md`: signed integer units per hour, `perSecond * 3600`, no float, no rounding — this is what 08-05's `interpolateResources` (`/ 3_600_000` ms) already assumes, so no divisor change is needed downstream.
- Declared `ResourceRate` with no `minimum` (deliberately signed) even though every value is currently `>= 0`, avoiding a contract rewrite when Phase 12 ships troop upkeep.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Reflowed a markdown paragraph so the plan's own acceptance grep would pass**
- **Found during:** Task 2, acceptance-criteria verification
- **Issue:** The plan's literal markdown snippet for `docs/game-design/economy.md` wrapped "units per" and "second" onto separate lines (a cosmetic line-wrap in the plan's action text). `grep -q "units per second"` — the plan's own stated acceptance check — does not match text split across two lines, so pasting the snippet verbatim would have failed the plan's own gate.
- **Fix:** Reflowed the paragraph so "units per second" (and the "units per hour" phrase) sit on a single line each. Content and meaning are unchanged; only the line-wrap position moved.
- **Files modified:** `docs/game-design/economy.md`
- **Verification:** `grep -q "units per second" docs/game-design/economy.md` now succeeds; `pint --test` still passes (markdown is unaffected by Pint but the surrounding PHP was unchanged anyway).
- **Committed in:** `59c2f26` (Task 2 GREEN commit)

---

**Total deviations:** 1 auto-fixed (1 blocking — markdown line-wrap vs. literal grep)
**Impact on plan:** Cosmetic only; no change to game-design content, no scope creep.

## Issues Encountered
None beyond the deviation above.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Plan 08-05 (mobile resource bar) is unblocked: `CityResources.rate` exists exactly as `08-UI-SPEC.md`'s Data Contract Dependency specified — signed integer units per hour, sibling to `current`/`capacity`.
- ROADMAP criterion 1 (elapsed-time accrual honesty) is now reinforced at the read layer: a test proves the published rate is exactly what an hour of real accrual produces, cap included.
- No balance number moved into PHP; `ratesPerHour()` is a pure unit conversion of a game-data-authored value, preserving ADR-013's data-driven-balancing rule.
- Plans 08-02 through 08-04 (capacity/credit strictness, ledger append-only, locked spending/concurrency) are untouched and remain to be executed.

## Self-Check: PASSED

All claimed files and commits verified to exist:
- FOUND: apps/api/tests/Feature/Economy/ProductionRateTest.php
- FOUND: .planning/phases/08-resources-economy/08-01-production-rate-contract-SUMMARY.md
- FOUND: ResourceRate in packages/contracts/openapi.yaml
- FOUND: ratesPerHour in CityEconomyService.php
- FOUND: commit aae3613
- FOUND: commit e20ca22
- FOUND: commit 59c2f26

---
*Phase: 08-resources-economy*
*Completed: 2026-09-05*
