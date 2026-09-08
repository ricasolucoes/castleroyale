---
phase: 10-technology-research
plan: 05
type: execute
wave: 3
depends_on: ["10-01", "10-03"]
files_modified:
  - apps/api/modules/Technology/Application/ResearchService.php
  - apps/api/modules/Technology/Application/ResearchReconciler.php
  - apps/api/modules/Technology/Interface/Jobs/CompleteResearch.php
  - apps/api/modules/Technology/Interface/Http/ResearchController.php
  - apps/api/modules/Technology/Interface/Http/TechnologyTreeController.php
  - apps/api/modules/Construction/Application/BuildingRequirementEvaluator.php
  - apps/api/routes/api.php
  - apps/api/routes/console.php
  - packages/contracts/openapi.yaml
  - packages/contracts/src/generated/api.ts
  - apps/api/tests/Feature/Technology/ResearchQueueTest.php
  - apps/api/tests/Feature/Technology/ResearchCompletionTest.php
  - apps/api/tests/Feature/Technology/ResearchEffectTest.php
autonomous: true
requirements: [REQ-05, REQ-06, REQ-09]

must_haves:
  truths:
    - "Starting a research debits resources atomically inside a lock and writes started_at and finishes_at in UTC from the injected Clock"
    - "A second concurrent research returns RESEARCH_IN_PROGRESS; a locked technology returns TECHNOLOGY_LOCKED; a maxed one returns TECHNOLOGY_MAX_LEVEL — all at HTTP 400"
    - "A completed technology's effect is observable in a recomputed value: production rate rises by the documented permille amount"
    - "A dead worker costs nothing — the reconciler finishes every overdue research exactly once"
    - "GET /game/technologies serves every technology with its tier and its prerequisites, both present regardless of the player's level"
  artifacts:
    - path: "apps/api/tests/Feature/Technology/ResearchEffectTest.php"
      provides: "ROADMAP criterion 4 — the effect observable in a recomputed rate, not merely a row"
      contains: "ratesPerHour"
    - path: "apps/api/tests/Feature/Technology/ResearchQueueTest.php"
      provides: "ROADMAP criteria 2 and 3 — the three refusal codes and their precedence"
      contains: "ResearchInProgress"
    - path: "apps/api/modules/Technology/Interface/Http/TechnologyTreeController.php"
      provides: "ROADMAP criterion 5's data — server-computed state and tier per technology"
      contains: "tier"
  key_links:
    - from: "apps/api/modules/Technology/Interface/Http/TechnologyTreeController.php"
      to: "Game\\Technology\\Domain\\TechnologyGraph"
      via: "the controller serves the graph's tier and prerequisites"
      pattern: "TechnologyGraph"
    - from: "apps/api/modules/Technology/Application/ResearchReconciler.php"
      to: "Game\\Technology\\Application\\ResearchCompletionService"
      via: "run() calls completeOverdueLocked per player"
      pattern: "completeOverdueLocked"
---

<objective>
The research command, the tree endpoint, the reconciler, and the proof that a finished
technology actually changes the empire.

This is where ROADMAP criteria 2, 3 and 4 are earned. Criterion 4 is the one that can
quietly fail: CONTEXT.md is explicit that "a completed technology's effect must be
observable in a recomputed value — test it, do not just assert the row exists."

10-03 already built the persistence, the effect resolver, the tier graph and the
completion service. This plan makes them reachable over HTTP and proves them.
</objective>

<context>

<interfaces>
From 10-03, verify each exists before depending on it:

```php
// Game\Technology\Domain\TechnologyGraph — pure, constructed from the catalogue array
public function tier(string $code): int;                                  // topological depth
public function prerequisites(string $code): array;                       // [{code, level}], survives max level
public function tiers(): array;                                           // code => tier

// Game\Technology\Domain\EffectResolver
public static function resolve(array $baseline, array $effectSets): array;  // add, then multiply by permille

// Game\Technology\Application\ResearchCompletionService
public function completeOverdueLocked(Player $player, DateTimeImmutable $now): void;
```

From Phase 09, the templates to copy rather than re-derive:

