---
phase: 08-resources-economy
plan: 05
type: execute
wave: 2
depends_on: ["08-01"]
files_modified:
  - apps/mobile/src/shared/components/resourceIcons.ts
  - apps/mobile/src/shared/components/ResourceCounter.tsx
  - apps/mobile/src/features/economy/interpolation/interpolateResources.ts
  - apps/mobile/src/features/economy/components/ResourceBar.tsx
  - apps/mobile/src/features/city/api/useCityQuery.ts
  - apps/mobile/app/(tabs)/city.tsx
  - apps/mobile/app/(tabs)/_layout.tsx
  - apps/mobile/app/_layout.tsx
  - apps/mobile/src/features/city/components/CityScene.tsx
  - packages/localization/locales/en/mvp.json
  - packages/localization/locales/pt-BR/mvp.json
  - packages/localization/locales/es/mvp.json
  - apps/mobile/__tests__/resource-interpolation.test.ts
  - apps/mobile/__tests__/resource-bar.test.tsx
  - apps/mobile/__tests__/city-scene.test.tsx
autonomous: true
requirements: [REQ-02]

must_haves:
  truths:
    - "A player sees their five resource levels on every tab, ticking forward once a second between server reads"
    - "The displayed number never exceeds capacity and never keeps climbing once storage is full"
    - "Every server read resets the display to server truth instantly, with no easing"
    - "A full warehouse is signalled by an icon and the word MAX, never by colour alone"
    - "Each resource is identifiable by its own icon, not only by the colour of its numeral"
    - "The city scene renders with exactly one top inset, not two"
  artifacts:
    - path: "apps/mobile/src/features/economy/interpolation/interpolateResources.ts"
      provides: "The pure, clamped projection function — no React, no RN imports"
      exports: ["interpolateResources"]
    - path: "apps/mobile/src/features/economy/components/ResourceBar.tsx"
      provides: "The persistent HUD strip above the tab navigator"
      min_lines: 80
    - path: "apps/mobile/src/shared/components/resourceIcons.ts"
      provides: "The one resource-to-glyph mapping every future screen reuses"
      contains: "barley"
    - path: "apps/mobile/src/features/city/api/useCityQuery.ts"
      provides: "The shared ['game','city'] query with a 30s refetch interval"
      exports: ["useCityQuery"]
  key_links:
    - from: "apps/mobile/app/(tabs)/_layout.tsx"
      to: "ResourceBar"
      via: "sibling rendered above <Tabs> inside a flex:1 View"
      pattern: "<ResourceBar />"
    - from: "apps/mobile/src/features/economy/components/ResourceBar.tsx"
      to: "useCityQuery"
      via: "dataUpdatedAt paired with resources.current/capacity/rate"
      pattern: "dataUpdatedAt"
    - from: "apps/mobile/app/_layout.tsx"
      to: "@tanstack/react-query focusManager"
      via: "AppState listener so a resumed app refetches instead of free-running"
      pattern: "focusManager.setEventListener"
---

<objective>
Build the persistent mobile resource bar exactly as `08-UI-SPEC.md` specifies: five
resource cells above the tab navigator, ticking once a second between reads, clamped
so it can never display a number the server would not confirm.

Purpose: `ResourceCounter` today is frozen until the next full refetch, resources are
distinguished by text colour alone (a latent colourblind violation — the `icon` prop
has existed since Phase 02 and no caller has ever passed it), and the resource row is
duplicated inside the city screen rather than being player-level chrome. This plan
fixes all three and closes the last of the phase's five deliverables.

Output: a `ResourceBar` mounted above `<Tabs>`, a pure clamped `interpolateResources`
projection, a shared `useCityQuery`, resource-type icons, MAX/`tray-alert` full-state
signalling, and safe-area ownership moved from `CityScene` to the bar.
</objective>

<execution_context>
@/Users/sierra/.claude/get-shit-done/workflows/execute-plan.md
@/Users/sierra/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/ROADMAP.md
@.planning/phases/08-resources-economy/08-CONTEXT.md
@.planning/phases/08-resources-economy/08-UI-SPEC.md
@.planning/codebase/CONVENTIONS.md
@.planning/codebase/TESTING.md
@.planning/phases/08-resources-economy/08-01-production-rate-contract-SUMMARY.md

<interfaces>
<!-- Verified against live code. The executor should not need to go looking. -->

**Design tokens** — `packages/tooling/design-tokens/index.ts`, reached via
`useTheme()` from `@/theme`:

