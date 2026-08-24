# Offline strategy

Built in GSD Phase 40.

## The principle

The server owns the truth (ADR-006), so **no server-authoritative action may ever
be presented as confirmed while offline.** Showing a player that their march
departed when the server never heard about it is worse than showing an error.

Cached *reads* are fine and expected. Committed *writes* are not.

## Connection states

Four, all visible to the player, each reachable in a test:

| State | Meaning | UI |
|-------|---------|-----|
| **Connected** | Websocket up, requests succeeding | Normal |
| **Synchronizing** | Reconnected, catching up | Subtle banner |
| **Reconnecting** | Backing off, retrying | Banner with attempt |
| **Offline** | No connectivity | Persistent banner; actions disabled or queued |

## What works offline

| Works | Does not |
|-------|----------|
| Viewing cached city, map, armies, heroes | Starting a build |
| Reading catalogues and past battle reports | Dispatching a march |
| Composing a draft (not sending) | Claiming a reward |
| Browsing settings | Anything that spends or grants |

Cached data is labelled with its age. Silence about staleness is its own lie.

## Reconnection

Exponential backoff **with jitter**. Without jitter every client reconnects at the
same instant after an outage and takes the server down a second time.

On reconnect: resubscribe to channels, then resync over HTTP. Server state
overwrites local state on every conflict, always.

## Event sequencing

Realtime events carry a monotonic sequence number per channel. A gap means the
client missed something — and in a delta stream a missed event is invisible, so
the client cannot detect divergence any other way.

```
gap detected → discard local delta state → refetch over HTTP → resume
```

## Queued actions

A small, explicit queue for actions attempted while offline. Rules:

- The player sees them as **pending**, never as done
- Each carries its `Idempotency-Key`, so replay on reconnect is safe
- Anything time-sensitive (an attack whose window has passed) is discarded with
  an explanation rather than executed late
- The queue is bounded; overflow is refused, not silently dropped

## Conflict resolution

The server always wins. The client is a projection.

If a queued action fails on replay because the world changed — the target moved,
the resources were spent — the player is told what happened. The client never
attempts to reconcile game state on its own.
