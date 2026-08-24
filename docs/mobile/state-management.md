# State management

## The rule

| State | Owner | Examples |
|-------|-------|----------|
| **Server state** | TanStack Query | Resources, city, armies, map tiles, alliance, battles |
| **Client state** | Zustand | Selected tile, open sheet, form draft, camera position |

**Server state never goes in Zustand.** If the server owns it, it lives in the
Query cache — one source of truth, one invalidation story.

Copying a resource balance into a Zustand store creates a second truth. It will
drift, and the drift will show up as a player insisting they had enough gold.

## Query conventions

Keys are hierarchical so invalidation can be surgical:

```ts
['city', cityId]
['city', cityId, 'buildings']
['world', worldId, 'viewport', bounds]
['alliance', allianceId, 'members']
```

Stale times reflect how fast the data actually changes:

| Data | Stale time | Why |
|------|-----------|-----|
| Resources | 10s | Accrue continuously; interpolated client-side between fetches |
| City / buildings | 30s | Changes on player action |
| Map viewport | 60s | Realtime deltas patch it in between |
| Catalogues | Infinity | Only changes on a content version bump |
| Rankings | 5min | Server-side snapshots anyway |

## Retries

Driven by the server's own judgement:

```ts
retry: (failureCount, error) =>
  error instanceof ApiError ? error.retryable && failureCount < 3 : failureCount < 2
```

`ErrorCode.retryable` mirrors `ErrorCode::isRetryable()` in PHP. Retrying an
`INSUFFICIENT_RESOURCES` helps nobody; retrying a `RATE_LIMITED` after backoff does.

Mutations never auto-retry — that is what `Idempotency-Key` and explicit user
action are for.

## Realtime

A realtime event **invalidates or patches the Query cache**. It never writes to a
parallel store.

```ts
// city.{id} → building.completed
queryClient.invalidateQueries({ queryKey: ['city', cityId] });
```

A detected sequence gap triggers a full resync over HTTP rather than continuing
from divergent state.

## Optimistic updates

Allowed for **display responsiveness only**, and only where a rollback is
harmless — toggling a filter, reordering a list.

**Never** for server-authoritative state. Showing troops as dispatched before the
server confirms it is how a player believes they have an army they do not have.

## Persistence

| Store | Contents |
|-------|----------|
| **MMKV** | Query cache snapshots, map tiles, catalogues, preferences |
| **SecureStore / Keychain** | Access and refresh tokens — nothing else |

A credential in MMKV is a security defect, asserted against by a test.

## Interpolation

The resource bar ticks smoothly between fetches by interpolating from the last
known value and rate. This is **display only** — an affordability check always
uses the server's number, never the interpolated one.
