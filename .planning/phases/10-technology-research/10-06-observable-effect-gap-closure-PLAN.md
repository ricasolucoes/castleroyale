---
phase: 10-technology-research
plan: 06
type: execute
wave: 5
depends_on: ["10-01", "10-04", "10-05"]
files_modified:
  - packages/game-data/data/technologies.json
  - docs/game-design/technology.md
  - apps/api/tests/Feature/Technology/ResearchEffectTest.php
  - apps/mobile/src/features/technology/components/TechnologyDetailSheet.tsx
  - apps/mobile/__tests__/technology-cta.test.tsx
autonomous: true
gap_closure: true
requirements: [REQ-06, REQ-05]

must_haves:
  truths:
    - "A player who completes Agriculture level 1 on the real, persisted starter city sees food production rise from 3600 to 7200 units/hour — a real, non-zero change, not a synthetic fixture's change"
    - "The rise is visible on the wire: GET /api/v1/game/city reports the higher data.resources.rate.food after the research completes"
    - "Every authored Agriculture rank clears the integer-truncation floor against the REAL starter baseline: the resolved production.food is strictly greater at rank 1 than at rank 0, at rank 2 than at rank 1, and at rank 3 than at rank 2"
    - "The proof runs the live pipeline end to end — real HTTP research command, real reconciler completion, real CityEconomyService::ratesPerHour() — with no synthetic building standing in for the starter city"
    - "The technology detail sheet renders both of a level's effect lines (the flat bonus and the percentage bonus) as distinct, stably-keyed rows"
    - "No prior-phase economy assertion changes: the unresearched starter city still produces exactly 3600/hour of food, wood and stone"
  artifacts:
    - path: "packages/game-data/data/technologies.json"
      provides: "Agriculture's three levels each carry a flat production.food `add` alongside their existing `multiply`, so the effect survives intdiv truncation against the placeholder building baseline"
      contains: "\"operation\": \"add\""
    - path: "apps/api/tests/Feature/Technology/ResearchEffectTest.php"
      provides: "ROADMAP criterion 4 proven on the live read path — ratesPerHour() and GET /game/city on the actual persisted starter city, before and after a real reconciler-completed research"
      contains: "ratesPerHour"
    - path: "apps/mobile/src/features/technology/components/TechnologyDetailSheet.tsx"
      provides: "Stable React keys for two effects sharing one target, so both effect lines render"
      contains: "effect.operation"
    - path: "docs/game-design/technology.md"
      provides: "The documented truncation-floor rule that explains why a live-consumed technology target must carry a flat add"
      contains: "truncation floor"
  key_links:
    - from: "packages/game-data/data/technologies.json"
      to: "Game\\Economy\\Application\\CityEconomyService::ratesPerHour"
      via: "GameDataCatalog::effectsFor() -> EffectResolver's add pass -> production.food"
      pattern: "production\\.food"
    - from: "apps/api/tests/Feature/Technology/ResearchEffectTest.php"
      to: "Game\\Economy\\Application\\CityEconomyService"
      via: "the test reads the real city's rate before and after the reconciler completes the research"
      pattern: "ratesPerHour"
    - from: "apps/api/tests/Feature/Technology/ResearchEffectTest.php"
      to: "GET /api/v1/game/city"
      via: "the wire-level assertion that a player actually sees the higher rate"
      pattern: "data\\.resources\\.rate\\.food"
---

<objective>
Close the single gap in `10-VERIFICATION.md`: the research pipeline is real, wired and
tested, but against the actual authored data **nothing observable changes**. A player who
spends real resources and real time researching Agriculture sees their food rate stay at
exactly 3600/hour.

Make the phase goal — "effects measurably modify their empire" — true in the live game,
with the smallest honest authored-data change, and prove it on the live read path instead
of a synthetic 100-unit fixture.

Purpose: ROADMAP Phase 10 criterion 4 and the phase goal itself.
Output: One authored `add` effect per Agriculture rank, a rewritten `ResearchEffectTest`
that asserts a real before/after difference on the real persisted city, and the mobile
detail sheet rendering both effect lines.
</objective>

<execution_context>
@/Users/sierra/.claude/get-shit-done/workflows/execute-plan.md
@/Users/sierra/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/ROADMAP.md
@.planning/STATE.md
@.planning/phases/10-technology-research/10-VERIFICATION.md
@.planning/phases/10-technology-research/10-CONTEXT.md
@.planning/phases/10-technology-research/10-05-research-command-reconciler-SUMMARY.md
@.planning/phases/10-technology-research/10-01-technology-catalogue-effects-model-SUMMARY.md
@docs/adr/010-integer-based-economy.md

