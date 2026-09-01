# Mobile architecture

## Structure

```
apps/mobile/
├── app/                  Expo Router — routes only, thin
│   ├── (tabs)/           City, Map, Army, Heroes, Alliance
│   ├── (auth)/           Splash, sign-in, world selection, onboarding
│   └── _layout.tsx       Providers: Query, theme, safe area, gestures
└── src/
    ├── api/              Client, interceptors, typed endpoint wrappers
    ├── components/       Design-system primitives and shared UI
    ├── features/         Feature modules mirroring backend domains
    ├── hooks/
    ├── stores/           Zustand — ephemeral client state ONLY
    ├── realtime/         Echo connection, channel subscriptions, sequencing
    └── theme/            Token consumption
```

Routes stay thin. A screen file wires a feature component to the route; the logic
lives in `src/features/`.

## The state rule

This is the one that matters most, and the one most easily got wrong.

| State | Where | Examples |
|-------|-------|----------|
| **Server state** | TanStack Query | Resources, city, armies, map tiles, alliance |
| **Client state** | Zustand | Selected tile, open sheet, form draft, camera position |

**Server state never goes in Zustand.** If the server owns it, it lives in the
Query cache, so there is one invalidation story and one source of truth. Copying a
resource balance into a Zustand store creates a second truth that will drift.

Realtime events **invalidate or patch the Query cache**; they do not write to a
parallel store.

## The client is a projection

The server owns the truth (ADR-006). The app renders what the server says.

- Optimistic UI is allowed for **display responsiveness**, never for committed state.
- No server-authoritative action may be shown as confirmed while offline. It is
  queued or refused explicitly (Phase 40).
- On any conflict, server state overwrites local state.

## API client

- One client with interceptors for auth, correlation id and error normalisation.
- Types come from `@castleroyale/contracts`, generated from the OpenAPI spec. **Never
  hand-write an API type.**
- Errors branch on `error.code`, never on `error.message`.
- `ErrorCode.retryable` drives the retry policy.
- Token refresh is **single-flight**: concurrent 401s must not each trigger a
  rotation, or they invalidate each other (ADR-011).

## Storage

| Store | Use |
|-------|-----|
| **SecureStore / Keychain** | Access and refresh tokens. Nothing else, and nothing else here. |
| **MMKV** | Non-sensitive cache: map tiles, catalogues, preferences |

A credential in MMKV is a security defect, asserted against by a test.

## Rendering

- Ordinary UI is React Native components.
- The **world map, battle replay and particles are Skia** — drawn in batches, no
  component per entity. An architecture test enforces this; a component-per-tile
  map cannot hold 60 FPS (ADR-003).
- Animation and gestures run on the UI thread via Reanimated and Gesture Handler.
  Camera state must not round-trip through React state per frame.

## Performance budget

- 60 FPS target, 30 FPS floor on complex screens
- Cold start to interactive city screen within the Phase 38 budget
- Lazy-load routes; cache assets; avoid unnecessary re-renders

## Known traps

Documented in `.planning/codebase/STACK.md`. The ones that bite hardest: Metro must
be monorepo-aware with `disableHierarchicalLookup` (or you get two Reacts), and
`react-native-worklets/plugin` must stay last in the Babel plugin list.
