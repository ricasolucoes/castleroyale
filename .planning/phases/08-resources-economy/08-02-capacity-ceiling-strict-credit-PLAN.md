---
phase: 08-resources-economy
plan: 02
type: execute
wave: 2
depends_on: ["08-01"]
files_modified:
  - apps/api/modules/Economy/Domain/OverflowPolicy.php
  - apps/api/modules/Economy/Application/CityEconomyService.php
  - apps/api/tests/Feature/Economy/WarehouseCapacityTest.php
autonomous: true
requirements: [REQ-02]

must_haves:
  truths:
    - "A city balance can never exceed its warehouse capacity, on any path"
    - "Production above the cap is discarded and the discarded amount is recorded, never silently dropped"
    - "A grant that demands an exact amount the warehouse cannot hold is refused with WAREHOUSE_CAPACITY_EXCEEDED and changes nothing"
    - "The refusal reaches the client as the documented error envelope, with no data key"
  artifacts:
    - path: "apps/api/modules/Economy/Domain/OverflowPolicy.php"
      provides: "The two ways a credit may meet the cap: discard at it, or refuse"
      contains: "enum OverflowPolicy"
    - path: "apps/api/modules/Economy/Application/CityEconomyService.php"
      provides: "creditLocked honouring OverflowPolicy::Refuse"
      contains: "WarehouseCapacityExceeded"
    - path: "apps/api/tests/Feature/Economy/WarehouseCapacityTest.php"
      provides: "Proof of the cap, the recorded discard, the all-or-nothing refusal and its HTTP envelope"
      min_lines: 90
  key_links:
    - from: "apps/api/modules/Economy/Application/CityEconomyService.php"
      to: "Game\\Shared\\Application\\Error\\ErrorCode::WarehouseCapacityExceeded"
      via: "GameException thrown before any mutation under OverflowPolicy::Refuse"
      pattern: "ErrorCode::WarehouseCapacityExceeded"
    - from: "apps/api/bootstrap/app.php"
      to: "ApiResponse::error"
      via: "GameException render funnel producing error.code"
      pattern: "GameException => ApiResponse::error"
---

<objective>
Close ROADMAP criterion 4. Two things are missing today: nothing anywhere in
`apps/api/modules/` ever throws `WAREHOUSE_CAPACITY_EXCEEDED` (the enum case exists
and is already listed in `openapi.yaml`, but is unreachable), and the "resources
never exceed capacity" guarantee is only asserted for one grant path, not for the
production accrual path.

Purpose: a passive faucet (production) correctly discards at the cap and records
the discard. An explicit, caller-requested transfer is different — the caller asked
for an exact amount and cannot have it, so silently keeping a fraction would be a
lie. That distinction is what `WAREHOUSE_CAPACITY_EXCEEDED` names, and it is the
control every future faucet (gathering Phase 16, plunder Phase 19, trade Phase 27)
will reach for.

Output: an `OverflowPolicy` domain enum, a strict all-or-nothing credit path that
raises the error before touching anything, and tests proving the cap holds on both
paths and that the refusal renders as the documented API error envelope.
</objective>

<execution_context>
@/Users/sierra/.claude/get-shit-done/workflows/execute-plan.md
@/Users/sierra/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/ROADMAP.md
@.planning/phases/08-resources-economy/08-CONTEXT.md
@.planning/codebase/ARCHITECTURE.md
@.planning/codebase/CONVENTIONS.md
@.planning/codebase/TESTING.md
@.planning/phases/08-resources-economy/08-01-production-rate-contract-SUMMARY.md

<interfaces>
<!-- Contracts the executor needs. Do not go exploring for these. -->

`Game\Shared\Application\Error\ErrorCode` already declares, under `// --- Economy ---`:

```php
    case InsufficientResources = 'INSUFFICIENT_RESOURCES';
    case WarehouseCapacityExceeded = 'WAREHOUSE_CAPACITY_EXCEEDED';
    case LedgerImbalance = 'LEDGER_IMBALANCE';
```