```ts
theme.spacing   = { xs: 4, sm: 8, md: 12, lg: 16, xl: 24, '2xl': 32, '3xl': 48 }
theme.radius    = { sm: 4, md: 8, lg: 12, xl: 20, full: 9999 }
theme.resourceColors = { food: '#7A9A4F', wood: '#8A6236', stone: '#8C8C87', iron: '#6E7B8B', gold: '#C08A2E' }
theme.color.surface.raised    // the bar's background
theme.color.border.strong     // the bar's bottom divider
theme.color.border.subtle     // the capacity meter's track
theme.color.text.secondary    // the MAX caption and the tray-alert glyph
theme.typography.numeric      // 16 / 600 / 20, tabular figures
theme.typography.caption      // 12 / 400 / 16
export type ResourceKey = 'food' | 'wood' | 'stone' | 'iron' | 'gold'
```

**`ResourceCounter`** — `apps/mobile/src/shared/components/ResourceCounter.tsx`,
current props (extend, do not fork):

```ts
export interface ResourceCounterProps extends ViewProps {
  resource: ResourceKey;
  amount: number;
  icon?: React.ReactNode;   // declared Phase 02, never passed by any caller until now
}
export function formatResourceAmount(amount: number): string;  // 1.1K / 2.4M / floor
```

**`Text`** — `apps/mobile/src/shared/components/Text.tsx`:
`<Text variant="numeric" | "caption" | ... color={string}>`.

**`Skeleton`** — `apps/mobile/src/shared/components/Skeleton.tsx`:
`<Skeleton width={DimensionValue} height={DimensionValue} borderRadius={number} />`.

**The city query today** lives inline in `apps/mobile/app/(tabs)/city.tsx`:

```ts
const cityQuery = useQuery({
  queryKey: ['game', 'city'],
  queryFn: () => apiRequest<CityData>('/game/city', {}, { authenticated: true }),
});
useCityRealtime(cityQuery.data?.city.id ?? null, cityQuery.data?.realtime ?? null);
```

`app/_layout.tsx` already sets `staleTime: 30_000` on the QueryClient default options —
reuse that number for `refetchInterval`, do not invent a new one.

**`CityData.resources`** after plan 08-01 (from `@castleroyale/contracts`):
`{ current: ResourceBundle; capacity: ResourceBundle; rate: ResourceRate }`, where
`rate` is signed integer units per HOUR. `CityData.server_time` is the as-of instant.

**`CityScene.tsx` — the two required edits.** Line ~54 currently reads
`paddingTop: insets.top + theme.spacing.sm`, and lines ~73-81 hold the
`RESOURCE_KEYS.map(...)` block rendering five `ResourceCounter`s. Both go.

**Glyphs** — all six verified present in the installed
`@expo/vector-icons` MaterialCommunityIcons glyph map:
`barley` (food), `tree` (wood), `terrain` (stone), `anvil` (iron), `gold` (gold),
`tray-alert` (storage full).

**Jest mocking, copied from `apps/mobile/__tests__/city-scene.test.tsx`.**
`@expo/vector-icons` pulls in `expo-font` → `expo-asset`, which Jest cannot resolve in
this workspace, so every test rendering an icon needs:

```tsx
jest.mock('@expo/vector-icons', () => {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const ReactNativeMock = require('react-native');
  return {
    MaterialCommunityIcons: (props: { name: string }) => (
      <ReactNativeMock.View testID={`icon-${props.name}`} />
    ),
  };
});
```

`react-native-safe-area-context` is likewise mocked in that file with
`useSafeAreaInsets: () => ({ top: 0, bottom: 0, left: 0, right: 0 })`.

**Localization** — `packages/localization/locales/{en,pt-BR,es}/mvp.json`, each with a
top-level `"resources"` object currently holding exactly the five display names
(`food`, `wood`, `stone`, `iron`, `gold`).
</interfaces>
</context>

<decisions>
**Locked decision — the UI-SPEC's manual screenshot check becomes an automated layout
regression test, plus a human step deferred to phase verification.** This runs inside
an autonomous multi-phase session where a blocking checkpoint stalls the run, and the
specific regression the screenshot guards against (the top inset counted twice once
the bar owns the safe area) is exactly assertable in Jest. Task 3 asserts it. The
device screenshot stays in `<verification>` as a human step for `/gsd:verify-phase`,
not as a blocking task.

