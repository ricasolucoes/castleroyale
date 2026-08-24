# Phase 03: Identity & Authentication - Context

**Gathered:** 2026-08-24
**Status:** Ready for planning
**Source:** Pre-written during Phase 00. These decisions are locked — do not re-open them
in discussion. If one is genuinely unworkable, write an ADR and record it in
`docs/gsd/DECISIONS.md` rather than quietly designing around it.

<domain>
## Phase Boundary

A player can create an account, sign in on a device, stay signed in securely, and revoke other sessions.

**Depends on:** Phase 01
**Milestone:** Playable Prototype

This phase is complete when every success criterion in `.planning/ROADMAP.md`
(Phase 03) is demonstrably true. Those criteria are the contract; anything
beyond them is out of scope for this phase.

**Planned work** (from the roadmap — the planner may split further, not wider):

1. Accounts, credentials and the token model with rotation
2. Device sessions, revocation and rate limiting
3. Apple and Google sign-in plus guest upgrade
4. Mobile auth flow, secure storage and session restoration

</domain>

<decisions>
## Implementation Decisions

### Token model
- Sanctum personal access tokens. Access token short-lived (60 min), refresh token long-lived (30 days) and ROTATING.
- Reuse of an already-rotated refresh token revokes the whole session family and returns TOKEN_EXPIRED. Do not issue a fresh pair.
- Do not use stateless JWT — revocation is the requirement (ADR-011).

### Guest accounts
- Guest start requires zero user input and returns a token pair immediately.
- Upgrade to email/password or social MUST preserve the same account id and all progress, in one transaction. A partial upgrade losing an empire is the worst bug in this area — test it explicitly.

### Social sign-in
- Apple and Google identity tokens are verified SERVER-SIDE against the provider (signature, audience, issuer, expiry). Never trust the client's claim.
- Provider credentials come from env and are absent by default; the feature degrades cleanly when unconfigured.

### Device sessions
- Record device_id, device_name, platform, ip, created_at, last_seen_at, revoked_at.
- A revoked session returns DEVICE_SESSION_REVOKED and its websocket is disconnected.
- Above AUTH_MAX_DEVICE_SESSIONS, evict least-recently-used.

### Client storage
- Credentials go ONLY in SecureStore/Keychain. Never MMKV, AsyncStorage, Zustand or a log. Assert with a test.
- Refresh must be single-flight on the client — concurrent 401s must not each trigger a rotation.

### Rate limiting
- Auth endpoints limited separately and tighter (RATE_LIMIT_AUTH_PER_MINUTE=10).
- Once tripped return RATE_LIMITED, not INVALID_CREDENTIALS, so the limiter is not an account-existence oracle.
- Key on IP AND submitted identifier.

### OpenAPI starts here
- This is the first phase with real endpoints, so packages/contracts/openapi.yaml is authored now: the envelope, the ErrorCode enum, and the auth paths.
- Generate TS types via npm run contracts:generate and commit them. CI checks the diff.

### Non-negotiables (apply to every phase)

These are enforced by tests. Breaking one fails the build, so do not work around them.

- **The server owns the truth.** The client sends intent; the server computes the
  outcome. Never accept a cost, duration, result or quantity from the client.
- **Time comes from the injected `Clock`.** Never `now()`, never a client timestamp.
- **Money is integer.** Use `ResourceAmount` / `ResourceBundle`. No float, ever.
- **Every gameplay query filters `world_id`.** Omitting it is a cross-world leak.
- **Spending resources means:** transaction → `lockForUpdate` → recompute cost
  server-side → re-check affordability *inside* the lock → mutate + write ledger.
- **Mutating commands accept `Idempotency-Key`** and are tested for double-submit.
- **Queued jobs that grant value are idempotent**, guarded on `completed_at IS NULL`.
- **Balance numbers live in `packages/game-data/`**, never in PHP.
- **New errors are added to the `ErrorCode` enum and to `openapi.yaml`** — never
  invented inline.
- **Broadcast channels deny by default** and both allow and deny paths are tested.

### Claude's Discretion
- File and class layout within the module, as long as the layering rule holds
  and layers are not created ceremonially (ADR-001).
- Test structure and naming, as long as the obligations in
  `.planning/codebase/TESTING.md` are covered.
- How work is split across plans.

</decisions>

<specifics>
## Specific Ideas

No additional product references beyond the success criteria and the decisions
above. Follow the documented design direction; do not imitate any existing game.

</specifics>

<canonical_refs>
## Canonical References

**Read these before planning or implementing.**

### Always
- `.planning/codebase/ARCHITECTURE.md` — The non-negotiables and the layering rule
- `.planning/codebase/CONVENTIONS.md` — PHP/TS style, naming, commits, versioning
- `.planning/codebase/TESTING.md` — What every phase must test and how to run the gates
- `.planning/codebase/CONCERNS.md` — Known debt and traps that have already cost time

### This phase
- `docs/api/authentication.md` — Flows, token lifetimes, rotation and reuse detection
- `docs/adr/011-authentication-strategy.md` — Why rotation, why not JWT
- `docs/adr/017-openapi-contract.md` — Spec-first workflow and the generated types
- `docs/api/api-guidelines.md` — The envelope and error-code discipline
- `docs/security/threat-model.md` — T-02 replay, T-09 takeover, T-10 limit bypass
- `docs/security/rate-limits.md` — Tiers and keying

### The plan itself
- `.planning/ROADMAP.md` §Phase 03 — goal, dependencies and success criteria
- `.planning/PROJECT.md` — requirements, constraints and key decisions
- `docs/gsd/EXECUTION_RULES.md` — how to execute a phase and when to stop

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable assets
- `Game\Shared\Domain\Time\Clock` — inject for any time. `FrozenClock` in tests.
- `Game\Shared\Domain\Economy\ResourceAmount` / `ResourceBundle` — all economy maths.
- `Game\Shared\Application\Error\ErrorCode` / `GameException` — player-safe failures.
- `Game\Shared\Interface\Http\ApiResponse` — the only response envelope.
- `tests/Architecture/ArchitectureTest.php` — extend when this phase adds a boundary.

### Established patterns
- Modules live in `apps/api/modules/<Module>/` under the `Game\` namespace.
- Timed work = delayed job + idempotent completion + scheduled reconciler.
- Cross-module communication is domain events, never direct model access.

</code_context>

<deferred>
## Deferred Ideas

**Explicitly out of scope for this phase:**
- Player entity and world membership (Phase 04)
- Two-factor auth
- Password reset email flow — stub it, wire it in Phase 33 when mail exists

**Belongs to a later phase:**
- Push token registration — Phase 33
- Ban enforcement — Phase 35
- Account deletion and export — Phase 52

</deferred>
