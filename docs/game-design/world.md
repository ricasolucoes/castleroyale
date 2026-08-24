# World

## Structure

```
World  →  Region  →  Tile
```

- **World** — a shard. A player belongs to exactly one (ADR-012). Independent
  economy, map, rankings, alliances and chat.
- **Region** — a partition within a world. The unit of realtime subscription, map
  fetching, client caching and territorial control.
- **Tile** — one addressable cell, identified by `(world_id, x, y)`.

## Coordinates

Square grid, integer coordinates, origin at the map centre.

`(world_id, x, y)` is unique — a database constraint, not a convention. Attempting
to occupy an occupied tile returns `TILE_OCCUPIED`.

Tile coordinates are plain integers, **not** PostGIS points: exact, cheap, and the
natural key for a grid. PostGIS is used for region and territory **polygons**,
where the shape is genuinely geometric (ADR-004).

**Distance** is Chebyshev (`max(|dx|, |dy|)`) — diagonal movement costs the same as
orthogonal, which suits a grid where marches travel freely. This metric is fixed
once, in Phase 05, and every later system uses it. Do not introduce a second one.

## Terrain

| Terrain | Effect |
|---------|--------|
| Plains | Neutral; fastest movement |
| Forest | Favours defence and archers; slower |
| Hills | Favours defence; slower |
| Mountains | Impassable; forms natural borders |
| River | Impassable except at crossings |
| Road | Fastest movement |

Terrain modifies combat (Phase 17) and march speed (Phase 15). Both modifiers are
**data**, not code (ADR-013).

## Contents

| Entity | Purpose | Phase |
|--------|---------|-------|
| Player city | A player's base | 07 |
| Neutral city | Capturable, grants bonuses | 20 |
| Resource node | Gathering target | 16 |
| NPC camp | PvE target | 16 |
| Fortress | Alliance-held strategic structure | 23 |
| Strategic point | Grants regional control | 21 |
| Special location | Event-driven | 31 |

## Generation

Deterministic and seeded: the same seed produces **byte-identical** terrain. This
is testable and is tested (Phase 05).

Generation runs once, as a command, at world creation — never per request.
Parameters (region size, terrain distribution, node density, beginner zone radius)
come from `packages/game-data`, not code.

## Beginner zones

The outer ring of a newly opened world is a beginner zone. New players settle
there and are protected by shields plus power-difference rules (Phase 19).

A player who outgrows the zone can relocate inward toward contested, higher-value
land. The intent is that a new player is never a free farm for a veteran, and that
leaving safety is a choice with an upside.

## Viewport loading

The client **never** loads the whole map.

- Requests are bounded by `game.limits.world_viewport_max_tiles`. A larger request
  returns `VALIDATION_FAILED` rather than being silently clamped — silent clamping
  hides client bugs.
- Fetch by chunk, cache client-side, apply **delta** updates.
- Budget: a viewport query returns in under 100ms p95 against a fully populated
  world (Phase 05, measured in Phase 38).
