# Threat model

The governing assumption: **the client is fully compromised.** The app is
decompiled, traffic is intercepted and rewritten, the device clock is arbitrary,
and requests are replayed at will. Every control below is designed against that
assumption rather than patched in after it is demonstrated.

Phase 37 closes this model: every threat must map to a named test or a written,
signed risk acceptance.

## Threats and controls

| # | Threat | Control | Where |
|---|--------|---------|-------|
| T-01 | **Resource duplication** — spending the same resources twice via concurrent requests | Transaction + `SELECT ... FOR UPDATE` on the owning row; affordability re-checked inside the lock; ledger reconciliation test | Phase 08 |
| T-02 | **Replay attacks** — resending a captured mutating request | `Idempotency-Key` required on all commands; stored response returned instead of re-execution | Phase 03, all command phases |
| T-03 | **Clock manipulation** — changing device time to finish timers | All timing server-side via the `Clock` contract; device clock never an input; architecture test forbids `now()` in domain | Phase 00 (enforced), Phase 09 |
| T-04 | **Speed hacks** — accelerating client to shorten durations | Durations computed and stored server-side; client only renders a countdown | Phase 09, 12, 15 |
| T-05 | **Forged battle results** — submitting a winner | Client sends intent only; server simulates deterministically | Phase 17 |
| T-06 | **Request modification** — editing costs, quantities, targets | Server recomputes every cost from game data; request carries intent, never economics | All command phases |
| T-07 | **IDOR** — addressing another player's entity by id | Every read scoped to the acting player; ULIDs are non-enumerable defence in depth, never the guard | Phase 37 |
| T-08 | **Mass assignment** — injecting extra fields to set protected columns | Explicit DTOs; `Model::preventSilentlyDiscardingAttributes()`; no `$fillable` on economic columns | Phase 00 (enforced) |
| T-09 | **Account takeover** | Rotating refresh with reuse detection; server-side social token verification; device session revocation | Phase 03 |
| T-10 | **Rate-limit bypass** — spoofing `X-Forwarded-For` | `trustProxies` restricted to our own edge; limits keyed on authenticated identity where available | Phase 37 |
| T-11 | **API automation / botting** | Per-endpoint rate limits; behavioural anomaly flags surfaced to the back office rather than silent bans | Phase 37 |
| T-12 | **Market manipulation** — duplicating resources through trade | Escrow; single transaction transfer; conservation-of-resources property test | Phase 27 |
| T-13 | **Chat spam and abuse** | Rate limits, muting, blocking, persisted audit trail, moderation queue | Phase 25, 35 |
| T-14 | **WebSocket abuse** — subscribing to channels the player cannot observe | Every channel private; deny-by-default authorisation callbacks; both allow and deny paths tested | Phase 08 onward |
| T-15 | **Debug tooling in production** | Double gate: flag AND non-production `APP_ENV`; test asserts unreachable when `APP_ENV=production` | Phase 00 (enforced), Phase 37 |
| T-16 | **Secret leakage** | No secrets in the repo; `.env` gitignored; secret scanner in CI; managed secret store in production | Phase 01, 50 |
| T-17 | **Sensitive data in logs** | Structured logging with an explicit deny list; test asserts no password, token, auth header or PII is logged | Phase 37 |
| T-18 | **New-player farming** | Shields, beginner zones, power-difference gates | Phase 19 |
| T-19 | **Reward double-claim** | Idempotency plus a unique claim record; concurrency test | Phase 28 |
| T-20 | **Purchase fraud** — forged receipts | Server-side receipt validation with the store; never trust client confirmation | Phase 50+ |

## Non-goals

- **Preventing decompilation or instrumentation of the client.** Not achievable,
  and not necessary: the client holds no authority worth stealing.
- **Client-side anti-tamper as a security control.** It may be added later as a
  signal for anomaly detection, never as a guard.

## Review obligation

Any phase that adds a mutating endpoint must state, in its plan, which threats it
touches and how it tests them. "Security reviewed" without a named test is not a
completed Definition of Done item.
