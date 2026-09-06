---
phase: 09-buildings-construction
plan: 02
type: execute
wave: 1
depends_on: []
files_modified:
  - packages/contracts/openapi.yaml
  - packages/contracts/src/generated/api.ts
  - apps/api/modules/Construction/Domain/BuildDuration.php
  - apps/api/modules/Construction/Application/BuildingUpgradeService.php
  - apps/api/modules/City/Application/CityStateService.php
  - apps/api/tests/Feature/City/CityConstructionQueueTest.php
  - apps/api/tests/Feature/Mvp/MvpGameplayTest.php
  - apps/mobile/src/features/city/components/CityScene.tsx
  - apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx
  - apps/mobile/__tests__/city-scene.test.tsx
autonomous: true
requirements: [REQ-05, REQ-09]

must_haves:
  truths:
    - "A city with four concurrent upgrades returns all four to the client, ordered the way the server will complete them"
    - "The client learns the build-queue ceiling from the server instead of hardcoding it"
    - "The build duration previewed before an upgrade equals the duration the server actually schedules, in every environment including a time-scaled local one"
    - "The upgrade endpoint's documented responses include the 400 its four gameplay error codes actually return"
    - "Every city tile with an active order shows its own timer, not just the soonest one"
  artifacts:
    - path: "apps/api/modules/Construction/Domain/BuildDuration.php"
      provides: "The single time_scale application, shared by the preview and the scheduler"
      contains: "public static function scaled"
    - path: "apps/api/modules/City/Application/CityStateService.php"
      provides: "constructions[] and queue_limit on the city read"
      contains: "'queue_limit'"
    - path: "packages/contracts/openapi.yaml"
      provides: "CityData.constructions, CityData.queue_limit, and a documented 400 on the upgrade endpoint"
      contains: "constructions"
    - path: "apps/api/tests/Feature/City/CityConstructionQueueTest.php"
      provides: "Proof that concurrent orders are all visible and the preview matches reality"
  key_links:
    - from: "apps/api/modules/City/Application/CityStateService.php"
      to: "Game\\Construction\\Domain\\BuildDuration"
      via: "preview duration scaling"
      pattern: "BuildDuration::scaled"
    - from: "apps/api/modules/Construction/Application/BuildingUpgradeService.php"
      to: "Game\\Construction\\Domain\\BuildDuration"
      via: "finishes_at computation"
      pattern: "BuildDuration::scaled"
    - from: "apps/mobile/src/features/city/components/CityScene.tsx"
      to: "city.constructions"
      via: "per-slot isBuilding lookup"
      pattern: "constructions\\.some"
---

<objective>
Turn the city read's singular `construction` into the queue the backend has always
supported, echo the structural queue ceiling to the client, and make the previewed
build duration equal the scheduled one.

Purpose: the backend permits up to `game.limits.max_build_queue_slots` (4)
concurrent orders per city, but `CityStateService` returns only the soonest, so a
player with three upgrades in flight can see one. 09-UI-SPEC.md § Data Contract
Dependency names this and the `time_scale` preview drift as the two gaps that must
close before 09-05 is buildable.
Output: `CityData.constructions[]` + `CityData.queue_limit` on the contract and in
the generated types, one shared `BuildDuration::scaled()`, a documented `400` on
the upgrade endpoint, and the existing mobile call sites moved onto the array.
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
@.planning/phases/09-buildings-construction/09-UI-SPEC.md
@.planning/codebase/ARCHITECTURE.md
@.planning/codebase/CONVENTIONS.md
@docs/adr/017-openapi-contract.md

<interfaces>
<!-- Current shapes. The executor changes these; it should not have to discover them. -->

`packages/contracts/openapi.yaml` today (lines ~828 and ~867):

