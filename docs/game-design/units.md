# Units

## Roster

| Unit | Class | Role |
|------|-------|------|
| **Legionaries** | Infantry | Frontline; absorbs damage |
| **Archers** | Ranged | Damage from range; weak in melee |
| **Armored Cavalry** | Cavalry | Fast; breaks ranged lines |
| **Scouts** | Recon | Intelligence; minimal combat value |
| **Ballista** | Siege | Anti-fortification, direct |
| **Trebuchet** | Siege | Anti-fortification, heavy, slow |

## Stats

Every unit carries: attack, defence, health, speed, range, capacity, training
cost, training time, upkeep, counter bonuses, siege damage.

All of it is **data** in `packages/game-data/data/units.json` (ADR-013). Not one of
these numbers appears in PHP — an architecture test enforces it.

## Counters

The base relationships:

```
Infantry  >  Cavalry
Cavalry   >  Archers
Archers   >  Infantry
Siege     >  Fortifications
```

**This is a starting point, not the system.** A pure rock-paper-scissors resolves
battles before they are fought, which removes the strategy the game is about.

The counter matrix is a **data table** of `(attacker_class, defender_class) →
permille modifier`, never a hardcoded branch. On top of it, the outcome is also
shaped by:

- **Terrain** — forest and hills favour defenders and archers
- **Formation** — how the army is arranged
- **Heroes** — commander skills and specialisation
- **Technology** — researched military and defence bonuses
- **Morale** — degrades with losses; a broken army withdraws
- **Walls** — absorb damage before troops take losses

A player who brings the countering unit should have an advantage. A player who
brings the countering unit *and* the terrain *and* the right commander should win.
That gap is where the strategy lives.

## Power

Unit power contribution is a **pure function** with no database access, so it can
be called cheaply in army composition (Phase 14) and rankings (Phase 30).

## Balance

Tuned by batch simulation over the full matchup matrix (Phase 47). No composition
may exceed the documented win-rate ceiling. Changes are data-only, and old battle
replays must still reproduce their original results (ADR-015).
