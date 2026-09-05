---
phase: 08-resources-economy
plan: 04
type: execute
wave: 4
depends_on: ["08-03"]
files_modified:
  - apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php
  - apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php
autonomous: true
requirements: [REQ-09, REQ-02]

must_haves:
  truths:
    - "Two spends competing for the same resources produce exactly one success and one INSUFFICIENT_RESOURCES"
    - "A spend that was affordable when the request arrived is refused if a rival committed first — affordability is decided inside the lock, not from an earlier snapshot"
    - "A refused spend leaves nothing behind: no construction order, no ledger row, no partial debit"
    - "The same Idempotency-Key submitted twice debits once"
    - "Summing the ledger reproduces the balance for any random sequence of operations, and a failure prints the seed that produced it"
  artifacts:
    - path: "apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php"
      provides: "The interleaved-race proof and the double-submit proof over HTTP"
      min_lines: 120
    - path: "apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php"
      provides: "Seeded random property test reconciling ledger sum to stored balance"
      contains: "ECONOMY_PROPERTY_SEED"
  key_links:
    - from: "apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php"
      to: "Game\\Construction\\Application\\BuildingUpgradeService"
      via: "an eloquent.retrieved listener committing a rival spend before the locked city read"
      pattern: "eloquent.retrieved"
    - from: "apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php"
      to: "ErrorCode::InsufficientResources"
      via: "toBeApiError on the losing HTTP request"
      pattern: "toBeApiError\\(ErrorCode::InsufficientResources\\)"
---

<objective>
Close ROADMAP criteria 3 and 5. Criterion 3 has no test at all today: I grepped the
whole suite and nothing exercises two competing spends. Criterion 5 has a test, but it
walks a fixed arithmetic sequence (`$amount = ($step * 37) % 121`), which is a
regression test, not the property test the criterion and `08-CONTEXT.md` both name.

Purpose: `08-CONTEXT.md` calls the concurrent-spend test "the single most important
test in the phase," and REQ-09 is the requirement it proves. A test that merely calls
spend twice in a row proves nothing — sequential calls succeed for trivial reasons.
The proof has to show the affordability decision being made against state read inside
the lock, with a rival's write landing in between.

Output: an interleaved race proof over HTTP using the same `Event::listen` technique
Phase 07 proved on the tile claim, an idempotency double-submit proof at the ledger
level, and a genuinely randomised, reproducibly-seeded reconciliation property test.
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
@.planning/codebase/TESTING.md
@.planning/codebase/CONCERNS.md
@.planning/phases/08-resources-economy/08-03-ledger-parties-append-only-SUMMARY.md

<interfaces>
<!-- Everything the executor needs to build the race. Do not go exploring. -->

**Why the default suite cannot open two real connections.** `phpunit.xml` pins
`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`. A second connection to `:memory:` is a
different, empty database, so genuine parallelism is unavailable. Phase 07 solved the
identical problem for the tile claim by injecting the rival mutation at the exact
interleaving point with a model event listener; that file is the template:
`apps/api/tests/Feature/City/CityTileClaimTest.php`, the test
`it('resolves a simultaneous claim on the same tile to exactly one city')`. It uses a
one-shot `$raced` flag and asserts `expect($raced)->toBeTrue()` so a code-path change
makes the test fail loudly instead of silently passing on a stale premise.

**The exact call order inside the spend request.** `BuildingUpgradeController::__invoke`
runs, in this order:

1. `IdempotencyService::run(...)` opens (inserts the in-flight record).
2. `$this->bootstrap->handle($account)` — opens its OWN `DB::transaction`, reads
   `Player`, **commits**.
3. `$this->upgrade->start(...)` — opens a NEW `DB::transaction`, then:
   a. reads `Player` (no lock),
   b. reads `City` **with `lockForUpdate`**,
   c. `completeOverdueLocked`, `accrueLocked`,
   d. reads the `CityBuilding` with `lockForUpdate`,
   e. build-queue and same-building-busy checks,
   f. `$balances = ResourceBundle::fromArray($this->economy->balances($city));`
      and `if (! $balances->covers($cost))` → **the affordability check**,
   g. `debitLocked(...)`, `ConstructionOrder::create(...)`.

