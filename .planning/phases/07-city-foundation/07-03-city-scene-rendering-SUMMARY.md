---
phase: 07-city-foundation
plan: 03
subsystem: mobile-ui
tags: [react-native, expo, zustand, i18n, accessibility, jest]

# Dependency graph
requires:
  - phase: 07-02-city-state-slots-api
    provides: "CityData.slots — fixed-length CitySlot[] roster, always returned in full, in stable roster order"
provides:
  - "computeSlotLayout(): pure, count-agnostic grid math enforcing the 44pt touch floor"
  - "CityScene: onLayout-measured, edge-to-edge tappable grid with pull-to-refresh"
  - "CitySlot / CitySlotDetailSheet: one accessible Pressable per plot, empty or occupied, no upgrade CTA"
  - "citySelectionStore: minimal Zustand store holding only the selected slot id"
  - "Nine new city.* / errors.* localization keys in en, pt-BR, es"
affects: [07-04-city-scene-art-realtime, 09-construction]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Pure rendering math module with no React/RN import (rendering/grid.ts), mirrored from features/world/rendering/cull.ts, unit-tested in isolation"
    - "Selection-identity-only Zustand store (citySelectionStore), mirrored from features/world/state/cameraStore.ts — server data stays in TanStack Query, never duplicated into client state"
    - "Skew-corrected Construction deadline (Date.parse(finishes_at) + (Date.now() - Date.parse(server_time))) computed once by the container and passed down as a plain timestamp, never trusting the device clock"

key-files:
  created:
    - apps/mobile/src/features/city/rendering/grid.ts
    - apps/mobile/src/features/city/state/citySelectionStore.ts
    - apps/mobile/src/features/city/components/CitySlot.tsx
    - apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx
    - apps/mobile/src/features/city/components/CityScene.tsx
    - apps/mobile/__tests__/city-grid.test.ts
    - apps/mobile/__tests__/city-scene.test.tsx
  modified:
    - apps/mobile/app/(tabs)/city.tsx
    - packages/localization/locales/en/mvp.json
    - packages/localization/locales/pt-BR/mvp.json
    - packages/localization/locales/es/mvp.json

key-decisions:
  - "Upgrade CTA removed from the client entirely (Phase 09 owns it); backend /game/city/buildings/{code}/upgrade route and its MvpGameplayTest coverage untouched"
  - "Frame measured by onLayout on the scene container itself, not a hardcoded tab-bar/header constant subtraction"
  - "Ships on bg.sunken; city_ground.png art is explicitly 07-04's job, not a silent placeholder"
  - "CitySlotTileProps extended with constructionFinishTimestamp: number | null (beyond the plan's literal isBuilding: boolean signature) so the in-tile Timer has a real, skew-corrected deadline instead of no target at all"
  - "Jest queries for CityScene opt into includeHiddenElements because the reused @gorhom/bottom-sheet mock always renders with accessibilityViewIsModal regardless of open state, which otherwise makes RNTL treat every sibling (the whole grid) as hidden-behind-a-modal — a mock-only artifact, not a real BottomSheet behaviour"

patterns-established:
  - "Spatial RN-view scenes (grid.ts + measured container) as the template for any future fixed-roster tappable surface, distinct from the Skia-canvas approach used for the world map"

requirements-completed: [REQ-08]

# Metrics
duration: 13min
completed: 2026-09-05
---

# Phase 07 Plan 03: City scene rendering with tappable plots Summary

**Replaced the city tab's scrolling `Card`-per-building list with a runtime-measured, edge-to-edge grid of accessible `Pressable` plots — one per server slot, in server order, each opening a detail sheet, with pull-to-refresh and no upgrade CTA.**

## Performance

- **Duration:** ~13 min
- **Started:** 2026-09-05T17:50:19Z
- **Completed:** 2026-09-05T18:01:44Z
- **Tasks:** 3
- **Files modified:** 11 (7 created, 4 modified)

## Accomplishments

