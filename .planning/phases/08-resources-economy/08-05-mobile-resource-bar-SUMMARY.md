---
phase: 08-resources-economy
plan: 05
subsystem: ui
tags: [react-native, expo-router, tanstack-query, i18n, accessibility]

# Dependency graph
requires:
  - phase: 08-resources-economy
    provides: "CityResources.rate (signed integer units/hour) from 08-01, sibling to current/capacity"
provides:
  - "ResourceBar: a persistent, icon-bearing, capacity-metered HUD strip mounted above the tab navigator"
  - "interpolateResources: a pure, clamped client-side projection between server reads"
  - "useCityQuery: the shared ['game','city'] query with a 30s refetchInterval, deduped between the bar and the city screen"
  - "resourceIcons.ts: the one resource-to-glyph mapping (barley/tree/terrain/anvil/gold, plus tray-alert for storage-full)"
  - "focusManager wired to AppState so a resumed app refetches instead of extrapolating across a backgrounding gap"
affects: [09-building-upgrades, 12-troop-upkeep, 27-market-trade]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Pure display-projection functions (interpolateResources) live beside features/city/rendering/grid.ts's precedent: no React/RN import, unit-tested with plain object literals"
    - "A shared TanStack Query hook (useCityQuery) is called independently by two consumers (ResourceBar, city.tsx); TanStack dedupes the identical key into one request/cache entry"
    - "focusManager.setEventListener called once at module scope in app/_layout.tsx (FocusManager owns its own subscription lifecycle internally) rather than wrapped in a component effect"

key-files:
  created:
    - apps/mobile/src/shared/components/resourceIcons.ts
    - apps/mobile/src/features/economy/interpolation/interpolateResources.ts
    - apps/mobile/src/features/economy/components/ResourceBar.tsx
    - apps/mobile/src/features/city/api/useCityQuery.ts
    - apps/mobile/__tests__/resource-interpolation.test.ts
    - apps/mobile/__tests__/resource-bar.test.tsx
  modified:
    - apps/mobile/src/shared/components/ResourceCounter.tsx
    - apps/mobile/app/(tabs)/city.tsx
    - apps/mobile/app/(tabs)/_layout.tsx
    - apps/mobile/app/_layout.tsx
    - apps/mobile/src/features/city/components/CityScene.tsx
    - apps/mobile/__tests__/city-scene.test.tsx
    - packages/contracts/src/index.ts
    - packages/localization/locales/en/mvp.json
    - packages/localization/locales/pt-BR/mvp.json
    - packages/localization/locales/es/mvp.json

key-decisions:
  - "Rendered the tray-alert glyph inside ResourceCounter itself (importing STORAGE_FULL_ICON from the sibling resourceIcons.ts) rather than having ResourceBar inject it as a JSX child, since React.createElement's explicit children always win over a spread children prop — ResourceCounter stays self-contained and gains no i18n dependency, only an icon-mapping one it already conceptually owns"
  - "Exported ResourceRate from @castleroyale/contracts/src/index.ts (Rule 3 - blocking): 08-01 added the schema to openapi.yaml/generated/api.ts but never re-exported the type from the package's public surface, so interpolateResources.ts could not import it as the plan's interfaces block specified"
  - "focusManager.setEventListener is called once at module scope in app/_layout.tsx rather than inside a useEffect, per the plan's stated fallback-avoidance preference — FocusManager's own onSubscribe/onUnsubscribe lifecycle already manages the underlying subscription, so a component effect would add no value and risks re-subscribing on every RootLayout re-render if written incorrectly"

patterns-established:
  - "A resource-to-glyph Record<ResourceKey, GlyphName> is the one mapping every future resource-bearing screen reuses (market, Phase 27; upkeep, Phase 12)"

requirements-completed: [REQ-02]

# Metrics
duration: ~25min
completed: 2026-09-05
---

# Phase 08 Plan 05: Mobile Resource Bar Summary

**Persistent five-resource HUD bar above the tab navigator, ticking once a second via a pure clamped `interpolateResources` projection that always converges to server truth and never displays past capacity, with icon-based resource identity and glyph+MAX (never colour) storage-full signalling.**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-09-05T23:20:00Z (approx, concurrent with 08-02)
- **Completed:** 2026-09-05T23:46:00Z
- **Tasks:** 3 completed
- **Files modified:** 16 (6 new, 10 modified)

