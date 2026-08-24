# Technical design document

The global technical view. Detail lives in the linked documents; this is the map.

## Product

A mobile MMO of empire building, territorial conquest and real-time strategic
warfare. One Laravel API (the authority), one React Native client (a projection),
one Filament back office.

## Governing constraint

**The server owns the truth.** The client is assumed compromised: decompiled,
traffic rewritten, clock forged, requests replayed. Every design decision is made
against that assumption rather than patched after it is demonstrated.

## Stack

| Layer | Choice | ADR |
|-------|--------|-----|
| Backend | PHP 8.4 · Laravel 13 | 002 |
| Database | PostgreSQL 16 + PostGIS | 004 |
| Cache/queue | Redis 7 — never authoritative | 005 |
| Realtime | Reverb (Pusher protocol) | 008 |
| Client | React Native · Expo · Skia | 003 |
| Back office | Filament | 002 |
| Contract | Hand-authored OpenAPI → generated TS | 017 |

## Structure

Modular monolith (ADR-001). `Interface → Application → Domain`,
`Infrastructure → Domain`. Boundaries enforced by architecture tests.

See [`overview.md`](overview.md), [`modules.md`](modules.md).

## The invariants

These are enforced mechanically. Each one exists because breaking it is a class
of bug that is invisible until players find it.

| Invariant | Mechanism |
|-----------|-----------|
| No client-supplied outcome is trusted | Design; exploit suite in Phase 37 |
| Money is integer, never float | `ResourceAmount` raises; PHPStan level 8 |
| Balances reconcile to the ledger exactly | Property test over random sequences |
| No double-spend under concurrency | Transaction + `FOR UPDATE` + re-check inside the lock |
| A retried command applies once | `Idempotency-Key` + stored response |
| A retried job applies once | Guarded on `completed_at IS NULL` |
| Timed work always completes | Delayed job + scheduled reconciler |
| Battles replay identically, forever | Pure seeded simulation + version pinning |
| Time is server-side and UTC | `Clock` contract; architecture test |
| No cross-world data leak | `world_id` on every table and every query |
| Balance is data, not code | Architecture test + CI validator |
| Channels deny by default | Both allow and deny paths tested |

## Data

PostgreSQL is the only source of truth. ULIDs for client-addressable entities,
integers for internal tables (ADR-016). Every gameplay table has `world_id`.
Resources are `bigint` with `CHECK (>= 0)`.

See [`../database/conventions.md`](../database/conventions.md).

## Time

All timestamps UTC. Timed operations persist `started_at`, `finishes_at`,
`completed_at`. Production accrues from elapsed time on read, not from a tick.

## Content versioning

Three independent counters — `data`, `combat`, `economy`. Battles and ledger
entries record the version they ran under, so a rebalance never rewrites history
(ADR-015).

## Observability

One correlation id from tap to job. Structured logs, OpenTelemetry traces, and
domain metrics (economy inflation, reconciler recoveries) that reveal a broken
game rather than a broken server.

See [`../operations/observability.md`](../operations/observability.md).

## Quality gates

Pint · PHPStan level 8 + strict rules · Pest including architecture tests ·
TypeScript strict · ESLint · OpenAPI drift check · game-data validator.

All must pass before a phase is done. See
[`../gsd/EXECUTION_RULES.md`](../gsd/EXECUTION_RULES.md).

## Known limitations

- PostGIS migrations are verified in CI, not on a developer host that lacks
  `pdo_pgsql`. The local suite runs on SQLite and cannot cover spatial work.
- No capacity claim is supported by measurement until Phase 39.
- Open debt is tracked as DEBT-001..007 in `.planning/codebase/CONCERNS.md`.