- `rendering/grid.ts` exports a pure `computeSlotLayout(frameWidth, slotCount, baseTileUnit, minTouchTarget)` that fits any server-driven slot count into a measured frame, decrementing columns until the 44pt touch floor is met (or floors at one column), verified against all seven behaviours from the plan plus the selection-store transitions (9 tests).
- `citySelectionStore` holds only `selectedSlot: string | null` — no building or slot data, exactly mirroring `cameraStore`'s shape.
- All nine new keys (`errors.CITY_NOT_OWNED`, `errors.TILE_OCCUPIED`, and seven `city.*` scene strings) landed verbatim in `en`, `pt-BR`, and `es`; `node --experimental-strip-types packages/localization/src/validate.ts` passes.
- `CitySlot` renders one accessible `Pressable` per plot: dashed `bg.sunken` + gold `plus-circle-outline` when empty, `surface.raised` + category glyph (silhouette-only, colourblind-safe) + name + level badge + in-tile `Timer` when occupied and under construction. Touch area is guaranteed ≥44pt via `hitSlop` even when the visual tile shrinks.
- `CitySlotDetailSheet` mirrors `WorldTileDetailSheet`'s `BottomSheet` contract with no loading/stale/error branches (data is already resident in the fetched `CityData`) and no upgrade button.
- `CityScene` assembles the resource header, the `onLayout`-measured grid, `RefreshControl`-driven pull-to-refresh, and the detail sheet; server slot order is never sorted, filtered or sliced.
- `apps/mobile/app/(tabs)/city.tsx` is now a thin screen: pending/error branches unchanged, success renders `<CityScene>`. The upgrade `useMutation`, the `Card`-per-building block, and `formatCost` are gone; the backend upgrade endpoint is untouched.
- `city-scene.test.tsx` proves 18 buttons render for 18 server slots (none hardcoded), every button clears the 44pt floor, pressing an empty and an occupied plot show the right sheet content with no `building.upgrade` text anywhere, and an architecture check pins `city.tsx` against ever reintroducing `city.buildings`, `<Card`, `useMutation` or `/upgrade`.

## Task Commits

Each task was committed atomically:

1. **Task 1: Pure grid math, the selection store, and the nine localization keys** - `64caafa` (feat)
2. **Task 2: The plot tile and the plot detail sheet** - `2e715f3` (feat)
3. **Task 3: Assemble the measured scene and replace the card list on the city tab** - `7ede28c` (feat)

**Plan metadata:** (this commit, following)

## Files Created/Modified

- `apps/mobile/src/features/city/rendering/grid.ts` - pure `computeSlotLayout`, no React/RN import
- `apps/mobile/src/features/city/state/citySelectionStore.ts` - Zustand store, `selectedSlot` only
- `apps/mobile/src/features/city/components/CitySlot.tsx` - one `Pressable` plot, empty/occupied states
- `apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx` - `BottomSheet`-based detail sheet, no upgrade CTA
- `apps/mobile/src/features/city/components/CityScene.tsx` - measured frame, grid assembly, pull-to-refresh
- `apps/mobile/app/(tabs)/city.tsx` - rewritten to a thin screen rendering `CityScene`
- `packages/localization/locales/en/mvp.json`, `pt-BR/mvp.json`, `es/mvp.json` - nine new keys each
- `apps/mobile/__tests__/city-grid.test.ts` - grid math + selection store tests
- `apps/mobile/__tests__/city-scene.test.tsx` - scene rendering, touch-target, sheet-content and architecture tests

## Decisions Made

- **Upgrade CTA removed, not deferred with a disabled button:** matches the plan's locked decision 1 — Phase 09 owns the CTA; the backend route and its test coverage are untouched, only the client button and its mutation left the screen.
- **Frame measured via `onLayout` on the scene container:** by the time that view is measured, the parent layout has already accounted for the safe area, resource strip and tab bar, so no tab-bar height constant is guessed or hardcoded.
- **Ships on `bg.sunken`, not a placeholder:** ground art (`city_ground.png`) is explicitly 07-04's scope, matching the plan's third locked decision.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Wired a real, skew-corrected deadline into the in-tile `Timer`**
- **Found during:** Task 2 (building `CitySlot`)
- **Issue:** The plan's `CitySlotTileProps` signature only carried `isBuilding: boolean`; the described in-tile `<Timer>` for the "living scene" would have had no `targetTimestamp` to count down to, so it would render nonsense (a countdown from `Date.now()`) rather than the actual construction deadline.
- **Fix:** Added `constructionFinishTimestamp: number | null` to `CitySlotTileProps`, computed once in `CityScene` the same way `CitySlotDetailSheet` and the pre-scene `city.tsx` already corrected for device-clock skew (`Date.parse(finishes_at) + (Date.now() - Date.parse(server_time))`), and passed down per-tile only when that tile is the one under construction.
- **Files modified:** `apps/mobile/src/features/city/components/CitySlot.tsx`, `apps/mobile/src/features/city/components/CityScene.tsx`
- **Verification:** `npm run typecheck --workspace=@castleroyale/mobile`, `npm run lint --workspace=@castleroyale/mobile`, `city-scene.test.tsx`.
- **Commit:** `2e715f3` (Task 2), `7ede28c` (Task 3, wiring)

