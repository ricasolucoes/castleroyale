---
phase: 06
slug: world-map-rendering
status: approved
shadcn_initialized: false
preset: none
created: 2026-08-28
---

# Phase 06 — UI Design Contract

> Visual and interaction contract for the mobile world map. It uses the
> existing token system and native mobile primitives; no new visual system is
> introduced in this phase.

## Design System

| Property | Value |
|----------|-------|
| Tool | none |
| Preset | not applicable |
| Component library | existing shared React Native components |
| Icon library | `@expo/vector-icons` / MaterialCommunityIcons |
| Font | existing app typography tokens |

## Layout and interaction contract

- The world screen keeps the title and compact coordinate/status chrome in a
  token-spaced shell. The map is the dominant surface and receives the largest
  available area below the header.
- The map is one `Canvas`/Skia surface. Tiles, terrain fills, roads, rivers,
  and markers are draw calls or batched atlas sprites, never React children per
  entity.
- The fetched rectangle is the visible map plus one-screen margin. Camera
  transforms are shared values updated on the UI thread. React state must not
  update on every pan or zoom frame.
- Pan is bounded to the loaded world extent. Pinch zoom keeps its focal point
  under the fingers and clamps to named LOD thresholds. A reset control returns
  to the player's city tile.
- At the closest LOD, terrain and player/important entity markers are visible.
  At middle LOD, markers simplify to compact atlas symbols. At far LOD, only
  region/strategic silhouettes remain; no entity is individually rendered when
  it cannot be distinguished.
- Every visual marker uses a minimum 44pt hit target. Hit-testing converts the
  tap through the camera transform to integer `(x, y)` and reads the matching
  server tile from TanStack Query data.
- Selection lives in a small Zustand store containing only selected coordinate,
  camera transform and selected tile id. Server tile contents remain in
  TanStack Query and are never copied into Zustand as authoritative state.
- A selected tile opens the existing bottom-sheet pattern with its true server
  response: coordinate, terrain label, region and known contents. Cached data is
  labelled/loading until the query confirms it; a guessed tile detail is never
  shown as fact.

## Spacing Scale

Use only `@dominion/tooling/design-tokens` values:

| Token | Value | Usage |
|-------|-------|-------|
| xs | 4pt | Icon and label gaps |
| sm | 8pt | Compact map chrome spacing |
| md | 12pt | Card/sheet internal spacing |
| lg | 16pt | Screen padding and section spacing |
| xl | 24pt | Sheet/header separation |
| 2xl | 32pt | Major screen break |
| 3xl | 48pt | Large empty/loading spacing |

Exceptions: none.

## Typography

| Role | Token | Size | Weight | Line Height |
|------|-------|------|--------|-------------|
| Body | `typography.body` | 16pt | 400 | 24pt |
| Label | `typography.label` | 14pt | 500 | 20pt |
| Heading | `typography.heading` | 18pt | 600 | 24pt |
| Display | `typography.display` | 32pt | 700 | 40pt |
| Metadata | `typography.caption` | 12pt | 400 | 16pt |

## Color

Use semantic theme tokens only; do not add literals in screen or map code.

| Role | Token | Usage |
|------|-------|-------|
| Dominant | `bg.base` | Screen background |
| Secondary | `surface.raised` / `bg.sunken` | Map frame, cards and inset map surface |
| Boundary | `border.subtle` / `border.strong` | Map frame, selected tile and dividers |
| Terrain | `success`, `warning`, `accent.steel` | Terrain categories only, with labels/symbols where surfaced |
| Player accent | `accent.gold` | Player city marker and selected-state emphasis |
| Information | `accent.steel` | Neutral markers and informational affordances |
| Destructive | `danger` | Error state only |
| Text | `text.primary` / `text.secondary` | Labels and metadata |

Accent reserved for player location, selection, map information and explicit
primary actions. It is not a blanket color for every interactive element.

## Copywriting Contract

All copy goes through the existing localization package in pt-BR, en and es.

| Element | Copy key / behavior |
|---------|---------------------|
| Primary CTA | `world.reset_to_city` — verb + destination |
| Selection heading | `world.selected_tile` |
| Coordinate label | `world.coordinates` with integer `x` and `y` |
| Empty state heading | `world.no_tiles` |
| Empty state body | Explain that this area is not available yet and offer `world.reset_to_city` |
| Loading state | `common.loading`; preserve the map frame and avoid layout shift |
| Error state | `errors.network` or canonical `errors.<code>` plus `common.retry` |
| Detail sheet | Terrain and region labels from server data; never expose a stale guess |

## Accessibility and performance

- Provide accessible labels for reset, zoom and selected-tile controls. The
  canvas has a screen-level summary and an accessible selected-tile action;
  accessibility work beyond the phase's map interaction remains deferred to
  Phase 41.
- Respect reduced motion through `motion.instant`; otherwise use only existing
  `fast`, `base`, `slow` and `deliberate` tokens.
- The architecture test must reject JSX loops that create a component per tile
  or per marker. Performance checks must exercise pan/zoom with a populated
  dataset and report the Expo performance monitor result.

## Registry Safety

| Registry | Blocks Used | Safety Gate |
|----------|-------------|-------------|
| none | none | not applicable |

## Checker Sign-Off

- [x] Dimension 1 Copywriting: PASS
- [x] Dimension 2 Visuals: PASS
- [x] Dimension 3 Color: PASS
- [x] Dimension 4 Typography: PASS
- [x] Dimension 5 Spacing: PASS
- [x] Dimension 6 Registry Safety: PASS

**Approval:** approved 2026-08-28 (manual fallback after UI researcher timeout)
