---
phase: 08-resources-economy
verified: 2026-09-05T00:00:00Z
status: human_needed
score: 5/5 ROADMAP truths verified (automated); 1 UI item needs a human simulator check
human_verification:
  - test: "Take one portrait screenshot each of the city tab and the world tab on a mid-range-equivalent simulator, with the new ResourceBar mounted above the tab navigator."
    expected: "Neither the 18-plot slot grid (city tab) nor the map canvas (world tab) is clipped or pushed off the bottom edge by the bar. The bar itself does not overlap the notch/status bar, and it does not wrap to a height that crowds the tab bar."
    why_human: "The bar has no fixed height by design (flex-sibling composition per CLAUDE.md's runtime-HUD-measurement rule), so its actual rendered height on a real/simulated device — including any text-wrapping edge case — cannot be asserted from a DOM/JSX string in Jest. The automated regression test in city-scene.test.tsx proves the specific 'double-counted inset' regression (grep for insets.top/ResourceCounter) but cannot see general layout clipping. Plan 08-05 itself deferred this exact check to phase verification (08-05 <verification> item 7)."
---

# Phase 08: Resources & Economy Verification Report

**Phase Goal:** Resources accrue over real time, are capped by storage, and can never be duplicated or spent twice.
**Verified:** 2026-09-05
**Status:** human_needed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths (ROADMAP Success Criteria)

| # | Truth | Status | Evidence |
|---|---|---|---|
| 1 | Production is computed from elapsed server time on read; a city closed six hours and one polled every minute reach the identical total | VERIFIED | `CityEconomyFoundationTest::it('produces the same balance whether a city is read hourly or after a long absence')` — pre-existing test, untouched (confirmed byte-identical to the pre-08 baseline per plan 08-02/08-03/08-04 diffs), still green. `CityEconomyService::accrueLocked()` multiplies a per-second game-data effect by elapsed seconds, independent of poll frequency. |
| 2 | Every resource mutation writes a ledger row recording source, destination, resource, amount, reason and reference | VERIFIED | All four ledger write sites (`accrueLocked`, `debitLocked`, `creditLocked` in `CityEconomyService.php`, and the starter grant in `GameBootstrapService.php`) call `EconomyLedger::record()` exclusively, each supplying a real, semantically-correct `LedgerParty` per the direction table (verified by reading every call site directly — see Key Link table). `grep -rn "EconomyLedger::create(" apps/api/modules/` outside the model returns nothing. An architecture test (`it('routes every ledger write through EconomyLedger::record')`) mechanically forbids a new bypass. `LedgerAuditTrailTest` proves a real guest→bootstrap→read→upgrade session writes `starter.grant`, `production.elapsed` and `building.upgrade` rows each in the documented direction, none carrying the `system:legacy` migration default. |
| 3 | Two concurrent spend requests for the same resources result in exactly one success and one INSUFFICIENT_RESOURCES, verified by a concurrency test | VERIFIED (with an honest environmental caveat, see narrative below) | `EconomyConcurrencyTest::it('resolves two competing spends...')`. Independently re-run: passes (2 tests / 33 assertions). I independently disabled the race listener two different ways and confirmed the test fails both times (see narrative) — the proof is not a tautology. |
| 4 | Resources never exceed warehouse capacity; overflow is discarded at the cap and recorded, and the API returns WAREHOUSE_CAPACITY_EXCEEDED where relevant | VERIFIED (with a documented scope caveat, see narrative) | `WarehouseCapacityTest` (4 tests): cap holds on both the accrual path and the strict-grant path, overflow is recorded in `overflow_amount`, a strict refusal mutates nothing and writes zero ledger rows, and the error renders through the real `bootstrap/app.php` `GameException` funnel as HTTP 400 with `error.code = WAREHOUSE_CAPACITY_EXCEEDED` and no `data` key. |
| 5 | Summing the ledger for any city reproduces its current balance exactly, verified by a property test over random operation sequences | VERIFIED | `CityEconomyFoundationTest::it('reconciles every city balance to the append-only ledger over a random operation sequence')` — genuinely randomised (`mt_rand`-driven credit/debit/time-advance over 120 steps), seeded via `ECONOMY_PROPERTY_SEED` (env override) falling back to `random_int`, failure messages carry the seed via `assertSame`/`assertGreaterThanOrEqual`/`assertLessThanOrEqual`. Independently re-ran three fresh-seed passes (all green) and `ECONOMY_PROPERTY_SEED=12345` twice (identical results both times, confirming reproducibility). |