**Locked decision — floating-point maths inside `interpolateResources` is not an
ADR-010 violation.** ADR-010 governs stored, authoritative economy state on the
server. This function is a client display projection that is never fed into an
affordability check (`08-CONTEXT.md`, Client decision) and always `Math.floor`s to an
integer before render. The UI-SPEC states this explicitly; record it in the file's own
docblock so a future reader does not "fix" it.
</decisions>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Resource icons, the extended counter, the pure interpolation and the new strings</name>
  <files>apps/mobile/src/shared/components/resourceIcons.ts, apps/mobile/src/shared/components/ResourceCounter.tsx, apps/mobile/src/features/economy/interpolation/interpolateResources.ts, packages/localization/locales/en/mvp.json, packages/localization/locales/pt-BR/mvp.json, packages/localization/locales/es/mvp.json, apps/mobile/__tests__/resource-interpolation.test.ts</files>
  <read_first>
    - apps/mobile/src/shared/components/ResourceCounter.tsx (the whole file — the new props wrap its render, they do not replace it)
    - apps/mobile/src/features/city/rendering/grid.ts (the pure-function convention `interpolateResources` mirrors: no React, no RN import, unit-testable)
    - apps/mobile/__tests__/city-grid.test.ts (the Jest style for a pure-function test file)
    - packages/localization/locales/en/mvp.json (the existing `"resources"` object the four new keys join)
    - .planning/phases/08-resources-economy/08-UI-SPEC.md (§ Copywriting Contract for the exact strings, § Resource-type icons for the exact glyph names, § Interpolation contract Rule 2 for the exact function body)
    - .planning/codebase/CONVENTIONS.md (§ TypeScript — `type` over `interface`, no `any`, no hardcoded colour/spacing, no user-facing string literal)
  </read_first>
  <behavior>
    - `interpolateResources({current:{food:100},capacity:{food:1000},rate:{food:3600},capturedAt:0}, 1000)` returns `food: 101` — one second at 3600/hour is one unit.
    - Clamped at capacity: with `current.food = 990`, `capacity.food = 1000`, `rate.food = 3600`, at `now = 60_000` the result is `1000`, not 1050. It saturates and stops climbing with no separate flag.
    - Never extrapolates backward: `now < capturedAt` yields the captured values unchanged (`elapsedMs` floors at 0).
    - Floors to an integer: a projection of `100.9` renders `100`.
    - Zero capacity is not treated as a cap: with `capacity.food = 0` the value grows unclamped from `current` (a resource with no warehouse is not permanently at zero).
    - A negative rate (Phase 12 upkeep) drains toward zero and floors there, never below.
  </behavior>
  <action>
**Step 1 — new file** `apps/mobile/src/shared/components/resourceIcons.ts`:

```ts
import type { ResourceKey } from '@castleroyale/tooling/design-tokens';

/**
 * The single resource-to-glyph mapping. Resources were distinguished by text
 * colour alone until this phase, which is a colour-only signal the project rules
 * forbid. Any future screen needing a resource icon (market, Phase 27; training
 * upkeep, Phase 12) reuses this instead of inventing a second mapping.
 *
 * Every name below was verified present in the installed MaterialCommunityIcons
 * glyph map before being written here.
 */
export const RESOURCE_ICONS: Record<ResourceKey, string> = {
  food: 'barley',
  wood: 'tree',
  stone: 'terrain',
  iron: 'anvil',
  gold: 'gold',
};

/** Shown beside a resource whose warehouse is full. Never a colour change. */
export const STORAGE_FULL_ICON = 'tray-alert';

export const RESOURCE_KEYS: ResourceKey[] = ['food', 'wood', 'stone', 'iron', 'gold'];
```

If `MaterialCommunityIcons`'s prop type rejects a plain `string`, type the record as
`Record<ResourceKey, React.ComponentProps<typeof MaterialCommunityIcons>['name']>` and
import the component type only — do not cast with `as any` (CONVENTIONS.md forbids it).

**Step 2 — extend `ResourceCounter`.** Add two optional props and a second row. The
existing row-1 render (icon + numeric text) is unchanged:

```ts
export interface ResourceCounterProps extends ViewProps {
  resource: ResourceKey;
  amount: number;
  icon?: React.ReactNode;
  capacity?: number;      // when present, renders the 4pt capacity meter
  isFull?: boolean;       // when true, appends the tray-alert glyph and the MAX caption
}
```

When `capacity` is a number, render below row 1 a `theme.spacing.xs`-tall,
full-width track with `backgroundColor: theme.color.border.subtle` and
`borderRadius: theme.radius.full`, containing a fill `View` with
`width: `${percent}%`` and `backgroundColor: theme.resourceColors[resource]`, where
`percent = capacity > 0 ? Math.min(100, Math.max(0, (amount / capacity) * 100)) : 0`.