`WarehouseCapacityExceeded` is not in any `match` arm of `httpStatus()`, so it falls
to `default => 400`. `WAREHOUSE_CAPACITY_EXCEEDED` is **already** listed in the
`ErrorCode` enum in `packages/contracts/openapi.yaml` (line ~1176). Neither file
needs editing in this plan — verify, do not change.

`Game\Shared\Application\Error\GameException`:

```php
public static function of(ErrorCode $code, string $message = '', array $details = []): self;
public readonly ErrorCode $errorCode;
public readonly array $details;
```

`apps/api/bootstrap/app.php` renders every `GameException` reaching an `api/*`
request through one funnel:

```php
$e instanceof GameException => ApiResponse::error($e->errorCode, $e->getMessage(), $e->details),
```

Pest custom expectation, in `apps/api/tests/Pest.php`:

```php
expect($response)->toBeApiError(ErrorCode $code, ?int $status = null);
// asserts status (defaults to $code->httpStatus()), error.code, and assertJsonMissingPath('data')
```

Current `creditLocked` signature and behaviour (discard-at-cap, one ledger row per
resource carrying both the accepted amount and the discarded tail):

```php
/** @return array{credited: ResourceBundle, overflow: ResourceBundle} */
public function creditLocked(City $city, ResourceBundle $grant, string $reason, string $reference): array;
```

Its only production caller today is none; its only caller at all is the passing test
`it('caps server grants and records discarded overflow in the ledger')` in
`apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php`. That test must keep
passing byte-for-byte unchanged — the new parameter is optional and defaults to
today's behaviour.

Starter city facts (from `packages/game-data/data/starter.json`):
`food = 500`, `food_capacity = 1000`; `gold = 100`, `gold_capacity = 500`.
</interfaces>
</context>

<decisions>
**Locked decision — overflow keeps its single-row model.** A capped credit writes ONE
ledger row per resource, carrying `amount` (the portion that landed) and
`overflow_amount` (the portion the cap refused). It is not split into two rows. Reason:
criterion 5 reconciles the balance by summing `amount` alone, and the already-passing
test `it('caps server grants and records discarded overflow in the ledger')` asserts
exactly this shape. Extending beats rewriting what works.

**Locked decision — `WAREHOUSE_CAPACITY_EXCEEDED` is a strict-credit refusal, not a
production error.** Production overflow is a normal economic ceiling and must never
raise; it discards and records. The error belongs to an explicit, all-or-nothing
transfer where partial delivery would be dishonest. Phase 08 ships the mechanism and
proves it; the first gameplay caller is Phase 16 (gathering returns). No new endpoint
is invented here — that would be widening the phase, which `08-CONTEXT.md` forbids.
The HTTP proof therefore exercises the render funnel in `bootstrap/app.php`, which is
the app infrastructure the criterion's "the API returns" clause actually depends on.
</decisions>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Add the OverflowPolicy domain enum and the strict credit path</name>
  <files>apps/api/modules/Economy/Domain/OverflowPolicy.php, apps/api/modules/Economy/Application/CityEconomyService.php</files>
  <read_first>
    - apps/api/modules/Economy/Application/CityEconomyService.php (the whole `creditLocked` method — the new behaviour wraps it, it does not replace it)
    - apps/api/modules/Shared/Application/Error/ErrorCode.php (confirm `WarehouseCapacityExceeded` exists and that `httpStatus()` leaves it on `default => 400`)
    - apps/api/modules/Shared/Domain/Economy/ResourceBundle.php (`toArray()`, `covers()`, `shortfallAgainst()`)
    - apps/api/tests/Architecture/ArchitectureTest.php (`arch('domain layer stays free of the framework')` — note `Game\Economy\Domain` is NOT in the `ignoring` list, so this new namespace must only use `Game` plus the whitelisted PHP classes)
    - .planning/codebase/CONVENTIONS.md (§ PHP — enums are backed by short stable strings; classes are final)
  </read_first>
  <behavior>
    - Under the default policy, `creditLocked` behaves exactly as it does today:
      caps at capacity, records the discarded tail in `overflow_amount`, returns
      `{credited, overflow}`. The existing passing test is untouched proof of this.
    - Under `OverflowPolicy::Refuse`, a grant where ANY resource would exceed its
      capacity throws `GameException` with `errorCode === ErrorCode::WarehouseCapacityExceeded`.
    - The refusal is all-or-nothing: the city's balances are unchanged and NO ledger
      row is written, including for the resources that would have fitted.
    - The refusal names what overflowed in `details['exceeded']` as a list of resource
      name strings, mirroring how `InsufficientResources` uses `details['missing']`.
    - A grant that exactly fills the warehouse to capacity under `Refuse` succeeds —
      the boundary is `> capacity`, not `>= capacity`.
  </behavior>
  <action>
