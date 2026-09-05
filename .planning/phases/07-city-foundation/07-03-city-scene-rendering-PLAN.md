---
phase: 07-city-foundation
plan: 03
type: execute
wave: 3
depends_on: ["07-02"]
files_modified:
  - packages/localization/locales/en/mvp.json
  - packages/localization/locales/pt-BR/mvp.json
  - packages/localization/locales/es/mvp.json
  - apps/mobile/src/features/city/rendering/grid.ts
  - apps/mobile/src/features/city/state/citySelectionStore.ts
  - apps/mobile/src/features/city/components/CitySlot.tsx
  - apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx
  - apps/mobile/src/features/city/components/CityScene.tsx
  - apps/mobile/app/(tabs)/city.tsx
  - apps/mobile/__tests__/city-grid.test.ts
  - apps/mobile/__tests__/city-scene.test.tsx
autonomous: true
requirements: [REQ-08]

must_haves:
  truths:
    - "The city screen renders a contiguous grid of tappable plots, not a scrolling list of cards"
    - "Tapping any plot — empty or occupied — opens a detail sheet describing that plot"
    - "Pulling down on the scene refetches server state and the screen reflects it"
    - "The scene sizes itself from a runtime measurement and every plot keeps a 44pt touch area whatever the slot count"
  artifacts:
    - path: "apps/mobile/src/features/city/rendering/grid.ts"
      provides: "Pure, count-agnostic computeSlotLayout with the 44pt floor"
      exports: ["computeSlotLayout"]
      min_lines: 25
    - path: "apps/mobile/src/features/city/components/CityScene.tsx"
      provides: "Measured frame, grid, RefreshControl"
      contains: "RefreshControl"
    - path: "apps/mobile/src/features/city/components/CitySlot.tsx"
      provides: "One Pressable plot with empty and occupied states"
      contains: "accessibilityRole"
    - path: "apps/mobile/src/features/city/state/citySelectionStore.ts"
      provides: "Zustand store holding only the selected slot id"
      exports: ["useCitySelectionStore"]
    - path: "packages/localization/locales/pt-BR/mvp.json"
      provides: "All nine new keys in pt-BR"
      contains: "slot_empty_hint"
  key_links:
    - from: "apps/mobile/app/(tabs)/city.tsx"
      to: "apps/mobile/src/features/city/components/CityScene.tsx"
      via: "the tab screen renders CityScene instead of a Card list"
      pattern: "CityScene"
    - from: "apps/mobile/src/features/city/components/CityScene.tsx"
      to: "apps/mobile/src/features/city/rendering/grid.ts"
      via: "computeSlotLayout called with the measured frame width"
      pattern: "computeSlotLayout"
    - from: "apps/mobile/src/features/city/components/CitySlot.tsx"
      to: "apps/mobile/src/features/city/state/citySelectionStore.ts"
      via: "onPress -> selectSlot(slot) -> sheet opens"
      pattern: "selectSlot"
---

# Plan 07-03: City scene rendering with tappable plots

<objective>
Replace the scrolling list of `Card` components on the city tab with a spatial,
tappable scene of plots driven entirely by the server's slot roster, with
pull-to-refresh and a detail sheet.

Purpose: ROADMAP Phase 07 success criterion 3 — "the city screen renders the
scene with tappable buildings and reflects server state after a pull-to-refresh".
The current screen is exactly what the phase forbids: a `ScrollView` of one
`<Card>` per building with an upgrade `<Button>` on each.

Output: a pure grid module, a minimal selection store, three components, nine
localization keys across three catalogues, and the rewritten tab screen.
</objective>

## Context

@.planning/phases/07-city-foundation/07-CONTEXT.md
@.planning/phases/07-city-foundation/07-UI-SPEC.md
@.planning/codebase/CONVENTIONS.md
@/Users/sierra/Dev/Jogos/CLAUDE.md