```php
// Game\Construction\Application\BuildingUpgradeService::start()
// the transaction, the lockForUpdate, the check order, the debitLocked, the order write,
// the `config('queue.default') !== 'sync'` dispatch guard. READ IT IN FULL.

// Game\Construction\Application\BuildingRequirementEvaluator
public function unmet(string $buildingCode, int $targetLevel, array $currentLevels): array;
// 09-04's SUMMARY says technology prerequisites were an explicit intended reuse.
// Currently hardcoded to buildingLevel(). Generalise; do not clone.

// Game\Construction\Domain\BuildDuration
public static function scaled(int $rawSeconds, int $timeScale): int;

// Game\Construction\Application\ConstructionReconciler::run(): int
// Game\Construction\Interface\Jobs\CompleteConstruction — note its OWN orderExists guard
```

`ErrorCode` already contains `ResearchInProgress`, `TechnologyLocked` and
`TechnologyMaxLevel`, and `openapi.yaml` already lists all three — **verified by the plan
checker against the real files**. Do not add them again; confirm with grep and move on.

Test fixtures: `freezeClock(string $iso): FrozenClock` with `advanceSeconds(int)`,
`toBeApiSuccess()`, `toBeApiError(ErrorCode, ?int)`. `phpunit.xml` forces
`QUEUE_CONNECTION=sync`, so a test wanting the job must set
`config(['queue.default' => 'redis'])` with `Queue::fake()`.
</interfaces>

<the_response_contract>
**This shape was corrected after the plan checker found a blocker.** The original draft
omitted `tier` entirely — the field `10-UI-SPEC.md` calls "the single most load-bearing
field in this contract" and on which the whole approved layout depends — and nested
prerequisites only inside `next_level`, which is `null` at max level and would have made
a completed technology lose its own prerequisite data. Both are fixed here. Serve
exactly this:

```
GET /game/technologies
data.technologies[]: {
  code, name_key, description_key, category,
  tier,                       // integer, from TechnologyGraph — the layout groups lanes by this
  prerequisites: [{ code, level }],   // ALWAYS present, never null, even at max level
  level, max_level,
  state: "locked" | "available" | "in_progress" | "completed",
  next_level: { cost, research_time_seconds, effects } | null   // null only at max level
}
data.research: { technology_code, target_level, started_at, finishes_at } | null
data.server_time
```

`prerequisites` sits at the technology level, not inside `next_level`, precisely because
it must survive `next_level === null`. `state` is computed server-side; the client
renders it and must never re-derive it.

**Endpoint path.** Use `/game/technologies` (plural) for both the collection GET and the
POST. `10-UI-SPEC.md` writes `/game/technology` (singular) in its Data Contract section;
that is superseded here. The UI-SPEC governs visual and interaction decisions, not a URL
— and 10-04 is being updated to match this plural form, so the two agree.
</the_response_contract>

<canonical_refs>
- `docs/game-design/technology.md` § The tree — the three error codes and one-at-a-time rule.
- `docs/adr/006-server-time.md` — the injected Clock.
- `.planning/phases/09-buildings-construction/09-03-SUMMARY.md` — the worker-death test shape and the falsification lesson.
</canonical_refs>

<trap>
**The read path completes orders.** If a service completes overdue work before doing
anything else, a test that advances the clock and then calls an HTTP endpoint has already
completed the research — so the job and the reconciler correctly find nothing and the test
passes while proving nothing. **Never touch an HTTP endpoint between advancing the clock
and the assertion under test.** Read state with Eloquent.

