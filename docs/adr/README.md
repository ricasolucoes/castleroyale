# Architecture Decision Records

An ADR records a decision that constrains future work: what we chose, what we
rejected, and what it costs us. They are immutable once accepted — a decision
that turns out wrong is superseded by a new ADR, never edited in place.

## When to write one

Write an ADR when a choice would otherwise have to be re-litigated later:
technology selection, a boundary between modules, a data integrity rule, a
security posture. Do not write one for a routine implementation detail.

If you are executing a GSD phase and discover that a recorded decision cannot
work, **stop and write a superseding ADR** rather than quietly designing around
it. Record the change in `docs/gsd/DECISIONS.md` too.

## Format

Every ADR has exactly these sections:

```
# ADR-NNN: Title

**Status:** Proposed | Accepted | Superseded by ADR-XXX | Deprecated
**Date:** YYYY-MM-DD

## Context
## Decision
## Alternatives
## Consequences
```

## Index

| ADR | Title | Status |
|-----|-------|--------|
| [001](001-modular-monolith.md) | Modular monolith over microservices | Accepted |
| [002](002-laravel-backend.md) | Laravel as the authoritative backend | Accepted |
| [003](003-react-native-mobile.md) | React Native + Expo for the client | Accepted |
| [004](004-postgresql-postgis.md) | PostgreSQL + PostGIS for world data | Accepted |
| [005](005-redis-role.md) | Redis for cache, locks, queues and presence only | Accepted |
| [006](006-server-authoritative-gameplay.md) | Server-authoritative gameplay | Accepted |
| [007](007-event-driven-internal-communication.md) | Event-driven communication between modules | Accepted |
| [008](008-realtime-transport.md) | Laravel Reverb as the realtime transport | Accepted |
| [009](009-deterministic-battle-simulation.md) | Deterministic, seeded battle simulation | Accepted |
| [010](010-integer-based-economy.md) | Integer-only economy | Accepted |
| [011](011-authentication-strategy.md) | Token authentication with rotating refresh | Accepted |
| [012](012-world-partitioning.md) | World sharding and region partitioning | Accepted |
| [013](013-data-driven-balancing.md) | Data-driven balancing | Accepted |
| [014](014-observability.md) | Observability via OpenTelemetry and structured logs | Accepted |
| [015](015-content-versioning.md) | Versioned game content and replay safety | Accepted |
| [016](016-ulid-identifiers.md) | ULID identifiers for game entities | Accepted |
| [017](017-openapi-contract.md) | Hand-authored OpenAPI as the shared contract | Accepted |
