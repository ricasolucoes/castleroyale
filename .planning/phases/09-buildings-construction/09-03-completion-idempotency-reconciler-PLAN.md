---
phase: 09-buildings-construction
plan: 03
type: execute
wave: 3
depends_on: ["09-01", "09-02", "09-04"]
files_modified:
  - apps/api/tests/Feature/Construction/ConstructionCompletionTest.php
  - apps/api/tests/Feature/Construction/ConstructionReconcilerTest.php
  - apps/api/tests/Feature/Construction/ConstructionQueueLimitTest.php
  - apps/api/tests/Feature/Construction/ConstructionTimersTest.php
autonomous: true
requirements: [REQ-05, REQ-09]

must_haves:
  truths:
    - "Running the completion job twice completes the upgrade once — the second run applies no level, writes no event and re-debits nothing"
    - "A worker that dies mid-timer costs nothing: the reconciler finishes every overdue order exactly once, and the late job that finally runs changes nothing"
    - "Running the reconciler twice in a row completes the same order once"
    - "A fifth concurrent order returns BUILD_QUEUE_FULL, and an upgrade past max_level returns BUILDING_MAX_LEVEL, both with HTTP 400"
    - "started_at and finishes_at come from the injected Clock in UTC; a client-supplied timestamp, duration or cost is ignored entirely"
  artifacts:
    - path: "apps/api/tests/Feature/Construction/ConstructionCompletionTest.php"
      provides: "Phase 09 success criterion 3 — job idempotency"
      contains: "CompleteConstruction"
    - path: "apps/api/tests/Feature/Construction/ConstructionReconcilerTest.php"
      provides: "Phase 09 success criterion 4 — worker death and reconciliation"
      contains: "ConstructionReconciler"
    - path: "apps/api/tests/Feature/Construction/ConstructionQueueLimitTest.php"
      provides: "Phase 09 success criterion 5 — BUILD_QUEUE_FULL and BUILDING_MAX_LEVEL"
      contains: "BuildQueueFull"
    - path: "apps/api/tests/Feature/Construction/ConstructionTimersTest.php"
      provides: "Phase 09 success criterion 2 — UTC server-owned timers"
      contains: "started_at"
  key_links:
    - from: "apps/api/tests/Feature/Construction/ConstructionReconcilerTest.php"
      to: "Game\\Construction\\Application\\ConstructionReconciler"
      via: "app(ConstructionReconciler::class)->run()"
      pattern: "ConstructionReconciler::class"
    - from: "apps/api/tests/Feature/Construction/ConstructionCompletionTest.php"
      to: "Game\\City\\Interface\\Broadcasting\\CityStateChanged"
      via: "Event::assertDispatchedTimes"
      pattern: "assertDispatchedTimes"
---

<objective>
Prove Phase 09 success criteria 2, 3, 4 and 5. Every mechanism they describe is
already implemented — `ConstructionCompletionService` guards on
`completed_at IS NULL`, `CompleteConstruction` re-checks the order before acting,
`ConstructionReconciler` is registered on the every-minute schedule, and the three
error codes are thrown — but there is no `tests/Feature/Construction/` directory at
all, so none of it is proven. Construction is currently exercised only incidentally,
by `MvpGameplayTest` and Phase 08's economy tests.

Purpose: an idempotency guard nobody has ever seen fail is a guess. This plan is
where the phase's central claim — *"complete reliably even if a worker dies"* —
stops being a design statement.
Output: four feature test files, no production change. If a test exposes a genuine
defect, fix it and say so loudly in the SUMMARY.
</objective>

<execution_context>
@/Users/sierra/.claude/get-shit-done/workflows/execute-plan.md
@/Users/sierra/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/ROADMAP.md
@.planning/STATE.md
@.planning/phases/09-buildings-construction/09-CONTEXT.md
@.planning/codebase/TESTING.md
@.planning/codebase/CONCERNS.md
@docs/backend/schedulers.md
@docs/backend/jobs-and-queues.md
@.planning/phases/09-buildings-construction/09-02-SUMMARY.md
@.planning/phases/09-buildings-construction/09-04-SUMMARY.md