Step 2 commits before step 3 opens. That is the interleaving seam: a listener firing
during step 2 runs inside a transaction that commits, so its write survives, and step
3b then reads the city fresh and sees it. A listener firing at step 3d or later would
be useless, because step 3f reads in-memory attributes loaded at 3b.

**Costs, verified in `packages/game-data/data/buildings.json`:**
- `farm` level 2 cost: `{ "wood": 120, "stone": 60 }`
- `lumber_mill` level 2 cost: `{ "food": 80, "stone": 100 }`

They share `stone`. A city holding `food = 80, wood = 120, stone = 100, iron = 0,
gold = 0` can afford EITHER alone but NOT both — the joint stone requirement is 160.
That is the "same resources" contention criterion 3 asks for.

**Guards that would fire first if the setup is wrong:**
`CityBusy` if both requests target the same building code (so use two different ones);
`BuildQueueFull` at 4 active orders (`config('game.limits.max_build_queue_slots')`);
`BuildingMaxLevel` above level 3.

**Service signature after plan 08-03:**

```php
BuildingUpgradeService::start(
    Account $account, string $worldId, string $cityId,
    string $buildingCode, string $idempotencyKey,
): array   // {id, building_code, from_level, target_level, started_at, finishes_at}
```

**Test fixtures available** (`apps/api/tests/Pest.php`):
`freezeClock(string $iso8601)` returns a `FrozenClock` with `advanceSeconds(int)`.
`expect($response)->toBeApiError(ErrorCode $code, ?int $status = null)` asserts status,
`error.code`, and `assertJsonMissingPath('data')`.
`ErrorCode::InsufficientResources->httpStatus()` is `400` (it falls to the `default` arm).

**Guest auth over HTTP**, as `MvpGameplayTest` does it:

```php
$guest = $this->withHeader('Idempotency-Key', 'some-unique-key')
    ->postJson('/api/v1/auth/guest');
$token = $guest->json('data.access_token');
$this->withToken($token)->getJson('/api/v1/game/city');
```

Check `MvpGameplayTest` for the exact JSON path to the access token before copying.
</interfaces>
</context>

<decisions>
**Locked decision — the race is proven by an interleaving injection, not by two real
threads.** SQLite in-memory makes real parallelism impossible and PostgreSQL-only
assertions belong in `tests/Postgres/` per TESTING.md. The interleaving injection is
the technique this repository already proved on the tile claim in Phase 07, it is
deterministic, and it tests the guarantee that actually matters: the affordability
decision is made from state read **inside** the lock, so a rival's committed spend is
seen. Assert `$raced === true` so the test cannot silently degrade into a sequential
one if the service's call order ever changes.

