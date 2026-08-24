# Combat

## Properties

Server-authoritative (ADR-006) and deterministic (ADR-009).

```
simulate(initial_state, seed, commands, simulation_version) -> timeline, result
```

- **Pure.** No database, cache, clock or global random inside the simulation core.
- **Seeded.** All randomness from a server-generated seed the client never sees
  before resolution.
- **Integer.** No float touches damage or casualties — floats diverge across
  platforms and break determinism.
- **Replayable.** Identical inputs produce byte-identical timelines, always.

## Inputs

`battle_id`, `seed`, armies, heroes, formation, terrain, technology, buffs,
structures, commands.

**Every input that affects the outcome must be in `initial_state`.** An input read
from outside — a config value, a global, a database lookup — silently breaks
replay. This is the primary review checkpoint on any combat change.

## Outputs

`timeline`, `events`, `damage`, `casualties`, `survivors`, `experience`, `loot`,
`result`.

## Resolution

Battle proceeds in rounds. Each round:

1. Ranged units fire; range and terrain determine who can reach whom
2. Melee engages; counter multipliers apply
3. Siege applies its bonus against fortifications
4. Casualties are computed; morale adjusts
5. A side whose morale breaks withdraws

Modifiers stack multiplicatively in integer permille, applied in a **documented,
fixed order** — order matters and must not be incidental.

## What decides a battle

Never one thing. In rough order of weight: army size and composition, counters,
technology, hero, terrain, formation, walls, morale.

A hero must be **decisive but not the sole determinant** — a hero that wins every
battle makes units irrelevant. The influence band is tuned in Phase 47.

## Replay storage

Persist `initial_state`, `seed`, `commands`, `simulation_version`, `combat_version`.

**Not a frame dump.** The timeline is re-derived by re-running the simulator, so a
replay costs a few hundred bytes rather than thousands of frames.

Replaying an old battle uses the version it was fought under (ADR-015). Old
simulator versions stay executable and are retired only when their battles fall out
of retention.

## Losses

Attacker and defender both take losses. A portion of the defender's wounded is
recoverable via the Hospital. Total loss on both sides is the norm for an even
fight — that is what makes committing an army a real decision.

## Future scope

The MVP resolves a battle in one server-side simulation. The architecture does not
prevent richer tactical battles — movement, target selection, abilities,
withdrawal, reinforcements mid-battle — because those are all just `commands` in
the same signature.

**Real-time tactical control by two humans simultaneously is explicitly out of
scope** for this milestone.
