---
phase: 09-buildings-construction
plan: 04
type: execute
wave: 2
depends_on: ["09-01", "09-02"]
files_modified:
  - apps/api/modules/Construction/Domain/BuildingRequirement.php
  - apps/api/modules/Construction/Application/BuildingRequirementEvaluator.php
  - apps/api/modules/Construction/Application/BuildingUpgradeService.php
  - apps/api/tests/Feature/Construction/BuildingRequirementsTest.php
  - apps/api/tests/Architecture/ArchitectureTest.php
autonomous: true
requirements: [REQ-05, REQ-06]

must_haves:
  truths:
    - "An upgrade whose data-declared requirements are unmet returns BUILDING_REQUIREMENTS_NOT_MET and names what is missing"
    - "No building can be raised above the Palace's current level, and the rule is read from game data rather than written as a Palace branch in PHP"
    - "A refused upgrade debits nothing, writes no ledger row and creates no construction order"
    - "Requirements are re-evaluated inside the same lock that debits the cost, so a concurrently-demolished prerequisite cannot be raced past"
    - "A building whose prerequisites are met upgrades exactly as before"
  artifacts:
    - path: "apps/api/modules/Construction/Application/BuildingRequirementEvaluator.php"
      provides: "Generic evaluation of a level's requirements[] against the city's current building levels"
      contains: "public function unmet"
    - path: "apps/api/modules/Construction/Domain/BuildingRequirement.php"
      provides: "The typed requirement value object parsed out of game data"
      contains: "final readonly class BuildingRequirement"
    - path: "apps/api/tests/Feature/Construction/BuildingRequirementsTest.php"
      provides: "Proof of the Palace gate, the error payload and the no-side-effect guarantee"
  key_links:
    - from: "apps/api/modules/Construction/Application/BuildingUpgradeService.php"
      to: "BuildingRequirementEvaluator"
      via: "constructor injection, called inside the locked transaction"
      pattern: "requirements->unmet\\("
    - from: "BuildingRequirementEvaluator"
      to: "packages/game-data/data/buildings.json"
      via: "GameDataCatalog::buildingLevel()['requirements']"
      pattern: "buildingLevel"
---

<objective>
Make the `requirements[]` arrays 09-01 authored actually gate an upgrade, so
`BUILDING_REQUIREMENTS_NOT_MET` stops being a code path only reachable when a
building is absent from the catalogue and becomes the real answer to "you cannot
build this yet."

Purpose: `docs/game-design/buildings.md` § The Palace gate is the spine of
progression — *"No building may exceed the Palace level"* — and REQ-06 requires it
to be data, not a `if ($code === 'palace')` branch. A generic evaluator also gives
Phase 10 (technology prerequisites) and Phase 12 (training buildings) their
unlock rule for free.
Output: a typed `BuildingRequirement`, a `BuildingRequirementEvaluator`, one new
check inside `BuildingUpgradeService::start()`'s existing lock, and the tests that
prove it refuses without side effects.
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
@.planning/codebase/ARCHITECTURE.md
@.planning/codebase/CONVENTIONS.md
@.planning/codebase/TESTING.md
@docs/game-design/buildings.md
@.planning/phases/09-buildings-construction/09-01-SUMMARY.md

<interfaces>
<!-- Everything the executor needs to write this without exploring. -->

`Game\Shared\Infrastructure\GameData\GameDataCatalog`:

```php
public function building(string $code): ?array;               // ['code','name_key','category','max_level','levels']
public function buildingLevel(string $code, int $level): ?array; // ['level','cost','build_time_seconds','requirements','effects']
```

A requirement as authored by 09-01 in `packages/game-data/data/buildings.json`:

```json
{ "type": "building", "code": "palace", "level": 2 }
```

`type` is one of `building | technology | nobility | player_level`
(`packages/game-data/src/index.ts` → `Requirement`). Only `building` is
satisfiable in Phase 09; the other three have no owning subsystem yet.

`Game\Shared\Application\Error\ErrorCode::BuildingRequirementsNotMet`
= `'BUILDING_REQUIREMENTS_NOT_MET'`, and `httpStatus()` routes it through
`default => 400`. It is already in the `ErrorCode` enum and in
`packages/contracts/openapi.yaml`'s `ErrorCode` enum — no contract change needed.

