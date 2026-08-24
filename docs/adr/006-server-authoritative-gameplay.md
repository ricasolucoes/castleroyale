# ADR-006: Server-authoritative gameplay

**Status:** Accepted
**Date:** 2026-08-24

## Context

The client is a React Native app on a device the player controls. It can be
decompiled, its traffic intercepted and rewritten, its clock changed, and its
requests replayed. In a competitive MMO with a persistent economy, any state the
client is trusted to report will eventually be forged.

## Decision

**The server decides everything that matters.** The client sends *intent*; the
server computes *outcome*.

Concretely:

- The client sends `POST /cities/{id}/buildings/{slot}/upgrade`. It never sends
  the cost, the duration, or the resulting level.
- The client sends an attack order. It never sends the battle result.
- All timing is server-side. `started_at` and `finishes_at` are written by the
  server in UTC from the `Clock` contract. The device clock is never an input to
  any rule — it is used only to render a countdown.
- Game rules never call `now()`, `time()` or read a request timestamp. An
  architecture test enforces this against `Game\...\Domain`.
- Every mutation is authorised against the acting player, and every read is scoped
  so a player cannot address another player's entity by guessing an id.

The mobile app is a **projection of server truth**. On any conflict, the server
wins and the client resynchronises (Phase 40).

## Alternatives

**Client-side prediction with server reconciliation.** Standard in action games,
rejected here: the loop is minutes-to-hours, not milliseconds, so prediction buys
nothing while adding a rollback path that is itself an exploit surface. Optimistic
UI is still allowed for *display*, never for committed state.

**Trusting the client for non-economic values** (cosmetics, UI preferences).
Accepted in that narrow sense — preferences are client-owned by definition. The
boundary is: if it can be converted into power or resources, the server owns it.

## Consequences

- Every gameplay action is a round trip. The UI must be designed for latency:
  skeletons, pending states, and honest offline behaviour (Phase 40).
- Server cost is higher than a trusting design. That is the price of an economy
  that cannot be forged.
- Exploit tests are a first-class deliverable (Phase 37), not a security review
  bolted on at the end.
- Offline play is impossible by construction. Cached *reads* are shown; actions
  are queued or refused explicitly, never confirmed locally.
