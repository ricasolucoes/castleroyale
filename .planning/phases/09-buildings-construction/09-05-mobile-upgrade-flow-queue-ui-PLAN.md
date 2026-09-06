---
phase: 09-buildings-construction
plan: 05
type: execute
wave: 2
depends_on: ["09-01", "09-02"]
files_modified:
  - apps/mobile/src/shared/components/buildingIcons.ts
  - apps/mobile/src/shared/components/ResourceCounter.tsx
  - apps/mobile/src/features/city/components/CitySlot.tsx
  - apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx
  - apps/mobile/src/features/city/components/ConstructionQueueStrip.tsx
  - apps/mobile/src/features/city/components/CityScene.tsx
  - apps/mobile/src/features/city/api/useUpgradeBuilding.ts
  - apps/mobile/__tests__/city-scene.test.tsx
  - apps/mobile/__tests__/building-upgrade.test.tsx
  - packages/localization/locales/en/mvp.json
  - packages/localization/locales/pt-BR/mvp.json
  - packages/localization/locales/es/mvp.json
autonomous: true
requirements: [REQ-05, REQ-09]

must_haves:
  truths:
    - "A player can start a building upgrade from the slot detail sheet, seeing its cost and duration before committing"
    - "The sheet tells the player which of four reasons blocks an upgrade — max level, already building, queue full, cannot afford — before any request is sent"
    - "Affordability is decided against the last server snapshot, never against the locally ticking resource bar"
    - "A server refusal keeps the sheet open, shows localized copy for the returned error code, and refetches truth so the button updates itself"
    - "The construction queue's occupancy is visible at a glance and each occupied slot jumps to the building it belongs to"
    - "Every one of the eighteen buildings renders a distinct icon; none falls back to a shared silhouette"
  artifacts:
    - path: "apps/mobile/src/shared/components/buildingIcons.ts"
      provides: "BUILDING_ICONS — the 18-building glyph map replacing CitySlot's binary ternary"
      contains: "siege_workshop"
    - path: "apps/mobile/src/features/city/api/useUpgradeBuilding.ts"
      provides: "The upgrade mutation, invalidating ['game','city'] on settle"
      contains: "invalidateQueries"
    - path: "apps/mobile/src/features/city/components/ConstructionQueueStrip.tsx"
      provides: "The queue occupancy strip with tappable filled pips"
      contains: "queue_slot_accessible"
    - path: "apps/mobile/__tests__/building-upgrade.test.tsx"
      provides: "Proof of the five CTA states, the server-snapshot affordability rule and the error protocol"
  key_links:
    - from: "apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx"
      to: "cityQuery.data.resources.current (passed as the `resources` prop)"
      via: "affordability comparison against next_level_cost"
      pattern: "next_level_cost"
    - from: "apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx"
      to: "useUpgradeBuilding"
      via: "mutation on CTA press"
      pattern: "useUpgradeBuilding"
    - from: "apps/mobile/src/features/city/components/ConstructionQueueStrip.tsx"
      to: "citySelectionStore.selectSlot"
      via: "filled pip onPress"
      pattern: "onSelectSlot"
---

<objective>
Restore the upgrade call-to-action Phase 07 deliberately removed, coherently inside
the slot detail sheet, and add the one new surface the phase needs: a construction
queue strip that answers "how many of my slots are busy" without scanning the grid.

Purpose: `09-UI-SPEC.md` is an approved contract (5 PASS, 1 flag applied) and this
plan is the only deliverable it governs. Every glyph, token, copy string and state
transition below is quoted from it; none is a fresh design decision.
Output: an eighteen-building icon map, `formatResourceCost`, the five-state CTA,
the queue strip, thirteen new localization strings in three catalogues, and the
tests that pin the affordability rule to the server snapshot.
</objective>

<execution_context>
@/Users/sierra/.claude/get-shit-done/workflows/execute-plan.md
@/Users/sierra/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/ROADMAP.md
@.planning/STATE.md
@.planning/phases/09-buildings-construction/09-CONTEXT.md
@.planning/phases/09-buildings-construction/09-UI-SPEC.md
@.planning/codebase/CONVENTIONS.md
@docs/mobile/architecture.md
@docs/design-system/tokens.md
@.planning/phases/09-buildings-construction/09-01-SUMMARY.md
@.planning/phases/09-buildings-construction/09-02-SUMMARY.md

<interfaces>
<!-- Everything below is already in the repo. Do not go looking for it. -->

`@castleroyale/contracts` after 09-02:

```ts
type CityBuilding = {
  slot: string; code: string; name_key: string; category: string;
  level: number; max_level: number;
  next_level_cost: ResourceBundle;      // all five keys, integers
  build_time_seconds: number;           // already scaled by the server (09-02)
};
type CitySlot = { slot: string; status: 'empty' | 'occupied'; building: CityBuilding | null };
type Construction = { id: string; building_code: string; from_level: number; target_level: number; started_at: string; finishes_at: string };
type CityData = { player; world; city; resources: { current: ResourceBundle; capacity: ResourceBundle; rate: ResourceRate };
                  slots: CitySlot[]; constructions: Construction[]; queue_limit: number; realtime; server_time: string };
```

