# Phase 06 Verification

status: gaps_found

## Must-Haves Verification

### 06-01
- **One Skia canvas, no component-per-tile/marker**: ❌ Failed. `MapCanvas.tsx` uses `.map` on `cities` to return `<Circle>` JSX elements instead of issuing Skia draw commands via the batch path.
- **Camera work stays on the UI thread**: ✅ Passed. Implemented with `react-native-reanimated` shared values and `react-native-gesture-handler`.
- **Theme and motion values come from design tokens**: ✅ Passed.

### 06-02
- **One-screen-margin culling**: ✅ Passed. Handled appropriately by `expandBounds` and `normalizeBounds`.
- **Deterministic three-tier LOD**: ❌ Failed. `lodForZoom` is defined in `lod.ts`, but it is never utilized in `MapCanvas.tsx`. Markers (cities) and terrain are rendered without regard to LOD thresholds at different zooms.
- **Batched Skia draw commands and architecture proof**: ❌ Failed. Terrain is batched with `Path.Make()`, but `cities` are mapped directly in JSX. The architecture proof in `world-map.test.ts` only asserts `not.toContain('terrainTiles.map')` and misses restricting `cities.map`.

### 06-03
- **TanStack Query owns server map data**: ✅ Passed.
- **MMKV is only a cache, never authoritative**: ✅ Passed.
- **Revisiting a cached region does not issue a network request while fresh**: ✅ Passed (globally configured via `staleTime: 30_000` in `_layout.tsx`).

### 06-04
- **True server-side tile contents in the sheet**: ✅ Passed.
- **Selection/camera only in Zustand; server state in Query**: ✅ Passed.
- **Localized and accessible map states**: ✅ Passed.

## Requirements Cross-Reference
*Note: The requirements were checked against `.planning/PROJECT.md` since `REQUIREMENTS.md` does not exist in the repository.*

All requirement IDs specified in the PLAN frontmatters are accounted for in `PROJECT.md`:
- **REQ-04**: Persistent shared world map with viewport-scoped loading and delta updates.
- **REQ-08**: Premium-feeling mobile UI at 60 FPS, one-handed, offline-aware.
