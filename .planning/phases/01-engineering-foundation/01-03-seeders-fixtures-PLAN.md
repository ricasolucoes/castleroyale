---
phase: 01-engineering-foundation
plan: 03
type: execute
wave: 3
depends_on: ["01-01", "01-02"]
files_modified:
  - apps/api/database/seeders/StaffUserSeeder.php
  - apps/api/database/seeders/DevelopmentUserSeeder.php
  - apps/api/database/seeders/DatabaseSeeder.php
  - apps/api/database/factories/UserFactory.php
  - apps/api/tests/Pest.php
  - apps/api/tests/Feature/Database/SeederTest.php
  - apps/api/tests/Feature/Platform/TestFixturesTest.php
  - .planning/codebase/TESTING.md
  - docs/gsd/DECISIONS.md
autonomous: true
requirements: [REQ-06]

must_haves:
  truths:
    - "`make seed` completes without error and creates a back-office account whose is_staff column is actually true"
    - "`make seed` run twice produces the same number of rows and the same primary keys — no duplicates, no exception"
    - "The seeded dataset is browsable: the accounts appear in the Filament back office at /admin"
    - "Reference data (buildings, units, technologies) is never seeded — it is imported from packages/game-data by its own command"
    - "Outside local/testing the staff seeder refuses to invent a password and creates nothing"
    - "A test can freeze the clock, act as a staff user, and assert an error envelope by ErrorCode without re-implementing any of it"
    - "The one locked CONTEXT decision this plan does not follow literally is written down in docs/gsd/DECISIONS.md, not quietly designed around"
  artifacts:
    - path: "apps/api/database/seeders/DevelopmentUserSeeder.php"
      provides: "Idempotent development accounts, gated on the environment"
      contains: "firstOrNew"
    - path: "apps/api/database/seeders/StaffUserSeeder.php"
      provides: "Back-office account whose is_staff flag survives mass-assignment protection"
      contains: "forceFill"
    - path: "apps/api/tests/Feature/Database/SeederTest.php"
      provides: "Idempotency, staff-flag and reference-data-boundary coverage"
      min_lines: 60
    - path: "apps/api/tests/Pest.php"
      provides: "freezeClock(), actingAsStaff(), toBeApiError() fixtures"
      contains: "toBeApiError"
    - path: "docs/gsd/DECISIONS.md"
      provides: "The record that the locked 'use updateOrCreate' decision was replaced, and why"
      contains: "Phase 01 — Seeder idempotency"
  key_links:
    - from: "apps/api/database/seeders/DatabaseSeeder.php"
      to: "DevelopmentUserSeeder"
      via: "environment-gated call list"
      pattern: "DevelopmentUserSeeder::class"
    - from: "apps/api/database/seeders/StaffUserSeeder.php"
      to: "users.is_staff"
      via: "forceFill, because is_staff is deliberately not in User::\\$fillable"
      pattern: "forceFill"
    - from: "apps/api/tests/Pest.php"
      to: "Game\\Shared\\Domain\\Time\\FrozenClock"
      via: "container instance binding for Clock::class"
      pattern: "app\\(\\)->instance\\(Clock::class"
---

<objective>
Make `make seed` produce a dataset a developer can actually open and browse, twice in
a row, and give every later phase a set of test fixtures instead of copy-paste.

There is a live defect to fix first: `AppServiceProvider` enables
`Model::preventSilentlyDiscardingAttributes()`, and `StaffUserSeeder` passes
`is_staff => true` through `updateOrCreate()` while `User::$fillable` is only
`['name', 'email', 'password']`. `php artisan db:seed` therefore throws
`MassAssignmentException` — or, if protection were relaxed, would silently create a
back-office account that cannot reach the back office.

Purpose: satisfies ROADMAP Phase 01 success criterion 3, and enforces REQ-06 at the
seeder boundary — balance data is imported from `packages/game-data`, never seeded
from PHP.