**Score:** 5/5 ROADMAP truths verified by direct code inspection, independent test execution, and (for criterion 3) independent adversarial tampering.

### Depth on criterion 3 — is the concurrency test a genuine proof?

I read `BuildingUpgradeController::__invoke` and `BuildingUpgradeService::start()` in full and confirmed the interleaving seam the plan describes actually exists in the live code: the controller calls `GameBootstrapService::handle($account)` (its own `DB::transaction`, which for an already-bootstrapped account is a no-op read that still touches `Player::query()->first()`) and only afterwards calls `BuildingUpgradeService::start()`, which opens its **own** `DB::transaction`, re-reads `Player`, locks `City` with `lockForUpdate()`, and **only then** reads `$this->economy->balances($city)` for the affordability check. The `EconomyConcurrencyTest` listener fires on `eloquent.retrieved: Player`, i.e. exactly inside the bootstrap read, and dispatches the rival `lumber_mill` upgrade from there — meaning the rival's debit is applied to the shared SQLite `:memory:` connection *before* the loser's own locked `City` read executes, so the loser's affordability check unavoidably sees the post-rival balance.

I independently verified this two ways, not just by reading the summary's claim:
1. Commented out the `Event::listen(...)` registration entirely and re-ran the test: it failed exactly as expected, at `expect($raced)->toBeTrue()` (`Failed asserting that false is true.`).
2. Left the listener registered (so `$raced` still becomes `true`) but disabled only the rival's `BuildingUpgradeService::start()` call inside it, and re-ran: the test failed at the *next* assertion (`expect($loser)->toBeApiError(...)`) with `Expected response status code [400] but received 201` — proving the assertion's pass depends on the rival's mutation actually landing, not merely on the flag being set.

I then restored the file (confirmed `git diff` clean) and re-ran the full concurrency suite green (2 tests, 33 assertions).

**Honest caveat, stated plainly:** this is a single-connection SQLite interleaving injection, not two real OS threads/connections. Genuine multi-connection parallelism is architecturally impossible in the default test environment (`phpunit.xml` pins `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`). What the test actually proves — and the only thing achievable in this environment — is that the affordability decision is made from a `City` row read *after* the interleaved rival write, inside the lock, rather than from a stale pre-race snapshot. That is the exact defect class ("read outside the lock") that would cause a real double-spend under true concurrency, and this test would catch a regression of it (e.g. someone hoisting the balance check above the locked read, or reusing an in-memory `$city` object captured before the lock). It would **not** catch a bug that is purely about database-level lock contention semantics (e.g. a missing `lockForUpdate()` clause producing a lost-update race under real concurrent connections on PostgreSQL) — that class of bug is out of reach of any SQLite-only test and is explicitly flagged as a known environmental limit in `.planning/codebase/CONCERNS.md`, not something this phase's plan overlooked.

### Depth on criterion 5 — is it a real property test?

Confirmed in the source (`CityEconomyFoundationTest.php` lines 73-161): `$seed = (int) (getenv('ECONOMY_PROPERTY_SEED') ?: random_int(1, 2_147_483_647)); mt_srand($seed);`, 120 steps of `mt_rand`-chosen operation/resource/amount/seconds, and every failure assertion (`assertSame`, `assertGreaterThanOrEqual`, `assertLessThanOrEqual`) embeds `"seed {$seed}"` or `ECONOMY_PROPERTY_SEED=%d` in its message. I independently ran it 3 times with fresh random seeds (all green) and twice with `ECONOMY_PROPERTY_SEED=12345` fixed (identical assertion counts and pass/fail both times) — this is a genuine, reproducible property test, not a fixed arithmetic walk (the old `($step * 37) % 121` sequence is confirmed gone).

