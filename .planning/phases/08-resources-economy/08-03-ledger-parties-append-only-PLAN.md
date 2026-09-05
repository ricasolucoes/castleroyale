---
phase: 08-resources-economy
plan: 03
type: execute
wave: 3
depends_on: ["08-02"]
files_modified:
  - apps/api/database/migrations/2026_09_06_000100_add_ledger_parties_to_economy_ledger.php
  - apps/api/modules/Economy/Domain/LedgerParty.php
  - apps/api/modules/Economy/Infrastructure/EconomyLedger.php
  - apps/api/modules/Economy/Application/CityEconomyService.php
  - apps/api/modules/Player/Application/GameBootstrapService.php
  - apps/api/modules/Construction/Application/BuildingUpgradeService.php
  - apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php
  - apps/api/tests/Feature/Economy/WarehouseCapacityTest.php
  - apps/api/tests/Feature/Economy/LedgerAuditTrailTest.php
  - apps/api/tests/Architecture/ArchitectureTest.php
autonomous: true
requirements: [REQ-02, REQ-09]

must_haves:
  truths:
    - "Every resource mutation writes a ledger row naming where the value came from and where it went"
    - "No ledger row can be updated or deleted once written — the trail is append-only"
    - "Every ledger write goes through one guarded factory, so a new mutation site cannot forget the parties"
    - "An operator reading the ledger can tell a production accrual from a construction spend from a starter grant without guessing"
  artifacts:
    - path: "apps/api/database/migrations/2026_09_06_000100_add_ledger_parties_to_economy_ledger.php"
      provides: "source and destination columns plus a backfill of pre-existing rows"
      contains: "destination"
    - path: "apps/api/modules/Economy/Domain/LedgerParty.php"
      provides: "The typed counterparty value object: city:{ulid} or system:{name}"
      contains: "final readonly class LedgerParty"
    - path: "apps/api/modules/Economy/Infrastructure/EconomyLedger.php"
      provides: "EconomyLedger::record() as the only write path, plus append-only model guards"
      contains: "public static function record"
    - path: "apps/api/tests/Feature/Economy/LedgerAuditTrailTest.php"
      provides: "Proof every row carries both parties in the documented direction and cannot be mutated"
      min_lines: 90
  key_links:
    - from: "apps/api/modules/Economy/Application/CityEconomyService.php"
      to: "EconomyLedger::record"
      via: "every accrual, debit and credit write"
      pattern: "EconomyLedger::record\\("
    - from: "apps/api/modules/Player/Application/GameBootstrapService.php"
      to: "EconomyLedger::record"
      via: "starter grant rows"
      pattern: "EconomyLedger::record\\("
    - from: "apps/api/modules/Construction/Application/BuildingUpgradeService.php"
      to: "CityEconomyService::debitLocked"
      via: "LedgerParty::system('construction') as the spend destination"
      pattern: "LedgerParty::system\\('construction'\\)"
---

<objective>
Close ROADMAP criterion 2. `08-CONTEXT.md` locks it in one sentence: "EVERY resource
mutation writes a ledger row: source, destination, resource, amount, reason,
reference, created_at, economy_version." Seven of those eight exist today. `source`
and `destination` do not exist at all — not as columns, not as values.

Purpose: without the two parties, the ledger records that value moved but not
between whom. A plunder (Phase 19) and a trade (Phase 27) are transfers, not faucets,
and the conservation property tests those phases must run are unwritable against a
ledger that cannot name both ends. Adding the columns later, after four write sites
have become forty, is the expensive version of this change.

Output: two non-null columns with a documented direction table, a `LedgerParty` value
object so a party cannot be a free-form string, a single guarded `EconomyLedger::record()`
write path enforced by an architecture test, and append-only guards on the model.
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
@.planning/phases/08-resources-economy/08-02-capacity-ceiling-strict-credit-SUMMARY.md

<interfaces>
<!-- Everything the executor needs. There are exactly FOUR ledger write sites in the
     whole repository. They were located with `grep -rn "EconomyLedger" apps/api`. -->

**Site 1** — `apps/api/modules/Player/Application/GameBootstrapService.php`, inside the
`if ($city === null)` block, reason `'starter.grant'`, reference `$account->getKey()`.