`apps/mobile/src/shared/components/resourceIcons.ts`:

```ts
type GlyphName = ComponentProps<typeof MaterialCommunityIcons>['name'];
export const RESOURCE_ICONS: Record<ResourceKey, GlyphName> = { food: 'barley', wood: 'tree', stone: 'terrain', iron: 'anvil', gold: 'gold' };
export const STORAGE_FULL_ICON: GlyphName = 'tray-alert';
export const RESOURCE_KEYS: ResourceKey[] = ['food', 'wood', 'stone', 'iron', 'gold'];
```

`apps/mobile/src/shared/components/ResourceCounter.tsx` exports
`formatResourceAmount(amount: number): string` beside the component.

`apps/mobile/src/shared/components/Timer.tsx` exports
`formatDuration(ms: number): string` → `HH:MM:SS`, and `<Timer targetTimestamp onFinish />`
ticking at 1 Hz.

`apps/mobile/src/shared/components/Button.tsx`:
`{ title: string; variant?: 'primary' | 'secondary' | 'danger'; style?: ViewStyle }`
plus every `PressableProps` (so `disabled`, `accessibilityLabel` and `onPress` pass
through untouched). **No change to this component is required or permitted.**

`apps/mobile/src/shared/components/Badge.tsx`:
`{ label: string; variant?: 'neutral' | 'success' | 'warning' | 'danger' }`.

`apps/mobile/src/features/city/state/citySelectionStore.ts` exposes
`selectedSlot`, `selectSlot(slot: string)`, `clearSelection()`.

`apps/mobile/src/api/client.ts` exports `apiRequest<TData>(path, init, options)`
and `class ApiError { code: ErrorCode; ... }`. `apiRequest` attaches an
`Idempotency-Key` to every POST automatically.

Design tokens available on `useTheme()`: `spacing.{xs:4,sm:8,md:12,lg:16,xl:24}`,
`radius.{sm,md,lg,full}`, `color.surface.raised`, `color.bg.sunken`,
`color.border.{subtle,strong}`, `color.accent.{bronze,gold}`,
`color.text.{primary,secondary,inverse}`, `resourceColors[resource]`,
`minTouchTarget` (44).

Test conventions in `apps/mobile/__tests__/`: `useTranslation` is mocked to echo
`key` or `` `${key} ${JSON.stringify(params)}` ``; `@gorhom/bottom-sheet`,
`@expo/vector-icons` (renders `testID={`icon-${name}`}`) and
`react-native-safe-area-context` are mocked; hooks are mocked with `jest.mock`
rather than wrapped in providers (`resource-bar.test.tsx` mocks `useCityQuery`).
</interfaces>

<binding_constraints>
From `09-UI-SPEC.md`, non-negotiable:

1. **No generated art.** Both Gemini and OpenAI image generation are
   billing-blocked (STATE.md, Phase 07-04). Icons only, from the installed
   `MaterialCommunityIcons` glyph map.
2. **Affordability reads `cityQuery.data.resources.current`, never the interpolated
   bar.** The sheet must not import `interpolateResources`, must not read
   `ResourceBar`'s ticked state, and must not call `Date.now()` inside the
   affordability comparison. (`Date.now()` remains correct for the existing Timer
   skew correction — that is a different computation.)
3. **Neither "cannot afford" nor "queue full" is `danger`/red.** A normal economic
   ceiling is not an error — Phase 08's established rule. The signal is the
   button's text and shape changing, never colour alone.
4. **Never optimistically show a building as upgrading before the 201.**
5. **No confirmation dialog.** Cost and duration are shown before the tap; a modal
   would confirm the same fact twice.
</binding_constraints>
</context>

<tasks>

<task type="auto">
  <name>Task 1: The eighteen-building icon map, formatResourceCost, and thirteen strings</name>

  <read_first>
    - apps/mobile/src/shared/components/resourceIcons.ts (the exact file shape and docblock style to mirror)
    - apps/mobile/src/features/city/components/CitySlot.tsx (the `building.category === 'core' ? 'castle' : 'sprout-outline'` ternary being replaced)
    - apps/mobile/src/shared/components/ResourceCounter.tsx (where `formatResourceAmount` sits — the new helper goes beside it)
    - .planning/phases/09-buildings-construction/09-UI-SPEC.md (§ Assets — the glyph table and its governing rule; § Copywriting Contract — the new-key table)
    - packages/localization/locales/en/mvp.json (the `building` and `city` objects the new keys join)
  </read_first>

  <files>
    apps/mobile/src/shared/components/buildingIcons.ts,
    apps/mobile/src/shared/components/ResourceCounter.tsx,
    apps/mobile/src/features/city/components/CitySlot.tsx,
    packages/localization/locales/en/mvp.json,
    packages/localization/locales/pt-BR/mvp.json,
    packages/localization/locales/es/mvp.json
  </files>

  <action>
**1a. `apps/mobile/src/shared/components/buildingIcons.ts`**, structured exactly like
`resourceIcons.ts` (same `GlyphName` type alias, same docblock discipline). Every
one of these names was re-verified present in
`apps/mobile/node_modules/@expo/vector-icons/build/vendor/react-native-vector-icons/glyphmaps/MaterialCommunityIcons.json`
before this plan was written; verify again before committing.

