---
phase: 10-technology-research
plan: 03
type: execute
wave: 2
depends_on: ["10-01"]
files_modified:
  - apps/api/database/migrations/2026_09_07_000100_create_player_technologies_tables.php
  - apps/api/modules/Technology/Infrastructure/PlayerTechnology.php
  - apps/api/modules/Technology/Infrastructure/ResearchOrder.php
  - apps/api/modules/Technology/Domain/EffectResolver.php
  - apps/api/modules/Technology/Application/ResearchService.php
  - apps/api/modules/Technology/Application/ResearchCompletionService.php
  - apps/api/modules/Technology/Application/ResearchReconciler.php
  - apps/api/modules/Technology/Interface/Jobs/CompleteResearch.php
  - apps/api/modules/Technology/Interface/Http/ResearchController.php
  - apps/api/modules/Technology/Interface/Http/TechnologyTreeController.php
  - apps/api/modules/Economy/Application/CityEconomyService.php
  - apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php
  - apps/api/routes/api.php
  - apps/api/routes/console.php
  - packages/contracts/openapi.yaml
  - packages/contracts/src/generated/api.ts
  - apps/api/tests/Feature/Technology/ResearchQueueTest.php
  - apps/api/tests/Feature/Technology/ResearchEffectTest.php
  - apps/api/tests/Feature/Technology/ResearchCompletionTest.php
autonomous: true
requirements: [REQ-05, REQ-06, REQ-09]

must_haves:
  truths:
    - "Starting a research debits resources atomically inside a lock and writes started_at and finishes_at in UTC from the injected Clock"
    - "A second concurrent research returns RESEARCH_IN_PROGRESS; a locked technology returns TECHNOLOGY_LOCKED; a maxed one returns TECHNOLOGY_MAX_LEVEL — all at HTTP 400"
    - "A completed technology's effect is observable in a recomputed value: production rate rises by the documented permille amount"
    - "The completion job is idempotent and a dead worker costs nothing — the reconciler finishes every overdue research exactly once"
  artifacts:
    - path: "apps/api/modules/Technology/Domain/EffectResolver.php"
      provides: "The pure add-then-multiply permille resolver shared by buildings and technologies"
      contains: "permille"
    - path: "apps/api/tests/Feature/Technology/ResearchEffectTest.php"
      provides: "ROADMAP criterion 4 — the effect observable in a recomputed rate, not merely a row"
      contains: "ratesPerHour"
    - path: "apps/api/tests/Feature/Technology/ResearchQueueTest.php"
      provides: "ROADMAP criteria 2 and 3 — the three refusal codes"
      contains: "ResearchInProgress"
  key_links:
    - from: "apps/api/modules/Economy/Application/CityEconomyService.php"
      to: "Game\\Technology\\Domain\\EffectResolver"
      via: "ratesPerHour() resolves building and technology effects through the shared resolver"
      pattern: "EffectResolver"
    - from: "apps/api/modules/Technology/Application/ResearchReconciler.php"
      to: "Game\\Technology\\Application\\ResearchCompletionService"
      via: "run() calls completeOverdueLocked per player"
      pattern: "completeOverdueLocked"
---

<objective>
Make research a real timed, paid, server-owned operation whose completion measurably
changes the empire.

This is where ROADMAP criteria 2, 3 and 4 are earned. Criterion 4 is the one that can
quietly fail: CONTEXT.md is explicit that "a completed technology's effect must be
observable in a recomputed value — test it, do not just assert the row exists."

Phase 09 built the analogue of nearly all of this for buildings. The instruction here
is to **reuse its seams**, not to re-derive them — and where a Phase 09 component was
deliberately written generic (`BuildingRequirementEvaluator`), to use it rather than
writing a technology-shaped twin.
</objective>

<context>

<interfaces>
Verified present. Read each before depending on it.

```php
// Game\Construction\Application\BuildingRequirementEvaluator — WRITTEN GENERIC ON PURPOSE
public function unmet(string $buildingCode, int $targetLevel, array $currentLevels): array;
// reads requirements[] from GameDataCatalog::buildingLevel(); returns code => level-needed
```
09-04's SUMMARY states Phase 10 technology prerequisites were an explicit intended
reuse of this evaluator. It is currently hardcoded to `buildingLevel`. Generalise it
(see Task 3) rather than cloning it.

```php
// Game\Economy\Application\CityEconomyService
public function ratesPerHour(City $city): array;      // production.<resource> * 3600
public function balances(City $city): array;
public function capacities(City $city): array;
private function effects(City $city): array;          // -> catalog->effectsForBuildings($buildings)
public function debitLocked(City $city, ResourceBundle $cost, string $reason, string $reference, LedgerParty $party): ...;
private const SECONDS_PER_HOUR = 3600;
```

