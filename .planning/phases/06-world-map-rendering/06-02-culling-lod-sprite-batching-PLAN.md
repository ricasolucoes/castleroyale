---
wave: 2
depends_on: ["06-01"]
files_modified:
  - apps/mobile/src/features/world/
  - apps/mobile/__tests__/
autonomous: true
requirements: [REQ-04, REQ-08]
---

# Plan 06-02: Viewport culling, level of detail and sprite batching

<objective>
Cull to the viewport plus one-screen margin and draw deterministic batched
terrain and marker commands across three zoom-dependent LOD tiers.
</objective>

## Objective

Make the canvas draw only the loaded viewport plus one-screen margin and choose
deterministic batched terrain/marker detail by zoom without rendering every map
entity at every scale.

## Tasks

<task>
<read_first>
- .planning/phases/06-world-map-rendering/06-CONTEXT.md
- .planning/phases/06-world-map-rendering/06-UI-SPEC.md
- apps/mobile/src/features/world/components/MapCanvas.tsx
- apps/mobile/src/features/world/state/cameraStore.ts
- packages/contracts/src/index.ts
- packages/tooling/design-tokens/index.ts
</read_first>
<action>
Create pure helpers in `apps/mobile/src/features/world/rendering/` for converting
camera pixels to integer tile bounds, expanding bounds by exactly one screen,
filtering tiles by bounds, selecting `near`, `mid` and `far` LOD thresholds,
and grouping draw commands by terrain/atlas sprite. Define the thresholds as
named constants in the feature (0.75, 1.5 and 3) and keep all colors, spacing,
radii and animation durations as theme-token inputs. Update `MapCanvas` to feed
these batches to Skia paths/sprites and never map over entities into JSX.
</action>
<acceptance_criteria>
- Culling helper returns only integer tiles inside expanded bounds and never includes a tile outside the one-screen margin.
- LOD helper returns `far` below 1.5, `mid` from 1.5 through below 3, and `near` at 3 or above.
- Renderer groups commands by terrain/atlas key before issuing Skia draw calls.
- `MapCanvas.tsx` contains no `.map(` that returns JSX for tiles, markers or decorations.
- Focused rendering tests pass for edge bounds, empty data and all three LOD tiers.
</acceptance_criteria>
</task>

<task>
<read_first>
- apps/mobile/src/features/world/rendering/
- apps/mobile/src/features/world/components/MapCanvas.tsx
- apps/mobile/__tests__/world-map.test.tsx
</read_first>
<action>
Add a static architecture test that scans the map feature source and rejects
`<Tile`, `<Marker`, `<Terrain` and JSX-returning entity loops inside the canvas
path. Add a performance-oriented unit test that processes a 4,096-tile fixture,
asserts the output command count is bounded by visible batches, and records the
rendering helper duration without relying on wall-clock assertions for native
FPS. Keep the Expo performance monitor measurement documented as a manual QA
check in the phase verification file.
</action>
<acceptance_criteria>
- Architecture test names the forbidden per-entity JSX patterns and passes against the implementation.
- 4,096-tile fixture test proves culling reduces work for a small viewport and preserves deterministic batch order.
- No hardcoded color/spacing/font-size literal is added to the feature source.
</acceptance_criteria>
</task>

## Verification

- `npm test --workspace=@dominion/mobile -- --runInBand world-map`
- `npm run typecheck --workspace=@dominion/mobile`
- `npm run lint --workspace=@dominion/mobile`

## Must-haves

- One-screen-margin culling.
- Deterministic three-tier LOD.
- Batched Skia draw commands and architecture proof.