<interfaces>
<!-- The production code under test. It is not modified by this plan. -->

```php
// Game\Construction\Interface\Jobs\CompleteConstruction
public function __construct(private readonly string $worldId, private readonly string $cityId, private readonly string $orderId) {}
public function handle(Clock $clock, ConstructionCompletionService $completion): void;
// early-returns when the city is gone or the order already has completed_at
```

```php
// Game\Construction\Application\ConstructionCompletionService
public function completeOverdueLocked(City $city, DateTimeImmutable $now): void;
// selects orders where finishes_at <= now AND completed_at IS NULL, lockForUpdate;
// per order: sets CityBuilding.level = target_level, sets completed_at, dispatches CityStateChanged
```

```php
// Game\Construction\Application\ConstructionReconciler
public function run(): int;   // number of overdue orders completed, across every open world
```

```php
// Game\Construction\Infrastructure\ConstructionOrder
// table construction_orders; columns world_id, city_id, building_code, from_level,
// target_level, idempotency_key, started_at, finishes_at, completed_at
```

```php
// Game\City\Interface\Broadcasting\CityStateChanged
public function __construct(public readonly string $cityId, public readonly string $worldId, public readonly string $occurredAt) {}
```

Dispatch is conditional in `BuildingUpgradeService::start()`:

```php
if (config('queue.default') !== 'sync') {
    CompleteConstruction::dispatch($worldId, $city->getKey(), $order->getKey())
        ->onQueue('gameplay')->delay($finishesAt)->afterCommit();
}
```

`apps/api/phpunit.xml` forces `QUEUE_CONNECTION=sync`, so **no job is dispatched in
the default test run** — the completion happens only on the lazy read path. A test
that wants the job must set `config(['queue.default' => 'redis'])` together with
`Queue::fake()`.

Test fixtures in `apps/api/tests/Pest.php`: `freezeClock(string $iso8601): FrozenClock`
(returns a clock with `advanceSeconds(int)`), and the expectations
`toBeApiSuccess(int $status = 200)` / `toBeApiError(ErrorCode $code, ?int $status = null)`.

Starter city after 09-01: palace **level 3** (its `max_level`), farm / lumber_mill /
quarry / warehouse at level 1, resources 500 food / 500 wood / 500 stone / 250 iron /
100 gold, plots `plot_01`..`plot_18` with `plot_06` onward empty.

Level-2 costs, for budgeting a multi-order test: farm `wood 120, stone 60`;
lumber_mill `food 80, stone 100`; quarry `food 100, wood 100`; warehouse
`wood 180, stone 160`; barracks `food 150, wood 200, stone 120`.
Level-2 durations: farm/lumber_mill/quarry 20s, warehouse 25s, barracks 35s.

`BuildingUpgradeService::start()` check order (09-04 fixed this): `CITY_NOT_OWNED`
→ `BUILDING_MAX_LEVEL` → `BUILDING_REQUIREMENTS_NOT_MET` → `BUILD_QUEUE_FULL` →
`CITY_BUSY` → `INSUFFICIENT_RESOURCES`.
</interfaces>

<trap>
**The read path completes orders.** `CityStateService::handle()` and
`BuildingUpgradeService::start()` both call `completeOverdueLocked()` before doing
anything else. A test that advances the clock and then calls `GET /game/city`
has already completed the order — the reconciler and the job will then correctly
find nothing, and the test proves nothing while passing. **Never touch an HTTP
endpoint between advancing the clock and the assertion under test.** Read state
with Eloquent (`ConstructionOrder::query()`, `CityBuilding::query()`) instead.
</trap>
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: The completion job is idempotent (criterion 3)</name>

  <read_first>
    - apps/api/modules/Construction/Interface/Jobs/CompleteConstruction.php (the `$orderExists` guard and the `completed_at IS NULL` filter)
    - apps/api/modules/Construction/Application/ConstructionCompletionService.php (where the level is applied and `CityStateChanged` is dispatched)
    - apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php (the guest → bootstrap → upgrade HTTP setup and the ledger-count assertions to imitate)
    - apps/api/tests/Pest.php (`freezeClock`)
    - apps/api/phpunit.xml (why `QUEUE_CONNECTION=sync` matters here)
  </read_first>

  <files>apps/api/tests/Feature/Construction/ConstructionCompletionTest.php</files>

  <behavior>
    - After a farm upgrade is started and the clock advanced past `finishes_at`, running `CompleteConstruction::handle()` once sets `city_buildings.level` to 2 and stamps `completed_at`
    - Running the identical job a second time leaves `level` at 2 and `completed_at` byte-identical to the first run
    - Exactly one `CityStateChanged` is dispatched across both runs
    - Exactly one set of `building.upgrade` ledger debits exists across both runs — completion grants the level, it must never re-run the debit
    - A job whose order id no longer exists returns without throwing
  </behavior>

  <action>