The approved `07-UI-SPEC.md` is binding: its layout algorithm, token usage, slot
states, localization keys and rendering-approach justification are the contract
this plan implements.

**Decisions locked in this plan (autonomous mode — recorded, not re-opened):**

1. **UI-SPEC Flagged Assumption 1 is resolved as: remove the upgrade CTA from the
   client.** 07-CONTEXT.md defers building upgrades to Phase 09, and the scene is
   read/inspect only. The backend `POST /game/city/buildings/{code}/upgrade` route
   and `MvpGameplayTest`'s coverage of it are **not** touched — only the button and
   its `useMutation` leave the screen. The CTA returns properly in 09-05.
2. **The frame is measured by `onLayout` on the scene container itself**, not by
   subtracting a tab-bar constant. The layout engine has already accounted for the
   safe area, the resource strip and the tab bar by the time that view is measured,
   so this is the honest runtime measurement the project's game rules demand and it
   re-fires on rotation and font-scale change for free.
3. **Ground artwork is not in this plan.** The scene ships on `bg.sunken` here and
   07-04 adds `city_ground.png` behind the same measured frame — the documented
   interim step UI-SPEC Flagged Assumption 3 permits. It is explicitly scheduled,
   not silently left as a grey placeholder.

<interfaces>
Everything the executor needs, copied from the codebase — no exploration required.

Contract types (from 07-02, `@castleroyale/contracts`):

```ts
export type CitySlot = { slot: string; status: 'empty' | 'occupied'; building: CityBuilding | null };
export type CityBuilding = {
  slot: string; code: string; name_key: string; category: string;
  level: number; max_level: number; next_level_cost: ResourceBundle; build_time_seconds: number;
};
export type CityData = {
  player: Player; world: World; city: CityIdentity; resources: CityResources;
  slots: CitySlot[]; construction: Construction | null; realtime: RealtimeConfig; server_time: string;
};
export type Construction = {
  id: string; building_code: string; from_level: number; target_level: number;
  started_at: string; finishes_at: string;
};
```

Theme (`apps/mobile/src/theme/index.ts` -> `useTheme(): AppTheme`):

```ts
theme.color.bg.sunken | theme.color.surface.raised | theme.color.border.subtle
theme.color.border.strong | theme.color.accent.gold | theme.color.accent.bronze
theme.color.text.secondary | theme.color.danger
theme.spacing.xs=4 sm=8 md=12 lg=16 xl=24 '2xl'=32 '3xl'=48
theme.radius.sm|md|lg
theme.minTouchTarget = 44
```

In-house components (`apps/mobile/src/shared/components/`):

```ts
Text({ variant?: 'display'|'title'|'heading'|'body'|'label'|'caption'|'numeric', color?, accessibilityRole? })
Badge({ label: string, variant?: 'neutral'|'success'|'warning'|'danger' })
Button({ title: string, variant?: 'primary'|'secondary', onPress, disabled? })
BottomSheet(props of @gorhom/bottom-sheet)   // forwardRef wrapper
Timer({ targetTimestamp: number, onFinish?: () => void })
ResourceCounter({ resource: ResourceKey, amount: number })  // + formatResourceAmount()
```

Reference implementation to mirror for the sheet —
`apps/mobile/src/features/world/components/WorldTileDetailSheet.tsx` uses:

```tsx
<BottomSheet accessibilityViewIsModal enablePanDownToClose index={open ? 0 : -1}
  onClose={onClose} snapPoints={[`${theme.spacing['3xl'] * 4}%`]}>
  <View style={{ gap: theme.spacing.md, padding: theme.spacing.lg }}>
    <Text accessibilityRole="header" variant="heading">{t('world.selected_tile')}</Text>
```

Reference store to mirror — `apps/mobile/src/features/world/state/cameraStore.ts`
stores only `selectedX`/`selectedY`, never tile contents.

Translation access — `apps/mobile/src/i18n/useTranslation.ts` resolves **dotted
paths against nested JSON** and interpolates `{name}` placeholders:
`t('city.slot_category', { category: 'core' })`.

