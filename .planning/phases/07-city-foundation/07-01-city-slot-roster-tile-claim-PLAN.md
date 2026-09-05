---
phase: 07-city-foundation
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - packages/game-data/data/city-slots.json
  - packages/game-data/data/starter.json
  - packages/game-data/src/index.ts
  - packages/game-data/src/validate.ts
  - apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php
  - apps/api/modules/Player/Application/GameBootstrapService.php
  - apps/api/database/migrations/2026_09_05_000000_map_city_building_slots_to_plot_roster.php
  - apps/api/tests/Feature/City/CitySlotRosterTest.php
  - apps/api/tests/Feature/City/CityTileClaimTest.php
autonomous: true
requirements: [REQ-01]

must_haves:
  truths:
    - "A city has a fixed, stable, addressable roster of build slots that does not depend on which buildings happen to exist"
    - "Every starter building lands in a named roster slot, and a slot never holds two buildings"
    - "Two claims on the same world tile produce exactly one city; the loser gets TILE_OCCUPIED"
    - "The TILE_OCCUPIED refusal survives the check-then-insert race because the database constraint, not the pre-check, is the authority"
  artifacts:
    - path: "packages/game-data/data/city-slots.json"
      provides: "The fixed 18-plot slot roster (balance data, never PHP)"
      contains: "plot_18"
    - path: "packages/game-data/data/starter.json"
      provides: "Explicit slot assignment for each of the 5 starter buildings"
      contains: "plot_05"
    - path: "apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php"
      provides: "citySlots() roster reader plus strict starterBuildings() slot validation"
      contains: "city-slots.json"
    - path: "apps/api/modules/Player/Application/GameBootstrapService.php"
      provides: "Constraint-backed tile claim translating a unique violation to TILE_OCCUPIED"
      contains: "cities_world_id_x_y_unique"
    - path: "apps/api/tests/Feature/City/CityTileClaimTest.php"
      provides: "Concurrency proof: pre-check passes, constraint still refuses"
      contains: "eloquent.creating"
  key_links:
    - from: "apps/api/modules/Player/Application/GameBootstrapService.php"
      to: "packages/game-data/data/city-slots.json"
      via: "GameDataCatalog::starterBuildings() validating slot against citySlots()"
      pattern: "starterBuildings"
    - from: "apps/api/modules/Player/Application/GameBootstrapService.php"
      to: "cities unique(world_id, x, y)"
      via: "QueryException catch around City::create translating to ErrorCode::TileOccupied"
      pattern: "ErrorCode::TileOccupied"
---

# Plan 07-01: City slot roster and constraint-backed tile claim

<objective>
Give a city a fixed, addressable set of build slots that exists independently of
which buildings are built, and make the one-city-per-tile rule enforced by the
database constraint rather than by a check-then-insert race.

Purpose: ROADMAP Phase 07 success criteria 1 and 2 are both currently unmet.
There is no slot roster at all (`slot` is an alias for the building code), and
`GameBootstrapService` decides the tile is free with a `SELECT` and then inserts
— two concurrent bootstraps can both pass that check.

Output: `packages/game-data/data/city-slots.json` (18 stable plots), starter
buildings pinned to named plots, a strict catalogue reader, a legacy data-fix
migration, and two Pest feature tests proving the roster and the race.
</objective>

## Context

@.planning/PROJECT.md
@.planning/ROADMAP.md
@.planning/phases/07-city-foundation/07-CONTEXT.md
@.planning/codebase/ARCHITECTURE.md
@.planning/codebase/TESTING.md

**Decisions locked in this plan (autonomous mode — recorded, not re-opened):**

1. **The roster is 18 plots, `plot_01` … `plot_18`.** `docs/game-design/buildings.md`
   documents exactly 18 buildings and `city_buildings` already carries
   `unique(world_id, city_id, building_code)` — one instance of each building per
   city. Sizing the roster to 18 means Phase 09 adds buildings by editing JSON and
   **never renumbers a slot**, which is what "slot identity is stable so the client
   can address it" (07-CONTEXT.md) actually requires.
