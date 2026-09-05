# Deferred Items — Phase 07 City Foundation

## Untracked 07-03 scaffolding found during 07-02 execution

**Found during:** 07-02 (city-state-slots-api) final verification sweep.

**What:** Two untracked files already exist on disk, out of scope for 07-02 and
not created by this plan's execution:

- `apps/mobile/src/features/city/rendering/grid.ts`
- `apps/mobile/src/features/city/state/citySelectionStore.ts`

Both match plan 07-03 (`07-03-city-scene-rendering-PLAN.md`) Task 1's file list
and acceptance criteria almost exactly (`computeSlotLayout(frameWidth, slotCount,
baseTileUnit, minTouchTarget)`, `useCitySelectionStore` with `selectedSlot` /
`selectSlot` / `clearSelection`, no React/React Native imports in `grid.ts`).
They appear to be leftover work from a prior, uncommitted attempt at 07-03.

**Action taken:** None. Left untouched — out of scope for 07-02, and not this
plan's job to judge whether they are correct, stale, or safe to build on.

**For the 07-03 executor:** Before writing Task 1, `Read` these two files first
and diff their content against the plan's acceptance criteria. If they already
satisfy the criteria, reuse and commit them as Task 1's output instead of
overwriting blind. If they diverge, treat the plan as authoritative and replace
them.