Create the directory `apps/api/tests/Feature/Construction/`.

Shared setup for every test in this file, written as a small local helper at the
top of the file (not in `tests/Pest.php` — it is construction-specific):

```php
/**
 * @return array{tokens: array<string,string>, worldId: string, cityId: string}
 */
function startFarmUpgrade(string $keyPrefix): array
```

which: posts `/api/v1/auth/guest` with `Idempotency-Key: {$keyPrefix}-guest`, posts
`/api/v1/game/bootstrap` with `{$keyPrefix}-bootstrap`, then posts
`/api/v1/game/city/buildings/farm/upgrade` with `{$keyPrefix}-upgrade`, asserts 201,
and returns the tokens plus the world and city ids from the bootstrap payload.

**Test 1 — "completes the upgrade once when the job runs twice".**

```php
$clock = freezeClock('2026-09-08T10:00:00+00:00');
config(['queue.default' => 'redis']);   // the sync guard in start() would skip dispatch
Queue::fake();
Event::fake([CityStateChanged::class]);

$ctx = startFarmUpgrade('completion-idem');
Queue::assertPushed(CompleteConstruction::class);

$order = ConstructionOrder::query()->where('city_id', $ctx['cityId'])->firstOrFail();
$clock->advanceSeconds(30);              // farm level 2 is 20s

$job = new CompleteConstruction($ctx['worldId'], $ctx['cityId'], (string) $order->getKey());
app()->call([$job, 'handle']);
$firstCompletedAt = ConstructionOrder::query()->whereKey($order->getKey())->value('completed_at');

app()->call([$job, 'handle']);
```

Then assert: `CityBuilding` `farm` level is `2`; `completed_at` still equals
`$firstCompletedAt`; `Event::assertDispatchedTimes(CityStateChanged::class, 1)`;
and the number of `economy_ledger` rows with `reason = 'building.upgrade'` is
exactly `2` (farm level 2 costs wood and stone — one row per non-zero resource,
written once at `start()` and never by completion).

`app()->call([$job, 'handle'])` resolves the `Clock` and
`ConstructionCompletionService` from the container, which is how the queue worker
would invoke it.

**Fallback, only if needed:** `->afterCommit()` under `RefreshDatabase` can make
`Queue::assertPushed` awkward on some Laravel patch versions. If the assertion is
not observable, drop *that one line* — the idempotency proof reads the persisted
order and constructs the job directly, so it does not depend on the dispatch — and
record the substitution in the SUMMARY. Do not weaken any other assertion, and do
not change production code to make an assertion convenient.

**Test 2 — "a job for an order that no longer exists is a no-op".** Same setup;
delete the order row with `ConstructionOrder::query()->whereKey(...)->delete()`,
run the job, assert no exception and that the farm is still level 1.

