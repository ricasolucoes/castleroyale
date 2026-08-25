---
wave: 1
requirements_addressed: [REQ-08, REQ-13]
---

# Plan 02-01: Design Tokens & Base Primitives

<objective>
Establish the strict, type-safe design token system using Restyle and create the fundamental building blocks (`Box`, `Text`) for all future components, ensuring light and dark mode support.
</objective>

<context>
- **Phase 02 Context**: `.planning/phases/02-design-system-mobile-shell/02-CONTEXT.md`
- No hardcoded styling is permitted outside the `theme.ts`.
</context>

<tasks>
## Task 1: Install and configure Restyle
1. Install `@shopify/restyle`.
2. Create `apps/mobile/src/shared/theme/theme.ts`.
3. Define the base `theme` (light mode) with tokens for `colors`, `spacing` (e.g., s: 8, m: 16), `breakpoints`, `textVariants`, `borderRadii`, and `zIndices`.
4. Define `darkTheme` mapping dark mode colors.
5. Export `Theme` type.

## Task 2: Root ThemeProvider
1. Modify `apps/mobile/app/_layout.tsx` (or create if missing) to wrap the app in the Restyle `ThemeProvider`.
2. Use a stub or context to toggle dark/light mode for testing.

## Task 3: Base Primitives
1. Create `apps/mobile/src/shared/components/Box.tsx` using `createBox`.
2. Create `apps/mobile/src/shared/components/Text.tsx` using `createText`.
3. Ensure these are exported for use across the app.
</tasks>
