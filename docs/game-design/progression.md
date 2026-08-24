# Progression

## The loop

```
Collect resources → Build city → Research → Train troops → Recruit heroes
    → Explore world → Attack NPCs → Take territory → Attack players
    → Join alliance → Fight wars → Hold strategic cities → Dominate regions
    → Compete for server supremacy
```

The loop must stay interesting for a player with ten minutes a day and for one with
three hours.

## Starting state

A new player receives:

- one city in a **beginner zone** on the outer ring of the world
- starting resources sufficient for the first several upgrades
- a new-player **shield** for a documented duration
- the tutorial quest chain (Phase 45)

## Pacing

| Stage | Focus | Rough horizon |
|-------|-------|---------------|
| **First session** | Tutorial, first buildings, first troops | Minutes |
| **First week** | Economy, first NPC clears, first alliance | Days |
| **Early game** | Research, hero roster, gathering | Weeks |
| **Mid game** | PvP, territory, alliance warfare | Weeks–months |
| **Late game** | Strategic cities, regional control, seasons | Months |

Projected by simulation across casual, average and heavy play profiles, and tuned
in Phase 46 — not by intuition.

## Power

Stored as an **auditable breakdown**, never a single opaque number:

```
building_power + technology_power + army_power + hero_power + territory_power
```

A player asking "why did my power drop" must get a real answer. That requirement is
what forces the breakdown.

## Nobility

A parallel social ladder: Citizen → Knight → Baron → Viscount → Count → Marquis →
Duke → Prince → King → Emperor.

Ranks require combinations of power, honour, territory, achievements and season
position. Privileges must be **observable** — an unlocked action or a recomputed
value, not a cosmetic title (Phase 29).

## Protecting new players

A new player must never be a free farm for a veteran:

- an unbreakable shield for the documented duration → `PLAYER_PROTECTED`
- power-difference gates → `POWER_DIFFERENCE_TOO_LARGE`
- beginner zones on the world's outer ring
- Warehouse and Treasury shield resources from plunder
- **a player cannot lose their last city** — the attack resolves as a defeat

Leaving the beginner zone is a *choice* with an upside, not an ambush.

## Catching up

Systems that keep a late joiner viable, so a server does not calcify:

- NPC camps scale to player level
- alliance membership grants immediate access to alliance technology
- seasons reset competitive standing (Phase 32)
- events offer bounded acceleration (Phase 31)
