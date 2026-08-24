---
phase: 01-engineering-foundation
plan: 02
type: execute
wave: 2
depends_on: ["01-01"]
files_modified:
  - apps/api/database/migrations/0000_01_01_000000_enable_postgis_extension.php
  - apps/api/modules/Shared/Infrastructure/Database/GameTable.php
  - apps/api/modules/Shared/Infrastructure/Eloquent/Concerns/HasGameUlid.php
  - apps/api/modules/Platform/Interface/Http/HealthController.php
  - apps/api/phpunit.postgres.xml
  - apps/api/tests/Pest.php
  - apps/api/tests/Feature/Database/MigrationConventionsTest.php
  - apps/api/tests/Feature/Platform/HealthEndpointTest.php
  - apps/api/tests/Postgres/PostgisExtensionTest.php
  - docs/database/conventions.md
  - .planning/codebase/TESTING.md
autonomous: true
requirements: [REQ-12]

must_haves:
  truths:
    - "`make migrate` applies every migration against PostgreSQL with the PostGIS extension enabled"
    - "`make migrate-fresh` then `make migrate` runs forward and back with no error on PostgreSQL"
    - "The default `./vendor/bin/pest` suite still runs on SQLite in-memory on a host with no pdo_pgsql"
    - "`make test-postgres` executes at least one test against real PostgreSQL + PostGIS and it passes"
    - "`GET /api/v1/health` reports a `postgis` check that is true on PostgreSQL and absent on SQLite"
    - "A migration author has one documented way to declare a ULID primary key, a world_id column and a timed triple"
  artifacts:
    - path: "apps/api/database/migrations/0000_01_01_000000_enable_postgis_extension.php"
      provides: "CREATE EXTENSION IF NOT EXISTS postgis, sorted before every other migration"
      contains: "CREATE EXTENSION IF NOT EXISTS postgis"
    - path: "apps/api/modules/Shared/Infrastructure/Database/GameTable.php"
      provides: "entity() / worldScoped() / timed() migration helpers"
      exports: ["entity", "worldScoped", "timed"]
    - path: "apps/api/modules/Shared/Infrastructure/Eloquent/Concerns/HasGameUlid.php"
      provides: "ULID primary key behaviour for game entity models"
      contains: "HasUlids"
    - path: "apps/api/phpunit.postgres.xml"
      provides: "The PostgreSQL-only test configuration the host suite never loads"
      contains: "tests/Postgres"
    - path: "apps/api/tests/Postgres/PostgisExtensionTest.php"
      provides: "Proof that PostGIS is really enabled and geometry + GiST work"
      contains: "postgis_version"
    - path: "docs/database/conventions.md"
      provides: "One — and only one — documented way to declare id, world_id and the timed triple"
      contains: "GameTable::worldScoped"
      absent: "foreignUlid('world_id')->constrained()"
  key_links:
    - from: "docs/database/conventions.md:## Mandatory columns"
      to: "Game\\Shared\\Infrastructure\\Database\\GameTable"
      via: "the mandatory-columns snippet calls the helpers instead of hand-rolling a FK"
      pattern: "GameTable::entity"
    - from: "apps/api/modules/Platform/Interface/Http/HealthController.php"
      to: "postgis_version()"
      via: "driver-guarded readiness probe"
      pattern: "postgis_version"
    - from: "apps/api/phpunit.postgres.xml"
      to: "tests/Postgres"
      via: "dedicated testsuite, never referenced by phpunit.xml"
      pattern: "tests/Postgres"
    - from: "apps/api/tests/Pest.php"
      to: "RefreshDatabase in Postgres"
      via: "pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Postgres')"
      pattern: "in\\('Postgres'\\)"
---

<objective>
Prove PostgreSQL + PostGIS actually works, and give every later phase one documented
way to write a migration.

`.planning/codebase/CONCERNS.md` states plainly: PostGIS migrations have **never been
executed on this machine**. The host has no `pdo_pgsql` and the test suite runs on
SQLite in-memory, so a green suite proves nothing about Postgres. This plan closes
that gap: a guarded extension migration, a runtime health probe, a PostgreSQL-only
test configuration that the host suite cannot accidentally run, and the ULID / world_id
/ timed-triple conventions from `docs/database/conventions.md` expressed as code
instead of prose.