**Locked decision — the seeded property test uses `$this->assertSame($expected, $actual, $message)`,
not `expect()->toBe()`.** A random test without a reproducible seed is a flaky test, and
PHPUnit's assertion is the version guaranteed to carry a custom failure message. The
seed is read from `ECONOMY_PROPERTY_SEED` when set, so a red CI run is replayable
locally with one environment variable.
</decisions>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Prove two competing spends resolve to exactly one success and one INSUFFICIENT_RESOURCES</name>
  <files>apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php</files>
  <read_first>
    - apps/api/tests/Feature/City/CityTileClaimTest.php (the whole file — the `Event::listen('eloquent.creating: ...')` one-shot `$raced` technique and the `expect($raced)->toBeTrue()` guard this test copies)
    - apps/api/modules/Construction/Application/BuildingUpgradeService.php (the whole `start()` method, so the interleaving seam in the interfaces block above is verified against live code before writing the listener)
    - apps/api/modules/Construction/Interface/Http/BuildingUpgradeController.php (the bootstrap-then-start order)
    - apps/api/tests/Feature/Mvp/MvpGameplayTest.php (the guest → token → upgrade HTTP flow; the access token is returned FLAT as `data.access_token`, NOT nested under `data.tokens`)
    - apps/api/tests/Pest.php (`freezeClock`, `toBeApiError`)
    - packages/game-data/data/buildings.json (confirm farm L2 and lumber_mill L2 costs before relying on them)
  </read_first>
  <behavior>
    - Given a city that can afford `farm` L2 alone and `lumber_mill` L2 alone but not
      both, a rival `lumber_mill` spend committing between the request's bootstrap and
      its locked city read makes the `farm` HTTP request return
      `INSUFFICIENT_RESOURCES` (HTTP 400) with no `data` key.
    - Exactly one `ConstructionOrder` exists afterwards, and it is the rival's.
    - Exactly one set of `building.upgrade` ledger rows exists, matching the rival's
      cost only — the loser wrote nothing.
    - The city's `stone` is `0` and no resource is negative.
    - Summing every ledger row's `amount` per resource reproduces the city's stored
      balance exactly — the race did not mint or destroy value.
    - `$raced` is `true`, so the interleaving genuinely happened.
  </behavior>
  <action>
Create `apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php`, namespace
`Tests\Feature\Economy`. This task writes ONE test; task 2 adds the second.

```php
it('resolves two competing spends for the same resources to one success and one INSUFFICIENT_RESOURCES', function (): void {
    freezeClock('2026-08-28T00:00:00+00:00');

    // Guest + bootstrap over HTTP so the whole request pipeline is under test,
    // not just the service.
    $guest = $this->withHeader('Idempotency-Key', 'economy-race-guest')
        ->postJson('/api/v1/auth/guest');
    $token = (string) $guest->json('data.access_token');

    $this->withToken($token)->getJson('/api/v1/game/city')->assertOk();

    $account = Account::query()->firstOrFail();          // the guest just created
    $city = City::query()->firstOrFail();
    $worldId = (string) $city->world_id;
    $cityId = (string) $city->getKey();

    // farm L2 costs wood 120 + stone 60; lumber_mill L2 costs food 80 + stone 100.
    // Either fits alone; together they need 160 stone and only 100 exists. This is
    // the contention the criterion asks for, expressed in real game-data costs.
    DB::table('cities')->where('id', $cityId)->update([
        'food' => 80, 'wood' => 120, 'stone' => 100, 'iron' => 0, 'gold' => 0,
    ]);

    // The rival commits between the request's own bootstrap transaction (which
    // reads Player and COMMITS) and start()'s locked City read. Firing on the
    // bootstrap read is what makes the rival's write survive to be seen — a
    // listener placed inside start()'s transaction would be rolled back with it.
    $raced = false;
    Event::listen('eloquent.retrieved: '.Player::class, function () use (&$raced, $account, $worldId, $cityId): void {
        if ($raced) {
            return;
        }
        $raced = true;

        app(BuildingUpgradeService::class)->start(
            $account, $worldId, $cityId, 'lumber_mill', 'economy-race-rival',
        );
    });

    $loser = $this->withToken($token)
        ->withHeader('Idempotency-Key', 'economy-race-loser')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade');

    expect($raced)->toBeTrue();
    expect($loser)->toBeApiError(ErrorCode::InsufficientResources);

    // Exactly one success.
    expect(ConstructionOrder::query()->where('world_id', $worldId)->count())->toBe(1)
        ->and((string) ConstructionOrder::query()->where('world_id', $worldId)->value('building_code'))
            ->toBe('lumber_mill');

    // The loser left nothing behind — no partial debit, no orphan ledger row.
    $spendRows = EconomyLedger::query()
        ->where('world_id', $worldId)
        ->where('reason', 'building.upgrade')
        ->get();
    expect($spendRows->pluck('reference')->unique()->values()->all())->toBe(['economy-race-rival']);

    // No value minted or destroyed by the race.
    $fresh = City::query()->whereKey($cityId)->firstOrFail();
    expect((int) $fresh->stone)->toBe(0);
    foreach (ResourceType::all() as $resource) {
        $ledgerTotal = (int) EconomyLedger::query()
            ->where('world_id', $worldId)->where('city_id', $cityId)
            ->where('resource', $resource->value)->sum('amount');
        expect((int) $fresh->getAttribute($resource->value))
            ->toBe($ledgerTotal)
            ->toBeGreaterThanOrEqual(0);
    }
});
```

