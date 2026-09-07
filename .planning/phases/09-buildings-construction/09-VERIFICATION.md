---
phase: 09-buildings-construction
verified: 2026-09-07T02:51:12Z
status: passed
score: 5/5 success criteria verified
---

# Phase 09: Buildings & Construction Verification Report

**Phase Goal:** A player queues building upgrades that cost resources, take server-controlled time, and complete reliably even if a worker dies.
**Verified:** 2026-09-07T02:51:12Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths (Success Criteria from ROADMAP.md)

| # | Truth | Status | Evidence |
| --- | --- | --- | --- |
| 1 | The eighteen planned buildings load from versioned game data; no cost, duration or effect appears in PHP code, verified by an architecture test | ✓ VERIFIED | `packages/game-data/data/buildings.json` independently parsed: exactly 18 codes, all Palace-gated on level ≥2, Palace itself ungated at every level. Starter Palace confirmed level 3. `php artisan game:import-data` run live: "buildings: 18 definitions, 54 levels". `ArchitectureTest.php::it('keeps cost, duration and effect tables out of PHP')` and `it('gates buildings from data, never from a hardcoded prerequisite')` both present and green in a fresh `pest` run |
| 2 | Starting an upgrade debits resources atomically and writes started_at and finishes_at in UTC; the device clock is never read | ✓ VERIFIED | Read `BuildingUpgradeService::start()` in full: time comes only from injected `Clock::now()`; `finishes_at` computed via `BuildDuration::scaled()` (single, framework-free domain function, no `use` imports, no `config()`/`now()`); debit and order-creation share one `DB::transaction` with `lockForUpdate`. `ConstructionTimersTest` (3 tests) independently confirms UTC `+00:00` timestamps and that client-supplied `started_at`/`finishes_at`/`cost`/`target_level` are silently ignored |
| 3 | A completion job is idempotent: running it twice completes the upgrade once, verified by a test | ✓ VERIFIED (with a documented, correctly-resolved subtlety) | `ConstructionCompletionTest` (4 tests) green. Independently falsified: removed `->whereNull('completed_at')` from `ConstructionCompletionService::completeOverdueLocked` and re-ran the suite — confirms the SUMMARY's claim exactly: the job-path test *"completes the upgrade once when the job runs twice"* still passes (the job's own `$orderExists` pre-check in `CompleteConstruction::handle()` short-circuits first), while the added test *"completes once when the service itself is called twice"* fails (2 `CityStateChanged` events instead of 1). Restored the guard; `git status --porcelain apps/api/modules` clean; full suite re-confirmed green. This is not a false idempotency claim — it correctly identifies that the job path and the reconciler path each depend on a different, independently-necessary guard, and both are now tested |
| 4 | Killing the queue worker mid-timer and running the reconciler completes every overdue upgrade exactly once | ✓ VERIFIED | `ConstructionReconcilerTest` (4 tests) green: worker death simulated via `Queue::fake()` with the job never processed; a single `run()` completes the overdue order and returns `1`; a second `run()` returns `0` and changes nothing; the late, resurrected job also changes nothing; exactly one `CityStateChanged` across the whole scenario. A second test proves one `run()` finishes 3 concurrent overdue orders in one city. **Concern raised in 09-03 assessed:** `ConstructionReconciler::run()` scans `World::where('is_open', true)` only (confirmed by reading `World.php`/`WorldSelectionService.php` — `is_open=false` means an administratively-closed world, distinct from "full"). This does not block criterion 4 as literally stated: the criterion is about worker-death recovery in normal operation, which is fully proven; no Phase 09 success criterion or CONTEXT.md decision mentions maintenance-window semantics. The plan itself instructed the executor to pin this as deliberate rather than widen scope, and the SUMMARY records a proposed owner (Phase 31 or 50). Correctly deferred, not a gap |
| 5 | Exceeding the build queue slot limit returns BUILD_QUEUE_FULL and a max-level building returns BUILDING_MAX_LEVEL | ✓ VERIFIED | `ConstructionQueueLimitTest` (5 tests) green, including a genuinely new 09-01 catalogue building (`barracks`) exercised end-to-end as the fifth, refused order. `openapi.yaml`'s `/game/city/buildings/{code}/upgrade` documents a `400` naming `BUILDING_MAX_LEVEL, BUILDING_REQUIREMENTS_NOT_MET, BUILD_QUEUE_FULL, CITY_BUSY, INSUFFICIENT_RESOURCES` (confirmed by direct read of the spec) |

**Score:** 5/5 success criteria verified

### Required Artifacts

