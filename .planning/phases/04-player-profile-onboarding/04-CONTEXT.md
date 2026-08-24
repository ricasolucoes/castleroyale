# Phase 04: Player Profile & Onboarding - Context

**Gathered:** 2026-08-24
**Status:** Ready for planning
**Source:** Pre-written during Phase 00. These decisions are locked — do not re-open them
in discussion. If one is genuinely unworkable, write an ADR and record it in
`docs/gsd/DECISIONS.md` rather than quietly designing around it.

<domain>
## Phase Boundary

An authenticated account becomes a named player inside a chosen world, with a private realtime channel.

**Depends on:** Phase 03
**Milestone:** Playable Prototype

This phase is complete when every success criterion in `.planning/ROADMAP.md`
(Phase 04) is demonstrably true. Those criteria are the contract; anything
beyond them is out of scope for this phase.

**Planned work** (from the roadmap — the planner may split further, not wider):

1. Player entity, world membership and name validation
2. World selection and capacity rules
3. Private player channel authorisation and the client bootstrap document
4. Mobile onboarding and first-run flow

</domain>

<decisions>
## Implementation Decisions

### Player vs account
- Account (identity) and Player (game presence) are separate. One account holds at most one player PER WORLD, enforced by unique(account_id, world_id).
- Player carries world_id. Every gameplay entity from here on descends from Player and carries world_id (ADR-012).

### World selection
- The list shows population and status. A full world returns WORLD_FULL, a closed one WORLD_CLOSED.
- World capacity is a structural limit in config, not balance data.

### Naming
- Player names are unique per world, length-bounded, and screened. Rejected content returns CONTENT_REJECTED.
- Screening in this phase is a simple deny list; real moderation is Phase 35.

### Realtime
- Implement the player.{playerId} channel authorisation for real — replace the deny-by-default stub in routes/channels.php (DEBT-001).
- Test both the allow case and the deny case.

### Onboarding
- A new player must land on the city screen with a starting city already created. Coordinate with Phase 07's city entity — this phase creates the player and triggers city creation.
- Client bootstrap document returns player, world, versions and realtime connection details (never REVERB_APP_SECRET).

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
- `docs/adr/012-world-partitioning.md` — World sharding and why world_id is on everything
- `docs/database/conventions.md` — Mandatory columns and unique constraints
- `docs/realtime/architecture.md` — Channel scopes and deny-by-default
- `docs/game-design/progression.md` — What a new player starts with

### The plan itself
- `.planning/ROADMAP.md` §Phase 04 — goal, dependencies and success criteria
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
- City mechanics (Phase 07)
- Tutorial content (Phase 45)
- Profile customisation beyond name

**Belongs to a later phase:**
- Avatar and cosmetics
- Cross-world features — explicitly out of scope

</deferred>