**Site 2** — `CityEconomyService::accrueLocked()`, reason `'production.elapsed'`,
reference `$city->getKey()`.

**Site 3** — `CityEconomyService::debitLocked()`, reason and reference are parameters.
Its only production caller is `BuildingUpgradeService::start()`, which passes
`'building.upgrade'` and the request's `Idempotency-Key`.

**Site 4** — `CityEconomyService::creditLocked()`, reason and reference are parameters.
No production caller yet (Phase 16 will be the first); called by tests.

Current model, `apps/api/modules/Economy/Infrastructure/EconomyLedger.php`:

```php
final class EconomyLedger extends Model
{
    use HasGameUlid;
    protected $table = 'economy_ledger';
    protected $fillable = [
        'world_id', 'city_id', 'resource', 'amount', 'overflow_amount',
        'reason', 'reference', 'economy_version',
    ];
    protected function casts(): array
    {
        return ['amount' => 'integer', 'overflow_amount' => 'integer', 'economy_version' => 'integer'];
    }
}
```

Current table, from `apps/api/database/migrations/2026_08_27_010400_*`:

```php
GameTable::entity($table);          // ULID id + created_at/updated_at
GameTable::worldScoped($table);     // world_id
$table->foreignUlid('city_id')->constrained('cities')->cascadeOnDelete();
$table->string('resource', 16);
$table->bigInteger('amount');
$table->unsignedBigInteger('overflow_amount')->default(0);
$table->string('reason');
$table->string('reference')->nullable();
$table->unsignedInteger('economy_version');
$table->index(['world_id', 'city_id', 'resource']);
```

Architecture test facts (`apps/api/tests/Architecture/ArchitectureTest.php`, 128 lines):
`arch('domain layer stays free of the framework')` ignores `Game\Economy\Application`
and `Game\Economy\Infrastructure` but NOT `Game\Economy\Domain`, so `LedgerParty` must
only use `Game` plus the whitelisted PHP classes — `InvalidArgumentException` is on
that whitelist. Every `arch()` block in the file ends with `->group('arch')`.

The suite runs SQLite in-memory (`phpunit.xml` pins `DB_CONNECTION=sqlite`,
`DB_DATABASE=:memory:`), so the migration must not use PostgreSQL-only syntax.
</interfaces>
</context>

<decisions>
**Locked decision — party format is `{kind}:{identifier}`, kind ∈ {`city`, `system`}.**
A city party is `city:{ULID}` (5 + 26 = 31 chars). A system party is `system:{name}`
with `name` matching `/^[a-z][a-z0-9_.]{0,50}$/`. Both columns are `string(64)`.
Chosen over two nullable FK columns because half the counterparties are not rows —
production, the starter grant and the construction sink are systemic, and modelling
them as nullable FKs would make "null" mean four different things.

**Locked decision — the direction table.** These are the only values Phase 08 writes.
A later phase adding a faucet or sink adds a row here, in this table, in its own plan.

| reason | source | destination |
|---|---|---|
| `starter.grant` | `system:starter` | `city:{city_id}` |
| `production.elapsed` | `system:production` | `city:{city_id}` |
| `building.upgrade` (debit) | `city:{city_id}` | `system:construction` |
| any other `debitLocked` spend | `city:{city_id}` | caller-supplied party |
| any other `creditLocked` grant | caller-supplied party | `city:{city_id}` |

**Locked decision — an over-cap credit keeps one row, and the discarded tail does NOT
get its own `system:void` destination row.** The row's parties describe the intended
flow; `overflow_amount` names what the cap refused to let through. Splitting it would
break the already-passing test
`it('caps server grants and records discarded overflow in the ledger')` and complicate
criterion 5's reconciliation, which sums `amount` alone. Document this in the model's
docblock so nobody re-derives it.

**Locked decision — the columns are NOT NULL with a `'system:legacy'` default purely
so the migration is safe on SQLite and PostgreSQL alike.** The default is a migration
mechanism, not an allowed runtime value: `LedgerAuditTrailTest` asserts no row written
by gameplay carries it, and the architecture test forbids any write path that could
skip the parties.
</decisions>

<tasks>

