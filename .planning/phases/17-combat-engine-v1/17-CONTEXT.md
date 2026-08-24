# Phase 17: Combat Engine V1 - Context

**Gathered:** 2026-08-24
**Status:** Ready for planning
**Source:** Pre-written during Phase 00. These decisions are locked — do not re-open them
in discussion. If one is genuinely unworkable, write an ADR and record it in
`docs/gsd/DECISIONS.md` rather than quietly designing around it.

<domain>
## Phase Boundary

A deterministic battle simulator that produces identical results from identical inputs and stores a replayable, compact record.

**Depends on:** Phase 16, Phase 11
**Milestone:** Internal Alpha

This phase is complete when every success criterion in `.planning/ROADMAP.md`
(Phase 17) is demonstrably true. Those criteria are the contract; anything
beyond them is out of scope for this phase.

**Planned work** (from the roadmap — the planner may split further, not wider):

1. Battle schema, participants and the versioned record
2. Deterministic seeded RNG and the pure simulation core
3. Damage, counters, terrain and morale resolution
4. Casualty, loot and experience application inside one transaction
5. Replay reconstruction and determinism test suite

</domain>

<decisions>
## Implementation Decisions

### Purity is the whole point
- The simulator performs NO database, cache, clock, or global-random access. An architecture test enforces it. This is what makes it replayable, testable and later extractable.
- Every input that affects the outcome MUST be captured in initial_state. An input read from outside — a config value, a global — silently breaks replay. This is the main review checkpoint.
- Integer arithmetic only. No float touches damage or casualties — floats diverge across platforms and break determinism.

### Randomness
- A seeded generator, passed explicitly through the simulation. Never rand() or mt_rand().
- The seed is server-generated and the client never sees it before resolution.

### Replay storage
- Persist initial_state, seed, commands, simulation_version and combat_version. Do NOT persist a frame dump — the timeline is re-derived by re-running the simulator.
- Replaying an old battle uses the version it was fought under (ADR-015). Old simulator versions stay executable; they are not deleted when a new one ships.

### Testing
- The headline test: identical inputs produce BYTE-IDENTICAL event timelines and results across repeated runs.
- Plus: an old battle still reproduces its original result after a balance version bump.
- Plus: two concurrent attacks on the same target resolve in a defined order without deadlock or double-applied losses.

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
- `docs/adr/009-deterministic-battle-simulation.md` — Purity, seeding and replay
- `docs/game-design/combat.md` — Damage model, counters, morale
- `docs/game-design/buildings.md` — Building catalogue and effects
- `docs/mobile/architecture.md` — App structure and the state boundary
- `docs/design-system/tokens.md` — Design tokens — no hardcoded values
- `docs/security/threat-model.md` — Mapped threats and their controls
- `docs/security/anti-cheat.md` — Prevented vs detected, economy invariants
- `docs/adr/013-data-driven-balancing.md` — No balance number in code
- `docs/adr/015-content-versioning.md` — Version bumps and replay safety
- `docs/database/conventions.md` — Identifiers, world_id, money, indexes, locking
- `docs/api/idempotency.md` — Idempotency-Key contract and testing

### The plan itself
- `.planning/ROADMAP.md` §Phase 17 — goal, dependencies and success criteria
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
- Battle UI (Phase 18)
- Real-time tactical control — explicitly out of scope for the whole project
- Balance tuning (Phase 47)

**Belongs to a later phase:**
- Extracting the simulator into its own service — possible later under ADR-001

</deferred>
