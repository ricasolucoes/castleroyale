# Database conventions

Binding rules for every migration in this project. A migration that violates one
of these is a defect, not a style preference.

## Identifiers

| Entity kind | Key type | Why |
|-------------|----------|-----|
| Client-addressable game entities (player, city, army, march, battle, alliance, hero) | `ULID` `char(26)` primary key | Non-enumerable, time-sortable (ADR-016) |
| Reference data imported from game data (buildings, units, technologies) | Stable string `code` as the natural key | Survives re-import; readable in queries |
| Internal / pivot / high-volume line tables | `bigIncrements` | Smaller, faster joins; never exposed |

Never expose an auto-increment id in an API response.

## Naming

- Tables: `snake_case`, **plural** — `cities`, `march_units`.
- Columns: `snake_case`, singular — `finishes_at`, `world_id`.
- Foreign keys: `<singular_table>_id` — `city_id`, `alliance_id`.
- Booleans: positive phrasing with a verb prefix — `is_staff`, `has_shield`.
  Never `not_active`.
- Timestamps: `_at` suffix — `started_at`, `finishes_at`, `completed_at`,
  `revoked_at`.
- Money/resources: the resource name, in whole units — `food`, `wood`, `gold`.
  Never `gold_amount_float`.
- Enum-ish columns: `string` plus an application-level PHP enum. See below.

## Mandatory columns

Every gameplay table carries:

```php
$table->ulid('id')->primary();
$table->foreignUlid('world_id')->constrained()->cascadeOnDelete();
$table->timestamps();   // created_at, updated_at — UTC
```

`world_id` is non-negotiable (ADR-012). **Every query must filter on it.** A query
that omits it is a cross-world data leak.

## Time

- All timestamps are stored in **UTC**. `APP_TIMEZONE=UTC` and
  `date_default_timezone_set('UTC')` in `AppServiceProvider` both enforce it.
- Timed operations persist the full triple where applicable:
  `started_at`, `finishes_at`, and `completed_at` (null until done).
- `completed_at` being null is what makes completion jobs idempotent and lets the
  reconciler find overdue rows. Never infer completion from `finishes_at` alone.
- The device clock is never written to the database.

## Money and resources

- Always `bigInteger`, never `decimal`, never `float` (ADR-010).
- Default `0`, and add a `CHECK (column >= 0)` constraint. The database is the last
  line of defence against a negative balance.
- Every mutation writes a ledger row. Balances are derived state that must
  reconcile against the ledger exactly.

## Enums

Do **not** use native PostgreSQL enum types for anything data-driven — altering
them requires a migration, which defeats ADR-013.

- Data-driven values (building codes, unit codes, technology codes): plain
  `string`, validated against imported game data.
- Fixed domain states (march state, battle result, sanction type): `string` column
  backed by a PHP `enum` with a `string` backing type. Add a `CHECK` constraint
  listing the values when the set is genuinely closed.

## Indexes

- Index every foreign key. PostgreSQL does not do this automatically.
- Composite indexes follow query order: `(world_id, x, y)` for tile lookup.
- Unique constraints express real rules:
  - one city per tile: `unique(world_id, x, y)` on `cities`
  - one player per world per account: `unique(account_id, world_id)`
  - unique alliance name per world: `unique(world_id, name)`
- Spatial columns (territory, region boundaries) use PostGIS `geometry` with a
  **GiST** index.
- **Prove it**: any migration adding a spatial or composite index for a hot query
  must be accompanied by a test asserting the plan uses it via `EXPLAIN`.

## Soft deletes

Use sparingly. Soft deletes on a table with unique constraints break those
constraints, because a deleted row still occupies the value.

- Player-facing content that may need restoration (alliances, chat) may soft delete.
- Gameplay state (cities, armies, marches) does **not** soft delete — it has
  explicit lifecycle states instead.
- Never soft delete ledger rows. The ledger is append-only.

## Concurrency

Any operation spending resources, claiming a tile, or modifying an army must:

1. Open a transaction.
2. `SELECT ... FOR UPDATE` the owning row (the city, the market order).
3. Re-check affordability **inside** the lock — a check outside the lock is the
   double-spend window.
4. Mutate, write the ledger, commit.

`docs/backend/architecture.md` has the canonical code shape.

## Audit

Administrative and economic actions write an immutable audit row:
`actor`, `action`, `target_type`, `target_id`, `before`, `after`, `reason`, `ip`,
`created_at`. See Phase 34.

## JSONB

Allowed for genuinely schemaless data: battle initial state, event payloads,
game-data effect blobs, analytics properties.

Not allowed for anything queried in a hot path or requiring referential integrity.
If you find yourself adding a GIN index to support a gameplay query, that data
wanted to be columns.