Purpose: satisfies ROADMAP Phase 01 success criterion 2, hardens criterion 5, and
gives Phase 05 (world architecture, PostGIS geometry) something to build on.

REQ-12 (full observability) is served concretely: the `postgis` entry this plan adds
to `/api/v1/health` makes "is the spatial extension actually loaded" a reported fact
instead of an assumption, on every environment, at runtime — not only at migrate time.
REQ-06 is deliberately **not** claimed here; balance data belongs to plans 01-03
(seeder boundary) and 01-04 (game-data validated in CI).

Scope note: this plan lists 11 files, above the usual 5–8. Six of them are tests,
config and documentation for the same three code artifacts (`GameTable`,
`HasGameUlid`, the extension migration). The code surface is small; splitting it
would separate a helper from the test that proves it.

Output: the extension migration, `GameTable`, `HasGameUlid`, `phpunit.postgres.xml`
and the first tests that only ever run against real PostgreSQL.
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
@.planning/codebase/ARCHITECTURE.md
@.planning/codebase/CONVENTIONS.md
@.planning/codebase/TESTING.md
@.planning/codebase/CONCERNS.md
@docs/database/conventions.md
@docs/adr/004-postgresql-postgis.md
@apps/api/tests/Architecture/ArchitectureTest.php

<interfaces>
<!-- Contracts the executor needs. Do not go hunting for these. -->

Existing PSR-4 autoload (apps/api/composer.json):
```
"App\\":                "app/"
"Game\\":               "modules/"
"Database\\Factories\\": "database/factories/"
"Database\\Seeders\\":   "database/seeders/"
```
So `Game\Shared\Infrastructure\Database\GameTable` lives at
`apps/api/modules/Shared/Infrastructure/Database/GameTable.php`.

Architecture rules that constrain this plan (tests/Architecture/ArchitectureTest.php):
- `arch('domain layer stays free of the framework')` ignores
  `Game\Shared\Interface`, `Game\Shared\Infrastructure` and `Game\Shared\Application`.
  **New Illuminate-using code therefore MUST go under `Game\Shared\Infrastructure`,
  never under `Game\Shared\Domain`.**
- `arch('strict types everywhere')->expect('Game')` — every new PHP file needs
  `declare(strict_types=1);`
- `arch('nothing debugs in production')` — no `dd`, `dump`, `var_dump`, `die`, `sleep`.

Existing HealthController shape (`apps/api/modules/Platform/Interface/Http/HealthController.php`):
```php
$checks = [
    'database' => $this->check(static fn () => DB::select('select 1')),
    'cache'    => $this->check(static fn () => cache()->set('health:ping', 1, 5)),
];
if (config('queue.default') === 'redis' || config('cache.default') === 'redis') {
    $checks['redis'] = $this->check(static fn () => Redis::connection()->command('ping', []));
}
$healthy = ! in_array($checks, ...);   // status 'ok' + 200, else 'degraded' + 503
```
`private function check(callable $probe): bool` already exists — reuse it.

Existing phpunit.xml pins the host suite to SQLite:
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```
PHPUnit `<env>` without `force="true"` does **not** overwrite an environment variable
that is already set. That is why the CI job can export `DB_CONNECTION=pgsql` and win.
The `phpunit.postgres.xml` created here uses `force="true"` so it wins unconditionally.

Makefile target created by plan 01-01 (already on disk when this plan runs):
```make
test-postgres:
	@$(COMPOSE) exec -T postgres psql -U dominion -d dominion -tc \
		"SELECT 1 FROM pg_database WHERE datname='dominion_test'" | grep -q 1 \
		|| $(COMPOSE) exec -T postgres createdb -U dominion dominion_test
	$(API) ./vendor/bin/pest --configuration=phpunit.postgres.xml