**Test 3 — "a job for a city in another world does nothing".** Same setup, but
construct the job with a random ULID as `worldId`; assert the farm stays level 1
even after the clock is advanced. This is the cross-world filter
(`.planning/codebase/CONCERNS.md` § Standing risks) proven on the job path.
  </action>

  <acceptance_criteria>
    - `test -d apps/api/tests/Feature/Construction` succeeds
    - `grep -c "assertDispatchedTimes(CityStateChanged::class, 1)" apps/api/tests/Feature/Construction/ConstructionCompletionTest.php` is ≥ 1
    - `grep -c "app()->call(\[\$job, 'handle'\])" apps/api/tests/Feature/Construction/ConstructionCompletionTest.php` is ≥ 2 (the job runs twice)
    - `grep -c "getJson\|postJson" apps/api/tests/Feature/Construction/ConstructionCompletionTest.php` shows no HTTP call after `advanceSeconds` — verify by reading the file; the trap in `<context>` explains why
    - `cd apps/api && ./vendor/bin/pest --filter=ConstructionCompletion` reports 3 passing tests
    - The guard is proven to bite: the SUMMARY records that commenting out `->whereNull('completed_at')` in `ConstructionCompletionService::completeOverdueLocked` makes Test 1 fail, and that the line was restored (`git status --porcelain apps/api/modules` prints nothing)
  </acceptance_criteria>

  <verify>
    <automated>cd apps/api &amp;&amp; ./vendor/bin/pest --filter=ConstructionCompletion</automated>
  </verify>

  <done>
    Criterion 3 is proven: the completion job run twice applies the level once,
    emits one `CityStateChanged`, and never re-debits; and the guard that makes
    that true has been observed failing when removed.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Kill the worker, run the reconciler (criterion 4)</name>

  <read_first>
    - apps/api/modules/Construction/Application/ConstructionReconciler.php (the open-world loop, the per-city transaction, and what `run()` returns)
    - apps/api/routes/console.php (the every-minute `construction-reconcile` schedule entry and the comment explaining why it races the job)
    - docs/backend/schedulers.md
    - apps/api/tests/Feature/Construction/ConstructionCompletionTest.php (Task 1 — reuse the same setup helper shape)
    - .planning/codebase/CONCERNS.md (§ Standing risks — "Reconciler silence")
  </read_first>

  <files>apps/api/tests/Feature/Construction/ConstructionReconcilerTest.php</files>

  <behavior>
    - The worker never runs: the job is dispatched to a faked queue and deliberately left unprocessed
    - `ConstructionReconciler::run()` returns `1` and completes the overdue order — level applied, `completed_at` stamped
    - A second `run()` immediately after returns `0` and changes nothing
    - The abandoned job, finally executed after the reconciler already finished the order, changes nothing: the level stays put, `completed_at` is unchanged, and only one `CityStateChanged` exists across the whole scenario
    - Three overdue orders in one city are all completed by a single `run()`, which returns `3`
    - An order that is not yet overdue is untouched by `run()`
  </behavior>

  <action>
This is the phase's headline claim. Structure each test as: start order(s) →
`Queue::fake()` so the job is captured and never executed (**that is the worker
dying**) → `advanceSeconds` past `finishes_at` → assert with Eloquent that nothing
completed on its own → run the reconciler → assert.

**Test 1 — "a dead worker costs nothing: the reconciler finishes the order exactly once".**

```php
$clock = freezeClock('2026-09-08T11:00:00+00:00');
config(['queue.default' => 'redis']);
Queue::fake();
Event::fake([CityStateChanged::class]);

$ctx = startFarmUpgrade('reconcile-single');   // same helper shape as Task 1
$order = ConstructionOrder::query()->where('city_id', $ctx['cityId'])->firstOrFail();

$clock->advanceSeconds(30);

// The worker is dead. Nothing has completed and nothing will, unaided.
expect(ConstructionOrder::query()->whereKey($order->getKey())->value('completed_at'))->toBeNull();

expect(app(ConstructionReconciler::class)->run())->toBe(1);
```

Assert: `farm` level is `2`; `completed_at` is set; then
`expect(app(ConstructionReconciler::class)->run())->toBe(0)` and re-assert the
level and `completed_at` are unchanged. Finally run the abandoned job —
`app()->call([new CompleteConstruction($ctx['worldId'], $ctx['cityId'], (string) $order->getKey()), 'handle'])`
— and assert one last time that nothing moved, with
`Event::assertDispatchedTimes(CityStateChanged::class, 1)` closing the scenario:
**exactly one completion across a reconciler run, a duplicate reconciler run and a
late job.**