**Step 1 — new file** `apps/api/modules/Economy/Domain/OverflowPolicy.php`:

```php
<?php

declare(strict_types=1);

namespace Game\Economy\Domain;

/**
 * What a credit does when the warehouse cannot hold all of it.
 *
 * A passive faucet (elapsed-time production) fills to the cap and discards the
 * rest — that is a normal economic ceiling, not a failure. An explicit transfer
 * asked for an exact amount, so delivering a fraction of it would be a lie; it is
 * refused whole with WAREHOUSE_CAPACITY_EXCEEDED.
 */
enum OverflowPolicy: string
{
    case DiscardAtCap = 'discard_at_cap';
    case Refuse = 'refuse';
}
```

Note: no `final` keyword — PHP enums are implicitly final, and adding it is a parse
error.

**Step 2 — `CityEconomyService::creditLocked`.** Add a fourth, optional parameter and
a pre-flight check. The new signature:

```php
    public function creditLocked(
        City $city,
        ResourceBundle $grant,
        string $reason,
        string $reference,
        OverflowPolicy $policy = OverflowPolicy::DiscardAtCap,
    ): array
```

Update the existing docblock's `@return` to stay as it is and add a `@param` line for
`$policy`. Immediately after `$balances = $this->balances($city);` and BEFORE the
`foreach` that mutates anything, insert:

```php
        if ($policy === OverflowPolicy::Refuse) {
            // Checked before the first mutation so the refusal is all-or-nothing —
            // a half-delivered transfer is the duplication bug this error exists to
            // prevent, not a lesser version of success.
            $exceeded = [];
            foreach ($grant->toArray() as $resource => $amount) {
                if ($balances[$resource] + $amount > (int) $city->getAttribute($resource.'_capacity')) {
                    $exceeded[] = $resource;
                }
            }

            if ($exceeded !== []) {
                throw GameException::of(
                    ErrorCode::WarehouseCapacityExceeded,
                    'The warehouse cannot hold this delivery.',
                    ['exceeded' => $exceeded],
                );
            }
        }
```

Add `use Game\Economy\Domain\OverflowPolicy;` to the imports. `ErrorCode` and
`GameException` are already imported.

Do not touch `accrueLocked`, `debitLocked`, `balances`, `capacities`,
`ratesPerHour`, `syncDerivedStatsLocked` or `effects` in this task.

