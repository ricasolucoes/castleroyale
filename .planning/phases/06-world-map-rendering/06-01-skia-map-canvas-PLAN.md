---
wave: 1
depends_on: []
files_modified:
  - apps/mobile/src/features/world/
  - apps/mobile/app/(tabs)/world.tsx
  - apps/mobile/__tests__/
autonomous: true
requirements: [REQ-04, REQ-08]
---

# Plan 06-01: Skia map canvas with pan, zoom and gesture handling

<objective>
Replace the decorative map with a single Skia canvas and UI-thread camera
gestures, keeping selection/camera state client-only and server data separate.
</objective>

## Objective

Replace the current decorative React-view map with a single Skia canvas and a
UI-thread camera model that supports bounded pan, focal-point pinch zoom and a
reset-to-city action.

## Tasks

<task>
<read_first>
- .planning/phases/06-world-map-rendering/06-CONTEXT.md
- .planning/phases/06-world-map-rendering/06-UI-SPEC.md
- docs/mobile/architecture.md
- docs/design-system/tokens.md
- apps/mobile/app/(tabs)/world.tsx
- apps/mobile/app/_layout.tsx
- apps/mobile/babel.config.js
- apps/mobile/package.json
</read_first>
<action>
Create `apps/mobile/src/features/world/state/cameraStore.ts` with Zustand state
only for camera center, zoom, selected coordinate and reset actions. Create a
`MapCanvas` component under `apps/mobile/src/features/world/components/` that
renders exactly one `@shopify/react-native-skia` `Canvas`; use Reanimated shared
values and `useAnimatedStyle`/gesture-handler worklets for pan and pinch, with
zoom clamped to `0.75..4` and camera coordinates clamped to the loaded world
extent. Add a 44pt reset control using the existing `Button`/tokens and expose
an accessibility label from localization. Wire the map screen to render this
component without a React child for each tile or marker.
</action>
<acceptance_criteria>
- `cameraStore.ts` contains no server tile data and exports camera/selection actions.
- `MapCanvas.tsx` imports `Canvas` from `@shopify/react-native-skia` and renders one canvas.
- Pan and pinch handlers use Reanimated worklets/shared values and contain no React state setter in a per-frame callback.
- The world screen no longer renders `world.cities.map`, grid-line loops, or one React `Pressable` per map entity.
- `npm run typecheck --workspace=@dominion/mobile` and the focused map component tests exit 0.
</acceptance_criteria>
</task>

<task>
<read_first>
- apps/mobile/src/features/world/components/MapCanvas.tsx
- apps/mobile/src/features/world/state/cameraStore.ts
- apps/mobile/src/theme/index.ts
- packages/tooling/design-tokens/index.ts
- apps/mobile/__tests__/touch-targets.test.tsx
</read_first>
<action>
Add tests that mount the map shell with mocked Skia/native gesture modules and
assert the canvas is singular, reset has a minimum 44pt target, camera updates
are isolated from React render state, and the camera clamp/reset actions return
the exact configured bounds. Keep mocks in test scope and do not add runtime
fallback rendering that violates the single-canvas architecture.
</action>
<acceptance_criteria>
- A test fails if the map component exposes more than one Skia Canvas.
- A test asserts reset accessibility metadata and `minHeight`/`minWidth` resolve to `MIN_TOUCH_TARGET`.
- Camera reducer/store tests cover clamp at both zoom limits and reset to the player coordinate.
- `npm test --workspace=@dominion/mobile -- --runInBand` passes.
</acceptance_criteria>
</task>

## Verification

- `npm run typecheck --workspace=@dominion/mobile`
- `npm run lint --workspace=@dominion/mobile`
- `npm test --workspace=@dominion/mobile -- --runInBand`

## Must-haves

- One Skia canvas, no component-per-tile/marker.
- Camera work stays on the UI thread.
- Theme and motion values come from design tokens.
