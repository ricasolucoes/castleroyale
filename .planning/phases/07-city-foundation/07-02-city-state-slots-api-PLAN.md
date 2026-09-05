---
phase: 07-city-foundation
plan: 02
type: execute
wave: 2
depends_on: ["07-01"]
files_modified:
  - packages/contracts/openapi.yaml
  - packages/contracts/src/generated/api.ts
  - packages/contracts/src/index.ts
  - apps/api/modules/City/Application/CityStateService.php
  - apps/api/tests/Feature/City/CityFoundationTest.php
  - apps/api/tests/Feature/City/CityAuthorizationTest.php
  - apps/api/tests/Feature/Mvp/MvpGameplayTest.php
  - apps/mobile/app/(tabs)/city.tsx
autonomous: true
requirements: [REQ-01, REQ-08]

must_haves:
  truths:
    - "A city read returns the full roster of slots every time, each one either empty or holding exactly one building"
    - "The client can render a fixed scene without knowing how many buildings happen to exist"
    - "Requesting a city the player does not own returns CITY_NOT_OWNED, is not a 404, and carries no data key"
    - "The city read carries everything the city screen needs, including the realtime connection details"
  artifacts:
    - path: "packages/contracts/openapi.yaml"
      provides: "CitySlot schema and CityData.slots replacing CityData.buildings"
      contains: "CitySlot:"
    - path: "packages/contracts/src/index.ts"
      provides: "Generated CitySlot and RealtimeConfig type exports"
      contains: "CitySlot"
    - path: "apps/api/modules/City/Application/CityStateService.php"
      provides: "Roster-ordered slot projection with empty slots as first-class entries"
      contains: "citySlots()"
    - path: "apps/api/tests/Feature/City/CityAuthorizationTest.php"
      provides: "HTTP-level CITY_NOT_OWNED proof with no leakage"
      contains: "toBeApiError"
  key_links:
    - from: "apps/api/modules/City/Application/CityStateService.php"
      to: "packages/game-data/data/city-slots.json"
      via: "GameDataCatalog::citySlots() driving the response order and length"
      pattern: "citySlots"
    - from: "apps/mobile/app/(tabs)/city.tsx"
      to: "CityData.slots"
      via: "flatMap over slots to derive occupied buildings"
      pattern: "city\\.slots"
    - from: "packages/contracts/src/generated/api.ts"
      to: "packages/contracts/openapi.yaml"
      via: "openapi-typescript generation (ADR-017, spec first)"
      pattern: "CitySlot"
---

# Plan 07-02: City state exposes the full slot roster

<objective>
Make the city read return the fixed slot roster — every slot, every time, empty
or occupied — so the client can render a scene instead of a list, and prove at
the HTTP boundary that a foreign city leaks nothing.

