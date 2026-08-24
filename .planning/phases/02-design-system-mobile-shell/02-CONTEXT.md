# Phase 02: Design System & Mobile Shell - Context

**Gathered:** 2026-08-24
**Status:** Ready for planning
**Source:** Pre-written during Phase 00. These decisions are locked — do not re-open them
in discussion. If one is genuinely unworkable, write an ADR and record it in
`docs/gsd/DECISIONS.md` rather than quietly designing around it.

<domain>
## Phase Boundary

A navigable, themed app shell with a documented component library the rest of the client is built from.

**Depends on:** Phase 00
**Milestone:** Foundation

This phase is complete when every success criterion in `.planning/ROADMAP.md`
(Phase 02) is demonstrably true. Those criteria are the contract; anything
beyond them is out of scope for this phase.

**Planned work** (from the roadmap — the planner may split further, not wider):

1. Design tokens and theme provider
2. Core primitive components and the gallery screen
3. Expo Router navigation shell with the five primary tabs
4. Typography, iconography and haptic feedback primitives

</domain>

<decisions>
## Implementation Decisions

### Design tokens are the single source
- Tokens live in packages/tooling/design-tokens/index.ts and are imported by the app. No screen hardcodes a colour, spacing, radius or font size.
- Palette direction: parchment, stone, bronze (#B4762E), gold, steel, deep blue (#2B4B7A), military red (#A4212B) on a dark ground (#0E1116). Original identity — do not imitate any existing game.
- Both light and dark themes are defined from the same token names; components never branch on theme.

### Navigation
- Expo Router with typedRoutes. Five primary tabs: City, Map, Army, Heroes, Alliance.
- Secondary destinations (research, inventory, quests, events, ranking, market, reports, mail, profile, settings) reach through a contextual menu, not more tabs.
- One-handed use is the constraint: primary actions sit in the lower third of the screen.

### Component library
- Build only what later phases need: Button, Card, Panel, BottomSheet, Tabs, ProgressBar, ResourceCounter, Badge, RarityIndicator, Timer, Skeleton.
- Prefer bottom sheets over modals (project UX rule). Modals are for destructive confirmation only.
- Every interactive element is at least 44x44pt. A test sweeps for violations.

### State discipline
- TanStack Query for all server state. Zustand only for ephemeral UI state (selection, drafts, sheet open/closed).
- Set this up correctly now — later phases inherit the pattern.

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
- `docs/design-system/tokens.md` — Token names and the palette
- `docs/mobile/architecture.md` — App structure and the state boundary
- `docs/mobile/navigation.md` — Tab structure and one-handed rules
- `docs/ui/screens.md` — Screen inventory this shell must host
- `docs/adr/003-react-native-mobile.md` — Why Skia, why not WebView, the Metro traps

### The plan itself
- `.planning/ROADMAP.md` §Phase 02 — goal, dependencies and success criteria
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
- Any API integration (no endpoints exist yet beyond health)
- The map canvas (Phase 06)
- Localisation wiring (Phase 42) — but write new strings through a catalogue-shaped helper from the start

**Belongs to a later phase:**
- Audio and haptics — Phase 43
- Full accessibility sweep — Phase 41
- Visual polish and particles — Phase 44

</deferred>
