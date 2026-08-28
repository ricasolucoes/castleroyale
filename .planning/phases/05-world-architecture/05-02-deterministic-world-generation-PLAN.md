---
wave: 2
depends_on: ["05-01"]
files_modified:
  - packages/game-data/data/
  - apps/api/modules/World/Domain/
  - apps/api/modules/World/Application/
  - apps/api/app/Console/
  - apps/api/tests/Unit/World/
  - apps/api/tests/Feature/World/
autonomous: true
---

# Plan 05-02: Deterministic world generation

## Goal

Generate a world's terrain exactly once from a stored seed, with all generation
parameters coming from versioned game data rather than PHP literals.

## Tasks

1. Add the world-generation dataset and validation rules for region size,
   terrain distribution, map dimensions and beginner-zone parameters.
2. Implement a pure generator whose output is byte-identical for identical seed,
   version and parameters, and whose output is independent of database state.
3. Add an idempotent `game:generate-world` command that materialises regions and
   tiles once at world creation; requests never generate map data.
4. Test same-seed identity, different-seed divergence, terrain validity and
   generation idempotency.

## Acceptance criteria

- Identical inputs produce byte-identical terrain and region metadata.
- The command is the only generation entry point and can be safely retried.
- No balance or terrain-distribution number is hardcoded in `Game\\` code.

## Verification

Run pure World unit tests, command/feature tests, game-data validation, full Pest,
PHPStan and Pint.