```
So the config file MUST be named exactly `apps/api/phpunit.postgres.xml` and the
database it targets MUST be `dominion_test`.
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: PostGIS extension migration and a driver-guarded postgis health check</name>
  <files>apps/api/database/migrations/0000_01_01_000000_enable_postgis_extension.php, apps/api/modules/Platform/Interface/Http/HealthController.php, apps/api/tests/Feature/Platform/HealthEndpointTest.php</files>

  <read_first>
    - apps/api/database/migrations/0001_01_01_000000_create_users_table.php (migration style: `return new class extends Migration`, `declare(strict_types=1)`, docblock explaining why)
    - apps/api/modules/Platform/Interface/Http/HealthController.php (the file being modified)
    - apps/api/tests/Feature/Platform/HealthEndpointTest.php (the file being modified)
    - docs/adr/004-postgresql-postgis.md (why the extension must exist in every environment)
    - .planning/codebase/CONCERNS.md (§ Environment — this is the limitation being closed)
  </read_first>

  <action>
**1. Create `apps/api/database/migrations/0000_01_01_000000_enable_postgis_extension.php`.**

The `0000_` prefix is deliberate: Laravel sorts migrations by filename, and `0000_`
sorts before the framework's `0001_01_01_000000_create_users_table.php`. This
guarantees the extension exists before any spatial migration in any later phase.

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enables PostGIS before anything else runs.
 *
 * Named `0000_` so it sorts ahead of every other migration — a spatial column in
 * a later phase must never be the thing that discovers the extension is missing
 * (ADR-004).
 *
 * Skipped on any non-PostgreSQL driver: the default test suite runs on SQLite
 * in-memory because the host has no pdo_pgsql (see .planning/codebase/CONCERNS.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis;');
    }

    public function down(): void
    {
        // Deliberately a no-op. Dropping the extension would cascade into every
        // geometry column in the database, and the postgis/postgis image ships it
        // regardless — so rolling back must not remove it. `migrate:rollback` in CI
        // depends on this being safe.
    }
};
```

**2. Add a `postgis` check to `HealthController::__invoke()`.**

Insert immediately after the `redis` block and before `$healthy = ...`:

```php
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Proof at runtime, not just at migration time: a database without the
            // extension cannot serve the world map (ADR-004). Guarded on the driver
            // because the test suite runs on SQLite.
            $checks['postgis'] = $this->check(static fn () => DB::select('select postgis_version()'));
        }
```

Do not change the envelope, the meta block, the status codes, or the `check()` helper.

**3. Extend `apps/api/tests/Feature/Platform/HealthEndpointTest.php`** with one test
appended at the end of the file:

```php
it('omits the postgis check on a non-postgres connection', function (): void {
    // The host suite runs on SQLite in-memory; probing postgis there would report
    // a permanently degraded API. See .planning/codebase/CONCERNS.md.
    expect(DB::connection()->getDriverName())->toBe('sqlite');

    $checks = $this->getJson('/api/v1/health')->json('data.checks');

    expect($checks)->not->toHaveKey('postgis')
        ->and($checks['database'])->toBeTrue();
});
```

Add `use Illuminate\Support\Facades\DB;` to the top of that test file (after
`declare(strict_types=1);`).