```yaml
    Construction:
      type: object
      required: [id, building_code, from_level, target_level, started_at, finishes_at]
      properties:
        id: { $ref: '#/components/schemas/Ulid' }
        building_code: { type: string }
        from_level: { type: integer }
        target_level: { type: integer }
        started_at: { type: string, format: date-time }
        finishes_at: { type: string, format: date-time }

    CityData:
      required: [player, world, city, resources, slots, construction, realtime, server_time]
      properties:
        ...
        construction:
          oneOf:
            - $ref: '#/components/schemas/Construction'
            - type: 'null'
```

`apps/api/modules/City/Application/CityStateService.php` today:

```php
$constructionQuery->getQuery()->whereNull('completed_at')->orderBy('finishes_at');
$construction = $constructionQuery->first();          // only the soonest
...
'build_time_seconds' => $next === null ? 0 : (int) ($next['build_time_seconds'] ?? 0),   // raw, unscaled
```

`apps/api/modules/Construction/Application/BuildingUpgradeService.php` today:

```php
$rawDuration = (int) ($target['build_time_seconds'] ?? 0);
$timeScale = max(1, (int) config('game.time_scale'));
$duration = intdiv($rawDuration, $timeScale);
$finishesAt = $now->add(new DateInterval('PT'.$duration.'S'));
```

`apps/mobile/src/features/city/components/CityScene.tsx` today:

```ts
const constructionFinishTimestamp = city.construction
  ? Date.parse(city.construction.finishes_at) + (Date.now() - Date.parse(city.server_time))
  : null;
const isBuilding = slot.building !== null && city.construction?.building_code === slot.building.code;
<CitySlotDetailSheet slot={selected} construction={city.construction} ... />
```

`apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx` today accepts
`{ slot, construction, serverTime, open, onClose, onConstructionFinish }` and does
`showConstruction = building !== null && construction !== null && construction.building_code === building.code`.