```ts
export const BUILDING_ICONS: Record<string, GlyphName> = {
  palace: 'castle',
  barracks: 'sword',
  archery_range: 'bow-arrow',
  stable: 'horse-variant',
  siege_workshop: 'tank',
  academy: 'school',
  embassy: 'handshake',
  marketplace: 'store',
  warehouse: 'warehouse',
  hospital: 'hospital-box',
  walls: 'shield-home-outline',
  watchtower: 'binoculars',
  farm: 'barley',
  lumber_mill: 'tree',
  quarry: 'terrain',
  iron_mine: 'anvil',
  treasury: 'gold',
  tavern: 'beer',
};

/** A code with no mapping yet. Visually distinct from every entry above, on purpose. */
export const UNMAPPED_BUILDING_ICON: GlyphName = 'home-city-outline';

/** Marks a cost the player cannot currently meet. Never a colour change. */
export const COST_SHORTFALL_ICON: GlyphName = 'alert-circle-outline';

export function buildingIcon(code: string): GlyphName {
  return BUILDING_ICONS[code] ?? UNMAPPED_BUILDING_ICON;
}
```

Carry the governing rule into the docblock, in the UI-SPEC's own terms: a building
whose entire function is producing one resource **reuses that resource's glyph**
(`farm`/`barley`, `lumber_mill`/`tree`, `quarry`/`terrain`, `iron_mine`/`anvil`,
`treasury`/`gold`) so the player reinforces an association learned from the
resource bar instead of memorising an eighteenth symbol; every other building has a
dedicated, silhouette-distinct glyph. A new code must be added to this map, not
left on the fallback — falling back silently would reopen the colourblind-safety
gap the map exists to close. Note that the `gold` *glyph name* is unrelated to the
`accent.gold` *colour token*: like every category icon it is tinted
`text.secondary`.

**1b. `CitySlot.tsx`** — replace the two-way ternary with the map. It is a
one-line change plus one import:

```ts
name={buildingIcon(building.code)}
```

Nothing else in that file moves: same size (`theme.spacing.xl`), same
`theme.color.text.secondary` tint, same layout, same `Timer`.

**1c. `formatResourceCost` in `ResourceCounter.tsx`**, exported beside
`formatResourceAmount`. This restores, verbatim in behaviour, the `formatCost`
helper deleted with the pre-Phase-07 card list — exported this time, because the
sheet needs it now and Phase 12's training queue will:

```ts
/**
 * "Wood 120 · Stone 60". Zero-cost resources are omitted; an all-zero cost
 * returns '' and the caller renders `building.no_cost` instead.
 */
export function formatResourceCost(
  cost: Partial<Record<ResourceKey, number>>,
  resourceLabel: (resource: ResourceKey) => string,
): string {
  return RESOURCE_KEYS.filter((resource) => (cost[resource] ?? 0) > 0)
    .map((resource) => `${resourceLabel(resource)} ${formatResourceAmount(cost[resource] ?? 0)}`)
    .join(' · ');
}
```

Import `RESOURCE_KEYS` from `./resourceIcons`.

**1d. Thirteen new strings in all three catalogues.** Twelve come from the
UI-SPEC's key table; the thirteenth (`city.queue_slots`) is the visible caption the
strip's header needs, defined in § C. Nine go inside the existing `building` and
`city` objects, four inside `errors`.

| key | en | pt-BR | es |
|---|---|---|---|
| `building.cannot_afford` | Not enough resources | Recursos insuficientes | Recursos insuficientes |
| `building.queue_full` | Queue full | Fila cheia | Cola llena |
| `building.upgrading` | Starting... | Iniciando... | Iniciando... |
| `building.cost_accessible` | Cost: {cost} | Custo: {cost} | Coste: {cost} |
| `building.duration_accessible` | Build time: {duration} | Tempo de construção: {duration} | Tiempo de construcción: {duration} |
| `building.upgrade_accessible` | Upgrade {building} | Melhorar {building} | Mejorar {building} |
| `city.queue_title` | Construction queue | Fila de construção | Cola de construcción |
| `city.queue_slots` | {active} of {max} slots in use | {active} de {max} vagas em uso | {active} de {max} espacios en uso |
| `city.queue_accessibility` | Construction queue: {active} of {max} slots in use | Fila de construção: {active} de {max} vagas em uso | Cola de construcción: {active} de {max} espacios en uso |
| `city.queue_slot_accessible` | {building}, under construction | {building}, em construção | {building}, en construcción |
| `city.queue_slot_free` | Queue slot available | Vaga de fila disponível | Espacio de cola disponible |
| `errors.BUILDING_MAX_LEVEL` | This building is already at its maximum level. | Esta edificação já está no nível máximo. | Este edificio ya está en el nivel máximo. |
| `errors.BUILDING_REQUIREMENTS_NOT_MET` | This upgrade is not available yet. | Esta melhoria ainda não está disponível. | Esta mejora aún no está disponible. |