`Game\Shared\Application\Error\GameException::of(ErrorCode $code, string $message, array $details = [])`.

`Game\City\Infrastructure\CityBuilding` — `$fillable = ['world_id','city_id','slot','building_code','level']`,
`level` cast to integer.

`BuildingUpgradeService::start()` today, in order, all inside one
`DB::transaction` with the city held by `lockForUpdate`:

1. resolve player + city (`CITY_NOT_OWNED`)
2. `completeOverdueLocked()` then `accrueLocked()`
3. lock the `CityBuilding` row; missing row or missing catalogue entry → `BUILDING_REQUIREMENTS_NOT_MET`
4. `$targetLevel > $maxLevel` → `BUILDING_MAX_LEVEL`
5. open orders `>= max_build_queue_slots` → `BUILD_QUEUE_FULL`
6. an open order on this same building → `CITY_BUSY`
7. `buildingLevel($code, $targetLevel)` missing → `BUILDING_REQUIREMENTS_NOT_MET`
8. affordability → `INSUFFICIENT_RESOURCES`
9. `debitLocked(...)`, compute `finishes_at` via `BuildDuration::scaled(...)`,
   create the `ConstructionOrder`, dispatch `CompleteConstruction` when the queue
   is not `sync`
</interfaces>
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: A typed requirement and a generic evaluator</name>

  <read_first>
    - packages/game-data/data/buildings.json (the `requirements` arrays 09-01 authored, including the Palace gate)
    - apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php (`buildingLevel()` return shape)
    - apps/api/modules/Construction/Domain/BuildDuration.php (the framework-free domain style 09-02 established in this module)
    - apps/api/tests/Architecture/ArchitectureTest.php (`domain layer stays free of the framework` — `Game\Construction\Domain` is not in the ignore list)
    - .planning/codebase/CONVENTIONS.md (§ PHP — final, readonly, static factories, named constructors)
  </read_first>

  <files>
    apps/api/modules/Construction/Domain/BuildingRequirement.php,
    apps/api/modules/Construction/Application/BuildingRequirementEvaluator.php
  </files>

  <behavior>
    - `BuildingRequirement::fromArray(['type'=>'building','code'=>'palace','level'=>2])` yields a requirement with those three values
    - A malformed entry (missing `code`, non-integer `level`, unknown `type`) yields `null` from `fromArray` rather than throwing — a bad dataset is caught by `game:import-data`, and a runtime parse must not 500 a player's request
    - `unmet('farm', 2, ['palace' => 1, 'farm' => 1])` returns `['palace' => 2]` — the code mapped to the level it needed
    - `unmet('farm', 2, ['palace' => 3, 'farm' => 1])` returns `[]`
    - `unmet('farm', 2, ['farm' => 1])` returns `['palace' => 2]` — an absent building counts as level 0
    - `unmet('palace', 2, ['palace' => 1])` returns `[]` — the Palace gates nothing, including itself
    - A requirement of a type other than `building` is reported as unmet under its own code, never silently skipped
    - `unmet('farm', 99, [...])` returns `[]` — no such level in the catalogue means there is nothing to check here; `BUILDING_MAX_LEVEL` and the missing-level guard already own that case
  </behavior>

  <action>
**1a. `apps/api/modules/Construction/Domain/BuildingRequirement.php`**, namespace
`Game\Construction\Domain`. Framework-free: no `use` of anything outside the
allow-list in the architecture rule, no `config()`, no Eloquent.

```php
final readonly class BuildingRequirement
{
    private function __construct(
        public string $type,
        public string $code,
        public int $level,
    ) {}

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw): ?self
    {
        $type = $raw['type'] ?? null;
        $code = $raw['code'] ?? null;
        $level = $raw['level'] ?? null;

        // A malformed row is a dataset defect, caught by `game:import-data`. At
        // request time it must not become a 500 on a player's upgrade tap.
        if (! is_string($type) || ! is_string($code) || ! is_int($level) || $level < 1) {
            return null;
        }

        return new self($type, $code, $level);
    }

    public function isSatisfiedBy(int $currentLevel): bool
    {
        return $this->type === 'building' && $currentLevel >= $this->level;
    }
}
```

