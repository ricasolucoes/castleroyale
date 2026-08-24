# ADR-004: PostgreSQL + PostGIS for world data

**Status:** Accepted
**Date:** 2026-08-24

## Context

The world map is a persistent grid holding tens of thousands of entities: cities,
resource nodes, NPC camps, fortresses and territory polygons. The dominant query
is "give me everything inside this viewport", issued constantly by every player
panning the map.

The economy in the same database needs strict transactional integrity.

## Decision

Use **PostgreSQL as the single source of truth**, with the **PostGIS** extension
for spatial data.

- Tile coordinates are stored as plain integers (`world_id`, `x`, `y`) with a
  unique constraint — exact, cheap, and the natural key for a grid.
- Territory and region boundaries, which are genuinely polygonal, use PostGIS
  geometry with GiST indexes.
- Viewport queries are bounded server-side; a request larger than
  `game.limits.world_viewport_max_tiles` is rejected with `VALIDATION_FAILED`
  rather than served slowly.

Every spatial query added must be proven to use its index via `EXPLAIN` in a test.

## Alternatives

**Plain integer columns with B-tree indexes only.** Sufficient for tile lookups
and rejected only for polygons: territory containment and overlap queries in SQL
without PostGIS are slow and painful to write correctly.

**A dedicated spatial store (Elasticsearch geo, Redis GEO).** Rejected: a second
source of truth for world state means reconciliation bugs, and world state must be
transactional with the economy that changes alongside it.

**MySQL.** Rejected: weaker spatial support, no partial indexes, and PostgreSQL's
transaction and locking semantics matter for double-spend prevention.

## Consequences

- One database, one transaction boundary for economy plus world changes.
- PostGIS must be present in every environment. The Docker image is `postgis/postgis`
  and CI provisions the same, so the extension can never be missing in a way that
  only shows up in production.
- The host development machine may lack `pdo_pgsql`; Docker is therefore the
  canonical development environment (Phase 01) and migrations are verified in CI
  against real Postgres + PostGIS.
- Spatial indexes are only useful if queries are written to hit them. This is a
  standing review obligation, backed by `EXPLAIN` assertions.
- Read replicas are the scaling path for map reads (ADR-012); writes stay on primary.
