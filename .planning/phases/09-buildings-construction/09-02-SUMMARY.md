---
phase: 09-buildings-construction
plan: 02
subsystem: api
tags: [laravel, pest, openapi, contracts, react-native, construction-queue]

# Dependency graph
requires:
  - phase: 08-resources-economy
    provides: CityEconomyService (balances/costs), locked debit/credit path
  - phase: 07-world-map-rendering
    provides: CitySlot roster convention ("array always present, in full"), CityScene/CitySlotDetailSheet
provides:
  - "GET /game/city returns constructions[] (every open order, ordered by finishes_at) and queue_limit, replacing the singular construction field"
  - "Game\\Construction\\Domain\\BuildDuration::scaled() — the one place game.time_scale is applied, shared by the preview and the scheduler"
  - "openapi.yaml documents the upgrade endpoint's 400 (BUILDING_MAX_LEVEL, BUILDING_REQUIREMENTS_NOT_MET, BUILD_QUEUE_FULL, CITY_BUSY, INSUFFICIENT_RESOURCES)"
  - "CityScene/CitySlotDetailSheet resolve construction per building_code from the array; every plot with an open order ticks its own timer"
affects: [09-04-building-requirements-unlocks, 09-05-mobile-upgrade-flow-queue-ui]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Domain-layer pure function (BuildDuration::scaled) shared between an Application service and a read model to keep preview and scheduling from drifting"
    - "Array-always-present convention (established for CitySlot in Phase 07) extended to CityData.constructions"

key-files:
  created:
    - apps/api/modules/Construction/Domain/BuildDuration.php
    - apps/api/tests/Feature/City/CityConstructionQueueTest.php
  modified:
    - apps/api/modules/Construction/Application/BuildingUpgradeService.php
    - apps/api/modules/City/Application/CityStateService.php
    - packages/contracts/openapi.yaml
    - packages/contracts/src/generated/api.ts
    - apps/api/tests/Feature/Mvp/MvpGameplayTest.php
    - apps/mobile/src/features/city/components/CityScene.tsx
    - apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx
    - apps/mobile/__tests__/city-scene.test.tsx

key-decisions:
  - "A building code occupies at most one construction slot today, so the client resolves which slot is building by matching building_code against slots[].building.code — Construction gained no slot field (09-UI-SPEC.md Flagged Assumption 2, unchanged by this plan)."
  - "The time-accelerator env test drives $_ENV, $_SERVER and putenv() together, not just $_ENV as first drafted — Laravel's Env repository here resolves through more than one adapter, and only $_ENV left the assertion silently passing at the wrong value (1) instead of 60."

patterns-established:
  - "Any duration or deadline the client previews and the server later schedules must be computed by one shared domain function, not reimplemented at each call site."

requirements-completed: [REQ-05, REQ-09]

# Metrics
duration: ~15min
completed: 2026-09-06
---

# Phase 09 Plan 02: Construction Queue Contract Summary

**`GET /game/city` now returns every open construction order (not just the soonest) plus the server's queue ceiling, and one `BuildDuration::scaled()` function guarantees the previewed build time always equals the one the server schedules.**

## Performance

- **Duration:** ~15 min (commits span 2026-09-05T23:51:33-03:00 to 23:56:02-03:00; wall-clock investigation/verification time was longer)
- **Started:** 2026-09-06T02:45:00Z
- **Completed:** 2026-09-06T02:57:26Z
- **Tasks:** 3
- **Files modified:** 9 (2 created, 7 modified)

## Accomplishments

