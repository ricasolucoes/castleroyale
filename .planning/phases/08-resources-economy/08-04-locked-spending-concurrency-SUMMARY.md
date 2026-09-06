---
phase: 08-resources-economy
plan: 04
subsystem: testing
tags: [economy, php, laravel, concurrency, idempotency, property-testing, pest]

# Dependency graph
requires:
  - phase: 08-resources-economy
    provides: "08-03's required LedgerParty argument on debitLocked()/creditLocked() and EconomyLedger::record() as the sole ledger write path"
provides:
  - "A genuine interleaved-race proof (Event::listen on eloquent.retrieved) that two HTTP requests contending for the same stone resolve to exactly one success and one INSUFFICIENT_RESOURCES, verified by hand to fail when the interleaving is removed"
  - "An HTTP-level idempotency double-submit proof for a resource-spending command (farm upgrade), not just auth/bootstrap"
  - "A genuinely randomised, ECONOMY_PROPERTY_SEED-reproducible 120-step property test reconciling ledger sum to stored balance across credit/debit/time-advance operations"
affects: [16-gathering-returns, 19-plunder, 27-trade]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "A concurrency proof over SQLite :memory: uses a one-shot Event::listen fired at the exact interleaving seam identified by reading the live service's transaction boundaries, with an $raced flag asserted true so the test cannot silently degrade to sequential if the call order changes (technique inherited from Phase 07's CityTileClaimTest, now applied to resource spending)"
    - "A property test seeds mt_srand from an environment variable (ECONOMY_PROPERTY_SEED), falling back to random_int, and prints the seed in every PHPUnit assertion message so a CI red run is replayable with one exported variable"
    - "Test setup that needs to pre-adjust a balance for contention purposes goes through the same locked debitLocked()/creditLocked() path as gameplay, not a raw DB::table update, so ledger-reconciliation assertions in the same test are not broken by their own fixture"

key-files:
  created:
    - apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php
  modified:
    - apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php

key-decisions:
  - "Replaced the plan's literal raw `DB::table('cities')->update(...)` fixture setup (in the race test) with a transactional `debitLocked()` call against the same starter balances, because the raw write bypasses the ledger and breaks the test's own 'ledger sum reproduces the stored balance exactly' assertion for every resource whose starting value the fixture changed (iron and gold went from a real starter grant of 250/100 to 0 with no offsetting ledger row) — see Deviations"
  - "Property test operation mix is credit / debit / advance-time (0/1/2 from mt_rand(0,2)) over 120 steps, matching the plan's `<behavior>` spec exactly; debit amounts are clamped to `min($available, $amount)` so a debit never throws INSUFFICIENT_RESOURCES and interrupts the sequence"

patterns-established:
  - "Race proofs for locked spending are written at the HTTP layer (guest -> read city -> contended upgrade) so the whole request pipeline — idempotency service, bootstrap, locking — is under test, not just the service method in isolation"

requirements-completed: [REQ-09, REQ-02]

# Metrics
duration: 7min
completed: 2026-09-06
---

# Phase 08 Plan 04: Locked Spending & Concurrency Summary

**Interleaved-race proof (a rival lumber_mill upgrade committed mid-request via `Event::listen('eloquent.retrieved: '.Player::class, ...)`) shows a farm upgrade contending for the same stone loses cleanly with `INSUFFICIENT_RESOURCES` and no residue, confirmed genuine by manually disabling the listener and watching the test fail; plus an HTTP idempotency double-submit proof and a 120-step `ECONOMY_PROPERTY_SEED`-reproducible randomised ledger-reconciliation property test, run green ten consecutive times.**

## Performance

- **Duration:** ~7 min
- **Started:** 2026-09-05T23:53:51Z
- **Completed:** 2026-09-06T00:00:35Z
- **Tasks:** 3 completed
- **Files modified:** 2 (1 new, 1 modified)

## Accomplishments