### Depth on criterion 2 — do source/destination carry real meaning?

Read every one of the four write sites directly in the current source (not the plan, not the summary):

| Site | source | destination | Matches documented direction? |
|---|---|---|---|
| `GameBootstrapService` starter grant | `LedgerParty::system('starter')` | `LedgerParty::city($city)` | Yes |
| `CityEconomyService::accrueLocked` | `LedgerParty::system('production')` | `LedgerParty::city($city)` | Yes |
| `CityEconomyService::debitLocked` | `LedgerParty::city($city)` | caller-supplied `$destination` (e.g. `BuildingUpgradeService` passes `LedgerParty::system('construction')`) | Yes |
| `CityEconomyService::creditLocked` | caller-supplied `$source` | `LedgerParty::city($city)` | Yes |

`LedgerAuditTrailTest` independently confirms this over a real HTTP session (guest → bootstrap → two city reads 600s apart → farm upgrade), asserting exact string equality per reason, and asserting no row anywhere carries the `system:legacy` migration backfill default. `EconomyLedger::booted()` throws `RuntimeException` on `update`/`delete`, confirmed by test and by reading the model directly.

### Depth on criterion 4 — is WAREHOUSE_CAPACITY_EXCEEDED genuinely reachable?

Confirmed: `CityEconomyService::creditLocked()` throws it from a real, non-test code path when called with `OverflowPolicy::Refuse` and the grant would exceed capacity — the check runs before any mutation (all-or-nothing), verified by `WarehouseCapacityTest`'s refusal test asserting the balance and ledger row count are unchanged. The one honest nuance, stated plainly as the task asked: **no production gameplay endpoint calls `creditLocked` with `OverflowPolicy::Refuse` yet** — the phase's own locked decision records that the first real caller is Phase 16 (gathering returns), and inventing a throwaway gameplay endpoint here would have widened Phase 08's scope, which `08-CONTEXT.md` forbids. The HTTP-envelope proof (`WarehouseCapacityTest`'s fourth test) therefore exercises a test-only probe route that throws the same `GameException` and asserts it renders through the real `bootstrap/app.php` exception-render funnel — the actual application infrastructure a future caller would rely on — as HTTP 400 with `error.code = "WAREHOUSE_CAPACITY_EXCEEDED"` and no `data` key.

**My conclusion:** this satisfies the ROADMAP wording "the API returns WAREHOUSE_CAPACITY_EXCEEDED where relevant" for what Phase 08 can honestly own. The mechanism is real, reachable from application code (not dead code — confirmed via `grep -rn "WarehouseCapacityExceeded" apps/api/modules/` returning hits in `CityEconomyService.php` and multiple test files), all-or-nothing, and proven to render correctly end-to-end through the app's real error pipeline. It is not proven through a real gameplay HTTP endpoint because none exists yet by design — that is a phase-boundary decision, not a gap in this phase's delivery.

## Required Artifacts

