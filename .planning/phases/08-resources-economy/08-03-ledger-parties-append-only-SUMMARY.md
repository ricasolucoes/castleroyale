---
phase: 08-resources-economy
plan: 03
subsystem: api
tags: [economy, php, laravel, ledger, audit-trail, architecture-test]

# Dependency graph
requires:
  - phase: 08-resources-economy
    provides: "08-02's OverflowPolicy enum and creditLocked()'s optional strict all-or-nothing credit path"
provides:
  - "Game\\Economy\\Domain\\LedgerParty value object (city:{ulid} / system:{name}), format-enforced"
  - "EconomyLedger::record() as the single sanctioned ledger write path, with update()/delete() blocked at the Eloquent event level"
  - "source and destination columns on economy_ledger, NOT NULL, indexed, backfilled by sign"
  - "All four ledger write sites (starter.grant, production.elapsed, building.upgrade, creditLocked's caller-supplied grant) naming both parties in the documented direction"
  - "Two new architecture tests forbidding EconomyLedger::create( and raw DB::table('economy_ledger') writes outside the model"
affects: [16-gathering-returns, 19-plunder, 27-trade]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "A counterparty that may or may not be a database row is modeled as a typed {kind}:{identifier} value object rather than a nullable FK, so 'no row on this end' never collapses into an ambiguous null"
    - "A model's only sanctioned write path is a single static factory method with required typed parameters (not fillable array keys), backed by an architecture test that greps for any competing ::create( call site"
    - "Append-only is enforced mechanically via Eloquent's booted() lifecycle hooks (updating/deleting throw), not left as a documented convention"

key-files:
  created:
    - apps/api/database/migrations/2026_09_06_000100_add_ledger_parties_to_economy_ledger.php
    - apps/api/modules/Economy/Domain/LedgerParty.php
    - apps/api/tests/Feature/Economy/LedgerAuditTrailTest.php
  modified:
    - apps/api/modules/Economy/Infrastructure/EconomyLedger.php
    - apps/api/modules/Economy/Application/CityEconomyService.php
    - apps/api/modules/Player/Application/GameBootstrapService.php
    - apps/api/modules/Construction/Application/BuildingUpgradeService.php
    - apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php
    - apps/api/tests/Feature/Economy/WarehouseCapacityTest.php
    - apps/api/tests/Architecture/ArchitectureTest.php

key-decisions:
  - "debitLocked gained a required trailing LedgerParty $destination parameter; creditLocked gained a required LedgerParty $source immediately before its existing optional OverflowPolicy $policy — preserving 08-02's optional-trailing-enum shape while making the new party mandatory rather than defaulted, since a caller forgetting the counterparty is a correctness bug, not a safe default"
  - "The migration's 'system:legacy' default is documented as a migration mechanism only; LedgerAuditTrailTest explicitly asserts no gameplay-written row ever carries it"
  - "The 'no raw ledger mutation' architecture test was narrowed to the literal strings DB::table('economy_ledger')->update( / ->delete( rather than a generic ->update(/ ->delete( grep, exactly as the plan's fallback allowed, because the generic form would flag unrelated model calls in the same files"

patterns-established:
  - "Ledger write sites are proven not just by unit assertions but by a real end-to-end HTTP session (guest -> bootstrap -> read -> upgrade) replayed in a Feature test, so the parties are checked against what a live session actually persists, not a hand-built row"

requirements-completed: [REQ-02, REQ-09]

# Metrics
duration: 6min
completed: 2026-09-05
---

# Phase 08 Plan 03: Ledger Parties & Append-Only Summary

**Added `source`/`destination` columns and a typed `LedgerParty` value object so every one of the four ledger write sites now names both ends of a resource movement through a single guarded `EconomyLedger::record()` factory, with append-only enforced by Eloquent event hooks and an architecture test blocking any competing write path.**

## Performance

- **Duration:** ~6 min
- **Started:** 2026-09-05T23:40:57Z
- **Completed:** 2026-09-05T23:47:11Z
- **Tasks:** 3 completed
- **Files modified:** 10 (3 new, 7 modified)