Test mocking pattern — `apps/mobile/__tests__/world-tile-detail-sheet.test.tsx`
mocks `../src/i18n/useTranslation` (echoing the key plus JSON params) and
`@gorhom/bottom-sheet` (a `forwardRef` View with `testID="gorhom-bottom-sheet"`).
Reuse both verbatim.
</interfaces>

## Tasks

<task type="auto" tdd="true">
<name>Task 1: Pure grid math, the selection store, and the nine localization keys</name>
<files>apps/mobile/src/features/city/rendering/grid.ts, apps/mobile/src/features/city/state/citySelectionStore.ts, packages/localization/locales/en/mvp.json, packages/localization/locales/pt-BR/mvp.json, packages/localization/locales/es/mvp.json, apps/mobile/__tests__/city-grid.test.ts</files>
<read_first>
- .planning/phases/07-city-foundation/07-UI-SPEC.md
- apps/mobile/src/features/world/rendering/cull.ts
- apps/mobile/src/features/world/state/cameraStore.ts
- apps/mobile/__tests__/world-map.test.ts
- packages/localization/locales/en/mvp.json
- packages/localization/locales/pt-BR/mvp.json
- packages/localization/locales/es/mvp.json
- packages/localization/src/validate.ts
</read_first>
<behavior>
- `computeSlotLayout(390, 18, 48, 44)` returns `{ columns: 8, tileSize: 48.75, rows: 3 }`.
- `computeSlotLayout(100, 18, 32, 44)` returns `{ columns: 2, tileSize: 50, rows: 9 }` — the 44pt floor forces a column decrement from 3.
- `computeSlotLayout(40, 5, 48, 44)` returns `{ columns: 1, tileSize: 40, rows: 5 }` — never fewer than one column, even when the floor cannot be met.
- `computeSlotLayout(0, 18, 48, 44)` returns `{ columns: 1, tileSize: 0, rows: 18 }` — the pre-measurement frame must not crash or divide by zero.
- `computeSlotLayout(390, 0, 48, 44)` returns `rows: 0`.
- `computeSlotLayout(390, 40, 48, 44)` returns `rows: 5` — the function is count-agnostic; no slot total is hardcoded.
- `useCitySelectionStore` starts with `selectedSlot: null`; `selectSlot('plot_07')` sets it; `clearSelection()` returns it to `null`.
</behavior>
<action>
Create `apps/mobile/src/features/city/rendering/grid.ts` — pure, no React and no
`react-native` import, mirroring how `features/world/rendering/cull.ts` is
structured so it stays unit-testable:

```ts
export type SlotLayout = {
  columns: number;
  tileSize: number;
  rows: number;
};

/**
 * Fit a server-driven number of plots into a runtime-measured frame.
 *
 * The count is never assumed: today a starter city has 18 plots and the
 * building catalogue grows, so the grid is derived, never authored.
 */
export function computeSlotLayout(
  frameWidth: number,
  slotCount: number,
  baseTileUnit: number,
  minTouchTarget: number,
): SlotLayout {
  const width = Math.max(0, frameWidth);
  let columns = Math.max(1, Math.floor(width / baseTileUnit));
  let tileSize = columns > 0 ? width / columns : 0;

  // Visual size may shrink in a dense grid; the touch area never does. Drop a
  // column rather than ship a plot the thumb cannot reliably hit.
  while (tileSize < minTouchTarget && columns > 1) {
    columns -= 1;
    tileSize = width / columns;
  }

  return { columns, tileSize, rows: Math.ceil(Math.max(0, slotCount) / columns) };
}
```

Create `apps/mobile/src/features/city/state/citySelectionStore.ts` holding the
selection identity only — never slot data, which lives in TanStack Query:

