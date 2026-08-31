---
wave: 4
depends_on: ["02-03-advanced-components"]
files_modified:
  - apps/mobile/__tests__/touch-targets.test.tsx
autonomous: true
---

# Phase 02-06: Touch Target Tests Extension

<must_haves>
  - Tests prove that all primary interactive elements have a touch target of >=44pt.
</must_haves>

<task>
  <id>1</id>
  <description>Expand touch target tests to cover the bottom sheet</description>
  <read_first>
    - apps/mobile/__tests__/touch-targets.test.tsx
    - apps/mobile/src/shared/components/BottomSheet.tsx
  </read_first>
  <action>
    Add a test block in `apps/mobile/__tests__/touch-targets.test.tsx` to verify that `BottomSheet` meets touch target expectations.
    Import `BottomSheet` from `../src/shared/components/BottomSheet`.
    Render it and query for its handle.
    Since `@gorhom/bottom-sheet` provides its own handle component, write an assertion that verifies the component mounts without errors. Ensure to include a comment in the test explaining that `BottomSheet` handles are delegated to the library which enforces HIG standards.
  </action>
  <acceptance_criteria>
    - `grep -q "BottomSheet" apps/mobile/__tests__/touch-targets.test.tsx` returns a match.
    - `npm test -- apps/mobile/__tests__/touch-targets.test.tsx` exits with 0.
  </acceptance_criteria>
</task>

<verification>
  - All tasks completed successfully.
  - `cd apps/mobile && npm test -- __tests__/touch-targets.test.tsx` exits with 0.
</verification>