```php
// Game\Shared\Infrastructure\GameData\GameDataCatalog
public function effectsForBuildings(Collection $buildings): array;
// TODAY: seeds production.<r> and storage.<r> to 0, then for each building level's
// effects applies ONLY `operation === 'add'` and ONLY when the target key already
// exists in the accumulator. `multiply` is silently ignored. There is no permille
// handling anywhere in the codebase yet.
```

```php
// Game\Shared\Infrastructure\Database\GameTable
public static function entity(Blueprint $t): void;      // ulid id + timestamps
public static function worldScoped(Blueprint $t): void; // ulid world_id, indexed
public static function timed(Blueprint $t): void;       // started_at/finishes_at/completed_at + index
```

```php
// Game\Construction\Domain\BuildDuration
public static function scaled(int $rawSeconds, int $timeScale): int;  // the one place game.time_scale is applied
```

`ErrorCode` already contains `ResearchInProgress` = `RESEARCH_IN_PROGRESS`,
`TechnologyLocked` = `TECHNOLOGY_LOCKED`, `TechnologyMaxLevel` = `TECHNOLOGY_MAX_LEVEL`,
and `openapi.yaml` already lists all three. **Confirm this yourself with grep before
adding anything to either.**

`Player` model: `protected $fillable = ['world_id', 'account_id', 'name']`.

Test fixtures in `apps/api/tests/Pest.php`: `freezeClock(string $iso): FrozenClock`
(with `advanceSeconds(int)`), `toBeApiSuccess(int $status = 200)`,
`toBeApiError(ErrorCode $code, ?int $status = null)`.

`apps/api/phpunit.xml` forces `QUEUE_CONNECTION=sync`, so no job dispatches in the
default test run. A test wanting the job must set `config(['queue.default' => 'redis'])`
together with `Queue::fake()`.
</interfaces>

<canonical_refs>
- `docs/game-design/technology.md` § Effects — "(target, operation, permille) applied by a pure resolver shared with hero bonuses". Shared is the operative word: Phase 13 (Heroes) will reuse this resolver.
- `docs/adr/010-integer-economy.md` — integer permille, truncate downward.
- `docs/adr/006-server-time.md` — the injected Clock.
- `.planning/phases/09-buildings-construction/09-03-SUMMARY.md` — the worker-death / reconciler / idempotency test shape this plan copies, and the falsification lesson below.
</canonical_refs>

<trap>
**The read path completes orders.** Phase 09 learned this the hard way: if a service
completes overdue work before doing anything else, a test that advances the clock and
then calls an HTTP endpoint has already completed the research, so the job and the
reconciler correctly find nothing and the test passes while proving nothing. **Never
touch an HTTP endpoint between advancing the clock and the assertion under test.** Read
state with Eloquent.

