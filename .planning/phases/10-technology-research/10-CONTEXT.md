# Phase 10: Technology & Research - Context

**Gathered:** 2026-08-24
**Status:** Ready for planning
**Source:** Pre-written during Phase 00. These decisions are locked — do not re-open them
in discussion. If one is genuinely unworkable, write an ADR and record it in
`docs/gsd/DECISIONS.md` rather than quietly designing around it.

<domain>
## Phase Boundary

A player researches technologies from an acyclic tree whose effects measurably modify their empire.

**Depends on:** Phase 09
**Milestone:** Playable Prototype

This phase is complete when every success criterion in `.planning/ROADMAP.md`
(Phase 10) is demonstrably true. Those criteria are the contract; anything
beyond them is out of scope for this phase.

**Planned work** (from the roadmap — the planner may split further, not wider):

1. Technology schema, effects model and game data import
2. Dependency graph validation and the cycle detector
3. Research queue, timers and effect application
4. Mobile technology tree screen and detail view

</domain>

<decisions>
## Implementation Decisions

### The validator is the deliverable
- Build the game data validator here (packages/game-data/src/validate.ts) and wire it into CI.
- It must reject: dependency cycles, negative costs, dangling references, duplicate ids, missing translation keys, and unlock requirements that can never be satisfied.
- Error messages must name the offending id. 'Invalid dataset' is not an acceptable failure message.

### Tree model
- Technology dependencies form a DAG. Cycle detection is a real graph algorithm, not a depth limit.
- One research at a time per player; a second returns RESEARCH_IN_PROGRESS.
- Locked returns TECHNOLOGY_LOCKED, maxed returns TECHNOLOGY_MAX_LEVEL.

### Effects
- Effects are data: a typed effect descriptor (target, operation, permille) applied by a pure resolver.
- Percentage effects use integer permille (ADR-010). A completed technology's effect must be observable in a recomputed value — test it, do not just assert the row exists.

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
- `docs/game-design/technology.md` — Research tree and effects
- `docs/mobile/architecture.md` — App structure and the state boundary
- `docs/design-system/tokens.md` — Design tokens — no hardcoded values
- `docs/security/threat-model.md` — Mapped threats and their controls
- `docs/security/anti-cheat.md` — Prevented vs detected, economy invariants
- `docs/adr/013-data-driven-balancing.md` — No balance number in code
- `docs/adr/015-content-versioning.md` — Version bumps and replay safety
- `docs/database/conventions.md` — Identifiers, world_id, money, indexes, locking
- `docs/adr/014-observability.md` — Correlation, structured logs, domain metrics
- `docs/api/idempotency.md` — Idempotency-Key contract and testing

### The plan itself
- `.planning/ROADMAP.md` §Phase 10 — goal, dependencies and success criteria
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
- Alliance technology (Phase 22)
- Balance tuning (Phase 46)

**Belongs to a later phase:**
- Technology respec

</deferred>
