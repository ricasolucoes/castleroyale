---
wave: 4
depends_on: ["05-03"]
files_modified:
  - apps/api/tests/Postgres/
  - apps/api/database/seeders/
  - apps/api/app/Console/
  - docs/database/
  - .github/workflows/
---

# Plan 05-04: Spatial index proof and viewport benchmark

## Goal

Prove the hot world queries use their intended indexes and measure viewport
latency against a fully populated benchmark dataset.

## Tasks

1. Add a deterministic Postgres benchmark fixture with enough regions and tiles
   to represent a populated world without making the default SQLite suite slow.
2. Assert `EXPLAIN` uses the region GiST index and the tile composite index for
   the corresponding bounded queries.
3. Add a repeatable p95 benchmark command/report for viewport requests and fail
   the CI check when the measured target exceeds 100ms.
4. Document the Postgres-only execution path and the interpretation of the
   benchmark result.

## Acceptance criteria

- Spatial and composite indexes are shown in query plans, not merely declared.
- The benchmark dataset is seeded deterministically.
- The viewport p95 is under 100ms in the documented CI environment.

## Verification

Run `./vendor/bin/pest --configuration=phpunit.postgres.xml`, the benchmark,
full SQLite Pest, PHPStan, Pint and the repository CI checks. If Docker/Postgres
is unavailable locally, report that exact skipped command and retain CI as the
required authority.
