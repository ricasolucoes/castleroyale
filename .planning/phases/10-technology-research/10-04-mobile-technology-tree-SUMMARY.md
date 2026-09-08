---
phase: 10-technology-research
plan: 04
subsystem: ui
tags: [react-native, expo-router, tanstack-query, zustand, material-community-icons, i18n]

# Dependency graph
requires:
  - phase: 10-technology-research
    provides: "10-01's technology catalogue/translation keys, 10-03's persistence, and 10-05's GET /game/technologies (tier, prerequisites, server-computed state) and POST /game/technologies/{code}/research"
provides:
  - "apps/mobile/app/technology.tsx — the pushed technology tree screen: category sections stacked vertically, each with ascending non-empty tier lanes scrolling horizontally"
  - "TechnologyNodeCard — the four server-decided states (locked/available/in-progress/completed) distinguished purely by border style and corner-badge shape/presence, never colour"
  - "TechnologyDetailSheet — the six-way CTA precedence (max/active/locked/busy/unaffordable/enabled), cross-category prerequisite chips, and the resolved multiply-effect permille sign convention"
  - "CitySlotDetailSheet's Academy-only 'Open Research' entry point"
  - "packages/contracts/src/index.ts — re-exported Technology/TechnologyLevel/TechnologyEffect/TechnologyPrerequisite/ActiveResearch/ResearchOrder/TechnologyTreeData, which 10-05 had defined but never exposed at the package boundary"
  - "apps/mobile/src/shared/utils/serverProgress.ts — the shared 1Hz skew-corrected progress-bar helper (clampPercent/progressPercent/useTickingNow)"
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "TechnologyNodeCard/TechnologyDetailSheet/CategoryJumpStrip mirror Phase 09's CitySlot/CitySlotDetailSheet/ConstructionQueueStrip conventions exactly: Pressable + accessibilityRole=\"button\", Badge level pip, conditional Timer, useTechnologyQuery/useResearchTechnology mirroring useCityQuery/useUpgradeBuilding's query-key and onSettled-invalidation shape"
    - "serverProgress.ts extracts ConstructionQueueStrip's 1Hz skew-corrected percent-complete arithmetic (clampPercent, progressPercent, useTickingNow) into a shared helper so a second progress-bar implementation cannot silently drift; ConstructionQueueStrip itself is left on its own inline copy since it already ships tested — only the new node card imports the shared helper"
    - "technology.* / city.open_research / errors.RESEARCH_IN_PROGRESS,TECHNOLOGY_LOCKED,TECHNOLOGY_MAX_LEVEL keys added to en/pt-BR/es mvp.json, following the existing flat namespace + {placeholder} interpolation convention"

key-files:
  created:
    - apps/mobile/src/shared/components/technologyIcons.ts
    - apps/mobile/src/features/technology/api/useTechnologyQuery.ts
    - apps/mobile/src/features/technology/api/useResearchTechnology.ts
    - apps/mobile/src/features/technology/state/technologySelectionStore.ts
    - apps/mobile/src/features/technology/components/TechnologyNodeCard.tsx
    - apps/mobile/src/features/technology/components/TechnologyCategorySection.tsx
    - apps/mobile/src/features/technology/components/CategoryJumpStrip.tsx
    - apps/mobile/src/features/technology/components/TechnologyDetailSheet.tsx
    - apps/mobile/app/technology.tsx
    - apps/mobile/src/shared/utils/serverProgress.ts
    - apps/mobile/__tests__/technology-tree.test.tsx
    - apps/mobile/__tests__/technology-cta.test.tsx
  modified:
    - packages/contracts/src/index.ts
    - apps/mobile/src/i18n/useTranslation.ts
    - apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx
    - packages/localization/locales/en/mvp.json
    - packages/localization/locales/pt-BR/mvp.json
    - packages/localization/locales/es/mvp.json