Practical notes for making this pass:

- The listener fires on EVERY `Player` retrieval, including the one inside the rival's
  own `start()` call. The `$raced` flag guards the re-entry — that is exactly why
  `CityTileClaimTest` uses the same flag.
- If the rival throws (it should not — its cost fits the starting balances), let the
  exception surface; do not swallow it. A swallowed rival failure would make the test
  pass for the wrong reason.
- The frozen clock keeps `accrueLocked` at zero elapsed seconds, so production cannot
  quietly top the balance back up mid-test. If the balances drift anyway, the clock is
  not frozen where you think — fix that, do not loosen the assertion.
- `Account::query()->firstOrFail()` and `City::query()->firstOrFail()` are safe here
  because `RefreshDatabase` gives each test an empty database and exactly one guest
  was created.
- Imports: `Game\City\Infrastructure\City`, `Game\Construction\Application\BuildingUpgradeService`,
  `Game\Construction\Infrastructure\ConstructionOrder`, `Game\Economy\Infrastructure\EconomyLedger`,
  `Game\Identity\Domain\Account`, `Game\Player\Infrastructure\Player`,
  `Game\Shared\Application\Error\ErrorCode`, `Game\Shared\Domain\Economy\ResourceType`,
  `Illuminate\Support\Facades\DB`, `Illuminate\Support\Facades\Event`.
- The dispatcher is rebuilt per test by the framework, so the listener does not leak.
  Add that as a comment, as `CityTileClaimTest` does.
  </action>
  <verify>
    <automated>cd apps/api && ./vendor/bin/pest --filter=EconomyConcurrency</automated>
  </verify>
  <acceptance_criteria>
    - `cd apps/api && ./vendor/bin/pest --filter=EconomyConcurrency` exits 0.
    - `grep -q "eloquent.retrieved: " apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php` succeeds.
    - `grep -q "expect(\$raced)->toBeTrue()" apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php` succeeds — the test cannot silently degrade to sequential.
    - `grep -q "toBeApiError(ErrorCode::InsufficientResources)" apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php` succeeds.
    - `grep -q "'lumber_mill'" apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php` and `grep -q "farm/upgrade" apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php` both succeed — two different buildings contending for the same stone, not a `CITY_BUSY` collision.
    - Temporarily removing the listener registration makes the test FAIL (the farm upgrade would succeed). Verify this by hand once, then restore it, and record the observation in the summary — a race test that passes without the race is worthless.
    - `cd apps/api && ./vendor/bin/pint --test` exits 0.
  </acceptance_criteria>
  <done>Exactly one of two competing spends succeeds, the loser returns INSUFFICIENT_RESOURCES over HTTP and leaves no trace, and the ledger reconciles to the balance afterwards.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Prove a double-submitted spend debits exactly once</name>
  <files>apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php</files>
  <read_first>
    - apps/api/modules/Shared/Application/Idempotency/IdempotencyService.php (the whole file — `run()`, `replayOrReject()`, and the unique `actor_key`/`endpoint`/`idempotency_key` insert that serialises a duplicate)
    - apps/api/tests/Feature/Mvp/MvpGameplayTest.php (the existing replay tests for `/auth/guest` and `/game/bootstrap` — this task adds the missing one for a resource SPEND, which is the case REQ-09 actually names)
    - docs/api/idempotency.md
    - apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php (as written in Task 1 — append, do not restructure)
  </read_first>
  <behavior>
    - The same `Idempotency-Key` posted twice to
      `POST /api/v1/game/city/buildings/farm/upgrade` returns two identical successful
      responses (the second replayed from the stored record).
    - Exactly one `ConstructionOrder` exists.
    - The `building.upgrade` ledger rows for that key sum to exactly one farm-L2 cost:
      `wood -120` and `stone -60`, not double.
    - The city's resulting balances equal the starting balances minus exactly one cost.
  </behavior>
  <action>