**Falsification must target the right guard.** 09-03's plan asked to prove the completion
guard by removing `whereNull('completed_at')` from the *service* and watching a *job* test
fail — it did not fail, because the job short-circuits on its own guard first. Remove the
guard on the exact path the test exercises; if it does not fail, say so in the SUMMARY and
add the test that does.
</trap>
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Starting a research — the locked spend, the three refusals, the timers</name>

  <read_first>
    - apps/api/modules/Construction/Application/BuildingUpgradeService.php (the ENTIRE start() method — this is the template)
    - apps/api/modules/Construction/Application/BuildingRequirementEvaluator.php (the generic evaluator to extend, not clone)
    - apps/api/modules/Construction/Interface/Http/BuildingUpgradeController.php (controller thinness, idempotency-key handling, response shape)
    - apps/api/modules/Technology/Application/ResearchCompletionService.php (from 10-03 — start() must call it first)
    - apps/api/modules/Shared/Application/Error/ErrorCode.php (confirm the three codes exist)
    - apps/api/tests/Feature/Construction/ConstructionQueueLimitTest.php (the uniquely-named local helper convention — a duplicated top-level function name across two test files in one directory is a fatal redeclare, which bit Phase 09)
  </read_first>

  <files>
    apps/api/modules/Technology/Application/ResearchService.php,
    apps/api/modules/Construction/Application/BuildingRequirementEvaluator.php,
    apps/api/modules/Technology/Interface/Http/ResearchController.php,
    apps/api/routes/api.php,
    apps/api/tests/Feature/Technology/ResearchQueueTest.php
  </files>

  <behavior>
    - `POST /game/technologies/{code}/research` debits the city atomically inside a lock and writes an order whose timestamps come from the injected Clock in UTC
    - A second research while one is open returns `RESEARCH_IN_PROGRESS` at HTTP 400 and spends nothing
    - Unmet prerequisites return `TECHNOLOGY_LOCKED` at HTTP 400, naming what is missing
    - A technology already at `max_level` returns `TECHNOLOGY_MAX_LEVEL` at HTTP 400
    - Refusal precedence is fixed and tested: max level, then locked, then in progress, then insufficient resources
    - A request body carrying its own cost, duration or target level changes nothing
  </behavior>

  <action>
**1a. Generalise `BuildingRequirementEvaluator`.** Add a method rather than changing the
existing signature — 09-04's `BuildingRequirementsTest` (6 tests) depends on `unmet()` as
it stands:

```php
/**
 * @param array<string,int> $currentBuildingLevels
 * @param array<string,int> $currentTechnologyLevels
 * @return array<string,array{type:string,level:int}> requirement code => what it needed
 */
public function unmetFor(string $type, string $code, int $targetLevel,
                         array $currentBuildingLevels, array $currentTechnologyLevels): array
```

`$type` is `'building'` or `'technology'` and selects which catalogue method supplies the
`requirements[]`. Each requirement is checked against the map matching its **own** type,
so a technology may require a building and vice versa. Types with no map yet (`nobility`,
`player_level`) are skipped with an explicit `continue` and a comment — treating them as
satisfied is correct today but must be deliberate, not accidental.

Reimplement `unmet()` as a call to `unmetFor('building', ...)` so there is one code path,
then confirm `BuildingRequirementsTest` still reports 6 passing, untouched.

**1b. `ResearchService::start()`** — model on `BuildingUpgradeService::start()`, read in
full first. Inside one `DB::transaction`, in this exact order:

1. Lock the city, resolve the owning player, `CITY_NOT_OWNED` if the account does not own
   it — copy the existing check verbatim.
2. `ResearchCompletionService::completeOverdueLocked($player, $clock->now())` — otherwise
   a player whose research finished but whose job has not run is wrongly told
   `RESEARCH_IN_PROGRESS`.
3. Read the player's current technology levels, scoped by `world_id`.
4. `TECHNOLOGY_MAX_LEVEL` if `currentLevel >= max_level`.
5. `TECHNOLOGY_LOCKED` if `unmetFor('technology', ...)` returns anything, with the unmet
   map in `details['missing']`, exactly as 09-04 does for `BUILDING_REQUIREMENTS_NOT_MET`.
6. `RESEARCH_IN_PROGRESS` if an open `research_orders` row exists for this player.
7. Recompute the cost from the catalogue and `debitLocked` it — `INSUFFICIENT_RESOURCES`
   propagates from there. Reason `'technology.research'`, reference the idempotency key.
8. Write the `ResearchOrder` with
   `BuildDuration::scaled($level['research_time_seconds'], (int) config('game.time_scale'))`.
9. Dispatch `CompleteResearch` on the `gameplay` queue delayed to `finishes_at`
   `->afterCommit()`, guarded by `config('queue.default') !== 'sync'` — copy the guard
   verbatim.

**Precedence is a decision, not an accident.** The order tells a player the permanent
reason (maxed) before the structural one (locked) before the transient one (in progress)
before the economic one (cannot afford). Assert all four boundaries.

**1c. `ResearchController`** — invokable and thin; read `BuildingUpgradeController` and
match it exactly, including how it reads the `Idempotency-Key` header.

**1d. Route** in `apps/api/routes/api.php`, in the existing authenticated group:
`POST /game/technologies/{code}/research` → `ResearchController`.

