# Scalability

Nothing here is implemented ahead of measurement. Phase 39 measures; this
document says where the seams are so they exist when they are needed.

## The primary axis: worlds

A **World** is a shard (ADR-012). Population grows by opening worlds, not by
growing one indefinitely. This is also a game-design property: a map that is full
has no room to settle, and a map that is empty feels dead.

Every gameplay table carries `world_id` and every query filters on it. That single
discipline is what makes each of these available later without a domain rewrite:

- partitioning tables by `world_id`
- moving a world to its own database
- placing worlds in different regions

## Second axis: regions within a world

Regions bound realtime subscription and map fetching. A player subscribes to the
regions in view, not the world. Viewport queries are capped by
`game.limits.world_viewport_max_tiles`.

Region size trades subscription granularity against subscription count, and lives
in game data so it can be tuned without a deploy.

## Known bottlenecks, in the order they will bite

| # | Bottleneck | First move | Measured in |
|---|-----------|------------|-------------|
| 1 | Map viewport queries | Spatial indexes (done), client caching, delta updates | 05, 38 |
| 2 | Battle simulation CPU | Pure and stateless, so it parallelises across workers trivially | 17, 39 |
| 3 | Websocket fan-out | Reverb scales horizontally; regions bound the fan-out | 39 |
| 4 | Queue throughput | Six tiers already isolate gameplay from analytics; scale per tier | 01, 39 |
| 5 | Database writes | Vertical first, then read replicas for map/ranking reads | 38, 50 |
| 6 | Ranking aggregation | Already materialised snapshots rather than live aggregation | 30 |

## What we do not do

**Read replicas before measurement.** They add replication lag, which is a
correctness hazard the moment someone reads a balance from a replica.

**Caching player-owned state in Redis.** Never (ADR-005). Losing Redis must
degrade the game, not corrupt it.

**Microservices.** ADR-001. The module boundaries are enforced so extraction
stays possible; the battle simulator is the natural first candidate precisely
because it is pure.

## Capacity claims

There are none yet. Phase 39 runs documented, re-runnable scenarios at 1k, 5k,
10k, 50k and 100k concurrent users and records throughput, latency percentiles,
error rate and queue depth — with the exact commit, dataset and infrastructure,
so a later run is comparable.

Any tier the system fails is documented as a known limit rather than omitted.
Claiming scale without measuring it is exactly what this project forbids.
