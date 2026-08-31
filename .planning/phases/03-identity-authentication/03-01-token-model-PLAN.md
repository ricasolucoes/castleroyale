---
wave: 1
depends_on: ["01-01", "01-02", "01-03", "01-04"]
files_modified:
  - packages/contracts/openapi.yaml
  - apps/api/modules/Identity/Domain/Account.php
  - apps/api/modules/Identity/Infrastructure/TokenRepository.php
  - apps/api/modules/Identity/Application/LoginHandler.php
  - apps/api/modules/Identity/Application/TokenRefresher.php
  - tests/Feature/Identity/TokenRotationTest.php
autonomous: true
---

# Plan 03-01: Accounts, credentials and the token model with rotation

## Goal
Establish the Account entity, password credentials, and a Sanctum-backed token model that enforces short-lived access tokens and rotating refresh tokens with reuse detection.

## Requirements
- REQ-01, REQ-10, REQ-14

## Context
- Tokens: Access token lives 60 min, refresh token lives 30 days and rotates.
- Reuse of rotated refresh token revokes all tokens for that account (family revocation) and returns `TOKEN_EXPIRED`.
- The OpenAPI contract is established in this plan.

## Tasks

<task>
<read_first>
- packages/contracts/openapi.yaml
- docs/api/api-guidelines.md
</read_first>
<action>
Create the initial OpenAPI schema at `packages/contracts/openapi.yaml`.
Add the `ErrorCode` enum containing exact strings `TOKEN_EXPIRED` and `INVALID_CREDENTIALS`.
Add endpoints for `POST /api/v1/auth/login` (email, password), `POST /api/v1/auth/refresh` (refresh_token), and `POST /api/v1/auth/logout`. Require the `Idempotency-Key` header on all these mutating endpoints.
Run `npm run contracts:generate` to generate TS types.
</action>
<acceptance_criteria>
- `packages/contracts/openapi.yaml` contains `TOKEN_EXPIRED`
- `packages/contracts/openapi.yaml` contains `INVALID_CREDENTIALS`
- Mutating endpoints define the `Idempotency-Key` header
- `npm run contracts:check` exits 0
</acceptance_criteria>
</task>

<task>
<read_first>
- apps/api/database/migrations/
- apps/api/modules/Identity/Domain/Account.php
</read_first>
<action>
Create migration `create_accounts_table` with `id` (ulid), `email` (string, nullable, unique), `password` (string, nullable), and `shield_expires_at` (timestamp, nullable) for beginner protection (REQ-10).
Create `Game\Identity\Domain\Account` extending Laravel Model.
Configure Laravel Sanctum for API tokens. Add a migration to add `refresh_token` (string, unique, nullable), `expires_at` (timestamp), and `family_id` (ulid) to the `personal_access_tokens` table.
</action>
<acceptance_criteria>
- `apps/api/modules/Identity/Domain/Account.php` contains `class Account extends Model`
- `php artisan migrate --pretend` outputs the accounts table creation
- `php artisan migrate` exits 0
</acceptance_criteria>
</task>

<task>
<read_first>
- apps/api/modules/Identity/Application/LoginHandler.php
- apps/api/modules/Identity/Application/TokenRefresher.php
</read_first>
<action>
Implement `Game\Identity\Application\LoginHandler` that accepts email and password, validates using Hash::check, and issues a new access token (expires in 60 min) and refresh token (expires in 30 days) under a new `family_id`.
Implement `Game\Identity\Application\TokenRefresher` that takes a refresh token.
If the refresh token is valid and not expired, issue a new access/refresh pair with the same `family_id`, and delete the old refresh token.
If the refresh token does not exist in DB (reuse detection), find the most recently used token for that user, get its `family_id`, and delete ALL tokens for that `family_id`. Return an API error with `ErrorCode::TOKEN_EXPIRED`.
</action>
<acceptance_criteria>
- `apps/api/modules/Identity/Application/LoginHandler.php` contains `Hash::check`
- `apps/api/modules/Identity/Application/TokenRefresher.php` contains `ErrorCode::TOKEN_EXPIRED`
</acceptance_criteria>
</task>

<task>
<read_first>
- tests/Feature/Identity/TokenRotationTest.php
</read_first>
<action>
Write `tests/Feature/Identity/TokenRotationTest.php`.
Test 1: Successful login returns tokens.
Test 2: Successful refresh rotates tokens and invalidates the old one.
Test 3: Refreshing with an already-used refresh token revokes all tokens for that family and returns `ErrorCode::TOKEN_EXPIRED`.
Test 4: Mutating endpoints (login, refresh, logout) are tested for double-submit using the Idempotency-Key.
</action>
<acceptance_criteria>
- `TokenRotationTest.php` exists
- `./vendor/bin/pest --filter TokenRotationTest` exits 0
</acceptance_criteria>
</task>

## Verification
- `make migrate`
- `./vendor/bin/phpstan analyse --memory-limit=1G`
- `./vendor/bin/pest --filter Identity`

## Must Haves
- Access tokens expire (60m).
- Refresh tokens rotate on use.
- Refresh token reuse revokes the whole family.
- Error codes match OpenAPI.
