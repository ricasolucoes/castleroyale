# Phase 16: PvE World Encounters - Context

**Gathered:** 2026-08-24
**Status:** Ready for planning
**Source:** Pre-written during Phase 00. These decisions are locked — do not re-open them
in discussion. If one is genuinely unworkable, write an ADR and record it in
`docs/gsd/DECISIONS.md` rather than quietly designing around it.

<domain>
## Phase Boundary

The world holds NPC camps and resource nodes that give solo players something to do and a reason to march.

**Depends on:** Phase 15
**Milestone:** Internal Alpha

This phase is complete when every success criterion in `.planning/ROADMAP.md`
(Phase 16) is demonstrably true. Those criteria are the contract; anything
beyond them is out of scope for this phase.

**Planned work** (from the roadmap — the planner may split further, not wider):

1. NPC camp and resource node entities with spawn rules
2. Gathering marches, capacity and node occupation
3. Camp clearing, rewards and respawn scheduling
4. Mobile target detail and gather flow

</domain>

<decisions>
## Implementation Decisions

### World content
- NPC camps and resource nodes spawn from game data at world generation, with a documented respawn schedule after being cleared.
- A gathering march occupies a node EXCLUSIVELY. A second march to an occupied node returns TILE_OCCUPIED.
- Gathered amounts are capped by the army's carrying capacity and credited via the ledger on return — never on arrival.

### Difficulty gating
- Attacking a camp far above the player's level returns INVALID_TARGET rather than allowing a guaranteed wipe. Protecting new players from themselves is cheaper than refunding them.

### Map sync
- Cleared camps and depleted nodes disappear via a DELTA update to subscribed regions, not a full map reload.

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
- `docs/adr/004-postgresql-postgis.md` — PostGIS use and index proof
- `docs/adr/012-world-partitioning.md` — Worlds, regions, world_id discipline
- `docs/game-design/world.md` — World structure and generation
- `docs/backend/jobs-and-queues.md` — Tier selection and job idempotency
- `docs/backend/schedulers.md` — Why reconcilers exist and how they race jobs
- `docs/game-design/units.md` — Unit roster, stats and counters
- `docs/mobile/architecture.md` — App structure and the state boundary
- `docs/design-system/tokens.md` — Design tokens — no hardcoded values
- `docs/adr/013-data-driven-balancing.md` — No balance number in code
- `docs/adr/015-content-versioning.md` — Version bumps and replay safety

### The plan itself
- `.planning/ROADMAP.md` §Phase 16 — goal, dependencies and success criteria
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
- PvP (Phase 19)
- Combat engine internals (Phase 17)

**Belongs to a later phase:**
- World bosses — Phase 31 events

</deferred>