Purpose: `CityData.buildings` is an array of occupied rows only. Success
criterion 2 ("a city exposes a fixed set of build slots, each either empty or
holding exactly one building") cannot be observed by any client against that
shape, and success criterion 5 has no HTTP-level test.

Output: a spec-first `CitySlot` schema, regenerated TypeScript types,
`CityStateService` projecting the roster, and feature tests updated to the new
contract without losing the MVP construction-loop coverage.
</objective>

## Context

@.planning/PROJECT.md
@.planning/phases/07-city-foundation/07-CONTEXT.md
@.planning/phases/07-city-foundation/07-UI-SPEC.md
@.planning/codebase/ARCHITECTURE.md
@.planning/codebase/TESTING.md

**ADR-017 is spec-first.** `packages/contracts/openapi.yaml` changes first;
`src/generated/api.ts` is produced by `npm run contracts:generate` and must never
be hand-edited. CI runs `npm run contracts:check`, which regenerates and fails on
any diff.

**Decisions locked in this plan (autonomous mode — recorded, not re-opened):**

1. **`CityData.buildings` is replaced, not supplemented.** Keeping both would give
   the screen two sources of truth for the same fact. The `/game/city/buildings/{code}/upgrade`
   endpoint and its `MvpGameplayTest` coverage stay exactly as they are — only the
   assertion *paths* in that test move from `data.buildings.0.*` to
   `data.slots.1.building.*`. Nothing about the upgrade behaviour changes.
2. **`CitySlot.building` is `required` and nullable**, where the UI-SPEC's sketch
   left it optional. With TypeScript `exactOptionalPropertyTypes` +
   `noUncheckedIndexedAccess`, a required-and-nullable field is unambiguous at the
   call site (`slot.building ? … : …`) while an optional one forces the consumer to
   distinguish "absent" from "null" for no gain. Any client written against the
   UI-SPEC's looser shape still validates.
3. **`realtime` moves onto `CityData`.** The connection details are already
   computed by `GameBootstrapService` and returned on `GameBootstrap`, but the
   mobile city screen only ever calls `GET /game/city`. Extracting the inline
   object into a named `RealtimeConfig` schema and referencing it from both places
   removes a duplicated inline definition and lets 07-04 subscribe from the same
   query the scene already runs. No new server computation is added.

<interfaces>
Current shapes the executor edits — copied here so no codebase hunt is needed.

`packages/contracts/openapi.yaml` (~line 771 and ~line 838):

```yaml
    CityBuilding:
      type: object
      required: [slot, code, name_key, category, level, max_level, next_level_cost, build_time_seconds]
      properties:
        slot: { type: string }
        code: { type: string }
        name_key: { type: string }
        category: { type: string }
        level: { type: integer }
        max_level: { type: integer }
        next_level_cost: { $ref: '#/components/schemas/ResourceBundle' }
        build_time_seconds: { type: integer }

    CityData:
      type: object
      required: [player, world, city, resources, buildings, construction, server_time]
      properties:
        # ...
        buildings:
          type: array
          items:
            $ref: '#/components/schemas/CityBuilding'
```

`GameBootstrap.realtime` is currently an **inline** object with
`required: [key, host, port, scheme, auth_endpoint]` and
`scheme: { type: string, enum: [http, https] }`.

`apps/api/modules/City/Application/CityStateService.php::handle()` currently
builds `$buildings` from a `CityBuilding` query ordered by `building_code` and
returns it under the `'buildings'` key. `$bootstrap['realtime']` is already in
scope inside `handle()` (it is part of the `GameBootstrapService::handle()`
return value assigned to `$bootstrap` on the first line).

New in 07-01: `GameDataCatalog::citySlots(): list<string>` returns the ordered
18-entry plot roster.
</interfaces>

## Tasks

<task type="auto">
<name>Task 1: Add CitySlot and RealtimeConfig to the OpenAPI contract and regenerate types</name>
<files>packages/contracts/openapi.yaml, packages/contracts/src/generated/api.ts, packages/contracts/src/index.ts</files>
<read_first>
- packages/contracts/openapi.yaml
- packages/contracts/src/index.ts
- packages/contracts/package.json
- docs/adr/017-openapi-contract.md
- .planning/phases/07-city-foundation/07-UI-SPEC.md
</read_first>
<action>
Edit `packages/contracts/openapi.yaml` under `components.schemas`.

1. Add `CitySlot` immediately after the existing `CityBuilding` schema:

```yaml
    CitySlot:
      type: object
      description: >-
        One plot of the city's fixed build roster. The array is always returned
        in full and in roster order; an empty plot is an entry, not an omission.
      required: [slot, status, building]
      properties:
        slot:
          type: string
        status:
          type: string
          enum: [empty, occupied]
        building:
          oneOf:
            - $ref: '#/components/schemas/CityBuilding'
            - type: 'null'
```

2. Add `RealtimeConfig` (extracted verbatim from the inline object currently
   nested under `GameBootstrap.realtime`):

```yaml
    RealtimeConfig:
      type: object
      required: [key, host, port, scheme, auth_endpoint]
      properties:
        key:
          type: string
        host:
          type: string
        port:
          type: integer
        scheme:
          type: string
          enum: [http, https]
        auth_endpoint:
          type: string
          format: uri
```

3. In `GameBootstrap`, replace the inline `realtime` object with
   `realtime: { $ref: '#/components/schemas/RealtimeConfig' }` — keep `realtime`
   in that schema's `required` list.

4. In `CityData`, change `required` to
   `[player, world, city, resources, slots, construction, realtime, server_time]`,
   delete the `buildings` property, and add:

```yaml
        slots:
          type: array
          items:
            $ref: '#/components/schemas/CitySlot'
        realtime:
          $ref: '#/components/schemas/RealtimeConfig'
```

5. Regenerate and export. Run `npm run contracts:generate` from the repo root,
   then add to `packages/contracts/src/index.ts`, next to the existing
   `CityData` export:

```ts
export type CitySlot = components['schemas']['CitySlot'];
export type CityBuilding = components['schemas']['CityBuilding'];
export type RealtimeConfig = components['schemas']['RealtimeConfig'];
```

Never hand-edit `packages/contracts/src/generated/api.ts`.
</action>
<verify>
  <automated>cd /Users/sierra/Dev/Jogos/CastleRoyale && npm run contracts:check && npm run typecheck --workspace=@castleroyale/contracts && npm run lint --workspace=@castleroyale/contracts</automated>
</verify>
<acceptance_criteria>
- `grep -n 'CitySlot:' packages/contracts/openapi.yaml` matches.
- `grep -n 'RealtimeConfig:' packages/contracts/openapi.yaml` matches.
- `grep -n 'buildings:' packages/contracts/openapi.yaml` no longer matches inside the `CityData` schema block (`CityBuilding` itself remains).
- `grep -n 'CitySlot' packages/contracts/src/generated/api.ts` matches.
- `grep -n "export type CitySlot" packages/contracts/src/index.ts` matches.
- `npm run contracts:check` exits 0 (regeneration produces no diff).
</acceptance_criteria>
<done>
The contract describes a fixed slot roster and a shared realtime config, and the
generated TypeScript matches the spec byte for byte.
</done>
</task>

<task type="auto">
<name>Task 2: Project the roster in CityStateService and keep the mobile screen compiling</name>
<files>apps/api/modules/City/Application/CityStateService.php, apps/mobile/app/(tabs)/city.tsx</files>
<read_first>
- apps/api/modules/City/Application/CityStateService.php
- apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php
- apps/mobile/app/(tabs)/city.tsx
- apps/api/modules/City/Interface/Http/CityController.php
- .planning/codebase/CONVENTIONS.md
</read_first>
<action>
In `apps/api/modules/City/Application/CityStateService.php::handle()`:

1. Keep the existing per-building payload construction, but collect it into a
   map keyed by slot instead of a flat list. Rename the local `$buildings` to
   `$occupied` and change the push to:

```php
$occupied[(string) $cityBuilding->slot] = [
    'slot' => (string) $cityBuilding->slot,
    'code' => $cityBuilding->building_code,
    'name_key' => (string) ($definition['name_key'] ?? $cityBuilding->building_code),
    'category' => (string) ($definition['category'] ?? 'city'),
    'level' => $level,
    'max_level' => $maxLevel,
    'next_level_cost' => $next === null ? $this->emptyBundle() : $this->cost($next),
    'build_time_seconds' => $next === null ? 0 : (int) ($next['build_time_seconds'] ?? 0),
];
```

   Initialise it as `$occupied = [];` and add a PHPStan-friendly docblock
   (`/** @var array<string, array<string, mixed>> $occupied */`). The
   `orderBy('building_code')` on the query is now irrelevant to the response
   order — leave it, it keeps the query deterministic.

2. Build the full roster projection immediately after that loop:

```php
// The roster is the contract: the client renders a fixed scene and must never
// have to infer how many plots exist from how many are built.
$slots = [];
foreach ($this->catalog->citySlots() as $slotCode) {
    $building = $occupied[$slotCode] ?? null;
    $slots[] = [
        'slot' => $slotCode,
        'status' => $building === null ? 'empty' : 'occupied',
        'building' => $building,
    ];
}
```

3. In the returned array, replace `'buildings' => $buildings,` with
   `'slots' => $slots,` and add `'realtime' => $bootstrap['realtime'],`
   immediately before `'server_time'`. Update the method's return docblock if it
   names the old key.

In `apps/mobile/app/(tabs)/city.tsx`, keep the existing MVP screen working
(07-03 replaces it wholesale) with the smallest possible edit. Immediately after
`const construction = city.construction;` add:

```tsx
const buildings = city.slots.flatMap((slot) => (slot.building ? [slot.building] : []));
```

and change `{city.buildings.map((building) => {` to `{buildings.map((building) => {`.
Do not add a non-null assertion (`!`) — the `flatMap` narrowing is what keeps this
type-safe under `strict`.
</action>
<verify>
  <automated>cd /Users/sierra/Dev/Jogos/CastleRoyale/apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G && ./vendor/bin/pint --test && cd /Users/sierra/Dev/Jogos/CastleRoyale && npm run typecheck && npm run lint</automated>
</verify>
<acceptance_criteria>
- `grep -n 'citySlots()' apps/api/modules/City/Application/CityStateService.php` matches.
- `grep -n "'slots' => \$slots" apps/api/modules/City/Application/CityStateService.php` matches.
- `grep -n "'realtime' => \$bootstrap\['realtime'\]" apps/api/modules/City/Application/CityStateService.php` matches.
- `grep -n "'buildings' =>" apps/api/modules/City/Application/CityStateService.php` returns nothing.
- `grep -n 'city.slots.flatMap' 'apps/mobile/app/(tabs)/city.tsx'` matches; `grep -n 'city.buildings' 'apps/mobile/app/(tabs)/city.tsx'` returns nothing.
- `npm run typecheck` and `npm run lint` exit 0 from the repo root.
- `cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G` and `./vendor/bin/pint --test` exit 0.
</acceptance_criteria>
<done>
`GET /api/v1/game/city` returns 18 slots in roster order with 5 occupied for a
starter city, carries the realtime config, and the existing mobile screen still
type-checks against the new contract.
</done>
</task>

<task type="auto">
<name>Task 3: Prove the slot contract and the CITY_NOT_OWNED boundary over HTTP</name>
<files>apps/api/tests/Feature/City/CityFoundationTest.php, apps/api/tests/Feature/City/CityAuthorizationTest.php, apps/api/tests/Feature/Mvp/MvpGameplayTest.php</files>
<read_first>
- apps/api/tests/Feature/City/CityFoundationTest.php
- apps/api/tests/Feature/Mvp/MvpGameplayTest.php
- apps/api/tests/Pest.php
- apps/api/modules/Shared/Application/Error/ErrorCode.php
- .planning/codebase/TESTING.md
</read_first>
<action>
1. Update `apps/api/tests/Feature/City/CityFoundationTest.php`. Rewrite the first
   test, `'persists stable starter building slots and returns them in city state'`,
   to assert the roster contract instead of the old occupied-only array:

```php
$city->assertOk()->assertJsonStructure(['data' => ['slots' => [['slot', 'status', 'building']]]]);

$slots = collect($city->json('data.slots'));
expect($slots)->toHaveCount(18)
    ->and($slots->pluck('slot')->all())->toBe(app(GameDataCatalog::class)->citySlots())
    ->and($slots->where('status', 'occupied')->count())->toBe(5)
    ->and($slots->where('status', 'empty')->count())->toBe(13)
    ->and($slots->pluck('status')->unique()->sort()->values()->all())->toBe(['empty', 'occupied'])
    ->and($slots->where('status', 'empty')->pluck('building')->unique()->all())->toBe([null])
    ->and($slots->where('status', 'occupied')->every(fn (array $slot): bool => is_array($slot['building'])))->toBeTrue();

expect($city->json('data.slots.0.slot'))->toBe('plot_01')
    ->and($city->json('data.slots.0.building.code'))->toBe('palace');
expect(CityBuilding::query()
    ->where('world_id', $bootstrap->json('data.world.id'))
    ->where('city_id', $cityId)
    ->count())->toBe(5);
```

   Import `Game\Shared\Infrastructure\GameData\GameDataCatalog`. Leave the other
   three tests in that file untouched.

2. Update `apps/api/tests/Feature/Mvp/MvpGameplayTest.php`. Only the four
   assertion paths change; `farm` sits in `plot_02`, which is roster index `1`:

   - line ~42-43: replace
     `->assertJsonPath('data.buildings.0.code', 'farm')` and
     `->assertJsonPath('data.buildings.0.level', 1)` with
     `->assertJsonPath('data.slots.1.slot', 'plot_02')`,
     `->assertJsonPath('data.slots.1.status', 'occupied')`,
     `->assertJsonPath('data.slots.1.building.code', 'farm')` and
     `->assertJsonPath('data.slots.1.building.level', 1)`.
   - line ~74-75: replace the same two paths with
     `->assertJsonPath('data.slots.1.building.code', 'farm')` and
     `->assertJsonPath('data.slots.1.building.level', 2)`.

   Change nothing else in that file — the upgrade endpoint, the resource-debit
   assertions and the idempotency tests all stay as they are.

3. Create `apps/api/tests/Feature/City/CityAuthorizationTest.php` proving the
   ownership boundary at the HTTP layer, which no existing test does:

   - **"refuses another player's city with CITY_NOT_OWNED"**: issue tokens for two
     accounts via `app(TokenIssuer::class)->issue(...)` (mirror the
     `cityFoundationTokens()` helper in `CityFoundationTest.php`), bootstrap both
     with distinct `Idempotency-Key` headers, then
     `$this->withToken($rivalToken)->getJson('/api/v1/game/city/'.$ownerCityId)`
     and assert with `expect($response)->toBeApiError(ErrorCode::CityNotOwned);`.
   - **"leaks nothing about a city it refuses"**: assert on the same response that
     the raw body contains neither the owner's city id, nor its `name_key`, nor
     any `plot_` string, and that `$response->json('data')` is `null`. Use
     `expect($response->getContent())->not->toContain($ownerCityId)`.
   - **"never answers 404 for a city that exists but is not yours"**: assert
     `$response->status()` is not `404` (criterion 5 names this explicitly — a 404
     would itself be an existence oracle).
</action>
<verify>
  <automated>cd /Users/sierra/Dev/Jogos/CastleRoyale/apps/api && ./vendor/bin/pest --filter=CityAuthorization && ./vendor/bin/pest --filter=CityFoundation && ./vendor/bin/pest --filter=MvpGameplay && ./vendor/bin/pest</automated>
</verify>
<acceptance_criteria>
- `grep -n "toHaveCount(18)" apps/api/tests/Feature/City/CityFoundationTest.php` matches.
- `grep -n "data.slots.1.building.code" apps/api/tests/Feature/Mvp/MvpGameplayTest.php` matches twice.
- `grep -n "data.buildings" apps/api/tests/` returns nothing.
- `apps/api/tests/Feature/City/CityAuthorizationTest.php` exists with 3 tests and contains `toBeApiError(ErrorCode::CityNotOwned)`.
- `cd apps/api && ./vendor/bin/pest --filter=CityAuthorization` exits 0 with 3 passing tests.
- `cd apps/api && ./vendor/bin/pest` exits 0 for the whole suite.
</acceptance_criteria>
<done>
The API is proven to return every slot every time, and a foreign city read is
proven to return `CITY_NOT_OWNED`, not a 404, with nothing of the city in the body.
</done>
</task>

## Verification

```bash
npm run contracts:check
npm run typecheck
npm run lint
npm test
cd apps/api && ./vendor/bin/pest
cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G
cd apps/api && ./vendor/bin/pint --test
```

## Success Criteria

- `CityData.slots` is always the full roster length (18 today) in roster order.
- Every slot carries `status` of exactly `empty` or `occupied`, and `building` is
  `null` for every empty one and an object for every occupied one.
- `GET /api/v1/game/city/{foreignCityId}` returns `CITY_NOT_OWNED`, no `data` key,
  no city identifier or name in the body, and a status that is not 404.
- `npm run contracts:check` proves the generated types match the hand-authored spec.
- The MVP construction loop test still passes end to end.

<output>
After completion, create
`.planning/phases/07-city-foundation/07-02-city-state-slots-api-SUMMARY.md`
recording the replace-not-supplement decision for `CityData.buildings`, the
required-and-nullable `building` field, and the move of `realtime` onto `CityData`.
</output>