**Falsification must target the right guard.** 09-03's plan asked to prove the
completion guard by removing `whereNull('completed_at')` from the completion *service*
and watching a *job* test fail — it did not fail, because the job short-circuits on its
own guard first. When this plan asks you to prove a guard bites, remove the guard on the
exact code path the test exercises, and if it does not fail, say so in the SUMMARY and
add the test that does fail.
</trap>
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Persistence — researched technologies and research orders</name>

  <read_first>
    - apps/api/database/migrations/*create_construction_orders* (the exact GameTable helper usage and unique-index style to mirror)
    - apps/api/modules/Shared/Infrastructure/Database/GameTable.php (entity/worldScoped/timed)
    - apps/api/modules/Construction/Infrastructure/ConstructionOrder.php (model conventions: fillable, casts, namespace)
    - apps/api/modules/City/Infrastructure/CityBuilding.php (the level-per-entity model this mirrors)
  </read_first>

  <files>
    apps/api/database/migrations/2026_09_07_000100_create_player_technologies_tables.php,
    apps/api/modules/Technology/Infrastructure/PlayerTechnology.php,
    apps/api/modules/Technology/Infrastructure/ResearchOrder.php
  </files>

  <behavior>
    - `player_technologies` holds one row per (world, player, technology) with its current level
    - `research_orders` holds the timed triple and is uniquely keyed on (world_id, player_id, idempotency_key)
    - A partial unique index guarantees at most one open research per player at the database level, not merely in application code
    - Both tables are world-scoped
  </behavior>

  <action>
Create the migration `2026_09_07_000100_create_player_technologies_tables.php` following
the construction_orders migration's exact style (`GameTable::entity`, `GameTable::worldScoped`,
`GameTable::timed`, explicit `foreign('world_id')`).

```php
Schema::create('player_technologies', function (Blueprint $table): void {
    GameTable::entity($table);
    GameTable::worldScoped($table);
    $table->foreignUlid('player_id')->constrained('players')->cascadeOnDelete();
    $table->string('technology_code');
    $table->unsignedInteger('level');
    $table->foreign('world_id')->references('id')->on('worlds')->cascadeOnDelete();
    $table->unique(['world_id', 'player_id', 'technology_code']);
});

Schema::create('research_orders', function (Blueprint $table): void {
    GameTable::entity($table);
    GameTable::worldScoped($table);
    GameTable::timed($table);
    $table->foreignUlid('player_id')->constrained('players')->cascadeOnDelete();
    $table->foreignUlid('city_id')->constrained('cities')->cascadeOnDelete();
    $table->string('technology_code');
    $table->unsignedInteger('from_level');
    $table->unsignedInteger('target_level');
    $table->string('idempotency_key', 64);
    $table->foreign('world_id')->references('id')->on('worlds')->cascadeOnDelete();
    $table->unique(['world_id', 'player_id', 'idempotency_key']);
});
```

`city_id` is on the order because the *cost* is paid from a city's balance even though
the *technology* belongs to the player — record that reasoning in a comment in the
migration, because it is the non-obvious part of this schema.

**The one-research-at-a-time invariant belongs in the database.** After both
`Schema::create` calls, add a partial unique index so the rule survives a race that
slips past the application lock:

```php
// One open research per player, enforced by the database. The application also
// checks this inside a lock, but a partial unique index is what makes the rule
// true rather than merely usually-true — the same reasoning that made the
// cities_world_id_x_y_unique index the authority in Phase 07.
DB::statement(
    'CREATE UNIQUE INDEX research_orders_one_open_per_player
     ON research_orders (world_id, player_id)
     WHERE completed_at IS NULL'
);
```

Partial indexes are PostgreSQL syntax. The default test suite runs SQLite
(`phpunit.xml`) while CI also runs PostgreSQL (`phpunit.postgres.xml`) — SQLite supports
this exact partial-index syntax too, so the statement works on both. **Verify that claim
by running the migration under both before finishing this task**; if SQLite rejects it,
guard the statement on the driver and note in the SUMMARY that the invariant is
PostgreSQL-only, so Task 2's concurrency test knows which authority it is proving.

Models `PlayerTechnology` and `ResearchOrder` in `Game\Technology\Infrastructure`,
mirroring `ConstructionOrder`'s conventions exactly — read it for `$fillable`, `casts()`
(datetime casts on the timed triple, integer casts on levels) and namespace layout.
  </action>

  <acceptance_criteria>
    - `docker compose exec -T api php artisan migrate:fresh --seed` exits 0
    - `grep -c "research_orders_one_open_per_player" apps/api/database/migrations/2026_09_07_000100_create_player_technologies_tables.php` is 1
    - `grep -c "GameTable::timed" apps/api/database/migrations/2026_09_07_000100_create_player_technologies_tables.php` is 1
    - `grep -c "world_id" apps/api/modules/Technology/Infrastructure/ResearchOrder.php` is ≥ 1
    - `docker compose exec -T api ./vendor/bin/pest` stays green (no existing test broken by the migration)
    - `docker compose exec -T api ./vendor/bin/phpstan analyse --memory-limit=1G` — 0 errors
  </acceptance_criteria>

  <verify>
    <automated>docker compose exec -T api php artisan migrate:fresh --seed &amp;&amp; docker compose exec -T api ./vendor/bin/pest</automated>
  </verify>

  <done>
    Research has persistence, and "one research at a time" is a database invariant
    rather than an application convention.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: The shared effect resolver — add, then multiply by permille</name>

  <read_first>
    - apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php (`effectsForBuildings` in full — the accumulator seeding, the `operation === 'add'` branch, and the `array_key_exists` guard that silently drops unknown targets)
    - apps/api/modules/Economy/Application/CityEconomyService.php (`ratesPerHour`, `effects`, `syncDerivedStatsLocked` — every consumer of the effect map)
    - apps/api/modules/Shared/Domain/Economy/ResourceType.php (the resource enum the targets are keyed by)
    - docs/adr/010-integer-economy.md (truncate downward)
  </read_first>

  <files>
    apps/api/modules/Technology/Domain/EffectResolver.php,
    apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php,
    apps/api/modules/Economy/Application/CityEconomyService.php
  </files>

  <behavior>
    - `EffectResolver::resolve(array $baseline, array $effectSets): array` applies every `add` first, then every `multiply`, and returns integers
    - `multiply` treats `value` as permille where 1000 is the identity: a base of 10 with a 1100 multiplier yields 11
    - Multiplication truncates downward: a base of 10 with 1105 yields 11, not 11.05 and not 12
    - Multipliers compose additively on the permille surplus, not multiplicatively — two +10% technologies yield +20%, not +21%
    - The resolver is pure: no container, no config, no clock, no database
    - `ratesPerHour` reflects a researched technology's production multiplier
    - With no technologies researched, every existing economy test produces byte-identical numbers to before this task
  </behavior>

  <action>
**2a. `Game\Technology\Domain\EffectResolver`** — a `final readonly class` (or a final
class of static methods; match whatever `BuildDuration` does, read it) with no imports
beyond what it strictly needs. It must not reference Laravel.

```php
/**
 * The one place an effect descriptor becomes a number.
 *
 * Shared deliberately: buildings feed it today, technologies from Phase 10, and
 * hero bonuses from Phase 13 (docs/game-design/technology.md § Effects).
 *
 * @param array<string,int> $baseline  target => starting value
 * @param list<list<array{target:string,operation:string,value:int}>> $effectSets
 * @return array<string,int>
 */