```ts
import { create } from 'zustand';

export type CitySelectionState = {
  selectedSlot: string | null;
  selectSlot: (slot: string) => void;
  clearSelection: () => void;
};

export const useCitySelectionStore = create<CitySelectionState>((set) => ({
  selectedSlot: null,
  selectSlot: (selectedSlot) => set({ selectedSlot }),
  clearSelection: () => set({ selectedSlot: null }),
}));
```

Add the nine keys to all three catalogues. `mvp.json` is **nested**, so two go
under the existing `errors` object and seven under the existing `city` object.
Use these exact strings:

`packages/localization/locales/en/mvp.json`
- `errors.CITY_NOT_OWNED`: `"This city does not belong to you."`
- `errors.TILE_OCCUPIED`: `"That tile is already claimed."`
- `city.scene_accessibility`: `"Interactive city scene"`
- `city.selected_slot`: `"Selected plot"`
- `city.slot_empty`: `"This plot is empty"`
- `city.slot_empty_hint`: `"Building options will open in a future update."`
- `city.slot_category`: `"Category: {category}"`
- `city.slot_accessible_empty`: `"Empty plot"`
- `city.slot_accessible_occupied`: `"{building}, level {level}"`

`packages/localization/locales/pt-BR/mvp.json`
- `errors.CITY_NOT_OWNED`: `"Esta cidade não pertence a você."`
- `errors.TILE_OCCUPIED`: `"Esse tile já está ocupado."`
- `city.scene_accessibility`: `"Cena interativa da cidade"`
- `city.selected_slot`: `"Lote selecionado"`
- `city.slot_empty`: `"Este lote está vazio"`
- `city.slot_empty_hint`: `"As opções de construção serão liberadas em uma atualização futura."`
- `city.slot_category`: `"Categoria: {category}"`
- `city.slot_accessible_empty`: `"Lote vazio"`
- `city.slot_accessible_occupied`: `"{building}, nível {level}"`

`packages/localization/locales/es/mvp.json`
- `errors.CITY_NOT_OWNED`: `"Esta ciudad no te pertenece."`
- `errors.TILE_OCCUPIED`: `"Esa casilla ya está ocupada."`
- `city.scene_accessibility`: `"Escena interactiva de la ciudad"`
- `city.selected_slot`: `"Parcela seleccionada"`
- `city.slot_empty`: `"Esta parcela está vacía"`
- `city.slot_empty_hint`: `"Las opciones de construcción se habilitarán en una futura actualización."`
- `city.slot_category`: `"Categoría: {category}"`
- `city.slot_accessible_empty`: `"Parcela vacía"`
- `city.slot_accessible_occupied`: `"{building}, nivel {level}"`

Create `apps/mobile/__tests__/city-grid.test.ts` covering every case in the
`<behavior>` block above plus the store transitions, importing from
`../src/features/city/rendering/grid` and `../src/features/city/state/citySelectionStore`.
</action>
<verify>
  <automated>cd /Users/sierra/Dev/Jogos/CastleRoyale && npm test --workspace=@castleroyale/mobile -- --runInBand city-grid && node --experimental-strip-types packages/localization/src/validate.ts && npm run typecheck</automated>
</verify>
<acceptance_criteria>
- `grep -n 'export function computeSlotLayout' apps/mobile/src/features/city/rendering/grid.ts` matches.
- `grep -c "import.*react-native\|import.*from 'react'" apps/mobile/src/features/city/rendering/grid.ts` outputs `0`.
- `grep -n 'selectedSlot' apps/mobile/src/features/city/state/citySelectionStore.ts` matches, and `grep -n 'building\|slots' apps/mobile/src/features/city/state/citySelectionStore.ts` returns nothing (no server data in the store).
- `grep -c 'slot_empty_hint' packages/localization/locales/en/mvp.json packages/localization/locales/pt-BR/mvp.json packages/localization/locales/es/mvp.json` outputs `1` for each file.
- `grep -c 'CITY_NOT_OWNED' packages/localization/locales/es/mvp.json` outputs `1`.
- `node --experimental-strip-types packages/localization/src/validate.ts` exits 0 (no locale is missing a key and none has an extra one).
- `npm test --workspace=@castleroyale/mobile -- --runInBand city-grid` exits 0 with all seven behaviours covered.
</acceptance_criteria>
<done>
The layout maths is pure and proven count-agnostic with the 44pt floor enforced,
the selection store holds nothing but an id, and all three locales are complete.
</done>
</task>