| Artifact | Expected | Status | Details |
|---|---|---|---|
| `packages/contracts/openapi.yaml` | `ResourceRate` schema + `CityResources.required: [current, capacity, rate]` | VERIFIED | Both present exactly as specified (lines 762-770, 1104-1120); no `minimum: 0` inside `ResourceRate` (signed field, confirmed). |
| `packages/contracts/src/generated/api.ts` | Generated TS carrying `ResourceRate` | VERIFIED | `npm run typecheck` green across all 4 workspaces; contract regenerated and checked in. |
| `apps/api/modules/Economy/Application/CityEconomyService.php` | `ratesPerHour()`, `debitLocked`/`creditLocked` with `LedgerParty`, `OverflowPolicy::Refuse` pre-flight check | VERIFIED | Read in full; matches every plan interface exactly, including the `SECONDS_PER_HOUR` named constant and the pre-mutation `Refuse` check. |
| `apps/api/modules/Economy/Domain/OverflowPolicy.php` | `enum OverflowPolicy: string { DiscardAtCap, Refuse }` | VERIFIED | Exists, framework-free, `arch` group green. |
| `apps/api/modules/Economy/Domain/LedgerParty.php` | `final readonly class LedgerParty` with `city()`/`system()` factories and format validation | VERIFIED | Exists; `InvalidArgumentException` on malformed input confirmed by `LedgerAuditTrailTest`. |
| `apps/api/modules/Economy/Infrastructure/EconomyLedger.php` | `record()` as sole write path, append-only guards | VERIFIED | `static::updating`/`static::deleting` throw `RuntimeException`; confirmed by test and by direct read. |
| `apps/api/database/migrations/2026_09_06_000100_add_ledger_parties_to_economy_ledger.php` | `source`/`destination` columns + sign-based backfill | VERIFIED | Exists; uses portable `||` concatenation (no `CONCAT()`), confirmed. |
| `apps/api/tests/Feature/Economy/ProductionRateTest.php` | Proof wire rate = real accrual | VERIFIED | 76 lines (min 60), 3 tests, all pass. |
| `apps/api/tests/Feature/Economy/WarehouseCapacityTest.php` | Cap, discard, refusal, HTTP envelope | VERIFIED | 133 lines (min 90), 4 tests, all pass. |
| `apps/api/tests/Feature/Economy/LedgerAuditTrailTest.php` | Both parties, direction, append-only | VERIFIED | 118 lines (min 90), 4 tests, all pass. |
| `apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php` | Interleaved race + double-submit proof | VERIFIED | 156 lines (min 120), 2 tests, all pass; independently proven non-tautological (see above). |
| `apps/mobile/src/features/economy/interpolation/interpolateResources.ts` | Pure, clamped, no-RN-import projection | VERIFIED | No `react`/`react-native` import; clamps at capacity, floors backward-extrapolation at 0, `Math.floor`s to int; zero-capacity unclamped and negative-rate-drains-to-zero both implemented exactly as specified. |
| `apps/mobile/src/features/economy/components/ResourceBar.tsx` | Persistent HUD strip | VERIFIED | 133 lines (min 80); no fixed height, no hex literals, no `accessibilityRole`, uses `dataUpdatedAt` as the interpolation baseline (not a second `Date.now()` capture). |
| `apps/mobile/src/shared/components/resourceIcons.ts` | Resource→glyph map | VERIFIED | Contains `barley`, `tray-alert`, all five `RESOURCE_KEYS`. |
| `apps/mobile/src/features/city/api/useCityQuery.ts` | Shared `['game','city']` query, 30s refetch | VERIFIED | Exports `useCityQuery`; `refetchInterval: 30_000`. |

## Key Link Verification

