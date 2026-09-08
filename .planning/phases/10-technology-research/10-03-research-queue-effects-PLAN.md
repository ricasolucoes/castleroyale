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
  - apps/api/modules/Technology/Domain/TechnologyGraph.php
  - apps/api/modules/Technology/Application/ResearchCompletionService.php
  - apps/api/modules/Economy/Application/CityEconomyService.php
  - apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php
  - apps/api/tests/Feature/Technology/EffectResolverTest.php
  - apps/api/tests/Feature/Technology/TechnologyGraphTest.php
autonomous: true
requirements: [REQ-06, REQ-09]

must_haves:
  truths:
    - "Research has persistence, and 'one research at a time' is a database invariant rather than an application convention"
    - "One pure resolver turns effect descriptors into numbers for buildings and technologies, with percentages as integer permille truncated downward"
    - "Every technology has a server-computed `tier` (topological depth) and a prerequisite list that survives max level"
    - "Completing a research raises the level exactly once, guarded on completed_at IS NULL"
    - "A city with no researched technologies produces byte-identical rates to before this plan"
  artifacts:
    - path: "apps/api/modules/Technology/Domain/EffectResolver.php"
      provides: "The pure add-then-multiply permille resolver shared by buildings and technologies"
      contains: "permille"
    - path: "apps/api/modules/Technology/Domain/TechnologyGraph.php"
      provides: "The tier (topological depth) computation the whole tree layout depends on"
      contains: "tier"
  key_links:
    - from: "apps/api/modules/Economy/Application/CityEconomyService.php"
      to: "Game\\Technology\\Domain\\EffectResolver"
      via: "ratesPerHour() resolves building and technology effects through the shared resolver"
      pattern: "EffectResolver"
---

<objective>
Lay the foundation research is built on: persistence, the shared effect resolver, the
technology graph's tier computation, and the completion service.

This plan deliberately stops short of the research *command* (10-05). It was split at
the layering seam after the plan checker flagged it at 19 files across 4 tasks, well
above this project's own precedent of 13 files and 3 tasks. Two smaller plans also lose
less work if an executor dies mid-run, which has happened repeatedly in this milestone.

Everything here is either persistence or a pure domain computation — nothing in this
plan handles an HTTP request.

Phase 09 built the analogue of much of this for buildings. Reuse its seams rather than
re-deriving them.
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
  <name>Task 3: The technology graph — tier, and prerequisites that survive max level</name>

  <read_first>
    - packages/game-data/src/validate.ts (its `findCycle` DFS and how it builds a dependency graph from `requirements[]` — the same graph shape, computed in PHP here)
    - apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php (technologies() / technologyLevel() as 10-01 leaves them)
    - .planning/phases/10-technology-research/10-UI-SPEC.md § Layout Strategy and § Data Contract (why `tier` exists and what depends on it)
    - apps/api/modules/Construction/Domain/BuildDuration.php (the shape of a pure domain class in this codebase — no imports, no framework)
  </read_first>

  <files>
    apps/api/modules/Technology/Domain/TechnologyGraph.php,
    apps/api/tests/Feature/Technology/TechnologyGraphTest.php
  </files>

  <behavior>
    - `tier(string $code)` returns the technology's topological depth: 0 when it has no technology prerequisite, otherwise 1 + the maximum tier of its technology prerequisites
    - `prerequisites(string $code)` returns the technology-type requirements for the technology's FIRST level, available regardless of the player's current level
    - Both are computed from the catalogue alone — no player, no database, no clock
    - A cycle in the data does not hang or overflow the stack; it is reported, not survived silently
    - Tiers are memoised so a tree of N technologies is walked once, not once per query
  </behavior>

  <action>
**Why this class exists.** `10-UI-SPEC.md` calls `tier` *"the single most load-bearing
field in this contract"*: the approved Layout Strategy renders one horizontal lane per
non-empty tier within each category, and without a tier there are no lanes and no tree —
only a flat list. Nothing computes it today. This is that computation.

Create `Game\Technology\Domain\TechnologyGraph`, a pure class constructed from the
catalogue's technology array (pass the array in; do not inject `GameDataCatalog`, so the
class stays framework-free and unit-testable exactly like `BuildDuration`):

