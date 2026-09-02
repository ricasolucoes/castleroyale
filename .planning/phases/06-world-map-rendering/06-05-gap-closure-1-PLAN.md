---
wave: 6
depends_on: ["06-01", "06-02", "06-04"]
files_modified:
  - apps/mobile/src/features/world/components/MapCanvas.tsx
  - apps/mobile/src/features/world/rendering/commands.ts
  - apps/mobile/__tests__/world-map.test.ts
autonomous: true
gap_closure: true
requirements: [REQ-04, REQ-08]
---

# Plan 06-05: Gap closure — batched marker draw commands and zoom-driven LOD

<objective>
Close the two gaps recorded in VERIFICATION.md: city markers are still emitted as one
JSX `<Circle>` per entity, and the `lodForZoom` tiers exist but are never applied.
Replace every per-entity JSX element with a single recorded Skia picture built from a
pure, deterministic draw-command list whose marker detail depends on the LOD tier, and
tighten the architecture test so the regression cannot come back.
</objective>

## Context

- Gaps (see `.planning/phases/06-world-map-rendering/VERIFICATION.md`):
  1. `MapCanvas.tsx` maps `cities` to `<Circle>` JSX and terrain batches to `<Path>` JSX.
  2. `lodForZoom` (`far` < 1.5, `mid` 1.5–<3, `near` ≥ 3) is defined in `rendering/lod.ts` but unused.
  3. The architecture test only forbids `terrainTiles.map`; it does not forbid `cities.map`.
- Existing pure helpers: `rendering/cull.ts` (`cullTiles`, `expandBounds`), `rendering/batches.ts`
  (`groupTileBatches`, sorted by terrain), `rendering/lod.ts`, `state/cameraStore.ts` (Zustand).
- Stack: `@shopify/react-native-skia` 2.6.2 (`Skia.PictureRecorder`, `<Picture>` and `PaintStyle` are
  available), `react-native-reanimated` 4.5.1 (shared values drive the UI-thread transform).
- Constraints from 06-CONTEXT/06-UI-SPEC: one canvas, every colour/spacing/radius from theme tokens,
  no literal visual values, hit-testing and the reset button stay as they are.
- Tooling: run from `apps/mobile` — `npx jest --runInBand world-map`, `npx tsc --noEmit`,
  `npx eslint src/features/world __tests__/world-map.test.ts --max-warnings=0`. Do not start Metro or Expo.

## Tasks

<task>
<read_first>
- .planning/phases/06-world-map-rendering/VERIFICATION.md
- .planning/phases/06-world-map-rendering/06-CONTEXT.md
- .planning/phases/06-world-map-rendering/06-UI-SPEC.md
- apps/mobile/src/features/world/components/MapCanvas.tsx
- apps/mobile/src/features/world/rendering/lod.ts
- apps/mobile/src/features/world/rendering/batches.ts
- apps/mobile/src/features/world/rendering/cull.ts
- apps/mobile/src/features/world/state/cameraStore.ts
- apps/mobile/__tests__/world-map.test.ts
</read_first>
<action>
1. Create `apps/mobile/src/features/world/rendering/commands.ts` — a pure module (no React and no
   Skia import) exporting:
   - `type MapMarker = { x: number; y: number; is_player_city: boolean }`
   - `type MapDrawCommand =
       | { kind: 'terrain'; terrain: WorldTile['terrain']; rects: { x: number; y: number; width: number; height: number }[] }
       | { kind: 'marker'; marker: 'player' | 'city'; shape: 'dot' | 'disc' | 'ring'; points: { cx: number; cy: number; r: number }[] }`
   - `buildMapDrawCommands(input: { tiles: readonly WorldTile[]; markers: readonly MapMarker[]; bounds: TileBounds; tileSize: number; lod: MapLod }): MapDrawCommand[]`
   Rules (deterministic — identical input must produce deep-equal output):
   - Terrain: one command per batch from `groupTileBatches(cullTiles(tiles, bounds))`, rects in batch
     order, positioned at `(tile.x - bounds.minX) * tileSize`, `(tile.y - bounds.minY) * tileSize`.
   - Markers are culled by `bounds` exactly like tiles; a marker outside bounds never appears at any LOD.
   - Marker centre: `((m.x - bounds.minX + 0.5) * tileSize, (m.y - bounds.minY + 0.5) * tileSize)`.
   - LOD `far`: only the player city is drawn (`marker: 'player'`, `shape: 'dot'`, `r = tileSize / 4`);
     every other city is omitted entirely.
   - LOD `mid`: player as `disc` (`r = tileSize / 3`) and all other cities together in ONE
     `marker: 'city'`, `shape: 'dot'` command (`r = tileSize / 4`).
   - LOD `near`: everything from `mid`, plus one extra `shape: 'ring'` command per marker kind
     (`r = tileSize / 2`) appended after the discs.
   - Output order: terrain commands (already sorted by terrain), then `city` commands, then `player`
     commands, then ring commands. Empty commands (zero points/rects) are not emitted.
