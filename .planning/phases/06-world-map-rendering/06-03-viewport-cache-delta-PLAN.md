---
wave: 2
depends_on: ["06-01"]
files_modified:
  - apps/mobile/src/features/world/
  - apps/mobile/src/api/
  - apps/mobile/__tests__/
autonomous: true
requirements: [REQ-04, REQ-08]
---

# Plan 06-03: Viewport fetching, MMKV cache and delta application

<objective>
Fetch bounded server viewports through TanStack Query, cache validated region
snapshots in MMKV, and apply coordinate-keyed deltas without duplicating server
state in Zustand.
</objective>

## Objective

Fetch server-authoritative regions through TanStack Query, persist normalized
region snapshots in MMKV for offline revisits, and apply validated deltas
without placing map data in Zustand.

## Tasks

<task>
<read_first>
- .planning/phases/06-world-map-rendering/06-CONTEXT.md
- docs/mobile/architecture.md
- packages/contracts/src/index.ts
- packages/contracts/src/generated/api.ts
- apps/mobile/src/api/client.ts
- apps/mobile/app/_layout.tsx
- apps/mobile/package.json
</read_first>
<action>
Create `useWorldViewport` under `apps/mobile/src/features/world/data/` with a
query key shaped as `['game','world','viewport',minX,maxX,minY,maxY]`, calling
`apiRequest<WorldViewport>('/game/world/viewport?...', {}, { authenticated:
true })`. Normalize query bounds to the one-screen-margin rectangle, keep the
response in TanStack Query, and add a small `WorldRegionCache` adapter using
`react-native-mmkv` keyed by world id and region coordinate. On query failure,
return the last validated snapshot as stale/offline data with a retry state;
never fabricate tile content.
</action>
<acceptance_criteria>
- Query keys contain all four integer bounds and no access token or player-owned state.
- API requests include all four encoded query parameters and `authenticated: true`.
- MMKV cache keys include the world id and region coordinate; cache values are serialized generated-contract data.
- Offline fallback is explicitly marked stale and never constructs a tile from coordinates alone.
- The data adapter exports no Zustand store and no duplicate handwritten `WorldTile` type.
</acceptance_criteria>
</task>

<task>
<read_first>
- apps/mobile/src/features/world/data/useWorldViewport.ts
- apps/mobile/src/features/world/data/WorldRegionCache.ts
- packages/contracts/src/index.ts
- apps/mobile/__tests__/api-client.test.ts
</read_first>
<action>
Add tests for exact viewport URL/query key construction, cache round-trip and
invalid cache rejection, stale fallback after a network error, and delta merge
where an incoming tile replaces the same `(x,y)` while unrelated cached tiles
remain. Mock MMKV at the adapter boundary and use contract-generated types.
</action>
<acceptance_criteria>
- Tests assert the URL contains `min_x`, `max_x`, `min_y` and `max_y` with integer values.
- Cache tests assert world/region namespacing prevents cross-world reads.
- Delta tests assert replacement by coordinate and preservation of untouched tiles.
- `npm test --workspace=@dominion/mobile -- --runInBand` passes.
</acceptance_criteria>
</task>

## Verification

- `npm run typecheck --workspace=@dominion/mobile`
- `npm run lint --workspace=@dominion/mobile`
- `npm test --workspace=@dominion/mobile -- --runInBand`

## Must-haves

- TanStack Query owns server map data.
- MMKV is only a cache, never authoritative.
- Revisiting a cached region does not issue a network request while fresh.