| Artifact | Expected | Status | Details |
| -------- | ----------- | ------ | ------- |
| `packages/game-data/data/buildings.json` | 18-building catalogue with Palace gate as data | ✓ VERIFIED | Independently parsed with Python, not trusted from SUMMARY |
| `packages/game-data/schema/buildings.schema.json` | JSON Schema 2020-12 for Building | ✓ VERIFIED | Valid JSON, present |
| `apps/api/modules/Shared/Interface/Console/ImportGameDataCommand.php` | `game:import-data` | ✓ VERIFIED | Ran live; correct output |
| `apps/api/tests/Architecture/ArchitectureTest.php` | Balance-out-of-PHP and Palace-gate-from-data rules | ✓ VERIFIED | Both new `it(...)` blocks present and green |
| `docs/adr/020-game-data-runtime-source.md` | Deviation from ADR-013 recorded | ✓ VERIFIED (existence + reference confirmed via grep in prior plan acceptance criteria; not re-read in full) | |
| `apps/api/modules/Construction/Domain/BuildDuration.php` | Single time_scale application | ✓ VERIFIED | Read in full: zero imports, pure static function |
| `apps/api/modules/City/Application/CityStateService.php` | `constructions[]` + `queue_limit` | ✓ VERIFIED | Confirmed via grep and code read |
| `packages/contracts/openapi.yaml` | `constructions`, `queue_limit`, documented 400 | ✓ VERIFIED | Confirmed by direct read; `npm run contracts:check` exits 0 |
| `apps/api/modules/Construction/Domain/BuildingRequirement.php` | Typed requirement value object | ✓ VERIFIED (existence + arch-test enforcement confirmed; not re-read line-by-line) | |
| `apps/api/modules/Construction/Application/BuildingRequirementEvaluator.php` | Generic requirement evaluation | ✓ VERIFIED | `BuildingUpgradeService::start()` calls `$this->requirements->unmet(...)` inside the lock, between max-level and queue-full checks, confirmed by direct code read |
| `apps/api/tests/Feature/Construction/*.php` (4 files) | Idempotency, reconciler, queue-limit, timers proofs | ✓ VERIFIED | All 4 files present; 16 tests, all green in a fresh run |
| `apps/mobile/src/shared/components/buildingIcons.ts` | 18-building glyph map | ✓ VERIFIED | Read in full: all 18 catalogue codes mapped, zero fallback usage in the map itself |
| `apps/mobile/src/features/city/api/useUpgradeBuilding.ts` | Upgrade mutation, invalidates `['game','city']` | ✓ VERIFIED | Read in full: `onSettled` (not `onSuccess`), `invalidateQueries` present |
| `apps/mobile/src/features/city/components/ConstructionQueueStrip.tsx` | Queue occupancy strip | ✓ VERIFIED | Read in full: 44×44 pips, `accessibilityElementsHidden` on empty pips, progress bar with skew correction |
| `apps/mobile/__tests__/building-upgrade.test.tsx` | Five CTA states + snapshot rule | ✓ VERIFIED (existence + passing confirmed via `npm test`; not re-read line-by-line) | |

### Key Link Verification

| From | To | Via | Status | Details |
| ---- | --- | --- | ------ | ------- |
| `buildings.json` | locale catalogues | `name_key` | ✓ WIRED | `game:import-data` rule 6 validates all `en`/`pt-BR`/`es` translations exist; command ran clean |
| `CityStateService.php` | `BuildDuration::scaled` | preview scaling | ✓ WIRED | Confirmed by grep + code read |
| `BuildingUpgradeService.php` | `BuildDuration::scaled` | finishes_at computation | ✓ WIRED | Confirmed by code read |
| `BuildingUpgradeService.php` | `BuildingRequirementEvaluator` | `requirements->unmet(...)` inside lock | ✓ WIRED | Confirmed by code read, correct position (between max-level and queue-full checks) |
| `CityScene.tsx` | `city.constructions` | per-slot lookup | ✓ WIRED | Confirmed: `constructionByCode` Map, `.some()`-style per-code lookup replaces old singular field |
| `CitySlotDetailSheet.tsx` | `useUpgradeBuilding` | mutation on CTA press | ✓ WIRED | Confirmed by code read |
| `CitySlotDetailSheet.tsx` | `resources` prop (server snapshot) | affordability comparison | ✓ WIRED | Confirmed: no `interpolateResources`, `ResourceBar`, or `useCityQuery` import in the sheet; `resources` prop passed from `city.resources.current` in `CityScene.tsx` |
| `ConstructionQueueStrip.tsx` | `citySelectionStore.selectSlot` | filled pip onPress | ✓ WIRED | Confirmed by code read |
| `ConstructionReconciler` | scheduler | `routes/console.php` every-minute | ✓ WIRED | `construction-reconcile` schedule entry confirmed present |

### Requirements Coverage

| Requirement | Source Plan(s) | Description | Status | Evidence |
| ----------- | ---------- | ----------- | ------ | -------- |
| REQ-05 | 09-02, 09-03, 09-04, 09-05 | Timed gameplay anchored to server time only | ✓ SATISFIED | Clock-only timestamps proven by `ConstructionTimersTest`; `BuildDuration::scaled` is the single time_scale site |
| REQ-06 | 09-01, 09-04 | Data-driven balancing: no balance number hardcoded in application code | ✓ SATISFIED | Architecture tests (`keeps cost, duration and effect tables out of PHP`, `gates buildings from data`) both green; buildings.json is the sole source of cost/duration/effect/requirement data |
| REQ-09 | 09-02, 09-03, 09-05 | Idempotent, concurrency-safe commands; no double-spend under any race | ✓ SATISFIED | `EconomyConcurrencyTest` (Phase 08) already covers replay-with-same-`Idempotency-Key` and concurrent-race on `farm/upgrade` specifically; Phase 09 adds job/reconciler idempotency (`ConstructionCompletionTest`, `ConstructionReconcilerTest`) and requirements-refusal-spends-nothing (`BuildingRequirementsTest`) |