<task type="auto">
<name>Task 2: The plot tile and the plot detail sheet</name>
<files>apps/mobile/src/features/city/components/CitySlot.tsx, apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx</files>
<read_first>
- .planning/phases/07-city-foundation/07-UI-SPEC.md
- apps/mobile/src/features/world/components/WorldTileDetailSheet.tsx
- apps/mobile/src/shared/components/Badge.tsx
- apps/mobile/src/shared/components/Timer.tsx
- apps/mobile/src/shared/components/BottomSheet.tsx
- apps/mobile/src/theme/index.ts
- apps/mobile/app/(tabs)/_layout.tsx
</read_first>
<action>
Create `apps/mobile/src/features/city/components/CitySlot.tsx`:

```tsx
export type CitySlotTileProps = {
  slot: CitySlot;
  size: number;
  isBuilding: boolean;      // an active construction order targets this plot
  onPress: (slot: string) => void;
};
```

Render exactly one `Pressable` from `react-native`, `width: size, height: size`,
`accessibilityRole="button"`, and:

- `accessibilityLabel` = `t('city.slot_accessible_empty')` when
  `slot.building === null`, otherwise
  `t('city.slot_accessible_occupied', { building: t(slot.building.name_key), level: slot.building.level })`.
- `hitSlop={Math.max(0, (theme.minTouchTarget - size) / 2)}` so the touch area
  never drops below 44pt even when the visual tile does.
- `onPress={() => onPress(slot.slot)}`.

Empty state: `backgroundColor: theme.color.bg.sunken`, `borderWidth: 1`,
`borderStyle: 'dashed'`, `borderColor: theme.color.border.subtle`,
`borderRadius: theme.radius.md`, and one centred
`<MaterialCommunityIcons name="plus-circle-outline" size={theme.spacing.xl} color={theme.color.accent.gold} />`
from `@expo/vector-icons` (already used in `app/(tabs)/_layout.tsx`). This icon is
the **interim** stand-in the UI-SPEC names; 07-04 replaces it with the generated
`slot_empty_icon.png` and it is never rendered alongside that asset.

Occupied state: `backgroundColor: theme.color.surface.raised`, `borderWidth: 1`,
`borderStyle: 'solid'`, `borderColor: theme.color.border.strong`, same radius, and
stacked vertically with `gap: theme.spacing.xs`, `alignItems: 'center'`,
`justifyContent: 'center'`, `padding: theme.spacing.xs`:

1. a category glyph — `<MaterialCommunityIcons>` with
   `name={slot.building.category === 'core' ? 'castle' : 'sprout-outline'}`,
   `size={theme.spacing.xl}`, `color={theme.color.text.secondary}`. Category is
   distinguished by **silhouette only**, never by colour (UI-SPEC Color section);
2. `<Text variant="label" numberOfLines={1}>{t(slot.building.name_key)}</Text>`;
3. `<Badge variant="neutral" label={String(slot.building.level)} />`;
4. when `isBuilding` is true, a `<Timer>` rendered inside the tile so the
   "living scene" reads without opening the sheet.

Use no literal colour, spacing, radius or font size anywhere — every value comes
from `useTheme()`.

Create `apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx`:

```tsx
export type CitySlotDetailSheetProps = {
  slot: CitySlot | null;
  construction: Construction | null;
  serverTime: string;
  open: boolean;
  onClose: () => void;
  onConstructionFinish: () => void;
};
```