| From | To | Via | Status | Details |
|---|---|---|---|---|
| `CityStateService::handle()` | `CityEconomyService::ratesPerHour` | `'rate' => $this->economy->ratesPerHour($city)` | WIRED | Confirmed at `CityStateService.php:119`, called after `accrueLocked`. |
| `CityEconomyService` (3 sites) | `EconomyLedger::record` | every accrual/debit/credit write | WIRED | `grep -c "EconomyLedger::record("` in the service returns 3, matching plan exactly. |
| `GameBootstrapService` | `EconomyLedger::record` | starter grant | WIRED | Confirmed inline, correct parties. |
| `BuildingUpgradeService` | `CityEconomyService::debitLocked` | `LedgerParty::system('construction')` as destination | WIRED | Confirmed at call site. |
| `CityEconomyService::creditLocked` | `ErrorCode::WarehouseCapacityExceeded` | pre-mutation `GameException` under `Refuse` | WIRED | Confirmed; all-or-nothing, zero ledger rows on refusal (test-proven). |
| `bootstrap/app.php` | `ApiResponse::error` | `GameException` render funnel | WIRED | Exercised end-to-end by `WarehouseCapacityTest`'s probe-route test; HTTP 400, correct `error.code`, no `data` key. |
| `EconomyConcurrencyTest` | `BuildingUpgradeService` | `eloquent.retrieved: Player` interleaving injection | WIRED (adversarially confirmed genuine, not tautological) | See narrative above — two independent tamper tests both fail as expected. |
| `app/(tabs)/_layout.tsx` | `ResourceBar` | sibling above `<Tabs>` inside `flex:1` View | WIRED | `<ResourceBar />` appears before `<Tabs` in source order; confirmed by grep and by the automated architecture test. |
| `ResourceBar.tsx` | `useCityQuery` | `dataUpdatedAt` as interpolation baseline | WIRED | Confirmed — `capturedAt: cityQuery.dataUpdatedAt`, not a second `Date.now()`. |
| `app/_layout.tsx` | `@tanstack/react-query focusManager` | `AppState` listener | WIRED | Both `focusManager.setEventListener` and `AppState` confirmed present. |
| `interpolateResources` | affordability checks (mobile) | — (must be absent) | CORRECTLY NOT WIRED | Grepped the whole mobile app: `interpolateResources`/`displayed` are used only inside `ResourceBar.tsx` for display. No mobile screen currently performs an affordability check at all (building-upgrade UI does not exist yet on the client), so there is no live risk of the interpolated, floating-point display value being used to gate a spend. |

## Requirements Coverage

*(No `.planning/REQUIREMENTS.md` exists in this repo; cross-referenced against `.planning/PROJECT.md`'s checkbox list per the task's traceability note.)*

| Requirement | Source Plans | Description | Status | Evidence |
|---|---|---|---|---|
| REQ-02 | 08-01, 08-02, 08-03, 08-04, 08-05 | Integer-only economy with an auditable ledger; no float touches a resource | MATERIALLY ADVANCED for this phase's scope | Server-side: every stored balance/capacity/rate value is `int`-cast; no float anywhere in `CityEconomyService`. Client-side: `interpolateResources` uses floating-point math but is explicitly display-only, `Math.floor`s before render, and is never fed into a stored value or an affordability check (confirmed by grep, see Key Links). `PROJECT.md` still lists REQ-02 under "Active" (unchecked) — this is expected and correct, since REQ-02 is a project-wide requirement spanning every future resource-touching phase (Phase 09 buildings, Phase 12 combat/upkeep, Phase 16 gathering, etc.), not something Phase 08 alone can mark "Validated." |
| REQ-09 | 08-03, 08-04 | Idempotent, concurrency-safe commands; no double-spend under any race | MATERIALLY ADVANCED for this phase's scope | Proven at the HTTP + ledger level for a real resource-spending command (`EconomyConcurrencyTest`, both tests, independently re-verified as genuine above). Remains "Active" (unchecked) in `PROJECT.md` because the requirement's full scope ("any race", "commands" plural) spans future combat/march/alliance commands not yet built — correctly not claimed complete by this phase alone. |

No orphaned requirements found: `PROJECT.md`'s REQ-02/REQ-09 rows match exactly what all five plans declared.

## Anti-Patterns Found

None. Scanned every new/modified Economy and mobile-economy file for `TODO|FIXME|XXX|HACK|PLACEHOLDER|coming soon` — zero hits. No `return <div>Placeholder</div>`-style stubs, no empty handlers, no `console.log`-only implementations. `ResourceBar.tsx` and `ResourceCounter.tsx` contain zero uses of `danger` colour and zero hex literals (grep-confirmed), matching the colour-only-signal prohibition.

## Independent Verification Performed (beyond reading the SUMMARYs)

