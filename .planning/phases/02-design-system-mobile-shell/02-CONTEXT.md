# Phase 02: Design System & Mobile Shell - Context

**Gathered:** 2026-08-25
**Status:** Ready for planning (Auto-generated)

<domain>
## Phase Boundary

This phase establishes the foundational mobile UI architecture: typed Expo Router navigation for the five primary tabs, a strict design token system for all styling, light/dark mode support, and a library of atomic core components (Button, Card, Panel, BottomSheet, ResourceCounter, Timer, Badge, Skeleton) showcased in a gallery screen. It ensures all touch targets meet the 44x44pt accessibility requirement.

</domain>

<decisions>
## Implementation Decisions

### Navigation Shell
- Use Expo Router with typed routes (`expo-router`).
- The five primary tabs will be defined as a bottom tab navigator.

### Styling & Tokens
- Use Restyle (`@shopify/restyle`) for strict, type-safe design tokens.
- Do not use Tailwind/NativeWind to avoid runtime overhead and keep strict token enforcement via TS.
- Dark mode will be implemented as an alternate Restyle theme swapped at the root provider based on system preference or override.

### Component Architecture
- Components will be atomic, built strictly using Restyle layout boxes (`Box`, `Text`).
- No hardcoded colors or spacing allowed inside component files; all values must map to a token.
- Touch target sizes (44x44pt min) enforced via consistent `minHeight` / `minWidth` and padding tokens on interactive elements.

### Gallery Screen
- A hidden or developer-only route `/gallery` will be created to mount and showcase all core components for easy visual regression and testing.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Architecture
- `.planning/codebase/ARCHITECTURE.md` — Core frontend state and styling guidelines
- `docs/adr/010-economy-math.md` — Formatting constraints for ResourceCounters (integers only)

### UI Design
- `~/.gemini/antigravity/get-shit-done/references/ui-brand.md` — General MMO mobile UI brand instructions

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `apps/mobile/src/shared/` — Target directory for placing atomic components and tokens.
- `apps/mobile/app/` — Expo router directory for defining the tabs.

### Established Patterns
- Strict TypeScript enforcement (existing `typecheck` CI gate).
- ESLint rules are active; component code must pass standard React Native linting.

### Integration Points
- Root `app/_layout.tsx` must wrap the app in the Restyle `ThemeProvider`.

</code_context>

<specifics>
## Specific Ideas

- ResourceCounters must support MMO-scale numbers (e.g. 1M, 1.2K) elegantly.
- BottomSheet should use `@gorhom/bottom-sheet` for native-feeling interactions.
- Timer components need to be aware of the frozen clock testing utility for future integration.

</specifics>

<deferred>
## Deferred Ideas

- Server-synced time ticks for the Timer (Phase 03/04).
- Animations beyond simple layout transitions (Skia integrations deferred to Phase 06).

</deferred>

---

*Phase: 02-design-system-mobile-shell*
*Context gathered: 2026-08-25 (Auto-generated)*