Mirror `WorldTileDetailSheet.tsx` exactly: same `BottomSheet` props
(`accessibilityViewIsModal`, `enablePanDownToClose`, `index={open ? 0 : -1}`,
`onClose`, `snapPoints={[`${theme.spacing['3xl'] * 4}%`]}`), same outer
`<View style={{ gap: theme.spacing.md, padding: theme.spacing.lg }}>`, and a
`<Text accessibilityRole="header" variant="heading">{t('city.selected_slot')}</Text>`
header. It needs **no** loading/stale/error branches — the slot is already in the
fetched `CityData`, so there is no per-tap network call.

- Empty branch (`slot?.building == null`): `<Text>{t('city.slot_empty')}</Text>`
  and `<Text color={theme.color.text.secondary}>{t('city.slot_empty_hint')}</Text>`.
- Occupied branch: `<Text variant="label">{t(slot.building.name_key)}</Text>`,
  `<Text color={theme.color.text.secondary}>{t('building.level', { level: slot.building.level })}</Text>`,
  `<Badge variant="neutral" label={t('city.slot_category', { category: slot.building.category })} />`,
  and — only when `construction !== null && construction.building_code === slot.building.code` —
  `<Text color={theme.color.text.secondary}>{t('city.construction_finish')}</Text>`
  plus
  `<Timer targetTimestamp={Date.parse(construction.finishes_at) + (Date.now() - Date.parse(serverTime))} onFinish={onConstructionFinish} />`.
  That skew term is the same correction `app/(tabs)/city.tsx` already applies —
  the device clock is never trusted as the source of the deadline.

**No upgrade button.** Do not import `useMutation` here and do not render a
`Button` for construction. Phase 09 owns that CTA (decision 1 above).
</action>
<verify>
  <automated>cd /Users/sierra/Dev/Jogos/CastleRoyale && npm run typecheck --workspace=@castleroyale/mobile && npm run lint --workspace=@castleroyale/mobile</automated>
</verify>
<acceptance_criteria>
- `grep -c 'Pressable' apps/mobile/src/features/city/components/CitySlot.tsx` is at least 1 and the file contains `accessibilityRole="button"` and `hitSlop`.
- `grep -En '#[0-9a-fA-F]{3,6}|fontSize: [0-9]|padding: [0-9]|margin: [0-9]' apps/mobile/src/features/city/components/CitySlot.tsx apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx` returns nothing.
- `grep -n 'useMutation\|upgrade' apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx` returns nothing.
- `grep -n "theme.spacing\['3xl'\] \* 4" apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx` matches (same snapPoints formula as `WorldTileDetailSheet`).
- `grep -n 'Date.parse(serverTime)' apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx` matches.
- `npm run typecheck --workspace=@castleroyale/mobile` and `npm run lint --workspace=@castleroyale/mobile` exit 0.
</acceptance_criteria>
<done>
A plot renders as a single accessible `Pressable` in both states with a guaranteed
44pt touch area, and its detail sheet reuses the established BottomSheet contract
with no upgrade action.
</done>
</task>

<task type="auto">
<name>Task 3: Assemble the measured scene and replace the card list on the city tab</name>
<files>apps/mobile/src/features/city/components/CityScene.tsx, apps/mobile/app/(tabs)/city.tsx, apps/mobile/__tests__/city-scene.test.tsx</files>
<read_first>
- apps/mobile/app/(tabs)/city.tsx
- apps/mobile/src/features/city/rendering/grid.ts
- apps/mobile/src/features/city/components/CitySlot.tsx
- apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx
- apps/mobile/src/features/world/components/MapCanvas.tsx
- apps/mobile/__tests__/world-tile-detail-sheet.test.tsx
- apps/mobile/__tests__/touch-targets.test.tsx
- /Users/sierra/Dev/Jogos/CLAUDE.md
</read_first>
<action>
Create `apps/mobile/src/features/city/components/CityScene.tsx`:

```tsx
export type CitySceneProps = {
  city: CityData;
  isRefreshing: boolean;
  onRefresh: () => void;
};
```

Structure:

1. A `ScrollView` as the outer container with
   `contentContainerStyle={{ flexGrow: 1, paddingHorizontal: theme.spacing.sm }}`
   and
   `refreshControl={<RefreshControl refreshing={isRefreshing} onRefresh={onRefresh} tintColor={theme.color.accent.bronze} />}`.
   `RefreshControl` on a `flexGrow: 1` content container is what enables the pull
   gesture on a view that does not naturally overflow — success criterion 3
   requires the gesture, and no screen in this codebase has needed it before.
   The 8pt (`spacing.sm`) gutter per side is the only whitespace around the scene:
   it is edge-to-edge, not a card floating in a margin.
2. A thin resource strip above the scene: the city name in
   `<Text variant="display">{t(city.city.name_key)}</Text>`, the coordinates line
   already used today (`t('city.location', { x, y })`), and the five
   `ResourceCounter`s in a `flexDirection: 'row', flexWrap: 'wrap'` row. Keep it
   informational — no `Card` wrapper, no banner.
3. The scene frame:

```tsx
const [frameWidth, setFrameWidth] = useState(0);
const layout = computeSlotLayout(frameWidth, city.slots.length, theme.spacing['3xl'], theme.minTouchTarget);
...
<View
  accessibilityLabel={t('city.scene_accessibility')}
  onLayout={(event) => setFrameWidth(event.nativeEvent.layout.width)}
  style={{
    flex: 1,
    flexDirection: 'row',
    flexWrap: 'wrap',
    backgroundColor: theme.color.bg.sunken,
    borderRadius: theme.radius.lg,
  }}
>
  {layout.tileSize > 0 && city.slots.map((slot) => (
    <CitySlot
      key={slot.slot}
      slot={slot}
      size={layout.tileSize}
      isBuilding={city.construction?.building_code === slot.building?.code && slot.building !== null}
      onPress={selectSlot}
    />
  ))}
</View>
```

   The frame measures itself; nothing subtracts a hardcoded header or tab-bar
   height, and a rotation or font-scale change re-fires `onLayout` and re-derives
   the grid. Slots render in the exact order the server returned them — never
   sorted, filtered or padded client-side.
4. Below the frame, render `<CitySlotDetailSheet>` driven by
   `useCitySelectionStore`: `slot` = `city.slots.find((s) => s.slot === selectedSlot) ?? null`,
   `open` = `selectedSlot !== null`, `onClose` = `clearSelection`,
   `construction` = `city.construction`, `serverTime` = `city.server_time`,
   `onConstructionFinish` = `onRefresh`.

Rewrite `apps/mobile/app/(tabs)/city.tsx` to be a thin screen: keep the existing
`useQuery({ queryKey: ['game', 'city'], queryFn: () => apiRequest<CityData>('/game/city', {}, { authenticated: true }) })`,
keep the pending `ActivityIndicator` (`color={theme.color.accent.bronze}`) and the
error branch (`Text color={theme.color.danger}` with `t(errorKey(error))` plus a
`Button variant="secondary" title={t('common.retry')}`), and render
`<CityScene city={cityQuery.data} isRefreshing={cityQuery.isRefetching} onRefresh={() => void cityQuery.refetch()} />`
on success. Delete the `useMutation` upgrade call, the `useQueryClient` import if
it becomes unused, the `Card`-per-building block, the `formatCost` helper and the
`city.buildings`/`buildings` derivation added in 07-02. Keep `errorKey()`.

Create `apps/mobile/__tests__/city-scene.test.tsx`, reusing the exact
`useTranslation` and `@gorhom/bottom-sheet` mocks from
`world-tile-detail-sheet.test.tsx`. Build an 18-slot fixture with 5 occupied
(`plot_01` palace/core, `plot_02` farm/economy, `plot_03`, `plot_04`, `plot_05`)
and assert:

