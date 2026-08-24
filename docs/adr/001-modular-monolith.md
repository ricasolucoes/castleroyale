# ADR-001: Modular monolith over microservices

**Status:** Accepted
**Date:** 2026-08-24

## Context

The game spans roughly thirty domains — world, cities, economy, combat, alliances,
trade, moderation and so on. Many of them must participate in the same
transaction: an attack debits troops, resolves a battle, moves resources and
updates territory. Those operations must be atomic or the economy leaks.

The team is small and the traffic pattern is unknown until real players arrive.

## Decision

Build a **modular monolith**: one deployable Laravel application, with domains
separated into modules under `apps/api/modules/<Module>/` and boundaries enforced
by automated architecture tests rather than by network calls.

Each module may use `Domain / Application / Infrastructure / Interface` layering,
but only where that layering earns its keep. Ceremonial folders that hold one
pass-through class are a defect, not compliance.

Cross-module communication goes through published domain events or explicitly
exported application services — never by reaching into another module's Eloquent
models or its `Domain` namespace.

## Alternatives

**Microservices from day one.** Rejected: distributed transactions across
economy and combat would be the dominant engineering cost before there is a
single player. Operationally it needs a team we do not have.

**Unstructured monolith.** Rejected: without enforced boundaries, the modules
blur within months and extraction later becomes impossible. The architecture
tests are cheap; discovering the coupling in year two is not.

**Modular monolith with a separate combat service.** Rejected for now, but this
is the most likely first extraction — see Consequences.

## Consequences

- One deployment, one database, ordinary ACID transactions for economy and combat.
- Module boundaries must be enforced mechanically. `tests/Architecture/` fails the
  build when the domain layer imports the framework or a module reaches across.
- Scaling is vertical plus horizontal replicas of the whole app, not per-service.
  This is adequate to the population targets in Phase 39 and is measured there.
- If a single subsystem becomes the bottleneck, the enforced boundary means it can
  be extracted. Battle simulation is the leading candidate: it is pure, seeded and
  has no database access, so it could move behind a queue or a service with no
  domain rewrite.
- A slow module can starve the whole app. Queue tiering (ADR-014) is the mitigation.