<task type="auto">
  <name>Task 1: Add source and destination columns with a direction-aware backfill</name>
  <files>apps/api/database/migrations/2026_09_06_000100_add_ledger_parties_to_economy_ledger.php</files>
  <read_first>
    - apps/api/database/migrations/2026_08_27_010400_*.php (the `economy_ledger` create migration — match its style, its use of `GameTable`, and its index conventions)
    - apps/api/modules/Shared/Infrastructure/Database/GameTable.php (what `entity()` and `worldScoped()` actually add)
    - .planning/codebase/CONVENTIONS.md (§ Database — index every foreign key; no native PostgreSQL enum types)
    - .planning/codebase/CONCERNS.md (§ Environment — the default suite is SQLite; PostgreSQL-only syntax cannot be covered by it)
  </read_first>
  <action>
Create `apps/api/database/migrations/2026_09_06_000100_add_ledger_parties_to_economy_ledger.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The default exists so the column can be added NOT NULL on both SQLite and
        // PostgreSQL without a two-step nullable dance. It is a migration mechanism,
        // not a runtime value: every write goes through EconomyLedger::record(),
        // which always supplies both parties, and a test asserts no gameplay row
        // carries 'system:legacy'.
        Schema::table('economy_ledger', function (Blueprint $table): void {
            $table->string('source', 64)->default('system:legacy')->after('city_id');
            $table->string('destination', 64)->default('system:legacy')->after('source');
            $table->index(['world_id', 'source']);
            $table->index(['world_id', 'destination']);
        });

        // Rows written before this migration still record a real direction: a credit
        // arrived at the city, a debit left it. The faucet or sink on the other end
        // was never captured, so it is named honestly as legacy rather than guessed.
        DB::table('economy_ledger')->where('amount', '>=', 0)->update([
            'source' => 'system:legacy',
            'destination' => DB::raw("'city:' || city_id"),
        ]);

        DB::table('economy_ledger')->where('amount', '<', 0)->update([
            'source' => DB::raw("'city:' || city_id"),
            'destination' => 'system:legacy',
        ]);
    }

    public function down(): void
    {
        Schema::table('economy_ledger', function (Blueprint $table): void {
            $table->dropIndex(['world_id', 'source']);
            $table->dropIndex(['world_id', 'destination']);
            $table->dropColumn(['source', 'destination']);
        });
    }
};
```

`||` is the string-concatenation operator in both SQLite and PostgreSQL, so the
backfill runs unchanged on the default suite and in Docker/CI. Do not use `CONCAT()`
— SQLite does not have it.

Verify the migration runs forward and back:

```bash
cd apps/api && php artisan migrate:fresh --env=testing && php artisan migrate:rollback --step=1 --env=testing
```