Then run `./vendor/bin/pint` and `./vendor/bin/phpstan analyse --memory-limit=1G` —
both must be clean before you consider this task done.
  </action>

  <verify>
    <automated>cd /Users/sierra/Dev/Jogos/MmoMobile/apps/api && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress && ./vendor/bin/pest --filter=Health</automated>
  </verify>

  <acceptance_criteria>
    - `ls apps/api/database/migrations/0000_01_01_000000_enable_postgis_extension.php` succeeds
    - `grep -q 'CREATE EXTENSION IF NOT EXISTS postgis' apps/api/database/migrations/0000_01_01_000000_enable_postgis_extension.php` succeeds
    - `grep -q "getDriverName() !== 'pgsql'" apps/api/database/migrations/0000_01_01_000000_enable_postgis_extension.php` succeeds
    - The migration's `down()` body contains no `DROP EXTENSION`
    - `grep -q 'postgis_version' apps/api/modules/Platform/Interface/Http/HealthController.php` succeeds
    - `grep -q "getDriverName() === 'pgsql'" apps/api/modules/Platform/Interface/Http/HealthController.php` succeeds
    - `grep -q 'omits the postgis check' apps/api/tests/Feature/Platform/HealthEndpointTest.php` succeeds
    - `cd apps/api && ./vendor/bin/pest --filter=Health` exits 0 (the SQLite suite is still green)
    - `cd apps/api && ./vendor/bin/pint --test` exits 0
    - `cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G` reports 0 errors
  </acceptance_criteria>

  <done>PostGIS is created by the first migration on PostgreSQL, is skipped on SQLite, and the health endpoint reports it as a dependency check on PostgreSQL only.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: GameTable migration helpers and the HasGameUlid model trait</name>
  <files>apps/api/modules/Shared/Infrastructure/Database/GameTable.php, apps/api/modules/Shared/Infrastructure/Eloquent/Concerns/HasGameUlid.php, apps/api/tests/Feature/Database/MigrationConventionsTest.php</files>

  <read_first>
    - docs/database/conventions.md (§ Identifiers, § Mandatory columns, § Time, § Indexes — the rules being encoded)
    - .planning/codebase/CONVENTIONS.md (§ PHP — final classes, strict_types, return types)
    - apps/api/modules/Shared/Domain/Time/Clock.php (house style for a Shared kernel file: docblock says *why*)
    - apps/api/tests/Architecture/ArchitectureTest.php (`Game\Shared\Infrastructure` is exempt from the no-framework rule; `Game\Shared\Domain` is not)
    - apps/api/composer.json (PSR-4 map — `Game\` => `modules/`)
  </read_first>

  <behavior>
    - `GameTable::entity($table)` adds a `char(26)` ULID primary key named `id` plus `created_at`/`updated_at`
    - `GameTable::worldScoped($table)` adds a non-null `world_id` ULID column with an index (no FK — the `worlds` table does not exist until Phase 05)
    - `GameTable::timed($table)` adds nullable `started_at`, `finishes_at`, `completed_at` plus a composite index on `(finishes_at, completed_at)` so the reconciler can find overdue rows
    - A model using `HasGameUlid` generates a 26-character ULID on create, reports `getKeyType() === 'string'` and `getIncrementing() === false`
    - All three helpers work on SQLite (the default suite) as well as PostgreSQL
  </behavior>

  <action>
**1. Create `apps/api/modules/Shared/Infrastructure/Database/GameTable.php`.**

```php
<?php

declare(strict_types=1);

namespace Game\Shared\Infrastructure\Database;

use Illuminate\Database\Schema\Blueprint;

/**
 * The migration conventions of docs/database/conventions.md, as code.
 *
 * Prose conventions drift; a helper that every migration calls does not. These
 * three cover the shapes that repeat across every gameplay table: a ULID-keyed
 * entity, world scoping, and a timed operation.
 *
 * Deliberately NOT here: resource columns. Those need a `CHECK (col >= 0)`
 * constraint, which SQLite cannot add via ALTER TABLE, and the default suite runs
 * on SQLite. They land with the economy module.
 */
final class GameTable
{
    /**
     * Client-addressable game entity: ULID primary key plus UTC timestamps.
     *
     * ULID rather than auto-increment so ids are non-enumerable and time-sortable,
     * and safe to expose in an API response (ADR-016).
     */
    public static function entity(Blueprint $table): void
    {
        $table->ulid('id')->primary();
        $table->timestamps();
    }

    /**
     * World scoping. Every gameplay query must filter on this column (ADR-012);
     * omitting it is a cross-world data leak.
     *
     * No foreign key constraint yet — the `worlds` table arrives in Phase 05, and
     * a constraint added here would make this helper unusable until then.
     */
    public static function worldScoped(Blueprint $table): void
    {
        $table->ulid('world_id')->index();
    }

    /**
     * The timed-operation triple.
     *
     * `completed_at` staying null is what makes a completion job idempotent and
     * what lets the reconciler find rows whose job never ran. Completion is never
     * inferred from `finishes_at` alone.
     */
    public static function timed(Blueprint $table): void
    {
        $table->timestamp('started_at')->nullable();
        $table->timestamp('finishes_at')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->index(['finishes_at', 'completed_at']);
    }
}
```

**2. Create `apps/api/modules/Shared/Infrastructure/Eloquent/Concerns/HasGameUlid.php`.**

```php
<?php

declare(strict_types=1);

namespace Game\Shared\Infrastructure\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * ULID primary keys for client-addressable game entities.
 *
 * Pairs with GameTable::entity(). Kept as one trait so no model has to remember
 * to also flip $keyType and $incrementing — forgetting either silently produces a
 * model that cannot find its own row.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasGameUlid
{
    use HasUlids;

    public function getKeyType(): string
    {
        return 'string';
    }

    public function getIncrementing(): bool
    {
        return false;
    }
}
```

The `@mixin` docblock is required — without it PHPStan level 8 cannot resolve the
Eloquent methods the trait inherits.

**3. Create `apps/api/tests/Feature/Database/MigrationConventionsTest.php`** (create
the `tests/Feature/Database/` directory). This runs on SQLite in the default suite.

```php
<?php

declare(strict_types=1);

use Game\Shared\Infrastructure\Database\GameTable;
use Game\Shared\Infrastructure\Eloquent\Concerns\HasGameUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('convention_probes', function (Blueprint $table): void {
        GameTable::entity($table);
        GameTable::worldScoped($table);
        GameTable::timed($table);
        $table->string('label');
    });
});

