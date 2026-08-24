# ADR-012: World sharding and region partitioning

**Status:** Accepted
**Date:** 2026-08-24

## Context

An MMO server has a natural population ceiling: too few players and the map feels
dead, too many and the map is full, latency rises and no one can find space to
settle. Growth must therefore add worlds, not endlessly grow one.

Separately, within a world, the client must never load the whole map.

## Decision

Two independent partitioning axes.

**Worlds (shards).** A `World` is the top-level container. A player belongs to
exactly one world and holds at most one player record per world, enforced by a
unique constraint. All gameplay is scoped by `world_id`. New population is
absorbed by opening new worlds, and a full world returns `WORLD_FULL`.

Cross-world play is explicitly out of scope (see `.planning/PROJECT.md`).

**Regions (partitions within a world).** A world is divided into regions; regions
contain tiles. Regions are the unit of:

- realtime subscription (`world.{worldId}.region.{regionId}`)
- map data fetching and client caching
- territory control and strategic point ownership

Viewport queries are bounded by `game.limits.world_viewport_max_tiles` and
rejected with `VALIDATION_FAILED` if larger.

## Alternatives

**One giant world with dynamic interest management.** Technically elegant,
rejected: it makes every query global, removes the natural population ceiling,
and turns a capacity problem into a distributed systems problem.

**Sharding by player rather than geography.** Rejected: the map is shared, so
geography is the only partition that keeps neighbours in the same shard.

**Database-level partitioning by `world_id` from day one.** Deferred, not
rejected. `world_id` is on every table precisely so this becomes available when
measurement justifies it (Phase 39).

## Consequences

- Every gameplay table carries `world_id`, and every query filters on it. Missing
  that filter is a cross-world data leak, so it is a review checkpoint.
- Worlds are independent, so they can later live on separate databases or regions
  with no domain change.
- Players cannot interact across worlds. Alliance, chat, market and rankings are
  all world-scoped, and this must be visible in the UI so players understand it.
- Region size is a tuning parameter that trades subscription granularity against
  subscription count. It is set in game data, not code.
