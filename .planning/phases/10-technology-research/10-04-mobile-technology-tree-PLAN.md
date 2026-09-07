---
phase: 10-technology-research
plan: 04
type: execute
wave: 3
depends_on: ["10-01", "10-03"]
files_modified:
  - apps/mobile/src/shared/components/technologyIcons.ts
  - apps/mobile/src/features/technology/api/useTechnologyQuery.ts
  - apps/mobile/src/features/technology/api/useResearchTechnology.ts
  - apps/mobile/src/features/technology/state/technologySelectionStore.ts
  - apps/mobile/src/features/technology/components/TechnologyNodeCard.tsx
  - apps/mobile/src/features/technology/components/TechnologyCategorySection.tsx
  - apps/mobile/src/features/technology/components/TechnologyDetailSheet.tsx
  - apps/mobile/src/features/technology/components/CategoryJumpStrip.tsx
  - apps/mobile/app/technology.tsx
  - apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx
  - apps/mobile/__tests__/technology-tree.test.tsx
  - apps/mobile/__tests__/technology-cta.test.tsx
autonomous: true
requirements: [REQ-05, REQ-06]

must_haves:
  truths:
    - "The technology tree screen renders every category and shows locked, available, in-progress and completed distinctly by shape, not by colour alone"
    - "A dependency is visible at three depths: tier position, an on-card prerequisite caption, and category-labelled chips in the detail sheet"
    - "The CTA renders exactly one of six mutually exclusive states, all decided from the server snapshot"
    - "Every interactive element clears 44x44pt"
  artifacts:
    - path: "apps/mobile/src/features/technology/components/TechnologyNodeCard.tsx"
      provides: "ROADMAP criterion 5 — the four states rendered distinctly"
      contains: "accessibilityRole"
    - path: "apps/mobile/__tests__/technology-cta.test.tsx"
      provides: "One named test per CTA state plus the snapshot-affordability rule"
      contains: "technology.cannot_afford"
  key_links:
    - from: "apps/mobile/app/technology.tsx"
      to: "apps/mobile/src/features/technology/api/useTechnologyQuery.ts"
      via: "the screen reads the server tree"
      pattern: "useTechnologyQuery"
    - from: "apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx"
      to: "apps/mobile/app/technology.tsx"
      via: "the Academy's Open Research button pushes /technology"
      pattern: "/technology"
---

<objective>
Build the technology tree screen and its detail sheet, exactly as
`.planning/phases/10-technology-research/10-UI-SPEC.md` specifies.

That contract is **approved, 6/6 dimensions, after one revision round**. It is
unusually concrete — it names the layout strategy, every state's border and badge, the
six-way CTA precedence, the token for every colour, and the glyph for every icon. Treat
it as the specification, not as advice. Where this plan and the UI-SPEC disagree, the
UI-SPEC wins and the discrepancy goes in the SUMMARY.

Purpose: ROADMAP criterion 5.
</objective>

<context>

<interfaces>
Phase 09 shipped the patterns this screen extends. Read the real files.

```ts
// apps/mobile/src/shared/components/buildingIcons.ts — the glyph-map pattern to copy
export const BUILDING_ICONS: Record<string, GlyphName> = { ... };
export const UNMAPPED_BUILDING_ICON: GlyphName = 'home-city-outline';
export const COST_SHORTFALL_ICON: GlyphName = 'alert-circle-outline';
export function buildingIcon(code: string): GlyphName;
```

```ts
// apps/mobile/src/shared/components/Timer.tsx
export function formatDuration(ms: number): string;   // HH:MM:SS
export function Timer({ targetTimestamp, onFinish, ... });  // 1Hz, plain update
// apps/mobile/src/shared/components/ResourceCounter.tsx
export function formatResourceAmount(n: number): string;
export function formatResourceCost(cost, translate): string;
// apps/mobile/src/shared/components/resourceIcons.ts
export const RESOURCE_ICONS, RESOURCE_KEYS;
```

`useUpgradeBuilding.ts` is the mutation template: `useMutation` + `onSettled`
invalidation, never `onSuccess`, never an optimistic cache patch.