One locked CONTEXT decision does not survive that defect. `01-CONTEXT.md` § Seeds
says *"use updateOrCreate"* — the mechanism that throws. The **requirement**
(idempotent, safe to run twice) is kept and tested; the **mechanism** becomes
`firstOrNew` + `forceFill` + `save`. Per the CONTEXT header, that swap is written into
`docs/gsd/DECISIONS.md` in Task 1 rather than being silently designed around.

Output: a fixed staff seeder, an idempotent development seeder, factory states,
reusable Pest fixtures (`freezeClock`, `actingAsStaff`, `toBeApiError`), and a decision
log entry.
</objective>

<execution_context>
@/Users/sierra/.claude/get-shit-done/workflows/execute-plan.md
@/Users/sierra/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/ROADMAP.md
@.planning/STATE.md
@.planning/phases/01-engineering-foundation/01-CONTEXT.md
@.planning/codebase/TESTING.md
@.planning/codebase/CONVENTIONS.md
@docs/operations/environments.md
@apps/api/app/Providers/AppServiceProvider.php

<interfaces>
<!-- Contracts the executor needs. Do not go hunting for these. -->

`App\Models\User` (apps/api/app/Models/User.php) — a Phase 03 placeholder:
```php
protected $fillable = ['name', 'email', 'password'];   // is_staff is NOT here, on purpose
protected $hidden   = ['password', 'remember_token'];
public function canAccessPanel(Panel $panel): bool { return $this->is_staff === true; }
```
Columns: `id` (bigIncrements), `name`, `email` (unique), `is_staff` (bool, default
false, indexed), `email_verified_at`, `password`, `remember_token`, timestamps.

`App\Providers\AppServiceProvider::boot()` enables:
```php
Model::preventSilentlyDiscardingAttributes();
Model::preventAccessingMissingAttributes(! production);
Model::preventLazyLoading(! production);
```
This is why `updateOrCreate([...], ['is_staff' => true])` throws. Keep `is_staff` out
of `$fillable` — an account flag that a future registration endpoint could mass-assign
is a privilege-escalation bug waiting to happen. Use `forceFill()` in the seeder
instead. Eloquent factories already run inside `Model::unguarded()`, so a factory
state may set `is_staff` directly.

`config('game.admin_seed')` (apps/api/config/game.php):
```php
'admin_seed' => [
    'email'    => env('ADMIN_SEED_EMAIL', 'admin@example.test'),
    'password' => env('ADMIN_SEED_PASSWORD', ''),   // empty by default
],
```

Shared kernel classes the fixtures wrap:
```php
interface Game\Shared\Domain\Time\Clock { public function now(): DateTimeImmutable; }
final class Game\Shared\Domain\Time\FrozenClock implements Clock {
    public static function at(string $iso8601): self;
    public function now(): DateTimeImmutable;
    public function advanceSeconds(int $seconds): void;
}
enum Game\Shared\Application\Error\ErrorCode: string {
    public function httpStatus(): int;
    public function isRetryable(): bool;
    // NotFound = 'NOT_FOUND', ValidationFailed = 'VALIDATION_FAILED', ...
}
```
`Clock::class` is bound as a singleton to `SystemClock` in `AppServiceProvider::register()`.

Existing `apps/api/tests/Pest.php` bindings (do not remove any of them):
```php
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Architecture');
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Postgres');  // added by plan 01-02
expect()->extend('toBeApiSuccess', ...);
```

Error envelope produced by `ApiResponse::error()`:
```json
{ "error": { "code": "NOT_FOUND", "message": "...", "retryable": false } }
```
There is never a `data` key alongside an `error` key.
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Fix the staff seeder and add an idempotent development dataset</name>
  <files>apps/api/database/seeders/StaffUserSeeder.php, apps/api/database/seeders/DevelopmentUserSeeder.php, apps/api/database/seeders/DatabaseSeeder.php, docs/gsd/DECISIONS.md</files>

  <read_first>
    - apps/api/database/seeders/StaffUserSeeder.php (the file being modified — note the existing environment guard and the `$this->command?->` null-safe calls)
    - apps/api/database/seeders/DatabaseSeeder.php (the file being modified — note the empty `$this->call([])` in the environment-gated block)
    - apps/api/app/Models/User.php ($fillable does not include is_staff; canAccessPanel depends on it)
    - apps/api/app/Providers/AppServiceProvider.php (preventSilentlyDiscardingAttributes — the reason the current seeder throws)
    - apps/api/config/game.php (§ admin_seed)
    - .planning/phases/01-engineering-foundation/01-CONTEXT.md (§ Seeds says "use updateOrCreate"; the header says an unworkable locked decision must be recorded, not designed around. Step 4 is that record.)
    - docs/gsd/DECISIONS.md (the file being appended to — copy the entry format from § Format and match the existing Phase 00 entries)
  </read_first>

  <action>
