# Technology

## Categories

| Category | Improves |
|----------|----------|
| **Economy** | Production rates, storage |
| **Military** | Unit attack, training speed and cost |
| **Defense** | Unit defence, wall durability |
| **Logistics** | March speed, carrying capacity, upkeep |
| **Construction** | Build speed, build queue |
| **Exploration** | Scout range and accuracy, viewport |
| **Alliance** | Alliance-wide bonuses, funded by donations |
| **Siege** | Siege damage, fortification bypass |

## The tree

Technologies form a **directed acyclic graph**. Dependencies are explicit, and
cycle detection is a real graph algorithm — not a depth limit that happens to
terminate.

- Locked → `TECHNOLOGY_LOCKED`
- Max level → `TECHNOLOGY_MAX_LEVEL`
- One research at a time per player → `RESEARCH_IN_PROGRESS`

## Effects

Effects are **data**: a typed descriptor of `(target, operation, permille)` applied
by a pure resolver shared with hero bonuses.

Percentage effects use integer permille and truncate downward (ADR-010).

A completed technology's effect must be **observable in a recomputed value** — the
test asserts production actually rose by the documented amount, not merely that a
row was written.

## The validator

Built in Phase 10 and wired into CI. It rejects a dataset containing:

- dependency cycles
- negative costs or durations
- references to ids that do not exist
- duplicate ids
- missing translation keys
- unlock requirements that can never be satisfied

Error messages **name the offending id**. "Invalid dataset" is not an acceptable
failure message when a designer is trying to ship a tuning pass.

This validator is load-bearing: a bad dataset reaching production can break the
game more thoroughly than a bad deploy (ADR-013).