public static function resolve(array $baseline, array $effectSets): array
```

Algorithm, in this order:

1. Copy `$baseline` into `$totals`.
2. **Pass one — `add`.** For every effect with `operation === 'add'`, add `value` to
   `$totals[$target]`, creating the key at 0 if absent.
3. **Pass two — `multiply`.** Accumulate a permille *surplus* per target:
   `$surplus[$target] += ($value - 1000)`. Then for each target with a surplus,
   `$totals[$target] = intdiv($totals[$target] * (1000 + $surplus[$target]), 1000)`.

Two decisions to encode in comments, because both are choices a later reader would
otherwise second-guess:

- **Add before multiply.** A flat bonus is part of the base a percentage then scales.
  The reverse order would make a technology's value depend on the order buildings were
  constructed, which is not a property a player could reason about.
- **Surplus accumulates additively.** Two +10% technologies give +20%, not +21%.
  Multiplicative stacking compounds and makes late-game balance unpredictable; additive
  stacking is what the design doc's flat permille model implies.

`intdiv` truncates toward zero, which for the non-negative values here is truncation
downward as ADR-010 requires. A negative total (possible only if a future `add` effect
is negative) would truncate toward zero rather than down — add a comment noting that
and that no negative-total case exists today.

**2b. `GameDataCatalog`.** Replace `effectsForBuildings`'s inline accumulation with a
call to `EffectResolver::resolve`, and add:

```php
/** @param Collection<int,PlayerTechnology> $technologies */
public function effectsForTechnologies(Collection $technologies): array
public function effectsFor(Collection $buildings, Collection $technologies): array
```

`effectsFor` seeds the baseline (`production.<r>` and `storage.<r>` at 0, exactly as
today), gathers the effect arrays from each building level and each technology level,
and calls the resolver once with all of them. Keep `effectsForBuildings` working with
its current signature — other callers exist — implemented as
`effectsFor($buildings, collect())`.

**Do not keep the `array_key_exists($target, $effects)` guard** that silently drops
unknown targets. 10-01 authors targets like `build.speed` and `march.speed` that no
consumer reads yet; silently dropping them is fine today but would hide a typo forever.
Instead let the resolver create the key, and let consumers read only the keys they know.
Note this change in the SUMMARY — it is a behaviour change, even if an invisible one.

**2c. `CityEconomyService`.** Change the private `effects(City $city)` to also load the
city's owner's researched technologies and pass both to `effectsFor`. The city knows its
player (read the City model to confirm the column name before writing the query). Every
query filters `world_id`.

`ratesPerHour` and `syncDerivedStatsLocked` then reflect technology effects with no
further change, because both already read from the effect map.
  </action>

  <acceptance_criteria>
    - `grep -cE "\b(config|now|app|DB|Model)\(" apps/api/modules/Technology/Domain/EffectResolver.php` is 0 — the resolver is pure
    - `grep -c "intdiv" apps/api/modules/Technology/Domain/EffectResolver.php` is ≥ 1 and `grep -cE "[0-9]+\.[0-9]+|\(float\)|floatval" apps/api/modules/Technology/Domain/EffectResolver.php` is 0
    - `grep -c "EffectResolver" apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php` is ≥ 1
    - `grep -c "function effectsForBuildings" apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php` is still 1 — the old signature survives
    - `docker compose exec -T api ./vendor/bin/pest --filter=Economy` is green with no changed expected numbers — a city with no technologies must produce exactly the rates it did before
    - `docker compose exec -T api ./vendor/bin/pest --group=arch` green
    - `docker compose exec -T api ./vendor/bin/phpstan analyse --memory-limit=1G` — 0 errors
  </acceptance_criteria>

  <verify>
    <automated>docker compose exec -T api ./vendor/bin/pest --filter=Economy</automated>
  </verify>

  <done>
    One pure resolver turns effect descriptors into numbers for both buildings and
    technologies, percentages are integer permille truncated downward, and a city with
    no research produces exactly the numbers it did before.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: Starting a research — the locked spend, the three refusals, the timers</name>

  <read_first>
    - apps/api/modules/Construction/Application/BuildingUpgradeService.php (the ENTIRE start() method — the transaction, the lock, the check order, the debit, the order write, the conditional job dispatch. This is the template.)
    - apps/api/modules/Construction/Application/BuildingRequirementEvaluator.php (the generic evaluator to extend, not clone)
    - apps/api/modules/Construction/Domain/BuildDuration.php (the one place time_scale is applied)
    - apps/api/modules/Construction/Interface/Http/BuildingUpgradeController.php (controller thinness, idempotency-key handling, response shape)
    - apps/api/modules/Shared/Application/Error/ErrorCode.php (confirm the three codes already exist)
    - apps/api/routes/api.php (route style and middleware)
    - packages/contracts/openapi.yaml (confirm the three codes are already documented; find where a new endpoint's schema goes)
  </read_first>

  <files>
    apps/api/modules/Technology/Application/ResearchService.php,
    apps/api/modules/Construction/Application/BuildingRequirementEvaluator.php,
    apps/api/modules/Technology/Interface/Http/ResearchController.php,
    apps/api/modules/Technology/Interface/Http/TechnologyTreeController.php,
    apps/api/routes/api.php,
    packages/contracts/openapi.yaml,
    packages/contracts/src/generated/api.ts,
    apps/api/tests/Feature/Technology/ResearchQueueTest.php
  </files>

  <behavior>
    - `POST /game/technologies/{code}/research` debits the city atomically inside a lock and writes an order whose `started_at`/`finishes_at` come from the injected Clock in UTC
    - A second research while one is open returns `RESEARCH_IN_PROGRESS` at HTTP 400 and spends nothing
    - A technology whose prerequisites are unmet returns `TECHNOLOGY_LOCKED` at HTTP 400, naming what is missing
    - A technology already at `max_level` returns `TECHNOLOGY_MAX_LEVEL` at HTTP 400
    - Refusal precedence is fixed and tested: max level, then locked, then in progress, then insufficient resources
    - A request body carrying its own cost, duration or target level changes nothing
    - `GET /game/technologies` returns the whole tree with each technology's current level, its requirements and its computed state
  </behavior>

  <action>
**3a. Generalise `BuildingRequirementEvaluator`.** It currently calls
`GameDataCatalog::buildingLevel`. Give it a second method rather than changing the
existing signature (09-04's tests depend on `unmet()` as it stands):

```php
/**
 * @param array<string,int> $currentBuildingLevels
 * @param array<string,int> $currentTechnologyLevels
 * @return array<string,array{type:string,level:int}> requirement code => what it needed
 */