2. Rewrite the drawing part of `apps/mobile/src/features/world/components/MapCanvas.tsx`:
   - Remove the `Circle`, `Path` and `Group` imports and every `.map(...)` that returns JSX.
   - Derive the LOD tier on the JS thread from the Reanimated `zoom` shared value with
     `useAnimatedReaction(() => lodForZoom(zoom.value), (next, prev) => { if (next !== prev) runOnJS(setLod)(next); })`
     so React re-renders only when a tier boundary is crossed, never per frame. Keep the shared
     values and the animated transform exactly as they are.
   - `const commands = useMemo(() => buildMapDrawCommands({ tiles, markers: cities, bounds, tileSize, lod }), [...])`.
   - Record ONE picture in a `useMemo`: `const recorder = Skia.PictureRecorder(); const canvas = recorder.beginRecording(Skia.XYWHRect(0, 0, width, height));`
     then for each command: terrain → build one `Skia.Path.Make()` from its rects and `canvas.drawPath(path, fillPaint)`;
     `dot`/`disc` → `canvas.drawCircle(cx, cy, r, fillPaint)` per point with one fill paint per command;
     `ring` → a stroke paint (`paint.setStyle(PaintStyle.Stroke)`, `paint.setStrokeWidth(theme.borderWidth ?? 1)`
     — use an existing token if the theme has one, otherwise `1`) and `canvas.drawCircle` per point;
     finish with `recorder.finishRecordingAsPicture()` and render it with a single `<Picture picture={picture} />`
     as the only child of the existing `<Canvas>`.
   - Colours: terrain through the existing `TERRAIN_COLORS` → theme map; player marker
     `theme.color.accent.gold`; other cities `theme.color.accent.steel`; a ring uses its marker's colour.
     No literal colours or sizes outside theme tokens and `tileSize` ratios.
   - Keep the `onTouchEnd` hit-testing, `selectCoordinate`, `onTilePress` and the reset button unchanged.
3. `lodForZoom` from `rendering/lod.ts` must now be imported and used by `MapCanvas.tsx`.
</action>
<acceptance_criteria>
- `MapCanvas.tsx` contains exactly one `<Canvas` and exactly one `<Picture`, and contains none of:
  `<Circle`, `<Path`, `<Rect`, `cities.map`, `paths.map`, or any `.map(` whose callback returns JSX.
- `MapCanvas.tsx` imports and calls `lodForZoom`; the recorded picture changes with the tier.
- `buildMapDrawCommands` at `far` emits zero `city` commands; at `mid` emits exactly one `city` command
  with one point per non-player city inside bounds; at `near` additionally emits `ring` commands.
- Repeated calls with the same input return deep-equal arrays.
- `npx tsc --noEmit` and `npx eslint src/features/world __tests__/world-map.test.ts --max-warnings=0` pass in `apps/mobile`.
</acceptance_criteria>
</task>

<task>
<read_first>
- apps/mobile/__tests__/world-map.test.ts
- apps/mobile/src/features/world/rendering/commands.ts
</read_first>
<action>
Tighten the architecture proof and add LOD/determinism coverage in `apps/mobile/__tests__/world-map.test.ts`:
- In the existing "keeps map entities inside one batched Skia canvas" test keep every current assertion and add:
  `expect(source).not.toContain('cities.map')`, `expect(source).not.toContain('<Circle')`,
  `expect(source).not.toContain('<Path')`, `expect(source).not.toMatch(/\.map\([^)]*\)\s*=>\s*\(?\s*</)`,
  `expect(source.match(/<Picture/g)).toHaveLength(1)`, `expect(source).toContain('lodForZoom')`.
- Add `describe('map draw commands', ...)` using the existing 3-tile fixture, markers
  `[{ x: 0, y: 0, is_player_city: true }, { x: 1, y: 0, is_player_city: false }, { x: 2, y: 0, is_player_city: false }, { x: 50, y: 50, is_player_city: false }]`,
  bounds `{ minX: 0, maxX: 2, minY: 0, maxY: 0 }`, `tileSize: 24`:
  - `far` → no `city` command; one `player` command with one `dot` point; no point at (50,50) at any LOD.
  - `mid` → exactly one `city` command with two points; one `player` `disc`.
  - `near` → everything from `mid` plus `ring` commands, and every ring index is greater than every non-ring marker index.
  - Determinism: `JSON.stringify` of two calls with the same input is identical, and terrain commands are in
    sorted terrain order (`forest`, `hills`, `plains`).
- Run `cd apps/mobile && npx jest --runInBand world-map` until green.
</action>
<acceptance_criteria>
- `npx jest --runInBand world-map` passes with all new assertions.
- The architecture test would fail on the previous `MapCanvas.tsx` (it contained `cities.map` and `<Circle`).
</acceptance_criteria>
</task>

## Verification

- `cd apps/mobile && npx jest --runInBand world-map` — green.
- `cd apps/mobile && npx tsc --noEmit` — green.
- `cd apps/mobile && npx eslint src/features/world __tests__/world-map.test.ts --max-warnings=0` — green.

## Must Haves

- One Skia canvas and one recorded picture; zero React elements per tile or marker.
- LOD tiers from `lodForZoom` change what is drawn; `far` never draws every city.
- Deterministic, pure, unit-tested draw-command builder in `rendering/commands.ts`.
- Every visual value comes from theme tokens or `tileSize` ratios.