<the_arithmetic>
**This is the whole problem, in numbers. Read it before touching anything.**

`EffectResolver::resolve()` runs two passes over every building's and every technology's
current-level effects (`apps/api/modules/Technology/Domain/EffectResolver.php`):

1. **add pass** — every `operation: "add"` effect's value is summed into the target.
2. **multiply pass** — every `operation: "multiply"` effect contributes `value - 1000`
   permille of *surplus*; the accumulated surplus is applied once as
   `intdiv($base * (1000 + $surplus), 1000)`.

The real starter city (`packages/game-data/data/starter.json`) holds exactly one food
producer: `farm` at level 1. Farm's authored effect is a flat `add: 1` at **every** level
(a Phase-09 placeholder). So the real `production.food` baseline is exactly **1**.

Today, with Agriculture's `multiply: 1100`:

```
intdiv(1 * 1100, 1000) = 1     <-- +10% of 1 truncates to zero change
intdiv(1 * 1200, 1000) = 1
intdiv(1 * 1300, 1000) = 1
```

The whole effect disappears. `ratesPerHour()['food']` is `1 * 3600 = 3600` before and
after. That is the gap, verified by the verifier via `php artisan tinker`.

After this plan's change (Agriculture also carries `add: 1 / 2 / 3` per rank):

| Agriculture rank | add pass total | multiply | resolved production.food | rate/hour |
|------------------|----------------|----------|--------------------------|-----------|
| 0 (unresearched) | 1 (farm only)  | —        | 1                        | 3600      |
| 1                | 1 + 1 = 2      | 1100     | `intdiv(2200,1000)` = 2  | 7200      |
| 2                | 1 + 2 = 3      | 1200     | `intdiv(3600,1000)` = 3  | 10800     |
| 3                | 1 + 3 = 4      | 1300     | `intdiv(5200,1000)` = 5  | 18000     |

Strictly increasing at every rank, all integer, no float anywhere. **ADR-010 survives
honestly** — the multiply still truncates (it contributes 0 at rank 1 and 2, and +1 at
rank 3); it is the flat `add` that carries the observable change.

**Effects are per-current-level, not cumulative.** `GameDataCatalog::effectsFor()` reads
only `technologyLevel($code, $playerTechnology->level)` — the player's *current* rank. This
is why the `add` must be authored on **all three** ranks with a non-decreasing value: if
only rank 1 carried it, researching rank 2 would silently *remove* the bonus and the food
rate would drop.
</the_arithmetic>

<why_this_change_and_not_the_other_one>
`10-VERIFICATION.md`'s `missing[0]` offers a choice. Take the second option — author an
`add` effect — and **do not** raise any building's base production. The reason is concrete,
not aesthetic:

`apps/api/tests/Feature/Economy/ProductionRateTest.php` hardcodes the starter city's rates
in three places:

- line 24-26: `expect(ratesPerHour($city))->toBe(['food' => 3600, 'wood' => 3600, 'stone' => 3600, 'iron' => 0, 'gold' => 0])`
- line 70: `->assertJsonPath('data.resources.rate.food', 3600)`

and its second test derives an hour of accrual against the warehouse cap of 1000 — a
producer faster than 1/second changes when the cap is reached. Raising `farm`'s
`production.food` would break Phase 08's published rate contract, its accrual/cap test, and
every Phase 09 construction test tuned against 1-unit-per-second producers, for no gain a
technology `add` does not already provide.

Authoring one technology's `add` touches **no** unresearched-city value at all: with zero
`PlayerTechnology` rows the resolver's add pass never sees it, so `ProductionRateTest`,
every Phase 07/08/09 economy assertion, and `ResearchEffectTest`'s own
"leaves an unresearched empire's rate exactly at the building-only baseline" pin are
untouched by construction, not by luck. **Confirm this** by running
`--filter=ProductionRate` after Task 1 and reporting the result in the SUMMARY.

**Why Agriculture specifically:** it is tier 0 (no prerequisites), its rank-1 cost
(80 wood, 50 stone) is affordable from the starter grant (500/500/500/250/100) with no
prior construction, it targets `production.food` — one of only two effect targets any live
code reads — and the real starter city has a non-zero `production.food` baseline (the farm)
for the `add` to build on. `mining` targets `production.iron`, whose starter baseline is
**0** (no `iron_mine` in the starter city), so it would need its own building work first.
</why_this_change_and_not_the_other_one>

<interfaces>
Read these before writing; do not re-derive them.

