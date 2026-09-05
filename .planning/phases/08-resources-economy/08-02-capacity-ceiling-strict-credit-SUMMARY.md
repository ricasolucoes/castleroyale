---
phase: 08-resources-economy
plan: 02
subsystem: api
tags: [economy, php, laravel, ledger, error-handling]

# Dependency graph
requires:
  - phase: 08-resources-economy
    provides: "08-01's ratesPerHour()/balances()/capacities() read accessors on CityEconomyService, and the accrueLocked() discard-at-cap production path"
provides:
  - "Game\\Economy\\Domain\\OverflowPolicy enum (DiscardAtCap | Refuse)"
  - "CityEconomyService::creditLocked() optional strict all-or-nothing path that raises WAREHOUSE_CAPACITY_EXCEEDED before any mutation"
  - "First reachable code path for ErrorCode::WarehouseCapacityExceeded, proven through the bootstrap/app.php render funnel"
  - "WarehouseCapacityTest.php: cap-holds proof on both the accrual and grant paths"
affects: [16-gathering-returns, 19-plunder, 27-trade]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "A faucet that discards at the cap (DiscardAtCap) is distinct from an explicit transfer that must be all-or-nothing (Refuse) — modeled as a domain enum passed into the existing mutation method, not a new method or a new error path bolted on separately"
    - "Refusal pre-flight check runs before the first balance mutation, inside the same lockForUpdate transaction, so a strict credit either fully lands or writes nothing at all — no partial ledger rows"

key-files:
  created:
    - apps/api/modules/Economy/Domain/OverflowPolicy.php
    - apps/api/tests/Feature/Economy/WarehouseCapacityTest.php
  modified:
    - apps/api/modules/Economy/Application/CityEconomyService.php

key-decisions:
  - "OverflowPolicy is a plain two-case string-backed enum with no methods — the branching logic stays in CityEconomyService::creditLocked, since the enum only names a choice, it doesn't own behavior"
  - "The refusal's HTTP proof uses a throwaway route registered inside the test itself, per the plan's locked decision, rather than inventing a gameplay endpoint Phase 08 doesn't own"

patterns-established:
  - "Strict-vs-lenient variants of an existing mutation are modeled as an optional trailing enum parameter with a safe default, so every existing caller is unaffected and the new behavior is opt-in"

requirements-completed: [REQ-02]

# Metrics
duration: 12min
completed: 2026-09-05
---

# Phase 08 Plan 02: Capacity Ceiling & Strict Credit Summary

**Added `OverflowPolicy` (DiscardAtCap | Refuse) to `CityEconomyService::creditLocked`, making `WAREHOUSE_CAPACITY_EXCEEDED` reachable for the first time as an all-or-nothing refusal, while the existing discard-at-cap production path is untouched and proven to land exactly on capacity.**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-09-05T23:24:00Z (approx)
- **Completed:** 2026-09-05T23:36:00Z
- **Tasks:** 3 completed
- **Files modified:** 3 (2 new, 1 modified)

## Accomplishments
- `Game\Economy\Domain\OverflowPolicy` enum ships with two cases (`DiscardAtCap`, `Refuse`), framework-free and obeying the domain-layer architecture rule
- `CityEconomyService::creditLocked()` gained a fifth, optional parameter (`OverflowPolicy $policy = OverflowPolicy::DiscardAtCap`); under `Refuse`, any resource that would exceed capacity throws `GameException` with `ErrorCode::WarehouseCapacityExceeded` before any balance is touched or any ledger row is written, naming the overflowed resources in `details['exceeded']`
- The default `DiscardAtCap` behavior is byte-for-byte unchanged — the pre-existing passing test `it('caps server grants and records discarded overflow in the ledger')` was not modified and still passes
- New `WarehouseCapacityTest.php` (4 tests) proves: a strict grant that overflows refuses and mutates nothing (balance and ledger row count both provably unchanged); a strict grant that exactly fills capacity succeeds (`> capacity`, not `>= capacity`, is the boundary); six hours of elapsed production lands every resource's balance exactly on its capacity with the discarded tail recorded in `overflow_amount`; and the error renders over HTTP through the real `bootstrap/app.php` exception funnel as `{"error":{"code":"WAREHOUSE_CAPACITY_EXCEEDED"}}` with no `data` key
- `WAREHOUSE_CAPACITY_EXCEEDED` is now reachable from real module code (verified: `grep -rn "WarehouseCapacityExceeded" apps/api/modules/` returns a hit outside `ErrorCode.php`)
- Full PHP gate suite green: 150 Pest tests / 1137 assertions (146 baseline + 4 new), 0 PHPStan errors, Pint clean