- A city with several concurrent upgrades now surfaces all of them to the client — `CityData.constructions[]` replaces the singular `construction` field that previously hid every order but the soonest.
- `queue_limit` echoes `config('game.limits.max_build_queue_slots')` so the mobile client no longer needs to hardcode the structural ceiling.
- The previewed `build_time_seconds` on `GET /game/city` and the scheduled `finishes_at - started_at` on `POST .../upgrade` are now computed by the exact same function, `BuildDuration::scaled()`, proven equal under `time_scale = 3` and `time_scale = 1` by test.
- Pinned, for the first time in this codebase, that `game.time_scale` cannot be pushed above 1 outside `local` even with `DEBUG_TIME_SCALE` set — `grep -rn "time_scale" apps/api/tests` was empty before this plan.
- The upgrade endpoint's OpenAPI document now names the `400` its four gameplay error codes actually return (`BUILDING_MAX_LEVEL`, `BUILDING_REQUIREMENTS_NOT_MET`, `BUILD_QUEUE_FULL`, `CITY_BUSY`, `INSUFFICIENT_RESOURCES`), closing a gap ADR-017 requires closed.
- Every city plot with an open order now ticks its own countdown on the mobile scene, not just the one the old singular field happened to carry.

## Task Commits

1. **Task 1: One BuildDuration, used by both the preview and the scheduler** - `dcb1bc9` (feat)
2. **Task 2: constructions[] and queue_limit on the contract and the city read** - `8ced12f` (feat)
3. **Task 3: Move the mobile call sites onto the array — plumbing only, no visual change** - `fd8247e` (feat)

_No separate TDD red/green commits — each task's tests and implementation landed together per the plan's `tdd="true"` flow, verified green before commit._

## Files Created/Modified

- `apps/api/modules/Construction/Domain/BuildDuration.php` - New framework-free domain class; `scaled(rawSeconds, timeScale)` clamps both inputs and floors via `intdiv`
- `apps/api/modules/Construction/Application/BuildingUpgradeService.php` - `start()` now calls `BuildDuration::scaled()` instead of an inline `intdiv`
- `apps/api/modules/City/Application/CityStateService.php` - Per-building preview uses `BuildDuration::scaled()`; the single-order query became a full open-order collection; response gained `constructions[]` (replacing `construction`) and `queue_limit`
- `packages/contracts/openapi.yaml` - `CityData.constructions`/`queue_limit` replace `CityData.construction`; upgrade endpoint documents a `400`
- `packages/contracts/src/generated/api.ts` - Regenerated from the above (`npm run contracts:check` passes)
- `apps/api/tests/Feature/City/CityConstructionQueueTest.php` - New: 6 tests covering preview/scheduled duration parity at two scales, the local-only time accelerator, empty queue, concurrent ordering, configurable ceiling, and a completed order dropping out
- `apps/api/tests/Feature/Mvp/MvpGameplayTest.php` - Two `data.construction === null` assertions became `assertJsonCount(0, 'data.constructions')`
- `apps/mobile/src/features/city/components/CityScene.tsx` - Per-`building_code` construction lookup (`Map`) replaces the single `city.construction` read; sheet receives the caller-resolved match
- `apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx` - `showConstruction` simplified now that the caller guarantees the match
- `apps/mobile/__tests__/city-scene.test.tsx` - Fixture moved to `constructions: [], queue_limit: 4`; new test proves two simultaneous plots each render their own timer

## Decisions Made

- Kept `Construction` schema unchanged (no `slot` field) per 09-UI-SPEC.md Flagged Assumption 2 — a building code occupies at most one slot today, so `building_code` alone is a sufficient join key. Recorded here so the day a building can occupy multiple slots, this join breaks visibly rather than silently.
- The plan-checker-added third test ("the time accelerator cannot escape local") initially used only `$_ENV` per the plan's suggested snippet, which passed for `production`/`staging` but silently returned `1` instead of `60` for `local` — meaning the assertion was checking the wrong thing without erroring loud enough to notice at a glance. Traced it to Laravel's `Env` repository resolving through `$_ENV`, `$_SERVER` and `putenv()/getenv()` adapters, of which only one was being moved. Fixed by moving all three together and restoring all three in the `finally` block.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Carbon's `diffInSeconds()` returns a signed float, not the plan's implied unsigned int**
- **Found during:** Task 1 (duration-parity test)
- **Issue:** `Carbon::parse($finishesAt)->diffInSeconds($startedAt)` returned `-6.0` (both a sign flip and a float) rather than the plan text's implied `6`, because Carbon 3's `diffInSeconds()` is signed by default and returns `int|float`.
- **Fix:** Wrapped in `(int) abs(...)` at both call sites in `CityConstructionQueueTest.php`.
- **Files modified:** `apps/api/tests/Feature/City/CityConstructionQueueTest.php`
- **Verification:** `./vendor/bin/pest --filter=CityConstructionQueue` green
- **Committed in:** `dcb1bc9`

