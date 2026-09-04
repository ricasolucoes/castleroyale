---
gsd_state_version: 1.0
milestone: v0.1
milestone_name: Foundation to Launch
status: unknown
stopped_at: Completed 06-04-markers-selection-detail-PLAN.md
last_updated: "2026-09-03T13:29:14.653Z"
progress:
  total_phases: 69
  completed_phases: 7
  total_plans: 31
  completed_plans: 32
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-08-25)

**Core value:** The server owns the truth — a player's empire is exactly what the server says it is, always.
**Current focus:** Phase 06 — world-map-rendering

## Current Position

Phase: 06 (world-map-rendering) — EXECUTING
Plan: 1 of 5

## Performance Metrics

**Velocity:**

- Total plans completed: 5
- Average duration: not yet measured
- Total execution time: not yet measured

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 00. Repository Bootstrap | 4/4 | - | - |
| 01. Engineering Foundation | 4/4 | ~2h | ~30min |

**Recent Trend:**

- Last 5 plans: Phase 01 (4 plans) + Phase 00
- Trend: Stable

*Updated after each plan completion*
| Phase 01-engineering-foundation P01 | 4 min | 3 tasks | 4 files |
| Phase 02 P01-design-tokens | 15 min | 2 tasks | 3 files |
| Phase 02-design-system-mobile-shell P02-02-core-components | 4 min | 3 tasks | 8 files |
| Phase 02-design-system-mobile-shell P02-03-advanced-components | 4 min | 3 tasks | 6 files |
| Phase 02-design-system-mobile-shell P02-04-navigation-shell | 5 min | 3 tasks | 8 files |
| Phase 02-design-system-mobile-shell P02-06-touch-target-tests | 2 min | 1 tasks | 1 files |
| Phase 02-design-system-mobile-shell P05-localization-integration | 4 min | 4 tasks | 10 files |
| Phase 02.1 P01 | 5 min | 2 tasks | 13 files |
| Phase 02.1 P02 | 8 min | 2 tasks | 16 files |
| Phase 06 P01 | 15 min | 2 tasks | 4 files |
| Phase 06 P02 | 3 min | 2 tasks | 7 files |
| Phase 06-world-map-rendering P06-04 | 30 min | 2 tasks | 4 files |

## Accumulated Context

### Roadmap Evolution

- Phase 02.1 inserted after Phase 02: Institutional Site and Public Backend Surface (URGENT)

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting current work:

- [Phase 00]: Modular monolith with a neutral `Game\` namespace — the product name is config, never code (ADR-001, ADR-002)
- [Phase 00]: Integer-only economy with checked arithmetic; floats are forbidden for anything a player owns (ADR-010)
- [Phase 00]: Server-authoritative time via a `Clock` contract; game rules never call `now()` (ADR-006)
- [Phase 00]: PHPStan analyses first-party code only — Laravel's stock config and Pest's fluent API are excluded (DEBT-002)
- [Phase 00]: GSD `research` disabled — every phase ships with CONTEXT.md and canonical references
- [Phase 01]: Repository will be published as **public** at `ricasolucoes/castleroyale`
  (user decision, 2026-08-24). The publish is gated: gitleaks scan runs autonomously,
  then the executor STOPS and hands back for a live human go-ahead before
  `gh repo create`. Plan files are explicitly not authorization for this.

- [Phase 01]: Plan 01-03 replaces the locked `updateOrCreate` seeder mechanism with
  `firstOrNew` + `forceFill` — `preventSilentlyDiscardingAttributes()` throws on the
  non-fillable `is_staff`. To be recorded in `docs/gsd/DECISIONS.md` during execution.

- [Phase 02]: Removed @shopify/restyle to adhere strictly to local @castleroyale/tooling/design-tokens with minimal runtime overhead
- [Phase 02]: Mocked `@gorhom/bottom-sheet` instead of `react-native-reanimated` because the failure originates deep in the reanimated/worklets setup, and for the purpose of the touch target test, we only need to verify that our wrapper correctly passes props down and mounts.
- [Phase 02.1]: Use query-string locale selection with an allow-list and restore the previous application locale after every request.
- [Phase 02.1]: Keep institutional copy in versioned Laravel locale catalogues and render every page through one shared Blade layout.
- [Phase 02.1]: Use a checked-in CSS artifact adjacent to the TypeScript token source so Laravel Vite can consume the same semantic vocabulary without changing the mobile contract.
- [Phase 02.1]: Build canonical and hreflang URLs from configured app.url and explicit locale query parameters.
- [Phase 03]: Use Sanctum token families with rotating refresh secrets; a replay revokes the family before returning TOKEN_EXPIRED.
- [Phase 03]: Verify Apple and Google identity tokens server-side against configured issuer, audience and JWKS; persist only the verified subject.
- [Phase 06]: Used Zustand to store only selected coordinates; derived the selected tile directly from TanStack Query's viewport cache.
- [Phase 06]: Added 44pt circular hit testing inside the Skia canvas onTouchEnd to reliably intercept taps near compact markers.
- [Phase 06]: Delegated interaction resolution logic to Skia's tap handler, skipping separate React Native pressables for map entities.

### Pending Todos

None yet.

### Blockers/Concerns

- [Phase 00 → resolved in Phase 01] Host PHP still lacks `pdo_pgsql`; Docker is now the canonical
  environment and CI runs migrations/seeds/PostGIS suite against `postgis/postgis:16-3.4`
  (evidence in `.planning/codebase/CONCERNS.md` § Environment). Never trust a host-only green suite.

- [Phase 01 → done] `ricasolucoes/castleroyale` is PUBLIC with `origin` configured; the publish
  gate is closed. Auto-mode denies pushing new branches / opening PRs — plan negative CI checks as
  human checkpoints, not autonomous steps.

- [Housekeeping] Untracked `roadmap.json` (stray `roadmap analyze` dump) and the tracked SQLite file
  `apps/api/castleroyale` (modified by runs) sit in the working tree; both should probably be removed /
  gitignored — left untouched pending the user's call.

- [Roadmap] Phases 55–67 (Google Play Sidekick, 13 phases) were appended to ROADMAP.md outside the
  autonomous session and committed as `e04193d`; they depend on Phase 54 and will be picked up by
  the autonomous loop after Phase 54 unless removed or moved to their own milestone.

## Session Continuity

Last session: 2026-08-31T03:46:00.000Z
Stopped at: Completed 06-04-markers-selection-detail-PLAN.md
Resume with: `/gsd:autonomous --from 4`
Resume file: None

**Phase 01 evidence:** `01-VERIFICATION.md` (passed 5/5), four SUMMARY.md files, CI run
https://github.com/ricasolucoes/castleroyale/actions/runs/32802315288 (green, 4 jobs).
