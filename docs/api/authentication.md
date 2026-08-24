# Authentication

Implements ADR-011. Built in GSD Phase 03.

## Token model

| Token | Lifetime | Storage on device | Purpose |
|-------|----------|-------------------|---------|
| Access | `AUTH_ACCESS_TOKEN_TTL_MINUTES` (60) | SecureStore/Keychain | Sent as `Authorization: Bearer` |
| Refresh | `AUTH_REFRESH_TOKEN_TTL_DAYS` (30) | SecureStore/Keychain | Exchanged for a new pair |

**Refresh tokens rotate.** Every refresh issues a new refresh token and
invalidates the old one.

**Reuse detection.** Presenting an already-rotated refresh token means it leaked.
The response is to revoke the entire session family and return `TOKEN_EXPIRED` —
not to issue a fresh pair. A thief and the legitimate player cannot both keep
using the account.

## Flows

### Guest start

No input required. The server creates an account flagged as guest plus a device
session, and returns a token pair. The player is in the game in one tap.

### Upgrade

A guest supplies email/password or a social identity. The **same** account gains
credentials — id, player and empire are all preserved. This runs in one
transaction; a partial upgrade that loses an empire is the worst bug in this area.

### Email/password

Registration and sign-in. Passwords are hashed with the framework default. Failed
attempts are rate limited per IP and per account, and once tripped return
`RATE_LIMITED` rather than `INVALID_CREDENTIALS` so the limiter is not an oracle
for valid usernames.

### Apple / Google

The client obtains an identity token and posts it. The **server verifies it
against the provider** — signature, audience, issuer, expiry. A client-supplied
identity token is never trusted on its face.

## Device sessions

Each sign-in creates a session recording `device_id`, `device_name`, `platform`,
`ip`, `created_at`, `last_seen_at`, `revoked_at`.

- A player lists their sessions and revokes any of them.
- A revoked session's next request returns `DEVICE_SESSION_REVOKED` and its
  websocket is disconnected.
- Sessions above `AUTH_MAX_DEVICE_SESSIONS` evict the least recently used.
- Push tokens are attached to the session and removed with it (Phase 33).

## Client rules

- Credentials live **only** in SecureStore/Keychain. Never MMKV, never
  AsyncStorage, never Zustand, never a log line. Asserted by a test.
- Refresh is **single-flight**: concurrent 401s must not each trigger a refresh,
  or rotation invalidates the winner's token. The client serialises through one
  in-flight refresh promise.
- On `TOKEN_EXPIRED` or `DEVICE_SESSION_REVOKED`, clear local credentials and
  return to sign-in. Do not retry.

## Back office

Filament uses **session** authentication, a separate path from the API. Access
requires `is_staff` via `User::canAccessPanel()`, and the panel path is
configurable (`FILAMENT_PATH`) as defence in depth — not as the access control.
