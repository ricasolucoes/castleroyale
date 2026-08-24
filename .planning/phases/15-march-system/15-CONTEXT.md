# Phase 15: March System - Context

**Gathered:** 2026-08-24
**Status:** Ready for planning
**Source:** Pre-written during Phase 00. These decisions are locked — do not re-open them
in discussion. If one is genuinely unworkable, write an ADR and record it in
`docs/gsd/DECISIONS.md` rather than quietly designing around it.

<domain>
## Phase Boundary

Armies move across the world over server-computed time, arrive reliably, and can be recalled.

**Depends on:** Phase 14, Phase 06
**Milestone:** Internal Alpha

This phase is complete when every success criterion in `.planning/ROADMAP.md`
(Phase 15) is demonstrably true. Those criteria are the contract; anything
beyond them is out of scope for this phase.

**Planned work** (from the roadmap — the planner may split further, not wider):

1. March entity, types and the state machine
2. Distance and travel time computation
3. Arrival jobs, reconciliation and recall
4. March limits and concurrency guards
5. Mobile march confirmation, live tracking and recall UI

</domain>

<decisions>
## Implementation Decisions

### Travel time
- Duration = distance / slowest unit speed, computed SERVER-SIDE from the world coordinates. A client-supplied duration is ignored.
- Distance uses the metric fixed in Phase 05. Do not introduce a second metric here.

### State machine
- States: scheduled, marching, arrived, engaged, returning, completed, cancelled. Implement as an explicit state machine and test that no other transition is reachable.
- Types: attack, reinforce, gather, occupy, scout, rally, return, transport. Not all are implemented in this phase — model the enum fully, implement what the criteria require.

### Reliability
- Arrival is a delayed job on `gameplay`, idempotent, plus a scheduled reconciler. Same pattern as Phase 09.
- Recall returns the army over the elapsed travel time. Recalling an engaged march returns MARCH_NOT_CANCELLABLE.
- Concurrent march limit from config game.limits.max_concurrent_marches; exceeding returns MARCH_LIMIT_REACHED.

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
- `docs/adr/004-postgresql-postgis.md` — PostGIS use and index proof
- `docs/adr/012-world-partitioning.md` — Worlds, regions, world_id discipline
- `docs/game-design/world.md` — World structure and generation
- `docs/backend/jobs-and-queues.md` — Tier selection and job idempotency
- `docs/backend/schedulers.md` — Why reconcilers exist and how they race jobs
- `docs/game-design/units.md` — Unit roster, stats and counters
- `docs/mobile/architecture.md` — App structure and the state boundary
- `docs/design-system/tokens.md` — Design tokens — no hardcoded values
- `docs/api/idempotency.md` — Idempotency-Key contract and testing

### The plan itself
- `.planning/ROADMAP.md` §Phase 15 — goal, dependencies and success criteria
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
- What happens on arrival at a hostile target (Phase 16/17/19)
- Rally marches (Phase 24)

**Belongs to a later phase:**
- March speed bonuses from technology — data exists from Phase 10, apply here if trivial, otherwise Phase 38

</deferred>