Write the failing tests from Task 2 first if you prefer a strict red-green loop;
either order is acceptable as long as Task 2's tests exist and pass at the end.
  </action>
  <verify>
    <automated>cd apps/api && ./vendor/bin/pest --group=arch && ./vendor/bin/phpstan analyse --memory-limit=1G && ./vendor/bin/pint --test</automated>
  </verify>
  <acceptance_criteria>
    - `test -f apps/api/modules/Economy/Domain/OverflowPolicy.php` succeeds.
    - `grep -q "enum OverflowPolicy: string" apps/api/modules/Economy/Domain/OverflowPolicy.php` succeeds.
    - `grep -q "case DiscardAtCap = 'discard_at_cap';" apps/api/modules/Economy/Domain/OverflowPolicy.php` succeeds.
    - `grep -q "case Refuse = 'refuse';" apps/api/modules/Economy/Domain/OverflowPolicy.php` succeeds.
    - `grep -q "ErrorCode::WarehouseCapacityExceeded" apps/api/modules/Economy/Application/CityEconomyService.php` succeeds — the code is finally reachable.
    - `grep -q "OverflowPolicy \$policy = OverflowPolicy::DiscardAtCap" apps/api/modules/Economy/Application/CityEconomyService.php` succeeds — existing callers keep working unchanged.
    - `grep -rn "declare(strict_types=1)" apps/api/modules/Economy/Domain/OverflowPolicy.php` succeeds.
    - `cd apps/api && ./vendor/bin/pest --group=arch` exits 0 — the new `Game\Economy\Domain` namespace obeys the framework-free rule.
    - `cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G` reports 0 errors.
    - `cd apps/api && ./vendor/bin/pint --test` exits 0.
  </acceptance_criteria>
  <done>`WAREHOUSE_CAPACITY_EXCEEDED` is raisable from a real code path, all-or-nothing, and every existing caller of `creditLocked` is unaffected.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Prove the cap, the recorded discard, the refusal and its HTTP envelope</name>
  <files>apps/api/tests/Feature/Economy/WarehouseCapacityTest.php</files>
  <read_first>
    - apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php (the `DB::transaction` + `lockForUpdate` + `creditLocked` shape to mirror — do NOT modify this file; plans 08-03 and 08-04 own their edits to it)
    - apps/api/tests/Pest.php (the `freezeClock` fixture and the `toBeApiError` expectation, including its `assertJsonMissingPath('data')` clause)
    - apps/api/bootstrap/app.php (lines 58-100 — the `withExceptions` render funnel this test exercises)
    - apps/api/tests/Feature/City/CityAuthorizationTest.php (how a Feature test authenticates and asserts an error envelope over HTTP)
  </read_first>
  <behavior>
    - Refuse path throws `WarehouseCapacityExceeded`, and afterwards the city's food
      balance and the total `economy_ledger` row count are both exactly what they were
      before the call.
    - Discard-at-cap path (the default) still caps and still records the discard.
    - Production accrual over a long absence lands the balance exactly ON capacity —
      never one unit above it — and the discarded tail appears as `overflow_amount`.
    - A `GameException` carrying `WarehouseCapacityExceeded` renders on an `api/*`
      request as HTTP 400 with `error.code = "WAREHOUSE_CAPACITY_EXCEEDED"` and no
      `data` key.
  </behavior>
  <action>
Create `apps/api/tests/Feature/Economy/WarehouseCapacityTest.php`, namespace
`Tests\Feature\Economy`. Four tests.

**Test 1 — `it('refuses a strict grant the warehouse cannot hold and changes nothing')`.**
Freeze the clock at `'2026-08-28T00:00:00+00:00'`, create an `Account`, bootstrap,
load the `City`. Capture `$before = (int) $city->food;` and
`$ledgerBefore = EconomyLedger::query()->where('city_id', $city->getKey())->count();`.
Then, inside `DB::transaction` with the same `lockForUpdate` shape used in
`CityEconomyFoundationTest`, call:

```php
app(CityEconomyService::class)->creditLocked(
    $locked,
    ResourceBundle::fromArray(['food' => 700]),
    'test.strict_grant',
    'warehouse-capacity-test',
    OverflowPolicy::Refuse,
);
```

A starter city holds `food = 500` against `food_capacity = 1000`, so 700 overflows by
200. Catch the `GameException` (do not let Pest swallow it — assign it to `$exception`
in a `try/catch`, as `CityTileClaimTest` does) and assert:

```php
expect($exception)->toBeInstanceOf(GameException::class)
    ->and($exception?->errorCode)->toBe(ErrorCode::WarehouseCapacityExceeded)
    ->and($exception?->details['exceeded'])->toBe(['food'])
    ->and((int) $city->fresh()->food)->toBe($before)
    ->and(EconomyLedger::query()->where('city_id', $city->getKey())->count())->toBe($ledgerBefore);
```