**1e. `ResearchQueueTest.php`** — guest → bootstrap → act. Use a uniquely-named local
helper (e.g. `enterCityForResearch`); PHPUnit loads every `*Test.php` in one process, so a
name shared with a sibling file is a fatal redeclare.

- *"starts a research, debiting the city and stamping the injected clock in UTC"* — assert
  `+00:00` on both timestamps, the debit matches the catalogue, and the ledger rows carry
  the idempotency key as reference.
- *"refuses a second concurrent research with RESEARCH_IN_PROGRESS"* — and that it spent
  nothing and created no second order.
- *"refuses a locked technology and names the missing prerequisite"*.
- *"refuses a maxed technology with TECHNOLOGY_MAX_LEVEL"*.
- *"reports TECHNOLOGY_MAX_LEVEL over an unmet prerequisite"*.
- *"reports TECHNOLOGY_LOCKED over RESEARCH_IN_PROGRESS"*.
- *"ignores a client-supplied cost, duration and target level"* — post
  `research_time_seconds: 0`, `cost: {}`, `target_level: 99`; assert the persisted order
  and the debit are the catalogue's.
  </action>

  <acceptance_criteria>
    - `grep -c "function unmetFor" apps/api/modules/Construction/Application/BuildingRequirementEvaluator.php` is 1
    - `docker compose exec -T api ./vendor/bin/pest --filter=BuildingRequirements` still reports 6 passing — 09-04 unbroken
    - `grep -c "ResearchInProgress" apps/api/tests/Feature/Technology/ResearchQueueTest.php` is ≥ 2
    - `grep -c "TechnologyLocked" apps/api/tests/Feature/Technology/ResearchQueueTest.php` is ≥ 2
    - `grep -c "TechnologyMaxLevel" apps/api/tests/Feature/Technology/ResearchQueueTest.php` is ≥ 2
    - `grep -c "assertStatus(400)" apps/api/tests/Feature/Technology/ResearchQueueTest.php` is ≥ 3
    - `grep -c "+00:00" apps/api/tests/Feature/Technology/ResearchQueueTest.php` is ≥ 2
    - `grep -cE "\bCarbon::now" apps/api/tests/Feature/Technology/ResearchQueueTest.php` is 0 — the frozen clock only
    - `grep -c "BuildDuration::scaled" apps/api/modules/Technology/Application/ResearchService.php` is 1 — shared, not reimplemented
    - `docker compose exec -T api ./vendor/bin/pest --filter=ResearchQueue` reports 7 passing tests
    - `phpstan` 0 errors; `pint --test` clean
  </acceptance_criteria>

  <verify>
    <automated>docker compose exec -T api ./vendor/bin/pest --filter=ResearchQueue</automated>
  </verify>

  <done>
    Research costs resources, takes server-controlled time, refuses for three distinct
    reasons in a fixed and tested precedence, and cannot be influenced by the request body.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: The tree endpoint — tier, prerequisites and server-computed state</name>

  <read_first>
    - apps/api/modules/Technology/Domain/TechnologyGraph.php (from 10-03 — tier() and prerequisites())
    - apps/api/modules/City/Application/CityStateService.php (how a read model is assembled and how server_time is emitted)
    - packages/contracts/openapi.yaml (where a schema goes and the existing error enum)
    - .planning/phases/10-technology-research/10-UI-SPEC.md § Data Contract (what the client needs)
  </read_first>

  <files>
    apps/api/modules/Technology/Interface/Http/TechnologyTreeController.php,
    apps/api/routes/api.php,
    packages/contracts/openapi.yaml,
    packages/contracts/src/generated/api.ts
  </files>

  <behavior>
    - `GET /game/technologies` returns every technology with `tier`, `prerequisites`, `level`, `max_level`, `state` and `next_level`
    - `prerequisites` is present and correct even when `state` is `completed` and `next_level` is null
    - `state` is one of locked / available / in_progress / completed, computed server-side
    - Every query filters `world_id`
  </behavior>

  <action>
Implement the contract in `<the_response_contract>` above, exactly.

`state` computation, server-side:
- `completed` when `level >= max_level`
- `in_progress` when this technology is the player's open research
- `locked` when `unmetFor('technology', ...)` returns anything for the next level
- `available` otherwise