Every other key this plan uses already exists in all three catalogues:
`building.level`, `building.upgrade`, `building.max_level`, `building.no_cost`,
`resources.*`, `errors.INSUFFICIENT_RESOURCES`, `errors.BUILD_QUEUE_FULL`,
`errors.CITY_BUSY`, `errors.generic`, `common.retry`. Do not re-add them.

Finish with `npx prettier --write 'packages/localization/locales/*/mvp.json'`.
  </action>

  <acceptance_criteria>
    - `grep -c "sprout-outline" apps/mobile/src/features/city/components/CitySlot.tsx` is `0`
    - `grep -c "buildingIcon(" apps/mobile/src/features/city/components/CitySlot.tsx` is `1`
    - All eighteen codes are mapped: `node -e "const m=require('fs').readFileSync('apps/mobile/src/shared/components/buildingIcons.ts','utf8');const codes=require('./packages/game-data/data/buildings.json').map(b=>b.code);console.log(codes.filter(c=>!m.includes(c+':')))"` prints `[]`
    - Every glyph exists: `node -e "const g=require('./apps/mobile/node_modules/@expo/vector-icons/build/vendor/react-native-vector-icons/glyphmaps/MaterialCommunityIcons.json');const src=require('fs').readFileSync('apps/mobile/src/shared/components/buildingIcons.ts','utf8');const names=[...src.matchAll(/'([a-z][a-z0-9-]+)'/g)].map(m=>m[1]).filter(n=>n.includes('-')||g[n]!==undefined);console.log(names.filter(n=>g[n]===undefined))"` prints `[]`
    - No glyph is used by two different real building codes: `node -e "const {BUILDING_ICONS}=/*inline-read*/0" ` is not required — instead assert by eye and record in the SUMMARY that the eighteen values contain exactly five intentional resource-glyph reuses and no accidental collision
    - `grep -c "export function formatResourceCost" apps/mobile/src/shared/components/ResourceCounter.tsx` is `1`
    - All thirteen keys land in all three catalogues: `for l in en pt-BR es; do python3 -c "
import json;d=json.load(open('packages/localization/locales/$l/mvp.json'))
keys=['building.cannot_afford','building.queue_full','building.upgrading','building.cost_accessible','building.duration_accessible','building.upgrade_accessible','city.queue_title','city.queue_slots','city.queue_accessibility','city.queue_slot_accessible','city.queue_slot_free','errors.BUILDING_MAX_LEVEL','errors.BUILDING_REQUIREMENTS_NOT_MET']
missing=[k for k in keys if d.get(k.split('.')[0],{}).get(k.split('.',1)[1]) is None]
print('$l', missing)"; done` prints an empty list for each locale
    - `npm run typecheck && npm run lint && npm test` exit 0
  </acceptance_criteria>

  <verify>
    <automated>npm run typecheck &amp;&amp; npm run lint &amp;&amp; npm test</automated>
  </verify>

  <done>
    Every building in the catalogue renders its own verified glyph, the cost
    formatter is shared rather than per-screen, and all thirteen new strings exist
    in `en`, `pt-BR` and `es`.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: The upgrade CTA — five states, server-snapshot affordability, and the refusal protocol</name>

  <read_first>
    - apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx (the occupied branch this extends; the props 09-02 left it with)
    - apps/mobile/src/features/city/components/CityScene.tsx (the caller; where the new props come from)
    - apps/mobile/src/features/city/api/useCityQuery.ts (the `['game','city']` query key the mutation invalidates)
    - apps/mobile/app/(tabs)/city.tsx (the local `errorKey(error)` helper this mirrors)
    - .planning/phases/09-buildings-construction/09-UI-SPEC.md (§ Component, Interaction & Copy-Precedence Contract, sections A and B — the precedence table and the cost/duration rows, verbatim)
    - apps/mobile/src/features/economy/components/ResourceBar.tsx (read once to confirm what this sheet must NOT touch — binding constraint 2)
  </read_first>

  <files>
    apps/mobile/src/features/city/api/useUpgradeBuilding.ts,
    apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx,
    apps/mobile/src/features/city/components/CityScene.tsx
  </files>

  <behavior>
    - `level >= max_level` → a `Badge` reading `building.max_level` and **no button at all**
    - already under construction → the existing `Timer` only, no button (unchanged from Phase 07)
    - `constructions.length >= queue_limit` and this building is not the one building → a disabled `secondary` button titled `building.queue_full`
    - any resource where `next_level_cost[r] > resources.current[r]` → a disabled `secondary` button titled `building.cannot_afford`, and every short resource's cost chip gains an `alert-circle-outline` glyph tinted `text.secondary`
    - otherwise → an enabled `primary` button titled `building.upgrade`, with `accessibilityLabel` `building.upgrade_accessible` interpolating the building's translated name
    - while the mutation is in flight the enabled button's title becomes `building.upgrading` and it is disabled — a prop swap, no spinner, no component change
    - on a mutation error the sheet stays open, renders the error copy for `error.code` in `text.secondary`, and the city query is invalidated
    - the sheet never renders "upgrading" before the server's 201
  </behavior>

  <action>
**2a. `apps/mobile/src/features/city/api/useUpgradeBuilding.ts`.**

