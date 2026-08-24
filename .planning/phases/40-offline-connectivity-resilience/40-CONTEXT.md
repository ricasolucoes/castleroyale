# Phase 40: Offline & Connectivity Resilience - Context

**Gathered:** 2026-08-24
**Status:** Ready for planning
**Source:** Pre-written during Phase 00. These decisions are locked — do not re-open them
in discussion. If one is genuinely unworkable, write an ADR and record it in
`docs/gsd/DECISIONS.md` rather than quietly designing around it.

<domain>
## Phase Boundary

A player on a bad connection sees honest state and never performs a server-authoritative action that silently fails.

**Depends on:** Phase 38
**Milestone:** Beta

This phase is complete when every success criterion in `.planning/ROADMAP.md`
(Phase 40) is demonstrably true. Those criteria are the contract; anything
beyond them is out of scope for this phase.

**Planned work** (from the roadmap — the planner may split further, not wider):

1. Connectivity state machine and status banner
2. Cached read strategy and staleness display
3. Websocket reconnection, backoff and resubscription
4. Event sequencing, gap detection and resync
5. Conflict resolution favouring server authority

</domain>

<decisions>
## Implementation Decisions

### Honesty about offline
- NO server-authoritative action may be presented as confirmed while offline. Queue it or refuse it explicitly. Optimistic UI for DISPLAY only.
- Four visible states: Offline, Reconnecting, Synchronizing, Connected. Each reachable in a test.

### Event integrity
- Realtime events carry a SEQUENCE NUMBER. A detected gap triggers an HTTP resync rather than silent divergence.
- Websocket reconnection uses exponential backoff WITH JITTER — without jitter, every client reconnects simultaneously after an outage and takes the server down again.
- On conflict, server state overwrites client state. Always. Test it.

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
- `docs/backend/jobs-and-queues.md` — Tier selection and job idempotency
- `docs/backend/schedulers.md` — Why reconcilers exist and how they race jobs
- `docs/realtime/architecture.md` — Channel scopes, deny-by-default
- `docs/realtime/events.md` — Event catalogue and sequencing
- `docs/mobile/architecture.md` — App structure and the state boundary
- `docs/design-system/tokens.md` — Design tokens — no hardcoded values
- `docs/security/threat-model.md` — Mapped threats and their controls
- `docs/security/anti-cheat.md` — Prevented vs detected, economy invariants

### The plan itself
- `.planning/ROADMAP.md` §Phase 40 — goal, dependencies and success criteria
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
- Full offline play — impossible by construction (ADR-006)

**Belongs to a later phase:**
- Background sync

</deferred>