**Test 2 — "one run finishes every overdue order in the city".** Start upgrades on
`farm` (20s), `lumber_mill` (20s) and `warehouse` (25s) — combined cost food 80,
wood 300, stone 320 against a 500/500/500 start, so all three succeed. Advance 40s.
Assert `run()` returns `3` and all three `CityBuilding` levels are `2`, and that
`Event::assertDispatchedTimes(CityStateChanged::class, 3)` — one per completed
order, not one per run.

**Test 3 — "leaves an order that is not due yet alone".** Start one upgrade,
advance only 5 seconds, assert `run()` returns `0`, `completed_at` is null and the
level is still 1.

**Test 4 — "ignores orders in a closed world".** After starting an order and
advancing the clock, set the world's `is_open` to `false`
(`World::query()->whereKey($ctx['worldId'])->update(['is_open' => false])`), run the
reconciler, and assert it returns `0` and the order is still open. `run()` only
scans open worlds — this test pins that as a deliberate boundary rather than an
accident. If the executor judges this behaviour wrong (an order in a world that was
closed for maintenance would never complete), **do not change it here**: record it
in the SUMMARY as a concern for the phase verification pass with a proposed owner
phase, because widening the reconciler's scope is a behaviour change no Phase 09
success criterion asks for.
  </action>

  <acceptance_criteria>
    - `grep -c "ConstructionReconciler::class)->run()" apps/api/tests/Feature/Construction/ConstructionReconcilerTest.php` is ≥ 5
    - `grep -c "Queue::fake()" apps/api/tests/Feature/Construction/ConstructionReconcilerTest.php` is ≥ 4 (the worker is dead in every scenario)
    - Test 1 contains, in this order: a `run()` asserted `toBe(1)`, a second `run()` asserted `toBe(0)`, a late `CompleteConstruction` handle, and `assertDispatchedTimes(CityStateChanged::class, 1)`
    - No `getJson('/api/v1/game/city')` appears after any `advanceSeconds` call in this file
    - `cd apps/api && ./vendor/bin/pest --filter=ConstructionReconciler` reports 4 passing tests
    - `grep -c "construction-reconcile" apps/api/routes/console.php` is `1` — the schedule entry the tests stand in for still exists
  </acceptance_criteria>

  <verify>
    <automated>cd apps/api &amp;&amp; ./vendor/bin/pest --filter=ConstructionReconciler</automated>
  </verify>

  <done>
    Criterion 4 is proven: with the worker dead, a single reconciler run finishes
    every overdue order exactly once; a second run and the resurrected job both
    change nothing; and one `CityStateChanged` per order is emitted across the
    whole sequence.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: Queue ceiling, max level and server-owned timers (criteria 5 and 2)</name>

  <read_first>
    - apps/api/modules/Construction/Application/BuildingUpgradeService.php (the check order after 09-04, and the `BuildDuration::scaled` block from 09-02)
    - apps/api/config/game.php (`limits.max_build_queue_slots` = 4, `time_scale`)
    - packages/game-data/data/buildings.json (level-2 costs and durations for the five starter buildings and `barracks`)
    - apps/api/tests/Pest.php (`toBeApiError` — it asserts the status implied by the code, which is 400 for all three of these)
    - apps/api/modules/City/Infrastructure/CityBuilding.php (`$fillable` — how the test seeds a sixth building)
  </read_first>

  <files>
    apps/api/tests/Feature/Construction/ConstructionQueueLimitTest.php,
    apps/api/tests/Feature/Construction/ConstructionTimersTest.php
  </files>

  <behavior>
    - Four concurrent orders are accepted; a fifth returns `BUILD_QUEUE_FULL` with HTTP 400 and creates no order
    - The refused fifth request writes no ledger row — the queue check runs before the debit
    - Lowering `game.limits.max_build_queue_slots` to 2 makes the third order fail, proving the ceiling is read from config and not hardcoded
    - A second order on a building already under construction returns `CITY_BUSY`, not `BUILD_QUEUE_FULL`, while slots remain free
    - Upgrading the starter Palace, already at its `max_level` of 3, returns `BUILDING_MAX_LEVEL` with HTTP 400
    - `started_at` equals the frozen clock exactly, `finishes_at` equals it plus the scaled catalogue duration, and both serialise with a `+00:00` offset
    - A request body carrying `started_at`, `finishes_at`, `build_time_seconds` and `cost` changes nothing about the persisted order or the debited amount
  </behavior>

  <action>
