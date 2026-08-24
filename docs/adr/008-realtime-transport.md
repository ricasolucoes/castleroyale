# ADR-008: Laravel Reverb as the realtime transport

**Status:** Accepted
**Date:** 2026-08-24

## Context

Players need to see incoming attacks, march arrivals, alliance chat, rally
coordination and neighbouring map changes without polling. Polling at the
required latency would multiply request volume for data that usually has not
changed.

## Decision

Use **Laravel Reverb** as the websocket server, speaking the Pusher protocol so
the client uses the standard, well-supported Echo client.

Channel design:

- **Every channel is private.** There is no public channel — knowing which regions
  are busy is itself strategic intelligence.
- Channel scopes: `player.{id}`, `city.{id}`, `alliance.{id}`, `battle.{id}`,
  `world.{worldId}.region.{regionId}`.
- Authorisation callbacks live in `routes/channels.php` and **deny by default**.
  Each is implemented by the phase that owns the scope; until then it returns false.
- World traffic uses **delta updates**, never repeated full snapshots (Phase 06).
- Realtime events carry a **sequence number**. A client detecting a gap resyncs
  via HTTP rather than continuing from a divergent state (Phase 40).

Realtime is for what genuinely needs it. Anything the player will see on their
next screen open goes over HTTP.

## Alternatives

**Pusher or Ably (hosted).** Lower operational burden, rejected on per-message
cost at MMO fan-out volume and on data residency. The protocol is identical, so
switching later is a configuration change.

**Server-Sent Events.** Simpler, rejected: one-way only, and chat plus rally
coordination need a client-to-server path.

**Polling.** Rejected on request volume, but retained as the degraded fallback
when websockets cannot connect.

## Consequences

- Reverb is a stateful process to run, monitor and scale separately from the API.
- Websocket authorisation is a real attack surface. Channel callbacks are tested
  for both the allow and the deny case in every phase that adds one.
- Broadcasting is queued on the `realtime` tier so a slow fan-out never blocks the
  request that triggered it.
- Delta updates require the client to hold reconcilable state, which is why event
  sequencing and resync are a dedicated phase rather than an afterthought.
- `REVERB_APP_SECRET` is server-only. The client receives the key, host, port and
  scheme, never the secret.