`isSatisfiedBy` returning `false` for any non-`building` type is deliberate and
must carry this comment: technology, nobility and player-level requirements have
no subsystem yet (Phases 10, 29, 04 respectively), so treating one as satisfied
would silently unlock content. The current dataset authors only `building`
requirements, so this branch is inert today — but it fails closed the day it is
not.

**1b. `apps/api/modules/Construction/Application/BuildingRequirementEvaluator.php`**,
namespace `Game\Construction\Application`, `final readonly`, constructor-injected
`GameDataCatalog`.

```php
/**
 * @param  array<string, int>  $currentLevels  building_code => level, for one city
 * @return array<string, int>                  building_code => level it needed
 */
public function unmet(string $buildingCode, int $targetLevel, array $currentLevels): array
```

Implementation: read `$this->catalog->buildingLevel($buildingCode, $targetLevel)`;
return `[]` when it is `null` or its `requirements` is not an array. Otherwise map
each entry through `BuildingRequirement::fromArray()`, drop the `null`s, and
collect `[$requirement->code => $requirement->level]` for every requirement where
`$requirement->isSatisfiedBy($currentLevels[$requirement->code] ?? 0)` is false.

Keep it a pure function of its arguments — no query, no `config()`, no clock. The
caller supplies `$currentLevels` because it already holds them inside the lock, and
a query here would read outside it.
  </action>

  <acceptance_criteria>
    - `grep -cE "^use " apps/api/modules/Construction/Domain/BuildingRequirement.php` is `0`
    - `grep -cE "\b(config|now|app|DB)\(" apps/api/modules/Construction/Domain/BuildingRequirement.php` is `0`
    - `grep -c "public function unmet" apps/api/modules/Construction/Application/BuildingRequirementEvaluator.php` is `1`
    - `grep -cE "'palace'" apps/api/modules/Construction/Application/BuildingRequirementEvaluator.php apps/api/modules/Construction/Domain/BuildingRequirement.php` is `0` — the Palace gate is data, never a branch
    - `grep -cE "\bDB::|Eloquent|CityBuilding" apps/api/modules/Construction/Application/BuildingRequirementEvaluator.php` is `0`
    - `cd apps/api && ./vendor/bin/pest --group=arch` is green
    - `cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G` reports 0 errors
  </acceptance_criteria>

  <verify>
    <automated>cd apps/api &amp;&amp; ./vendor/bin/pest --group=arch &amp;&amp; ./vendor/bin/phpstan analyse --memory-limit=1G &amp;&amp; ./vendor/bin/pint --test</automated>
  </verify>

  <done>
    A generic, framework-free requirement evaluator exists; it names the Palace
    nowhere in its source, fails closed on requirement types no subsystem
    implements yet, and passes level 8 static analysis.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Gate the upgrade inside the existing lock and prove it costs nothing to be refused</name>

  <read_first>
    - apps/api/modules/Construction/Application/BuildingUpgradeService.php (the whole `start()` method — the check order listed in this plan's `<interfaces>` block must be preserved except for the one insertion)
    - apps/api/modules/Economy/Application/CityEconomyService.php (`debitLocked` — nothing may be spent before the new check)
    - apps/api/tests/Feature/Economy/EconomyConcurrencyTest.php (the ledger-reconciliation assertions to imitate when proving "a refusal writes nothing")
    - apps/api/tests/Pest.php (`freezeClock`, `toBeApiError`)
    - packages/game-data/data/starter.json (the starter city: palace level 3, four other buildings at level 1)
  </read_first>

  <files>
    apps/api/modules/Construction/Application/BuildingUpgradeService.php,
    apps/api/tests/Feature/Construction/BuildingRequirementsTest.php
  </files>

  <behavior>
    - Palace at level 1, farm at level 1: `POST /game/city/buildings/farm/upgrade` returns `BUILDING_REQUIREMENTS_NOT_MET` with HTTP 400 and `error.details.missing` equal to `{"palace": 2}`
    - Palace at level 3, farm at level 1: the same request returns 201
    - Palace at level 2, farm at level 2: upgrading farm to level 3 returns `BUILDING_REQUIREMENTS_NOT_MET` with `missing` `{"palace": 3}`
    - A refused upgrade leaves the city's five balances byte-identical, writes zero `economy_ledger` rows and creates zero `construction_orders` rows
    - `BUILDING_MAX_LEVEL` still wins over a requirement failure: a maxed building with an unmet requirement reports `BUILDING_MAX_LEVEL`
    - `BUILD_QUEUE_FULL` is still evaluated after requirements: a city whose queue is full and whose requirement is unmet reports `BUILDING_REQUIREMENTS_NOT_MET`
  </behavior>

  <action>
**2a. `BuildingUpgradeService`.** Add `private BuildingRequirementEvaluator $requirements`
to the promoted constructor. Insert the new check **between step 4
(`BUILDING_MAX_LEVEL`) and step 5 (`BUILD_QUEUE_FULL`)** of the existing order:

```php
// Requirements are read inside the same lock that will spend the cost. A
// prerequisite that was true when the client rendered its sheet may not be
// true now — the lock, not the client's snapshot, decides.
/** @var array<string, int> $currentLevels */
$currentLevels = CityBuilding::query()
    ->where('world_id', $worldId)
    ->where('city_id', $city->getKey())
    ->pluck('level', 'building_code')
    ->map(static fn (mixed $level): int => (int) $level)
    ->all();

$unmet = $this->requirements->unmet($buildingCode, $targetLevel, $currentLevels);
if ($unmet !== []) {
    throw GameException::of(
        ErrorCode::BuildingRequirementsNotMet,
        'This upgrade is not available yet.',
        ['missing' => $unmet],
    );
}
```

The position is load-bearing and must be stated in the SUMMARY: *"cannot ever be
built"* answers (`BUILDING_MAX_LEVEL`, then requirements) come before *"cannot be
built right now"* answers (`BUILD_QUEUE_FULL`, `CITY_BUSY`,
`INSUFFICIENT_RESOURCES`), so a player is told the permanent reason rather than a
temporary one that will still not help when it clears. Do not reorder anything
else — 09-03 asserts `BUILD_QUEUE_FULL` fires ahead of `INSUFFICIENT_RESOURCES`,
which depends on the existing ordering.