**1. Fix `apps/api/database/seeders/StaffUserSeeder.php`.**

Replace the `User::query()->updateOrCreate(...)` call with a `firstOrNew` +
`forceFill` + `save` sequence. Keep the existing password/environment guard exactly as
it is. The new body of `run()` after the guard:

```php
        $user = User::query()->firstOrNew(['email' => $email]);

        // forceFill, not fill: `is_staff` is deliberately absent from
        // User::$fillable so no future registration endpoint can mass-assign it,
        // and AppServiceProvider enables preventSilentlyDiscardingAttributes().
        $user->forceFill([
            'name' => 'Game Master',
            'password' => Hash::make($password),
            'is_staff' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
            // Re-running the seeder must not invalidate an existing session.
            'remember_token' => $user->remember_token ?? Str::random(10),
        ])->save();

        $this->command?->info("Staff user ready: {$email}");
```

`$user->email_verified_at` and `$user->remember_token` on a new (non-existing) model
would trip `Model::preventAccessingMissingAttributes()`. Guard with
`$user->exists ? $user->remember_token : null` if that fires; verify by running the
seeder, do not guess.

**2. Create `apps/api/database/seeders/DevelopmentUserSeeder.php`:**

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Development accounts.
 *
 * Enough rows that a developer opening the back office sees a populated list
 * rather than a single admin. Deterministic emails so a re-run updates instead of
 * duplicating, and so a test can assert against a known address.
 *
 * Gameplay fixtures (players, cities, armies, alliances) are added by the phases
 * that own those entities — Phase 01 has no gameplay entities to seed.
 */
final class DevelopmentUserSeeder extends Seeder
{
    /**
     * @var list<array{email: string, name: string, is_staff: bool}>
     */
    private const ACCOUNTS = [
        ['email' => 'support@example.test', 'name' => 'Support Agent', 'is_staff' => true],
        ['email' => 'dev-alpha@example.test', 'name' => 'Alpha Tester', 'is_staff' => false],
        ['email' => 'dev-bravo@example.test', 'name' => 'Bravo Tester', 'is_staff' => false],
        ['email' => 'dev-charlie@example.test', 'name' => 'Charlie Tester', 'is_staff' => false],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing', 'development'])) {
            $this->command?->warn('Not a development environment — skipping development users.');

            return;
        }

        foreach (self::ACCOUNTS as $account) {
            $user = User::query()->firstOrNew(['email' => $account['email']]);

            $user->forceFill([
                'name' => $account['name'],
                'is_staff' => $account['is_staff'],
                'email_verified_at' => $user->exists ? $user->email_verified_at : now(),
                'password' => $user->exists ? $user->password : Hash::make('password'),
            ])->save();
        }

        $this->command?->info('Development users ready: '.count(self::ACCOUNTS).' accounts.');
    }
}
```

Note the `$user->exists ?` guards on `password` and `email_verified_at`: re-running
must not rewrite an existing row's password hash, otherwise "safe to run twice" is
true only for row counts and not for state.

**3. Wire it in `apps/api/database/seeders/DatabaseSeeder.php`.** Replace the empty
call array:

```php
            $this->call([
                //
            ]);
```

with:

```php
            $this->call([
                DevelopmentUserSeeder::class,
            ]);
```

Leave the class docblock untouched — it already states the reference-data boundary
this plan is enforcing. Delete the stale `GSD Phase 01 task P01-BE-006` reference in
the inline comment and replace it with:

```php
            // Populated by the phases that own each subsystem. Each seeder is
            // additive and safe to re-run (GSD Phase 01, plan 01-03).