afterEach(function (): void {
    Schema::dropIfExists('convention_probes');
});

it('gives a game entity a ulid primary key and utc timestamps', function (): void {
    expect(Schema::hasColumns('convention_probes', ['id', 'created_at', 'updated_at']))->toBeTrue();
});

it('gives every gameplay table a world_id', function (): void {
    expect(Schema::hasColumn('convention_probes', 'world_id'))->toBeTrue();
});

it('gives a timed operation the started/finishes/completed triple', function (): void {
    expect(Schema::hasColumns('convention_probes', ['started_at', 'finishes_at', 'completed_at']))->toBeTrue();
});

it('generates a 26 character ulid primary key on create', function (): void {
    $model = new class extends Model
    {
        use HasGameUlid;

        protected $table = 'convention_probes';

        protected $fillable = ['world_id', 'label'];
    };

    $saved = $model->newInstance(['world_id' => (string) \Illuminate\Support\Str::ulid(), 'label' => 'probe']);
    $saved->save();

    expect($saved->getKey())->toBeString()
        ->and(strlen((string) $saved->getKey()))->toBe(26)
        ->and($saved->getKeyType())->toBe('string')
        ->and($saved->getIncrementing())->toBeFalse();
});
```

If the anonymous-class model trips `Model::preventAccessingMissingAttributes()` or
Larastan, give it an explicit `public $timestamps = true;` and add the class to the
PHPStan ignore list in `apps/api/phpstan.neon` **only as a last resort** — prefer
fixing the model definition.

Run `./vendor/bin/pint`, `./vendor/bin/phpstan analyse --memory-limit=1G` and
`./vendor/bin/pest` before finishing.
  </action>

  <verify>
    <automated>cd /Users/sierra/Dev/Jogos/MmoMobile/apps/api && ./vendor/bin/pest --filter=MigrationConventions && ./vendor/bin/pest --group=arch && ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress && ./vendor/bin/pint --test</automated>
  </verify>

  <acceptance_criteria>
    - `ls apps/api/modules/Shared/Infrastructure/Database/GameTable.php` succeeds
    - `ls apps/api/modules/Shared/Infrastructure/Eloquent/Concerns/HasGameUlid.php` succeeds
    - `grep -q 'public static function entity(Blueprint $table): void' apps/api/modules/Shared/Infrastructure/Database/GameTable.php` succeeds
    - `grep -q 'public static function worldScoped(Blueprint $table): void' apps/api/modules/Shared/Infrastructure/Database/GameTable.php` succeeds
    - `grep -q 'public static function timed(Blueprint $table): void' apps/api/modules/Shared/Infrastructure/Database/GameTable.php` succeeds
    - `grep -q "index(\['finishes_at', 'completed_at'\])" apps/api/modules/Shared/Infrastructure/Database/GameTable.php` succeeds
    - `grep -q 'use HasUlids;' apps/api/modules/Shared/Infrastructure/Eloquent/Concerns/HasGameUlid.php` succeeds
    - `grep -q '@mixin' apps/api/modules/Shared/Infrastructure/Eloquent/Concerns/HasGameUlid.php` succeeds
    - Neither new file lives under `modules/Shared/Domain/` (the arch test forbids Illuminate there)
    - Both new files start with `declare(strict_types=1);`
    - `cd apps/api && ./vendor/bin/pest --filter=MigrationConventions` runs 4 tests, all passing
    - `cd apps/api && ./vendor/bin/pest --group=arch` exits 0
    - `cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G` reports 0 errors
  </acceptance_criteria>

  <done>A migration author has one call each for a ULID entity, world scoping and a timed triple, all covered by tests that run on the SQLite host suite.</done>
</task>

<task type="auto">
  <name>Task 3: A PostgreSQL-only test configuration the host suite can never load</name>
  <files>apps/api/phpunit.postgres.xml, apps/api/tests/Pest.php, apps/api/tests/Postgres/PostgisExtensionTest.php, .planning/codebase/TESTING.md, docs/database/conventions.md</files>

  <read_first>
    - apps/api/phpunit.xml (the SQLite host config — read every `<env>` entry; the new file mirrors it)
    - apps/api/tests/Pest.php (the file being modified — note the existing `->in('Feature')` / `->in('Architecture')` bindings)
    - .planning/codebase/TESTING.md (the file being modified — § "The suite runs on SQLite")
    - docs/database/conventions.md (the file being modified — read § "Mandatory columns" in full; it currently contradicts what this task documents, and reconciling it is step 5a)
    - Makefile (§ `test-postgres` — this task must match the filename and database it expects)
  </read_first>

  <action>
`phpunit.xml` declares only the `Unit` and `Feature` testsuites, so a `tests/Postgres/`
directory is invisible to `./vendor/bin/pest`. That is the isolation mechanism — no
group filtering, no ambiguity about whether a CLI `--group` overrides an XML exclude.
Do **not** modify `phpunit.xml`.

**1. Create `apps/api/phpunit.postgres.xml`:**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!--
  PostgreSQL + PostGIS test configuration.

  The default suite (phpunit.xml) runs on SQLite in-memory because the host has no
  pdo_pgsql. Nothing here is reachable from `./vendor/bin/pest` — tests/Postgres is
  not in any testsuite of phpunit.xml. Run it with:

      make test-postgres                                  (local, through Docker)
      ./vendor/bin/pest --configuration=phpunit.postgres.xml   (CI)

  `force="true"` on the database vars is required: these must beat both phpunit.xml
  and whatever the ambient environment says.
-->
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
>
    <testsuites>
        <testsuite name="Postgres">
            <directory>tests/Postgres</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="APP_MAINTENANCE_DRIVER" value="file"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="BROADCAST_CONNECTION" value="null"/>
        <env name="CACHE_STORE" value="array"/>
        <env name="DB_CONNECTION" value="pgsql" force="true"/>
        <env name="DB_DATABASE" value="dominion_test" force="true"/>
        <env name="DB_URL" value="" force="true"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="PULSE_ENABLED" value="false"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
        <env name="NIGHTWATCH_ENABLED" value="false"/>
    </php>
</phpunit>
```

