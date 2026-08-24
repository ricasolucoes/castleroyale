# Heroes

Heroes are commanders and governors, not stat sticks. They are meant to make a
composition decision interesting, not to replace it.

## Attributes

`id`, `name`, `rarity`, `level`, `experience`, `stars`, `class`, `attributes`,
`skills`, `equipment`, `talents`, `specialisation`, `army_bonuses`, `city_bonuses`.

Reference data (rarity, class, base attributes, skills) lives in
`packages/game-data/data/heroes.json`. Per-player state (level, experience, stars,
equipment) lives in the database.

## Roles

| Role | Applies to | Effect |
|------|------------|--------|
| **Commander** | An army | Attack, counter and morale bonuses |
| **Defender** | A city | Defensive and wall bonuses |
| **Governor** | A city | Production and construction bonuses |
| **Gatherer** | A gathering march | Yield and capacity |
| **Scout** | A scouting march | Intelligence accuracy |
| **Siege Commander** | A siege | Fortification damage |
| **Support** | An army | Healing, recovery, upkeep reduction |

**One assignment at a time.** A hero commanding an army is not also governing a
city; a second assignment returns `CONFLICT`. This is what makes hero collection a
real allocation decision rather than a pile of passive bonuses.

## Progression

- **Experience** from battles and assignments. Server-computed — a client-supplied
  level or experience value is ignored entirely, and that is tested.
- **Levels** raise base attributes.
- **Stars** are a rarity-bounded upgrade track, and a long-term gold and material
  sink (Phase 46).
- **Skills** unlock at levels; **talents** are a chosen branch.
- **Equipment** occupies slots and contributes stats.

## Bonuses

Hero bonuses are **typed effect descriptors** resolved by the same pure resolver as
technology effects (Phase 10). There is one effect system, not two.

An assigned hero's bonus must be **observable in a recomputed value** — the test
asserts the number changed, not that a row exists.

## Balance

Hero contribution stays inside a documented **influence band**: decisive, but never
the sole determinant of a battle. Tuned by simulation in Phase 47.

## Acquisition

Recruited at the Tavern. The acquisition economy — rates, currencies, pity
mechanics — is deliberately **not** designed in Phase 13. Monetisation must never
become load-bearing for the core loop (`.planning/PROJECT.md`), so acquisition
economics are settled alongside the store, from Phase 50.
