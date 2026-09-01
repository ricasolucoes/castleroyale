---
wave: 3
depends_on: ["06-02", "06-03"]
files_modified:
  - apps/mobile/app/(tabs)/world.tsx
  - apps/mobile/src/features/world/
  - apps/mobile/src/shared/components/
  - apps/mobile/src/i18n/
  - apps/mobile/__tests__/
  - packages/localization/
autonomous: true
requirements: [REQ-04, REQ-08]
---

# Plan 06-04: Map markers, selection and the target detail sheet

<objective>
Connect canvas hit-testing to server-backed tile selection and a localized
bottom sheet that shows true tile contents with explicit stale/loading/error
states.
</objective>

## Objective

Connect the rendered map to server-backed tile selection, compact LOD markers
and a bottom sheet that shows true tile contents with loading, stale and error
states in every supported locale.

## Tasks

<task>
<read_first>
- .planning/phases/06-world-map-rendering/06-UI-SPEC.md
- apps/mobile/app/(tabs)/world.tsx
- apps/mobile/src/features/world/components/MapCanvas.tsx
- apps/mobile/src/features/world/data/useWorldViewport.ts
- apps/mobile/src/features/world/state/cameraStore.ts
- apps/mobile/src/shared/components/BottomSheet.tsx
- apps/mobile/src/shared/components/Text.tsx
- packages/localization/src/index.ts
</read_first>
<action>
Add a `WorldTileDetailSheet` using the existing `BottomSheet`, `Text`, `Badge`
and `Button` components. On a canvas hit-test, convert screen coordinates to
integer `(x,y)`, update only the selection/camera store, find the corresponding
contract tile from TanStack Query data and present terrain, region id,
coordinate and stale/loading state. Render marker symbols through the canvas
batch path with 44pt hit testing; do not add a React marker component. Add a
reset-to-city action and retry action wired to query invalidation/refetch.
</action>
<acceptance_criteria>
- Detail sheet reads tile data from the viewport query and never builds a tile object from the tap coordinate.
- Selected coordinate is stored in Zustand; tile contents are not stored there.
- Map marker hit targets are at least `MIN_TOUCH_TARGET` and the sheet has an accessible heading/action label.
- Loading, stale/offline, empty and API error states expose localized text and a retry action.
- No user-facing literal string or hardcoded visual token is introduced.
</acceptance_criteria>
</task>

<task>
<read_first>
- apps/mobile/src/features/world/WorldTileDetailSheet.tsx
- apps/mobile/app/(tabs)/world.tsx
- packages/localization/locales/pt-BR.json
- packages/localization/locales/en.json
- packages/localization/locales/es.json
- apps/mobile/__tests__/touch-targets.test.tsx
</read_first>
<action>
Add localization keys `world.reset_to_city`, `world.selected_tile`,
`world.no_tiles`, `world.tile_loading`, `world.tile_stale`, `world.tile_error`
and their retry/reset labels in pt-BR, en and es. Add component tests for tapping
a tile, showing its exact server terrain, refusing an absent tile as a fact,
opening/closing the sheet, localized error/retry and the reset control. Extend
the architecture test to reject direct tile API fetching from the component.
</action>
<acceptance_criteria>
- All new keys exist in pt-BR, en and es catalogues with matching placeholders.
- Tests assert the detail sheet renders server terrain and coordinate values from the generated `WorldViewport` fixture.
- Tests assert absent/failed tiles render an error or empty state, never a guessed terrain.
- Full mobile tests and localization validation pass.
</acceptance_criteria>
</task>

## Verification

- `npm run typecheck --workspace=@castleroyale/mobile`
- `npm run lint --workspace=@castleroyale/mobile`
- `npm test --workspace=@castleroyale/mobile -- --runInBand`
- `npm run validate --workspace=@castleroyale/localization`

## Must-haves

- True server-side tile contents in the sheet.
- Selection/camera only in Zustand; server state in Query.
- Localized and accessible map states.
