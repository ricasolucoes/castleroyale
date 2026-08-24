# ADR-009: Deterministic, seeded battle simulation

**Status:** Accepted
**Date:** 2026-08-24

## Context

Battle is the emotional payload of the game and the moment players are most
likely to allege unfairness. A player who lost an army must be able to see
exactly why. Support must be able to reproduce a disputed battle. And a
rebalance must never silently rewrite the history of battles already fought.

## Decision

The battle simulator is a **pure, deterministic function**:

```
simulate(initial_state, seed, commands, simulation_version) -> timeline, result
```

- **No I/O.** No database, cache, clock, or global random access inside the
  simulation core. Enforced by an architecture test.
- **All randomness derives from a server-generated seed** the client never sees
  before resolution. The RNG is an explicit, seeded generator passed through the
  simulation, never `rand()` or `mt_rand()`.
- **Replays store inputs, not frames**: initial state, seed, commands and
  `simulation_version`. The timeline is reconstructed by re-running the simulator,
  so a replay costs a few hundred bytes rather than thousands of frames.
- **`simulation_version` is recorded on every battle.** Replaying an old battle
  runs the simulator version it was fought under (ADR-015), so rebalancing never
  changes a historical outcome.
- Determinism is proven by test: the same inputs must produce a byte-identical
  event timeline and result, every run.

Integer arithmetic throughout. No float enters a damage or casualty computation —
floats are the classic source of platform-dependent divergence.

## Alternatives

**Storing the full battle timeline as data.** Simpler to replay, rejected on
storage: at MMO volume this is the largest table in the database, and it still
does not let you re-derive anything the original recording omitted.

**Non-deterministic simulation with a stored result.** Rejected: no replay, no
dispute resolution, and no way to detect a simulation bug after the fact.

**Client-side simulation with server verification.** Rejected outright — it
requires shipping the seed and rules to the client (ADR-006).

## Consequences

- Every input that affects the outcome must be captured in the initial state.
  An input read from outside — a config value, a global — silently breaks replay.
  This is the main failure mode to guard against in review.
- Old simulation versions must be kept executable. They cannot be deleted when a
  new one ships; they are retired only when their battles are past retention.
- The purity constraint makes the simulator trivially unit-testable and, later,
  trivially extractable into its own service (ADR-001).
- Determinism enables balance work: Phase 47 runs the full matchup matrix in batch
  because the simulator needs nothing but inputs.