`ConstructionQueueStrip.tsx` is the progress-bar template: 1 Hz `setInterval` cleared on
unmount, server-skew-corrected deadline, plain update with no animated roll.

`citySelectionStore.ts` is the selection-store template (Zustand, stores only the
selected id; the entity is derived from the query cache).

**The server contract 10-03 produces** (confirm against the regenerated
`packages/contracts/src/generated/api.ts` before writing against it — do not code to
this summary):

```
GET /game/technologies -> data.technologies[]: {
  code, name_key, description_key, category, level, max_level,
  next_level: { cost, research_time_seconds, requirements[], effects[] } | null,
  state: "locked" | "available" | "in_progress" | "completed"
}
data.research: { technology_code, target_level, started_at, finishes_at } | null
data.server_time
POST /game/technologies/{code}/research
```

**`state` is computed server-side.** Render it; do not re-derive it. The UI-SPEC's
condition table describes what the server means by each state, not a client computation
to duplicate — the one exception is the CTA's `affordable`/`busyElsewhere` derivation,
which is genuinely client-side because it depends on the city snapshot.
</interfaces>

<canonical_refs>
- `.planning/phases/10-technology-research/10-UI-SPEC.md` — sections A (entry point), B (screen), C (node card states), D (CTA precedence), E (cost/requires/duration/effect rows), Layout Strategy, Assets, and the five Flagged Assumptions.
- `.planning/phases/09-buildings-construction/09-05-SUMMARY.md` — the CTA grammar and the "affordability from the server snapshot alone" rule this inherits.
</canonical_refs>

<hard_constraint_art>
Generated art is BLOCKED — both image routes are billing-blocked (STATE.md, verified
2026-09-05). Category icons are `MaterialCommunityIcons` vector glyphs. Do not call any
image API. Every glyph name must be verified present in the installed glyph map before
use; the UI-SPEC's checker already verified the names it lists.
</hard_constraint_art>

<trap>
**Affordability comes from `city.resources.current`, never from the interpolated
resource bar.** Phase 09 established this and pinned it with a test that raises capacity
and rate while `current` stays short. The bar ticks between polls; letting it decide
offers a button the server is about to refuse. Repeat that test here.

**Do not wrap the test suite in a `QueryClientProvider`.** Phase 09's convention is to
`jest.mock` the hook. And note the Jest hoisting rule that bit 09-05: a variable
referenced inside a `jest.mock` factory must be `mock`-prefixed.
</trap>
</context>

<tasks>

<task type="auto" tdd="false">
  <name>Task 1: Category glyphs, the query, the mutation and the selection store</name>

  <read_first>
    - apps/mobile/src/shared/components/buildingIcons.ts (the map + fallback + accessor pattern to copy exactly)
    - apps/mobile/src/features/city/api/useCityQuery.ts (query key convention, options)
    - apps/mobile/src/features/city/api/useUpgradeBuilding.ts (the mutation template, including the onSettled comment)
    - apps/mobile/src/features/city/state/citySelectionStore.ts (the store template)
    - .planning/phases/10-technology-research/10-UI-SPEC.md § Assets (the category icon map it specifies)
    - packages/contracts/src/generated/api.ts (the real generated types from 10-03)
  </read_first>

  <files>
    apps/mobile/src/shared/components/technologyIcons.ts,
    apps/mobile/src/features/technology/api/useTechnologyQuery.ts,
    apps/mobile/src/features/technology/api/useResearchTechnology.ts,
    apps/mobile/src/features/technology/state/technologySelectionStore.ts
  </files>

  <behavior>
    - Every one of the eight categories maps to a distinct, verified glyph
    - An unmapped category falls back to an explicit sentinel rather than crashing or rendering nothing
    - `useTechnologyQuery` reads `GET /game/technologies` under the `['game','technology']` key
    - `useResearchTechnology` posts the research and, on settle, invalidates both `['game','technology']` and `['game','city']`
    - The selection store holds only the selected technology code
  </behavior>

  <action>
