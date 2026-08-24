# Buildings

## Catalogue

| Building | Function |
|----------|----------|
| **Palace** | City centre; gates the level of every other building |
| **Barracks** | Trains infantry |
| **Archery Range** | Trains archers |
| **Stable** | Trains cavalry |
| **Siege Workshop** | Builds siege engines |
| **Academy** | Research |
| **Embassy** | Alliance capacity and reinforcement slots |
| **Marketplace** | Trade and transport capacity |
| **Warehouse** | Storage capacity; protects resources from plunder |
| **Hospital** | Recovers a portion of wounded troops |
| **Walls** | Defensive durability |
| **Watchtower** | Detects incoming marches; resists scouting |
| **Farm** | Produces food |
| **Lumber Mill** | Produces wood |
| **Quarry** | Produces stone |
| **Iron Mine** | Produces iron |
| **Treasury** | Produces gold; protects a portion from plunder |
| **Tavern** | Recruits heroes |

## Per-level data

Every building level defines: `level`, `requirements`, `cost`, `build_time`,
`effects`, `capacity`, `unlock_conditions`.

All of it lives in `packages/game-data/data/buildings.json`. **No cost, duration,
effect or capacity appears in PHP** — enforced by an architecture test (ADR-013).

## The Palace gate

No building may exceed the Palace level. This gives progression a single spine and
prevents a player from rushing one building far past the rest.

## Construction

- Costs are debited atomically inside the locked command shape
- `started_at` / `finishes_at` / `completed_at`, all UTC, all server-side
- A delayed job completes it idempotently; a reconciler catches what the job missed
- Queue slots bounded by `game.limits.max_build_queue_slots` → `BUILD_QUEUE_FULL`
- Max level → `BUILDING_MAX_LEVEL`; unmet requirements → `BUILDING_REQUIREMENTS_NOT_MET`

`config('game.time_scale')` accelerates durations in **local only**, so a developer
does not wait four real hours to test a completion screen. It is forced to 1
everywhere else.

## Protection

Warehouse and Treasury shield a portion of stored resources from plunder. This is
what stops a single defeat from erasing a player's stockpile and is a core part of
the new-player experience being survivable.