When `isFull` is true, the caller supplies the `tray-alert` glyph and the MAX caption
is rendered by `ResourceCounter` as a `caption`-variant `Text` in
`theme.color.text.secondary`. The MAX string must come from the caller as a prop or
from the translation hook — never a literal `'MAX'` in the component. Prefer passing
it down: add `fullLabel?: string` and render it only when `isFull && fullLabel`. That
keeps `ResourceCounter` free of an i18n dependency it does not have today.

The meter fill stays the resource's own colour at 100%. Never `theme.color.danger`.
Do not change the component's existing `flexDirection: 'row'` outer container into
something incompatible — wrap it: outer `View` becomes a column holding the existing
row plus the meter.

**Step 3 — new file** `apps/mobile/src/features/economy/interpolation/interpolateResources.ts`.
Copy the body from `08-UI-SPEC.md` § Interpolation contract Rule 2 verbatim, plus a
docblock stating why floating-point maths here is not an ADR-010 violation (see the
Decisions section of this plan). Import `RESOURCE_KEYS` from
`@/shared/components/resourceIcons` and the `ResourceBundle` / `ResourceRate` types
from `@castleroyale/contracts`. No React import, no `react-native` import — this file
must be unit-testable in plain Node.

Watch `noUncheckedIndexedAccess`: `snapshot.current[key]` is `number | undefined`, so
the `?? 0` fallbacks in the spec's body are load-bearing. Keep them.

**Step 4 — localization.** Add these four keys to the existing `"resources"` object in
all three catalogues, verbatim from the UI-SPEC:

`en`:
```json
    "storage_full_short": "MAX",
    "bar_accessibility": "Resource levels",
    "accessible_reading": "{resource}: {amount} of {capacity}",
    "accessible_full": "{resource}: storage full at {capacity}"
```

`pt-BR`:
```json
    "storage_full_short": "MÁX",
    "bar_accessibility": "Níveis de recursos",
    "accessible_reading": "{resource}: {amount} de {capacity}",
    "accessible_full": "{resource}: armazenamento cheio em {capacity}"
```

`es`:
```json
    "storage_full_short": "MÁX",
    "bar_accessibility": "Niveles de recursos",
    "accessible_reading": "{resource}: {amount} de {capacity}",
    "accessible_full": "{resource}: almacenamiento lleno en {capacity}"
```

Do NOT add `errors.WAREHOUSE_CAPACITY_EXCEEDED` or `errors.LEDGER_IMBALANCE` copy —
the UI-SPEC explicitly rules them out of scope here; the bar submits no command.