- rendering the scene and firing
  `fireEvent(sceneFrame, 'layout', { nativeEvent: { layout: { width: 390, height: 600, x: 0, y: 0 } } })`
  produces exactly 18 elements with `accessibilityRole === 'button'` — one per
  server slot, none hardcoded;
- every one of those has a `hitSlop` or a `width` that resolves the touch area to
  at least `MIN_TOUCH_TARGET`;
- pressing the `plot_07` tile sets `useCitySelectionStore.getState().selectedSlot`
  to `'plot_07'` and renders `city.slot_empty` in the sheet;
- pressing the `plot_01` tile renders `building.level {"level":1}` in the sheet
  and renders **no** element whose text is `building.upgrade`;
- an architecture assertion in the style of `world-map.test.ts`: read
  `app/(tabs)/city.tsx` with `readFileSync` and assert its source contains
  `CityScene` and contains none of `city.buildings`, `<Card`, `useMutation`,
  `/upgrade`.
</action>
<verify>
  <automated>cd /Users/sierra/Dev/Jogos/CastleRoyale && npm test --workspace=@castleroyale/mobile -- --runInBand && npm run typecheck && npm run lint</automated>
</verify>
<acceptance_criteria>
- `grep -n 'RefreshControl' apps/mobile/src/features/city/components/CityScene.tsx` matches, with `tintColor={theme.color.accent.bronze}`.
- `grep -n 'onLayout' apps/mobile/src/features/city/components/CityScene.tsx` matches and `grep -n 'computeSlotLayout' apps/mobile/src/features/city/components/CityScene.tsx` matches.
- `grep -En 'height: [0-9]+|TAB_BAR|HEADER_HEIGHT|useBottomTabBarHeight' apps/mobile/src/features/city/components/CityScene.tsx` returns nothing (no hardcoded pixel budget).
- `grep -En 'city\.slots\.(sort|filter|slice)' apps/mobile/src/features/city/components/CityScene.tsx` returns nothing (server order is preserved).
- `grep -n 'CityScene' 'apps/mobile/app/(tabs)/city.tsx'` matches; `grep -En 'useMutation|<Card|city\.buildings|/upgrade' 'apps/mobile/app/(tabs)/city.tsx'` returns nothing.
- `npm test --workspace=@castleroyale/mobile -- --runInBand` exits 0 with the city-scene suite passing.
- `npm run typecheck` and `npm run lint` exit 0 from the repo root.
</acceptance_criteria>
<done>
The city tab renders a runtime-measured, edge-to-edge grid of exactly as many
tappable plots as the server returned, pull-to-refresh refetches and re-renders,
and the card list with its upgrade buttons is gone from the screen while the
backend upgrade endpoint remains untouched.
</done>
</task>

## Verification

```bash
npm run typecheck
npm run lint
npm test
node --experimental-strip-types packages/localization/src/validate.ts
cd apps/api && ./vendor/bin/pest        # regression guard: nothing server-side moved
```

## Success Criteria

- The city tab renders one `Pressable` plot per server slot, in server order,
  with no per-building `Card` and no upgrade `Button`.
- Every plot is `accessibilityRole="button"` with a localized label and an
  effective touch area of at least 44pt at any slot count.
- Pulling down triggers `refetch()` and the re-rendered scene reflects the
  refetched server state.
- Tapping an empty plot shows `city.slot_empty` / `city.slot_empty_hint`; tapping
  an occupied plot shows its name, level and category.
- All three locales validate with zero missing and zero extra keys.
- No literal colour, spacing, radius or font size appears in any new component.

<output>
After completion, create
`.planning/phases/07-city-foundation/07-03-city-scene-rendering-SUMMARY.md`
recording the removal of the upgrade CTA (Phase 09 owns it), the `onLayout`
frame-measurement decision, and that the scene ships on `bg.sunken` pending
07-04's generated ground art.
</output>
