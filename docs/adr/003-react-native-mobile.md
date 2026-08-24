# ADR-003: React Native + Expo for the client

**Status:** Accepted
**Date:** 2026-08-24

## Context

The client must feel like a premium game — a fluid world map with thousands of
entities, animated city scenes, 60 FPS interaction — while shipping to iOS and
Android from one codebase with a small team.

React Native and Expo were fixed by the project brief.

## Decision

Use **React Native (0.86) with Expo SDK 57**, TypeScript in strict mode, and:

- **Expo Router** for typed, file-based navigation
- **TanStack Query** for all server state — cache, retries, invalidation
- **Zustand** for ephemeral client state only (selection, UI mode, drafts)
- **Reanimated + Gesture Handler** for interaction on the UI thread
- **Skia** for the world map, battle replay and particle rendering
- **MMKV** for non-sensitive cache, **SecureStore/Keychain** for credentials

The strict split matters: **server state never lives in Zustand.** Anything the
server owns is TanStack Query cache, so it has one invalidation story and one
source of truth. Anything in Zustand is disposable.

The app is never a WebView.

## Alternatives

**Unity or Godot.** Better raw rendering, and rejected: the product is 90% UI —
lists, panels, sheets, forms, chat. Native UI toolkits win that fight decisively,
and the team's leverage is in React.

**Flutter.** Comparable capability, rejected on brief and team familiarity.

**Bare React Native without Expo.** Rejected: Expo's build service, OTA updates
and config plugins remove weeks of native toolchain work. `expo prebuild` keeps
the escape hatch open if a native module ever demands it.

**React Native views for the map.** Rejected explicitly — a component per tile or
per marker cannot hold 60 FPS at map scale. Skia draws the map in batches, and an
architecture test enforces it.

## Consequences

- One codebase, two platforms, and OTA updates for anything that is not native code.
- Skia means the map is a canvas: no free accessibility, no free hit-testing. Both
  are built explicitly (Phases 06 and 41).
- Reanimated's worklet transform must stay the last Babel plugin, and Metro must be
  monorepo-aware or `packages/*` edits will not rebuild.
- Two React copies resolved from nested `node_modules` breaks hooks in confusing
  ways; `disableHierarchicalLookup` is set in `metro.config.js` to prevent it.
- New Architecture (Fabric/TurboModules) is enabled, so every native dependency
  must support it.