```

**4. Record the departure from a locked decision in `docs/gsd/DECISIONS.md`.**

`01-CONTEXT.md` § Seeds is locked and says *"Seeders must be idempotent — safe to run
twice (use updateOrCreate)."* Steps 1 and 2 keep the requirement (idempotent) and drop
the mechanism (`updateOrCreate`), because `updateOrCreate` genuinely cannot set
`is_staff`: it mass-assigns, `is_staff` is not in `User::$fillable`, and
`AppServiceProvider` enables `Model::preventSilentlyDiscardingAttributes()`. The
CONTEXT header is explicit that a locked decision which proves unworkable gets written
down rather than quietly worked around — so write it down.

Append this entry to the end of `docs/gsd/DECISIONS.md`, after the last Phase 00
entry, matching the § Format block already in that file. Use today's date
(`date +%F`), not the literal below:

```
### YYYY-MM-DD — Phase 01 — Seeder idempotency uses firstOrNew + forceFill, not updateOrCreate

**Type:** Change
**What:** `01-CONTEXT.md` § Seeds specifies `updateOrCreate` as the idempotency
mechanism for seeders. `StaffUserSeeder` and `DevelopmentUserSeeder` use
`firstOrNew()` + `forceFill()` + `save()` instead. The requirement itself is
unchanged and still tested: seeding twice produces the same rows with the same
primary keys.
**Why:** `updateOrCreate()` mass-assigns, and `is_staff` is deliberately absent from
`User::$fillable` so no future registration endpoint can escalate a account to staff.
`AppServiceProvider` enables `Model::preventSilentlyDiscardingAttributes()`, so the
attribute is not silently dropped — it throws `MassAssignmentException` and
`php artisan db:seed` fails outright. Adding `is_staff` to `$fillable` would trade a
seeder convenience for a privilege-escalation surface. `forceFill()` bypasses
mass-assignment protection at the one call site that is allowed to, inside a seeder.
**Impact:** Every seeder in every later phase. The idempotency pattern for this
project is `firstOrNew` + `forceFill` + `save`, with `$user->exists ?` guards on any
attribute a re-run must not overwrite. Covered by
`tests/Feature/Database/SeederTest.php`.
**ADR:** none needed — an implementation detail of a seeder, not an architectural
decision. ADR-016 and the mass-assignment posture are unchanged.
```

Do not edit any existing entry, the header, or the § Format block — append only.

Then run `./vendor/bin/pint` and confirm `php artisan db:seed --force` completes
inside Docker.
  </action>

  <verify>
    <automated>cd /Users/sierra/Dev/Jogos/MmoMobile && make migrate-fresh && make seed && make seed && STAFF=$(docker compose exec -T postgres psql -U dominion -d dominion -tAc "select count(*) from users where is_staff = true" | tr -d '[:space:]') && TOTAL=$(docker compose exec -T postgres psql -U dominion -d dominion -tAc "select count(*) from users" | tr -d '[:space:]') && echo "staff=$STAFF total=$TOTAL" && [ "$STAFF" = "2" ] && [ "$TOTAL" = "5" ] && grep -q 'Phase 01 — Seeder idempotency' docs/gsd/DECISIONS.md && echo SEED_IDEMPOTENT_AND_STAFF_OK</automated>
  </verify>

  <acceptance_criteria>
    - `ls apps/api/database/seeders/DevelopmentUserSeeder.php` succeeds
    - `grep -q 'forceFill' apps/api/database/seeders/StaffUserSeeder.php` succeeds
    - `grep -q 'updateOrCreate' apps/api/database/seeders/StaffUserSeeder.php` returns nothing (the throwing call is gone)
    - `grep -q 'DevelopmentUserSeeder::class' apps/api/database/seeders/DatabaseSeeder.php` succeeds
    - `grep -q "environment(\['local', 'testing', 'development'\])" apps/api/database/seeders/DevelopmentUserSeeder.php` succeeds
    - `grep -q 'P01-BE-006' apps/api/database/seeders/DatabaseSeeder.php` returns nothing
    - `is_staff` is still absent from `User::$fillable` in `apps/api/app/Models/User.php` (`git diff --exit-code apps/api/app/Models/User.php` succeeds — this task must not touch the model)
    - `make seed` exits 0 twice in a row with no `MassAssignmentException`
    - `select count(*) from users` returns 5 after one seed and still 5 after a second seed
    - `docker compose exec -T postgres psql -U dominion -d dominion -tAc "select count(*) from users where is_staff = true" | tr -d '[:space:]'` equals exactly `2` (admin + support). Use `-tAc` (unaligned): with psql's default aligned output the value is padded and a whitespace-anchored grep gives a false failure.
    - **The locked decision is recorded, not worked around:** `grep -q 'Phase 01 — Seeder idempotency' docs/gsd/DECISIONS.md` succeeds
    - `grep -q 'updateOrCreate' docs/gsd/DECISIONS.md` succeeds (the entry names the mechanism it replaced)
    - `grep -A12 'Phase 01 — Seeder idempotency' docs/gsd/DECISIONS.md | grep -q 'preventSilentlyDiscardingAttributes'` succeeds (the entry gives the real reason, not "it didn't work")
    - `grep -A20 'Phase 01 — Seeder idempotency' docs/gsd/DECISIONS.md | grep -qE '^\*\*(Type|What|Why|Impact|ADR):\*\*'` succeeds for all five fields — the entry follows the § Format block
    - `git diff docs/gsd/DECISIONS.md | grep -c '^-' ` returns 1 (only the `---` diff header; the append removed nothing)
  </acceptance_criteria>

  <done>`make seed` produces five browsable accounts, is safe to run twice, the back-office accounts have `is_staff = true` in the database, and `docs/gsd/DECISIONS.md` records why the locked `updateOrCreate` decision was replaced.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Factory states and the seeder test suite</name>
  <files>apps/api/database/factories/UserFactory.php, apps/api/tests/Feature/Database/SeederTest.php, apps/api/database/seeders/DatabaseSeeder.php</files>

  <read_first>
    - apps/api/database/factories/UserFactory.php (the file being modified — note the existing `unverified()` state and the shared `static::$password`)
    - apps/api/database/seeders/StaffUserSeeder.php (as fixed in Task 1)
    - apps/api/database/seeders/DevelopmentUserSeeder.php (as created in Task 1)
    - apps/api/tests/Feature/Platform/HealthEndpointTest.php (house test style: `it('...')`, sentence names)
    - .planning/codebase/TESTING.md (§ "What every phase must test")
  </read_first>

  <behavior>
    - `User::factory()->staff()->create()->is_staff` is `true`
    - Running `db:seed` once creates 5 users, of which 2 are staff
    - Running `db:seed` twice leaves 5 users with unchanged primary keys
    - `StaffUserSeeder` creates nothing when the environment is not local/testing and `ADMIN_SEED_PASSWORD` is empty
    - `DevelopmentUserSeeder` creates nothing when the environment is not local/testing/development
    - No file under `database/seeders/` references buildings, units, technologies or game-data (REQ-06 boundary)
  </behavior>

  <action>
**1. Add a `staff()` state to `apps/api/database/factories/UserFactory.php`**, after
the existing `unverified()` method:

```php
    /**
     * A back-office account.
     *
     * Factories run inside Model::unguarded(), so `is_staff` is settable here even
     * though it is deliberately absent from User::$fillable.
     */
    public function staff(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_staff' => true,
        ]);
    }