`DB_HOST`, `DB_USERNAME` and `DB_PASSWORD` are intentionally not forced: inside Docker
they come from `apps/api/.env` (`postgres` / `dominion` / `dominion`), and in CI the
workflow exports `DB_HOST=127.0.0.1` with the same credentials.

**2. Add the Pest binding in `apps/api/tests/Pest.php`.** After the existing
`pest()->extend(TestCase::class)->in('Architecture');` line add:

```php
/*
| PostgreSQL-only tests. Loaded only by phpunit.postgres.xml — the default suite's
| testsuites do not include this directory. See .planning/codebase/TESTING.md.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Postgres');
```

**3. Create `apps/api/tests/Postgres/PostgisExtensionTest.php`:**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('runs against real postgresql, not sqlite', function (): void {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
})->group('postgres');

it('has the postgis extension enabled', function (): void {
    $row = DB::selectOne('select postgis_version() as version');

    expect($row?->version)->toBeString()->not->toBeEmpty();
})->group('postgres');

it('applied the extension migration before every other migration', function (): void {
    $first = DB::table('migrations')->orderBy('id')->value('migration');

    expect($first)->toBe('0000_01_01_000000_enable_postgis_extension');
})->group('postgres');

it('creates a geometry column with a gist index', function (): void {
    Schema::create('postgis_probes', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->geometry('boundary', 'polygon', 4326);
    });
    DB::statement('CREATE INDEX postgis_probes_boundary_gist ON postgis_probes USING GIST (boundary)');

    $indexes = DB::select("select indexdef from pg_indexes where tablename = 'postgis_probes'");
    $definitions = implode("\n", array_map(static fn (object $i): string => (string) $i->indexdef, $indexes));

    expect($definitions)->toContain('USING gist');

    Schema::dropIfExists('postgis_probes');
})->group('postgres');