## Accomplishments
- `economy_ledger` gained indexed, NOT NULL `source` and `destination` columns; every pre-existing row was backfilled by the sign of its `amount` (credits get the city as destination, debits get the city as source), naming the unrecorded faucet/sink honestly as `system:legacy` rather than guessing
- `Game\Economy\Domain\LedgerParty` ships as a framework-free value object (`city:{ulid}` / `system:{name}`), format-enforced via `InvalidArgumentException` — a party can no longer be a free-form string
- `EconomyLedger::record()` is now the only sanctioned write path; the model refuses `update()`/`delete()` at the Eloquent `booted()` event level, making the append-only rule mechanical rather than a convention
- All four ledger write sites converted: `starter.grant` (`system:starter` → city), `production.elapsed` (`system:production` → city), `building.upgrade` debit (city → `system:construction`), and `creditLocked`'s caller-supplied grant source — verified with `grep -rn "EconomyLedger::create(" apps/api/modules/` returning nothing outside the model itself
- `debitLocked()` gained a required trailing `LedgerParty $destination`; `creditLocked()` gained a required `LedgerParty $source` immediately before 08-02's optional `OverflowPolicy $policy` — including re-argumenting both of `WarehouseCapacityTest`'s positional `OverflowPolicy::Refuse` calls, which would otherwise have bound to the wrong parameter and thrown a silent `TypeError`
- New `LedgerAuditTrailTest` (4 tests) replays a real guest → bootstrap → read → upgrade HTTP session and proves every row it writes names both parties in the documented direction, none carries `system:legacy`, every party correctly names its own row's city, update/delete both throw, and `LedgerParty::system()` rejects malformed input
- Two new architecture tests lock the write path: one greps for any `EconomyLedger::create(` outside the model, the other for any raw `DB::table('economy_ledger')->update(`/`->delete(` bypassing the guard
- Full PHP gate suite green: 156 Pest tests / 1210 assertions (150 baseline + 4 audit-trail + 2 architecture), 0 PHPStan errors, Pint clean

## Task Commits

Each task was committed atomically:

1. **Task 1: Add source and destination columns with a direction-aware backfill** - `ab84b8d` (feat)
2. **Task 2: Add LedgerParty, make EconomyLedger::record the only write path, and convert all four sites** - `37b029d` (feat)
3. **Task 3: Prove the audit trail and lock the write path with an architecture test** - `12d9749` (test)

## Files Created/Modified
- `apps/api/database/migrations/2026_09_06_000100_add_ledger_parties_to_economy_ledger.php` - new migration: `source`/`destination` columns, both indexed with `world_id`, backfilled by sign using portable `||` concatenation
- `apps/api/modules/Economy/Domain/LedgerParty.php` - new value object, `city()`/`system()` factories, format-enforced
- `apps/api/modules/Economy/Infrastructure/EconomyLedger.php` - `record()` guarded factory, `updating`/`deleting` throw guards, `source`/`destination` added to `$fillable` and docblock
- `apps/api/modules/Economy/Application/CityEconomyService.php` - all three internal `EconomyLedger::create()` calls converted to `record()`; `debitLocked()`/`creditLocked()` signatures gain required `LedgerParty` parameters
- `apps/api/modules/Player/Application/GameBootstrapService.php` - starter grant write converted to `record()` with `system:starter` → city
- `apps/api/modules/Construction/Application/BuildingUpgradeService.php` - its `debitLocked()` call now passes `LedgerParty::system('construction')`
- `apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php` - three call sites gained the new `LedgerParty` argument; no assertion touched
- `apps/api/tests/Feature/Economy/WarehouseCapacityTest.php` - both `OverflowPolicy::Refuse` calls re-argumented with `LedgerParty::system('test_strict_grant')` inserted before the policy, per the plan's Step 8
- `apps/api/tests/Architecture/ArchitectureTest.php` - two new `arch`-grouped tests for the write-path and append-only guarantees
- `apps/api/tests/Feature/Economy/LedgerAuditTrailTest.php` - new file, 4 tests, plus a shared `playLedgerAuditSession()` helper replaying the full HTTP flow