**Test 2 — `it('accepts a strict grant that fills the warehouse exactly to capacity')`.**
Same setup, grant `['food' => 500]` under `OverflowPolicy::Refuse`. Assert it does not
throw, `credited.food === 500`, `overflow.food === 0`, and the city's `food` now equals
`food_capacity` (1000). This pins the boundary at `> capacity`, not `>= capacity`.

**Test 3 — `it('lands elapsed production exactly on the cap and records the discarded tail')`.**
Freeze the clock, bootstrap, call `app(CityStateService::class)->handle($account)` once
to set the accrual marker, then `$clock->advanceSeconds(21600)` (six hours) and call it
again. Assert, for `food`:

```php
expect($state['resources']['current']['food'])->toBe($state['resources']['capacity']['food'])
    ->and($state['resources']['current']['food'])->toBeLessThanOrEqual($state['resources']['capacity']['food']);
```

and that the discard was recorded rather than dropped:

```php
$rows = EconomyLedger::query()
    ->where('world_id', $worldId)->where('city_id', $cityId)
    ->where('resource', 'food')->where('reason', 'production.elapsed')->get();
expect((int) $rows->sum('overflow_amount'))->toBeGreaterThan(0);
```

Assert the same `current <= capacity` invariant for all five resources with a loop over
`ResourceType::all()`, so a future producer added to game data cannot break the ceiling
unnoticed.

**Test 4 — `it('renders WAREHOUSE_CAPACITY_EXCEEDED through the API error envelope')`.**
This proves the render funnel in `bootstrap/app.php`, which is what makes the code
reachable by any future endpoint. Register a throwaway route inside the test body and
call it:

```php
Route::get('/api/v1/__warehouse-capacity-probe', static function (): void {
    throw GameException::of(
        ErrorCode::WarehouseCapacityExceeded,
        'The warehouse cannot hold this delivery.',
        ['exceeded' => ['food']],
    );
});

$response = $this->getJson('/api/v1/__warehouse-capacity-probe');

expect($response)->toBeApiError(ErrorCode::WarehouseCapacityExceeded);
$response->assertStatus(400)->assertJsonPath('error.details.exceeded', ['food']);
```

Add a comment above the route explaining why it exists: no gameplay endpoint performs
a strict credit until Phase 16, so this asserts the transport contract the criterion
names without inventing a game endpoint this phase does not own.

Imports the file needs: `Game\City\Application\CityStateService`,
`Game\City\Infrastructure\City`, `Game\Economy\Application\CityEconomyService`,
`Game\Economy\Domain\OverflowPolicy`, `Game\Economy\Infrastructure\EconomyLedger`,
`Game\Identity\Domain\Account`, `Game\Player\Application\GameBootstrapService`,
`Game\Shared\Application\Error\ErrorCode`, `Game\Shared\Application\Error\GameException`,
`Game\Shared\Domain\Economy\ResourceBundle`, `Game\Shared\Domain\Economy\ResourceType`,
`Illuminate\Support\Facades\DB`, `Illuminate\Support\Facades\Route`.
  </action>
  <verify>
    <automated>cd apps/api && ./vendor/bin/pest --filter=WarehouseCapacity</automated>
  </verify>
  <acceptance_criteria>
    - `cd apps/api && ./vendor/bin/pest --filter=WarehouseCapacity` exits 0 with 4 passing tests.
    - `grep -q "OverflowPolicy::Refuse" apps/api/tests/Feature/Economy/WarehouseCapacityTest.php` succeeds.
    - `grep -q "toBeApiError(ErrorCode::WarehouseCapacityExceeded)" apps/api/tests/Feature/Economy/WarehouseCapacityTest.php` succeeds.
    - `grep -q "overflow_amount" apps/api/tests/Feature/Economy/WarehouseCapacityTest.php` succeeds.
    - `git diff --quiet -- apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php` succeeds — the three already-passing tests were not touched by this plan.
    - `cd apps/api && ./vendor/bin/pint --test` exits 0.
  </acceptance_criteria>
  <done>The cap holds on both the accrual path and the grant path, the discard is recorded, the strict refusal mutates nothing, and the error reaches a client as `{"error":{"code":"WAREHOUSE_CAPACITY_EXCEEDED"}}` with no `data` key.</done>