it('round-trips a 26 character ulid primary key', function (): void {
    Schema::create('ulid_probes', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('label');
    });

    $id = (string) Str::ulid();
    DB::table('ulid_probes')->insert(['id' => $id, 'label' => 'probe']);

    expect(DB::table('ulid_probes')->where('id', $id)->value('label'))->toBe('probe');

    Schema::dropIfExists('ulid_probes');
})->group('postgres');
```

**4. Update `.planning/codebase/TESTING.md`.** In § "The suite runs on SQLite",
replace the sentence *"Those tests are tagged and run in CI against real Postgres +
PostGIS."* with:

```
Those tests live in `tests/Postgres/` and are loaded **only** by
`apps/api/phpunit.postgres.xml`, which forces `DB_CONNECTION=pgsql` and
`DB_DATABASE=dominion_test`. `phpunit.xml` does not declare that directory as a
testsuite, so `./vendor/bin/pest` can never run them by accident.

    make test-postgres    # local, through Docker
    ./vendor/bin/pest --configuration=phpunit.postgres.xml    # CI

When you add PostGIS work, add the test there. Do not assume SQLite coverage.
```

**5. Update `docs/database/conventions.md`.** Two edits, and **5a is not optional** —
without it the file documents two incompatible schemas for the same column.

**5a. Rewrite § "Mandatory columns" so it stops contradicting the helpers.**

Today that section reads:

````
## Mandatory columns

Every gameplay table carries:

```php
$table->ulid('id')->primary();
$table->foreignUlid('world_id')->constrained()->cascadeOnDelete();
$table->timestamps();   // created_at, updated_at — UTC
```

`world_id` is non-negotiable (ADR-012). **Every query must filter on it.** A query
that omits it is a cross-world data leak.
````

That snippet is not merely stale, it is **broken**: `->constrained()` emits a real
foreign key against a `worlds` table that does not exist until Phase 05, so a
migration author who follows it literally today fails at `migrate` time. Replace the
whole section — heading kept, body replaced — with:

````
## Mandatory columns

Every gameplay table carries a ULID primary key, `world_id`, and UTC timestamps.
Declare them through the helpers rather than by hand:

```php
use Game\Shared\Infrastructure\Database\GameTable;

