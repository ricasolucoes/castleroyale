---
phase: 09-buildings-construction
plan: 05
subsystem: mobile
tags: [react-native, react-query, accessibility, localization, city-scene, construction]

# Dependency graph
requires:
  - phase: 09-buildings-construction
    provides: "09-01 — the eighteen-building catalogue the glyph map must cover"
  - phase: 09-buildings-construction
    provides: "09-02 — CityData.constructions[] and queue_limit; CityScene/CitySlotDetailSheet already reading the array"
  - phase: 07-city-foundation
    provides: "CityScene, CitySlot, CitySlotDetailSheet, the removed upgrade CTA this restores"
provides:
  - "BUILDING_ICONS — all 18 catalogue codes mapped to verified MaterialCommunityIcons glyphs, plus UNMAPPED_BUILDING_ICON and COST_SHORTFALL_ICON"
  - "useUpgradeBuilding() — the upgrade mutation, invalidating ['game','city'] on settle"
  - "CitySlotDetailSheet five-state CTA driven by the server snapshot"
  - "ConstructionQueueStrip — queue occupancy at a glance, tap a pip to jump to that building"
  - "formatResourceCost exported from ResourceCounter"
affects: [10-technology-research, 12-training-system, 45-tutorial-ftue-polish]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Hook-stubbing via jest.mock over wrapping a suite in QueryClientProvider (extends the resource-bar.test.tsx pattern to mutations)"
    - "Source-reading architecture assertions inside a component test (readFileSync + not.toContain) to forbid a colour or an import rather than a rendered output"
    - "Caller-derived props: the sheet and the strip derive nothing from global state; CityScene resolves every lookup and passes plain values"

key-files:
  created:
    - apps/mobile/src/shared/components/buildingIcons.ts
    - apps/mobile/src/features/city/api/useUpgradeBuilding.ts
    - apps/mobile/src/features/city/components/ConstructionQueueStrip.tsx
    - apps/mobile/__tests__/building-upgrade.test.tsx
  modified:
    - apps/mobile/src/features/city/components/CitySlotDetailSheet.tsx
    - apps/mobile/src/features/city/components/CityScene.tsx
    - apps/mobile/src/features/city/components/CitySlot.tsx
    - apps/mobile/src/shared/components/ResourceCounter.tsx
    - apps/mobile/__tests__/city-scene.test.tsx
    - packages/localization/locales/en/mvp.json
    - packages/localization/locales/pt-BR/mvp.json
    - packages/localization/locales/es/mvp.json

key-decisions:
  - "Affordability is computed from resources.current alone, never the interpolated bar. The bar ticks between polls; letting it decide would offer a button the server is about to refuse. A test raises capacity and rate while current stays short and asserts the CTA does not change."
  - "A refusal is text.secondary, never danger, and never a red button. Not having saved up yet, or losing a race against another device, is not destructive — red is reserved for destruction. A source assertion in building-upgrade.test.tsx enforces this."
  - "useUpgradeBuilding uses onSettled, not onSuccess: a refusal means the server's state moved without us, so both outcomes deserve a refetch. The cache is never patched optimistically."
  - "Five buildings reuse their resource's glyph (farm/barley, lumber_mill/tree, quarry/terrain, iron_mine/anvil, treasury/gold) rather than inventing an eighteenth symbol — reinforcing an association the player already learned from the resource bar."
  - "Empty queue pips are accessibilityElementsHidden. The container already announces 'N of M slots in use'; four more VoiceOver nodes saying 'queue slot available' is noise. city.queue_slot_free exists for a future surface that needs it individually and is deliberately not rendered."
  - "The strip returns null when queueLimit <= 0. Always true today; it guards a future world configured without a build queue rather than assuming one cannot exist."

patterns-established:
  - "A CTA whose enablement mirrors a server rule derives from the server snapshot only — never from a client-side interpolation of that same data."

requirements-completed: [REQ-05, REQ-06]

# Metrics
duration: ~35min (Tasks 2-3 interrupted by an account spend limit; completed inline)
completed: 2026-09-06
---

# Phase 09 Plan 05: Mobile Upgrade Flow & Queue UI Summary

**The upgrade button Phase 07 removed is back, in five mutually exclusive states decided by the server snapshot alone, above a queue strip that shows occupancy at a glance and jumps to whatever is building.**

## The five CTA states and the test that pins each

| State | Condition | Test |
|---|---|---|
| Max level badge, no button | `level >= max_level` | *shows only a max-level badge at max level* |
| Timer only (Phase 07, unchanged) | this building is under construction | *shows the timer and no button while this building is under construction* |
| Disabled "queue full" | `constructions.length >= queue_limit` | *says the queue is full before it says anything else about affordability* |
| Disabled "cannot afford" | any `next_level_cost[r] > resources.current[r]` | *refuses on the snapshot, not the ticking bar* |
| Enabled "upgrade" | otherwise | *offers the upgrade when the server snapshot covers the cost* |

Plus *swaps the label while the mutation is in flight*, *renders localized copy for a server refusal and keeps the sheet open*, *falls back to generic copy for a non-API failure*, and the source assertion *never colours a normal ceiling as danger*.

## Verification

| Check | Result |
|---|---|
| `npm test` | 14 suites, 86 tests passed (was 13 / 75) |
| `npm run typecheck` | clean, 4 workspaces |
| `npm run lint` | clean, `--max-warnings=0` |
| `git diff Button.tsx Badge.tsx` | empty — zero component diffs, as the UI-SPEC promised |

Every Task 2 and Task 3 acceptance grep verified: no `interpolateResources` / `ResourceBar` / `useCityQuery` in the sheet, no `theme.color.danger`, no `variant="danger"`, no confirmation dialog, `invalidateQueries` present and `onSuccess` absent, ≥ 2 `theme.minTouchTarget` uses in the strip, 9 `it(` blocks in the new suite.

*(The "no dialog" grep reports 1 match — `accessibilityViewIsModal`, a pre-existing accessibility prop on the BottomSheet, not a confirmation dialog. The criterion's intent holds.)*

## Issues encountered

**Spend-limit interruption.** Execution was killed mid-Task-2 by an account-level monthly spend limit (HTTP 429). Task 1 was committed (`2c99948`); Task 2's implementation was complete and correct on disk but unstaged, and Task 3 had not started. The remaining work was completed inline: Task 2's files were verified against every acceptance criterion unchanged, then the queue strip, its wiring, and both test suites were written.

**Jest mock hoisting.** `jest.mock`'s factory may not close over an out-of-scope variable unless it is `mock`-prefixed. The per-test mutation state had to be named `mockUpgradeState` / `mockMutate`.

**No generated art.** Per project CLAUDE.md, game art comes from the Gemini API, but STATE.md records both image routes as billing-blocked (Gemini free-tier image quota 0; OpenAI credit balance exhausted). The eighteen buildings therefore ship as vector glyphs, continuing UI-SPEC Flagged Assumption 3. This is deliberate and reversible: `BUILDING_ICONS` is one map to swap once billing is enabled.

**Flagged Assumption 5 not invoked.** The pip progress bar was in budget and shipped; the pips are not static.

## What this enables

Phase 10 (technology research) and Phase 12 (training queues) inherit both the CTA grammar and the queue strip — both are generic over "a thing that costs resources and takes server time", not over buildings specifically.
