# Realtime architecture

Transport: **Laravel Reverb**, Pusher protocol, standard Echo client (ADR-008).

## Channels

**Every channel is private.** There is no public channel — knowing which regions
are busy is itself strategic intelligence.

| Channel | Scope | Authorised for | Phase |
|---------|-------|----------------|-------|
| `player.{playerId}` | One player | That player | 04 |
| `city.{cityId}` | One city | Owner (and reinforcers from Phase 24) | 07 |
| `alliance.{allianceId}` | One alliance | Current members | 22 |
| `battle.{battleId}` | One battle | Participants, reinforcers, alliance spectators | 17 |
| `world.{worldId}.region.{regionId}` | Map region | Players in that world with the region in view | 05 |

## Deny by default

Every callback in `routes/channels.php` currently returns `false`. That is
deliberate (DEBT-001): a channel is unusable until the phase that owns it
implements authorisation properly.

**Both the allow path and the deny path are tested** in the implementing phase. An
untested deny path is how a player ends up subscribed to a rival's city.

A kicked alliance member is dropped from the channel **immediately**, not on next
reconnect.

## What goes over realtime

Only what genuinely needs it:

- an incoming attack (before it lands, not after)
- a march arriving
- a battle resolving
- chat and rally coordination
- map deltas for regions in view

Anything the player would see on their next screen open goes over HTTP. Broadcast
volume is a cost, and a chatty channel is a scaling problem.

## Delta updates

World traffic sends **deltas**, never repeated full snapshots. A tile changed
owner; the client patches its cache.

## Sequencing

Every realtime event carries a **sequence number**. A client detecting a gap
resyncs over HTTP rather than continuing from a divergent state (Phase 40).

This matters because a lost event in a delta stream is invisible — the client does
not know it is wrong.

## Reconnection

Exponential backoff **with jitter**. Without jitter, every client reconnects at the
same moment after an outage and takes the server down a second time.

On reconnect: resubscribe, then resync state over HTTP. Server state always wins.

## Queueing

Broadcasts are queued on the `realtime` tier so a slow fan-out never blocks the
request that triggered it.

## Security

- `REVERB_APP_SECRET` is server-only. The client receives key, host, port and
  scheme — never the secret.
- Channel authorisation is a real attack surface (threat model T-14).
- A banned player's channels disconnect immediately (Phase 35).
