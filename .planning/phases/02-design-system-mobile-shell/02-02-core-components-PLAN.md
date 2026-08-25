---
wave: 2
requirements_addressed: [REQ-08, REQ-13]
---

# Plan 02-02: Core Component Library

<objective>
Build the fundamental interactive and layout components (Button, Card, Panel, Badge, Skeleton) using the base primitives, enforcing the 44x44pt touch target rule.
</objective>

<context>
- **Phase 02 Context**: `.planning/phases/02-design-system-mobile-shell/02-CONTEXT.md`
- **Rule**: Every interactive element must have a touch target of at least 44x44 points.
</context>

<tasks>
## Task 1: Button Component
1. Create `Button.tsx`.
2. Support variants (primary, secondary, danger) and sizes (small, medium, large).
3. Enforce `minHeight: 44` and `minWidth: 44` in the styling.

## Task 2: Layout & Information Components
1. Create `Card.tsx` (container with elevation/borders).
2. Create `Panel.tsx` (larger contextual container).
3. Create `Badge.tsx` (small status indicator, non-interactive).
4. Create `Skeleton.tsx` (loading placeholder).

## Task 3: Accessibility Test
1. Create a jest test `apps/mobile/__tests__/touch-targets.test.ts`.
2. Write a test asserting that interactive components (e.g., `Button`) render with at least 44pt height/width or have `minHeight`/`minWidth` styles applied.
</tasks>