**Step 5 — new test file** `apps/mobile/__tests__/resource-interpolation.test.ts`
covering every bullet in `<behavior>` above, one `it()` per bullet, using plain object
literals for the snapshot. No rendering, no mocks.
  </action>
  <verify>
    <automated>cd apps/mobile && npx jest __tests__/resource-interpolation.test.ts</automated>
  </verify>
  <acceptance_criteria>
    - `cd apps/mobile && npx jest __tests__/resource-interpolation.test.ts` exits 0 with at least 6 passing tests.
    - `grep -q "barley" apps/mobile/src/shared/components/resourceIcons.ts` and `grep -q "tray-alert" apps/mobile/src/shared/components/resourceIcons.ts` both succeed.
    - `grep -q "capacity?: number" apps/mobile/src/shared/components/ResourceCounter.tsx` and `grep -q "isFull?: boolean" apps/mobile/src/shared/components/ResourceCounter.tsx` both succeed.
    - `grep -q "danger" apps/mobile/src/shared/components/ResourceCounter.tsx` returns NOTHING — a full warehouse is never red.
    - `grep -q "import.*react-native" apps/mobile/src/features/economy/interpolation/interpolateResources.ts` returns NOTHING — the projection is pure.
    - `grep -q "Math.min" apps/mobile/src/features/economy/interpolation/interpolateResources.ts` and `grep -q "Math.floor" apps/mobile/src/features/economy/interpolation/interpolateResources.ts` both succeed.
    - `python3 -c "import json;[json.load(open(f'packages/localization/locales/{l}/mvp.json'))['resources']['storage_full_short'] for l in ['en','pt-BR','es']]"` exits 0 — the key exists in all three catalogues.
    - `python3 -c "import json;[json.load(open(f'packages/localization/locales/{l}/mvp.json'))['resources']['accessible_full'] for l in ['en','pt-BR','es']]"` exits 0.
    - `npm run typecheck` exits 0.
    - `npm run lint` exits 0.
  </acceptance_criteria>
  <done>The icon mapping, the extended counter, the pure clamped projection and all three locale catalogues exist, with the projection's behaviour pinned by tests.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Build the ResourceBar on a shared city query</name>
  <files>apps/mobile/src/features/city/api/useCityQuery.ts, apps/mobile/src/features/economy/components/ResourceBar.tsx, apps/mobile/app/(tabs)/city.tsx, apps/mobile/__tests__/resource-bar.test.tsx</files>
  <read_first>
    - apps/mobile/app/(tabs)/city.tsx (the inline `useQuery` this task extracts, and the pending / error / success branches that must keep behaving identically)
    - apps/mobile/app/_layout.tsx (the `staleTime: 30_000` default this task reuses as `refetchInterval` — do not invent a new interval)
    - apps/mobile/src/features/city/realtime/useCityRealtime.ts (it already invalidates `['game','city']`, which is what makes a spend correct the bar immediately)
    - apps/mobile/src/shared/components/Skeleton.tsx (the first-load state)
    - apps/mobile/src/shared/components/Timer.tsx (the existing 1Hz interval + plain unanimated text precedent this component follows)
    - apps/mobile/__tests__/city-scene.test.tsx (the `@expo/vector-icons`, `react-native-safe-area-context` and `useTranslation` mock blocks to copy)
    - .planning/phases/08-resources-economy/08-UI-SPEC.md (§ Layout of the bar itself, § Interpolation contract Rules 1-4, § Copywriting Contract error/empty states)
  </read_first>
  <behavior>
    - With successful data, the bar renders exactly five cells, one per resource, each carrying its own MaterialCommunityIcons glyph (`icon-barley`, `icon-tree`, `icon-terrain`, `icon-anvil`, `icon-gold` testIDs under the standard mock).
    - While `isPending` with no cached data, it renders five `Skeleton` pills and no numerals.
    - A resource at or above capacity renders the `tray-alert` glyph AND the `resources.storage_full_short` text; the numeral's colour is unchanged.
    - When the query is in an error state but cached data exists, the last numbers stay on screen, the interval stops advancing them, and the container's opacity is 0.6.
    - When the query has never succeeded, the bar renders nothing (`return null`).
    - Each cell is `accessible` with a label built from `resources.accessible_reading` or `resources.accessible_full`; no cell has `accessibilityRole="button"`.
    - The outer container carries `accessibilityLabel` from `resources.bar_accessibility`.
  </behavior>
  <action>
**Step 1 — new file** `apps/mobile/src/features/city/api/useCityQuery.ts`:

```ts
import { useQuery } from '@tanstack/react-query';
import type { CityData } from '@castleroyale/contracts';

import { apiRequest } from '@/api/client';

/**
 * The one `['game', 'city']` query. `ResourceBar` and the city screen both call
 * this; TanStack Query dedupes the identical key, so it is one request and one
 * cache entry, not two.
 *
 * `refetchInterval` reuses the app's global `staleTime` (30s, set in
 * `app/_layout.tsx`) rather than introducing a second cadence number.
 */
export function useCityQuery() {
  return useQuery({
    queryKey: ['game', 'city'],
    queryFn: () => apiRequest<CityData>('/game/city', {}, { authenticated: true }),
    refetchInterval: 30_000,
  });
}
```

**Step 2 — `app/(tabs)/city.tsx`** replaces its inline `useQuery` with
`const cityQuery = useCityQuery();`. Delete the now-unused `useQuery` and `CityData`
imports if nothing else needs them. Every other line of the file — the
`useCityRealtime` call, the pending branch, the error branch, the `<CityScene />`
render — stays exactly as it is.

**Step 3 — new file** `apps/mobile/src/features/economy/components/ResourceBar.tsx`.
Follow `08-UI-SPEC.md` § Layout of the bar itself precisely:

- Outer `View`: `flexDirection: 'row'`, `justifyContent: 'space-between'`,
  `paddingTop: insets.top + theme.spacing.xs`, `paddingBottom: theme.spacing.xs`,
  `paddingHorizontal: theme.spacing.sm`, `gap: theme.spacing.xs`,
  `backgroundColor: theme.color.surface.raised`, `borderBottomWidth: 1`,
  `borderBottomColor: theme.color.border.strong`. **No fixed height** — the flex
  sibling composition is what satisfies the project's runtime-HUD-measurement rule,
  and a hardcoded height would break it.