```ts
import { useMutation, useQueryClient } from '@tanstack/react-query';
import type { Construction } from '@castleroyale/contracts';

import { apiRequest } from '@/api/client';

/**
 * Start a building upgrade.
 *
 * `onSettled`, not `onSuccess`: a refusal means the server's state moved without
 * us (another device spent the resources, an order freed a slot), so the honest
 * response to both outcomes is to refetch truth and let the CTA re-derive itself
 * — never to patch the cache optimistically (docs/mobile/architecture.md).
 * `apiRequest` attaches a fresh Idempotency-Key per call, which is correct: a
 * retry after a refusal is a new logical operation, not a replay of the old one.
 */
export function useUpgradeBuilding() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (buildingCode: string) =>
      apiRequest<{ construction: Construction }>(
        `/game/city/buildings/${encodeURIComponent(buildingCode)}/upgrade`,
        { method: 'POST' },
        { authenticated: true },
      ),
    onSettled: () => queryClient.invalidateQueries({ queryKey: ['game', 'city'] }),
  });
}
```

**2b. `CitySlotDetailSheet.tsx`.** Extend the props — the caller resolves
everything, the sheet derives nothing from global state:

```ts
export type CitySlotDetailSheetProps = {
  slot: CitySlot | null;
  construction: Construction | null;
  serverTime: string;
  /** cityQuery.data.resources.current — the last server snapshot, never the ticked bar. */
  resources: ResourceBundle;
  activeConstructions: number;
  queueLimit: number;
  open: boolean;
  onClose: () => void;
  onConstructionFinish: () => void;
};
```

Inside the occupied branch, below the existing name / level / category block, add
the derivation exactly as § A specifies:

```ts
const upgrade = useUpgradeBuilding();
const isMax = building.level >= building.max_level;
const shortfalls = RESOURCE_KEYS.filter(
  (resource) => (building.next_level_cost[resource] ?? 0) > (resources[resource] ?? 0),
);
const affordable = shortfalls.length === 0;
const queueFull = !isMax && !showConstruction && activeConstructions >= queueLimit;
```

Then the CTA area, one branch per state, in this order:

1. `isMax` → `<Badge variant="neutral" label={t('building.max_level')} />`, no button, no cost row.
2. `showConstruction` → unchanged Phase 07 `Timer` block only.
3. `queueFull` → `<Button variant="secondary" disabled title={t('building.queue_full')} />`
4. `!affordable` → `<Button variant="secondary" disabled title={t('building.cannot_afford')} />`
5. else → `<Button variant="primary" title={upgrade.isPending ? t('building.upgrading') : t('building.upgrade')} disabled={upgrade.isPending} accessibilityLabel={t('building.upgrade_accessible', { building: t(building.name_key) })} onPress={() => upgrade.mutate(building.code)} />`

States 3, 4 and 5 all render the cost and duration rows above the button (state 1
does not — there is nothing to cost). Per § B:

- **Cost row.** One inline chip per resource where `next_level_cost[resource] > 0`:
  the `RESOURCE_ICONS[resource]` glyph at 12pt, then a `numeric` `Text` of
  `formatResourceAmount(...)` tinted `theme.resourceColors[resource]`, chips
  separated by `theme.spacing.sm` and the icon/numeral gap `theme.spacing.xs`. A
  chip whose resource is in `shortfalls` gains a trailing 12pt
  `COST_SHORTFALL_ICON` tinted `theme.color.text.secondary` — **never `danger`**.
  If every cost is zero render `<Text variant="caption">{t('building.no_cost')}</Text>`.
- **Duration row.** `<Text variant="numeric">{formatDuration(building.build_time_seconds * 1000)}</Text>`
  — the exported `formatDuration` from `Timer.tsx`, static, not a live `Timer`.
  Reusing it guarantees the preview and the post-commit countdown use the identical
  `HH:MM:SS` notation. `build_time_seconds` is already time-scaled by the server
  (09-02); do not scale it again and do not compute a duration client-side.
- Wrap both rows in a single `accessible` `View` whose `accessibilityLabel` is
  `` `${t('building.cost_accessible', { cost })} ${t('building.duration_accessible', { duration })}` ``,
  where `cost` is `formatResourceCost(building.next_level_cost, (r) => t(`resources.${r}`))`
  or `t('building.no_cost')` when empty. One coherent announcement beats five
  icon-only chips.

Reactive error, below the button:

```ts
{upgrade.error && (
  <Text color={theme.color.text.secondary}>{t(upgradeErrorKey(upgrade.error))}</Text>
)}
```

with a local three-line helper mirroring `city.tsx`'s:

```ts
// Local on purpose: city.tsx's twin describes a failed *read*, this one a failed
// *mutation*. They will diverge (retry affordances differ) before they merge.
function upgradeErrorKey(error: unknown): string {
  return error instanceof ApiError ? `errors.${error.code}` : 'errors.generic';
}
```

`text.secondary`, not `danger`: losing a race against another device is not
destructive, the same reasoning that keeps "cannot afford" and "queue full" out of
red. All five reachable codes have copy —
`INSUFFICIENT_RESOURCES`, `BUILD_QUEUE_FULL`, `CITY_BUSY` already existed;
`BUILDING_MAX_LEVEL` and `BUILDING_REQUIREMENTS_NOT_MET` arrived in Task 1.

