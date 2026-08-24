# Rate limits

## Tiers

| Class | Default | Env var | Applies to |
|-------|---------|---------|------------|
| Auth | 10/min | `RATE_LIMIT_AUTH_PER_MINUTE` | Sign-in, register, refresh, social |
| Command | 60/min | `RATE_LIMIT_COMMAND_PER_MINUTE` | Build, research, train, march, trade |
| Chat | 20/min | `RATE_LIMIT_CHAT_PER_MINUTE` | Message send |
| General | 120/min | `RATE_LIMIT_API_PER_MINUTE` | Everything else, mostly reads |

## Keying

Prefer the **authenticated player id** over IP. IP alone punishes shared networks
(schools, mobile carriers, an entire country behind CGNAT) and is trivially
rotated by an attacker.

- Authenticated: key on `player_id`, with a secondary IP limit as a floor.
- Unauthenticated (auth endpoints): key on IP **and** on the submitted identifier,
  so one attacker cannot lock out a specific account by hammering it, and cannot
  spray many accounts from one address.

`trustProxies` is restricted to our own edge (`TRUSTED_PROXIES`). Without that,
`X-Forwarded-For` is attacker-controlled and every IP-keyed limit is bypassable —
this is T-10 in the threat model.

## Response

Exceeding a limit returns `RATE_LIMITED` (HTTP 429) with a `Retry-After` header.
`ErrorCode::RateLimited->isRetryable()` is `true`, so the client backs off and
retries rather than surfacing an error to the player.

Auth endpoints return `RATE_LIMITED` **instead of** `INVALID_CREDENTIALS` once
tripped, so the limiter cannot be used as an oracle for which accounts exist.

## What rate limiting is not

It is not a substitute for authorisation, idempotency or locking. A limit slows an
attacker; it does not make an unsafe operation safe. Reviewing a command endpoint
means checking all four.
