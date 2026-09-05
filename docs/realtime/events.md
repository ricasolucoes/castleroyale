# Realtime event catalogue

## Envelope

Every broadcast event carries:

```json
{
  "event": "march.arrived",
  "sequence": 1042,
  "world_id": "01J...",
  "occurred_at": "2026-08-24T07:00:00+00:00",
  "data": { }
}
```

`sequence` is monotonic **per channel**. A gap means the client missed something
and must resync over HTTP (Phase 40).

## Naming

`<subject>.<past-tense-verb>` — lowercase, dot-separated. Events name facts that
already happened, never requests.

`march.arrived`, not `MoveArmy`.

## Catalogue

| Event | Channel | Phase |
|-------|---------|-------|
| `city.state_changed` | `city.{id}` | 07 |
| `resources.updated` | `city.{id}` | 08 |
| `building.started` | `city.{id}` | 09 |
| `building.completed` | `city.{id}` | 09 |
| `research.completed` | `player.{id}` | 10 |
| `training.completed` | `city.{id}` | 12 |
| `hero.acquired` | `player.{id}` | 13 |
| `march.started` | `player.{id}` | 15 |
| `march.arrived` | `player.{id}` | 15 |
| `march.incoming` | `city.{id}` | 19 |
| `battle.started` | `battle.{id}` | 17 |
| `battle.finished` | `battle.{id}`, `player.{id}` | 17 |
| `city.captured` | `player.{id}`, `world.{w}.region.{r}` | 20 |
| `territory.changed` | `world.{w}.region.{r}` | 21 |
| `alliance.member_joined` | `alliance.{id}` | 22 |
| `alliance.rally_started` | `alliance.{id}` | 24 |
| `chat.message` | scope channel | 25 |
| `diplomacy.changed` | `alliance.{id}` | 26 |
| `market.order_filled` | `player.{id}` | 27 |
| `event.started` | `world.{w}` | 31 |
| `map.delta` | `world.{w}.region.{r}` | 06 |

## Payload rules

- **Identifiers and deltas, not full entities.** The client refetches or patches;
  a full entity in every event is bandwidth and a second source of truth.
- **Never include another player's private data.** A `march.incoming` event tells
  the defender that something is coming and when — not the exact composition,
  unless scouting earned it.
- Payloads are versioned with the content versions from `/health` so a client that
  does not understand the current rules can prompt an update rather than
  misrendering.

## Client handling

An event **invalidates or patches the TanStack Query cache**. It never writes to a
parallel Zustand store — that would create a second truth (`docs/mobile/architecture.md`).