The `CityBuilding` rows are read inside the transaction that already holds the
city with `lockForUpdate`, and every mutation of a `CityBuilding` in this codebase
(`ConstructionCompletionService::completeOverdueLocked`) acquires the city lock
first, so this read cannot observe a torn state. Say so in the code comment; do
not add a second `lockForUpdate` over the whole building table.

**2b. `apps/api/tests/Feature/Construction/BuildingRequirementsTest.php`** — create
the directory. Each test does `freezeClock('2026-09-07T09:00:00+00:00')`, a guest
sign-up with an `Idempotency-Key`, `POST /game/bootstrap`, then manipulates
`CityBuilding` levels directly through the model (a test fixture adjusting a
building's *level* is not a resource mutation, so the Phase 08 rule about routing
fixtures through `debitLocked` does not apply here — say so in a comment so the
next reader does not "fix" it).

Tests, one per bullet of `<behavior>`:

1. *"refuses an upgrade past the palace level and names the palace"* — set the
   palace row to `level = 1`, then `POST /game/city/buildings/farm/upgrade` and
   `expect($response)->toBeApiError(ErrorCode::BuildingRequirementsNotMet)`, plus
   `assertJsonPath('error.details.missing.palace', 2)`.
2. *"allows the same upgrade once the palace is high enough"* — leave the starter
   palace at 3, assert 201 and one `ConstructionOrder`.
3. *"gates each level independently"* — set palace to 2, upgrade farm to 2 (201),
   advance the clock past `finishes_at`, read `/game/city` so the order completes,
   then attempt farm again and assert `missing.palace` is `3`.
4. *"spends nothing when it refuses"* — palace at 1; capture the city's five
   balances and the `economy_ledger` row count; attempt the farm upgrade; assert
   the balances are identical, the ledger count is unchanged and
   `ConstructionOrder::query()->count()` is `0`.
