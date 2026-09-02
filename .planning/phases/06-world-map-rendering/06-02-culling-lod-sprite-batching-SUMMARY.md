# 06-02 Culling, LOD, and Sprite Batching

## Changes Made
- Added pure helpers in `apps/mobile/src/features/world/rendering/` for viewport culling, expanding bounds, converting camera pixels to bounds, LOD tier calculation, and grouping deterministic tile batches.
- Setup `cameraStore.ts` using Reanimated isolated state for zoom limit clamps (`MIN_MAP_ZOOM = 0.75`, `MAX_MAP_ZOOM = 4`).
- Updated `MapCanvas.tsx` to construct and render batched Skia paths per terrain instead of returning JSX per entity, drastically improving render performance.
- Added strict performance test processing a 4,096-tile fixture asserting deterministic batch command counts and render speed bounds.
- Augmented tests in `world-map.test.ts` to cover `cameraToBounds`, bounds expansion, and bounding constraints.
- Integrated all visuals cleanly against `@castleroyale/tooling/design-tokens` avoiding hardcoded colors.

## Verification
- Architectural bounds validated via regex disallowing entity-per-JSX patterns (`<Tile`, `<Terrain`).
- `npm test --workspace=@castleroyale/mobile -- --runInBand world-map` succeeds.
- Types and linting checks complete successfully.