key-decisions:
  - "packages/contracts/src/index.ts never re-exported Technology/TechnologyLevel/TechnologyEffect/TechnologyPrerequisite/ActiveResearch/ResearchOrder/TechnologyTreeData — 10-05 added the schemas to openapi.yaml and the generated api.ts but only the raw components['schemas'][...] path worked; added the seven named type exports (Rule 3 — blocking, this plan's data-layer files cannot import a named Technology type otherwise)"
  - "TechnologyDetailSheet.tsx was built during Task 2's work, not deferred to Task 3, because app/technology.tsx (a Task 2 file) renders it directly and cannot typecheck without it existing. Task 3 then added the Academy button and both test files against the already-complete sheet, touching TechnologyDetailSheet.tsx zero further times."
  - "The effect row's sign convention (10-UI-SPEC.md Flagged Assumption 1) is resolved per 10-01: a multiply effect's value is full permille (1000 = identity), rendered as ((value-1000)/10) percent with an explicit sign (1100 -> +10%, 900 -> -10%); an add effect renders its value directly with a sign."
  - "serverProgress.ts extracts ConstructionQueueStrip's 1Hz skew-corrected percent-complete arithmetic into a shared helper (used by the new node card) rather than hand-copying it, per the plan's explicit instruction; ConstructionQueueStrip.tsx itself was left untouched since it already ships tested — refactoring a passing Phase 09 file was judged out of this plan's scope boundary."
  - "Added technology.effect_target.{build_speed,march_speed,scout_range,siege_damage,unit_attack,unit_defense,production_iron} localization keys beyond the UI-SPEC's two representative examples (production_food/production_wood), because 10-01's actual dataset uses production.iron (not production.wood) plus six non-production targets, and the effect row needs a real key for every target the authored dataset introduces."

requirements-completed: [REQ-05, REQ-06]

# Metrics
duration: 28min
completed: 2026-09-08
---

# Phase 10 Plan 04: Mobile Technology Tree Summary

**The technology tree screen (category-stacked, tier-laned "Netflix-row" layout) and its detail sheet, with four shape-distinct node states, a six-way server-decided CTA precedence, and the Academy as the sole entry point — built directly against 10-05's live GET /game/technologies and POST /game/technologies/{code}/research contract.**

## Performance

