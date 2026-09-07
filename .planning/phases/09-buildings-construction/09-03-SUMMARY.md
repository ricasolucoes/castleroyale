---
phase: 09-buildings-construction
plan: 03
subsystem: api
tags: [pest, idempotency, reconciler, queues, jobs, clock, utc]

# Dependency graph
requires:
  - phase: 09-buildings-construction
    provides: "09-01 — the eighteen-building catalogue (barracks, tavern) and the level-3 starter Palace"
  - phase: 09-buildings-construction
    provides: "09-02 — BuildDuration::scaled, the constructions[] contract, the documented 400"
  - phase: 09-buildings-construction
    provides: "09-04 — the check order CITY_NOT_OWNED → BUILDING_MAX_LEVEL → BUILDING_REQUIREMENTS_NOT_MET → BUILD_QUEUE_FULL → CITY_BUSY → INSUFFICIENT_RESOURCES"
provides:
  - "tests/Feature/Construction/ — the directory that did not exist; construction was previously exercised only incidentally by MvpGameplayTest"
  - "Phase 09 success criterion 2 (server-owned UTC timers), 3 (job idempotency), 4 (worker death + reconciler), 5 (BUILD_QUEUE_FULL / BUILDING_MAX_LEVEL) each proven by a named test"
affects: [12-training-system, 15-march-system, 37-security-anticheat-hardening]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Worker death simulated as Queue::fake() + never processing the captured job, then asserting with Eloquent that nothing completed unaided before running the reconciler"
    - "Falsification as an acceptance step: a guard is only proven when the suite has been observed failing with it removed"

key-files:
  created:
    - apps/api/tests/Feature/Construction/ConstructionCompletionTest.php
    - apps/api/tests/Feature/Construction/ConstructionReconcilerTest.php
    - apps/api/tests/Feature/Construction/ConstructionQueueLimitTest.php
    - apps/api/tests/Feature/Construction/ConstructionTimersTest.php
  modified: []

key-decisions:
  - "No HTTP call appears between advancing the clock and the assertion under test. CityStateService::handle() and BuildingUpgradeService::start() both call completeOverdueLocked() first, so a GET /game/city there would complete the order on the read path and leave the reconciler with nothing to do — the test would pass while proving nothing."
  - "Each test file declares its own uniquely-named setup helper. PHPUnit require()s every *Test.php in one process, so two top-level functions sharing a name in one directory is a fatal 'Cannot redeclare'."
  - "The closed-world boundary is pinned as-is rather than widened. Extending the reconciler's scope is a behaviour change no Phase 09 success criterion asks for — see Concerns below."

patterns-established:
  - "An idempotency guard is not proven until the suite has been watched failing with the guard removed — and the falsification must target the specific guard, not merely a guard."

requirements-completed: [REQ-05, REQ-09]

# Metrics
duration: ~40min
completed: 2026-09-06
---

# Phase 09 Plan 03: Completion Idempotency & Reconciler Summary

**16 tests across four new files turn Phase 09's central claim — "completes reliably even if a worker dies" — from a design statement into a proven one. No production code changed.**

## Criteria proven

| Criterion | Test |
|---|---|
| 2 — server-owned UTC timers, device clock never read | *stamps the injected clock, in UTC*; *ignores every timestamp, duration and cost the client tries to dictate*; *writes one debit set and one order per command* |
| 3 — running the job twice completes once | *completes the upgrade once when the job runs twice*; *completes once when the service itself is called twice* |
| 4 — kill the worker, reconciler completes every overdue upgrade exactly once | *finishes an order exactly once after the worker dies*; *finishes every overdue order in the city in one run*; *leaves an order that is not due yet alone*; *ignores orders in a closed world* |
| 5 — BUILD_QUEUE_FULL and BUILDING_MAX_LEVEL | *refuses a fifth concurrent order with BUILD_QUEUE_FULL*; *reads the queue ceiling from config*; *reports CITY_BUSY for a second order on the same building*; *refuses an upgrade past the maximum level*; *refuses a catalogue building the city does not have* |

## Finding: the plan's falsification targeted the wrong guard

The plan's acceptance criterion asked that commenting out `->whereNull('completed_at')` in `ConstructionCompletionService::completeOverdueLocked` make Test 1 fail. **It does not.** With that guard removed, Test 1 (*"completes the upgrade once when the job runs twice"*) still passes, because `CompleteConstruction::handle()` has its **own** `$orderExists ... whereNull('completed_at')` pre-check and returns early on the second run — the service is never reached twice via the job.

So the job path proves the *job's* guard. The service's guard is what the **reconciler** depends on, since `ConstructionReconciler::run()` calls `completeOverdueLocked()` directly with no such pre-check.

**Resolution:** added a fifth test, *"completes once when the service itself is called twice"*, which calls the service directly. Re-running the falsification against it: with the guard removed the test **fails** — 2 `CityStateChanged` events dispatched instead of 1. The guard was then restored and `git status --porcelain apps/api/modules` confirmed clean.

Worth noting *why* the event count is the load-bearing assertion: re-applying `level = target_level` is idempotent in value, and re-stamping `completed_at` from a frozen clock writes the identical instant. A missing guard is therefore **invisible in the row data** and shows up only as a duplicate broadcast telling every connected client the city changed when it did not.

## Concerns raised, not fixed

**Reconciler ignores closed worlds.** `ConstructionReconciler::run()` iterates `World::query()->where('is_open', true)` only. An order in a world closed for maintenance will never complete — not while closed, and not after reopening if the queue job has already expired. This is pinned by *"ignores orders in a closed world"* as deliberate current behaviour rather than left ambiguous. Widening the scope is a behaviour change no Phase 09 criterion asks for. **Proposed owner: Phase 50 (Production Infrastructure)** or Phase 31 (Events & LiveOps), whichever first introduces planned maintenance windows.

**A stylistic note, not a defect.** `ConstructionQueueLimitTest`/`ConstructionTimersTest`/`ConstructionReconcilerTest` contain zero wall-clock reads. `BuildingRequirementsTest.php` (from 09-04, in the same directory) uses `Carbon::now()` twice in a fixture. That is safe today — `freezeClock()` calls `Carbon::setTestNow()`, so it resolves to the frozen instant — but it would become a real wall-clock read if copied into a test that does not freeze the clock first. Left as-is; it is 09-04's file and already green.

*(The plan's literal criterion `grep -cE "\bnow\(\)|Carbon::now"` returning 0 is unsatisfiable as written — the pattern also matches `$clock->now()`, which is the frozen clock and precisely what the tests should use. The criterion's intent — no wall-clock reads — holds.)*

## Verification

| Check | Result |
|---|---|
| `pest --filter=Construction` | 30 passed, 233 assertions |
| Full API suite | 191 passed, 1450 assertions (was 175) |
| PHPStan level 8 | 0 errors, 152 files |
| Pint | PASS, 223 files |
| `npm run typecheck && npm run lint && npm test` | clean; 14 suites, 86 tests |
| `git status --porcelain apps/api/modules` | empty — no production file modified |

## What this enables

Phase 12 (training queues) and Phase 15 (marches) both need "a timed thing that completes reliably". The worker-death-plus-reconciler test shape here is the template both should copy rather than re-derive.