```php
// Game\Technology\Domain\EffectResolver — pure, no container/clock/db
public static function resolve(array $baseline, array $effectSets): array;

// Game\Shared\Infrastructure\GameData\GameDataCatalog
public function effectsFor(Collection $buildings, Collection $technologies): array; // 'production.food' => int
public function technologyLevel(string $code, int $level): ?array;  // ['cost','research_time_seconds','requirements','effects']
public function buildingLevel(string $code, int $level): ?array;

// Game\Economy\Application\CityEconomyService — the LIVE read path this gap is about
public function ratesPerHour(City $city): array;   // resource => units/hour (per-second effect * 3600)
// its private effects() already queries CityBuilding for the city AND PlayerTechnology
// for the city's owning player, then calls $catalog->effectsFor(...). The wiring is
// real and correct — only the numbers were inert. Do not change this class.

// Game\Technology\Application\ResearchReconciler
public function run(): int;   // returns the number of research orders completed
```

Models usable unpersisted in tests (both attributes are `$fillable`):

```php
new CityBuilding(['building_code' => 'farm', 'level' => 1]);
new PlayerTechnology(['technology_code' => 'agriculture', 'level' => 2]);
```

Test fixtures: `freezeClock(string $iso)` returning a clock with `advanceSeconds(int)`.
`phpunit.xml` forces `QUEUE_CONNECTION=sync`, so a test that must NOT let the delayed job
run sets `config(['queue.default' => 'redis'])` with `Queue::fake()` — the existing
`ResearchEffectTest` already does exactly this; keep it.
</interfaces>

<do_not>
- **Do not** touch `packages/game-data/data/buildings.json`. Building baselines are Phase 46's.
- **Do not** retune the other 15 technologies or Agriculture's `multiply` permille values
  (1100/1200/1300 stay exactly as authored). One technology, three `add` effects, nothing else.
- **Do not** author effects on the six not-yet-consumed targets (`unit.attack`,
  `unit.defense`, `march.speed`, `build.speed`, `scout.range`, `siege.damage`). They are
  correctly waiting on Phases 11-20.
- **Do not** introduce any float, `round()`, `/`, or fractional permille. ADR-010 is
  non-negotiable; the change works *because* a flat integer add survives `intdiv`.
- **Do not** bump `GAME_DATA_VERSION` / `GAME_ECONOMY_VERSION`. ADR-015's "enforced by a CI
  check" is not built yet and Phases 09 and 10 both authored datasets without a bump;
  introducing the bump here is a separate concern with its own blast radius (worlds already
  carry `generation_version: 1`).
- **Do not** modify `EffectResolver.php`, `GameDataCatalog.php`, `CityEconomyService.php`,
  `ResearchService.php` or the reconciler. The verifier confirmed all of them correct. If
  one genuinely must change, stop and say so in the SUMMARY rather than quietly editing it.