Do not add a confirmation dialog, do not add a `Card`, do not modify `Button` or
`Badge`, and do not import `interpolateResources`, `ResourceBar` or `useCityQuery`
into this file.

**2c. `CityScene.tsx`** — pass the three new props through from the `city` object it
already holds:

```tsx
<CitySlotDetailSheet
  slot={selected}
  construction={selectedConstruction}
  serverTime={city.server_time}
  resources={city.resources.current}
  activeConstructions={city.constructions.length}
  queueLimit={city.queue_limit}
  open={selectedSlot !== null}
  onClose={clearSelection}
  onConstructionFinish={onRefresh}
/>
```
  </action>

  <acceptance_criteria>
    - `grep -c "interpolateResources\|ResourceBar\|useCityQuery" apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx` is `0` — binding constraint 2
    - `grep -c "theme.color.danger" apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx` is `0` — binding constraint 3
    - `grep -c "variant=\"danger\"" apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx` is `0`
    - `grep -c "building.upgrade_accessible" apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx` is `1`
    - `grep -c "building.upgrading" apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx` is `1`
    - `grep -c "next_level_cost" apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx` is ≥ 2 (the shortfall filter and the cost row)
    - `grep -c "invalidateQueries" apps/mobile/src/features/city/api/useUpgradeBuilding.ts` is `1` and `grep -c "onSuccess" apps/mobile/src/features/city/api/useUpgradeBuilding.ts` is `0`
    - `git diff --stat apps/mobile/src/shared/components/Button.tsx apps/mobile/src/shared/components/Badge.tsx` prints nothing — zero component diffs, as the UI-SPEC promises
    - `grep -c "Alert\|Modal\|confirm" apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx` is `0` — no confirmation dialog
    - `npm run typecheck && npm run lint` exit 0
  </acceptance_criteria>

  <verify>
    <automated>npm run typecheck &amp;&amp; npm run lint</automated>
  </verify>

  <done>
    The sheet renders exactly one of five mutually exclusive CTA states, decides
    affordability from the server snapshot alone, shows cost and duration before the
    tap, and on refusal keeps itself open with localized non-red copy while
    refetching truth.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: The construction queue strip, and the tests that pin all of it</name>

  <read_first>
    - apps/mobile/src/features/city/components/CityScene.tsx (the header block and the slot grid — the strip goes between them, inside the existing ScrollView)
    - apps/mobile/src/features/city/components/CitySlot.tsx (the empty-plot visual grammar the empty pip deliberately reuses: `bg.sunken` fill, dashed `border.subtle`)
    - apps/mobile/__tests__/city-scene.test.tsx (every mock this suite needs, and the two assertions Task 3 must update)
    - apps/mobile/__tests__/resource-bar.test.tsx (the `jest.mock` hook-stubbing pattern used instead of wrapping in a provider)
    - .planning/phases/09-buildings-construction/09-UI-SPEC.md (§ C — the strip's full specification including the ASCII sketch, and Flagged Assumption 5)
  </read_first>

  <files>
    apps/mobile/src/features/city/components/ConstructionQueueStrip.tsx,
    apps/mobile/src/features/city/components/CityScene.tsx,
    apps/mobile/__tests__/city-scene.test.tsx,
    apps/mobile/__tests__/building-upgrade.test.tsx
  </files>

  <behavior>
    - With `queue_limit = 4` and two orders, the strip renders four pips: two filled and tappable, two empty and hidden from the accessibility tree
    - The header caption reads `city.queue_slots` interpolating `{active: 2, max: 4}`; the container's `accessibilityLabel` is `city.queue_accessibility` with the same values
    - Tapping a filled pip calls `selectSlot` with the plot holding that building, opening its detail sheet
    - Every filled pip's hit area is at least 44×44pt
    - The strip renders nothing when `queue_limit` is 0
    - The sheet shows `building.upgrade` when affordable, `building.cannot_afford` when a single resource is short, `building.queue_full` when the queue is at its ceiling, and only a `building.max_level` badge with no button at max level
    - Affordability follows `resources.current` alone: raising `resources.capacity` or `resources.rate` never changes the CTA
  </behavior>

  <action>
**3a. `ConstructionQueueStrip.tsx`.** Props, all derived by the caller:

```ts
export type ConstructionQueueStripProps = {
  constructions: Construction[];
  queueLimit: number;
  /** Resolves a building_code to the plot holding it, or null. Supplied by CityScene. */
  slotForBuildingCode: (code: string) => string | null;
  /** Resolves a building_code to its name_key, for the pip's accessibility label. */
  nameKeyForBuildingCode: (code: string) => string | null;
  serverTime: string;
  onSelectSlot: (slot: string) => void;
};
```

Return `null` when `queueLimit <= 0` — always true today, but this guards against a
future world configured without a build queue rather than assuming.

Layout, straight from § C:

- Header row: `flexDirection: 'row', justifyContent: 'space-between'`, a
  `<Text variant="label">{t('city.queue_title')}</Text>` and a
  `<Text variant="caption" color={theme.color.text.secondary}>{t('city.queue_slots', { active, max })}</Text>`.
- Pip row: `flexDirection: 'row', flexWrap: 'wrap', gap: theme.spacing.xs`,
  `accessibilityLabel={t('city.queue_accessibility', { active: constructions.length, max: queueLimit })}`.
- `queueLimit` pips, index `0..queueLimit-1`:
  - **Filled** (`index < constructions.length`): a `Pressable`,
    `width: theme.minTouchTarget, height: theme.minTouchTarget`,
    `backgroundColor: theme.color.surface.raised`, `borderWidth: 1`,
    `borderColor: theme.color.border.strong`, `borderRadius: theme.radius.md`,
    centred `<MaterialCommunityIcons name={buildingIcon(code)} size={theme.spacing.lg} color={theme.color.text.secondary} />`,
    `accessibilityRole="button"`,
    `accessibilityLabel={t('city.queue_slot_accessible', { building: t(nameKey) })}`,
    `onPress={() => { const slot = slotForBuildingCode(code); if (slot) onSelectSlot(slot); }}`.
    Along the bottom inner edge, a progress bar `height: theme.spacing.xs`,
    `borderRadius: theme.radius.full`, track `theme.color.border.subtle`, fill
    `theme.color.accent.bronze`, `width: `${percent}%`` where
    `percent = clamp(0, 100, (nowMs - startedMs) / (finishesMs - startedMs) * 100)`
    with `nowMs = Date.now()` corrected by the same server skew the scene applies
    (`Date.now() - Date.parse(serverTime)` subtracted from the deadline, exactly as
    `CityScene` already does), recomputed on a 1 Hz `setInterval` cleared on unmount —
    the same cadence and the same "plain update, no animated roll" policy `Timer.tsx`
    already uses. Guard `finishesMs === startedMs` to avoid a division by zero
    (render 100%).
  - **Empty** (`index >= constructions.length`): a non-pressable `View`, same 44×44
    box, `backgroundColor: theme.color.bg.sunken`, `borderStyle: 'dashed'`,
    `borderColor: theme.color.border.subtle`, no icon,
    `accessibilityElementsHidden` — the container already announces "N of M slots
    in use" once; four more VoiceOver nodes saying "queue slot available" is noise.
    (`city.queue_slot_free` exists as the string for a future surface that needs it
    individually; it is deliberately not rendered here.)

**Flagged Assumption 5 applies:** if the progress bar proves out of budget, the pip
may ship as a static filled icon with no bar — the grid's per-tile `Timer` already
gives exact remaining time. The pip's **tap target and accessibility label are not
optional**; they are what makes the strip a navigation aid rather than decoration.
If you drop the bar, say so in the SUMMARY.

**3b. Wire it into `CityScene.tsx`**, between the city name/location header and the
slot grid, inside the existing `ScrollView`. No new frame measurement: Phase 07's
measurement is width-driven for the grid and the column already scrolls. Build the
two resolver callbacks from `city.slots` (a `Map` from `building.code` to
`{ slot, name_key }`, computed once alongside the existing `constructionByCode`
map), and pass `onSelectSlot={selectSlot}`.

**3c. `apps/mobile/__tests__/city-scene.test.tsx`** — three edits:

1. Add `jest.mock('../src/features/city/api/useUpgradeBuilding', () => ({ useUpgradeBuilding: () => ({ mutate: jest.fn(), isPending: false, error: null }) }));`
   alongside the existing mocks. This follows `resource-bar.test.tsx`'s
   hook-stubbing pattern and avoids wrapping the suite in a `QueryClientProvider`
   for a component whose mutation is not what these tests are about.
2. Rename *"opens the sheet with building details and no upgrade CTA…"* to
   *"…and the upgrade CTA"*, replacing `expect(queryByText('building.upgrade')).toBeNull()`
   with `expect(getByText('building.upgrade')).toBeTruthy()`. Phase 07's UI-SPEC
   promised this exact reversal.
3. Add *"renders one queue pip per configured slot"*: with `constructions: []` and
   `queue_limit: 4`, assert `getByLabelText('city.queue_accessibility {"active":0,"max":4}')`
   exists and that `getAllByRole('button')` is still 18 (empty pips are not buttons,
   so the slot count assertion in the first test is unaffected — verify that test
   still passes unchanged).

**3d. `apps/mobile/__tests__/building-upgrade.test.tsx`** — a new suite rendering
`CitySlotDetailSheet` directly with the same mock set (`useTranslation`,
`@gorhom/bottom-sheet`, `@expo/vector-icons`), stubbing `useUpgradeBuilding` per
test so mutation state is controllable. A local `buildSlot(overrides)` factory
produces a `CitySlot` whose building is `farm`, level 1, max_level 3,
`next_level_cost: { food: 0, wood: 120, stone: 60, iron: 0, gold: 0 }`,
`build_time_seconds: 20`.

Tests:

- *"offers the upgrade when the server snapshot covers the cost"* — `resources`
  `{ food: 500, wood: 500, stone: 500, iron: 0, gold: 0 }`; assert `building.upgrade`
  is present and that pressing it calls the stubbed `mutate` with `'farm'`.
- *"refuses on the snapshot, not the ticking bar"* — `resources.wood` `100`; assert
  `building.cannot_afford` is present, `building.upgrade` is absent, and
  `mutate` is never called when the button is pressed. Then re-render with the same
  `current` but a far larger `capacity` and `rate`, and assert the CTA is still
  `building.cannot_afford` — the ticked/derived values must not leak into the
  decision.
- *"says the queue is full before it says anything else about affordability"* —
  affordable resources, `activeConstructions: 4`, `queueLimit: 4`; assert
  `building.queue_full`.
- *"shows only a max-level badge at max level"* — building `level: 3`,
  `max_level: 3`; assert `building.max_level` is present and that neither
  `building.upgrade` nor `building.cannot_afford` nor any element with
  `accessibilityRole="button"` exists in the CTA area.
- *"shows the timer and no button while this building is under construction"* —
  pass a matching `construction`; assert no `building.upgrade` and a
  `HH:MM:SS` string is rendered.
- *"swaps the label while the mutation is in flight"* — stub
  `{ isPending: true }`; assert `building.upgrading` and that the button is
  disabled.
- *"renders localized copy for a server refusal and keeps the sheet open"* — stub
  `{ error: new ApiError(400, { error: { code: 'BUILD_QUEUE_FULL', message: 'x', retryable: false } }) }`;
  assert `errors.BUILD_QUEUE_FULL` is rendered and the building's name is still on
  screen (the sheet did not dismiss).
- *"never colours a normal ceiling as danger"* — a source assertion in the same
  file: read `CitySlotDetailSheet.tsx` with `readFileSync` (the pattern
  `city-scene.test.tsx` already uses for its architecture assertions) and assert it
  contains neither `theme.color.danger` nor `variant="danger"` nor
  `interpolateResources`.
  </action>

  <acceptance_criteria>
    - `grep -c "accessibilityElementsHidden" apps/mobile/src/features/city/components/ConstructionQueueStrip.tsx` is ≥ 1
    - `grep -c "theme.minTouchTarget" apps/mobile/src/features/city/components/ConstructionQueueStrip.tsx` is ≥ 2 (width and height of the pip)
    - `grep -c "city.queue_accessibility" apps/mobile/src/features/city/components/ConstructionQueueStrip.tsx` is `1`
    - `grep -c "ConstructionQueueStrip" apps/mobile/src/features/city/components/CityScene.tsx` is ≥ 2 (import and usage)
    - `grep -c "queryByText('building.upgrade')).toBeNull" apps/mobile/__tests__/city-scene.test.tsx` is `0`
    - `grep -c "useUpgradeBuilding" apps/mobile/__tests__/city-scene.test.tsx` is ≥ 1
    - `apps/mobile/__tests__/building-upgrade.test.tsx` contains at least 8 `it(` blocks
    - `npm test` reports 14 suites green (13 baseline + `building-upgrade`), with no previously-passing test deleted
    - `npm run typecheck && npm run lint` exit 0
    - The five CTA states are each covered by a named test — list them in the SUMMARY with their test names
  </acceptance_criteria>

  <verify>
    <automated>npm run typecheck &amp;&amp; npm run lint &amp;&amp; npm test</automated>
  </verify>

  <done>
    The queue strip shows occupancy at a glance and jumps to a building on tap; the
    five CTA states, the server-snapshot affordability rule, the in-flight label
    swap and the refusal protocol are each pinned by a test; and a source assertion
    prevents the sheet from ever reaching for the interpolated bar or the danger
    colour.
  </done>
</task>

</tasks>

<verification>
- `npm run typecheck` — 0 errors across all four workspaces
- `npm run lint` — 0 warnings
- `npm test` — 14 suites green
- `cd apps/api && ./vendor/bin/pest` — still green (no backend change in this plan)
- Manual, against a running stack: open the city, tap the Farm plot, confirm the
  cost chips and the `HH:MM:SS` duration match, tap Upgrade, confirm a queue pip
  fills, the plot's timer starts, and the sheet's CTA becomes the timer with no
  button. Then tap the filled pip from the scene and confirm it reopens that
  building's sheet. Attach a screenshot to the SUMMARY — "should be fine" is not
  evidence (root CLAUDE.md).
</verification>

<success_criteria>
- The upgrade CTA is back, inside the sheet, with cost and duration shown before
  the tap and no confirmation dialog
- Exactly one of five mutually exclusive states renders, distinguished by text and
  shape rather than colour
- Affordability is computed from `cityQuery.data.resources.current` only; the sheet
  imports neither `interpolateResources` nor `ResourceBar`
- A refusal keeps the sheet open, renders localized copy for the returned code in
  `text.secondary`, and invalidates `['game','city']`
- The queue strip shows `queue_limit` pips with 44pt tap targets and one summary
  accessibility announcement
- All eighteen buildings render a distinct, verified glyph
- Thirteen new strings exist in `en`, `pt-BR` and `es`
- Zero diffs to `Button.tsx` and `Badge.tsx`; no generated art
</success_criteria>

<output>
After completion, create `.planning/phases/09-buildings-construction/09-05-SUMMARY.md`
</output>
</content>