```php
/** @param list<array<string,mixed>> $technologies the catalogue array */
public function __construct(private array $technologies) {}

/** Topological depth. 0 for a technology with no technology prerequisite. */
public function tier(string $code): int;

/**
 * The technology-type requirements of this technology's FIRST level.
 * @return list<array{code:string,level:int}>
 */
public function prerequisites(string $code): array;

/** @return array<string,int> code => tier, for every technology */
public function tiers(): array;
```

**Tier algorithm.** Depth-first with memoisation over technology-type requirements only
(a `building` requirement does not create a technology tier — a technology gated on a
building is still tier 0 within the technology graph, and the UI-SPEC's lanes are about
technology depth). For each technology, `tier = 0` when it has no technology
prerequisite, otherwise `1 + max(tier(prereq))`.

**Cycle safety.** 10-02's validator rejects cycles at authoring time and CI runs it, so a
cycle cannot normally reach production. But this class must not infinite-loop if one ever
does: track an in-progress set during the walk and, on re-entry, throw a
`DomainException` naming the code rather than recursing. A validator that runs elsewhere
is not a reason for this class to be fragile.

**`prerequisites()` reads level 1's requirements deliberately.** The checker on this
phase's plans caught that nesting prerequisite data only inside a `next_level` field
would make it `null` for a completed technology — so a maxed technology would lose the
ability to render its own `→ prerequisite` caption and its requires-chips. A technology's
unlock prerequisites are a property of the technology, not of whichever level the player
happens to be looking at next, so they are read from level 1 and served unconditionally.

**Tests** (`TechnologyGraphTest.php`), against the real catalogue plus small in-memory
fixtures:
- *"gives a technology with no prerequisite tier 0"*.
- *"gives a technology tier one more than its deepest prerequisite"* — build a fixture
  `a` (no prereq), `b` requires `a`, `c` requires `a` and `b`; assert tiers 0, 1, 2.
  `c` requiring both `a` (tier 0) and `b` (tier 1) must be tier 2, not tier 1 — this is
  the max-not-min case, and getting it wrong puts a node in a lane before its own
  prerequisite.
- *"ignores building requirements when computing tier"* — a technology whose only
  requirement is a building is tier 0.
- *"serves prerequisites for a maxed technology"* — the case the plan checker caught:
  assert `prerequisites()` returns the list regardless of level.
- *"throws naming the code rather than hanging on a cyclic dataset"* — feed `a→b→a`,
  assert a `DomainException` whose message contains both codes.
- *"assigns every real authored technology a tier"* — over the actual catalogue, assert
  every code has an integer tier and at least one technology has tier ≥ 1 (otherwise
  10-01 authored a flat tree and the whole lane layout is untested).
  </action>

  <acceptance_criteria>
    - `grep -cE "^use " apps/api/modules/Technology/Domain/TechnologyGraph.php` is ≤ 1 (only DomainException, if imported) — the class is framework-free
    - `grep -cE "\b(config|now|app|DB)\(" apps/api/modules/Technology/Domain/TechnologyGraph.php` is 0
    - `grep -c "function tier" apps/api/modules/Technology/Domain/TechnologyGraph.php` is ≥ 1
    - `grep -c "function prerequisites" apps/api/modules/Technology/Domain/TechnologyGraph.php` is 1
    - `docker compose exec -T api ./vendor/bin/pest --filter=TechnologyGraph` reports 6 passing tests
    - `docker compose exec -T api ./vendor/bin/phpstan analyse --memory-limit=1G` — 0 errors
  </acceptance_criteria>

  <verify>
    <automated>docker compose exec -T api ./vendor/bin/pest --filter=TechnologyGraph</automated>
  </verify>

  <done>
    Every technology has a tier the tree layout can group by, and a prerequisite list
    that does not vanish when the technology is maxed.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 4: Completion — raising the level exactly once</name>

  <read_first>
    - apps/api/modules/Construction/Application/ConstructionCompletionService.php (the whereNull guard, the per-order loop, the broadcast — the template)
    - apps/api/modules/City/Interface/Broadcasting/CityStateChanged.php (the event to dispatch and its constructor)
    - apps/api/modules/Technology/Infrastructure/ResearchOrder.php (as Task 1 leaves it)
    - apps/api/tests/Feature/Construction/ConstructionCompletionTest.php (the service-direct idempotency test 09-03 had to add, and why)
  </read_first>

  <files>
    apps/api/modules/Technology/Application/ResearchCompletionService.php,
    apps/api/tests/Feature/Technology/EffectResolverTest.php
  </files>

  <behavior>
    - `completeOverdueLocked(Player $player, DateTimeImmutable $now)` completes every overdue open research for that player
    - Completing upserts `player_technologies` to `target_level` and stamps `completed_at`
    - Calling it twice completes once — guarded on `completed_at IS NULL`
    - One broadcast per completed order, not one per call
  </behavior>

  <action>