**2. [Rule 1 - Bug] `$_ENV`-only env mutation left the "cannot escape local" test asserting the wrong value**
- **Found during:** Task 1 (plan-checker's added third test)
- **Issue:** The plan's suggested snippet mutates only `$_ENV`, but this Laravel version's `env()` helper resolves through a multi-adapter repository (`$_ENV`, `$_SERVER`, `putenv()/getenv()`); mutating one left the others holding the process's real `APP_ENV=testing`, so `local` incorrectly evaluated `time_scale` to `1`.
- **Fix:** Introduced `$set`/`$unset` helpers that move `$_ENV`, `$_SERVER` and `putenv()` together, matching the plan's own escape hatch ("if `$_ENV` proves not to work, use `putenv()`/`getenv()` — the assertions are the point, not the mechanism").
- **Files modified:** `apps/api/tests/Feature/City/CityConstructionQueueTest.php`
- **Verification:** All three assertions (`local`→60, `production`→1, `staging`→1) pass
- **Committed in:** `dcb1bc9`

**3. [Rule 3 - Blocking] Test file's stray `namespace` declaration broke project convention**
- **Found during:** Task 1, before first test run
- **Issue:** First draft declared `namespace Tests\Feature\City;` at the top of the new test file. No sibling file under `tests/Feature/City/` does this (they rely on PHP's function-fallback to the global namespace for Pest's `it()`/`test()`/`expect()` and this repo's `freezeClock()`), so the namespace was unnecessary and inconsistent with the codebase's established pattern of declaring a local top-level helper function without any namespace.
- **Fix:** Removed the namespace declaration; added a `constructionQueueTokens()` helper mirroring the existing `cityAuthorizationTokens()` pattern in `CityAuthorizationTest.php`.
- **Files modified:** `apps/api/tests/Feature/City/CityConstructionQueueTest.php`
- **Verification:** Tests still pass; file matches sibling conventions
- **Committed in:** `dcb1bc9`

---

**Total deviations:** 3 auto-fixed (all Rule 1/3, test-file mechanics only — no production code deviated from the plan's described behavior).
**Impact on plan:** All three fixes were necessary for the plan-checker's own added test to actually assert what it claims to assert. No scope creep; no change to any file outside this plan's declared `files_modified`.

## Issues Encountered

None beyond the auto-fixed items above.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `09-04` (building requirements/unlocks) and `09-05` (mobile upgrade flow/queue UI) — both wave 2 — can now build against `CityData.constructions[]` and `queue_limit` as committed, stable contract surfaces; 09-UI-SPEC.md's two Data Contract Dependency gaps for those plans are closed.
- **Parallel-execution note, not a blocker for this plan:** at the time this plan finished, the concurrently-running 09-01 (building catalogue) plan had two uncommitted, in-progress test failures in `apps/api/tests/Feature/GameData/GameDataImportTest.php` (an artisan command not yet rejecting some malformed catalogue content) and one Pint style issue in `apps/api/tests/Feature/GameData/DebugTest.php`. Both files are explicitly 09-01's ownership per the parallel-execution assignment; neither was touched here. A full-suite run at commit `fd8247e` showed 166 passed / 2 failed for that reason — all 6 of this plan's own tests, plus the two `MvpGameplayTest` assertions this plan touched, are green.

---
*Phase: 09-buildings-construction*
*Completed: 2026-09-06*

## Self-Check: PASSED

All 10 files listed in Files Created/Modified plus the SUMMARY.md itself were verified present on disk.
All 3 task commit hashes (`dcb1bc9`, `8ced12f`, `fd8247e`) were verified present in git history.