Append a second test to `apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php`:

```php
it('debits exactly once when the same spend is submitted twice', function (): void {
    freezeClock('2026-08-28T00:00:00+00:00');

    $guest = $this->withHeader('Idempotency-Key', 'economy-replay-guest')
        ->postJson('/api/v1/auth/guest');
    $token = (string) $guest->json('data.access_token');
    $this->withToken($token)->getJson('/api/v1/game/city')->assertOk();

    $city = City::query()->firstOrFail();
    $worldId = (string) $city->world_id;
    $cityId = (string) $city->getKey();

    DB::table('cities')->where('id', $cityId)->update([
        'food' => 500, 'wood' => 500, 'stone' => 500, 'iron' => 0, 'gold' => 0,
    ]);

    $first = $this->withToken($token)
        ->withHeader('Idempotency-Key', 'economy-replay-upgrade')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade');

    $second = $this->withToken($token)
        ->withHeader('Idempotency-Key', 'economy-replay-upgrade')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade');

    $first->assertStatus(201);
    $second->assertStatus(201);
    expect($second->json('data.construction.id'))->toBe($first->json('data.construction.id'));

    expect(ConstructionOrder::query()->where('world_id', $worldId)->count())->toBe(1);

    // farm level 2 costs wood 120 + stone 60. Twice would be 240 / 120.
    $debits = EconomyLedger::query()
        ->where('world_id', $worldId)->where('city_id', $cityId)
        ->where('reason', 'building.upgrade')
        ->get()
        ->groupBy('resource')
        ->map(static fn ($rows): int => (int) $rows->sum('amount'));

    expect($debits->get('wood'))->toBe(-120)
        ->and($debits->get('stone'))->toBe(-60);

    $fresh = City::query()->whereKey($cityId)->firstOrFail();
    expect((int) $fresh->wood)->toBe(380)
        ->and((int) $fresh->stone)->toBe(440);
});
```

If the second request returns a code other than 201 (for example
`IDEMPOTENCY_KEY_REUSED` because the payload hash differs, or
`IDEMPOTENCY_REQUEST_IN_FLIGHT`), read `IdempotencyService::replayOrReject()` and
adjust the ASSERTION to whatever the service actually guarantees for an identical
replay — but the ledger and `ConstructionOrder` assertions are non-negotiable: one
submission's worth of effect, whatever the status code turns out to be.

The frozen clock is what makes the exact balances (`380`, `440`) predictable — with
production running, the second read would top them back up. If those two numbers do
not match, check the clock before changing the numbers.
  </action>
  <verify>
    <automated>cd apps/api && ./vendor/bin/pest --filter=EconomyConcurrency</automated>
  </verify>
  <acceptance_criteria>
    - `cd apps/api && ./vendor/bin/pest --filter=EconomyConcurrency` exits 0 with 2 passing tests.
    - `grep -c "economy-replay-upgrade" apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php` returns 2 — the same key really is submitted twice.
    - `grep -q "toBe(-120)" apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php` succeeds — the ledger is asserted at exactly one cost, not "less than double".
    - `cd apps/api && ./vendor/bin/pest` exits 0 with 0 failures.
    - `cd apps/api && ./vendor/bin/pint --test` exits 0.
  </acceptance_criteria>
  <done>A double-submitted spend produces one construction order and one cost's worth of ledger debits.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: Turn the reconciliation test into a genuinely seeded property test</name>
  <files>apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php</files>
  <read_first>
    - apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php (the whole file — only the THIRD test changes; the first two stay exactly as they are)
    - apps/api/modules/Economy/Application/CityEconomyService.php (the `debitLocked` / `creditLocked` signatures as plan 08-03 left them, including the `LedgerParty` argument)
    - apps/api/modules/Economy/Domain/LedgerParty.php
    - .planning/phases/08-resources-economy/08-CONTEXT.md (§ The ledger — "a property test over random operation sequences")
    - .planning/codebase/TESTING.md (§ What every phase must test)
  </read_first>
  <behavior>
    - The operation sequence is genuinely random: random resource, random amount,
      random choice of credit / debit / time-advance, over at least 120 steps.
    - Every run prints its seed on failure, and setting `ECONOMY_PROPERTY_SEED=<n>`
      reproduces that exact run.
    - For every resource, the sum of `amount` across all ledger rows for the city
      equals the city's stored balance, exactly.
    - No balance is ever negative and none ever exceeds its capacity.
  </behavior>
  <action>