**3a. `ConstructionQueueLimitTest.php`.**

Test 1 — *"refuses a fifth concurrent order with BUILD_QUEUE_FULL"*. Freeze the
clock so nothing completes mid-test. Guest → bootstrap. Seed a sixth building so a
fifth *distinct* upgradeable target exists (the four starter non-Palace buildings
fill the queue, and the Palace is already maxed):

```php
CityBuilding::create([
    'world_id' => $worldId,
    'city_id' => $cityId,
    'slot' => 'plot_06',
    'building_code' => 'barracks',
    'level' => 1,
]);
```

This doubles as end-to-end proof that a building added to the catalogue by 09-01,
and never present in a starter city, is fully functional.

Start upgrades on `farm`, `lumber_mill`, `quarry`, `warehouse` — each 201.
Combined cost is food 180, wood 400, stone 320 against 500/500/500, so all four are
affordable; do not add a resource grant. Capture
`EconomyLedger::query()->count()`. Then `POST .../barracks/upgrade` and:

```php
expect($fifth)->toBeApiError(ErrorCode::BuildQueueFull);
$fifth->assertStatus(400);
```

plus: `ConstructionOrder::query()->count()` is still `4`, the ledger count is
unchanged, and the city's five balances are unchanged. Note in a comment that
`BUILD_QUEUE_FULL` is reached even though the barracks is *also* unaffordable at
this point (wood 100 left against a 200 cost) — the queue check deliberately runs
before the affordability check, so the player is told the real reason.

Test 2 — *"reads the ceiling from config"* —
`config(['game.limits.max_build_queue_slots' => 2])`, start two orders, assert the
third is `BUILD_QUEUE_FULL`.