- `insets` come from `useSafeAreaInsets()`.
- Five cells from `RESOURCE_KEYS`, each `flex: 1`, each a `ResourceCounter` with
  `resource`, `amount` (the interpolated value), `capacity`, `isFull`,
  `fullLabel={t('resources.storage_full_short')}`, and
  `icon={<MaterialCommunityIcons name={RESOURCE_ICONS[resource]} size={16} color={theme.resourceColors[resource]} />}`.
  When `isFull`, also render a 12pt `tray-alert` glyph in `theme.color.text.secondary`
  immediately after the numeral.
- Interpolation state: hold the displayed bundle in `useState`, recompute it from
  `interpolateResources(snapshot, Date.now())` inside a `setInterval(..., 1000)` in a
  `useEffect`. Build the snapshot from `cityQuery.data.resources.current`,
  `.capacity`, `.rate` and `cityQuery.dataUpdatedAt` — TanStack Query's own resolved-at
  timestamp. Do not capture a second `Date.now()` at read time.
- Rule 1: the effect's dependency array includes `cityQuery.dataUpdatedAt`, so every
  successful read resets the baseline instantly. Recompute once immediately on that
  change, then let the interval take over. No easing, no animation.
- Rule 4: skip creating the interval when `cityQuery.isError`, and set the container's
  `opacity` to `0.6`. Resume automatically when `cityQuery.isSuccess` is true again.
- First load: `if (cityQuery.isPending) return <five Skeletons>`. Never succeeded:
  `if (!cityQuery.data) return null;`.
- Accessibility exactly as the `<behavior>` block above states.
- Every colour, size and spacing value comes from `useTheme()`. No literal hex, no
  literal pixel number other than the `1` border width and the `1000` interval period.

**Step 4 — new test file** `apps/mobile/__tests__/resource-bar.test.tsx`. Mock
`@expo/vector-icons`, `react-native-safe-area-context` and `@/i18n/useTranslation`
exactly as `city-scene.test.tsx` does. Mock `useCityQuery` with `jest.mock` so each
test can hand the component a shaped query result:

```tsx
jest.mock('../src/features/city/api/useCityQuery', () => ({
  useCityQuery: jest.fn(),
}));
```

Write one test per `<behavior>` bullet. Use `jest.useFakeTimers()` and
`jest.advanceTimersByTime(1000)` inside `act(...)` to prove the numeral advances by
one second's worth of production, and to prove it does NOT advance while the query is
in an error state.
  </action>
  <verify>
    <automated>cd apps/mobile && npx jest __tests__/resource-bar.test.tsx</automated>
  </verify>
  <acceptance_criteria>
    - `cd apps/mobile && npx jest __tests__/resource-bar.test.tsx` exits 0 with at least 6 passing tests.
    - `grep -q "refetchInterval: 30_000" apps/mobile/src/features/city/api/useCityQuery.ts` succeeds.
    - `grep -q "useCityQuery()" "apps/mobile/app/(tabs)/city.tsx"` succeeds and `grep -q "useQuery({" "apps/mobile/app/(tabs)/city.tsx"` returns NOTHING.
    - `grep -q "dataUpdatedAt" apps/mobile/src/features/economy/components/ResourceBar.tsx` succeeds.
    - `grep -q "height:" apps/mobile/src/features/economy/components/ResourceBar.tsx` returns NOTHING — no fixed height anywhere in the bar.
    - `grep -q "accessibilityRole" apps/mobile/src/features/economy/components/ResourceBar.tsx` returns NOTHING — nothing here is tappable.
    - `grep -q "resources.bar_accessibility" apps/mobile/src/features/economy/components/ResourceBar.tsx` succeeds.
    - `grep -qE "#[0-9A-Fa-f]{6}" apps/mobile/src/features/economy/components/ResourceBar.tsx` returns NOTHING — tokens only.
    - `npm run typecheck` exits 0.
    - `npm run lint` exits 0.
  </acceptance_criteria>
  <done>The bar renders five icon-bearing, capacity-metered cells from one shared query, ticks once a second, freezes on error, and signals a full warehouse with a glyph and the word MAX.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: Mount the bar, hand it the safe area, and wire app-resume refetching</name>
  <files>apps/mobile/app/(tabs)/_layout.tsx, apps/mobile/app/_layout.tsx, apps/mobile/src/features/city/components/CityScene.tsx, apps/mobile/__tests__/city-scene.test.tsx</files>
  <read_first>
    - apps/mobile/app/(tabs)/_layout.tsx (the whole file — `<Tabs>` gains a wrapper, its `screenOptions` are untouched)
    - apps/mobile/app/_layout.tsx (the provider stack the `focusManager` wiring joins)
    - apps/mobile/src/features/city/components/CityScene.tsx (line ~54 `paddingTop: insets.top + theme.spacing.sm`, and lines ~73-81 the `RESOURCE_KEYS.map(...)` block — both change)
    - apps/mobile/__tests__/city-scene.test.tsx (the existing four scene tests plus the `city tab screen architecture` source-grep test at the bottom — this task extends that last block, it does not rewrite the others)
    - .planning/phases/08-resources-economy/08-UI-SPEC.md (§ Placement, § HUD-aware framing, § Interpolation contract Rule 5)
    - /Users/sierra/Dev/Jogos/CLAUDE.md (§ "O jogo ocupa a tela" — HUD height is measured at runtime, never a hardcoded pixel constant; the flex-sibling composition is how this component satisfies that)
  </read_first>
  <behavior>
    - `app/(tabs)/_layout.tsx` renders `<ResourceBar />` as a sibling ABOVE `<Tabs>`, both inside a `View style={{ flex: 1 }}`, and `<Tabs>` still carries every existing `screenOptions` and all five `Tabs.Screen` entries.
    - `CityScene` no longer adds `insets.top` — the top inset is counted once, by the bar.
    - `CityScene` no longer renders any `ResourceCounter`; the global bar supersedes it.
    - The city scene's slot grid still renders 18 tappable plots at or above the 44pt touch floor (the four existing `city-scene.test.tsx` tests still pass, unmodified).
    - `app/_layout.tsx` wires `focusManager.setEventListener` to React Native's `AppState`, so returning to the app triggers a real refetch instead of a stretched extrapolation.
  </behavior>
  <action>