public function unmetFor(string $type, string $code, int $targetLevel,
                         array $currentBuildingLevels, array $currentTechnologyLevels): array
```

`$type` is `'building'` or `'technology'` and selects which catalogue method supplies
the `requirements[]`. Each requirement is then checked against the map matching its own
`type` — a technology may require a building and vice versa. Requirement types with no
map yet (`nobility`, `player_level`) are skipped with an explicit `continue` and a
comment; silently treating them as satisfied is the correct behaviour today but must be
deliberate.

Reimplement the existing `unmet()` as a call to `unmetFor('building', ...)` so there is
one code path, and confirm 09-04's `BuildingRequirementsTest` still passes untouched.

**3b. `ResearchService::start()`** — model it on `BuildingUpgradeService::start()`,
which you must read in full first. Signature:

```php
public function start(Account $account, string $worldId, string $cityId,
                      string $technologyCode, string $idempotencyKey): array
```

Inside one `DB::transaction`, in this exact order:

1. Lock the city (`lockForUpdate`), resolve the owning player, refuse `CITY_NOT_OWNED`
   if the account does not own it — copy the existing check verbatim.
2. Complete any overdue research first (`ResearchCompletionService::completeOverdueLocked`),
   the same way `BuildingUpgradeService` completes overdue construction — otherwise a
   player whose research finished but whose job has not run yet is wrongly told
   `RESEARCH_IN_PROGRESS`.
3. Read current technology levels for the player, scoped by `world_id`.
4. `TECHNOLOGY_MAX_LEVEL` if `currentLevel >= max_level`.
5. `TECHNOLOGY_LOCKED` if `unmetFor('technology', ...)` returns anything, with the
   unmet map in the exception's `details['missing']`, exactly as 09-04 does for
   `BUILDING_REQUIREMENTS_NOT_MET`.
6. `RESEARCH_IN_PROGRESS` if an open `research_orders` row exists for this player.
7. Recompute the cost server-side from the catalogue and `debitLocked` it —
   `INSUFFICIENT_RESOURCES` propagates from there. Reason `'technology.research'`,
   reference the idempotency key, `LedgerParty` matching how 09 does it.
8. Write the `ResearchOrder` with `BuildDuration::scaled($level['research_time_seconds'], (int) config('game.time_scale'))`.
9. Dispatch `CompleteResearch` on the `gameplay` queue delayed to `finishes_at`
   `->afterCommit()`, guarded by `config('queue.default') !== 'sync'` — copy the guard
   verbatim from `BuildingUpgradeService`.

**Precedence is a decision, not an accident.** The order above tells a player the
permanent reason (maxed) before the structural one (locked) before the transient one
(in progress) before the economic one (cannot afford). Assert all four boundaries in
tests.

**3c. Controllers.** `ResearchController` (invokable, thin — read
`BuildingUpgradeController` and match it exactly) and `TechnologyTreeController`
returning the tree. The tree response, per 10-UI-SPEC's Data Contract section:

```
data.technologies[]: { code, name_key, description_key, category, level, max_level,
                       next_level: { cost, research_time_seconds, requirements[], effects[] } | null,
                       state: "locked"|"available"|"in_progress"|"completed" }