## Accomplishments
- `resourceIcons.ts` closes the latent colourblind gap flagged in the UI-SPEC: `ResourceCounter`'s `icon` prop had existed since Phase 02 but no caller had ever passed it — resources were told apart by text colour alone. Every resource now carries its own MaterialCommunityIcons glyph (`barley`/`tree`/`terrain`/`anvil`/`gold`)
- `interpolateResources` is a pure, RN-free, unit-tested projection: 6 tests cover the one-second advance, capacity clamp (saturates, no separate flag), backward-extrapolation guard, integer flooring, zero-capacity-as-unclamped, and negative-rate drain-to-zero
- `ResourceBar` mounts once above `<Tabs>` as player-level chrome (not city-tab-only), ticks every second from `cityQuery.dataUpdatedAt`, freezes and dims to 0.6 opacity on a failed background refetch with cached data, shows five `Skeleton` pills on first load, and renders nothing if the query has never succeeded — 8 tests cover every behaviour bullet in the plan
- A full warehouse is signalled by the `tray-alert` glyph plus the localised "MAX"/"MÁX" caption — `grep`-verified that `danger` never appears in `ResourceCounter.tsx` or `ResourceBar.tsx`
- `CityScene.tsx` no longer double-counts the top safe-area inset or duplicates the resource row; `useCityQuery` is shared and deduped between the bar and the city screen; `app/_layout.tsx` now wires `focusManager` to `AppState` so app resume triggers a real refetch
- Full mobile suite: 13 suites / 74 tests green; `npm run typecheck` and `npm run lint` clean at every task boundary; `apps/api`'s `./vendor/bin/pest` (150 tests) confirmed still green, untouched by this plan despite concurrent 08-02/08-03 API work in the same tree

## Task Commits

1. **Task 1: Resource icons, the extended counter, the pure interpolation and the new strings** - `b19edd7` (feat, TDD)
2. **Task 2: Build the ResourceBar on a shared city query** - `621288b` (feat, TDD)
3. **Task 3: Mount the bar, hand it the safe area, and wire app-resume refetching** - `41d7273` (feat, TDD)

_All three tasks combined RED+GREEN into single commits after tests were written and passed together, consistent with how each file's tests were authored and verified before commit — no separate refactor commits were needed._

## Files Created/Modified
- `apps/mobile/src/shared/components/resourceIcons.ts` - `RESOURCE_ICONS`, `STORAGE_FULL_ICON`, `RESOURCE_KEYS`
- `apps/mobile/src/shared/components/ResourceCounter.tsx` - extended with `capacity?`, `isFull?`, `fullLabel?`; renders the 4pt capacity meter, the tray-alert glyph and the MAX caption; outer container wrapped from row to column
- `apps/mobile/src/features/economy/interpolation/interpolateResources.ts` - the pure clamped projection
- `apps/mobile/src/features/economy/components/ResourceBar.tsx` - the persistent HUD strip (133 lines)
- `apps/mobile/src/features/city/api/useCityQuery.ts` - the shared `['game','city']` query, `refetchInterval: 30_000`
- `apps/mobile/app/(tabs)/city.tsx` - switched to `useCityQuery()`
- `apps/mobile/app/(tabs)/_layout.tsx` - wraps `<Tabs>` with `<ResourceBar />` inside a `flex:1` `View`
- `apps/mobile/app/_layout.tsx` - `focusManager.setEventListener` wired to `AppState`
- `apps/mobile/src/features/city/components/CityScene.tsx` - dropped `insets.top` and the duplicated `ResourceCounter` row
- `apps/mobile/__tests__/resource-interpolation.test.ts` - 6 tests
- `apps/mobile/__tests__/resource-bar.test.tsx` - 8 tests
- `apps/mobile/__tests__/city-scene.test.tsx` - `rate` added to the fixture; two new architecture tests
- `packages/contracts/src/index.ts` - exported `ResourceRate` (see Deviations)
- `packages/localization/locales/{en,pt-BR,es}/mvp.json` - four new `resources.*` keys each