Rewrite ONLY the third test,
`it('reconciles every city balance to the append-only ledger after a sequence of operations')`.
Leave the first two tests in the file byte-for-byte unchanged.

```php
it('reconciles every city balance to the append-only ledger over a random operation sequence', function (): void {
    // A random test without a reproducible seed is a flaky test. Export
    // ECONOMY_PROPERTY_SEED=<n> to replay the exact sequence a red run produced.
    $seed = (int) (getenv('ECONOMY_PROPERTY_SEED') ?: random_int(1, 2_147_483_647));
    mt_srand($seed);

    $clock = freezeClock('2026-08-28T00:00:00+00:00');
    $account = Account::factory()->create();
    $bootstrap = app(GameBootstrapService::class)->handle($account);
    $cityId = $bootstrap['city']['id'];
    $worldId = $bootstrap['world']['id'];
    $economy = app(CityEconomyService::class);
    $resources = ResourceType::all();

    foreach (range(1, 120) as $step) {
        $operation = mt_rand(0, 2);   // 0 = credit, 1 = debit, 2 = advance time
        $resource = $resources[mt_rand(0, count($resources) - 1)]->value;
        $amount = mt_rand(0, 400);
        $seconds = mt_rand(1, 900);

        DB::transaction(function () use (
            $economy, $worldId, $cityId, $step, $operation, $resource, $amount, $seconds, $clock
        ): void {
            $query = City::query()->where('world_id', $worldId)->whereKey($cityId);
            $query->getQuery()->lockForUpdate();
            $city = $query->firstOrFail();

            if ($operation === 2) {
                $clock->advanceSeconds($seconds);
                $economy->accrueLocked($city, $clock->now());

                return;
            }

            if ($operation === 0) {
                $economy->creditLocked(
                    $city,
                    ResourceBundle::fromArray([$resource => $amount]),
                    'test.sequence.credit',
                    'step-'.$step,
                    LedgerParty::system('test_faucet'),
                );

                return;
            }

            $available = (int) $city->getAttribute($resource);
            $economy->debitLocked(
                $city,
                ResourceBundle::fromArray([$resource => min($available, $amount)]),
                'test.sequence.debit',
                'step-'.$step,
                LedgerParty::system('test_sink'),
            );
        });
    }

    $city = City::query()->where('world_id', $worldId)->whereKey($cityId)->firstOrFail();
    $ledgerBalances = EconomyLedger::query()
        ->where('world_id', $worldId)
        ->where('city_id', $cityId)
        ->get()
        ->groupBy('resource')
        ->map(static fn ($rows): int => (int) $rows->sum('amount'));

    foreach ($resources as $resource) {
        $stored = (int) $city->getAttribute($resource->value);
        $summed = $ledgerBalances->get($resource->value, 0);

        // PHPUnit's assertion is used rather than expect()->toBe() because it is the
        // one guaranteed to carry the seed in the failure message.
        $this->assertSame(
            $stored,
            $summed,
            sprintf(
                'Ledger sum for %s does not reproduce the balance. Replay with ECONOMY_PROPERTY_SEED=%d',
                $resource->value,
                $seed,
            ),
        );

        $this->assertGreaterThanOrEqual(0, $stored, "Negative balance for {$resource->value}; seed {$seed}");
        $this->assertLessThanOrEqual(
            (int) $city->getAttribute($resource->value.'_capacity'),
            $stored,
            "Balance above capacity for {$resource->value}; seed {$seed}",
        );
    }
});
```

