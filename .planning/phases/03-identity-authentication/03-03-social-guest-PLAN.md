---
wave: 2
depends_on: ["03-01"]
files_modified:
  - apps/api/modules/Identity/Application/SocialLoginHandler.php
  - apps/api/modules/Identity/Application/GuestLoginHandler.php
  - apps/api/modules/Identity/Application/AccountUpgrader.php
  - packages/contracts/openapi.yaml
  - tests/Feature/Identity/GuestUpgradeTest.php
autonomous: true
---

# Plan 03-03: Apple and Google sign-in plus guest upgrade

## Goal
Support frictionless guest account creation and subsequent upgrade to permanent credentials (email/password or social) without losing account data.

## Requirements
- REQ-01, REQ-10, REQ-14

## Context
- Guest account needs no user input.
- Social tokens verified server-side.
- Upgrade must preserve `account_id`.

## Tasks

<task>
<read_first>
- packages/contracts/openapi.yaml
</read_first>
<action>
Update `openapi.yaml`.
Add `FEATURE_DISABLED` to the `ErrorCode` enum.
Add endpoints `POST /api/v1/auth/guest` (returns tokens).
Add `POST /api/v1/auth/social` (provider: string, identity_token: string).
Add `POST /api/v1/auth/upgrade` (accepts either email/password or social provider/identity_token).
Require the `Idempotency-Key` header on all these mutating POST endpoints.
Run `npm run contracts:generate`.
</action>
<acceptance_criteria>
- `packages/contracts/openapi.yaml` contains `FEATURE_DISABLED`
- `packages/contracts/openapi.yaml` contains `/api/v1/auth/guest`
- `packages/contracts/openapi.yaml` contains `/api/v1/auth/upgrade`
- `npm run contracts:check` exits 0
</acceptance_criteria>
</task>

<task>
<read_first>
- apps/api/modules/Identity/Application/GuestLoginHandler.php
</read_first>
<action>
Implement `Game\Identity\Application\GuestLoginHandler`. Creates an `Account` with null email/password, flags as `is_guest = true` (add this column via a migration). Sets `shield_expires_at` to provide beginner protection (REQ-10). Issues access and refresh tokens.
</action>
<acceptance_criteria>
- `GuestLoginHandler.php` contains `is_guest = true`
- Migration file to add `is_guest` exists
</acceptance_criteria>
</task>

<task>
<read_first>
- apps/api/modules/Identity/Application/SocialLoginHandler.php
</read_first>
<action>
Implement `Game\Identity\Application\SocialLoginHandler`. For Google, use Google API Client to verify `identity_token`. For Apple, use Apple Sign In verifier.
If env vars are missing, return a `FEATURE_DISABLED` error.
Link provider ID to `Account` (add `provider` and `provider_id` columns to `accounts` table via migration). Sets `shield_expires_at` on new account creation (REQ-10).
</action>
<acceptance_criteria>
- `SocialLoginHandler.php` exists
- Migration to add `provider` and `provider_id` exists
- `SocialLoginHandler.php` returns `FEATURE_DISABLED` when env missing
</acceptance_criteria>
</task>

<task>
<read_first>
- apps/api/modules/Identity/Application/AccountUpgrader.php
- tests/Feature/Identity/GuestUpgradeTest.php
</read_first>
<action>
Implement `Game\Identity\Application\AccountUpgrader`. Accepts authenticated guest user. Updates `Account` with email/password (hashed) or social provider details. Sets `is_guest = false`. Must run inside a `DB::transaction`.
Write `tests/Feature/Identity/GuestUpgradeTest.php` asserting that an account retains its ID after upgrade, and a partial failure rolls back. Add double-submit tests for all mutating endpoints (guest, social, upgrade) using the Idempotency-Key.
</action>
<acceptance_criteria>
- `AccountUpgrader.php` contains `DB::transaction`
- `GuestUpgradeTest.php` exists and `./vendor/bin/pest --filter GuestUpgradeTest` exits 0
</acceptance_criteria>
</task>

## Verification
- `make migrate`
- `./vendor/bin/pest --filter Identity`

## Must Haves
- Guest flow requires zero input.
- Upgrade preserves account ID.
- Social tokens verified server-side.