```

If this throws `MassAssignmentException`, fall back to
`->afterMaking(fn (User $user) => $user->forceFill(['is_staff' => true]))` — but run
it first and only change it if it actually fails.

**2. Create `apps/api/tests/Feature/Database/SeederTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DevelopmentUserSeeder;
use Database\Seeders\StaffUserSeeder;

it('seeds a browsable development dataset', function (): void {
    $this->artisan('db:seed')->assertSuccessful();

    expect(User::query()->count())->toBe(5)
        ->and(User::query()->where('is_staff', true)->count())->toBe(2)
        ->and(User::query()->where('email', config('game.admin_seed.email'))->exists())->toBeTrue();
});

it('is safe to run twice', function (): void {
    $this->artisan('db:seed')->assertSuccessful();
    $first = User::query()->orderBy('id')->pluck('id')->all();

    $this->artisan('db:seed')->assertSuccessful();
    $second = User::query()->orderBy('id')->pluck('id')->all();

    expect($second)->toBe($first)
        ->and(User::query()->count())->toBe(5);
});

it('persists the staff flag the back office authorises on', function (): void {
    // Regression: is_staff is absent from User::$fillable, so a mass-assigning
    // seeder either throws or silently creates an admin who cannot log in.
    $this->artisan('db:seed')->assertSuccessful();

    $admin = User::query()->where('email', config('game.admin_seed.email'))->sole();

    expect($admin->is_staff)->toBeTrue();
});

