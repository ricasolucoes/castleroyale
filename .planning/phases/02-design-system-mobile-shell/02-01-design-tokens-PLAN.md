---
wave: 1
requirements_addressed: [REQ-08, REQ-13]
---

# Plan 02-01: Connect Design Tokens

<objective>
Adopt the existing design tokens from `@dominion/tooling/design-tokens` and create the foundational layout primitives (`Box` and `Text`) using `@shopify/restyle`, enforcing strict type-safe design tokens and implementing light/dark mode support.
</objective>

<context>
- Phase 02 Context: `.planning/phases/02-design-system-mobile-shell/02-CONTEXT.md`
- Dark mode will be implemented as an alternate Restyle theme swapped at the root provider.
- We will strictly use `@shopify/restyle` for all design tokens and avoid standard `StyleSheet`.
</context>

<tasks>
## Task 1: Restyle Theme Configuration
1. Define the base (light) and dark themes in `apps/mobile/src/shared/theme/theme.ts` using `createTheme` from `@shopify/restyle`.
2. Configure the root provider in `apps/mobile/app/_layout.tsx` to wrap the app in the Restyle `ThemeProvider`, switching between light and dark themes based on system preference.

## Task 2: Base Components
1. Create `apps/mobile/src/shared/components/Box.tsx` using `createBox` from `@shopify/restyle`.
2. Create `apps/mobile/src/shared/components/Text.tsx` using `createText` from `@shopify/restyle` to apply typography tokens.
</tasks>
