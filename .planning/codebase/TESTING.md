# Testing

## Commands

```bash
cd apps/api
./vendor/bin/pest                      # full suite
./vendor/bin/pest --group=arch         # architecture rules only
./vendor/bin/pest --filter=ResourceAmount
./vendor/bin/phpstan analyse --memory-limit=1G
./vendor/bin/pint --test

npm run typecheck                      # repo root, all workspaces
npm run lint
npm test
```

All of these must pass before a phase is DONE. Not "should pass" — run them.

## Layout

| Directory | Gets | Must not need |
|-----------|------|---------------|
| `tests/Unit/` | Pure domain logic | Container, database, HTTP |
| `tests/Feature/` | Full app, fresh database (`RefreshDatabase`) | — |
| `tests/Architecture/` | Boundary rules via Pest `arch()` | — |

If a test in `tests/Unit/` starts needing the container, it belongs in `Feature/`.

## The suite runs on SQLite

`phpunit.xml` pins `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`. This is why the
suite runs on a host without `pdo_pgsql`.

**Consequence:** a migration or query using PostgreSQL-only syntax (PostGIS types,
`ILIKE`, JSONB operators, `EXPLAIN` assertions) **cannot be covered by the default
suite.** Those tests live in `tests/Postgres/` and are loaded **only** by
`apps/api/phpunit.postgres.xml`, which forces `DB_CONNECTION=pgsql` and
`DB_DATABASE=dominion_test`. `phpunit.xml` does not declare that directory as a
testsuite, so `./vendor/bin/pest` can never run them by accident.

    make test-postgres    # local, through Docker
    ./vendor/bin/pest --configuration=phpunit.postgres.xml    # CI

When you add PostGIS work, add the test there. Do not assume SQLite coverage.

## What every phase must test

- **Happy path** through the public API, asserting the `{data, meta}` envelope.
- **Every error code** the phase introduces, asserting `error.code` — not the message.
- **Authorisation**: a player cannot read or mutate another player's entity.
- **Concurrency**, for anything spending resources: two simultaneous requests
  produce exactly one success.
- **Idempotency**, for every mutating command: same key twice, one effect.
- **Job idempotency**: running a completion job twice completes once.
- **Reconciliation**, for timed work: kill the job, run the reconciler, exactly
  one completion.

## Determinism (combat)

Battle tests assert **byte-identical** output across runs from identical inputs,
and that an old battle replays correctly under a newer balance version.

## Custom expectations

`toBeApiSuccess()` asserts status plus the `data` key. Defined in `tests/Pest.php`.

## Coverage

No blanket percentage target. The obligations above are the bar. Type coverage is
available via `pest --type-coverage`.

## Known gaps

- `config/` and `tests/` are excluded from PHPStan (DEBT-002). Laravel's stock
  config files are framework-owned, and Pest's fluent API is not statically
  modelable without a plugin.
- Mobile tests are minimal until Phase 02 lands the component library.
