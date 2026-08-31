---
wave: 2
depends_on: ["03-01"]
files_modified:
  - apps/api/modules/Identity/Domain/DeviceSession.php
  - apps/api/app/Http/Middleware/CheckDeviceSession.php
  - apps/api/app/Providers/RouteServiceProvider.php
  - packages/contracts/openapi.yaml
  - tests/Feature/Identity/DeviceSessionTest.php
  - tests/Feature/Identity/AuthRateLimitTest.php
autonomous: true
---

# Plan 03-02: Device sessions, revocation and rate limiting

## Goal
Track device metadata alongside tokens, allow remote revocation, and enforce strict rate limiting on auth endpoints.

## Requirements
- REQ-01, REQ-10, REQ-14

## Context
- Device session tracks device_id, device_name, platform, ip, created_at, last_seen_at, revoked_at.
- Revoked session returns DEVICE_SESSION_REVOKED.
- Rate limiting: 10 attempts per minute per IP+Identifier. Returns RATE_LIMITED.

## Tasks

<task>
<read_first>
- packages/contracts/openapi.yaml
</read_first>
<action>
Update `packages/contracts/openapi.yaml`.
Add `DEVICE_SESSION_REVOKED` and `RATE_LIMITED` to `ErrorCode` enum.
Add `GET /api/v1/auth/sessions` (list sessions) and `DELETE /api/v1/auth/sessions/{id}` (revoke session). Require the `Idempotency-Key` header on the DELETE endpoint.
Run `npm run contracts:generate`.
</action>
<acceptance_criteria>
- `packages/contracts/openapi.yaml` contains `DEVICE_SESSION_REVOKED`
- `packages/contracts/openapi.yaml` contains `RATE_LIMITED`
- `npm run contracts:check` exits 0
</acceptance_criteria>
</task>

<task>
<read_first>
- apps/api/database/migrations/
- apps/api/modules/Identity/Domain/DeviceSession.php
</read_first>
<action>
Create `create_device_sessions_table` migration. Columns: `id` (ulid), `account_id` (ulid), `token_id` (nullable, FK to personal_access_tokens), `device_id` (string), `device_name` (string), `platform` (string), `ip` (string), `last_seen_at` (timestamp), `revoked_at` (timestamp, nullable).
Update `LoginHandler` and `TokenRefresher` to populate/update this table. Evict least-recently-used session if count > 5 (`AUTH_MAX_DEVICE_SESSIONS`).
Create `Game\Identity\Domain\DeviceSession` model.
</action>
<acceptance_criteria>
- Migration file exists and `php artisan migrate` exits 0
- `apps/api/modules/Identity/Domain/DeviceSession.php` exists
</acceptance_criteria>
</task>

<task>
<read_first>
- apps/api/app/Http/Middleware/CheckDeviceSession.php
- apps/api/bootstrap/app.php
</read_first>
<action>
Create middleware `App\Http\Middleware\CheckDeviceSession`. If the authenticated token's associated `DeviceSession` has `revoked_at != null`, delete the token, disconnect any Reverb websocket for that session, and return an API response with ErrorCode `DEVICE_SESSION_REVOKED`.
Register middleware in `bootstrap/app.php`.
Create a rate limiter in `App\Providers\RouteServiceProvider` (or `bootstrap/app.php` depending on Laravel 11 setup) for auth endpoints: key by `request->ip() . '|' . request('email', request('device_id'))`. Limit to 10 per minute. On hit, return `RATE_LIMITED` via the ApiResponse envelope.
</action>
<acceptance_criteria>
- `CheckDeviceSession.php` contains `DEVICE_SESSION_REVOKED`
- The rate limiter definition limits to 10 requests per minute
</acceptance_criteria>
</task>

<task>
<read_first>
- tests/Feature/Identity/DeviceSessionTest.php
- tests/Feature/Identity/AuthRateLimitTest.php
</read_first>
<action>
Write `tests/Feature/Identity/DeviceSessionTest.php` to assert: listing sessions works, revoking a session sets `revoked_at`, using a revoked token returns `DEVICE_SESSION_REVOKED`, and the revoke endpoint is tested for double-submit using the Idempotency-Key.
Write `tests/Feature/Identity/AuthRateLimitTest.php` to assert: 11 failed logins from the same IP/email return `RATE_LIMITED`.
</action>
<acceptance_criteria>
- `DeviceSessionTest.php` exists and `./vendor/bin/pest --filter DeviceSessionTest` exits 0
- `AuthRateLimitTest.php` exists and `./vendor/bin/pest --filter AuthRateLimitTest` exits 0
</acceptance_criteria>
</task>

## Verification
- `make migrate`
- `./vendor/bin/phpstan analyse --memory-limit=1G`
- `./vendor/bin/pest --filter Identity`

## Must Haves
- Revoked sessions are blocked.
- Auth endpoints have 10/min rate limit per IP+Identifier.
- Error codes match exactly.