Test 3 — *"a second order on the same building is CITY_BUSY, not a full queue"* —
start `farm`, then start `farm` again with a different `Idempotency-Key`; assert
`ErrorCode::CityBusy` and that only one order exists. (A repeat with the *same*
key is a replay, and Phase 08's `EconomyConcurrencyTest` already covers it.)

Test 4 — *"a maxed building returns BUILDING_MAX_LEVEL"* — the starter Palace is at
level 3, its `max_level`. `POST /game/city/buildings/palace/upgrade`; assert
`ErrorCode::BuildingMaxLevel`, HTTP 400, no order, no ledger row.

Test 5 — *"a building that is not in the city cannot be upgraded"* —
`POST /game/city/buildings/tavern/upgrade` (present in the catalogue after 09-01,
absent from the city) returns `BUILDING_REQUIREMENTS_NOT_MET`, not a 500 and not a
404 that leaks catalogue membership.

**3b. `ConstructionTimersTest.php`** — criterion 2, *"Starting an upgrade debits
resources atomically and writes started_at and finishes_at in UTC; the device clock
is never read."*

Test 1 — *"stamps the injected clock, in UTC"*:

```php
$clock = freezeClock('2026-09-08T12:34:56+00:00');
config(['game.time_scale' => 1]);
// ... guest, bootstrap, upgrade farm (catalogue level 2 = 20s)
$order = ConstructionOrder::query()->where('city_id', $cityId)->firstOrFail();

expect($order->started_at?->toDateTimeImmutable()->format(DATE_ATOM))->toBe('2026-09-08T12:34:56+00:00')
    ->and($order->finishes_at?->toDateTimeImmutable()->format(DATE_ATOM))->toBe('2026-09-08T12:35:16+00:00');
```

and assert the HTTP payload's `data.construction.started_at` / `finishes_at` carry
the same values with the `+00:00` offset — the client is never handed a
zone-ambiguous timestamp.

Test 2 — *"ignores everything the client tries to dictate"*: post the same upgrade
with a body of

```php
[
    'started_at' => '1999-01-01T00:00:00+00:00',
    'finishes_at' => '1999-01-01T00:00:01+00:00',
    'build_time_seconds' => 0,
    'cost' => ['wood' => 0, 'stone' => 0],
    'target_level' => 99,
]
```

and assert the persisted order's `started_at`, `finishes_at` and `target_level` are
exactly what the server would have computed anyway, and that the wood and stone
debits are the catalogue's 120 and 60. This is the "server owns the truth" rule
(ADR-006) proven on the one endpoint that spends resources and starts a timer.

Test 3 — *"the debit and the order are one transaction"*: assert that the
`economy_ledger` rows for `reason = 'building.upgrade'` carry the upgrade's
`Idempotency-Key` as their `reference`, and that their count matches the number of
non-zero resources in the level-2 cost (2 for farm). Then assert there is exactly
one `ConstructionOrder` with the same `idempotency_key` — one command, one debit
set, one order.
  </action>

  <acceptance_criteria>
    - `cd apps/api && ./vendor/bin/pest --filter=ConstructionQueueLimit` reports 5 passing tests
    - `cd apps/api && ./vendor/bin/pest --filter=ConstructionTimers` reports 3 passing tests
    - `grep -c "ErrorCode::BuildQueueFull" apps/api/tests/Feature/Construction/ConstructionQueueLimitTest.php` is ≥ 2
    - `grep -c "ErrorCode::BuildingMaxLevel" apps/api/tests/Feature/Construction/ConstructionQueueLimitTest.php` is ≥ 1
    - `grep -c "assertStatus(400)" apps/api/tests/Feature/Construction/ConstructionQueueLimitTest.php` is ≥ 2 — the HTTP status the 09-02 OpenAPI change documents
    - `grep -c "'barracks'" apps/api/tests/Feature/Construction/ConstructionQueueLimitTest.php` is ≥ 1 — a 09-01 catalogue building proven functional end to end
    - `grep -c "+00:00" apps/api/tests/Feature/Construction/ConstructionTimersTest.php` is ≥ 2
    - `grep -cE "\bnow\(\)|Carbon::now" apps/api/tests/Feature/Construction/*.php` is `0` — the tests read the frozen clock, never the wall clock
  </acceptance_criteria>

  <verify>
    <automated>cd apps/api &amp;&amp; ./vendor/bin/pest --filter=Construction &amp;&amp; ./vendor/bin/pest</automated>
  </verify>

  <done>
    Criteria 5 and 2 are proven: the fifth concurrent order and the maxed Palace
    return `BUILD_QUEUE_FULL` and `BUILDING_MAX_LEVEL` at HTTP 400 with no side
    effects, the ceiling is read from config, and the order's timestamps come from
    the injected Clock in UTC and cannot be influenced by the request body.
  </done>
</task>

</tasks>

<verification>
- `cd apps/api && ./vendor/bin/pest` — green; the four new files add ≥ 15 tests
- `cd apps/api && ./vendor/bin/pest --filter=Construction` — all green
- `cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G` — 0 errors
- `cd apps/api && ./vendor/bin/pint --test` — clean
- `npm run typecheck && npm run lint && npm test` — green (no client change here)
- No production file under `apps/api/modules/` is modified by this plan unless a
  test exposed a real defect; if one did, the SUMMARY names the defect, the fix and
  the test that now covers it
</verification>

<success_criteria>
- Criterion 2: `started_at` / `finishes_at` are the injected Clock's, in UTC, and a
  client-supplied timestamp, duration, cost or target level is ignored
- Criterion 3: the completion job run twice completes the upgrade once, emits one
  `CityStateChanged` and re-debits nothing
- Criterion 4: with the worker dead, one reconciler run finishes every overdue
  order exactly once; a duplicate run and the late job change nothing
- Criterion 5: `BUILD_QUEUE_FULL` and `BUILDING_MAX_LEVEL` are returned at HTTP 400
  with no order, no debit and no ledger row
- Every guard proven by removal at least once, with the observation recorded
</success_criteria>

<output>
After completion, create `.planning/phases/09-buildings-construction/09-03-SUMMARY.md`
</output>
</content>
