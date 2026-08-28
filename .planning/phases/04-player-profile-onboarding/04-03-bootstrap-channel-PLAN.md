---
wave: 3
depends_on: ["04-01", "04-02"]
files_modified:
  - apps/api/modules/Player/
  - apps/api/routes/channels.php
  - apps/api/routes/api.php
  - packages/contracts/openapi.yaml
  - apps/api/tests/Feature/Player/
  - apps/api/tests/Feature/Realtime/
autonomous: true
---

# Plan 04-03: Private player channel and bootstrap document

## Goal
Expose a single authoritative onboarding document and authorize only the
owning account on the private player channel.

## Tasks

1. Create the bootstrap service and endpoint returning player, world, versions
   and realtime key/host/port/scheme/auth endpoint without REVERB_APP_SECRET.
2. Create or invoke the starter-city boundary needed for onboarding without
   moving city mechanics into this phase.
3. Replace the player channel deny stub with a world-scoped ownership check;
   test allow, wrong-account deny and missing-player deny paths.
4. Add OpenAPI schemas and tests for the bootstrap envelope and secret
   exclusion.

## Acceptance criteria

- player.playerId authorizes only its owner.
- Bootstrap returns player, world, versions, realtime details and starter city.
- REVERB_APP_SECRET never appears in the response.

## Verification

- Run bootstrap/channel feature tests, full Pest, PHPStan, Pint and contracts:check.