`tier` and `prerequisites` come from `TechnologyGraph`. Construct the graph once per
request from the catalogue array, not once per technology — it memoises internally, but
constructing it N times defeats that.

**`prerequisites` must be emitted unconditionally.** The plan checker caught that putting
it inside `next_level` would null it out at max level, breaking the client's on-card
prerequisite caption and its requires-chips for exactly the technologies a player has
finished. Emit it at the technology level.

Add both endpoints and the `Technology` / `ResearchOrder` schemas to `openapi.yaml`. The
three error codes are already in the enum — confirm with grep and do not duplicate.
Regenerate with `npm run contracts:generate`, verify with `npm run contracts:check`.

Add a test to `ResearchQueueTest.php`: *"serves the tree with tier, prerequisites and a
server-computed state"* — assert a tier-0 technology is `available` with `tier: 0`, one
behind an unmet prerequisite is `locked` with `tier >= 1`, and that a technology at max
level still reports a non-empty `prerequisites` array with `next_level: null`.
  </action>

  <acceptance_criteria>
    - `grep -c "tier" apps/api/modules/Technology/Interface/Http/TechnologyTreeController.php` is ≥ 1
    - `grep -c "prerequisites" apps/api/modules/Technology/Interface/Http/TechnologyTreeController.php` is ≥ 1
    - `grep -c "tier" packages/contracts/src/generated/api.ts` is ≥ 1 — the field reached the generated client types
    - `grep -c "prerequisites" packages/contracts/src/generated/api.ts` is ≥ 1
    - `npm run contracts:check` exits 0
    - `docker compose exec -T api ./vendor/bin/pest --filter=ResearchQueue` reports 8 passing tests
  </acceptance_criteria>

  <verify>
    <automated>npm run contracts:check &amp;&amp; docker compose exec -T api ./vendor/bin/pest --filter=ResearchQueue</automated>
  </verify>

  <done>
    The client can render tier lanes and cross-category prerequisite chips, because both
    fields exist in the contract and survive a completed technology.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: The reconciler, the job, and the effect made observable</name>

  <read_first>
    - apps/api/modules/Construction/Application/ConstructionReconciler.php (the open-world loop and what run() returns)
    - apps/api/modules/Construction/Interface/Jobs/CompleteConstruction.php (the job's own orderExists guard — it short-circuits before the service)
    - apps/api/routes/console.php (the every-minute construction-reconcile entry to mirror)
    - apps/api/tests/Feature/Construction/ConstructionReconcilerTest.php (the worker-death test shape)
    - apps/api/tests/Feature/Construction/ConstructionCompletionTest.php (both idempotency tests, including the service-direct one 09-03 had to add)
    - apps/api/modules/Economy/Application/CityEconomyService.php (ratesPerHour — the recomputed value criterion 4 needs)
  </read_first>

  <files>
    apps/api/modules/Technology/Application/ResearchReconciler.php,
    apps/api/modules/Technology/Interface/Jobs/CompleteResearch.php,
    apps/api/routes/console.php,
    apps/api/tests/Feature/Technology/ResearchCompletionTest.php,
    apps/api/tests/Feature/Technology/ResearchEffectTest.php
  </files>

  <behavior>
    - With the worker dead, one reconciler pass completes every overdue research exactly once; a second pass changes nothing; the late job changes nothing
    - Running the job twice completes once; calling the completion service twice completes once
    - After a production-multiplier technology completes, the recomputed rate rises by the documented amount
    - Two ranks stack additively on the permille surplus
  </behavior>

  <action>
**3a. `ResearchReconciler::run(): int`** — mirror `ConstructionReconciler`: iterate open
worlds, find distinct players with overdue open orders, complete per player in a
transaction, return the count.

09-03 recorded that `ConstructionReconciler` scans only open worlds, so an order in a
world closed for maintenance never completes. **Mirror that rather than diverging**, and
note in the SUMMARY that both reconcilers now share the limitation so whichever phase
fixes it fixes both. Two reconcilers behaving differently would be worse than one shared,
documented limitation.

**3b. `CompleteResearch`** — mirror `CompleteConstruction`, including its own
`whereNull('completed_at')` existence pre-check.

**3c. `routes/console.php`** — a `research-reconcile` every-minute entry mirroring
`construction-reconcile`, matching its style and comment.

**3d. `ResearchCompletionTest.php`**:
- *"completes once when the job runs twice"* — `Queue::fake()`, advance, run
  `app()->call([$job,'handle'])` twice, assert one level gain and one broadcast.
- *"completes once when the service itself is called twice"* — **not optional.** 09-03
  found the job short-circuits on its own guard, so the job test does not exercise the
  service's guard at all, and the reconciler depends on that guard.
- *"a dead worker costs nothing: the reconciler finishes the research exactly once"* —
  assert nothing completed unaided, then `run()` returns 1, a second `run()` returns 0,
  and the late job changes nothing, with exactly one broadcast across the sequence.
- *"leaves a research that is not due yet alone"*.

**Prove the guard bites.** Remove `whereNull('completed_at')` from
`ResearchCompletionService` and confirm the **service-direct** test fails. Restore it and
confirm `git status --porcelain apps/api/modules` is empty. Record in the SUMMARY which
test failed and which did not.

**3e. `ResearchEffectTest.php` — ROADMAP criterion 4, the one that matters most.**

- *"a completed production technology raises the city's rate by the documented amount"* —
  freeze the clock, guest, bootstrap, capture `data.resources.rate.food`. Read the
  multiplier from the catalogue
  (`app(GameDataCatalog::class)->technologyLevel('agriculture', 1)`) rather than
  hardcoding 1100, so a designer's rebalance does not turn this into a false failure.
  Start the research, advance past `finishes_at`, complete via the **reconciler** — not an
  HTTP call, which would complete it on the read path and prove nothing. Then assert the
  recomputed rate equals `intdiv($baseRate * $permille, 1000)` **and**, separately, that
  it is strictly greater than the base. Both matter: the equality pins the arithmetic, the
  strict inequality catches a permille of 1000 or a silently dropped effect that the
  equality alone would happily accept.
- *"two ranks stack additively, not multiplicatively"* — research to level 2, assert the
  rate matches a surplus sum and assert explicitly it is **not** the compounded value.
  Skip only if no authored technology has a production multiplier on both of its first two
  levels — and if you skip, say so in the SUMMARY.
- *"an unresearched empire's rates are unchanged"* — the regression pin that 10-03's
  resolver refactor did not shift a baseline.
  </action>

  <acceptance_criteria>
    - `grep -c "research-reconcile" apps/api/routes/console.php` is 1
    - `grep -c "ratesPerHour\|resources.rate" apps/api/tests/Feature/Technology/ResearchEffectTest.php` is ≥ 2
    - `grep -c "intdiv" apps/api/tests/Feature/Technology/ResearchEffectTest.php` is ≥ 1 — expected value computed from the catalogue, not hardcoded
    - Verify by reading that no HTTP call occurs between `advanceSeconds` and the assertion under test in either new test file
    - `docker compose exec -T api ./vendor/bin/pest --filter=ResearchCompletion` reports 4 passing
    - `docker compose exec -T api ./vendor/bin/pest --filter=ResearchEffect` reports 3 passing (2 if the stacking test was justifiably skipped)
    - `git status --porcelain apps/api/modules` is empty after the falsification experiment
    - `docker compose exec -T api ./vendor/bin/pest` fully green; `phpstan` 0 errors; `pint --test` clean
  </acceptance_criteria>

  <verify>
    <automated>docker compose exec -T api ./vendor/bin/pest --filter=Research &amp;&amp; docker compose exec -T api ./vendor/bin/pest</automated>
  </verify>

  <done>
    Research completes reliably even when the worker dies, completion is idempotent at both
    the job and the service layer, and a finished technology is observable as a higher
    production rate computed from the catalogue's own documented multiplier.
  </done>
</task>

</tasks>

<verification>
- `docker compose exec -T api ./vendor/bin/pest` — green, ≥ 15 new tests
- `docker compose exec -T api ./vendor/bin/pest --filter=BuildingRequirements` — 6 passing, 09-04 unbroken
- `docker compose exec -T api ./vendor/bin/phpstan analyse --memory-limit=1G` — 0 errors
- `docker compose exec -T api ./vendor/bin/pint --test` — clean
- `npm run contracts:check` — exits 0, and `tier` and `prerequisites` are present in the generated types
- `git status --porcelain apps/api/modules` — empty (the falsification experiment restored)
</verification>