**2. [Rule 3 - Blocking] Mocked `@expo/vector-icons` in `city-scene.test.tsx` instead of installing `expo-asset`**
- **Found during:** Task 3 (writing `city-scene.test.tsx`)
- **Issue:** Rendering `CitySlot` (which imports `@expo/vector-icons` -> `expo-font` -> `expo-asset`) under Jest failed with `Cannot find module 'expo-asset'`. Investigation showed `expo-asset` exists only nested under `apps/mobile/node_modules/expo/node_modules/expo-asset`, unreachable from `expo-font`'s own Node resolution — a pre-existing hoisting gap, not something this plan's code introduced (`_layout.tsx` already imports the same icon library and is exercised only by Metro at runtime, never by a Jest test, until now). A first attempt to fix it properly via `npm install --workspace=@castleroyale/mobile expo-asset@~57.0.15 --save` triggered a full dependency re-resolution (2114 packages removed, 439 added, a 61k-line `package-lock.json` diff) that broke `@types/react-native` compatibility across the entire mobile workspace (`tsc --noEmit` failed with dozens of "View cannot be used as a JSX component" errors). That install was reverted (`git checkout -- apps/mobile/package.json package-lock.json` + `npm ci`) to restore the verified baseline.
- **Fix:** Mocked `@expo/vector-icons` directly in `city-scene.test.tsx` (a trivial `View` stand-in), following the same precedent already established in this codebase for `@gorhom/bottom-sheet` (STATE.md, Phase 02: mocked rather than fixing the underlying reanimated/worklets setup). This is test-only; production code is untouched and the real icon library still renders in the Expo/Metro app exactly as it did in `_layout.tsx` before this plan.
- **Files modified:** `apps/mobile/__tests__/city-scene.test.tsx` (package.json/package-lock.json reverted to their pre-plan state — no net change)
- **Verification:** `npm test --workspace=@castleroyale/mobile -- --runInBand` (10 suites, 49 tests, all green); `npm run typecheck` and `npm run lint` from repo root, both clean.
- **Commit:** `7ede28c` (Task 3)

**3. [Rule 1 - Bug] Opted CityScene's Jest queries into `includeHiddenElements`**
- **Found during:** Task 3 (writing `city-scene.test.tsx`)
- **Issue:** The `@gorhom/bottom-sheet` mock (reused verbatim per the plan) always renders with `accessibilityViewIsModal` regardless of the sheet's actual `index`/open state, because the mock has no visibility logic. React Native Testing Library's default accessibility-tree walk treats every *other* host sibling of an `accessibilityViewIsModal` element as hidden — so with the sheet always "modal" in the test tree, the entire scene grid (and its `city.scene_accessibility` label) became unqueryable by default, even though the real `BottomSheet` component only sets that flag meaningfully while genuinely open.
- **Fix:** Called `configure({ defaultIncludeHiddenElements: true })` at the top of `city-scene.test.tsx`, documented inline as a mock-only artifact, not a real accessibility concern in the shipped component.
- **Files modified:** `apps/mobile/__tests__/city-scene.test.tsx`
- **Verification:** All five `city-scene.test.tsx` cases pass; the underlying components (`CitySlot`, `CityScene`, `CitySlotDetailSheet`) were not modified for this fix.
- **Commit:** `7ede28c` (Task 3)

---

**Total deviations:** 3 auto-fixed (1 missing-critical Timer wiring, 1 blocking test-environment dependency resolved via mocking after a reverted destructive install attempt, 1 test-harness accessibility-query fix)
**Impact on plan:** All three keep the shipped component behaviour exactly as specified; none touch server code, none change the plan's locked decisions. No scope creep.

## Issues Encountered

- An initial attempt to resolve the missing `expo-asset` module via `npm install` cascaded into a destructive, unrelated dependency re-resolution across the whole mobile workspace. Caught immediately by re-running `npm run typecheck` before committing; reverted cleanly with `git checkout` + `npm ci`, and re-solved with a test-only mock instead. No broken state was ever committed.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Plan 07-04 (city scene art + realtime) can drop `city_ground.png` behind the same measured `CityScene` frame and subscribe to `CityData.realtime` without touching slot layout or tap handling.
- Phase 09 (construction) has a clean seam to reintroduce the upgrade CTA inside `CitySlotDetailSheet`'s occupied branch — the sheet's structure (empty/occupied branches, construction-timer sub-branch) is already in place, only the action button is missing.
- All six quality gates are green: `./vendor/bin/pest` (139 passed, 1096 assertions, unchanged from 07-02 baseline — no server code touched), `npm run typecheck`, `npm run lint`, `npm test` (10 suites, 49 tests, up from 8/35), and `node --experimental-strip-types packages/localization/src/validate.ts`.
- No blockers.

---
*Phase: 07-city-foundation*
*Completed: 2026-09-05*

## Self-Check: PASSED

All seven created files and the modified `city.tsx` and SUMMARY.md found on disk; all three task commits (`64caafa`, `2e715f3`, `7ede28c`) found in git history.