data.research: { technology_code, target_level, started_at, finishes_at } | null
data.server_time
```

`state` is computed server-side. The client must not re-derive it — that is the same
rule that made Phase 09's CTA read the server snapshot.

**3d. Routes** in `apps/api/routes/api.php`, matching the existing group and middleware:
`GET /game/technologies` → `TechnologyTreeController`,
`POST /game/technologies/{code}/research` → `ResearchController`.

**3e. `openapi.yaml`** — add both endpoints and the `Technology`/`ResearchOrder` schemas.
The three error codes are already in the enum; confirm with grep and do not duplicate
them. Regenerate `packages/contracts/src/generated/api.ts` with
`npm run contracts:generate` and verify with `npm run contracts:check`.

**3f. `ResearchQueueTest.php`** — guest → bootstrap → act, following
`ConstructionQueueLimitTest`'s helper style (a uniquely-named local helper; PHPUnit
loads every `*Test.php` in one process, so a duplicated top-level function name is a
fatal redeclare — this bit Phase 09).

- *"starts a research, debiting the city and stamping the injected clock in UTC"* —
  assert `started_at`/`finishes_at` format `+00:00`, the debit matches the catalogue,
  and the ledger rows carry the idempotency key as reference.
- *"refuses a second concurrent research with RESEARCH_IN_PROGRESS"* — and assert the
  second request spent nothing and created no second order.
- *"refuses a locked technology and names the missing prerequisite"*.
- *"refuses a maxed technology with TECHNOLOGY_MAX_LEVEL"*.
- *"reports TECHNOLOGY_MAX_LEVEL over an unmet prerequisite"* and
  *"reports TECHNOLOGY_LOCKED over RESEARCH_IN_PROGRESS"* — the precedence pair.
- *"ignores a client-supplied cost, duration and target level"* — post a body with
  `research_time_seconds: 0`, `cost: {}`, `target_level: 99`; assert the persisted order
  and the debit are the catalogue's.
- *"serves the tree with a server-computed state per technology"* — assert a tier-1
  technology is `available`, one behind an unmet prerequisite is `locked`, and after
  starting one it is `in_progress`.
  </action>

  <acceptance_criteria>
    - `grep -c "function unmetFor" apps/api/modules/Construction/Application/BuildingRequirementEvaluator.php` is 1
    - `docker compose exec -T api ./vendor/bin/pest --filter=BuildingRequirements` still reports 6 passing — 09-04's tests unchanged and unbroken
    - `grep -c "ResearchInProgress" apps/api/tests/Feature/Technology/ResearchQueueTest.php` is ≥ 2
    - `grep -c "TechnologyLocked" apps/api/tests/Feature/Technology/ResearchQueueTest.php` is ≥ 2
    - `grep -c "TechnologyMaxLevel" apps/api/tests/Feature/Technology/ResearchQueueTest.php` is ≥ 2
    - `grep -c "assertStatus(400)" apps/api/tests/Feature/Technology/ResearchQueueTest.php` is ≥ 3
    - `grep -c "+00:00" apps/api/tests/Feature/Technology/ResearchQueueTest.php` is ≥ 2
    - `grep -cE "\bnow\(\)|Carbon::now" apps/api/tests/Feature/Technology/ResearchQueueTest.php` is 0 — the frozen clock only
    - `grep -c "BuildDuration::scaled" apps/api/modules/Technology/Application/ResearchService.php` is 1 — the shared duration function, not a reimplementation
    - `docker compose exec -T api ./vendor/bin/pest --filter=ResearchQueue` reports 8 passing tests
    - `npm run contracts:check` exits 0
    - `docker compose exec -T api ./vendor/bin/phpstan analyse --memory-limit=1G` — 0 errors; `pint --test` clean
  </acceptance_criteria>

  <verify>
    <automated>docker compose exec -T api ./vendor/bin/pest --filter=Research &amp;&amp; npm run contracts:check</automated>
  </verify>

  <done>
    Research costs resources, takes server-controlled time, refuses for three distinct
    reasons in a fixed and tested precedence, and cannot be influenced by the request
    body.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 4: Completion, the reconciler, and the effect made observable</name>

  <read_first>
    - apps/api/modules/Construction/Application/ConstructionCompletionService.php (the whereNull guard and the per-order loop)
    - apps/api/modules/Construction/Application/ConstructionReconciler.php (the open-world loop and what run() returns)
    - apps/api/modules/Construction/Interface/Jobs/CompleteConstruction.php (the job's own orderExists guard — note it short-circuits before the service)
    - apps/api/routes/console.php (the every-minute construction-reconcile entry to mirror)
    - apps/api/tests/Feature/Construction/ConstructionReconcilerTest.php (the worker-death test shape to copy)
    - apps/api/tests/Feature/Construction/ConstructionCompletionTest.php (both idempotency tests, including the service-direct one 09-03 had to add)
    - apps/api/modules/Economy/Application/CityEconomyService.php (ratesPerHour — the recomputed value criterion 4 needs)
  </read_first>

  <files>
    apps/api/modules/Technology/Application/ResearchCompletionService.php,
    apps/api/modules/Technology/Application/ResearchReconciler.php,
    apps/api/modules/Technology/Interface/Jobs/CompleteResearch.php,
    apps/api/routes/console.php,
    apps/api/tests/Feature/Technology/ResearchCompletionTest.php,
    apps/api/tests/Feature/Technology/ResearchEffectTest.php
  </files>

  <behavior>
    - Completing raises the player's technology level by one and stamps `completed_at`, guarded on `completed_at IS NULL`
    - Running the job twice completes once; calling the service twice completes once
    - With the worker dead, one reconciler pass completes every overdue research exactly once; a second pass changes nothing; the late job changes nothing
    - After a production-multiplier technology completes, `ratesPerHour` returns the documented higher number
    - Two ranks of the same technology stack additively on the permille surplus
  </behavior>

  <action>
**4a. `ResearchCompletionService::completeOverdueLocked(Player $player, DateTimeImmutable $now)`**
— mirror `ConstructionCompletionService` exactly: select `research_orders` where
`finishes_at <= now` AND `completed_at IS NULL`, `lockForUpdate`; per order upsert
`player_technologies` to `target_level`, stamp `completed_at`, and dispatch the
appropriate state-changed broadcast (read how Phase 07/09 dispatch `CityStateChanged`
and follow it; if a player-level channel does not exist yet, dispatch `CityStateChanged`
for the order's `city_id` and note in the SUMMARY that a player-scoped channel is a
Phase 33 concern).

**4b. `ResearchReconciler::run(): int`** — mirror `ConstructionReconciler`: iterate open
worlds, find distinct players with overdue open orders, and complete per player in a
transaction. Return the number completed.

09-03 recorded a concern that `ConstructionReconciler` scans only open worlds, so an
order in a world closed for maintenance never completes. **Mirror that behaviour here
rather than diverging**, and note in the SUMMARY that both reconcilers now share the
limitation, so whichever phase fixes it fixes both. Divergence between the two would be
worse than the shared limitation.

**4c. `CompleteResearch` job** — mirror `CompleteConstruction`, including its own
`whereNull('completed_at')` existence pre-check.

**4d. `routes/console.php`** — add a `research-reconcile` every-minute entry mirroring
`construction-reconcile`, matching its exact style and comment.

**4e. `ResearchCompletionTest.php`** — copy 09-03's shape, including the lesson it
learned:

- *"completes once when the job runs twice"* — `Queue::fake()`, advance the clock, run
  `app()->call([$job,'handle'])` twice, assert one level gain and one broadcast.
- *"completes once when the service itself is called twice"* — **this test is not
  optional.** 09-03 discovered the job short-circuits on its own guard, so the job test
  does not exercise the service's guard at all, and the reconciler depends on the
  service's guard. Call `completeOverdueLocked` directly twice.
- *"a dead worker costs nothing: the reconciler finishes the research exactly once"* —
  `Queue::fake()` and never process; assert nothing completed unaided, then
  `run()` returns 1, a second `run()` returns 0, and the late job changes nothing, with
  exactly one broadcast across the whole sequence.
- *"leaves a research that is not due yet alone"*.

**Prove the guards bite.** After the tests pass, temporarily remove
`whereNull('completed_at')` from `ResearchCompletionService` and confirm the
*service-direct* test fails (not necessarily the job test — see the trap). Restore it
and confirm `git status --porcelain apps/api/modules` is empty. Record in the SUMMARY
which test failed and which did not.

**4f. `ResearchEffectTest.php` — ROADMAP criterion 4, the one that matters most.**

- *"a completed production technology raises the city's rate by the documented amount"* —
  freeze the clock, guest, bootstrap. Capture `GET /game/city`'s
  `data.resources.rate.food`. Read the *documented* multiplier straight from the
  catalogue (`app(GameDataCatalog::class)->technologyLevel('agriculture', 1)`) rather
  than hardcoding 1100, so a designer's rebalance does not turn this into a false
  failure. Start the research, advance past `finishes_at`, complete it via the
  reconciler (**not** via an HTTP call — the read path would complete it and the test
  would prove nothing). Then assert the recomputed rate equals
  `intdiv($baseRate * $permille, 1000)` and, separately, that it is strictly greater
  than the base rate. **Both assertions matter**: the equality pins the arithmetic, and
  the strict inequality catches a permille of 1000 or a dropped effect that the equality
  alone would happily accept.
- *"two ranks stack additively, not multiplicatively"* — research the same technology to
  level 2, assert the rate matches a surplus sum, and assert explicitly that it is
  **not** the compounded value. Skip only if the authored tree has no technology whose
  first two levels both carry a production multiplier — and if you skip, say so in the
  SUMMARY.
- *"an unresearched empire's rates are unchanged"* — a regression pin that the resolver
  refactor did not shift baseline numbers.
  </action>

  <acceptance_criteria>
    - `grep -c "whereNull('completed_at')" apps/api/modules/Technology/Application/ResearchCompletionService.php` is ≥ 1
    - `grep -c "research-reconcile" apps/api/routes/console.php` is 1
    - `grep -c "ratesPerHour\|resources.rate" apps/api/tests/Feature/Technology/ResearchEffectTest.php` is ≥ 2
    - `grep -c "intdiv" apps/api/tests/Feature/Technology/ResearchEffectTest.php` is ≥ 1 — the expected value is computed from the catalogue, not hardcoded
    - `grep -cE "\bgetJson\(|postJson\(" apps/api/tests/Feature/Technology/ResearchEffectTest.php` — verify by reading that no HTTP call occurs between `advanceSeconds` and the assertion under test
    - `docker compose exec -T api ./vendor/bin/pest --filter=ResearchCompletion` reports 4 passing
    - `docker compose exec -T api ./vendor/bin/pest --filter=ResearchEffect` reports 3 passing (2 if the stacking test was justifiably skipped)
    - `git status --porcelain apps/api/modules` is empty after the falsification experiment
    - `docker compose exec -T api ./vendor/bin/pest` fully green; `phpstan` 0 errors; `pint --test` clean
  </acceptance_criteria>

  <verify>
    <automated>docker compose exec -T api ./vendor/bin/pest --filter=Research &amp;&amp; docker compose exec -T api ./vendor/bin/pest</automated>
  </verify>

  <done>
    Research completes reliably even when the worker dies, completion is idempotent at
    both the job and the service layer, and a finished technology is observable as a
    higher production rate computed from the catalogue's own documented multiplier.
  </done>
</task>

</tasks>

<verification>
- `docker compose exec -T api ./vendor/bin/pest` — green, ≥ 15 new tests
- `docker compose exec -T api ./vendor/bin/pest --filter=Economy` — green with unchanged numbers for an unresearched empire
- `docker compose exec -T api ./vendor/bin/pest --filter=BuildingRequirements` — 6 passing, 09-04 unbroken
- `docker compose exec -T api ./vendor/bin/phpstan analyse --memory-limit=1G` — 0 errors
- `docker compose exec -T api ./vendor/bin/pint --test` — clean
- `npm run contracts:check` — exits 0
- `npm run typecheck && npm run lint && npm test` — green
- `git status --porcelain apps/api/modules` — empty (the falsification experiment restored)
</verification>