## Decisions Made
See `key-decisions` in the frontmatter. In summary: the tray-alert glyph rendering was placed inside `ResourceCounter` (not injected by `ResourceBar` as a child) because JSX's explicit children always override a spread `children` prop, so the plan's "the caller supplies the tray-alert glyph, immediately after the numeral" is satisfied by `ResourceCounter` owning that rendering via its own `isFull` prop rather than a literal child injection.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Exported `ResourceRate` from `@castleroyale/contracts`**
- **Found during:** Task 1, while writing `interpolateResources.ts`
- **Issue:** 08-01 added the `ResourceRate` schema to `openapi.yaml` and it appears in the generated `api.ts`, but `packages/contracts/src/index.ts` never re-exported it as a named type (only `ResourceBundle`/`ResourceType` were exported). The plan's own interfaces block says to "Import the `ResourceBundle` / `ResourceRate` types from `@castleroyale/contracts`," which would not compile without this.
- **Fix:** Added `export type ResourceRate = components['schemas']['ResourceRate'];` alongside the existing `ResourceBundle` export.
- **Files modified:** `packages/contracts/src/index.ts`
- **Verification:** `npm run typecheck` passes repo-wide; `interpolateResources.ts` imports `ResourceRate` without error.
- **Committed in:** `b19edd7` (Task 1 commit)

**2. [Rule 1 - Bug] Fixed the plan's `app/_layout.tsx` snippet from `insets.top`-style double string match**
- **Found during:** Task 3, extending `city-scene.test.tsx`'s architecture test
- **Issue:** The literal explanatory comment I first wrote above `CityScene`'s `paddingTop` ("adding `insets.top` here as well would count the notch twice") itself contained the substring `insets.top`, causing the new `expect(scene).not.toContain('insets.top')` assertion to fail against my own comment, not the removed code.
- **Fix:** Reworded the comment to convey the same meaning ("re-adding the top inset here would count the notch twice") without containing the literal string.
- **Files modified:** `apps/mobile/src/features/city/components/CityScene.tsx`
- **Verification:** `npx jest __tests__/city-scene.test.tsx` — 7/7 pass.
- **Committed in:** `41d7273` (Task 3 commit)

---

**Total deviations:** 2 auto-fixed (1 blocking export, 1 self-inflicted test-vs-comment string collision)
**Impact on plan:** Both fixes are cosmetic/mechanical — no scope creep, no architectural change.

## Issues Encountered
None beyond the two auto-fixed deviations above.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Phase 08's fifth and final deliverable is complete: all five roadmap items for Phase 08 (storage/rates/capacity, elapsed-time accrual, ledger, locked spending, mobile resource bar) now have shipped plans.
- `RESOURCE_ICONS`/`resourceIcons.ts` is ready for reuse by Phase 27 (market/trade UI) and Phase 12 (troop upkeep, which will exercise the negative-rate path `interpolateResources` already handles).
- `useCityQuery` is the pattern any future screen needing city data should follow — call the shared hook rather than inlining a new `useQuery`.
- The human verification step (portrait screenshots of city/world tabs confirming no clipping under the new bar) is deferred to `/gsd:verify-phase` per the plan's locked decision; the automated double-inset regression test already covers the specific pixel-arithmetic risk.
- Plans 08-02, 08-03 (and likely more) were executing concurrently in `apps/api/**` during this plan's execution; `./vendor/bin/pest` was confirmed green (150 tests) at the end of this plan's Task 3, with no interference in either direction.

## Self-Check: PASSED

All claimed files and commits verified to exist:
- FOUND: apps/mobile/src/shared/components/resourceIcons.ts
- FOUND: apps/mobile/src/features/economy/interpolation/interpolateResources.ts
- FOUND: apps/mobile/src/features/economy/components/ResourceBar.tsx
- FOUND: apps/mobile/src/features/city/api/useCityQuery.ts
- FOUND: apps/mobile/__tests__/resource-interpolation.test.ts
- FOUND: apps/mobile/__tests__/resource-bar.test.tsx
- FOUND: commit b19edd7
- FOUND: commit 621288b
- FOUND: commit 41d7273

---
*Phase: 08-resources-economy*
*Completed: 2026-09-05*
