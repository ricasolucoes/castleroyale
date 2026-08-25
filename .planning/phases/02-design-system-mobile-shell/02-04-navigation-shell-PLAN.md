---
wave: 4
requirements_addressed: [REQ-08, REQ-13]
---

# Plan 02-04: Expo Router Shell & Gallery

<objective>
Configure the 5 primary tabs with typed routes and create a developer gallery screen to mount and verify all components.
</objective>

<context>
- **Phase 02 Context**: `.planning/phases/02-design-system-mobile-shell/02-CONTEXT.md`
</context>

<tasks>
## Task 1: Tab Navigation
1. Create `apps/mobile/app/(tabs)/_layout.tsx` configuring a bottom tab navigator.
2. Create 5 dummy tab screens: `city.tsx`, `world.tsx`, `military.tsx`, `alliance.tsx`, `profile.tsx`.
3. Ensure routes are typed according to Expo Router conventions.

## Task 2: Gallery Screen
1. Create `apps/mobile/app/gallery.tsx`.
2. Mount instances of `Button`, `Card`, `Panel`, `Badge`, `Skeleton`, `ResourceCounter`, `Timer`, and a trigger for `BottomSheet`.
3. Ensure it is accessible during development (e.g., a secret tap or explicitly navigating to `/gallery`).

## Task 3: CI/Verification Check
1. Run `npm run typecheck` and `npm run lint` in `apps/mobile` to verify the routes and component typings.
2. Run `npm test` to verify the touch target tests from wave 2.
</tasks>