- **Duration:** ~28 min
- **Started:** 2026-09-08T17:06:17Z (approx., following 10-05's completion)
- **Completed:** 2026-09-08T17:34:14Z
- **Tasks:** 3
- **Files modified:** 18 (12 created, 6 modified)

## Accomplishments
- `TechnologyNodeCard` renders the four server-computed states — locked (dashed border, lock badge), available (solid, no badge), in-progress (solid, clock badge, 4pt progress bar), completed (solid, check badge) — distinguished entirely by border style and corner-badge shape/presence, category icon staying `text.secondary` in every state
- `TechnologyCategorySection`/`CategoryJumpStrip` implement the Layout Strategy verbatim: categories stacked vertically, each category's non-empty tiers ascending as horizontal lanes, an 8-chip jump strip scrolling the outer view to a measured section offset, every chip clearing 44x44pt
- `TechnologyDetailSheet` implements the exact six-way CTA precedence (max level -> active -> locked -> busy elsewhere -> unaffordable -> enabled), cross-category prerequisite chips naming both the prerequisite and its category, and the resolved multiply-effect permille sign convention
- `CitySlotDetailSheet` gains the Academy-only "Open Research" button, present at every Academy level, absent everywhere else, pushing `/technology`
- `useTechnologyQuery`/`useResearchTechnology`/`technologySelectionStore` mirror Phase 09's `useCityQuery`/`useUpgradeBuilding`/`citySelectionStore` exactly, including the double `['game','technology']` + `['game','city']` invalidation on settle
- 19 new tests (13 in `technology-cta.test.tsx`, 6 in `technology-tree.test.tsx`) pin every CTA state, both precedence pairs, the in-flight label swap, the server-refusal copy, the cross-category chip, the four node states, and every jump chip's touch target; full suite reports 16 suites / 105 tests green
- `npm run typecheck && npm run lint` clean workspace-wide (mobile, contracts, game-data, localization); `npm run contracts:check` and both `gamedata:validate`/localization `validate` exit clean; zero diff on `Button.tsx`/`Badge.tsx`/`BottomSheet.tsx`; every glyph name verified present in the installed `MaterialCommunityIcons.json`

## Task Commits

Each task was committed atomically:

1. **Task 1: Category glyphs, the query, the mutation and the selection store** - `fc818dd` (feat)
2. **Task 2: The node card, the category section, the jump strip and the screen** - `80d9912` (feat)
3. **Task 3: The detail sheet, the Academy entry point, and the tests that pin all of it** - `25f5f27` (feat)

**Plan metadata:** pending (this commit)

## Files Created/Modified
- `apps/mobile/src/shared/components/technologyIcons.ts` - category-to-glyph map, sibling to `buildingIcons.ts`
- `apps/mobile/src/features/technology/api/useTechnologyQuery.ts` - `GET /game/technologies` under `['game','technology']`
- `apps/mobile/src/features/technology/api/useResearchTechnology.ts` - the research mutation, double invalidation on settle
- `apps/mobile/src/features/technology/state/technologySelectionStore.ts` - Zustand, selected technology code only
- `apps/mobile/src/shared/utils/serverProgress.ts` - the shared 1Hz skew-corrected progress helper
- `apps/mobile/src/features/technology/components/TechnologyNodeCard.tsx` - the four-state 96x120pt node card
- `apps/mobile/src/features/technology/components/TechnologyCategorySection.tsx` - one category's ascending non-empty tier lanes
- `apps/mobile/src/features/technology/components/CategoryJumpStrip.tsx` - the 8-chip jump strip
- `apps/mobile/src/features/technology/components/TechnologyDetailSheet.tsx` - the six-way CTA precedence and requires/cost/duration/effect rows
- `apps/mobile/app/technology.tsx` - the pushed tree screen
- `apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx` - Academy-only "Open Research" button
- `packages/contracts/src/index.ts` - re-exported the seven technology types
- `apps/mobile/src/i18n/useTranslation.ts` - exported `TranslateFn` for the effect-row formatter's signature
- `packages/localization/locales/{en,pt-BR,es}/mvp.json` - new `technology.*`/`city.open_research`/three `errors.*` keys
- `apps/mobile/__tests__/technology-tree.test.tsx` - screen-level tests (categories, states, touch targets, sheet-opening, Academy-only button)
- `apps/mobile/__tests__/technology-cta.test.tsx` - sheet CTA precedence and copy tests

## Decisions Made
See `key-decisions` in frontmatter: the contracts re-export fix, building the detail sheet inside Task 2's work, the resolved effect sign convention, the shared progress helper (without touching `ConstructionQueueStrip.tsx`), and the extra `effect_target.*` keys the real dataset needed beyond the UI-SPEC's two examples.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] `packages/contracts/src/index.ts` never exported the technology types**
- **Found during:** Task 1, first attempt to import `ResearchOrder` from `@castleroyale/contracts`
- **Issue:** 10-05 added `Technology`, `TechnologyLevel`, `TechnologyEffect`, `TechnologyPrerequisite`, `ActiveResearch`, `ResearchOrder` and `TechnologyTreeData` to `openapi.yaml`/the generated `api.ts`, but never added the corresponding named `export type X = components['schemas']['X']` lines `index.ts` uses for every other schema — a plan-blocking gap, not a design choice.
- **Fix:** Added the seven missing exports, alphabetically consistent with the existing list.
- **Files modified:** `packages/contracts/src/index.ts`
- **Verification:** `npm run typecheck`/`lint` clean in `@castleroyale/contracts` and `@castleroyale/mobile`; `npm run contracts:check` reports no diff (only `index.ts`, not the generated file, changed).
- **Committed in:** `fc818dd` (Task 1 commit)