it('refuses to invent an admin password outside local', function (): void {
    $this->app->detectEnvironment(fn (): string => 'staging');
    config()->set('game.admin_seed.password', '');

    app(StaffUserSeeder::class)->run();

    expect(User::query()->where('email', config('game.admin_seed.email'))->exists())->toBeFalse();
});

it('does not create development accounts outside a development environment', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');

    app(DevelopmentUserSeeder::class)->run();

    expect(User::query()->count())->toBe(0);
});

it('never seeds reference data — that is game:import-data\'s job', function (): void {
    // REQ-06: balance data is authored in packages/game-data, validated in CI and
    // imported by its own command. A seeder that creates a building is a balance
    // number in PHP by another name (ADR-013).
    $forbidden = ['building', 'unit', 'technolog', 'game-data', 'game_data'];

    foreach (glob(database_path('seeders/*.php')) ?: [] as $file) {
        $contents = strtolower((string) file_get_contents($file));

        foreach ($forbidden as $needle) {
            expect(str_contains($contents, $needle))
                ->toBeFalse("{$file} references reference data ({$needle})");
        }
    }
});
```

Note: `DatabaseSeeder`'s existing docblock mentions "buildings, units, technologies"
in prose, which would trip the last test. Rewrite that docblock paragraph to keep the
meaning without the trigger words — replace the sentence

> *Reference data — buildings, units, technologies, heroes. Imported from
> `packages/game-data` by `php artisan game:import-data`, NOT seeded here.*

with

> *Reference content is imported by `php artisan game:import-data` from the shared
> data package, NOT seeded here. It is versioned content and production needs it too.
> See docs/database/conventions.md and ADR-013.*

Then run `./vendor/bin/pest --filter=Seeder` and `./vendor/bin/pint`.
  </action>

  <verify>
    <automated>cd /Users/sierra/Dev/Jogos/MmoMobile/apps/api && ./vendor/bin/pest --filter=Seeder && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress</automated>
  </verify>

  <acceptance_criteria>
    - `grep -q 'public function staff(): static' apps/api/database/factories/UserFactory.php` succeeds
    - `ls apps/api/tests/Feature/Database/SeederTest.php` succeeds
    - `cd apps/api && ./vendor/bin/pest --filter=Seeder` runs 6 tests, all passing
    - `grep -q 'is safe to run twice' apps/api/tests/Feature/Database/SeederTest.php` succeeds
    - `grep -q 'never seeds reference data' apps/api/tests/Feature/Database/SeederTest.php` succeeds
    - `grep -riE 'building|technolog|game-data' apps/api/database/seeders/` returns nothing
    - `cd apps/api && ./vendor/bin/pest` (full suite) exits 0
    - `cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G` reports 0 errors
  </acceptance_criteria>

  <done>Idempotency, the staff flag, the environment guards and the REQ-06 reference-data boundary are all covered by named tests that pass.</done>
</task>

<task type="auto">
  <name>Task 3: Reusable test fixtures — freezeClock, actingAsStaff and toBeApiError</name>
  <files>apps/api/tests/Pest.php, apps/api/tests/Feature/Platform/TestFixturesTest.php, .planning/codebase/TESTING.md</files>

  <read_first>
    - apps/api/tests/Pest.php (the file being modified — keep every existing binding and the toBeApiSuccess expectation)
    - apps/api/modules/Shared/Domain/Time/FrozenClock.php (`FrozenClock::at()` factory, `advanceSeconds()`)
    - apps/api/modules/Shared/Application/Error/ErrorCode.php (`httpStatus()`, `isRetryable()`, backed string values)
    - apps/api/tests/Feature/Platform/ErrorEnvelopeTest.php (the shape toBeApiError must assert)
    - apps/api/app/Providers/AppServiceProvider.php (Clock::class is a singleton bound to SystemClock)
    - .planning/codebase/TESTING.md (the file being modified — § "Custom expectations")
  </read_first>

  <action>
**1. Extend `apps/api/tests/Pest.php`.** Add these imports at the top (after the
existing `use` statements):

```php
use App\Models\User;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Domain\Time\Clock;
use Game\Shared\Domain\Time\FrozenClock;
```

Add a new expectation next to `toBeApiSuccess`:

```php
expect()->extend('toBeApiError', function (ErrorCode $code, ?int $status = null) {
    /** @var Illuminate\Testing\TestResponse $this */
    $this->value
        ->assertStatus($status ?? $code->httpStatus())
        ->assertJsonPath('error.code', $code->value)
        ->assertJsonMissingPath('data');

    return $this;
});
```

Then add a fixtures section at the end of the file:

```php
/*
|--------------------------------------------------------------------------
| Fixtures
|--------------------------------------------------------------------------
|
| Shared setup every phase needs. Anything copy-pasted into a third test file
| belongs here instead.
|
*/

