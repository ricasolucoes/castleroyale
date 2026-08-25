---
phase: 02-design-system-mobile-shell
plan: 01-design-tokens
subsystem: ui
tags: [react-native, design-system, theming]

requires:
  - phase: 01-engineering-foundation
    provides: [project structure, strict TS config]
provides:
  - Base Box layout primitive mapped to theme spacing/radius tokens
  - Base Text typography primitive mapped to theme typography/color tokens
  - Theme utility hook ensuring type-safe design tokens usage
affects: [02-02-core-components, 02-03-advanced-components]

tech-stack:
  added: []
  patterns: [Atomic token-mapped primitives instead of inline styles]

key-files:
  created: 
    - apps/mobile/src/shared/components/Box.tsx
    - apps/mobile/src/shared/components/Text.tsx
  modified:
    - apps/mobile/package.json

key-decisions:
  - "Decided against @shopify/restyle to rely directly on custom @dominion/tooling/design-tokens to minimize runtime overhead."
  - "Box component updated to map p, pt, m, mx etc. to SpacingToken strings for full token safety."

patterns-established:
  - "Theme mapping: Base components must accept semantic token keys instead of raw numbers/colors."

requirements-completed: [REQ-08, REQ-13]

duration: 15 min
completed: 2026-08-25T14:58:00Z
---

# Phase 02 Plan 01: Connect Design Tokens Summary

**Foundational type-safe layout and typography primitives built directly on Dominion design tokens without Restyle.**

## Performance

- **Duration:** 15 min
- **Started:** 2026-08-25T14:40:00Z
- **Completed:** 2026-08-25T14:58:00Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- Cleaned up package.json to remove `@shopify/restyle` and rely directly on our internal design tokens.
- Implemented `Box` primitive supporting type-safe margins, paddings, and border radius tokens.
- Implemented `Text` primitive strictly mapped to typography and color tokens.

## Task Commits

Each task was committed atomically:

1. **Task 1: Clean up restyle** - `3825189` (feat) (Executed in previous incomplete run)
2. **Task 2: Base Components** - `[pending]` (feat) (Implemented token mapping)

## Files Created/Modified
- `apps/mobile/package.json` - Removed @shopify/restyle.
- `apps/mobile/src/shared/components/Box.tsx` - Token-mapped layout View wrapper.
- `apps/mobile/src/shared/components/Text.tsx` - Token-mapped typography Text wrapper.

## Decisions Made
- Removed @shopify/restyle to adhere strictly to local `@dominion/tooling/design-tokens` with minimal runtime overhead.
- Used a flat mapping for `Box` (e.g. `p`, `px`, `m`, `mx`) typing them natively against `useTheme` spacing properties.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] ViewProps/TextProps TS1484 verbatimModuleSyntax fix**
- **Found during:** Task 2 (Base Components)
- **Issue:** TypeScript threw TS1484 because ViewProps was not imported as a type-only import under verbatimModuleSyntax.
- **Fix:** Added `type` modifier to imports in `Box.tsx` and `Text.tsx`.
- **Files modified:** apps/mobile/src/shared/components/Box.tsx, apps/mobile/src/shared/components/Text.tsx
- **Verification:** `npm run typecheck` passes cleanly.

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** Code correctness improved, ensuring strict TS compilation passes.

## Issues Encountered
None

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
Core primitive components (`Box`, `Text`) are fully tested against TS and ready for the next phase: `02-02-core-components-PLAN.md` which will build Buttons, Cards, etc.