Add the imports the rewritten test needs: `Game\Economy\Domain\LedgerParty` (if plan
08-03 did not already add it to this file) and keep every existing import.

Run the test at least ten times in a row before calling it done — a property test that
only passes on one lucky seed is not a property test:

```bash
cd apps/api && for i in $(seq 1 10); do ./vendor/bin/pest --filter="random operation sequence" || break; done
```

If a run fails, do NOT reduce the step count or narrow the random ranges to make it
green. Take the printed seed, replay it with `ECONOMY_PROPERTY_SEED=<n>`, and fix the
economy code the failure exposed — that is the entire point of the test.
  </action>
  <verify>
    <automated>cd apps/api && for i in 1 2 3 4 5 6 7 8 9 10; do ./vendor/bin/pest --filter="random operation sequence" || exit 1; done</automated>
  </verify>
  <acceptance_criteria>
    - `grep -q "ECONOMY_PROPERTY_SEED" apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php` succeeds.
    - `grep -q "mt_srand(\$seed)" apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php` succeeds.
    - `grep -q "assertSame(" apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php` succeeds — the failure message carries the seed.
    - `grep -q "(\$step \* 37) % 121" apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php` returns NOTHING — the deterministic walk is gone.
    - `grep -c "^it(" apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php` returns 3 — still three tests, the first two untouched.
    - `cd apps/api && ./vendor/bin/pest --filter=CityEconomyFoundation` exits 0, ten consecutive runs.
    - `ECONOMY_PROPERTY_SEED=12345 ./vendor/bin/pest --filter="random operation sequence"` run twice from `apps/api` produces the same result both times.
    - `cd apps/api && ./vendor/bin/pest` exits 0 with 0 failures.
    - `cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G` prints `[OK] No errors`.
    - `cd apps/api && ./vendor/bin/pint --test` exits 0.
    - `npm run typecheck && npm run lint && npm test` all exit 0.
  </acceptance_criteria>
  <done>A randomised 120-step sequence including accrual, credits and debits reconciles the ledger to the balance exactly, on ten consecutive runs, and any failure names the seed that caused it.</done>
</task>

</tasks>

<verification>
1. `./vendor/bin/pest --filter=EconomyConcurrency` passes both tests; removing the race listener makes the first one fail (verified by hand once).
2. The losing spend returns `INSUFFICIENT_RESOURCES` (400) with no `data` key and writes no `ConstructionOrder` and no ledger row.
3. A double-submitted spend produces exactly one construction order and exactly one cost's worth of ledger debits.
4. The reconciliation test is randomised over 120 steps with a printed, replayable seed, and passes ten consecutive runs.
5. Balances never go negative and never exceed capacity across the whole random sequence.
6. All six gates from TESTING.md pass.
</verification>

<success_criteria>
- ROADMAP criterion 3 satisfied: two concurrent spends for the same resources produce exactly one success and one INSUFFICIENT_RESOURCES, proven by a test that fails if the race is removed.
- ROADMAP criterion 5 satisfied: a property test over random operation sequences reproduces the balance from the ledger exactly, reproducibly.
- REQ-09 materially advanced: idempotent, concurrency-safe spending with no double-spend, proven at the ledger level rather than asserted.
</success_criteria>

<output>
After completion, create `.planning/phases/08-resources-economy/08-04-locked-spending-concurrency-SUMMARY.md`.
</output>
