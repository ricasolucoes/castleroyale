# ADR-005: Redis for cache, locks, queues and presence only

**Status:** Accepted
**Date:** 2026-08-24

## Context

Redis is fast and tempting. The failure mode it invites is treating it as a
database: caching a player's resource balance, then serving a spend decision from
that cache. When Redis evicts a key, loses a write on failover, or is simply
stale, the result is duplicated or destroyed player value.

## Decision

Redis is used for, and **only** for:

- **Cache** of derived, recomputable data
- **Locks** guarding concurrent operations
- **Queues** (Horizon) for background work
- **Presence** and ephemeral realtime state
- **Rate limiting** counters

Redis is **never authoritative for anything a player owns.** Resources, troops,
buildings, heroes, territory and ledger entries live in PostgreSQL, and a spend
decision is made inside a database transaction against database rows.

A cached value may inform the UI. It may never be the basis of a state mutation.

## Alternatives

**Redis as a write-through hot store for player state.** Rejected: this is the
single most common way live games leak economy. The performance gain does not
justify a class of bug that is invisible until players exploit it.

**Redis Cluster with persistence tuned for durability.** Rejected as a substitute
for the rule above — better durability narrows the window but does not close it,
and it complicates operations for a guarantee PostgreSQL already gives.

**Memcached.** Rejected: no locks, no queues, no pub/sub.

## Consequences

- Losing Redis entirely degrades the game — queues stop, cache misses, realtime
  drops — but **cannot corrupt player state**. That is the property being bought.
- Every spend path costs a database round trip inside a transaction with row
  locking. This is measured in Phase 39, and it is the correct trade.
- Cache invalidation is an explicit design step for each cached read, not an
  afterthought.
- Locks must have timeouts and be safe to lose; a lock is an optimisation over the
  database's own guarantees, never the only thing preventing a double-spend.
