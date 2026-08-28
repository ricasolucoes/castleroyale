# ADR-019: Integer tile coordinates with polygonal PostGIS boundaries

**Status:** Accepted
**Date:** 2026-08-28

## Context

The Phase 05 roadmap wording says that region and tile geometry use PostGIS,
while the locked Phase 05 context explicitly decides that tiles use exact
integer `(world_id, x, y)` coordinates and that PostGIS is reserved for shapes
that are genuinely polygonal.

## Decision

The locked context wins. Regions store polygonal PostGIS geometry and use GiST
indexes. Tiles store integer coordinates with a unique `(world_id, x, y)`
constraint and a composite B-tree index for bounded viewport queries; tile
points are not duplicated as PostGIS geometry.

The roadmap criterion's reference to tile geometry is therefore satisfied by
the tile's spatial coordinate/index representation, while the spatial planner
proof applies to region polygon queries. This preserves exact grid identity and
avoids maintaining two representations of every tile.

## Consequences

- Region containment and overlap queries use PostGIS and require `EXPLAIN`
  evidence for their GiST index.
- Tile viewport queries use the `(world_id, x, y)` composite index and remain
  portable to the SQLite unit-test suite.
- A future feature that needs tile-shaped geometry must add a new ADR rather
  than silently introducing a second source of coordinate truth.
