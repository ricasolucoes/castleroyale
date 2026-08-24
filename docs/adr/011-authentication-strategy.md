# ADR-011: Token authentication with rotating refresh

**Status:** Accepted
**Date:** 2026-08-24

## Context

A mobile game must keep players signed in for months without re-prompting, while
a stolen token must not grant indefinite access. Players expect to start playing
before creating an account, and to sign in with Apple or Google. They also expect
to be able to kick a device they no longer own.

## Decision

**Laravel Sanctum personal access tokens**, with:

- **Short-lived access tokens** (`AUTH_ACCESS_TOKEN_TTL_MINUTES`, default 60).
- **Long-lived refresh tokens** that **rotate on every use**
  (`AUTH_REFRESH_TOKEN_TTL_DAYS`, default 30).
- **Reuse detection**: presenting an already-rotated refresh token revokes the
  entire session family and returns `TOKEN_EXPIRED`. A replayed refresh token is
  the signature of theft, so the response is to invalidate everything, not to
  issue a fresh pair.
- **Device sessions** as first-class records: `device_id`, `device_name`,
  `platform`, `last_seen_at`, `ip`, `created_at`, `revoked_at`. A player can list
  and revoke them; a revoked device gets `DEVICE_SESSION_REVOKED`.
- **Guest accounts** created with no user input, upgradeable to email/password or
  a social provider **without losing progress**.
- **Apple and Google sign-in** verified server-side against the provider. An
  identity token from the client is verified, never trusted.
- Credentials on device live **only** in SecureStore/Keychain — never MMKV, never
  AsyncStorage. A test asserts this.

Auth endpoints are rate limited separately and more tightly than gameplay
(`RATE_LIMIT_AUTH_PER_MINUTE`), and return `RATE_LIMITED` rather than
`INVALID_CREDENTIALS` once tripped, so the limiter is not an oracle.

## Alternatives

**Stateless JWT.** Rejected: revocation is the requirement that matters here, and
stateless tokens make "kick this device now" either impossible or a blocklist —
which is a session table with worse ergonomics.

**Long-lived non-rotating tokens.** Rejected: a stolen token is then permanent.

**OAuth2 with a full authorisation server.** Rejected as disproportionate for a
first-party client with no third-party integrations.

## Consequences

- Every request hits the token store. It is indexed and cacheable, and measured
  in Phase 39.
- Rotation means the client must handle a refresh race: two in-flight requests
  both refreshing. The client serialises refresh through a single-flight guard.
- Guest upgrade must be transactional — a partial upgrade that loses an empire is
  the worst possible bug in this area, and it is tested explicitly.
- Expired tokens are pruned on a schedule (`sanctum:prune-expired`).
