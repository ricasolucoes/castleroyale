# Phase 11: Unit System - Context

**Gathered:** 2026-08-24
**Status:** Ready for planning
**Source:** Pre-written during Phase 00. These decisions are locked — do not re-open them
in discussion. If one is genuinely unworkable, write an ADR and record it in
`docs/gsd/DECISIONS.md` rather than quietly designing around it.

<domain>
## Phase Boundary

A complete, data-driven unit roster with stats and a counter system richer than rock-paper-scissors.

**Depends on:** Phase 10
**Milestone:** Playable Prototype

This phase is complete when every success criterion in `.planning/ROADMAP.md`
(Phase 11) is demonstrably true. Those criteria are the contract; anything
beyond them is out of scope for this phase.

**Planned work** (from the roadmap — the planner may split further, not wider):

1. Unit schema, stats and game data import
2. Counter matrix and modifier resolution
3. Unit power calculation
4. Mobile unit catalogue and detail cards

</domain>

<decisions>
## Implementation Decisions

### Counters must not be rock-paper-scissors
- The counter matrix is DATA: a table of (attacker_type, defender_type) -> permille modifier. Never a hardcoded if/match in PHP — architecture test enforces it.
- Base direction (infantry > cavalry > archers > infantry, siege > fortifications) is a starting point, not the whole system. Terrain, formation, hero and technology all modify the result (Phase 17).
- Keep the modifier resolution pure and unit-tested so Phase 17 can compose it.

### Stats
- Every unit carries attack, defence, health, speed, range, capacity, training cost, training time, upkeep, counter bonuses and siege damage.
- Unit power contribution is a pure function with no database access — Phase 14 and Phase 30 both depend on it being cheap and testable.

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
- `docs/game-design/units.md` — Unit roster, stats and counters
- `docs/mobile/architecture.md` — App structure and the state boundary
- `docs/design-system/tokens.md` — Design tokens — no hardcoded values
- `docs/adr/013-data-driven-balancing.md` — No balance number in code
- `docs/adr/015-content-versioning.md` — Version bumps and replay safety
- `docs/database/conventions.md` — Identifiers, world_id, money, indexes, locking

### The plan itself
- `.planning/ROADMAP.md` §Phase 11 — goal, dependencies and success criteria
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
- Training (Phase 12)
- Combat resolution (Phase 17)
- Balance tuning (Phase 47)

**Belongs to a later phase:**
- Unit tiers and upgrades within a type

</deferred>