**Step 1 — `app/(tabs)/_layout.tsx`.** Wrap the existing return:

```tsx
  return (
    <View style={{ flex: 1 }}>
      <ResourceBar />
      <Tabs screenOptions={{ /* unchanged */ }}>
        {/* all five Tabs.Screen entries, unchanged */}
      </Tabs>
    </View>
  );
```

Add `import { View } from 'react-native';` and
`import { ResourceBar } from '@/features/economy/components/ResourceBar';`. Do not set
a height on the wrapper or on the bar — `<Tabs>` keeps `flex: 1` and receives whatever
remains, which is how the runtime-measurement rule is satisfied by construction rather
than by arithmetic.

**Step 2 — `CityScene.tsx`, two deletions.**

Change line ~54 from `paddingTop: insets.top + theme.spacing.sm,` to
`paddingTop: theme.spacing.sm,`. Then remove the now-unused `insets` local and the
`useSafeAreaInsets` import if nothing else in the file uses them (check first — the
detail sheet may). Add a one-line comment above the padding explaining the handover:

```tsx
        // The resource bar above the tab navigator owns the top safe area now;
        // adding insets.top here as well would count the notch twice.
```

Delete the entire `RESOURCE_KEYS.map(...)` `<View>` block (lines ~73-81), the
`RESOURCE_KEYS` constant, the `ResourceCounter` import, and the `ResourceKey` type
import if it becomes unused. Nothing else in the file changes — the title block, the
`onLayout` frame measurement, `computeSlotLayout`, the slot grid and the detail sheet
all stay exactly as they are.

**Step 3 — `app/_layout.tsx`, app-resume refetching.** Add, above the component:

```tsx
import { AppState, type AppStateStatus } from 'react-native';
import { focusManager } from '@tanstack/react-query';

// Without this, TanStack Query's focus-based refetching never fires on React
// Native at all, and a player returning after minutes away would watch the
// resource bar free-run its extrapolation across a gap it cannot confirm.
// This is the documented RN recipe, and it benefits every query, not just city.
focusManager.setEventListener((handleFocus) => {
  const subscription = AppState.addEventListener('change', (status: AppStateStatus) => {
    handleFocus(status === 'active');
  });

  return () => subscription.remove();
});
```

If module-scope execution causes a lint or SSR complaint, move the call into a
`useEffect(() => { ... }, [])` inside `RootLayout` and return the unsubscribe from it.
Everything else in the file — the `QueryClient` memo, the provider nesting, the
`StatusBar`, the `Stack` — is unchanged.

**Step 4 — `apps/mobile/__tests__/city-scene.test.tsx`.** Leave the four existing
`describe('CityScene')` tests untouched; they must still pass. Add `rate` to the
`buildCity()` fixture's `resources` object
(`rate: { food: 3600, wood: 3600, stone: 3600, iron: 0, gold: 0 }`) so the fixture
matches the post-08-01 contract. Then extend the bottom
`describe('city tab screen architecture')` block with three source-grep assertions —
this is the automated stand-in for the UI-SPEC's manual screenshot, targeting the
exact regression it guards:

```tsx
it('leaves the top safe area to the resource bar and drops the duplicated resource row', () => {
  const scene = readFileSync(join(__dirname, '../src/features/city/components/CityScene.tsx'), 'utf8');
  expect(scene).not.toContain('insets.top');
  expect(scene).not.toContain('ResourceCounter');

  const layout = readFileSync(join(__dirname, '../app/(tabs)/_layout.tsx'), 'utf8');
  expect(layout).toContain('<ResourceBar />');
  expect(layout.indexOf('<ResourceBar />')).toBeLessThan(layout.indexOf('<Tabs'));

  const bar = readFileSync(join(__dirname, '../src/features/economy/components/ResourceBar.tsx'), 'utf8');
  expect(bar).toContain('insets.top');
  expect(bar).not.toMatch(/height:\s*\d/);
});

it('refetches on app resume rather than extrapolating across a backgrounding gap', () => {
  const root = readFileSync(join(__dirname, '../app/_layout.tsx'), 'utf8');
  expect(root).toContain('focusManager.setEventListener');
  expect(root).toContain('AppState');
});
```

Then run the full mobile suite and fix any fallout in the other test files. If
`touch-targets.test.tsx` or `map-canvas.test.tsx` breaks because the tab layout now
renders a bar, mock `useCityQuery` in that file the same way `resource-bar.test.tsx`
does rather than weakening the assertion.
  </action>
  <verify>
    <automated>npm test && npm run typecheck && npm run lint</automated>
  </verify>
  <acceptance_criteria>
    - `npm test` exits 0 with at least 13 suites passing and 0 failures.
    - `grep -q "<ResourceBar />" "apps/mobile/app/(tabs)/_layout.tsx"` succeeds.
    - `grep -q "insets.top" apps/mobile/src/features/city/components/CityScene.tsx` returns NOTHING.
    - `grep -q "ResourceCounter" apps/mobile/src/features/city/components/CityScene.tsx` returns NOTHING.
    - `grep -q "insets.top" apps/mobile/src/features/economy/components/ResourceBar.tsx` succeeds — the safe area moved, it was not dropped.
    - `grep -q "focusManager.setEventListener" apps/mobile/app/_layout.tsx` succeeds.
    - `grep -q "AppState" apps/mobile/app/_layout.tsx` succeeds.
    - `cd apps/mobile && npx jest __tests__/city-scene.test.tsx` exits 0 — all four original scene tests plus the two new architecture tests pass.
    - `npm run typecheck` exits 0.
    - `npm run lint` exits 0.
    - `cd apps/api && ./vendor/bin/pest` still exits 0 — this plan touches no PHP.
  </acceptance_criteria>
  <done>The bar is mounted above the tabs, owns the top inset, the city scene no longer duplicates the resource row or double-counts the notch, and a resumed app refetches instead of guessing.</done>
</task>

</tasks>

<verification>
1. `npm test` and `npm run typecheck` and `npm run lint` all pass.
2. `interpolateResources` clamps at capacity, never extrapolates backward, floors to an integer, and handles a zero capacity and a negative rate — proven by unit tests.
3. The bar renders five icon-bearing cells, skeletons on first load, nothing when the query has never succeeded, and frozen dimmed values when a refetch fails with data cached.
4. A full warehouse shows `tray-alert` + MAX, and `grep` proves no `danger` colour is used in `ResourceCounter` or the bar.
5. `CityScene.tsx` contains no `insets.top` and no `ResourceCounter`; `ResourceBar.tsx` contains `insets.top` and no fixed height.
6. `app/_layout.tsx` wires `focusManager` to `AppState`.
7. **Human step, deferred to `/gsd:verify-phase`:** take one portrait screenshot each of the city tab and the world tab on a mid-range-equivalent simulator and confirm neither the slot grid nor the map canvas is clipped or pushed off the bottom edge by the new bar. The automated assertions above cover the double-inset regression specifically; the screenshot covers everything they cannot see.
</verification>

<success_criteria>
- ROADMAP Phase 08 plan 5 delivered: a mobile resource bar with live client-side interpolation.
- The interpolation never lies — it converges to server truth on every read, never displays above capacity, and never appears to keep growing once full.
- The latent colour-only signal in `ResourceCounter` is closed: every resource carries its own glyph, and warehouse fullness is an icon plus a word, never a colour.
- No image was generated; the whole contract runs on existing MaterialCommunityIcons glyphs and existing design tokens.
</success_criteria>

<output>
After completion, create `.planning/phases/08-resources-economy/08-05-mobile-resource-bar-SUMMARY.md`.
</output>
