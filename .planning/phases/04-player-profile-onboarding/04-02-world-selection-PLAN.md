---
wave: 2
depends_on: ["04-01"]
files_modified:
  - apps/api/modules/World/
  - apps/api/database/migrations/
  - apps/api/routes/api.php
  - packages/contracts/openapi.yaml
  - apps/api/tests/Feature/World/
autonomous: true
---

# Plan 04-02: World selection and capacity rules

## Goal
Let an authenticated account inspect available worlds and join an open world
while enforcing capacity and closed-world errors.

## Tasks

1. Add the World model and world metadata migration, including population,
   structural capacity, status and open/closed state.
2. Add the authenticated world list and selection endpoints. Return population,
   capacity, status and whether the account already has a player.
3. Lock the selected world inside a transaction, re-check capacity under the
   lock, and return WORLD_FULL or WORLD_CLOSED through ApiResponse.
4. Add tests for open, full and closed worlds, concurrent capacity claims and
   idempotent selection.

## Acceptance criteria

- World list includes population and status.
- Full worlds return WORLD_FULL and closed worlds return WORLD_CLOSED.
- Capacity cannot be exceeded under concurrent requests.

## Verification

- Run World feature tests, full Pest, PHPStan, Pint and contracts:check.