</task>

<task type="auto">
  <name>Task 3: Run the full gate and confirm the existing economy tests still pass untouched</name>
  <files>apps/api/tests/Feature/Economy/WarehouseCapacityTest.php</files>
  <read_first>
    - .planning/codebase/TESTING.md (§ Commands, § What every phase must test)
    - .planning/codebase/CONCERNS.md (§ Environment — the host suite is SQLite-only evidence)
    - apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php (confirm all three tests still pass and were not edited)
  </read_first>
  <action>
Run every gate:

```bash
cd apps/api && ./vendor/bin/pest
cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G
cd apps/api && ./vendor/bin/pint --test
npm run typecheck
npm run lint
npm test
```

Expected pest count after this plan: 150 passing (143 baseline + 3 from plan 08-01 +
4 from this plan), 0 failures.

Confirm specifically that these three pre-existing tests still pass, by name:
- `it('produces the same balance whether a city is read hourly or after a long absence')`
- `it('caps server grants and records discarded overflow in the ledger')`
- `it('reconciles every city balance to the append-only ledger after a sequence of operations')`

Run `cd apps/api && ./vendor/bin/pest --filter=CityEconomyFoundation` and check all
three are green. If the new optional `$policy` parameter broke the second one, the
default is wrong — fix the default, never the test.

Do not run `make test-postgres` in this plan; nothing here uses PostgreSQL-only
syntax, and per CONCERNS.md the host cannot run it anyway.
  </action>
  <verify>
    <automated>cd apps/api && ./vendor/bin/pest && ./vendor/bin/pest --filter=CityEconomyFoundation && ./vendor/bin/phpstan analyse --memory-limit=1G && ./vendor/bin/pint --test</automated>
  </verify>
  <acceptance_criteria>
    - `cd apps/api && ./vendor/bin/pest` exits 0 with at least 150 passing tests and 0 failures.
    - `cd apps/api && ./vendor/bin/pest --filter=CityEconomyFoundation` exits 0 with 3 passing tests.
    - `cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G` prints `[OK] No errors`.
    - `cd apps/api && ./vendor/bin/pint --test` exits 0.
    - `npm run typecheck` exits 0.
    - `npm run lint` exits 0.
    - `npm test` exits 0.
    - `grep -rn "WarehouseCapacityExceeded" apps/api/modules/ | grep -v ErrorCode.php` returns at least one line — proof the code is no longer declared-but-unreachable.
  </acceptance_criteria>
  <done>Every gate green, the three pre-existing economy tests still passing unmodified, and `WAREHOUSE_CAPACITY_EXCEEDED` reachable from module code.</done>
</task>

</tasks>

<verification>
1. `grep -rn "WarehouseCapacityExceeded" apps/api/modules/` returns a hit outside `ErrorCode.php` — the enum case is no longer dead.
2. A strict credit that overflows throws, leaves balances identical and writes zero ledger rows.
3. A strict credit that exactly fills the warehouse succeeds — the boundary is correct.
4. Six hours of accrual lands the balance exactly on capacity for every resource, with the discarded tail in `overflow_amount`.
5. The error renders as HTTP 400, `error.code = WAREHOUSE_CAPACITY_EXCEEDED`, no `data` key.
6. All six gates from TESTING.md pass; the three pre-existing economy tests are untouched and green.
</verification>

<success_criteria>
- ROADMAP criterion 4 fully satisfied: capacity is never exceeded on any path, overflow is discarded at the cap and recorded, and the API returns `WAREHOUSE_CAPACITY_EXCEEDED` where it is genuinely relevant.
- The mechanism Phases 16, 19 and 27 will need (all-or-nothing delivery) exists and is proven, without widening Phase 08 by inventing an endpoint.
- No float anywhere; no balance number moved into PHP.
</success_criteria>

<output>
After completion, create `.planning/phases/08-resources-economy/08-02-capacity-ceiling-strict-credit-SUMMARY.md`.
</output>