**2. [Rule 3 - Blocking] `TechnologyDetailSheet.tsx` built during Task 2, not Task 3**
- **Found during:** Task 2, writing `app/technology.tsx`
- **Issue:** The plan splits `TechnologyDetailSheet.tsx` into Task 3's file list, but `app/technology.tsx` (a Task 2 file) renders `<TechnologyDetailSheet>` directly per the UI-SPEC's screen composition — Task 2 cannot typecheck in isolation without the sheet existing.
- **Fix:** Implemented the full six-way CTA precedence sheet as part of Task 2's work and commit. Task 3 then added only the Academy button and the two test files; `TechnologyDetailSheet.tsx` needed zero further changes.
- **Files modified:** `apps/mobile/src/features/technology/components/TechnologyDetailSheet.tsx` (created in the Task 2 commit `80d9912`, not `25f5f27`)
- **Verification:** `npm run typecheck && npm run lint` clean after Task 2's commit; Task 3's acceptance criteria (grep checks against this file) still pass unchanged.
- **Committed in:** `80d9912` (Task 2 commit)

**3. [Rule 1 - Bug] Test fixture forgot to derive `name_key` from an overridden `code`**
- **Found during:** Task 3, first run of `technology-cta.test.tsx`'s "names the category of a cross-category prerequisite" test
- **Issue:** `buildTechnology()`'s default `name_key`/`description_key` were hardcoded to `'technologies.agriculture'` regardless of an overridden `code`, so a fixture built with `code: 'siege_engineering'` still rendered as `technologies.agriculture` — the assertion failed because the wrong technology's name appeared in the requires chip.
- **Fix:** Derived `name_key`/`description_key` from `code` (defaulting to `'agriculture'` only when `code` itself is not overridden), matching the real `technologies.<code>` / `technologies.<code>_desc` convention 10-01 authored.
- **Files modified:** `apps/mobile/__tests__/technology-cta.test.tsx`
- **Verification:** All 13 tests in the file pass.
- **Committed in:** `25f5f27` (Task 3 commit)

**4. [Rule 1 - Bug] Missing `configure({ defaultIncludeHiddenElements: true })` in the new tree test**
- **Found during:** Task 3, first run of `technology-tree.test.tsx`
- **Issue:** The mocked `BottomSheet` (from `TechnologyDetailSheet`, rendered unconditionally by the screen) always carries `accessibilityViewIsModal`, which makes React Native Testing Library treat every sibling — the entire category-section tree — as hidden-behind-a-modal by default, the same artifact `city-scene.test.tsx` and `building-upgrade.test.tsx` already document and work around.
- **Fix:** Added the same `configure({ defaultIncludeHiddenElements: true })` call used by both sibling suites.
- **Files modified:** `apps/mobile/__tests__/technology-tree.test.tsx`
- **Verification:** All 6 tests in the file pass.
- **Committed in:** `25f5f27` (Task 3 commit)

---

**Total deviations:** 4 auto-fixed (2 blocking, 2 bugs — both bugs in new test code, not shipped product code)
**Impact on plan:** All four were necessary to make the plan's own file list and acceptance criteria buildable and true. No scope creep — one shared-package export fix, one test-only fixture fix, one test-only RNTL-artifact fix, and one task-ordering adjustment documented above rather than hidden.

## Issues Encountered
- Diagnosing the jump-chip touch-target test's `style.minHeight` read required walking the React Native Testing Library host-tree structure (`Text` host -> our `Text` wrapper -> two `View` layers -> the `Pressable` composite) rather than assuming `.parent` reaches the Pressable in one hop; resolved by walking up to the nearest ancestor carrying `accessibilityRole="button"` instead of a fixed hop count.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- ROADMAP criterion 5 ("The technology tree screen renders dependencies and shows locked, available, in-progress and completed states distinctly") is met and pinned by tests.
- Phase 10 (technology-research) is now feature-complete across all 5 plans (10-01 through 10-05).
- No blockers.

---
*Phase: 10-technology-research*
*Completed: 2026-09-08*

## Self-Check: PASSED

All 18 created/modified files verified present on disk; all three task commit
hashes (`fc818dd`, `80d9912`, `25f5f27`) verified present in `git log`.