/**
 * Pin the server clock. Every duration in this game is anchored to the injected
 * Clock, so freezing it is how timed gameplay becomes deterministic (ADR-006).
 */
function freezeClock(string $iso8601 = '2026-01-01T00:00:00+00:00'): FrozenClock
{
    $clock = FrozenClock::at($iso8601);

    app()->instance(Clock::class, $clock);

    return $clock;
}

/**
 * Authenticate as a back-office account.
 */
function actingAsStaff(?User $user = null): User
{
    $user ??= User::factory()->staff()->create();

    test()->actingAs($user);

    return $user;
}
```

**2. Create `apps/api/tests/Feature/Platform/TestFixturesTest.php`:**

```php
<?php

declare(strict_types=1);

use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Domain\Time\Clock;

it('freezes the clock the container hands to game code', function (): void {
    $clock = freezeClock('2026-06-01T12:00:00+00:00');

    expect(app(Clock::class))->toBe($clock)
        ->and(app(Clock::class)->now()->format(DATE_ATOM))->toBe('2026-06-01T12:00:00+00:00');
});

it('freezes the clock the api reports as server time', function (): void {
    freezeClock('2026-06-01T12:00:00+00:00');

    $this->getJson('/api/v1/health')
        ->assertJsonPath('meta.server_time', '2026-06-01T12:00:00+00:00');
});

it('advances a frozen clock by whole seconds', function (): void {
    $clock = freezeClock('2026-06-01T12:00:00+00:00');
    $clock->advanceSeconds(90);

    expect(app(Clock::class)->now()->format(DATE_ATOM))->toBe('2026-06-01T12:01:30+00:00');
});

it('authenticates a staff account for back-office assertions', function (): void {
    $user = actingAsStaff();

    expect($user->is_staff)->toBeTrue()
        ->and(auth()->id())->toBe($user->id);
});

it('asserts an error envelope by code', function (): void {
    expect($this->getJson('/api/v1/does-not-exist'))->toBeApiError(ErrorCode::NotFound);
});
```

**3. Update `.planning/codebase/TESTING.md` § "Custom expectations".** Replace that
section's body with:

```
`toBeApiSuccess()` asserts status plus the `data` key.
`toBeApiError(ErrorCode $code, ?int $status = null)` asserts the status implied by the
code, `error.code`, and that no `data` key leaked alongside the error.

Fixtures, also in `tests/Pest.php`:

| Helper | Does |
|--------|------|
| `freezeClock(string $iso8601)` | Binds a `FrozenClock` over `Clock::class` and returns it |
| `actingAsStaff(?User $user)` | Creates (or takes) a staff account and authenticates as it |