## Task Commits

Each task was committed atomically:

1. **Task 1: Add the OverflowPolicy domain enum and the strict credit path** - `42ee7ec` (feat)
2. **Task 2: Prove the cap, the recorded discard, the refusal and its HTTP envelope** - `f9e6cdd` (test)
3. **Task 3: Run the full gate and confirm the existing economy tests still pass untouched** - no code changes; every gate passed on first run after Task 2's commit

## Files Created/Modified
- `apps/api/modules/Economy/Domain/OverflowPolicy.php` - new domain enum, two cases, no framework imports
- `apps/api/modules/Economy/Application/CityEconomyService.php` - `creditLocked()` gains the optional `$policy` parameter and the pre-flight `Refuse` check, inserted before the mutating `foreach`
- `apps/api/tests/Feature/Economy/WarehouseCapacityTest.php` - 4 tests covering refusal, exact-boundary acceptance, elapsed-production ceiling, and the HTTP error envelope

## Decisions Made
- Kept the single-row ledger model for capped credits exactly as locked in `08-CONTEXT.md` (one row per resource carrying both `amount` and `overflow_amount`) — extended `creditLocked`, never rewrote its shape
- Did not touch `openapi.yaml` or `ErrorCode.php` — both already declare `WAREHOUSE_CAPACITY_EXCEEDED`; this plan only makes the existing declaration reachable
- No new gameplay endpoint was invented for the HTTP proof; the throwaway test route asserts the render funnel itself, which is the actual infrastructure the roadmap criterion depends on

## Deviations from Plan

None - plan executed exactly as written. Pint's auto-formatter reflowed the new multi-line method signature and flipped one comparison to Yoda style (`$x < $y + $z` instead of `$y + $z > $x`) on first run of `pint --test`; this is standard project formatting (enforced, not a deviation) and was applied via `./vendor/bin/pint`, with the diff confirmed semantically identical before continuing.

## Issues Encountered
None. All 4 new tests passed on the first run; no auto-fixes were needed.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- ROADMAP criterion 4 ("resources never exceed capacity") is now satisfied on both paths that can mutate a balance: passive accrual (discards, never throws) and explicit credit (refuses whole under `Refuse`)
- The all-or-nothing delivery mechanism Phases 16 (gathering returns), 19 (plunder) and 27 (trade) will need already exists and is proven — those phases call `creditLocked(..., OverflowPolicy::Refuse)` directly, no further Economy-module work required for the mechanism itself
- Plan 08-03 is unblocked and already accounts for this plan's shape: it will insert a required `LedgerParty $source` parameter into `creditLocked()` at position 5 (pushing `OverflowPolicy` to position 6), which its own Step 8 handles — no coordination needed here
- Plan 08-04 (locked spending / concurrency tests) and 08-05 (mobile resource bar, executing concurrently in this same tree) are both untouched and unaffected by this plan's changes

## Self-Check: PASSED

All claimed files and commits verified to exist:
- FOUND: apps/api/modules/Economy/Domain/OverflowPolicy.php
- FOUND: apps/api/tests/Feature/Economy/WarehouseCapacityTest.php
- FOUND: commit 42ee7ec
- FOUND: commit f9e6cdd

---
*Phase: 08-resources-economy*
*Completed: 2026-09-05*
