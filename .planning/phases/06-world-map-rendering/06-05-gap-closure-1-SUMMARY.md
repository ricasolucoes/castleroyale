---
phase: 06-world-map-rendering
plan: 05
subsystem: ui
tags: [react-native, skia, reanimated, lod, gap-closure]

# Dependency graph
requires:
  - phase: 06-world-map-rendering
    provides: [Skia map canvas, culling/LOD helpers, viewport query data, marker hit-testing]
provides:
  - Pure deterministic draw-command builder (rendering/commands.ts)
  - Single recorded Skia Picture for terrain and markers (no JSX per entity)
  - Zoom-driven LOD tiers wired into MapCanvas via useAnimatedReaction
  - Architecture test that forbids per-entity JSX in MapCanvas
affects: [world-map, performance, phase-15-march-rendering]

# Tech tracking
tech-stack:
  added: []
  patterns: [Skia PictureRecorder for batched draw commands, useAnimatedReaction + runOnJS for tier-boundary re-renders]

key-files:
  created: [apps/mobile/src/features/world/rendering/commands.ts]
  modified: [apps/mobile/src/features/world/components/MapCanvas.tsx, apps/mobile/__tests__/world-map.test.ts, apps/mobile/__tests__/map-canvas.test.tsx]

key-decisions:
  - "Draw commands are built in a pure module with no Skia import so LOD behaviour is unit-tested without a canvas"
  - "LOD tier lives in React state fed by useAnimatedReaction; React re-renders only when the tier changes, never per frame"
  - "far LOD draws only the player city; mid batches all other cities into one dot command; near adds ring commands after the discs"

patterns-established:
  - "One <Canvas>, one <Picture>: any .map returning JSX inside MapCanvas fails the architecture test"

# Metrics
duration: ~45 min (executor cut off by spend limit after task 1; task 2 finished inline by the orchestrator)
completed: 2026-09-02
---

# Phase 06 Plan 05: Gap closure — batched marker draw commands and zoom-driven LOD

**Closed both VERIFICATION.md gaps: city markers are now Skia draw commands inside one recorded picture, and `lodForZoom` drives marker detail across far/mid/near.**

## Performance

- **Duration:** ~45 min
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments

- `rendering/commands.ts`: `buildMapDrawCommands` turns culled tiles, culled markers, bounds, tile size and LOD tier into a deterministic ordered command list (terrain batches → city markers → player marker → rings).
- `MapCanvas.tsx`: records one `Skia.PictureRecorder` picture from the commands and renders a single `<Picture>`; `Circle`, `Path`, `Group` and every `.map` returning JSX are gone. Also fixed a dormant bug where dotted `TERRAIN_COLORS` paths (`border.strong`, `accent.steel`) resolved to `undefined`.
- LOD: `useAnimatedReaction` watches the UI-thread zoom shared value and calls `runOnJS(setLod)` only when the tier changes.
- Tests: architecture proof now forbids `cities.map`, `<Circle`, `<Path` and any JSX-returning `.map`, requires exactly one `<Picture` and a `lodForZoom` import; new `map draw commands` block covers far/mid/near, out-of-bounds culling, ring ordering and determinism.

## Task Commits

1. **Task 1: Batched draw commands + LOD wiring** — `58ad92f` (feat)
2. **Task 2: Architecture proof and LOD coverage** — `28ff4d5` (test)

## Verification

- `apps/mobile`: `npx jest --runInBand` → 8 suites, 35 tests passed; `npx tsc --noEmit` exit 0; `npx eslint src/features/world __tests__/world-map.test.ts __tests__/map-canvas.test.tsx --max-warnings=0` exit 0.

## Deviations from Plan

- The executor subagent was terminated by a spend limit after committing task 1; the orchestrator finished task 2 inline and verified the whole plan.
- Regression gate found the backend suite red (95 failures) for reasons unrelated to this phase: the gamification module committed outside GSD used a bigint FK against ULID `players.id` and placed a `ServiceProvider` inside the framework-free `Game` namespace. Fixed in `fix(gamification)` commit (models → `Infrastructure`, services → `Application`, `foreignUlid('player_id')`). Architecture + PostGIS viewport tests pass, PHPStan clean, Pint clean.

## Self-Check: PASSED

- commands.ts exists and is imported by MapCanvas.tsx
- Commits 58ad92f and 28ff4d5 present
- Test suites green