`apps/api/config/game.php`: `limits.max_build_queue_slots` is `4`; `time_scale` is
`max(1, (int) env('DEBUG_TIME_SCALE', 1))` in local and forced to `1` elsewhere.
</interfaces>
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: One BuildDuration, used by both the preview and the scheduler</name>

  <read_first>
    - apps/api/modules/Construction/Application/BuildingUpgradeService.php (the `intdiv($rawDuration, $timeScale)` block near the end of `start()`)
    - apps/api/modules/City/Application/CityStateService.php (the `build_time_seconds` line inside the per-building loop)
    - apps/api/tests/Architecture/ArchitectureTest.php (the `domain layer stays free of the framework` rule and its `ignoring` list — `Game\Construction\Domain` is deliberately NOT ignored, so the new class must import nothing)
    - .planning/phases/09-buildings-construction/09-UI-SPEC.md (§ Data Contract Dependency #2 — why this matters)
  </read_first>

  <files>
    apps/api/modules/Construction/Domain/BuildDuration.php,
    apps/api/modules/Construction/Application/BuildingUpgradeService.php,
    apps/api/modules/City/Application/CityStateService.php,
    apps/api/tests/Feature/City/CityConstructionQueueTest.php
  </files>

  <behavior>
    - `BuildDuration::scaled(60, 1)` returns `60`
    - `BuildDuration::scaled(60, 3)` returns `20`
    - `BuildDuration::scaled(45, 60)` returns `0` — integer division floors; a sub-second build is instant, never negative
    - `BuildDuration::scaled(60, 0)` returns `60` — a misconfigured scale of 0 or below is clamped to 1, never a division by zero
    - `BuildDuration::scaled(-5, 2)` returns `0` — a negative raw duration is clamped to 0
    - With `config(['game.time_scale' => 3])`, `GET /game/city` reports
      `slots[n].building.build_time_seconds === 20` for a building whose catalogue
      duration is 60, and a subsequent upgrade of that building produces
      `finishes_at - started_at === 20` seconds
    - `game.time_scale` resolves to `1` whenever `APP_ENV` is not `local`, even with
      `DEBUG_TIME_SCALE=60` set — the accelerator cannot escape a developer machine
  </behavior>

  <action>
Create `apps/api/modules/Construction/Domain/BuildDuration.php`, namespace
`Game\Construction\Domain`. It is a domain class: it imports nothing, calls no
helper, and receives the scale as an argument — the architecture rule `domain layer
stays free of the framework` does not ignore `Game\Construction\Domain`, and
`domain never reaches for global helpers` is the house rule regardless.

```php
<?php

declare(strict_types=1);

namespace Game\Construction\Domain;

/**
 * The one place `game.time_scale` is applied.
 *
 * The preview the player reads before tapping Upgrade and the deadline the server
 * schedules must be the same number. They were computed in two places and drifted
 * apart in local development, where the scale is not 1 (09-UI-SPEC.md § Data
 * Contract Dependency #2). The scale is never sent to the client — the server
 * returns an already-scaled duration instead.
 */
final readonly class BuildDuration
{
    public static function scaled(int $rawSeconds, int $timeScale): int
    {
        return intdiv(max(0, $rawSeconds), max(1, $timeScale));
    }
}
```

In `BuildingUpgradeService::start()` replace the three-line block with:

```php
$duration = BuildDuration::scaled(
    (int) ($target['build_time_seconds'] ?? 0),
    (int) config('game.time_scale'),
);
```

Keep `$finishesAt = $now->add(new DateInterval('PT'.$duration.'S'));` unchanged and
drop the now-unused `$rawDuration` / `$timeScale` locals.

In `CityStateService::handle()`, inside the per-building loop, replace the raw
preview with the scaled one:

```php
'build_time_seconds' => $next === null ? 0 : BuildDuration::scaled(
    (int) ($next['build_time_seconds'] ?? 0),
    (int) config('game.time_scale'),
),
```

Create `apps/api/tests/Feature/City/CityConstructionQueueTest.php` and add the
first test now (Task 2 adds the rest to the same file):

*"previews the same build duration the server will actually schedule"* —
`freezeClock('2026-09-06T12:00:00+00:00')`, `config(['game.time_scale' => 3])`,
guest → bootstrap → `GET /game/city`; capture `build_time_seconds` for the `farm`
slot (catalogue level 2 is 20s, so 20/3 = 6); `POST
/game/city/buildings/farm/upgrade`; assert
`Carbon::parse(finishes_at)->diffInSeconds(Carbon::parse(started_at))` equals the
previewed number exactly. Then repeat the whole flow with
`config(['game.time_scale' => 1])` and assert it equals 20 — proving the scale is
applied, not merely that two numbers agree at 1.

**Test 3 — "the time accelerator cannot escape local".** `config/game.php` has
forced `time_scale` to `1` outside `local` since the bootstrap commit, but no test
has ever exercised it (`grep -rn "time_scale" apps/api/tests` is empty today), and
Phase 09 is the first phase where that value actually drives gameplay timers.
Pin it now. Re-evaluate the config file directly rather than trusting the already
-booted container, because `APP_ENV` is `testing` for the whole suite:

```php
$evaluate = static function (string $appEnv, string $debugScale): int {
    $previousEnv = $_ENV['APP_ENV'] ?? null;
    $previousScale = $_ENV['DEBUG_TIME_SCALE'] ?? null;
    $_ENV['APP_ENV'] = $appEnv;
    $_ENV['DEBUG_TIME_SCALE'] = $debugScale;

    try {
        return (int) (require base_path('config/game.php'))['time_scale'];
    } finally {
        $previousEnv === null ? ($_ENV['APP_ENV'] = 'testing') : ($_ENV['APP_ENV'] = $previousEnv);
        $previousScale === null ? unset($_ENV['DEBUG_TIME_SCALE']) : ($_ENV['DEBUG_TIME_SCALE'] = $previousScale);
    }
};

expect($evaluate('local', '60'))->toBe(60);      // a developer may accelerate
expect($evaluate('production', '60'))->toBe(1);  // production may not
expect($evaluate('staging', '60'))->toBe(1);     // nor may anything else
```

If `env()` proves to read from `getenv()` rather than `$_ENV` under this Laravel
version, use `putenv()`/`getenv()` for the same three assertions — the assertions
are the point, not the mechanism.
  </action>

  <acceptance_criteria>
    - `grep -c "BuildDuration::scaled" apps/api/modules/Construction/Application/BuildingUpgradeService.php` is `1`
    - `grep -c "BuildDuration::scaled" apps/api/modules/City/Application/CityStateService.php` is `1`
    - `grep -c "intdiv" apps/api/modules/Construction/Application/BuildingUpgradeService.php` is `0`
    - `grep -cE "^use " apps/api/modules/Construction/Domain/BuildDuration.php` is `0` (the domain class imports nothing)
    - `grep -cE "\b(config|now|app)\(" apps/api/modules/Construction/Domain/BuildDuration.php` is `0`
    - `cd apps/api && ./vendor/bin/pest --group=arch` is green
    - `cd apps/api && ./vendor/bin/pest --filter=CityConstructionQueue` reports the duration test passing
    - `grep -rn "time_scale" apps/api/tests` is no longer empty — the outside-local forcing is pinned by a test
  </acceptance_criteria>

  <verify>
    <automated>cd apps/api &amp;&amp; ./vendor/bin/pest --filter=CityConstructionQueue &amp;&amp; ./vendor/bin/pest --group=arch</automated>
  </verify>

  <done>
    A single `BuildDuration::scaled()` produces both the previewed and the
    scheduled duration, proven equal under `time_scale = 3` and under
    `time_scale = 1`, and the domain class stays framework-free.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: `constructions[]` and `queue_limit` on the contract and the city read</name>

  <read_first>
    - apps/api/modules/City/Application/CityStateService.php (the `$constructionQuery ... ->first()` block and the returned array literal)
    - packages/contracts/openapi.yaml (the `Construction` schema at ~line 828, `CityData` at ~line 867, `/game/city/buildings/{code}/upgrade` at ~line 581, and the `BadRequest` response component at ~line 1264)
    - packages/contracts/src/index.ts (the `export type CityData = ...` block — `Construction` is already re-exported)
    - apps/api/tests/Feature/Mvp/MvpGameplayTest.php (the two `assertJsonPath('data.construction', null)` assertions)
    - .planning/phases/09-buildings-construction/09-UI-SPEC.md (§ Data Contract Dependency #1 and #3 — the required shape, verbatim)
    - apps/api/config/game.php (`limits.max_build_queue_slots`)
  </read_first>

  <files>
    packages/contracts/openapi.yaml,
    packages/contracts/src/generated/api.ts,
    apps/api/modules/City/Application/CityStateService.php,
    apps/api/tests/Feature/City/CityConstructionQueueTest.php,
    apps/api/tests/Feature/Mvp/MvpGameplayTest.php
  </files>

  <behavior>
    - A city with nothing building returns `constructions: []` — an empty array, never `null`
    - A city with three concurrent orders returns all three, ordered by `finishes_at` ascending
    - `queue_limit` equals `config('game.limits.max_build_queue_slots')` (4 by default) and changes with it
    - A completed order disappears from `constructions[]` on the next read (the existing `completeOverdueLocked` call already guarantees this — assert it, do not rebuild it)
  </behavior>

  <action>
**2a. `CityStateService::handle()`** — replace the single-order block with the full
open queue:

```php
$constructionsQuery = ConstructionOrder::query()
    ->where('world_id', $worldId)
    ->where('city_id', $city->getKey());
$constructionsQuery->getQuery()->whereNull('completed_at')->orderBy('finishes_at');
/** @var Collection<int, ConstructionOrder> $constructions */
$constructions = $constructionsQuery->get();
```

In the returned array, delete the `'construction' => $construction === null ? null : [...]`
entry entirely and put in its place:

```php
'constructions' => $constructions
    ->map(static fn (ConstructionOrder $order): array => [
        'id' => (string) $order->getKey(),
        'building_code' => (string) $order->building_code,
        'from_level' => (int) $order->from_level,
        'target_level' => (int) $order->target_level,
        'started_at' => $order->started_at?->toDateTimeImmutable()->format(DATE_ATOM),
        'finishes_at' => $order->finishes_at?->toDateTimeImmutable()->format(DATE_ATOM),
    ])
    ->values()
    ->all(),
'queue_limit' => max(1, (int) config('game.limits.max_build_queue_slots')),
```

Keep the key's position in the array (where `construction` was, between `slots` and
`realtime`) so the response shape stays readable next to the OpenAPI document.
`max(1, ...)` mirrors `BuildingUpgradeService::start()`'s own clamp so the client
can never be told a ceiling the server would not enforce.

**2b. `packages/contracts/openapi.yaml`** — three edits, copied from
09-UI-SPEC.md § Data Contract Dependency:

1. In `CityData.required`, replace `construction` with `constructions, queue_limit`:
   `required: [player, world, city, resources, slots, constructions, queue_limit, realtime, server_time]`
2. Replace the `construction:` property with:

```yaml
        constructions:
          type: array
          description: >-
            Every active order for this city (completed_at IS NULL), ordered the
            way the server will complete them. An empty array, never null, when
            nothing is building — the same "array always present, in full"
            convention CitySlot established in Phase 07.
          items:
            $ref: '#/components/schemas/Construction'
        queue_limit:
          type: integer
          description: >-
            game.limits.max_build_queue_slots, echoed back so the client never
            hardcodes a structural limit.
```

3. On `/game/city/buildings/{code}/upgrade`, add a documented `400` above the
   existing `401`. `ErrorCode::httpStatus()` routes `BUILDING_MAX_LEVEL`,
   `BUILDING_REQUIREMENTS_NOT_MET`, `BUILD_QUEUE_FULL`, `CITY_BUSY` and
   `INSUFFICIENT_RESOURCES` through its `default => 400` arm, but the document
   claims only 401/409/422 — ADR-017 requires one contract both sides verify
   against:

```yaml
        '400':
          description: >-
            The upgrade cannot be applied: BUILDING_MAX_LEVEL,
            BUILDING_REQUIREMENTS_NOT_MET, BUILD_QUEUE_FULL, CITY_BUSY or
            INSUFFICIENT_RESOURCES.
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
```

Do **not** change the `Construction` schema — `slot` is not added. 09-UI-SPEC.md
Flagged Assumption 2 settles this: a building code occupies at most one slot today,
so the client resolves the slot by matching `building_code` against
`slots[].building.code`. Record that choice in the SUMMARY so the day it stops
being true is traceable.

Regenerate the types with `npm run contracts:generate` from the repository root and
commit `packages/contracts/src/generated/api.ts` — `npm run contracts:check`
regenerates and fails on any diff.

**2c. Tests.** Add to `apps/api/tests/Feature/City/CityConstructionQueueTest.php`:

- *"returns an empty array, not null, when nothing is building"* — bootstrap, read,
  `assertJsonPath('data.constructions', [])` and
  `assertJsonPath('data.queue_limit', 4)`.
- *"returns every concurrent order in completion order"* — freeze the clock, start
  upgrades on `lumber_mill` (20s), `warehouse` (25s) and `farm` (20s) in that order,
  then read: assert `assertJsonCount(3, 'data.constructions')` and that the
  `finishes_at` values are non-decreasing. Starter resources (500/500/500/250/100)
  cover the three level-2 costs (food 80+0+0=80, wood 0+180+120=300, stone
  100+160+60=320) with room to spare — do not add a resource grant.
- *"echoes the configured ceiling"* — `config(['game.limits.max_build_queue_slots' => 2])`,
  read, assert `data.queue_limit` is `2`.
- *"drops a completed order from the queue"* — start one upgrade, advance the frozen
  clock past `finishes_at`, read, assert `data.constructions` is `[]` and the slot's
  building level advanced.

Update `apps/api/tests/Feature/Mvp/MvpGameplayTest.php`: both
`->assertJsonPath('data.construction', null)` assertions become
`->assertJsonCount(0, 'data.constructions')`. Change nothing else in that file —
its balance assertions are Phase 08's proof.
  </action>

  <acceptance_criteria>
    - `grep -c "'construction' =>" apps/api/modules/City/Application/CityStateService.php` is `0`
    - `grep -c "'constructions' =>" apps/api/modules/City/Application/CityStateService.php` is `1`
    - `grep -c "'queue_limit' =>" apps/api/modules/City/Application/CityStateService.php` is `1`
    - `grep -c "data.construction'" apps/api/tests/Feature/Mvp/MvpGameplayTest.php` is `0`
    - `grep -n "required: \[player, world, city, resources, slots, constructions, queue_limit, realtime, server_time\]" packages/contracts/openapi.yaml` matches one line
    - `grep -c "^        '400':" packages/contracts/openapi.yaml` is ≥ 1 and `grep -c "BUILD_QUEUE_FULL" packages/contracts/openapi.yaml` is ≥ 2 (the ErrorCode enum plus the new response description)
    - `npm run contracts:check` exits 0 (regenerated types are committed)
    - `grep -c "constructions" packages/contracts/src/generated/api.ts` is ≥ 1
    - `cd apps/api && ./vendor/bin/pest --filter=CityConstructionQueue` reports 5 passing tests
  </acceptance_criteria>

  <verify>
    <automated>npm run contracts:check &amp;&amp; cd apps/api &amp;&amp; ./vendor/bin/pest</automated>
  </verify>

  <done>
    `GET /game/city` returns every open order plus the configured queue ceiling,
    the OpenAPI document and the generated TypeScript agree with it, and the
    upgrade endpoint documents the 400 its gameplay errors actually return.
  </done>
</task>

<task type="auto">
  <name>Task 3: Move the mobile call sites onto the array — plumbing only, no visual change</name>

  <read_first>
    - apps/mobile/src/features/city/components/CityScene.tsx (the `constructionFinishTimestamp` computation, the `isBuilding` expression inside the slot map, and the `<CitySlotDetailSheet ... />` props)
    - apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx (the `construction` prop and `showConstruction`)
    - apps/mobile/__tests__/city-scene.test.tsx (the `buildCity()` fixture's `construction: null`)
    - apps/mobile/src/features/city/realtime/useCityRealtime.ts (confirm it only invalidates the query key and needs no change)
    - .planning/phases/09-UI-SPEC.md § D "Per-tile Timer — plumbing change only, no visual change" and § Data Contract Dependency's call-site checklist
  </read_first>

  <files>
    apps/mobile/src/features/city/components/CityScene.tsx,
    apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx,
    apps/mobile/__tests__/city-scene.test.tsx
  </files>

  <action>
This task changes lookups, not layout. Do not add a CTA, a queue strip, an icon map
or a new localization key — those are 09-05's, and adding them here would land
unreviewed against the approved UI contract.

**3a. `CityScene.tsx`.** Replace the single `constructionFinishTimestamp` with a
per-code lookup, keeping the identical device-clock skew correction the file
already applies (`+ (Date.now() - Date.parse(city.server_time))`):

```ts
const clockSkewMs = Date.now() - Date.parse(city.server_time);

const constructionByCode = new Map(
  city.constructions.map((construction) => [construction.building_code, construction]),
);
```

Inside the slot map:

```ts
const construction = slot.building ? (constructionByCode.get(slot.building.code) ?? null) : null;
const isBuilding = construction !== null;

<CitySlot
  key={slot.slot}
  slot={slot}
  size={layout.tileSize}
  isBuilding={isBuilding}
  constructionFinishTimestamp={
    construction ? Date.parse(construction.finishes_at) + clockSkewMs : null
  }
  onPress={selectSlot}
/>
```

Every plot with an open order now ticks its own timer simultaneously — up to
`queue_limit` at once — with no change to `CitySlot.tsx` itself.

For the sheet, resolve the one matching entry in the caller (09-UI-SPEC.md is
explicit that this resolution belongs to `CityScene`, not the sheet):

```ts
const selectedConstruction =
  selected?.building ? (constructionByCode.get(selected.building.code) ?? null) : null;

<CitySlotDetailSheet
  slot={selected}
  construction={selectedConstruction}
  serverTime={city.server_time}
  ...
/>
```

**3b. `CitySlotDetailSheet.tsx`.** The `construction` prop keeps its type and name;
because the caller now guarantees the match, simplify `showConstruction` to:

```ts
const showConstruction = building !== null && construction !== null;
```

Nothing else in the sheet changes in this plan.

**3c. `city-scene.test.tsx`.** In `buildCity()`, replace `construction: null` with
`constructions: [], queue_limit: 4`. Add one test to the `CityScene` describe
block:

*"ticks a timer on every plot with an open order, not just the soonest"* — build a
city whose `constructions` holds two entries (`farm` finishing in 30s, `quarry`
finishing in 90s, both with `started_at` in the past and `server_time` = now),
render, fire the `layout` event on the scene frame as the existing tests do, and
assert two `00:00:` timers are present via `getAllByText(/^\d{2}:\d{2}:\d{2}$/)`
having length 2. This test fails against the pre-change single-`construction`
lookup, which is the point.
  </action>

  <acceptance_criteria>
    - `grep -c "city.construction?" apps/mobile/src/features/city/components/CityScene.tsx` is `0`
    - `grep -c "city.constructions" apps/mobile/src/features/city/components/CityScene.tsx` is ≥ 1
    - `grep -c "construction: null" apps/mobile/__tests__/city-scene.test.tsx` is `0`
    - `grep -c "queue_limit" apps/mobile/__tests__/city-scene.test.tsx` is ≥ 1
    - No CTA leaked in: `grep -c "building.upgrade" apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx` is `0`
    - `git diff --stat apps/mobile/src/features/city/components/CitySlot.tsx` prints nothing (the tile is untouched)
    - `npm run typecheck` and `npm run lint` exit 0
    - `npm test` reports 13 suites green, with the new "ticks a timer on every plot" test included
  </acceptance_criteria>

  <verify>
    <automated>npm run typecheck &amp;&amp; npm run lint &amp;&amp; npm test</automated>
  </verify>

  <done>
    The scene and the sheet read `constructions[]`; every plot with an open order
    shows its own countdown; `CitySlot.tsx`, `useCityRealtime.ts` and every visual
    treatment are unchanged.
  </done>
</task>

</tasks>

<verification>
- `npm run contracts:check` — exits 0
- `cd apps/api && ./vendor/bin/pest` — green, ≥ 163 tests
- `cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G` — 0 errors
- `cd apps/api && ./vendor/bin/pint --test` — clean
- `npm run typecheck && npm run lint && npm test` — green
- Manual: `grep -rn "\.construction\b" apps/mobile/src apps/api/modules` returns no
  reference to the retired singular field
</verification>

<success_criteria>
- `GET /game/city` returns `constructions[]` (empty array when idle) and
  `queue_limit`, and the singular `construction` field is gone from the API, the
  OpenAPI document, the generated types and the mobile client
- The previewed `build_time_seconds` equals `finishes_at - started_at` under
  `time_scale = 3` and under `time_scale = 1`, proven by test
- `/game/city/buildings/{code}/upgrade` documents a `400` naming its five reachable
  error codes
- Up to four plots tick their own timers at once
- No visual or copy change ships in this plan
</success_criteria>

<output>
After completion, create `.planning/phases/09-buildings-construction/09-02-SUMMARY.md`
</output>
</content>