5. *"reports the permanent reason before the temporary one"* — palace at 1 and the
   farm already at its max level (`level = 3`): assert `BUILDING_MAX_LEVEL`, not
   `BUILDING_REQUIREMENTS_NOT_MET`. Then a second case: palace at 1,
   `config(['game.limits.max_build_queue_slots' => 1])`, one open order already on
   `quarry`; attempt `farm` and assert `BUILDING_REQUIREMENTS_NOT_MET`, not
   `BUILD_QUEUE_FULL`.

Every assertion is on `error.code` and `error.details`, never on the message
(`.planning/codebase/TESTING.md`).

**2c. Guard the rule.** Append one architecture test to
`apps/api/tests/Architecture/ArchitectureTest.php`:

```php
it('gates buildings from data, never from a hardcoded prerequisite', function (): void {
    // docs/game-design/buildings.md § The Palace gate is a data rule (ADR-013).
    // A string comparison against a building code inside the Construction module
    // would move progression back into PHP where designers cannot review it.
    $offenders = [];
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('modules/Construction'), RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = (string) file_get_contents((string) $file->getPathname());
        if (preg_match('/[\'"](palace|barracks|academy|warehouse|walls)[\'"]/', $contents) === 1) {
            $offenders[] = (string) $file->getPathname();
        }
    }

    expect($offenders)->toBe([]);
})->group('arch');
```

Confirm it is green before committing, and prove it bites by temporarily adding
`$isPalace = $buildingCode === 'palace';` to `BuildingUpgradeService` and watching
it fail. Remove the line.
  </action>

  <acceptance_criteria>
    - `grep -c "requirements->unmet(" apps/api/modules/Construction/Application/BuildingUpgradeService.php` is `1`
    - The insertion is between the max-level and queue-full checks: `grep -n "BuildingMaxLevel\|requirements->unmet\|BuildQueueFull" apps/api/modules/Construction/Application/BuildingUpgradeService.php` lists them in exactly that order
    - `grep -cE "'(palace|barracks|academy|warehouse|walls)'" apps/api/modules/Construction/` (recursive, PHP files) is `0`
    - `grep -c "gates buildings from data" apps/api/tests/Architecture/ArchitectureTest.php` is `1`
    - `cd apps/api && ./vendor/bin/pest --filter=BuildingRequirements` reports 6 passing tests
    - `cd apps/api && ./vendor/bin/pest` is green with no pre-existing test changed (`git diff --stat apps/api/tests/Feature/Mvp apps/api/tests/Feature/Economy` prints nothing)
    - The SUMMARY records the temporary `$buildingCode === 'palace'` run failing the new architecture test, and `git status --porcelain apps/api/modules` prints nothing afterwards
  </acceptance_criteria>

  <verify>
    <automated>cd apps/api &amp;&amp; ./vendor/bin/pest &amp;&amp; ./vendor/bin/phpstan analyse --memory-limit=1G &amp;&amp; ./vendor/bin/pint --test</automated>
  </verify>

  <done>
    An upgrade whose data-declared prerequisites are unmet returns
    `BUILDING_REQUIREMENTS_NOT_MET` with the missing code and level, refuses before
    spending anything, and yields to `BUILDING_MAX_LEVEL`; no building code appears
    as a literal anywhere in the Construction module.
  </done>
</task>

</tasks>

<verification>
- `cd apps/api && ./vendor/bin/pest` — green, including the untouched Phase 07/08 suites
- `cd apps/api && ./vendor/bin/pest --group=arch` — green, two more rules than before this plan
- `cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G` — 0 errors
- `cd apps/api && ./vendor/bin/pint --test` — clean
- `npm run typecheck && npm run lint && npm test` — green (no client change in this plan)
</verification>

<success_criteria>
- The Palace gate from `docs/game-design/buildings.md` is enforced and comes
  entirely from `packages/game-data/data/buildings.json`
- `BUILDING_REQUIREMENTS_NOT_MET` returns HTTP 400 with
  `error.details.missing = {building_code: required_level}`
- A refused upgrade mutates no balance, writes no ledger row and creates no order
- Requirements are evaluated inside the same locked transaction that spends the cost
- A hardcoded building code inside `apps/api/modules/Construction/` fails the
  architecture suite
</success_criteria>

<output>
After completion, create `.planning/phases/09-buildings-construction/09-04-SUMMARY.md`
</output>
</content>
