# Phase 09: Buildings & Construction - Context

**Gathered:** 2026-08-24
**Status:** Ready for planning
**Source:** Pre-written during Phase 00. These decisions are locked — do not re-open them
in discussion. If one is genuinely unworkable, write an ADR and record it in
`docs/gsd/DECISIONS.md` rather than quietly designing around it.

<domain>
## Phase Boundary

A player queues building upgrades that cost resources, take server-controlled time, and complete reliably even if a worker dies.

**Depends on:** Phase 08, Phase 06
**Milestone:** Playable Prototype

This phase is complete when every success criterion in `.planning/ROADMAP.md`
(Phase 09) is demonstrably true. Those criteria are the contract; anything
beyond them is out of scope for this phase.

**Planned work** (from the roadmap — the planner may split further, not wider):

1. Building catalogue import from game data
2. Construction queue, cost debit and timer persistence
3. Completion jobs, idempotency and the overdue reconciler
4. Requirement and unlock evaluation
5. Mobile building detail, upgrade flow and construction queue UI

</domain>

<decisions>
## Implementation Decisions

### Game data starts here
- This is the first phase importing packages/game-data. Create the buildings dataset, the JSON schema and the import command (php artisan game:import-data).
- No cost, duration, effect or capacity appears in PHP. An architecture test enforces it.
- Reference data uses a stable string `code` as its natural key so re-import is idempotent.

### Timers
- Persist started_at, finishes_at, completed_at (null until done). All UTC, all from the injected Clock.
- Dispatch a delayed job on the `gameplay` queue. The job is idempotent, guarded on completed_at IS NULL — never on finishes_at.
- Register a scheduled reconciler that completes overdue rows. It races the job deliberately; that is why the job is idempotent.
- Test: kill the worker mid-timer, run the reconciler, assert exactly one completion.

### Queue and limits
- Build queue slots are bounded by config game.limits.max_build_queue_slots — a structural limit, not balance. Exceeding returns BUILD_QUEUE_FULL.
- Max level returns BUILDING_MAX_LEVEL; unmet requirements return BUILDING_REQUIREMENTS_NOT_MET.

### time_scale
- config('game.time_scale') accelerates durations in local only, so developers do not wait four real hours to test a completion screen. It is forced to 1 outside local — verify that.

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
- `docs/adr/010-integer-based-economy.md` — Integer-only arithmetic rules
- `docs/game-design/economy.md` — Faucets, sinks and the economic model
- `docs/backend/jobs-and-queues.md` — Tier selection and job idempotency
- `docs/backend/schedulers.md` — Why reconcilers exist and how they race jobs
- `docs/game-design/buildings.md` — Building catalogue and effects
- `docs/mobile/architecture.md` — App structure and the state boundary
- `docs/design-system/tokens.md` — Design tokens — no hardcoded values
- `docs/adr/013-data-driven-balancing.md` — No balance number in code
- `docs/adr/015-content-versioning.md` — Version bumps and replay safety
- `docs/api/idempotency.md` — Idempotency-Key contract and testing

### The plan itself
- `.planning/ROADMAP.md` §Phase 09 — goal, dependencies and success criteria
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
- Technology effects on build time (Phase 10)
- Building visuals (Phase 44)
- Balance values beyond plausible placeholders (Phase 46)

**Belongs to a later phase:**
- Instant-finish via premium currency — Phase 50+

</deferred>
