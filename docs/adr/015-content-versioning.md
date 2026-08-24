# ADR-015: Versioned game content and replay safety

**Status:** Accepted
**Date:** 2026-08-24

## Context

Balance will change many times over the life of the game. A battle fought in
March under one set of unit stats must still replay correctly in September under
different stats — otherwise every historical battle report becomes a lie, and
disputes become unresolvable.

The same applies to economy rules: an audit of a ledger entry must be able to say
which cost table was in force at the time.

## Decision

**Three independent version counters**, exposed in `config/game.versions`:

| Version | Governs | Bumped when |
|---------|---------|-------------|
| `data` | The game-data bundle as a whole | Any dataset changes |
| `combat` | Unit stats, counters, damage rules, simulator behaviour | Anything affecting battle outcome |
| `economy` | Costs, production rates, capacities, tax | Anything affecting resource flow |

Rules:

- Every **battle** persists `simulation_version` and the `combat_version` it ran
  under. Replaying it uses those versions, not the current ones (ADR-009).
- Every **ledger entry** records the `economy_version` in force.
- Old versions of balance data and simulator code stay executable. They are
  retired only when the records referencing them fall out of retention.
- `GET /api/v1/health` reports all three so the client can detect a mismatch and
  prompt an update rather than rendering stale rules.
- Bumping a version is part of the change, enforced by a CI check that fails when
  a dataset is modified without a version bump.

## Alternatives

**A single global content version.** Rejected: a cosmetic quest text change would
invalidate combat replay caches for no reason. Separate axes let each change
invalidate only what it affects.

**Snapshotting the full ruleset into every battle record.** Correct and rejected
on storage: the full unit table per battle, at MMO volume, dwarfs everything else.
A version pointer is a few bytes.

**Accepting that old replays break.** Rejected: it destroys the dispute-resolution
property that justified determinism in the first place.

## Consequences

- Multiple simulator versions may coexist in the codebase. This is deliberate, and
  each is covered by its own determinism test.
- A rebalance requires a regression run proving old replays still reproduce their
  original results (Phases 46 and 47).
- Version bumps are mechanical and enforced, so they cannot be forgotten in a
  hurried tuning pass.
- Retention policy for battles becomes a real decision, since it determines when
  an old simulator version can finally be deleted.