</do_not>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Author the flat production bonus that survives truncation</name>

  <read_first>
    - packages/game-data/data/technologies.json (the whole `agriculture` entry — lines 1-31)
    - packages/game-data/data/buildings.json (`farm`: confirm with your own eyes that every level is `production.food add 1`)
    - packages/game-data/data/starter.json (confirm the starter city's only food producer is farm level 1)
    - packages/game-data/schema/technologies.schema.json (`$defs.effect` — confirm `operation` already enumerates `"add"`, and `effects` is an unbounded array, so no schema change is needed)
    - apps/api/modules/Technology/Domain/EffectResolver.php (the two-pass add-then-multiply arithmetic this data depends on)
    - packages/game-data/src/rules.ts (confirm no validator rule constrains effects — if one does, stop and report it)
    - docs/game-design/technology.md (the Effects section you will extend)
  </read_first>

  <files>
    packages/game-data/data/technologies.json,
    docs/game-design/technology.md
  </files>

  <action>
**1a. Amend `agriculture` in `packages/game-data/data/technologies.json`.** Leave `code`,
`name_key`, `description_key`, `category`, `max_level`, every `cost`, every
`research_time_seconds` and every `requirements` array exactly as they are. Append a second
effect object to each of the three levels' `effects` arrays — **append, keep the existing
`multiply` object first** — producing exactly:

```json
      {
        "level": 1,
        "cost": { "wood": 80, "stone": 50 },
        "research_time_seconds": 30,
        "requirements": [],
        "effects": [
          { "target": "production.food", "operation": "multiply", "value": 1100 },
          { "target": "production.food", "operation": "add", "value": 1 }
        ]
      },
      {
        "level": 2,
        "cost": { "food": 150, "wood": 180, "stone": 120 },
        "research_time_seconds": 50,
        "requirements": [],
        "effects": [
          { "target": "production.food", "operation": "multiply", "value": 1200 },
          { "target": "production.food", "operation": "add", "value": 2 }
        ]
      },
      {
        "level": 3,
        "cost": { "food": 300, "wood": 350, "stone": 250 },
        "research_time_seconds": 90,
        "requirements": [],
        "effects": [
          { "target": "production.food", "operation": "multiply", "value": 1300 },
          { "target": "production.food", "operation": "add", "value": 3 }
        ]
      }
```

`add` values are `1`, `2`, `3` — one flat food-per-second per rank. They are **plausible
placeholders on the same footing as the buildings' own `add: 1`**, chosen to be the
smallest values that clear the truncation floor and stay strictly increasing rank over
rank. Phase 46 (Economy Balance Pass) owns the real curve; it may replace these freely as
long as the strictly-increasing property in Task 2's rank test still holds.

No other technology in this file changes. Diff-check yourself: `git diff --stat` must show
`packages/game-data/data/technologies.json` and nothing else in `packages/`.

**1b. Reformat.** These JSON datasets are covered by the repo-wide Prettier check
(`printWidth: 100`). Run `npx prettier --write packages/game-data/data/technologies.json`
and then confirm `npm run format:check` exits 0.

**1c. Document the rule in `docs/game-design/technology.md`.** Under `## Effects`, after
the existing "Percentage effects use integer permille and truncate downward (ADR-010)"
line, add a paragraph that uses the phrase **truncation floor** verbatim:

> **The truncation floor.** A `multiply` effect is invisible when the value it scales is
> small: `intdiv(1 * 1100, 1000)` is `1`, so +10% of a one-unit-per-second producer is
> +0. A technology whose target is read by live code must therefore also carry a flat
> `add`, or its effect is arithmetically unobservable to the player. `agriculture` carries
> `add: 1 / 2 / 3` alongside its `multiply: 1100 / 1200 / 1300` for exactly this reason —
> a placeholder that Phase 46 will retune, not a permanent balance decision. Effects are
> resolved against the player's **current** rank, not summed across ranks, so a flat bonus
> must be authored on every level or a later rank silently removes it.

**1d. Prove nothing upstream moved.** Run `--filter=ProductionRate` and the game-data test
suite before declaring the task done, and record both results in the SUMMARY.
  </action>

  <acceptance_criteria>
    - `grep -c '"operation": "add"' packages/game-data/data/technologies.json` is exactly 3
    - `grep -c '"operation": "multiply"' packages/game-data/data/technologies.json` is exactly 48 — no existing multiply removed
    - This command prints exactly `1 2`, `2 3`, `3 5` on three lines (the resolved production.food per rank against the real farm baseline of 1):
      `python3 -c "import json;t=json.load(open('packages/game-data/data/technologies.json'));a=[x for x in t if x['code']=='agriculture'][0];farm=1;[print(l['level'],(farm+[e['value'] for e in l['effects'] if e['operation']=='add'][0])*[e['value'] for e in l['effects'] if e['operation']=='multiply'][0]//1000) for l in a['levels']]"`
    - `git diff --name-only packages/` lists only `packages/game-data/data/technologies.json`
    - `grep -c "truncation floor" docs/game-design/technology.md` is ≥ 1
    - `npm run gamedata:validate` exits 0
    - `npm test --workspace=@castleroyale/game-data` reports 12 passing (unchanged)
    - `npm run format:check` exits 0
    - `docker compose exec -T api php artisan game:import-data` succeeds and its output still reads `technologies: 16 definitions, 48 levels`
    - `docker compose exec -T api ./vendor/bin/pest --filter=ProductionRate` reports 3 passing — the Phase 08 rate contract is untouched
    - `docker compose exec -T api ./vendor/bin/pest --filter=TechnologyCatalogue` still passes
    - `docker compose exec -T api ./vendor/bin/pest --group=arch` passes, including "keeps cost, duration and effect tables out of PHP"
  </acceptance_criteria>

  <verify>
    <automated>npm run gamedata:validate &amp;&amp; npm test --workspace=@castleroyale/game-data &amp;&amp; docker compose exec -T api ./vendor/bin/pest --filter=ProductionRate</automated>
  </verify>

  <done>
    Agriculture's three ranks each carry a flat `production.food` add alongside their
    unchanged multiply; the validator, the import command, the game-data tests and Phase
    08's starter-rate contract are all still green.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Prove the effect on the real city, through the live read path</name>

  <read_first>
    - apps/api/tests/Feature/Technology/ResearchEffectTest.php (the ENTIRE file — you are rewriting two of its tests and adding one; its `enterCityForResearchEffect` helper and its Queue::fake/frozen-clock setup are kept verbatim)
    - apps/api/modules/Economy/Application/CityEconomyService.php (`ratesPerHour()` and the private `effects()` — confirm for yourself that PlayerTechnology is already in the live query)
    - apps/api/modules/Technology/Domain/EffectResolver.php (the two passes — the expected values below must be derived from this, not guessed)
    - apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php (`effectsFor`, `levelEffects`, `technologyLevel`, `buildingLevel`)
    - apps/api/tests/Feature/Economy/ProductionRateTest.php (the shape of a rate assertion in this codebase, and the 3600 values you must not disturb)
    - apps/api/tests/Feature/Technology/ResearchCompletionTest.php (the HTTP research + advanceSeconds + reconciler sequencing this file must mirror)
  </read_first>

  <files>
    apps/api/tests/Feature/Technology/ResearchEffectTest.php
  </files>

  <behavior>
    - The real persisted starter city's food rate is 3600/hour before any research and 7200/hour after Agriculture rank 1 completes — asserted on `CityEconomyService::ratesPerHour()` AND on `GET /api/v1/game/city`'s `data.resources.rate.food`
    - The after-value equals `intdiv((farmAdd + techAdd) * permille, 1000) * 3600` computed from the catalogue, and is strictly greater than the before-value
    - Every authored Agriculture rank strictly raises the resolved `production.food` over the previous rank, measured against the city's REAL persisted buildings (1 -> 2 -> 3 -> 5)
    - Two ranks still stack as their own declared surplus rather than compounding
    - An unresearched empire's rate is still exactly the building-only baseline
  </behavior>

  <action>
Rewrite `apps/api/tests/Feature/Technology/ResearchEffectTest.php`. Keep
`enterCityForResearchEffect()` exactly as it is. Four tests when you are done.

**2a. Add a uniquely-named catalogue reader** (PHPUnit loads every `*Test.php` in one
process — a top-level function name shared with a sibling file is a fatal redeclare, the
trap 10-05 already hit):

```php
/**
 * The value of the one effect on this level matching (target, operation).
 *
 * Read by target+operation rather than by array index: agriculture level 1 now
 * carries TWO effects on production.food (a multiply and a flat add), and
 * `['effects'][0]` would silently read whichever happened to be authored first.
 */
function researchEffectComponent(array $level, string $target, string $operation): int
{
    foreach ($level['effects'] as $effect) {
        if ($effect['target'] === $target && $effect['operation'] === $operation) {
            return (int) $effect['value'];
        }
    }

    throw new RuntimeException("No {$operation} effect on {$target} at this level.");
}
```

**2b. Replace the first test entirely.** Delete the synthetic-baseline version. New name:
`it('raises the real starter city\'s food rate once a real research completes', ...)`.
Sequence — the ordering is load-bearing, do not reorder:

1. `$clock = freezeClock('2026-09-08T09:00:00+00:00'); config(['queue.default' => 'redis']); Queue::fake();`
2. `$ctx = enterCityForResearchEffect('effect-live');`
3. Read the catalogue components:
   `$farmAdd = researchEffectComponent($catalog->buildingLevel('farm', 1), 'production.food', 'add');`
   `$techAdd = researchEffectComponent($catalog->technologyLevel('agriculture', 1), 'production.food', 'add');`
   `$permille = researchEffectComponent($catalog->technologyLevel('agriculture', 1), 'production.food', 'multiply');`
4. **Before**, on the real city: `$city = City::query()->whereKey($ctx['cityId'])->firstOrFail();`
   `$before = app(CityEconomyService::class)->ratesPerHour($city)['food'];`
   and over HTTP: `$beforeHttp = (int) test()->withToken($ctx['token'])->getJson('/api/v1/game/city')->json('data.resources.rate.food');`
   Assert `$before === $beforeHttp` and `$before === $farmAdd * 3600` (3600 today, derived not hardcoded).
5. `POST /api/v1/game/technologies/agriculture/research` with an `Idempotency-Key`, assert 201.
6. `$clock->advanceSeconds((int) $catalog->technologyLevel('agriculture', 1)['research_time_seconds'] + 1);`
7. `expect(app(ResearchReconciler::class)->run())->toBe(1);` — **no HTTP call between step 6
   and here.** The city and technology read paths both complete overdue research, which
   would finish it before the reconciler could and prove nothing (10-05's own finding).
8. **After**: re-fetch the city from the database, then
   `$after = app(CityEconomyService::class)->ratesPerHour($city)['food'];`
   and `$afterHttp = (int) test()->withToken($ctx['token'])->getJson('/api/v1/game/city')->json('data.resources.rate.food');`
9. Assertions, all four:
   - `expect($after)->toBe(intdiv(($farmAdd + $techAdd) * $permille, 1000) * 3600)` — the exact arithmetic, computed from the catalogue
   - `expect($afterHttp)->toBe($after)` — the player actually sees it on the wire
   - `expect($after)->toBeGreaterThan($before)` — catches a permille of 1000, a dropped effect, or a future re-truncation
   - `expect($after - $before)->toBe(3600)` — the concrete, documented delta this gap closure exists to produce

**2c. Add a new second test** pinning every rank against the REAL baseline —
`it('clears the integer-truncation floor at every authored Agriculture rank', ...)`. No
HTTP research needed; this is catalogue arithmetic measured against the city's actual
persisted buildings:

```php
$ctx = enterCityForResearchEffect('effect-ranks-floor');
$catalog = app(GameDataCatalog::class);
$buildings = CityBuilding::query()->where('city_id', $ctx['cityId'])->get();  // the REAL starter buildings

$previous = $catalog->effectsFor($buildings, collect())['production.food'];    // 1 — farm only
expect($previous)->toBeGreaterThan(0);

foreach ([1, 2, 3] as $level) {
    $resolved = $catalog->effectsFor(
        $buildings,
        collect([new PlayerTechnology(['technology_code' => 'agriculture', 'level' => $level])]),
    )['production.food'];

    expect($resolved)->toBeGreaterThan($previous);   // 1 < 2 < 3 < 5
    $previous = $resolved;
}
```

This is the regression guard: a future tuning pass that drops one rank's `add`, or lowers
it, fails here rather than silently re-inerting the phase goal.

**2d. Keep the stacking test, fix its arithmetic.** `syntheticFarms(100)` stays — that test
proves the *resolver's* additive-surplus rule, which needs a baseline large enough to tell
`+20%` from `+21%`, and it is explicitly not the goal proof any more. Its expected values
must now account for the technology's own add:

- after rank 1: `intdiv((100 * $farmAdd + $techAdd1) * $permille1, 1000)` = `intdiv(101 * 1100, 1000)` = **111**
- after rank 2: `intdiv((100 * $farmAdd + $techAdd2) * $permille2, 1000)` = `intdiv(102 * 1200, 1000)` = **122**
- the compounded alternative it must NOT equal: `intdiv(111 * 1200, 1000)` = **133**

Compute all of these from the catalogue via `researchEffectComponent()`; do not hardcode
111/122/133. Replace the index lookups on lines 114-115 with `researchEffectComponent()`
calls. Rewrite `syntheticFarms()`'s docblock: it no longer exists to dodge the truncation
trap for the goal proof (test 2b now proves the goal on the real city) — it exists solely
to give the stacking rule a baseline coarse enough to distinguish additive from
multiplicative stacking.

**2e. Leave the third test** ("leaves an unresearched empire's rate exactly at the
building-only baseline") **untouched** — it is the pin proving this plan changed nothing
for a player who has researched nothing. Its `['effects'][0]` lookup on `farm` is still
correct (farm levels carry exactly one effect), but convert it to
`researchEffectComponent()` anyway so the whole file reads by meaning, not by index.
  </action>

  <acceptance_criteria>
    - `grep -c "ratesPerHour" apps/api/tests/Feature/Technology/ResearchEffectTest.php` is ≥ 3
    - `grep -c "data.resources.rate.food" apps/api/tests/Feature/Technology/ResearchEffectTest.php` is ≥ 2 — the wire-level before and after
    - `grep -c "researchEffectComponent" apps/api/tests/Feature/Technology/ResearchEffectTest.php` is ≥ 7 — every catalogue read is by target+operation
    - `grep -cE "\['effects'\]\[0\]" apps/api/tests/Feature/Technology/ResearchEffectTest.php` is 0 — no index lookups survive
    - `grep -c "syntheticFarms" apps/api/tests/Feature/Technology/ResearchEffectTest.php` is exactly 3 (one definition + two calls, both inside the stacking test) — the goal proof no longer uses it
    - `grep -c "toBe(3600)" apps/api/tests/Feature/Technology/ResearchEffectTest.php` is ≥ 1 — the concrete delta is asserted
    - `grep -c "intdiv" apps/api/tests/Feature/Technology/ResearchEffectTest.php` is ≥ 3 — expectations computed, not hardcoded
    - `grep -cE "\bCarbon::now" apps/api/tests/Feature/Technology/ResearchEffectTest.php` is 0
    - Read the file and confirm no HTTP call sits between `advanceSeconds(...)` and `ResearchReconciler::run()` in any test
    - `docker compose exec -T api ./vendor/bin/pest --filter=ResearchEffect` reports 4 passing
    - `docker compose exec -T api ./vendor/bin/pest --filter=Research` reports 17 passing (16 before + the new rank test), with no previously passing test deleted
    - `docker compose exec -T api ./vendor/bin/pest` fully green
    - `docker compose exec -T api ./vendor/bin/pint --test` clean
    - **Falsification, then restore:** temporarily set Agriculture level 1's `add` value to `0` in `technologies.json`, re-run `--filter=ResearchEffect`, and confirm the live-path test FAILS (its `toBe(3600)` delta becomes 0). Restore the value to `1`, re-run, confirm 4 passing, and confirm `git diff packages/game-data/data/technologies.json` shows the `add` back at 1/2/3. Record both observations in the SUMMARY.
  </acceptance_criteria>

  <verify>
    <automated>docker compose exec -T api ./vendor/bin/pest --filter=ResearchEffect &amp;&amp; docker compose exec -T api ./vendor/bin/pest</automated>
  </verify>

  <done>
    The phase goal is proven on the live pipeline: a real HTTP research command, completed
    by the real reconciler, raises the real persisted starter city's food rate from 3600 to
    7200 units/hour, visible both through `CityEconomyService::ratesPerHour()` and on the
    wire through `GET /game/city` — and every authored rank is pinned strictly above the
    one before it.
  </done>
</task>

<task type="auto">
  <name>Task 3: Render both effect lines in the technology detail sheet</name>

  <read_first>
    - apps/mobile/src/features/technology/components/TechnologyDetailSheet.tsx (the `formatEffect` helper at lines ~39-53 and the `nextLevel.effects.map(...)` render at lines ~187-191 — the `key={effect.target}` on that row is the defect)
    - apps/mobile/__tests__/technology-cta.test.tsx (the whole file: the `buildTechnology` fixture whose `next_level.effects` you extend, the `t`-returns-the-key i18n mock, the `renderSheet` helper, and the `mock`-prefixed jest.mock convention)
    - packages/localization/locales/en/mvp.json (`technology.effect_target` — `production_food` already exists in en, pt-BR and es, so NO locale file changes are needed; confirm this before adding any key)
  </read_first>

  <files>
    apps/mobile/src/features/technology/components/TechnologyDetailSheet.tsx,
    apps/mobile/__tests__/technology-cta.test.tsx
  </files>

  <action>
Agriculture's levels now carry two effects on the **same** target. The sheet keys its
effect rows by `effect.target` alone, so React sees two children with the same key — a
duplicate-key warning and an unstable list. `formatEffect` itself already handles `add`
correctly (`+1 <label>` vs `+10% <label>`); only the key is wrong.

**3a. Fix the key.** In the `nextLevel.effects.map(...)` block, change
`key={effect.target}` to a composite that includes the operation:

```tsx
{nextLevel.effects.map((effect) => (
  <Text
    key={`${effect.target}:${effect.operation}`}
    variant="caption"
    color={theme.color.text.secondary}
  >
    {formatEffect(effect, t)}
  </Text>
))}
```

Change nothing else in this file — no new colour, no `theme.color.danger`, no
`interpolateResources` (the file's own source-assertion test enforces all three).

**3b. Add a test to `apps/mobile/__tests__/technology-cta.test.tsx`** named
`renders both effect lines when a level carries a flat bonus and a percentage bonus`.
Render through the existing `renderSheet` helper with a technology whose `next_level.effects`
mirrors the real authored Agriculture rank 1:

```tsx
const technology = buildTechnology({
  next_level: {
    cost: { food: 0, wood: 80, stone: 50, iron: 0, gold: 0 },
    research_time_seconds: 30,
    effects: [
      { target: 'production.food', operation: 'multiply', value: 1100 },
      { target: 'production.food', operation: 'add', value: 1 },
    ],
  },
} as Partial<Technology>);
```

The i18n mock returns the key itself, so assert both rendered strings exactly:

- `expect(getByText('+10% technology.effect_target.production_food')).toBeTruthy();`
- `expect(getByText('+1 technology.effect_target.production_food')).toBeTruthy();`

Then prove the key collision is gone rather than merely tolerated. Spy on `console.error`
around the render and assert React never warned:

```tsx
const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
// ...render and assert both lines...
expect(errorSpy.mock.calls.map((call) => call.join(' ')).join('\n')).not.toContain('same key');
errorSpy.mockRestore();
```

(React's duplicate-key warning reads "Encountered two children with the same key" — the
narrow `same key` substring keeps the assertion from catching unrelated warnings.)
  </action>

  <acceptance_criteria>
    - `grep -c "effect.operation" apps/mobile/src/features/technology/components/TechnologyDetailSheet.tsx` is ≥ 2 — one in `formatEffect`, one in the row key
    - `grep -c "key={effect.target}" apps/mobile/src/features/technology/components/TechnologyDetailSheet.tsx` is 0 — the bare key is gone
    - `grep -c "renders both effect lines" apps/mobile/__tests__/technology-cta.test.tsx` is 1
    - `grep -c "same key" apps/mobile/__tests__/technology-cta.test.tsx` is 1
    - `grep -c "operation: 'add'" apps/mobile/__tests__/technology-cta.test.tsx` is ≥ 1
    - `git diff --name-only packages/localization/` is empty — no locale key was needed or added
    - `npm test --workspace=@castleroyale/mobile` reports 16 suites green and 106 tests (105 before + 1), with no previously passing test deleted
    - `npm run typecheck` and `npm run lint` both exit 0 workspace-wide
    - `git diff --stat apps/mobile/src` shows only `TechnologyDetailSheet.tsx`, and its diff is the single key line
  </acceptance_criteria>

  <verify>
    <automated>npm run typecheck &amp;&amp; npm run lint &amp;&amp; npm test --workspace=@castleroyale/mobile</automated>
  </verify>

  <done>
    A technology level carrying both a flat and a percentage bonus renders both lines, each
    with a stable key, and a regression test pins it.
  </done>
</task>

</tasks>

<verification>
- `npm run gamedata:validate` — exits 0
- `npm run format:check` — exits 0
- `npm test` — every workspace green: 12 game-data tests, 16 mobile suites / 106 tests
- `npm run typecheck && npm run lint` — exit 0
- `docker compose exec -T api php artisan game:import-data` — succeeds, `technologies: 16 definitions, 48 levels`
- `docker compose exec -T api ./vendor/bin/pest` — fully green (224 tests: 223 before + the new rank test)
- `docker compose exec -T api ./vendor/bin/pest --filter=ResearchEffect` — 4 passing
- `docker compose exec -T api ./vendor/bin/pest --filter=ProductionRate` — 3 passing, Phase 08's rate contract untouched
- `docker compose exec -T api ./vendor/bin/pest --group=arch` — passing, balance still out of PHP
- `docker compose exec -T api ./vendor/bin/pint --test` — clean
- `git diff --name-only` — exactly the five files in `files_modified`, nothing else
</verification>

<success_criteria>
1. `10-VERIFICATION.md`'s `missing[0]` is closed: at least one tier-1 technology effect uses
   `operation: "add"` against a live-consumed target (`production.food`), and a real player
   completing a real research sees a real, non-zero change today.
2. `10-VERIFICATION.md`'s `missing[1]` is closed: a test calls
   `CityEconomyService::ratesPerHour()` on the actual persisted starter city before and
   after an HTTP-driven, reconciler-completed research and asserts a nonzero,
   correctly-computed difference (3600 → 7200 units/hour), corroborated on the wire by
   `GET /game/city`.
3. Every authored Agriculture rank is pinned strictly above the previous one against the
   real starter baseline (1 → 2 → 3 → 5 units/second).
4. No prior-phase test changed behaviour: `ProductionRate` still reports 3600/hour for an
   unresearched starter city, and the full backend, mobile and game-data suites are green.
5. ADR-010 holds: no float, no rounding, no fractional permille anywhere in the diff.
6. `buildings.json`, `EffectResolver.php`, `GameDataCatalog.php`, `CityEconomyService.php`
   and the research services are untouched.
</success_criteria>

<output>
After completion, create
`.planning/phases/10-technology-research/10-06-observable-effect-gap-closure-SUMMARY.md`.

It must record, explicitly:
- The before/after rate on the real starter city (3600 → 7200 units/hour) and the rank
  table (1 → 2 → 3 → 5 units/second).
- The falsification result from Task 2 (which test failed with `add: 0`, and confirmation
  that the data was restored).
- That the `add: 1 / 2 / 3` values are plausible placeholders on the same footing as the
  buildings' `add: 1`, explicitly deferred to Phase 46 (Economy Balance Pass) for real
  tuning — and that the strictly-increasing property is now test-pinned, so a Phase 46
  retune cannot silently re-inert the phase goal.
- That `ProductionRate` (Phase 08) and the architecture test were re-run and are unchanged.
</output>