GameTable::entity($table);        // ulid('id')->primary() + timestamps() — UTC
GameTable::worldScoped($table);   // ulid('world_id')->index()
```

`world_id` is non-negotiable (ADR-012). **Every query must filter on it.** A query
that omits it is a cross-world data leak.

`worldScoped()` deliberately emits an **indexed column, not a foreign key**: the
`worlds` table does not exist until Phase 05, and a foreign-key constraint declared
before its target exists fails at migrate time. When Phase 05 creates `worlds`, it
adds the constraint inside `worldScoped()` — one place — and every existing table
picks it up through a follow-up migration. Until then, do not hand-roll a `world_id`
foreign key: it will not run.
````

After this edit neither `foreignUlid` nor `constrained()` may appear anywhere in
`docs/database/conventions.md` — not in the code block, and not in the prose. That is
what makes the acceptance criteria below a real check rather than a hopeful one, and
what lets a future lint grep for the forbidden shape across all of `docs/`.

**5b. Add a new section immediately before § "Soft deletes":**

````
## The helpers

Do not hand-roll the shapes above. `Game\Shared\Infrastructure\Database\GameTable`
encodes them, and § "Mandatory columns" already calls two of the three:

| Call | Adds |
|------|------|
| `GameTable::entity($table)` | `ulid('id')->primary()` + `timestamps()` |
| `GameTable::worldScoped($table)` | `ulid('world_id')->index()` |
| `GameTable::timed($table)` | `started_at`, `finishes_at`, `completed_at` + `index(['finishes_at','completed_at'])` |

Models for ULID-keyed entities use
`Game\Shared\Infrastructure\Eloquent\Concerns\HasGameUlid`, which sets the key type
and disables auto-increment in one place.

`world_id` carries no foreign key constraint until the `worlds` table exists
(Phase 05) — see § "Mandatory columns". Resource columns and their
`CHECK (col >= 0)` constraints are not in `GameTable`: SQLite cannot add a constraint
via `ALTER TABLE`, and the default suite runs on SQLite.
````

Change nothing else in the file — § Identifiers, § Naming, § Time, § Money, § Enums,
§ Indexes, § Soft deletes, § Concurrency, § Audit and § JSONB stay byte-identical.

Then run `./vendor/bin/pint --test` and `make test-postgres`.
  </action>

  <verify>
    <automated>cd /Users/sierra/Dev/Jogos/MmoMobile && ! grep -q "foreignUlid('world_id')" docs/database/conventions.md && grep -q 'GameTable::worldScoped' docs/database/conventions.md && grep -A6 '^## Mandatory columns' docs/database/conventions.md | grep -q 'GameTable::entity' && make test-postgres</automated>
  </verify>

  <acceptance_criteria>
    - `ls apps/api/phpunit.postgres.xml` succeeds
    - `grep -q 'tests/Postgres' apps/api/phpunit.postgres.xml` succeeds
    - `grep -q 'value="pgsql" force="true"' apps/api/phpunit.postgres.xml` succeeds
    - `grep -q 'value="dominion_test" force="true"' apps/api/phpunit.postgres.xml` succeeds
    - `grep -q "in('Postgres')" apps/api/tests/Pest.php` succeeds
    - `grep -c "group('postgres')" apps/api/tests/Postgres/PostgisExtensionTest.php` returns 5
    - `apps/api/phpunit.xml` is byte-identical to its state before this task (`git diff --exit-code apps/api/phpunit.xml` succeeds)
    - `cd apps/api && ./vendor/bin/pest` output does NOT contain `PostgisExtension` (the host suite never loads it)
    - `make test-postgres` exits 0 and reports 5 passing tests
    - `grep -q 'phpunit.postgres.xml' .planning/codebase/TESTING.md` succeeds
    - `grep -q 'GameTable::entity' docs/database/conventions.md` succeeds
    - `grep -q 'GameTable::worldScoped' docs/database/conventions.md` succeeds
    - **The contradiction is gone:** `grep -q "foreignUlid('world_id')" docs/database/conventions.md` returns **nothing** (exits 1), and so does `grep -q 'constrained()' docs/database/conventions.md`
    - `grep -A6 '^## Mandatory columns' docs/database/conventions.md | grep -q 'GameTable::entity'` succeeds — the mandatory-columns section itself now calls the helper
    - `grep -c '^## Mandatory columns' docs/database/conventions.md` returns 1 (the section was rewritten in place, not duplicated)
    - `grep -q 'Phase 05' docs/database/conventions.md` succeeds (the deferred FK is stated, not implied)
    - `grep -c '^## ' docs/database/conventions.md` returns 12 — the eleven original sections plus the new "The helpers"; nothing was dropped
  </acceptance_criteria>

  <done>PostGIS-only tests exist, run green against real PostgreSQL through `make test-postgres`, are structurally impossible for the SQLite host suite to pick up, and `docs/database/conventions.md` now describes exactly one way to declare `world_id`.</done>
</task>

</tasks>

<verification>
Docker must be running (plan 01-01 made it canonical). Run from the repository root:

```bash
# The host suite still works without pdo_pgsql — this is non-negotiable
cd apps/api && ./vendor/bin/pest && ./vendor/bin/pint --test \
  && ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress
cd ../..

# Success criterion 2 — migrations against real PostgreSQL + PostGIS
make migrate
make migrate-fresh && make migrate      # forward, back, forward
docker compose exec -T postgres psql -U dominion -d dominion -c "select postgis_version();"

# The PostGIS-only suite
make test-postgres

# Criterion 5 now includes postgis
make health
docker compose exec -T api curl -fsS http://localhost:8000/api/v1/health
# -> must contain "postgis":true
```
</verification>

<success_criteria>
- `make migrate` applies every migration against PostgreSQL with PostGIS enabled
- `make migrate-fresh && make migrate` runs clean forward and back
- `./vendor/bin/pest` on the host is still green on SQLite and never touches tests/Postgres
- `make test-postgres` runs 5 tests against real PostgreSQL and passes
- The health endpoint reports `"postgis":true` inside Docker and omits the key on SQLite
- `GameTable` and `HasGameUlid` exist under `Game\Shared\Infrastructure` with tests
- `docs/database/conventions.md` documents one way to declare `world_id`: section "Mandatory columns" calls `GameTable`, and `foreignUlid('world_id')->constrained()` appears nowhere in the file
- PHPStan level 8 reports 0 errors and Pint is clean
</success_criteria>

<output>
After completion, create `.planning/phases/01-engineering-foundation/01-02-SUMMARY.md`.
Paste the literal output of `make migrate`, `make test-postgres` and the
`select postgis_version();` query. `.planning/codebase/CONCERNS.md` says PostGIS has
never run on this machine — the SUMMARY is where that stops being true, so show the
evidence rather than asserting it.
</output>