## Decisions Made
- Kept the locked single-row ledger model for capped credits exactly as `08-CONTEXT.md` specifies — `overflow_amount` stays on the same row as `amount`, never a second `system:void` row
- Chose to factor the three-part HTTP session setup (guest → bootstrap → advance-clock-and-read → upgrade) into one shared `playLedgerAuditSession()` function in `LedgerAuditTrailTest.php`, called from three separate tests, rather than duplicating the ~15 lines of setup three times — the file has no `beforeEach` convention in this codebase, and Pest's `test()` helper works correctly when called from a plain function, not just from inside an `it()` closure
- Narrowed the second architecture test to the literal strings `DB::table('economy_ledger')->update(`/`->delete(` rather than a generic method-name grep, exactly as the plan's documented fallback permitted, to avoid false positives from unrelated `->update()`/`->delete()` calls on other models in the same files

## Deviations from Plan

**1. [Cosmetic - Pint reformat] `static::` became `self::` in `EconomyLedger`'s guard closures**

- **Found during:** Task 2
- **Issue:** The plan's action block wrote the `booted()` guards using `static::updating(...)` / `static::deleting(...)`, and its acceptance criteria literally grepped for `static::updating`/`static::deleting`. Pint's `self_static_accessor` fixer (enforced project-wide, same as 08-02's method-signature reflow) rewrote these to `self::updating`/`self::deleting` on a `final class`, where the two are behaviorally identical.
- **Fix:** Accepted Pint's rewrite rather than fighting the project's enforced formatting rule; re-ran the full test suite to confirm behavior (append-only guard still throws in `LedgerAuditTrailTest`) was unaffected.
- **Files modified:** `apps/api/modules/Economy/Infrastructure/EconomyLedger.php`
- **Verification:** `./vendor/bin/pest --filter=LedgerAuditTrail` (the "refuses to update or delete" test) passes; `./vendor/bin/pint --test` is clean.
- **Committed in:** `37b029d` (Task 2 commit)

---

**Total deviations:** 1 cosmetic (enforced formatter output, not a logic change)
**Impact on plan:** None on behavior. The plan's own acceptance-criteria grep string (`static::updating`) no longer matches verbatim, but the append-only guarantee it was checking for is intact and proven by a passing test — the checker's grep predates knowledge of Pint's fixer, exactly as happened with 08-02's Yoda-style comparison.

## Issues Encountered

None. Every task's tests passed on first run; no debugging iterations were needed. The signature-collision risk flagged in `<critical_notes>` (WarehouseCapacityTest's positional `OverflowPolicy::Refuse` argument) was handled exactly per the plan's Step 8 and confirmed with a clean `--filter=WarehouseCapacity` run before commit — no `TypeError` was ever observed.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- ROADMAP criterion 2 is now satisfied in full: every ledger row carries `source`, `destination`, `resource`, `amount`, `reason`, `reference`, `created_at` and `economy_version`
- The append-only rule from `08-CONTEXT.md` is mechanically enforced (Eloquent event hooks + architecture test), not a convention someone could forget
- Phases 19 (plunder) and 27 (trade) can now write their conservation property tests against a ledger that names both ends of a transfer — the `LedgerParty` type and `EconomyLedger::record()` factory are the exact primitives those phases' plans described needing
- Plan 08-04 (locked spending / concurrency tests) is untouched and unaffected by this plan's changes; it will call `debitLocked`/`creditLocked` with the now-required `LedgerParty` argument
- Plan 08-05 (mobile resource bar, executing concurrently in this same tree) touched only `apps/mobile/**` and `packages/localization/locales/**` throughout this plan's execution — verified zero file overlap at every commit boundary

## Self-Check: PASSED

All claimed files and commits verified to exist:
- FOUND: apps/api/database/migrations/2026_09_06_000100_add_ledger_parties_to_economy_ledger.php
- FOUND: apps/api/modules/Economy/Domain/LedgerParty.php
- FOUND: apps/api/tests/Feature/Economy/LedgerAuditTrailTest.php
- FOUND: commit ab84b8d
- FOUND: commit 37b029d
- FOUND: commit 12d9749

---
*Phase: 08-resources-economy*
*Completed: 2026-09-05*