**4a. `ResearchCompletionService`** — mirror `ConstructionCompletionService` exactly. Read
it first; select `research_orders` where `finishes_at <= $now` AND `completed_at IS NULL`,
`lockForUpdate`, then per order upsert `player_technologies` to `target_level`, stamp
`completed_at`, and dispatch the state-changed broadcast.

For the broadcast: read how Phase 07/09 dispatch `CityStateChanged` and follow it,
using the order's `city_id`. A player-scoped channel does not exist yet; note in the
SUMMARY that a player-level channel is Phase 33's concern and that using the city
channel is the deliberate interim, not an oversight.

This service lives in this plan rather than with the research *start* logic because
`ResearchService::start()` (10-05) must call it before deciding whether a player is
already busy — a player whose research finished but whose job has not run yet must not
be told `RESEARCH_IN_PROGRESS`. Putting completion in the earlier wave makes that
dependency a real one rather than a circular one.

**4b. `EffectResolverTest.php`** — the arithmetic Task 2 specified, pinned:
- *"applies add before multiply"* — baseline 100, an `add` of 50 and a `multiply` of
  1100; assert 165 (`(100+50) * 1100 / 1000`), not 160 (`100*1100/1000 + 50`).
- *"stacks two multipliers additively, not multiplicatively"* — two 1100 effects on a
  baseline of 100; assert 120, and assert explicitly it is **not** 121.
- *"truncates downward"* — baseline 10 with 1105; assert 11.
- *"treats 1000 as the identity"* — assert an unchanged value.
- *"creates a target the baseline did not seed"* — an `add` to `march.speed` (a target
  10-01 authors but nothing reads yet) appears in the output rather than being dropped,
  proving the `array_key_exists` guard really was removed.
- *"leaves an empty effect set equal to the baseline"*.
  </action>

  <acceptance_criteria>
    - `grep -c "whereNull('completed_at')" apps/api/modules/Technology/Application/ResearchCompletionService.php` is ≥ 1
    - `grep -c "lockForUpdate" apps/api/modules/Technology/Application/ResearchCompletionService.php` is ≥ 1
    - `grep -c "world_id" apps/api/modules/Technology/Application/ResearchCompletionService.php` is ≥ 1
    - `docker compose exec -T api ./vendor/bin/pest --filter=EffectResolver` reports 6 passing tests
    - `docker compose exec -T api ./vendor/bin/pest` fully green
    - `docker compose exec -T api ./vendor/bin/phpstan analyse --memory-limit=1G` — 0 errors; `pint --test` clean
  </acceptance_criteria>

  <verify>
    <automated>docker compose exec -T api ./vendor/bin/pest --filter=EffectResolver &amp;&amp; docker compose exec -T api ./vendor/bin/pest</automated>
  </verify>

  <done>
    Completion raises a technology's level exactly once, and the resolver's add-then-
    multiply permille arithmetic is pinned including the non-obvious cases.
  </done>
</task>


</tasks>

<verification>
- `docker compose exec -T api php artisan migrate:fresh --seed` — exits 0
- `docker compose exec -T api ./vendor/bin/pest` — green, ≥ 12 new tests
- `docker compose exec -T api ./vendor/bin/pest --filter=Economy` — green with UNCHANGED expected numbers for an unresearched empire (the resolver refactor must not shift a baseline)
- `docker compose exec -T api ./vendor/bin/phpstan analyse --memory-limit=1G` — 0 errors
- `docker compose exec -T api ./vendor/bin/pint --test` — clean
- `grep -rn "effectsForBuildings" apps/api/modules` — every caller still compiles; the old signature survives
</verification>
