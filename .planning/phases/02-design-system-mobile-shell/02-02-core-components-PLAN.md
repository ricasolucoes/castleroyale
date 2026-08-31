---
wave: 2
requirements_addressed: [REQ-08, REQ-13]
---

# Plan 02-02: Core Component Library

<objective>
Build the fundamental interactive and layout components (Button, Card, Panel, Badge, Skeleton) strictly using Restyle layout boxes (`Box`, `Text`), enforcing the 44x44pt touch target rule.
</objective>

<context>
- Phase 02 Context: `.planning/phases/02-design-system-mobile-shell/02-CONTEXT.md`
- Components must be built using the `Box` and `Text` primitives from Task 1.
- No hardcoded colors or spacing allowed; all values must map to a token.
- Interactive elements must be >= 44x44pt.
</context>

<tasks>
## Task 1: Button Component
1. Create `Button.tsx` using `Box` and `Text`.
2. Support variants (primary, secondary, danger). Use colors from the Restyle theme.
3. Enforce `minHeight` / `minWidth` and padding tokens to meet the 44x44pt accessibility requirement.

## Task 2: Layout Components
1. Create `Card.tsx` (container with elevation/borders) using `Box`.
2. Create `Panel.tsx` using `Box`.
3. Create `Badge.tsx` using `Box` and `Text`.
4. Create `Skeleton.tsx` (for loading states) using `Box`.

## Task 3: Accessibility Test
1. Create a jest test `apps/mobile/__tests__/touch-targets.test.tsx`.
2. Verify interactive components render with >= 44pt touch targets or have `minHeight`/`minWidth` styles applied.
</tasks>