- Ran `cd apps/api && ./vendor/bin/pest` fresh: **158 passed, 1263 assertions** — matches the stated known_state exactly.
- Ran `npm test` fresh: **13 suites, 74 tests, all passed** — matches exactly.
- Ran `phpstan analyse --memory-limit=1G`: 0 errors. `pint --test`: clean. `npm run typecheck` and `npm run lint`: clean across all 4 workspaces.
- Ran `./vendor/bin/pest --group=arch`: 14 tests, 59 assertions, all passed (both the write-path guard and the never-update/delete guard).
- **Adversarially tampered with `EconomyConcurrencyTest.php` twice** (disabling the listener entirely; then leaving the flag but disabling the rival call) and confirmed the race test fails both times, for the two different reasons the design predicts. Restored the file and confirmed `git diff` was clean and the suite green again afterward.
- Ran the property test 3 times with fresh random seeds (all green) and twice with `ECONOMY_PROPERTY_SEED=12345` (identical output both times).
- Read every one of the four `EconomyLedger::record()` call sites directly in source and cross-checked each against the plan's locked direction table — all four match exactly.
- Verified `grep -rn "EconomyLedger::create(" apps/api/modules/` outside the model returns nothing (no bypass of the guarded write path exists).
- Verified localization keys (`storage_full_short`, `bar_accessibility`, `accessible_reading`, `accessible_full`) exist with correct leaf values in all three locale catalogues (en, pt-BR, es).
- Verified `insets.top` and `ResourceCounter` are both absent from `CityScene.tsx`, and `<ResourceBar />` is mounted above `<Tabs>` in `app/(tabs)/_layout.tsx`, and `focusManager`/`AppState` are wired in `app/_layout.tsx`.
- Confirmed `git status --short` is clean and every referenced commit exists in `git log`.

## Human Verification Required

### 1. Resource bar layout on a real/simulated device

**Test:** Run the mobile app on a mid-range-equivalent simulator (as plan 08-05 itself specifies), open the city tab and the world tab in portrait, and take one screenshot of each with the new persistent `ResourceBar` mounted above the tab navigator.
**Expected:** Neither the 18-plot city slot grid nor the world map canvas is clipped, compressed, or pushed off the bottom edge by the bar. The bar sits flush under the status bar/notch with no double-counted inset and no overlap. If any resource cell's icon+numeral+meter+MAX caption combination causes the bar to wrap or grow taller than expected on a narrow device, it does not crowd out the tab bar below it.
**Why human:** The bar deliberately has no fixed height (by design, to satisfy the project's runtime-HUD-measurement rule), so its real rendered height — including any text-wrapping edge case on a narrow device — cannot be asserted from Jest's JSX string output. The automated regression test added in this phase (`city-scene.test.tsx`, "leaves the top safe area to the resource bar...") proves the specific *double-inset* regression it was written to guard against, but by its own design (grep-based source assertions) it cannot see general visual clipping. Plan `08-05-mobile-resource-bar-PLAN.md` itself defers exactly this check to phase verification (`<verification>` item 7), and the project's own `CLAUDE.md` rule ("Confira com captura de tela antes de dar por pronto") makes this check mandatory before the UI half of this phase can be called fully done.

Note: per the documented known_state, Gemini/OpenAI image generation is billing-blocked, and this phase's UI-SPEC deliberately required no generated art — that constraint is unrelated to this screenshot check, which is about layout, not art assets, and is not a gap.

### Gaps Summary

No functional gaps found. All five ROADMAP success criteria are backed by real, working code and passing tests, independently re-verified rather than taken on the SUMMARYs' word — including one criterion (concurrency) that was specifically adversarially tampered with to rule out a tautological pass. The single open item is a device/simulator visual check that the phase's own plan explicitly deferred to this verification step; it blocks nothing structurally and there is no reason to expect it to fail given the bar's flex-based, no-fixed-height composition, but it has not been executed by a human and I cannot execute it myself.

---

*Verified: 2026-09-05*
*Verifier: Claude (gsd-verifier)*
