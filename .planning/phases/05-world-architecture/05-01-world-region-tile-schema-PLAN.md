---
wave: 1
depends_on: []
files_modified:
  - apps/api/database/migrations/
  - apps/api/modules/World/Infrastructure/
  - apps/api/tests/Feature/World/
  - apps/api/tests/Postgres/
autonomous: true
---

# Plan 05-01: World, region and tile schema

## Goal

Persist regions and addressable tiles with the world partitioning rules from
ADR-012 and the integer-coordinate/PostGIS boundary in ADR-019.

## Tasks

1. Add region and tile migrations with ULIDs, `world_id`, UTC timestamps,
   integer coordinates, region membership, terrain and deterministic seed
   metadata.
2. Add polygon geometry and GiST support for regions on PostgreSQL, plus the
   `(world_id, x, y)` unique constraint and query index for tiles.
3. Add focused models and feature tests for world scoping, duplicate tile
   rejection and region/tile relationships.
4. Add the PostgreSQL-only migration and index assertions where SQLite cannot
   represent geometry.

## Acceptance criteria

- Duplicate `(world_id, x, y)` inserts fail while the same coordinates in two
  worlds remain valid.
- Every new gameplay query includes `world_id`.
- Region geometry is polygonal PostGIS with a GiST index; tile coordinates are
  integers with the composite B-tree index, as documented by ADR-019.

## Verification

Run the World feature tests, Postgres test configuration, full Pest, PHPStan and
Pint. The Postgres configuration is mandatory for the spatial assertions and is
not replaced by SQLite.
