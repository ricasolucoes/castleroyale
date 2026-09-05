---
phase: 08-resources-economy
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - packages/contracts/openapi.yaml
  - packages/contracts/src/generated/api.ts
  - apps/api/modules/Economy/Application/CityEconomyService.php
  - apps/api/modules/City/Application/CityStateService.php
  - apps/api/tests/Feature/Economy/ProductionRateTest.php
  - docs/game-design/economy.md
autonomous: true
requirements: [REQ-02]

must_haves:
  truths:
    - "A city read reports its production rate alongside its current balance and its warehouse capacity"
    - "The published rate is exactly what an hour of server accrual actually produces — it cannot lie"
    - "Warehouse capacity is derived from building storage effects in game data, never a constant in PHP"
  artifacts:
    - path: "packages/contracts/openapi.yaml"
      provides: "ResourceRate schema and CityResources.rate as a required field"
      contains: "ResourceRate"
    - path: "packages/contracts/src/generated/api.ts"
      provides: "Generated TS type carrying rate on CityResources"
      contains: "ResourceRate"
    - path: "apps/api/modules/Economy/Application/CityEconomyService.php"
      provides: "ratesPerHour() derived from game-data production effects"
      contains: "ratesPerHour"
    - path: "apps/api/tests/Feature/Economy/ProductionRateTest.php"
      provides: "Proof the wire rate equals real accrual and that capacity comes from game data"
      min_lines: 60
  key_links:
    - from: "apps/api/modules/City/Application/CityStateService.php"
      to: "CityEconomyService::ratesPerHour"
      via: "resources.rate in the GET /game/city payload"
      pattern: "'rate' => \\$this->economy->ratesPerHour"
    - from: "apps/api/modules/Economy/Application/CityEconomyService.php"
      to: "GameDataCatalog::effectsForBuildings"
      via: "production.{resource} effect lookup"
      pattern: "production\\.'"
---

<objective>
Publish each city's production rate on the API contract, spec-first, so the mobile
resource bar (plan 08-05) can interpolate between reads without inventing a unit,
and prove the published figure is exactly what the server actually accrues.

Purpose: `CityResources` today exposes only `current` and `capacity`
(`packages/contracts/openapi.yaml:762-770`). Without a rate the client cannot tick
forward honestly, and 08-05 is unbuildable. ADR-017 is spec-first: the YAML changes
first, then the TypeScript is regenerated from it.

Output: `ResourceRate` schema, `CityResources.rate` required on every city read,
`CityEconomyService::ratesPerHour()`, and a test that ties the published number to
a real hour of accrual.
</objective>

<execution_context>
@/Users/sierra/.claude/get-shit-done/workflows/execute-plan.md
@/Users/sierra/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/ROADMAP.md
@.planning/STATE.md
@.planning/phases/08-resources-economy/08-CONTEXT.md
@.planning/phases/08-resources-economy/08-UI-SPEC.md
@.planning/codebase/ARCHITECTURE.md
@.planning/codebase/CONVENTIONS.md
@.planning/codebase/TESTING.md

<interfaces>
<!-- Contracts the executor needs. Do not go looking for these in the codebase. -->

Existing, in `apps/api/modules/Economy/Application/CityEconomyService.php`:

```php
final readonly class CityEconomyService
{
    public function __construct(private GameDataCatalog $catalog) {}
    public function accrueLocked(City $city, DateTimeImmutable $now): void;
    public function debitLocked(City $city, ResourceBundle $cost, string $reason, string $reference): void;
    /** @return array{credited: ResourceBundle, overflow: ResourceBundle} */
    public function creditLocked(City $city, ResourceBundle $grant, string $reason, string $reference): array;
    /** @return array<string, int> */ public function balances(City $city): array;
    /** @return array<string, int> */ public function capacities(City $city): array;
    /** @return array<string, int> */ private function effects(City $city): array;  // keys: production.{r}, storage.{r}
}
```

`accrueLocked()` already computes, verbatim:

```php
$produced = (int) ($effects['production.'.$name] ?? 0) * $elapsedSeconds;
```