2. **Plot ids are building-agnostic (`plot_NN`), not building names.** Which
   building may occupy which plot is a construction-placement rule and belongs to
   Phase 09. The UI copy already says "plot" (`city.slot_empty` — "This plot is
   empty"), so the vocabulary matches.
3. **Empty slots are not database rows.** The roster is data; `city_buildings`
   holds only occupied slots, and `unique(world_id, city_id, slot)` already
   guarantees "at most one building per slot". No schema change is needed for
   slots in this phase.
4. **The pre-check stays, but is demoted to an optimisation.** It gives a cheap,
   friendly refusal in the common case; the unique index is the authority.

<interfaces>
Existing contracts the executor works against — use them directly, do not go
looking for them.

`apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php` (today):

```php
public function starter(): array;                                  // starter.json
public function buildings(): array;                                // buildings.json
public function building(string $code): ?array;
public function buildingLevel(string $code, int $level): ?array;
public function starterValues(string $section): array;             // resources|capacity
/** @return list<array{slot: string, code: string, level: int}> */
public function starterBuildings(): array;                          // slot falls back to code TODAY
private function read(string $file): array;                         // config('game.data_path').'/data/'.$file
```

`apps/api/modules/Shared/Application/Error/ErrorCode.php` — both codes already exist:

```php
case CityNotOwned = 'CITY_NOT_OWNED';   // httpStatus() default => 400
case TileOccupied = 'TILE_OCCUPIED';    // httpStatus() => 409
```

`apps/api/database/migrations/2026_08_27_010200_create_cities_table.php`:

```php
$table->unique(['world_id', 'x', 'y']);        // -> cities_world_id_x_y_unique
$table->unique(['world_id', 'player_id']);     // -> cities_world_id_player_id_unique
$table->timestamp('last_accrued_at');          // NOT NULL, no default
```

`packages/game-data/src/validate.ts` already runs `checkDuplicateCodes` on any
dataset file whose top level is a JSON **array** of objects carrying `code`.
Authoring the roster as an array gets duplicate detection for free.
</interfaces>

## Tasks

<task type="auto">
<name>Task 1: Author the fixed slot roster as game data and validate it</name>
<files>packages/game-data/data/city-slots.json, packages/game-data/data/starter.json, packages/game-data/src/index.ts, packages/game-data/src/validate.ts</files>
<read_first>
- packages/game-data/data/starter.json
- packages/game-data/data/buildings.json
- packages/game-data/src/index.ts
- packages/game-data/src/validate.ts
- packages/game-data/package.json
- docs/game-design/buildings.md
</read_first>
<action>
Create `packages/game-data/data/city-slots.json` as a JSON **array** (top-level
array so the existing `checkDuplicateCodes` in `validate.ts` runs on it), holding
exactly 18 entries in this order:

```json
[
  { "code": "plot_01" },
  { "code": "plot_02" },
  { "code": "plot_03" },
  { "code": "plot_04" },
  { "code": "plot_05" },
  { "code": "plot_06" },
  { "code": "plot_07" },
  { "code": "plot_08" },
  { "code": "plot_09" },
  { "code": "plot_10" },
  { "code": "plot_11" },
  { "code": "plot_12" },
  { "code": "plot_13" },
  { "code": "plot_14" },
  { "code": "plot_15" },
  { "code": "plot_16" },
  { "code": "plot_17" },
  { "code": "plot_18" }
]
```

Edit `packages/game-data/data/starter.json` so `city.buildings` pins every
starter building to a named plot (array order unchanged, one new key per row):

```json
"buildings": [
  { "code": "palace",      "level": 1, "slot": "plot_01" },
  { "code": "farm",        "level": 1, "slot": "plot_02" },
  { "code": "lumber_mill", "level": 1, "slot": "plot_03" },
  { "code": "quarry",      "level": 1, "slot": "plot_04" },
  { "code": "warehouse",   "level": 1, "slot": "plot_05" }
]
```

In `packages/game-data/src/index.ts` add the roster type and register the dataset:

```ts
/** A fixed, stable build plot. Identity never changes once shipped — the client addresses it. */
export type CitySlot = {
  code: string;
};
```

and extend `DATASET_NAMES` to
`['buildings', 'units', 'counters', 'technologies', 'city-slots'] as const;`.

In `packages/game-data/src/validate.ts`, after the existing per-file loop and
before the `problems.length > 0` report, add a dedicated roster check (it is the
dangling-reference rule that actually matters here, and the generic loop cannot
express it):

- Read `city-slots.json`; fail `city-slots` with `"is not a JSON array"` when it
  is not an array.
- Fail with `` `"${code}" is not a valid plot id (expected plot_NN)` `` for any
  entry whose `code` does not match `/^plot_\d{2}$/`.
- Read `starter.json`; for every entry of `city.buildings`, fail `starter` with
  `` `building "${code}" has no "slot"` `` when `slot` is absent, and with
  `` `building "${code}" is assigned unknown slot "${slot}"` `` when the slot is
  not in the roster.
- Fail `starter` with `` `slot "${slot}" is assigned twice` `` on a duplicate slot
  assignment.

Do not add any level/cost logic to `city-slots.json` — it carries slot identity
only. Costs and durations stay in `buildings.json`.
</action>
<verify>
  <automated>cd /Users/sierra/Dev/Jogos/CastleRoyale && npm run gamedata:validate && npm run typecheck --workspace=@castleroyale/game-data && npm run lint --workspace=@castleroyale/game-data</automated>
</verify>
<acceptance_criteria>
- `packages/game-data/data/city-slots.json` exists, parses as a JSON array, and `grep -c 'plot_' packages/game-data/data/city-slots.json` outputs `18`.
- `grep -c '"slot": "plot_' packages/game-data/data/starter.json` outputs `5`.
- `grep -n 'city-slots' packages/game-data/src/index.ts` matches `DATASET_NAMES`.
- `grep -n 'expected plot_NN' packages/game-data/src/validate.ts` matches.
- `npm run gamedata:validate` exits 0 and its final line reports the dataset count.
- Temporarily changing one starter building's `slot` to `plot_99` makes `npm run gamedata:validate` exit non-zero with a message naming `plot_99`; revert afterwards.
</acceptance_criteria>
<done>
The slot roster exists as reviewable, CI-validated JSON; every starter building
names a roster plot; a typo in a slot assignment fails the validator with a
message naming the offending value.
</done>
</task>

<task type="auto">
<name>Task 2: Read the roster in PHP strictly, and migrate legacy slot values</name>
<files>apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php, apps/api/database/migrations/2026_09_05_000000_map_city_building_slots_to_plot_roster.php, apps/api/tests/Feature/City/CitySlotRosterTest.php</files>
<read_first>
- apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php
- apps/api/modules/Player/Application/GameBootstrapService.php
- apps/api/database/migrations/2026_08_27_010300_create_city_buildings_table.php
- apps/api/tests/Feature/City/CityFoundationTest.php
- .planning/codebase/CONVENTIONS.md
</read_first>
<action>
In `apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php`:

1. Add the roster reader. It must return ordered plot codes and reject a
   malformed dataset loudly (a bad roster is a deploy defect, not a player error):

```php
/**
 * The fixed build-plot roster.
 *
 * Order is the roster order the client renders in — never re-sorted downstream.
 *
 * @return list<string>
 */
public function citySlots(): array
{
    $rows = $this->read('city-slots.json');
    $slots = [];

    foreach ($rows as $row) {
        if (! is_array($row) || ! is_string($row['code'] ?? null)) {
            throw new RuntimeException('City slot roster entry is invalid.');
        }

        $slots[] = $row['code'];
    }

    if ($slots === []) {
        throw new RuntimeException('City slot roster is empty.');
    }

    return $slots;
}
```

2. Replace the silent fallback in `starterBuildings()`. Today it does
   `'slot' => (string) ($row['slot'] ?? $row['code'])`, which is why "slot" is
   currently just an alias for the building code. Require an explicit slot and
   require it to be in the roster:

```php
$roster = $this->citySlots();
// ... inside the loop, replacing the current $result[] push:
$slot = $row['slot'] ?? null;
if (! is_string($slot) || ! in_array($slot, $roster, true)) {
    throw new RuntimeException('Starter building "'.((string) $row['code']).'" has no valid slot.');
}

$result[] = ['slot' => $slot, 'code' => (string) $row['code'], 'level' => (int) $row['level']];
```

Keep the existing `if (! is_array($row) || ! isset($row['code'], $row['level'])) { continue; }` guard ahead of this.

3. Create the legacy data-fix migration
`apps/api/database/migrations/2026_09_05_000000_map_city_building_slots_to_plot_roster.php`.
Rows written before this plan carry `slot` equal to the building code, which is
not in the roster and would render as an invisible building. Map them, guarded so
the migration is safe on a fresh database:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Before the plot roster existed, `slot` was written as the building code.
 * Those rows would silently vanish from a roster-driven read, so map them onto
 * the plots `packages/game-data/data/starter.json` now assigns.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private array $legacy = [
        'palace' => 'plot_01',
        'farm' => 'plot_02',
        'lumber_mill' => 'plot_03',
        'quarry' => 'plot_04',
        'warehouse' => 'plot_05',
    ];

    public function up(): void
    {
        foreach ($this->legacy as $code => $slot) {
            DB::table('city_buildings')
                ->where('building_code', $code)
                ->where('slot', $code)
                ->update(['slot' => $slot]);
        }
    }

    public function down(): void
    {
        foreach ($this->legacy as $code => $slot) {
            DB::table('city_buildings')
                ->where('building_code', $code)
                ->where('slot', $slot)
                ->update(['slot' => $code]);
        }
    }
};
```

The plot literals are acceptable **only** here: `apps/api/database/migrations/`
is a data-migration surface, not application code. No `plot_` literal may appear
anywhere under `apps/api/modules/`.

4. Create `apps/api/tests/Feature/City/CitySlotRosterTest.php` with three tests:
   - "persists every starter building into a named roster plot": bootstrap a fresh
     account through `app(GameBootstrapService::class)->handle($account)`, then
     assert the created `CityBuilding` rows' `slot` values equal
     `['plot_01','plot_02','plot_03','plot_04','plot_05']` and each building code
     maps to the plot `starter.json` assigns.
   - "exposes a fixed roster that does not depend on what is built": assert
     `app(GameDataCatalog::class)->citySlots()` has 18 entries, is
     `plot_01`-first / `plot_18`-last, and contains no duplicates.
   - "keeps the slot roster out of PHP": read every file under
     `apps/api/modules/` with `RecursiveDirectoryIterator` and assert none of
     their contents match `/plot_\d{2}/`. Group it with `->group('arch')`.
</action>
<verify>
  <automated>cd /Users/sierra/Dev/Jogos/CastleRoyale/apps/api && ./vendor/bin/pest --filter=CitySlotRoster && ./vendor/bin/pest --filter=CityFoundation && ./vendor/bin/phpstan analyse --memory-limit=1G && ./vendor/bin/pint --test</automated>
</verify>
<acceptance_criteria>
- `grep -n 'city-slots.json' apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php` matches.
- `grep -n "row\['slot'\] ?? \$row\['code'\]" apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php` returns nothing (the silent fallback is gone).
- `grep -rEn 'plot_[0-9]{2}' apps/api/modules/` returns nothing.
- `apps/api/database/migrations/2026_09_05_000000_map_city_building_slots_to_plot_roster.php` exists and contains `'palace' => 'plot_01'`.
- `cd apps/api && ./vendor/bin/pest --filter=CitySlotRoster` exits 0 with 3 passing tests.
- `cd apps/api && ./vendor/bin/pest --filter=CityFoundation` still exits 0 (the pre-existing phase tests are not broken).
- `./vendor/bin/phpstan analyse --memory-limit=1G` and `./vendor/bin/pint --test` exit 0.
</acceptance_criteria>
<done>
PHP reads the roster from game data only, refuses a starter building without a
valid roster slot, and pre-roster rows in an existing database are migrated onto
plot ids instead of silently disappearing.
</done>
</task>

<task type="auto">
<name>Task 3: Make the unique index — not the pre-check — refuse a double tile claim</name>
<files>apps/api/modules/Player/Application/GameBootstrapService.php, apps/api/tests/Feature/City/CityTileClaimTest.php</files>
<read_first>
- apps/api/modules/Player/Application/GameBootstrapService.php
- apps/api/database/migrations/2026_08_27_010200_create_cities_table.php
- apps/api/modules/Shared/Application/Error/ErrorCode.php
- apps/api/tests/Feature/City/CityFoundationTest.php
- apps/api/tests/Pest.php
- .planning/codebase/TESTING.md
</read_first>
<action>
In `apps/api/modules/Player/Application/GameBootstrapService.php`, keep the
existing `$occupiedQuery->exists()` pre-check (it is a cheap, friendly refusal)
but stop treating it as the guarantee. Wrap the `City::create([...])` call in its
own `try/catch` for `Illuminate\Database\QueryException`:

```php
try {
    $city = City::create([ /* unchanged payload */ ]);
} catch (QueryException $exception) {
    // The SELECT above cannot see a row another transaction commits between the
    // check and this INSERT. The unique index is the authority; translate its
    // violation instead of leaking a 500.
    //
    // We match on the index name rather than re-querying, because PostgreSQL
    // aborts the whole transaction after a constraint violation and any follow-up
    // query would fail with 25P02.
    $message = $exception->getMessage();
    $isTileConflict = str_contains($message, 'cities_world_id_x_y_unique')
        || str_contains($message, 'cities.world_id, cities.x, cities.y');

    if (in_array($exception->getCode(), ['23000', '23505'], true) && $isTileConflict) {
        throw GameException::of(ErrorCode::TileOccupied, 'The city tile is already occupied.');
    }

    throw $exception;
}
```

Add `use Illuminate\Database\QueryException;` to the imports and change the
existing outer `catch (\Illuminate\Database\QueryException $exception)` to use
the imported short name. The outer catch keeps mapping
`cities_world_id_player_id_unique` and the players unique index to
`ErrorCode::Conflict` — do not widen it.

Create `apps/api/tests/Feature/City/CityTileClaimTest.php` with two tests:

1. **"refuses a second claim on an occupied tile"** — the pre-check path. Create a
   world with `code` `'tile-claim-precheck'`, `capacity` 10, `is_open` true;
   bootstrap `$first` into it; reset `spawn_index` to 0 with
   `$world->forceFill(['spawn_index' => 0])->save();`; bootstrap `$second` and
   assert the thrown `GameException`'s `errorCode` is `ErrorCode::TileOccupied`.

2. **"resolves a simultaneous claim on the same tile to exactly one city"** — the
   constraint path, which is the one criterion 1 actually names. SQLite in-memory
   cannot run two real connections, so reproduce the exact interleaving with a
   model event: the rival row commits *after* our SELECT and *before* our INSERT.

```php
use Game\City\Infrastructure\City;
use Game\Player\Infrastructure\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('resolves a simultaneous claim on the same tile to exactly one city', function (): void {
    $world = World::create([
        'code' => 'tile-claim-race',
        'name' => 'Tile Claim Race',
        'capacity' => 10,
        'is_open' => true,
    ]);
    $first = Account::factory()->create();
    $second = Account::factory()->create();

    app(GameBootstrapService::class)->handle($first, (string) $world->getKey(), 'Race First');

    $rival = Player::create([
        'world_id' => $world->getKey(),
        'account_id' => Account::factory()->create()->getKey(),
        'name' => 'Race Rival',
    ]);

    // The dispatcher is rebuilt per test by the framework, so this listener does
    // not leak into the rest of the suite.
    $raced = false;
    Event::listen('eloquent.creating: '.City::class, function (City $city) use (&$raced, $rival): void {
        if ($raced) {
            return;
        }
        $raced = true;

        DB::table('cities')->insert([
            'id' => (string) Str::ulid(),
            'world_id' => $city->world_id,
            'player_id' => $rival->getKey(),
            'name_key' => 'city.starter_name',
            'x' => $city->x,
            'y' => $city->y,
            'last_accrued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $exception = null;
    try {
        app(GameBootstrapService::class)->handle($second, (string) $world->getKey(), 'Race Second');
    } catch (GameException $caught) {
        $exception = $caught;
    }

    expect($raced)->toBeTrue()
        ->and($exception)->toBeInstanceOf(GameException::class)
        ->and($exception?->errorCode)->toBe(ErrorCode::TileOccupied)
        ->and(City::query()->where('world_id', $world->getKey())->count())->toBe(1);
});
```

The listener must return `void` — Eloquent dispatches `creating` through `until()`
and a non-null return would cancel the insert and defeat the test.
</action>
<verify>
  <automated>cd /Users/sierra/Dev/Jogos/CastleRoyale/apps/api && ./vendor/bin/pest --filter=CityTileClaim && ./vendor/bin/pest && ./vendor/bin/phpstan analyse --memory-limit=1G && ./vendor/bin/pint --test</automated>
</verify>
<acceptance_criteria>
- `grep -n 'cities_world_id_x_y_unique' apps/api/modules/Player/Application/GameBootstrapService.php` matches.
- `grep -n "eloquent.creating" apps/api/tests/Feature/City/CityTileClaimTest.php` matches.
- `cd apps/api && ./vendor/bin/pest --filter=CityTileClaim` exits 0 with 2 passing tests.
- Commenting out the `$occupiedQuery->exists()` pre-check still leaves `./vendor/bin/pest --filter=CityTileClaim` green on the race test (the constraint alone carries it); restore the pre-check afterwards.
- `cd apps/api && ./vendor/bin/pest` exits 0 for the whole suite.
- `./vendor/bin/phpstan analyse --memory-limit=1G` and `./vendor/bin/pint --test` exit 0.
</acceptance_criteria>
<done>
A tile claim that survives the pre-check is still refused by the unique index and
surfaces as `TILE_OCCUPIED`, and a test proves exactly one city survives the race.
</done>
</task>

## Verification

```bash
npm run gamedata:validate
npm run typecheck
npm run lint
cd apps/api && ./vendor/bin/pest
cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G
cd apps/api && ./vendor/bin/pint --test
```

The PHP suite is SQLite in-memory. The unique index on `cities(world_id, x, y)`
is enforced identically on SQLite and PostgreSQL, so no `tests/Postgres/` addition
is needed here — but do not claim PostGIS coverage from this suite.

## Success Criteria

- `city-slots.json` holds 18 stable plot ids and is validated in CI.
- Every starter building is pinned to a roster plot; an unknown or missing slot
  throws instead of silently aliasing the building code.
- No `plot_NN` literal exists anywhere under `apps/api/modules/`.
- A tile claim that passes the pre-check and loses the insert race returns
  `TILE_OCCUPIED`, and exactly one city exists on that tile.
- The whole backend suite, PHPStan and Pint are green.

<output>
After completion, create
`.planning/phases/07-city-foundation/07-01-city-slot-roster-tile-claim-SUMMARY.md`
recording the roster size decision (18 plots, one per documented building), the
plot-id naming decision, and the index-name matching used to translate the unique
violation.
</output>
