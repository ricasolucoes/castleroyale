# Nobility

Built in GSD Phase 29.

## Why it exists

Power measures what you have. Nobility measures what you have *done*, and it
persists through a bad week. It gives a player who has lost cities something they
have not lost, and gives long-term play a visible marker.

## The ladder

```
Citizen → Knight → Baron → Viscount → Count → Marquis → Duke → Prince → King → Emperor
```

Names are placeholders and may change without any structural consequence — they
are data.

## Requirements

Each rank requires a combination of:

| Requirement | Source |
|-------------|--------|
| **Power** | The auditable breakdown |
| **Honour** | PvP conduct — earned by fair fights, lost by farming the weak |
| **Territory** | Held land |
| **Achievements** | Specific milestones |
| **Season position** | Competitive standing |

Requirements are **data**, evaluated by a pure function (ADR-013). Promotion is
automatic when the requirements are met, and never granted twice for the same rank.

## Honour is the interesting one

Honour rises from fighting opponents near or above your own power, and falls from
attacking far weaker players.

This is the mechanism that makes new-player protection a *design* feature rather
than only a restriction. The rules already forbid attacking far below your power
(`POWER_DIFFERENCE_TOO_LARGE`); honour makes the behaviour unattractive right up
to that boundary, instead of merely illegal past it.

## Privileges must be real

A rank must confer something **observable** — an unlocked action or a recomputed
value. A purely cosmetic ladder is not what this phase delivers.

Examples: additional march slots, alliance capacity, trade tax reduction, access
to rank-gated events.

Privileges must not be so strong that they compound — a ladder that makes the
leader permanently unbeatable ends the server.

## Demotion

Losing the underlying qualification applies either a documented demotion or a
documented grace period. **Which one is a decision Phase 29 must make and record**
— leaving it implicit produces a rank system nobody can predict, which is worse
than either choice.
