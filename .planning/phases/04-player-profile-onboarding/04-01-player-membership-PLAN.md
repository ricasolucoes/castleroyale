---
wave: 1
depends_on: ["03-04"]
files_modified:
  - apps/api/modules/Player/
  - apps/api/database/migrations/
  - apps/api/modules/Player/Domain/PlayerNamePolicy.php
  - packages/contracts/openapi.yaml
  - apps/api/tests/Feature/Player/
autonomous: true
---

# Plan 04-01: Player entity, world membership and name validation

## Goal
Represent an account's game presence with one player per world and enforce
world-scoped, screened player names.

## Tasks

1. Add the Player model and migration with ULID id, account_id, world_id, name,
   indexes and unique(account_id, world_id) plus unique(world_id, name).
2. Add a domain name policy with configured length bounds, Unicode-safe
   normalization and deny-list screening; add CONTENT_REJECTED and CONFLICT to
   the shared error contract.
3. Add feature tests for successful creation, duplicate membership/name,
   cross-world isolation, invalid names and idempotent mutation.

## Acceptance criteria

- A second player in the same world returns CONFLICT.
- Names are unique per world and rejected names return CONTENT_REJECTED.
- All player queries include world_id.

## Verification

- Run Player feature tests, full Pest, PHPStan, Pint and contracts:check.