If `--env=testing` is not wired for artisan in this repo, the Pest suite's
`RefreshDatabase` running the full migration set is sufficient proof of `up()`; in
that case verify `down()` by reading it for symmetry with `up()` and note it in the
summary.
  </action>
  <verify>
    <automated>cd apps/api && ./vendor/bin/pest --filter=CityEconomyFoundation</automated>
  </verify>
  <acceptance_criteria>
    - `test -f apps/api/database/migrations/2026_09_06_000100_add_ledger_parties_to_economy_ledger.php` succeeds.
    - `grep -q "string('source', 64)" apps/api/database/migrations/2026_09_06_000100_add_ledger_parties_to_economy_ledger.php` succeeds.
    - `grep -q "string('destination', 64)" apps/api/database/migrations/2026_09_06_000100_add_ledger_parties_to_economy_ledger.php` succeeds.
    - `grep -q "index(\['world_id', 'source'\])" apps/api/database/migrations/2026_09_06_000100_add_ledger_parties_to_economy_ledger.php` succeeds.
    - The migration file contains `'city:' || city_id` and does NOT contain `CONCAT(`.
    - `cd apps/api && ./vendor/bin/pest --filter=CityEconomyFoundation` exits 0 — the schema change did not break the existing economy tests.
    - `cd apps/api && ./vendor/bin/pint --test` exits 0.
  </acceptance_criteria>
  <done>`economy_ledger` has indexed, non-null `source` and `destination` columns, and every pre-existing row records the direction its sign implies.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Add LedgerParty, make EconomyLedger::record the only write path, and convert all four sites</name>
  <files>apps/api/modules/Economy/Domain/LedgerParty.php, apps/api/modules/Economy/Infrastructure/EconomyLedger.php, apps/api/modules/Economy/Application/CityEconomyService.php, apps/api/modules/Player/Application/GameBootstrapService.php, apps/api/modules/Construction/Application/BuildingUpgradeService.php, apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php</files>
  <read_first>
    - apps/api/modules/Economy/Infrastructure/EconomyLedger.php (the whole file)
    - apps/api/modules/Economy/Application/CityEconomyService.php (all three `EconomyLedger::create([...])` blocks, verbatim — the new calls must carry the identical reason/reference/economy_version values)
    - apps/api/modules/Player/Application/GameBootstrapService.php (the `foreach ($resources as $resource => $amount)` block writing `starter.grant`)
    - apps/api/modules/Construction/Application/BuildingUpgradeService.php (the single `debitLocked` call, line ~113)
    - apps/api/modules/Economy/Domain/OverflowPolicy.php (from plan 08-02 — mirror its file header, namespace and comment style)
    - apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php (the two `creditLocked` / `debitLocked` call sites that need a new argument — change ONLY the call signatures, never an assertion)
    - apps/api/tests/Feature/Economy/WarehouseCapacityTest.php (from plan 08-02 — its two `OverflowPolicy::Refuse` calls pass the policy as the FIFTH positional argument, which the new `LedgerParty $source` parameter now occupies; they break with a TypeError unless fixed in Step 8)
    - .planning/codebase/CONVENTIONS.md (§ PHP — static factories over public constructors for value objects; exceptions named for the rule broken)
  </read_first>
  <behavior>
    - `LedgerParty::city('01J...')->value()` returns `'city:01J...'`.
    - `LedgerParty::system('production')->value()` returns `'system:production'`.
    - `LedgerParty::system('Production')` and `LedgerParty::system('has spaces')` both
      throw `InvalidArgumentException` — the format is enforced, not hoped for.
    - `EconomyLedger::record()` refuses to write without both parties, because they
      are required typed parameters.
    - `EconomyLedger` cannot be updated or deleted: calling `save()` on a retrieved
      row, or `delete()`, throws `RuntimeException`.
    - After the conversion, every existing economy test still passes with its
      assertions unchanged.
  </behavior>
  <action>
**Step 1 — new file** `apps/api/modules/Economy/Domain/LedgerParty.php`:

```php
<?php

declare(strict_types=1);

namespace Game\Economy\Domain;

use InvalidArgumentException;

/**
 * One end of a resource movement.
 *
 * Half the counterparties in this economy are not rows — production, the starter
 * grant and the construction sink are systemic — so a nullable foreign key would
 * have to mean four different things. A typed `{kind}:{identifier}` string keeps
 * every end nameable and greppable, and keeps a party from becoming free-form text.
 */
final readonly class LedgerParty
{
    private function __construct(public string $value) {}

    public static function city(string $cityId): self
    {
        if ($cityId === '') {
            throw new InvalidArgumentException('A city ledger party needs a city id.');
        }

        return new self('city:'.$cityId);
    }

    public static function system(string $name): self
    {
        if (preg_match('/^[a-z][a-z0-9_.]{0,50}$/', $name) !== 1) {
            throw new InvalidArgumentException(
                'A system ledger party must be lower_snake_case: "'.$name.'" is not.',
            );
        }

        return new self('system:'.$name);
    }

    public function value(): string
    {
        return $this->value;
    }
}
```

**Step 2 — `EconomyLedger`.** Add `'source'` and `'destination'` to `$fillable`,
add the two `@property string` docblock lines, add the guarded factory, and add the
append-only guards:

```php
    /**
     * The only sanctioned way to write a ledger row.
     *
     * Both parties are required arguments rather than optional attributes, so a new
     * mutation site cannot forget them the way it could forget an array key. An
     * architecture test forbids `EconomyLedger::create(` anywhere else.
     *
     * `overflow_amount` on a credit row is the portion the warehouse cap refused to
     * let through. It is deliberately NOT a second row to `system:void`: the parties
     * describe the intended flow, and criterion 5 reconciles balances by summing
     * `amount` alone.
     */
    public static function record(
        string $worldId,
        string $cityId,
        LedgerParty $source,
        LedgerParty $destination,
        string $resource,
        int $amount,
        int $overflowAmount,
        string $reason,
        ?string $reference,
        int $economyVersion,
    ): self {
        return self::create([
            'world_id' => $worldId,
            'city_id' => $cityId,
            'source' => $source->value(),
            'destination' => $destination->value(),
            'resource' => $resource,
            'amount' => $amount,
            'overflow_amount' => $overflowAmount,
            'reason' => $reason,
            'reference' => $reference,
            'economy_version' => $economyVersion,
        ]);
    }

    protected static function booted(): void
    {
        // Append-only (08-CONTEXT.md): a corrected balance is a new compensating row,
        // never an edited history. Raw DB::table writes bypass this on purpose — the
        // schema migration's backfill needs exactly that.
        static::updating(static function (self $ledger): void {
            throw new RuntimeException('economy_ledger is append-only; a correction is a new row.');
        });

        static::deleting(static function (self $ledger): void {
            throw new RuntimeException('economy_ledger is append-only; rows are never deleted.');
        });
    }
```

Imports the model needs: `Game\Economy\Domain\LedgerParty`, `RuntimeException`.
PHPStan level 8 will flag the unused `$ledger` closure parameters — drop them to
`static function (): void` if so.

**Step 3 — convert site 2 (`accrueLocked`).** Replace the `EconomyLedger::create([...])`
block with:

```php
                EconomyLedger::record(
                    worldId: (string) $city->world_id,
                    cityId: (string) $city->getKey(),
                    source: LedgerParty::system('production'),
                    destination: LedgerParty::city((string) $city->getKey()),
                    resource: $name,
                    amount: $credited,
                    overflowAmount: $overflow,
                    reason: 'production.elapsed',
                    reference: (string) $city->getKey(),
                    economyVersion: (int) config('game.versions.economy', 1),
                );
```

**Step 4 — convert sites 3 and 4 with an explicit counterparty parameter.**
`debitLocked` gains a required `LedgerParty $destination` as its LAST parameter;
`creditLocked` gains a required `LedgerParty $source` immediately before its optional
`$policy` parameter. New signatures:

```php
    public function debitLocked(
        City $city,
        ResourceBundle $cost,
        string $reason,
        string $reference,
        LedgerParty $destination,
    ): void

    public function creditLocked(
        City $city,
        ResourceBundle $grant,
        string $reason,
        string $reference,
        LedgerParty $source,
        OverflowPolicy $policy = OverflowPolicy::DiscardAtCap,
    ): array
```

In `debitLocked` the row is written with `source: LedgerParty::city(...)`,
`destination: $destination`. In `creditLocked` it is `source: $source`,
`destination: LedgerParty::city(...)`. Nothing else in either method changes.

**Step 5 — convert site 1 (`GameBootstrapService`).** Replace its
`EconomyLedger::create([...])` with `EconomyLedger::record(...)` using
`source: LedgerParty::system('starter')`,
`destination: LedgerParty::city((string) $city->getKey())`,
`reason: 'starter.grant'`, `reference: (string) $account->getKey()`. Add the
`Game\Economy\Domain\LedgerParty` import.

**Step 6 — `BuildingUpgradeService`.** The single `debitLocked` call becomes:

```php
            $this->economy->debitLocked(
                $city,
                $cost,
                'building.upgrade',
                $idempotencyKey,
                LedgerParty::system('construction'),
            );
```

Add the `Game\Economy\Domain\LedgerParty` import.

**Step 7 — `CityEconomyFoundationTest`.** Exactly three call sites need a new argument
and NOTHING else in this file may change:
- the `creditLocked(...)` in `it('caps server grants and records discarded overflow in the ledger')` gains `LedgerParty::system('test_grant')` as its fifth argument;
- the `creditLocked(...)` in the reconciliation test gains the same;
- the `debitLocked(...)` in the reconciliation test gains `LedgerParty::system('test_sink')` as its fifth argument.

Add the import. Do not touch a single `expect(...)` line — if an assertion now fails,
the conversion is wrong, not the assertion.

**Step 8 — `WarehouseCapacityTest` (created by plan 08-02) MUST be re-argumented too.**
This file is the one call site that breaks silently at runtime rather than at analysis
time, so do it deliberately. Plan 08-02 wrote both of its strict-grant calls passing
the policy **positionally as the fifth argument**:

```php
app(CityEconomyService::class)->creditLocked(
    $locked,
    ResourceBundle::fromArray(['food' => 700]),
    'test.strict_grant',
    'warehouse-capacity-test',
    OverflowPolicy::Refuse,
);
```

Position 5 is now `LedgerParty $source`, so `OverflowPolicy::Refuse` would bind to a
`LedgerParty` parameter and throw a `TypeError` before the test ever reaches its
assertion. In **both** `OverflowPolicy::Refuse` call sites in this file (the 700-food
overflow test and the exact-capacity boundary test), insert
`LedgerParty::system('test_strict_grant')` immediately BEFORE `OverflowPolicy::Refuse`,
making the policy the sixth positional argument:

```php
app(CityEconomyService::class)->creditLocked(
    $locked,
    ResourceBundle::fromArray(['food' => 700]),
    'test.strict_grant',
    'warehouse-capacity-test',
    LedgerParty::system('test_strict_grant'),
    OverflowPolicy::Refuse,
);
```

Add the `Game\Economy\Domain\LedgerParty` import to this file as well. Change nothing
else — every `expect(...)` in this file must still pass unmodified.
  </action>
  <verify>
    <automated>cd apps/api && ./vendor/bin/pest --filter=CityEconomyFoundation && ./vendor/bin/pest --filter=WarehouseCapacity && ./vendor/bin/phpstan analyse --memory-limit=1G</automated>
  </verify>
  <acceptance_criteria>
    - `grep -q "final readonly class LedgerParty" apps/api/modules/Economy/Domain/LedgerParty.php` succeeds.
    - `grep -q "LedgerParty::system('test_strict_grant')" apps/api/tests/Feature/Economy/WarehouseCapacityTest.php` succeeds (both `OverflowPolicy::Refuse` call sites re-argumented).
    - `cd apps/api && ./vendor/bin/pest --filter=WarehouseCapacity` exits 0 — no `TypeError`.
    - `grep -q "public static function record(" apps/api/modules/Economy/Infrastructure/EconomyLedger.php` succeeds.
    - `grep -q "static::updating" apps/api/modules/Economy/Infrastructure/EconomyLedger.php` and `grep -q "static::deleting" apps/api/modules/Economy/Infrastructure/EconomyLedger.php` both succeed.
    - `grep -c "EconomyLedger::record(" apps/api/modules/Economy/Application/CityEconomyService.php` returns 3.
    - `grep -q "EconomyLedger::record(" apps/api/modules/Player/Application/GameBootstrapService.php` succeeds.
    - `grep -q "LedgerParty::system('construction')" apps/api/modules/Construction/Application/BuildingUpgradeService.php` succeeds.
    - `grep -rn "EconomyLedger::create(" apps/api/modules/ | grep -v "Economy/Infrastructure/EconomyLedger.php"` returns NOTHING — the only remaining `create` is inside `record()` itself.
    - `grep -c "expect(" apps/api/tests/Feature/Economy/CityEconomyFoundationTest.php` is unchanged from before this task.
    - `cd apps/api && ./vendor/bin/pest --filter=CityEconomyFoundation` exits 0 with 3 passing tests.
    - `cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G` reports 0 errors.
    - `cd apps/api && ./vendor/bin/pint --test` exits 0.
  </acceptance_criteria>
  <done>All four ledger write sites go through one guarded factory with typed parties, the model refuses updates and deletes, and every pre-existing assertion still passes.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: Prove the audit trail and lock the write path with an architecture test</name>
  <files>apps/api/tests/Feature/Economy/LedgerAuditTrailTest.php, apps/api/tests/Architecture/ArchitectureTest.php</files>
  <read_first>
    - apps/api/tests/Architecture/ArchitectureTest.php (the whole 128-line file — every block ends `->group('arch')`; match that style exactly)
    - apps/api/tests/Feature/Mvp/MvpGameplayTest.php (the guest-token → bootstrap → `POST /game/city/buildings/farm/upgrade` HTTP flow this test replays, including the `Idempotency-Key` header)
    - apps/api/modules/Economy/Infrastructure/EconomyLedger.php (as written in Task 2)
    - .planning/phases/08-resources-economy/08-CONTEXT.md (§ The ledger — the locked wording this test enforces)
  </read_first>
  <behavior>
    - After a full guest → bootstrap → city read → farm upgrade flow, EVERY
      `economy_ledger` row has a non-empty `source` and `destination`, and neither is
      `'system:legacy'`.
    - The starter rows read `system:starter` → `city:{id}`.
    - The production rows read `system:production` → `city:{id}`.
    - The `building.upgrade` rows read `city:{id}` → `system:construction`.
    - Every `city:` party in the table refers to the city the row is scoped to —
      `source` or `destination` is always `'city:'.$row->city_id`, never another city.
    - Attempting `$row->update([...])` or `$row->delete()` throws.
    - No module file outside `EconomyLedger.php` calls `EconomyLedger::create(`.
  </behavior>
  <action>
**Step 1 — new test file** `apps/api/tests/Feature/Economy/LedgerAuditTrailTest.php`,
namespace `Tests\Feature\Economy`. Four tests.

1. `it('names both parties on every ledger row a real gameplay session writes')` —
   freeze the clock, create a guest over HTTP the way `MvpGameplayTest` does, bootstrap,
   `GET /api/v1/game/city`, advance the clock 600 seconds, `GET` again (forces a
   `production.elapsed` row), then
   `POST /api/v1/game/city/buildings/farm/upgrade` with an `Idempotency-Key` header
   (forces a `building.upgrade` debit row). Then:

   ```php
   $rows = EconomyLedger::query()->where('world_id', $worldId)->get();
   expect($rows)->not->toBeEmpty();
   foreach ($rows as $row) {
       expect($row->source)->not->toBe('')
           ->and($row->destination)->not->toBe('')
           ->and($row->source)->not->toBe('system:legacy')
           ->and($row->destination)->not->toBe('system:legacy')
           ->and([$row->source, $row->destination])->toContain('city:'.$row->city_id);
   }
   ```

   Also assert the three reasons are all present:
   `expect($rows->pluck('reason')->unique()->sort()->values()->all())->toContain('starter.grant', 'production.elapsed', 'building.upgrade');`

2. `it('records the documented direction for each reason')` — from the same session,
   assert per reason:

   ```php
   $starter = $rows->firstWhere('reason', 'starter.grant');
   expect($starter->source)->toBe('system:starter')
       ->and($starter->destination)->toBe('city:'.$cityId);

   $production = $rows->firstWhere('reason', 'production.elapsed');
   expect($production->source)->toBe('system:production')
       ->and($production->destination)->toBe('city:'.$cityId);

   $spend = $rows->firstWhere('reason', 'building.upgrade');
   expect($spend->source)->toBe('city:'.$cityId)
       ->and($spend->destination)->toBe('system:construction')
       ->and($spend->amount)->toBeLessThan(0);
   ```

3. `it('refuses to update or delete a ledger row')` — take any row, then two
   `try/catch` blocks:

   ```php
   $updateFailed = false;
   try { $row->update(['amount' => 999]); } catch (RuntimeException) { $updateFailed = true; }

   $deleteFailed = false;
   try { $row->delete(); } catch (RuntimeException) { $deleteFailed = true; }

   expect($updateFailed)->toBeTrue()
       ->and($deleteFailed)->toBeTrue()
       ->and((int) EconomyLedger::query()->whereKey($row->getKey())->value('amount'))->toBe($original);
   ```

4. `it('rejects a malformed system party')` — pure value-object assertions, no database:
   `LedgerParty::system('production')->value()` is `'system:production'`;
   `LedgerParty::city('01J')->value()` is `'city:01J'`; and
   `expect(fn () => LedgerParty::system('Not Valid'))->toThrow(InvalidArgumentException::class)`.

**Step 2 — architecture test.** Append to `apps/api/tests/Architecture/ArchitectureTest.php`,
matching the file's existing comment-free `arch(...)` style and always closing with
`->group('arch')`. Pest's `arch()` cannot express "this static method is only called
here", so use a plain grep-backed test in the same file instead:

```php
it('routes every ledger write through EconomyLedger::record', function (): void {
    $offenders = [];
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('modules'), RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        if (str_ends_with((string) $file->getPathname(), 'Economy/Infrastructure/EconomyLedger.php')) {
            continue;
        }
        $contents = (string) file_get_contents((string) $file->getPathname());
        if (str_contains($contents, 'EconomyLedger::create(')) {
            $offenders[] = (string) $file->getPathname();
        }
    }

    expect($offenders)->toBe([], 'Ledger rows are written only through EconomyLedger::record().');
})->group('arch');
```

Add `use RecursiveDirectoryIterator;` and `use RecursiveIteratorIterator;` at the top of
the file if it does not already import them. If `expect()->toBe()` does not accept a
second message argument in the installed Pest version, drop the message and keep the
assertion.

Add a second grep-backed test in the same style asserting no module file mutates the
ledger table directly:

```php
it('never updates or deletes economy_ledger rows from module code', function (): void {
    // ... same file walk, flag any file containing "economy_ledger" together with
    // "->update(" or "->delete(" — excluding EconomyLedger.php itself.
})->group('arch');
```

If that second test proves too noisy to express cleanly (for example because
`->update(` appears for an unrelated table in the same file), replace it with a
narrower check for the literal strings `DB::table('economy_ledger')->update(` and
`DB::table('economy_ledger')->delete(` and say so in the summary. Do not delete the
test — narrow it.
  </action>
  <verify>
    <automated>cd apps/api && ./vendor/bin/pest --filter=LedgerAuditTrail && ./vendor/bin/pest --group=arch</automated>
  </verify>
  <acceptance_criteria>
    - `cd apps/api && ./vendor/bin/pest --filter=LedgerAuditTrail` exits 0 with 4 passing tests.
    - `cd apps/api && ./vendor/bin/pest --group=arch` exits 0.
    - `grep -q "system:construction" apps/api/tests/Feature/Economy/LedgerAuditTrailTest.php` succeeds.
    - `grep -q "system:production" apps/api/tests/Feature/Economy/LedgerAuditTrailTest.php` succeeds.
    - `grep -q "system:starter" apps/api/tests/Feature/Economy/LedgerAuditTrailTest.php` succeeds.
    - `grep -q "system:legacy" apps/api/tests/Feature/Economy/LedgerAuditTrailTest.php` succeeds — the migration default is explicitly asserted absent from gameplay rows.
    - `grep -q "EconomyLedger::record" apps/api/tests/Architecture/ArchitectureTest.php` succeeds.
    - `cd apps/api && ./vendor/bin/pest` exits 0 with 0 failures.
    - `cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G` prints `[OK] No errors`.
    - `cd apps/api && ./vendor/bin/pint --test` exits 0.
    - `npm run typecheck && npm run lint && npm test` all exit 0.
  </acceptance_criteria>
  <done>Every ledger row a real session writes names both ends in the documented direction, the trail cannot be edited, and an architecture test stops the next mutation site from bypassing the guarded write path.</done>
</task>

</tasks>

<verification>
1. `economy_ledger` has non-null, indexed `source` and `destination`; pre-existing rows were backfilled by sign.
2. A guest → bootstrap → read → upgrade session writes `starter.grant`, `production.elapsed` and `building.upgrade` rows, each with both parties in the documented direction, none carrying `system:legacy`.
3. `grep -rn "EconomyLedger::create(" apps/api/modules/ | grep -v EconomyLedger.php` returns nothing.
4. Updating or deleting a ledger row throws.
5. `./vendor/bin/pest --group=arch` passes, including the new write-path rule.
6. All six gates from TESTING.md pass; `CityEconomyFoundationTest`'s three tests pass with their assertions unmodified.
</verification>

<success_criteria>
- ROADMAP criterion 2 satisfied in full: source, destination, resource, amount, reason and reference on every mutation, plus `created_at` and `economy_version` which already existed.
- The append-only rule from `08-CONTEXT.md` is mechanically enforced, not a convention.
- Phases 19 and 27 can write their conservation property tests against a ledger that names both ends of a transfer.
</success_criteria>

<output>
After completion, create `.planning/phases/08-resources-economy/08-03-ledger-parties-append-only-SUMMARY.md`.
</output>