Assert on `error.code`, never on the message — the message is localised and may
change at any time.
```

Then run `./vendor/bin/pest`, `./vendor/bin/pint --test` and
`./vendor/bin/phpstan analyse --memory-limit=1G`. `tests/` is excluded from PHPStan
(DEBT-002), so the fixtures need no extra annotations, but the suite must be green.
  </action>

  <verify>
    <automated>cd /Users/sierra/Dev/Jogos/MmoMobile/apps/api && ./vendor/bin/pest --filter=TestFixtures && ./vendor/bin/pest && ./vendor/bin/pint --test</automated>
  </verify>

  <acceptance_criteria>
    - `grep -q "function freezeClock" apps/api/tests/Pest.php` succeeds
    - `grep -q "function actingAsStaff" apps/api/tests/Pest.php` succeeds
    - `grep -q "toBeApiError" apps/api/tests/Pest.php` succeeds
    - `grep -q "app()->instance(Clock::class" apps/api/tests/Pest.php` succeeds
    - `grep -q "toBeApiSuccess" apps/api/tests/Pest.php` still succeeds (nothing removed)
    - `grep -q "in('Feature')" apps/api/tests/Pest.php` and `grep -q "in('Postgres')" apps/api/tests/Pest.php` both still succeed
    - `ls apps/api/tests/Feature/Platform/TestFixturesTest.php` succeeds
    - `cd apps/api && ./vendor/bin/pest --filter=TestFixtures` runs 5 tests, all passing
    - `cd apps/api && ./vendor/bin/pest` exits 0 with no skipped tests
    - `grep -q 'toBeApiError' .planning/codebase/TESTING.md` succeeds
    - `grep -q 'freezeClock' .planning/codebase/TESTING.md` succeeds
  </acceptance_criteria>

  <done>Any later phase can freeze time, act as staff, and assert an error code in one line each, and the helpers are documented where an agent will look for them.</done>
</task>

</tasks>

<verification>
Docker must be running. Run from the repository root:

```bash
# Success criterion 3 — a browsable dataset, safe to run twice
make migrate-fresh
make seed
docker compose exec -T postgres psql -U dominion -d dominion \
  -c "select id, email, is_staff from users order by id;"
make seed
docker compose exec -T postgres psql -U dominion -d dominion \
  -c "select count(*) as users, count(*) filter (where is_staff) as staff from users;"
# -> users = 5, staff = 2, both times

# Browsable: the accounts show up in the back office
make dev
open http://localhost:8080/admin   # log in with ADMIN_SEED_EMAIL / password

# Gates
cd apps/api && ./vendor/bin/pest && ./vendor/bin/pint --test \
  && ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress
cd ../.. && make test-postgres

# The locked decision that was replaced is on the record
grep -A20 'Phase 01 — Seeder idempotency' docs/gsd/DECISIONS.md
```
</verification>

<success_criteria>
- `make seed` exits 0 twice in a row; user count and primary keys are identical both times
- `select count(*) filter (where is_staff) from users` returns 2 — the flag actually persisted
- The seeded accounts are listed in the Filament back office at `/admin`
- `grep -riE 'building|technolog|game-data' apps/api/database/seeders/` returns nothing (REQ-06)
- `./vendor/bin/pest` is green, including 6 seeder tests and 5 fixture tests
- `freezeClock`, `actingAsStaff` and `toBeApiError` exist in `tests/Pest.php` and are documented in `.planning/codebase/TESTING.md`
- `docs/gsd/DECISIONS.md` carries a Phase 01 entry explaining why `updateOrCreate` (locked in `01-CONTEXT.md` § Seeds) was replaced by `firstOrNew` + `forceFill`
- PHPStan level 8 reports 0 errors and Pint is clean
</success_criteria>

<output>
After completion, create `.planning/phases/01-engineering-foundation/01-03-SUMMARY.md`.
Paste the literal output of the two consecutive `make seed` runs and of the
`select count(*) ... filter (where is_staff)` query — idempotency claimed is not
idempotency shown.
</output>