**1a. `technologyIcons.ts`** — copy `buildingIcons.ts`'s structure exactly: a typed
`Record<string, GlyphName>`, an explicit `UNMAPPED_TECHNOLOGY_ICON`, and a
`technologyIcon(category: string): GlyphName` accessor. Use the glyphs the UI-SPEC's
Assets section names (its checker verified each against the installed glyph map):
`economy → sack`, `military → sword-cross`, `defense → shield`,
`logistics → truck-fast`, `construction → hammer-wrench`,
`exploration → compass-outline`, `alliance → account-group-outline`,
`siege → target`. Fallback `help-circle-outline`.

Carry over the doc-comment discipline: say *why* a category reuses a glyph family and
note that falling back silently would reopen the colourblind-safety gap the map exists
to close — the same reasoning `buildingIcons.ts` records.

**1b. `useTechnologyQuery.ts`** — mirror `useCityQuery.ts`. Query key
`['game','technology']`. Return type from the generated contract, not hand-written.

**1c. `useResearchTechnology.ts`** — mirror `useUpgradeBuilding.ts` exactly:

```ts
return useMutation({
  mutationFn: (technologyCode: string) =>
    apiRequest<{ research: ResearchOrder }>(
      `/game/technologies/${encodeURIComponent(technologyCode)}/research`,
      { method: 'POST' },
      { authenticated: true },
    ),
  onSettled: () => {
    queryClient.invalidateQueries({ queryKey: ['game', 'technology'] });
    queryClient.invalidateQueries({ queryKey: ['game', 'city'] });
  },
});
```

Both invalidations are required: a completed research changes `resources.rate`, so the
city snapshot is stale too. `onSettled` not `onSuccess`, for the reason
`useUpgradeBuilding` documents — a refusal means the server moved without us.

**1d. `technologySelectionStore.ts`** — Zustand, mirroring `citySelectionStore`:
`selectedTechnology: string | null`, `selectTechnology(code)`, `clearSelection()`.
  </action>

  <acceptance_criteria>
    - `grep -c "export function technologyIcon" apps/mobile/src/shared/components/technologyIcons.ts` is 1
    - All eight categories appear as keys in technologyIcons.ts
    - `grep -c "UNMAPPED_TECHNOLOGY_ICON" apps/mobile/src/shared/components/technologyIcons.ts` is ≥ 2 (declaration and use in the fallback)
    - `grep -c "invalidateQueries" apps/mobile/src/features/technology/api/useResearchTechnology.ts` is 2
    - `grep -c "onSuccess" apps/mobile/src/features/technology/api/useResearchTechnology.ts` is 0
    - `grep -c "'game', 'city'" apps/mobile/src/features/technology/api/useResearchTechnology.ts` is 1
    - `npm run typecheck && npm run lint` clean
  </acceptance_criteria>

  <verify>
    <automated>npm run typecheck &amp;&amp; npm run lint</automated>
  </verify>

  <done>
    The data layer and the glyph vocabulary exist, following the Phase 09 conventions
    exactly rather than inventing parallel ones.
  </done>
</task>