- **The race is real, not a tautology.** `EconomyConcurrencyTest` fires a one-shot listener on `Player`'s `eloquent.retrieved` event — the exact seam between `BuildingUpgradeController`'s bootstrap transaction (which commits) and `BuildingUpgradeService::start()`'s own locked transaction (which reads fresh). The rival's `lumber_mill` upgrade commits in that gap; the `farm` request then reads the city inside its lock, recomputes affordability against the post-rival balance, and is correctly refused. I disabled the listener by hand, reran the test, and it failed exactly where expected (`expect($raced)->toBeTrue()`), then I restored it and reran green — the manual verification the plan's acceptance criteria required.
- Exactly one `ConstructionOrder` exists after the race (the rival's `lumber_mill`), the loser wrote zero `building.upgrade` ledger rows, and summing the ledger for all five resources reproduces the city's stored balance exactly — no value was minted or destroyed by the interleaving.
- A second test proves the same `Idempotency-Key` posted twice to the farm-upgrade endpoint produces two identical `201` responses (the second replayed from the stored record) and exactly one cost's worth of ledger debits (`wood -120`, `stone -60`), not double — closing the gap that only `/auth/guest` and `/game/bootstrap` had idempotency-replay coverage for, not an actual resource spend.
- `CityEconomyFoundationTest`'s third test was rewritten from a fixed arithmetic walk (`($step * 37) % 121`, which is a regression test) into a genuine property test: 120 steps of `mt_rand`-chosen credit/debit/time-advance operations, seeded from `ECONOMY_PROPERTY_SEED` (falling back to `random_int` when unset), with every failure assertion printing the seed via `PHPUnit`'s `assertSame`/`assertGreaterThanOrEqual`/`assertLessThanOrEqual`. Run 10 consecutive times with fresh random seeds (all green) and twice with `ECONOMY_PROPERTY_SEED=12345` (identical results both times), confirming reproducibility.
- Full six-gate suite green: `pest` 158/158 (1263 assertions), `phpstan` 0 errors, `pint` clean, `npm run typecheck` clean across all 4 workspaces, `npm run lint` clean, `npm test` 13/13 suites (74 tests).

## Task Commits

Each task was committed atomically:

1. **Task 1: Prove two competing spends resolve to exactly one success and one INSUFFICIENT_RESOURCES** - `4e220d6` (test)
2. **Task 2: Prove a double-submitted spend debits exactly once** - `37347ea` (test)
3. **Task 3: Turn the reconciliation test into a genuinely seeded property test** - `646a9a0` (test)

## Files Created/Modified
- `apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php` - new file, 2 tests: the interleaved race proof and the idempotency double-submit proof, both driven over real HTTP requests through the guest/bootstrap/upgrade pipeline
- `apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php` - only the third test rewritten (title and body); the first two tests are byte-for-byte unchanged, verified with `git diff` showing only one `it(...)` line changed

## Decisions Made
- Kept the HTTP-level race proof (guest → `GET /game/city` → contended `POST .../upgrade`) rather than calling `BuildingUpgradeService::start()` directly for both sides, so the idempotency service, bootstrap and locking all sit inside the proof exactly as the plan's interfaces block specified
- Used `mt_srand`/`mt_rand` rather than PHP's Mersenne Twister via `random_int` for the property test's step generation, matching the plan's locked decision and its own worked example verbatim (only `random_int` is used to pick the seed itself, when none is supplied)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Replaced a raw `DB::table('cities')->update(...)` test fixture with a ledger-honest `debitLocked()` call**

- **Found during:** Task 1
- **Issue:** The plan's worked example set up the race's contested starting balances (`food=80, wood=120, stone=100, iron=0, gold=0`) via a raw `DB::table('cities')->where('id', $cityId)->update([...])`. Running the test as written failed at the final reconciliation loop: `Failed asserting that 0 is identical to 420` for `food`. The starter grant (`packages/game-data/data/starter.json`) actually leaves a new city at `food/wood/stone=500`, `iron=250`, `gold=100`; the raw update silently discarded 420 food, 380 wood, 400 stone, 250 iron and 100 gold with no compensating ledger row, so the ledger could never sum back to the balances the fixture forced — a bug in the plan's example, not in application code, but one that would make the test itself lie about "the race did not mint or destroy value" (its own stated purpose).
- **Fix:** Replaced the raw update with a `DB::transaction` + `lockForUpdate` + `CityEconomyService::debitLocked()` call, debiting the exact deltas (`food 420, wood 380, stone 400, iron 250, gold 100`) under `reason: 'test.setup'`, `destination: LedgerParty::system('test_setup')`. This lands the setup adjustment in the ledger too, so the full five-resource reconciliation at the end of the test holds honestly, while leaving the contested balances (and every other assertion in the plan) identical to what was specified.
- **Files modified:** `apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php`
- **Verification:** `./vendor/bin/pest --filter=EconomyConcurrency` passes with all 24 assertions in the race test, including the full per-resource reconciliation loop; the fix required no change to any assertion, only to how the fixture reaches its starting state.
- **Committed in:** `4e220d6` (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 — a bug in the plan's example test code, not in application code)
**Impact on plan:** None on the guarantees being proved. Every assertion in the plan's `<behavior>` and `<acceptance_criteria>` blocks holds exactly as written; only the mechanism for reaching the contested starting balance changed, from a ledger-bypassing raw write to a ledger-honest locked debit.

## Issues Encountered

None beyond the deviation above. Task 2 and Task 3 passed on first run with no debugging iterations. The property test's random step generation needed no tuning — debit amounts are clamped to `min($available, $amount)` per the plan's worked example, so no run in 10 consecutive attempts (nor the two fixed-seed replays) ever hit a code path the test wasn't designed to exercise.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- ROADMAP criterion 3 is now satisfied: two concurrent spends for the same resources produce exactly one success and one `INSUFFICIENT_RESOURCES`, proven by a test verified by hand to fail when the race interleaving is removed.
- ROADMAP criterion 5 is now satisfied: a genuinely randomised, reproducibly-seeded property test reconciles the ledger to the balance exactly, across credit, debit and time-advance operations, over 10 consecutive runs.
- REQ-09 (idempotent, concurrency-safe spending) is proven at the HTTP and ledger level for a real resource-spending command, not just asserted or covered only for auth/bootstrap.
- Phase 08 (resources-economy) is now complete: all five plans (production-rate-contract, capacity-ceiling-strict-credit, ledger-parties-append-only, locked-spending-concurrency, mobile-resource-bar) are executed and committed. The economy's core guarantees — accrual on read, capped credit, append-only audit trail, locked concurrency-safe spending, and a live client resource bar — are all in place and tested.
- Phases 16 (gathering/returns), 19 (plunder) and 27 (trade) can build their own concurrency and conservation tests directly against the `LedgerParty` / `debitLocked` / `creditLocked` primitives this plan exercised under real contention, with the interleaved-race technique available as a template for any future locked-spending path.

## Self-Check: PASSED

All claimed files and commits verified to exist:
- FOUND: apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php
- FOUND: apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php
- FOUND: commit 4e220d6
- FOUND: commit 37347ea
- FOUND: commit 646a9a0

---
*Phase: 08-resources-economy*
*Completed: 2026-09-06*