— i.e. the game-data effect value is **units per second**. That is the unit this
plan must not change; it is what the passing test
`it('produces the same balance whether a city is read hourly or after a long absence')`
already depends on, and changing it would be a balance change (Phase 46's job).

Game data facts, verified in `packages/game-data/data/`:
- `farm` level 1 effect: `{ "target": "production.food", "operation": "add", "value": 1 }` → 1 food/second.
- `lumber_mill` L1 → `production.wood` 1; `quarry` L1 → `production.stone` 1.
- `warehouse` L1 has **no** effects; L2+ add `storage.{resource}` 500 (gold 250).
- `starter.json` city capacity: `food/wood/stone/iron = 1000`, `gold = 500`.
- A starter city therefore has `production.food = 1/s`, `production.iron = 0`, `production.gold = 0`.

`GameDataCatalog::effectsForBuildings()` returns a pre-zeroed map with a
`production.{resource}` and `storage.{resource}` key for every `ResourceType`, so
`$effects['production.gold']` is `0`, never missing.

Existing `CityStateService::handle()` returns (excerpt):

```php
'resources' => [
    'current' => $this->economy->balances($city),
    'capacity' => $this->economy->capacities($city),
],
```
</interfaces>
</context>

<decisions>
**Locked decision — the wire unit for `rate` is signed integer units per HOUR.**

The game-data `production.{resource}` effect stays what it already is (units per
second) because `accrueLocked()` multiplies it by elapsed seconds and a passing test
depends on that. The wire value is `perSecond * 3600` — an exact integer, no float,
no rounding, ADR-010 intact. Per hour is what `08-UI-SPEC.md` § Data Contract
Dependency assumed and what `interpolateResources` divides by (`/ 3_600_000` ms), so
08-05 needs no divisor change. This resolves UI-SPEC Flagged Assumption 1.

The field is **signed** (`type: integer`, no `minimum`) even though every rate is
`>= 0` until Phase 12 ships troop upkeep. Declaring the sign now avoids a contract
rewrite later.
</decisions>

<tasks>

<task type="auto">
  <name>Task 1: Add ResourceRate to the OpenAPI contract and regenerate the TS types</name>
  <files>packages/contracts/openapi.yaml, packages/contracts/src/generated/api.ts</files>
  <read_first>
    - packages/contracts/openapi.yaml (read lines 690-800 for `CityResources`, `CityIdentity`, `CitySlot`, and lines 1085-1115 for `ResourceBundle` — copy its formatting style exactly)
    - docs/adr/017-openapi-contract.md
    - .planning/phases/08-resources-economy/08-UI-SPEC.md (§ Data Contract Dependency — the schema below is copied from it verbatim)
  </read_first>
  <action>
Edit `packages/contracts/openapi.yaml` only. Two changes, both under `components.schemas`.

1. Replace the existing `CityResources` block (currently `required: [current, capacity]`
   with only `current` and `capacity` properties) with:

```yaml
    CityResources:
      type: object
      required: [current, capacity, rate]
      properties:
        current:
          $ref: '#/components/schemas/ResourceBundle'
        capacity:
          $ref: '#/components/schemas/ResourceBundle'
        rate:
          $ref: '#/components/schemas/ResourceRate'
```

2. Add a new `ResourceRate` schema immediately after the existing `ResourceBundle`
   schema (which sits around line 1090, just before `ContentVersions`), keeping the
   same two-space-per-level indentation the file already uses:

```yaml
    ResourceRate:
      type: object
      description: >-
        Net production, integer units per hour, signed. Positive accrues toward
        capacity. A future negative value (troop upkeep, Phase 12) would drain
        toward zero — the sign is supported now so this contract does not need a
        rewrite later, even though every rate is >= 0 until Phase 12 ships upkeep.
        Display-only: the client never uses this figure as the basis of an
        affordability check (08-CONTEXT.md, Client decision) — only `current`,
        read fresh from the server, governs what can be spent.
      additionalProperties: false
      properties:
        food: { type: integer }
        wood: { type: integer }
        stone: { type: integer }
        iron: { type: integer }
        gold: { type: integer }
```

Do NOT add a `minimum: 0` — the field is deliberately signed. Do NOT add a new
timestamp field; `CityData.server_time` is already the "as-of" instant.

Then regenerate and verify the contract from the repository root:

```bash
npm run contracts:generate
npm run contracts:check
```

`contracts:check` must exit 0 — it proves the checked-in `api.ts` matches the YAML
byte for byte. If it fails, the generator output is authoritative; re-run generate
and commit whatever it produced. Never hand-edit `api.ts` (ADR-017).
  </action>
  <verify>
    <automated>grep -q "ResourceRate:" packages/contracts/openapi.yaml && grep -q "required: \[current, capacity, rate\]" packages/contracts/openapi.yaml && grep -q "ResourceRate" packages/contracts/src/generated/api.ts && npm run contracts:check</automated>
  </verify>
  <acceptance_criteria>
    - `grep -c "ResourceRate" packages/contracts/openapi.yaml` returns at least 2 (the definition and the `$ref`).
    - `packages/contracts/openapi.yaml` contains the literal line `      required: [current, capacity, rate]`.
    - `packages/contracts/openapi.yaml` does NOT contain `minimum: 0` inside the `ResourceRate` block.
    - `packages/contracts/src/generated/api.ts` contains `ResourceRate`.
    - `npm run contracts:check` exits 0.
    - `npm run typecheck` exits 0.
  </acceptance_criteria>
  <done>The contract declares a signed integer-per-hour `ResourceRate`, `CityResources.rate` is required, and the generated TypeScript matches the YAML.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Derive ratesPerHour from game data and publish it on the city read</name>
  <files>apps/api/modules/Economy/Application/CityEconomyService.php, apps/api/modules/City/Application/CityStateService.php, apps/api/tests/Feature/Economy/ProductionRateTest.php, docs/game-design/economy.md</files>
  <read_first>
    - apps/api/modules/Economy/Application/CityEconomyService.php (the whole file — `accrueLocked`, `effects`, `resourceAttributes`, `syncDerivedStatsLocked`)
    - apps/api/modules/City/Application/CityStateService.php (the `resources` key of the returned array)
    - apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php (the `freezeClock` + `GameBootstrapService` + `CityStateService` test shape this new file must mirror — do NOT modify this file in this plan)
    - apps/api/tests/Pest.php (the `freezeClock` fixture and the `toBeApiSuccess` / `toBeApiError` expectations)
    - .planning/codebase/CONVENTIONS.md (§ PHP, § Comments)
  </read_first>
  <behavior>
    - `ratesPerHour()` on a freshly bootstrapped starter city returns exactly
      `['food' => 3600, 'wood' => 3600, 'stone' => 3600, 'iron' => 0, 'gold' => 0]`
      (farm/lumber_mill/quarry are each level 1 producing 1 per second; nothing
      produces iron or gold yet).
    - Over exactly 3600 seconds of frozen-clock accrual, the sum of
      `amount + overflow_amount` across that window's `production.elapsed` ledger
      rows for `food` equals `3600` — i.e. the published rate is the truth, cap or no
      cap. (A starter city holds `food = 500` against `food_capacity = 1000`, so the
      run credits 500 and discards 3100.)
    - Capacity comes from game data, not a constant: a starter city reports
      `capacity.food = 1000` and `capacity.gold = 500`, matching `starter.json`.
  </behavior>
  <action>
**Step 1 — `CityEconomyService`.** Add a private constant and one public method.
Place the method immediately after `capacities()` so the three read accessors sit
together:

```php
    /**
     * Game data authors production per second; the wire contract publishes it per
     * hour so the client can interpolate against a wall clock without re-deriving
     * the unit (08-UI-SPEC.md § Data Contract Dependency). The multiplication is
     * exact — no float, no rounding (ADR-010).
     *
     * @return array<string, int>
     */
    public function ratesPerHour(City $city): array
    {
        $effects = $this->effects($city);
        $rates = [];
        foreach (ResourceType::all() as $resource) {
            $rates[$resource->value] = (int) ($effects['production.'.$resource->value] ?? 0) * self::SECONDS_PER_HOUR;
        }

        return $rates;
    }
```

and at the top of the class body:

```php
    private const int SECONDS_PER_HOUR = 3600;
```

(PHP 8.4 typed class constants are available; if Pint or PHPStan objects to the
`int` type on the constant, drop the type and keep `private const SECONDS_PER_HOUR = 3600;`.)

Do not change `accrueLocked()`, `debitLocked()` or `creditLocked()` in this plan.

**Step 2 — `CityStateService`.** In `handle()`, extend the `resources` key to:

```php
                'resources' => [
                    'current' => $this->economy->balances($city),
                    'capacity' => $this->economy->capacities($city),
                    'rate' => $this->economy->ratesPerHour($city),
                ],
```

Call `ratesPerHour($city)` **after** `$city->refresh()` and after
`accrueLocked()` has run, so the rate reflects any building that completed on this
same read.

**Step 3 — `docs/game-design/economy.md`.** Under `## Rules`, immediately after the
paragraph beginning "**Production accrues from elapsed server time on read**", add:

```markdown
**Units.** A `production.{resource}` effect in `packages/game-data` is **units per
second** — that is the figure `CityEconomyService::accrueLocked()` multiplies by
elapsed seconds. The API publishes `CityResources.rate` as **signed integer units
per hour** (`perSecond * 3600`) so a client can interpolate against a wall clock.
The two never disagree: an hour of accrual produces exactly the published rate,
counting the portion the warehouse cap discards.
```

**Step 4 — new test file** `apps/api/tests/Feature/Economy/ProductionRateTest.php`,
namespace `Tests\Feature\Economy`, three tests:

1. `it('publishes a starter city production rate of one unit per second per producer')`
   — `freezeClock('2026-08-28T00:00:00+00:00')`, `Account::factory()->create()`,
   `app(GameBootstrapService::class)->handle($account)`, load the `City` by
   `world_id` + key, then
   `expect(app(CityEconomyService::class)->ratesPerHour($city))->toBe(['food' => 3600, 'wood' => 3600, 'stone' => 3600, 'iron' => 0, 'gold' => 0])`.

2. `it('publishes a rate that exactly equals an hour of real accrual, cap included')`
   — freeze the clock, bootstrap, call `app(CityStateService::class)->handle($account)`
   once to set the accrual marker, capture `$rate = $state['resources']['rate']['food']`,
   then `$clock->advanceSeconds(3600)` and call `CityStateService::handle()` again.
   Sum the `production.elapsed` ledger rows for `food`:

   ```php
   $rows = EconomyLedger::query()
       ->where('world_id', $worldId)
       ->where('city_id', $cityId)
       ->where('resource', 'food')
       ->where('reason', 'production.elapsed')
       ->get();
   $accrued = (int) $rows->sum('amount') + (int) $rows->sum('overflow_amount');
   expect($accrued)->toBe($rate);
   ```

   Also assert the balance never passed the cap:
   `expect($state['resources']['current']['food'])->toBe($state['resources']['capacity']['food'])`.

3. `it('returns rate and capacity over HTTP with capacity taken from game data')`
   — authenticate with a guest token the same way `MvpGameplayTest` does
   (`POST /api/v1/auth/guest` with an `Idempotency-Key` header, then
   `->withToken($tokens['access_token'])`), `GET /api/v1/game/city`, and assert:

   ```php
   $response->assertOk()
       ->assertJsonPath('data.resources.rate.food', 3600)
       ->assertJsonPath('data.resources.rate.gold', 0)
       ->assertJsonPath('data.resources.capacity.food', 1000)
       ->assertJsonPath('data.resources.capacity.gold', 500);
   expect(array_keys($response->json('data.resources.rate')))
       ->toBe(['food', 'wood', 'stone', 'iron', 'gold']);
   ```

Write the tests first, watch them fail, then implement steps 1-2.
  </action>
  <verify>
    <automated>cd apps/api && ./vendor/bin/pest --filter=ProductionRate && ./vendor/bin/phpstan analyse --memory-limit=1G && ./vendor/bin/pint --test</automated>
  </verify>
  <acceptance_criteria>
    - `cd apps/api && ./vendor/bin/pest --filter=ProductionRate` exits 0 with 3 passing tests.
    - `grep -q "ratesPerHour" apps/api/modules/Economy/Application/CityEconomyService.php` succeeds.
    - `grep -q "'rate' => \$this->economy->ratesPerHour" apps/api/modules/City/Application/CityStateService.php` succeeds.
    - `grep -q "SECONDS_PER_HOUR" apps/api/modules/Economy/Application/CityEconomyService.php` succeeds — the 3600 is a named constant, not an inline literal.
    - `grep -q "units per second" docs/game-design/economy.md` succeeds.
    - `cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G` reports 0 errors.
    - `cd apps/api && ./vendor/bin/pint --test` exits 0.
  </acceptance_criteria>
  <done>`GET /api/v1/game/city` returns `data.resources.rate`, the number is derived from game-data production effects, and a test proves an hour of accrual produces exactly that number.</done>
</task>

<task type="auto">
  <name>Task 3: Run the full gate and confirm nothing downstream broke on the new required field</name>
  <files>apps/api/tests/Feature/Economy/ProductionRateTest.php</files>
  <read_first>
    - .planning/codebase/TESTING.md (§ Commands — every gate below is listed there)
    - .planning/codebase/CONCERNS.md (§ Environment — a green host suite is SQLite-only evidence; do not claim more than that)
    - apps/api/tests/Feature/Mvp/MvpGameplayTest.php (asserts on the `GET /game/city` payload; confirm the new key did not break its JSON path assertions)
    - apps/mobile/__tests__/city-scene.test.tsx (its `buildCity()` fixture casts through `as unknown as CityData`, so a newly required `rate` must not break `npm run typecheck` — confirm, do not edit; plan 08-05 owns that file)
  </read_first>
  <action>
Run every gate from `.planning/codebase/TESTING.md` in order and fix anything this
plan broke. Do not "fix" a pre-existing failure by weakening an assertion.

```bash
cd apps/api && ./vendor/bin/pest
cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G
cd apps/api && ./vendor/bin/pint --test
npm run typecheck
npm run lint
npm test
npm run contracts:check
```

Baseline before this plan: pest 143 passed / 1101 assertions, jest 11 suites / 58
tests, phpstan 0 errors, pint / typecheck / lint clean. After this plan pest must be
146 passed (the three new `ProductionRateTest` tests) with every previously passing
test still passing.

The one realistic breakage is a TypeScript consumer that constructs a `CityResources`
object literal and now misses `rate`. If `npm run typecheck` reports one, add
`rate: { food: 0, wood: 0, stone: 0, iron: 0, gold: 0 }` to that fixture — do not
make `rate` optional in the YAML to dodge it.

If `apps/api/castleroyale` (the tracked SQLite file) shows as modified after the run,
leave it alone — it is a known housekeeping item in STATE.md, not this plan's business.
  </action>
  <verify>
    <automated>cd apps/api && ./vendor/bin/pest && ./vendor/bin/phpstan analyse --memory-limit=1G && ./vendor/bin/pint --test</automated>
  </verify>
  <acceptance_criteria>
    - `cd apps/api && ./vendor/bin/pest` exits 0 with at least 146 passing tests and 0 failures.
    - `cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G` prints `[OK] No errors`.
    - `cd apps/api && ./vendor/bin/pint --test` exits 0.
    - `npm run typecheck` exits 0.
    - `npm run lint` exits 0.
    - `npm test` exits 0 with 11 suites passing.
    - `npm run contracts:check` exits 0.
  </acceptance_criteria>
  <done>Every repository gate is green with the new required contract field in place.</done>
</task>

</tasks>

<verification>
1. `packages/contracts/openapi.yaml` declares `ResourceRate` and `CityResources.required: [current, capacity, rate]`; `npm run contracts:check` proves the generated TS matches.
2. `GET /api/v1/game/city` returns `data.resources.rate` with all five resource keys.
3. A test proves the published hourly rate equals `amount + overflow_amount` summed over one hour of `production.elapsed` ledger rows — the number cannot drift from reality.
4. A test proves capacity comes from `starter.json` + `storage.*` building effects.
5. All six gates from TESTING.md pass.
</verification>

<success_criteria>
- ROADMAP criterion 1 is reinforced: the rate the API publishes is provably the same rate elapsed-time accrual applies.
- ROADMAP criterion 4's "resources never exceed warehouse capacity" is asserted on the read path (`current.food === capacity.food` after an over-cap hour).
- Plan 08-05 is unblocked: `CityResources.rate` exists as signed integer units per hour, exactly as `08-UI-SPEC.md` specified.
- No balance number moved into PHP; the rate is a unit conversion of a game-data value.
</success_criteria>

<output>
After completion, create `.planning/phases/08-resources-economy/08-01-production-rate-contract-SUMMARY.md`.
</output>