<task type="auto" tdd="false">
  <name>Task 2: The node card, the category section, the jump strip and the screen</name>

  <read_first>
    - .planning/phases/10-technology-research/10-UI-SPEC.md §§ Layout Strategy, B, C (read these three in full — they are the specification)
    - apps/mobile/src/features/city/components/CitySlot.tsx (the card grammar: Pressable, icon, label, Badge level pip, conditional Timer)
    - apps/mobile/src/features/city/components/ConstructionQueueStrip.tsx (the 1 Hz progress bar and skew correction to reuse)
    - apps/mobile/app/(tabs)/military.tsx (the header row with useSafeAreaInsets, the Skeleton loading branch, the errorKey() error branch to mirror)
    - apps/mobile/app/gallery.tsx (a pushed non-tab route's placement and structure)
  </read_first>

  <files>
    apps/mobile/src/features/technology/components/TechnologyNodeCard.tsx,
    apps/mobile/src/features/technology/components/TechnologyCategorySection.tsx,
    apps/mobile/src/features/technology/components/CategoryJumpStrip.tsx,
    apps/mobile/app/technology.tsx
  </files>

  <behavior>
    - A node card is 96x120pt, `radius.md`, one `Pressable` with `accessibilityRole="button"`
    - The four states differ per the UI-SPEC's table: locked is dashed with a lock badge; available is solid with no badge; in-progress is solid with a clock badge and a 4pt progress bar; completed is solid with a check badge
    - The category icon is `text.secondary` in every state — no state is signalled by colour
    - Categories stack vertically; each category's tiers scroll horizontally
    - The jump strip scrolls the outer view to a tapped category's measured offset
    - Every jump-strip chip and every node card clears 44x44pt
    - Loading renders skeletons; a fetch error renders retry copy and a retry button
  </behavior>

  <action>
Implement §§ B, C and Layout Strategy of the UI-SPEC verbatim. The specification is
detailed enough to build from directly; the notes below are the parts most easily got
wrong.

**`TechnologyNodeCard`.** The state comes from the server's `state` field. Map it to the
UI-SPEC's border/badge/bar table. Card content top to bottom: category icon
(`theme.spacing.xl`, `text.secondary`), name (`label`, `numberOfLines={1}`), a
`Badge variant="neutral"` reading `"{level}/{max_level}"`, then **exactly one** of the
`→ {prerequisite}` caption (when the technology has a prerequisite and is not
in-progress) or a live `Timer` (in-progress only). Never both — the prerequisite hint is
moot once the technology is running.

96x120pt already clears 44x44pt natively; no `hitSlop` needed on the card.

**Progress bar.** Reuse `ConstructionQueueStrip`'s mechanism exactly — read that file
and copy the skew correction (`Date.now() - Date.parse(serverTime)`), the 1 Hz
`setInterval` cleared on unmount, the `finishesMs === startedMs` guard, and the
`clampPercent` helper. Do not invent a second progress-bar implementation; if the logic
is genuinely identical, extract it to a shared helper and use it from both, and say so
in the SUMMARY.

**`TechnologyCategorySection`.** A `Text variant="heading"` header plus one horizontal
`ScrollView` per tier. Nesting a horizontal `ScrollView` inside the screen's vertical one
is the whole point of the chosen layout — a tier's node count is never bounded by the
390pt viewport because it scrolls rather than wraps.

**`CategoryJumpStrip`.** Eight chips, icon + label, horizontal `ScrollView`. Tapping one
calls `scrollTo` on the outer `ScrollView` using that section's `onLayout`-measured `y`.
**Each chip needs an explicit `minHeight: theme.minTouchTarget` plus `hitSlop`** — the
UI-SPEC calls this out specifically because `__tests__/touch-targets.test.tsx` exercises
`Button` and `BottomSheet` directly and will not catch an undersized bespoke `Pressable`.

**`app/technology.tsx`.** A pushed non-tab route. Header row built with
`useSafeAreaInsets()`, an icon-only back `Pressable` (glyph `arrow-left`, `hitSlop` to
44x44pt, `accessibilityLabel={t('technology.back_accessible')}`,
`onPress={() => router.back()}`), and a `Text variant="heading"` title —
**not `display`**; the UI-SPEC's typography section is explicit that matching
`military.tsx`'s `display` title would put five font sizes on this screen.

Loading and error branches mirror `military.tsx`'s existing ones; read that file and
reuse its `errorKey()` helper shape rather than writing a third variant.
  </action>

  <acceptance_criteria>
    - `grep -c "accessibilityRole=\"button\"" apps/mobile/src/features/technology/components/TechnologyNodeCard.tsx` is ≥ 1
    - `grep -c "theme.minTouchTarget" apps/mobile/src/features/technology/components/CategoryJumpStrip.tsx` is ≥ 1
    - `grep -c "hitSlop" apps/mobile/src/features/technology/components/CategoryJumpStrip.tsx` is ≥ 1
    - `grep -c "variant=\"display\"" apps/mobile/app/technology.tsx` is 0
    - `grep -c "variant=\"heading\"" apps/mobile/app/technology.tsx` is ≥ 1
    - `grep -c "dashed" apps/mobile/src/features/technology/components/TechnologyNodeCard.tsx` is ≥ 1 — the locked state's border
    - `grep -c "text.secondary" apps/mobile/src/features/technology/components/TechnologyNodeCard.tsx` is ≥ 1 and `grep -c "theme.color.danger" apps/mobile/src/features/technology/components/TechnologyNodeCard.tsx` is 0
    - `grep -cE "clearInterval" apps/mobile/src/features/technology/components/TechnologyNodeCard.tsx` is ≥ 1 or the shared progress helper is imported — the interval must be cleaned up
    - `npm run typecheck && npm run lint` clean
  </acceptance_criteria>

  <verify>
    <automated>npm run typecheck &amp;&amp; npm run lint</automated>
  </verify>

  <done>
    The tree renders as vertically stacked categories of horizontally scrolling tiers,
    with four shape-distinct node states and a working category jump strip, all clearing
    44x44pt.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: The detail sheet, the Academy entry point, and the tests that pin all of it</name>

  <read_first>
    - .planning/phases/10-technology-research/10-UI-SPEC.md §§ A, D, E (the entry point, the six-way CTA precedence table, and the cost/requires/duration/effect rows)
    - apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx (the sheet this mirrors, and the file Task 3 also edits for the Academy button)
    - apps/mobile/__tests__/building-upgrade.test.tsx (the suite shape to copy: mock-prefixed jest.mock state, renderSheet helper, the source-assertion test)
    - apps/mobile/__tests__/city-scene.test.tsx (the mock set and the configure({defaultIncludeHiddenElements:true}) artifact)
  </read_first>

  <files>
    apps/mobile/src/features/technology/components/TechnologyDetailSheet.tsx,
    apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx,
    apps/mobile/__tests__/technology-tree.test.tsx,
    apps/mobile/__tests__/technology-cta.test.tsx
  </files>

  <behavior>
    - The sheet renders exactly one of the six CTA states, in the UI-SPEC's precedence order
    - Affordability is decided from `city.resources.current` alone
    - A prerequisite chip names the prerequisite AND its category — the only way a cross-category dependency is visible
    - The Academy's detail sheet gains an "Open Research" button; no other building has one
    - On a mutation error the sheet stays open and renders localized non-red copy
  </behavior>

  <action>
**3a. `TechnologyDetailSheet`.** Implement §D's six-way precedence exactly:

1. `isMax` → `Badge variant="neutral" label={t('technology.max_level')}`, no button.
2. `isThisTechActive` → `Timer` only, no button.
3. `isLocked` → disabled secondary, `t('technology.locked')`.
4. `busyElsewhere` → disabled secondary, `t('technology.busy')`.
5. `!affordable` → disabled secondary, `t('technology.cannot_afford')`, plus
   `COST_SHORTFALL_ICON` on each short resource's chip.
6. else → enabled primary, `t('technology.research')`, with
   `accessibilityLabel={t('technology.research_accessible', { technology: t(tech.name_key) })}`.

In-flight: state 6's title swaps to `t('technology.researching')` and `disabled` becomes
true. No spinner, no new `Button` prop.

§E's rows above the CTA (states 3–6 only): the requires row with
`{name} · {category}` chips and a `check-circle-outline` prefix when satisfied; the cost
row reusing `formatResourceCost` and `RESOURCE_ICONS`; the duration row using the
exported `formatDuration`; the effect row. All `text.secondary` — never `danger`, never
`success`, because these are informational, not confirmations or refusals.

**The requires chip is load-bearing.** The UI-SPEC's checker flagged that tier stacking
shows only *within-category* dependencies, so a cross-category prerequisite is invisible
on the tree itself. The category label on this chip is the entire mechanism by which a
player learns that a siege technology depends on a construction one. Do not drop the
category from the chip to save space.

**3b. The Academy entry point** in `CitySlotDetailSheet.tsx` — §A, verbatim:

```tsx
{building.code === 'academy' && (
  <Button variant="secondary" title={t('city.open_research')} onPress={() => router.push('/technology')} />
)}
```

Present at every Academy level, absent for every other building. No accessibility-label
override — "Open Research" is self-describing as visible text.

**3c. `technology-cta.test.tsx`** — copy `building-upgrade.test.tsx`'s structure exactly,
including `configure({ defaultIncludeHiddenElements: true })`, the mock set, and the
`mock`-prefixed jest.mock state (a factory may not close over a non-`mock`-prefixed
variable — this cost time in 09-05).

One test per CTA state, named for the state, plus:
- *"refuses on the snapshot, not the ticking bar"* — `current` short while `capacity` and
  `rate` are large; assert `technology.cannot_afford` and that re-rendering with a larger
  capacity and rate does not change the CTA.
- *"says locked before it says busy"* and *"says max level before anything else"* — the
  precedence pairs.
- *"swaps the label while the mutation is in flight"*.
- *"keeps the sheet open and renders localized copy on a server refusal"*.
- *"names the category of a cross-category prerequisite"* — a prerequisite in a different
  category renders a chip containing both the prerequisite name and its category label.
- *"never colours an ordinary blocker as danger"* — a source assertion via `readFileSync`,
  the same technique `building-upgrade.test.tsx` uses: assert the file contains neither
  `theme.color.danger` nor `variant="danger"` nor `interpolateResources`.

**3d. `technology-tree.test.tsx`** — renders the screen with a stubbed query:
- *"renders every category section"* — all eight headers present.
- *"renders the four node states distinctly"* — assert the locked card renders the lock
  badge, in-progress renders a timer string matching `/^\d{2}:\d{2}:\d{2}$/`, completed
  renders the check badge, and available renders none of those three.
- *"gives every jump chip a 44pt touch target"* — assert each chip's resolved style has
  `minHeight` ≥ 44, the assertion `touch-targets.test.tsx` cannot make for a bespoke
  Pressable.
- *"opens the detail sheet for the tapped technology"* — press a card, assert the store
  holds that code.
- *"offers Open Research only on the Academy"* — render `CitySlotDetailSheet` with an
  academy building and assert `city.open_research` is present; render with a farm and
  assert it is absent.
  </action>

  <acceptance_criteria>
    - `grep -c "technology.max_level\|technology.locked\|technology.busy\|technology.cannot_afford\|technology.research\b" apps/mobile/src/features/technology/components/TechnologyDetailSheet.tsx` is ≥ 5 — all six states present
    - `grep -c "theme.color.danger" apps/mobile/src/features/technology/components/TechnologyDetailSheet.tsx` is 0
    - `grep -c "variant=\"danger\"" apps/mobile/src/features/technology/components/TechnologyDetailSheet.tsx` is 0
    - `grep -c "interpolateResources" apps/mobile/src/features/technology/components/TechnologyDetailSheet.tsx` is 0
    - `grep -c "city.open_research" apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx` is 1
    - `grep -c "academy" apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx` is ≥ 1
    - `grep -c "  it(" apps/mobile/__tests__/technology-cta.test.tsx` is ≥ 10
    - `grep -c "  it(" apps/mobile/__tests__/technology-tree.test.tsx` is ≥ 5
    - `grep -c "mockResearchState\|mockMutate" apps/mobile/__tests__/technology-cta.test.tsx` is ≥ 2 — the jest hoisting rule respected
    - `npm test` reports 16 suites green (14 baseline + the 2 new), with no previously passing test deleted
    - `npm run typecheck && npm run lint` exit 0
    - `git diff --stat apps/mobile/src/shared/components/Button.tsx apps/mobile/src/shared/components/Badge.tsx apps/mobile/src/shared/components/BottomSheet.tsx` prints nothing — zero shared-component diffs, as the UI-SPEC promises
  </acceptance_criteria>

  <verify>
    <automated>npm run typecheck &amp;&amp; npm run lint &amp;&amp; npm test</automated>
  </verify>

  <done>
    The detail sheet renders one of six server-decided CTA states, cross-category
    prerequisites are visible and category-labelled, the Academy is the way in, and
    every state and the snapshot-affordability rule is pinned by a named test.
  </done>
</task>

</tasks>

<verification>
- `npm test` — 16 suites green
- `npm run typecheck && npm run lint` — clean
- No diff in Button.tsx, Badge.tsx or BottomSheet.tsx
- Every glyph name used exists in the installed MaterialCommunityIcons map
- No image-generation API was called; category icons are vector glyphs
- The four node states and the six CTA states each have a named test
</verification>