No orphaned requirements: REQ-05, REQ-06, REQ-09 (the three IDs the ROADMAP maps to Phase 09) all appear in at least one plan's `requirements:` frontmatter.

### Anti-Patterns Found

None. Swept every production file this phase created or modified (Construction module domain/application/interface files, `ImportGameDataCommand.php`, `CityStateService.php`, and the five key mobile files) for `TODO|FIXME|XXX|HACK|PLACEHOLDER`, stub-style empty returns, and console-log-only implementations. No matches beyond legitimate, commented `null`-return branches that are the documented fail-closed behavior (e.g., `BuildingRequirement::fromArray` returning `null` on a malformed row, `CompleteConstruction::handle()` returning early for a missing city/order).

### Human Verification Required

None required to close out this phase's automated success criteria. One item is worth a human glance opportunistically, not blocking:

1. **Queue strip visual rhythm and the manual screenshot step in 09-05's plan**

   **Test:** Open the running app, tap a building plot, confirm cost chips and the `HH:MM:SS` duration match; tap Upgrade; confirm a queue pip fills and its progress bar advances; tap the filled pip from the queue strip and confirm it reopens the correct building's sheet.
   **Expected:** Matches 09-UI-SPEC.md § C exactly (44×44pt pips, non-red disabled states, bronze progress fill).
   **Why human:** This is a rendered-pixels/interaction-feel check the automated Jest suite (which mocks `@expo/vector-icons`, `@gorhom/bottom-sheet`) cannot fully substitute for. Not required to pass this phase's automated success criteria — 09-05's SUMMARY notes the manual verification step from its own plan was not separately re-confirmed with a fresh screenshot in this pass, but every acceptance-criterion grep and all 14 Jest suites (86 tests) are independently green in this verification run.

### Gaps Summary

No gaps found. All five ROADMAP.md success criteria for Phase 09 are independently verified against the actual codebase (not merely against SUMMARY claims):

- Live re-ran `make test` (191 API tests), `make analyse` (PHPStan level 8, 0 errors), `npm test` (86 mobile tests, 14 suites), `npm run typecheck` (4 workspaces clean), Pint (223 files clean), `npm run contracts:check` (exit 0), and `npm run gamedata:validate` / `php artisan game:import-data` live — all green, matching the numbers claimed in the task brief exactly.
- Independently parsed `buildings.json` and `starter.json` with Python rather than trusting the SUMMARY's own grep output.
- Independently read `BuildingUpgradeService.php`, `ConstructionCompletionService.php`, `CompleteConstruction.php`, and `ConstructionReconciler.php` in full — the check-order, the Clock-only timestamp sourcing, and the two-layer idempotency guard structure all match what the plans and summaries claim.
- Independently re-ran the 09-03 falsification (removed `whereNull('completed_at')` from the service, confirmed the exact pass/fail split the SUMMARY reports, restored the guard, confirmed a clean `git status`). The SUMMARY's reasoning about *which* guard the job path versus the reconciler path each depend on is accurate, not an overclaim.
- Assessed the "reconciler ignores closed worlds" concern against the actual `is_open` semantics (confirmed via `WorldSelectionService.php` that this denotes an administrative closure, not merely "full") and against 09-CONTEXT.md's own decisions — correctly out of this phase's scope, appropriately deferred with a named owner phase, does not block criterion 4 as literally worded.
- Assessed the vector-glyph-instead-of-generated-art deferral against 09-CONTEXT.md's own `<deferred>` section, which explicitly lists "Building visuals (Phase 44)" as out of scope for Phase 09 — the STATE.md-recorded billing block on both Gemini and OpenAI image routes is a correctly-recorded, pre-authorized deferral, not a criterion gap. Phase 09's success criteria never require generated art; the eighteen buildings render as distinct, verified `MaterialCommunityIcons` glyphs per the approved 09-UI-SPEC.md contract (binding constraint #1, Flagged Assumption 3), and every glyph was independently confirmed mapped in `buildingIcons.ts` with zero unmapped-fallback usage among the 18 real codes.
- Read every mobile artifact this phase's plans claim to have created (`buildingIcons.ts`, `useUpgradeBuilding.ts`, `ConstructionQueueStrip.tsx`, `CitySlotDetailSheet.tsx`, `CityScene.tsx`) in full and confirmed the five-state CTA, server-snapshot-only affordability, and queue-strip wiring match the plan and UI-SPEC exactly — no stubs, no placeholder returns, no shortcut around the "never the interpolated bar" constraint.
- No orphaned requirements; REQ-05, REQ-06, REQ-09 all traced to satisfying evidence.

---

_Verified: 2026-09-07T02:51:12Z_
_Verifier: Claude (gsd-verifier)_
