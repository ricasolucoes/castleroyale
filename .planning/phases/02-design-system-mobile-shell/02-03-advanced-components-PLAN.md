---
wave: 3
requirements_addressed: [REQ-08, REQ-13]
---

# Plan 02-03: Advanced Components

<objective>
Implement MMO-specific components (ResourceCounter, Timer) and the BottomSheet wrapper.
</objective>

<context>
- **Phase 02 Context**: `.planning/phases/02-design-system-mobile-shell/02-CONTEXT.md`
- Resource amounts are integers (never floats).
</context>

<tasks>
## Task 1: BottomSheet
1. Install `@gorhom/bottom-sheet` and its peer dependencies (`react-native-reanimated`, `react-native-gesture-handler`).
2. Add necessary Babel plugins for Reanimated.
3. Create `BottomSheet.tsx` wrapper integrating Restyle theme colors.

## Task 2: ResourceCounter
1. Create `ResourceCounter.tsx`.
2. Implement formatting logic (e.g., 1000 -> 1K, 1500000 -> 1.5M).
3. Ensure it renders an icon slot and text using the `Text` primitive.

## Task 3: Timer
1. Create `Timer.tsx`.
2. Accept a future timestamp and countdown locally.
3. Format as `HH:MM:SS`.
</tasks>
