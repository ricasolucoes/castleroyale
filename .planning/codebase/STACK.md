# Stack

## Backend — `apps/api`

| Component | Version | Notes |
|-----------|---------|-------|
| PHP | 8.4.4 | Typed properties, enums, `readonly`, constants in enums |
| Laravel | 13.26.1 | |
| PostgreSQL | 16 + PostGIS 3 | Runs in Docker. **The host lacks `pdo_pgsql`.** |
| Redis | 7 | Cache, locks, queues, presence. Never authoritative (ADR-005). |
| Horizon | ^5.48 | Six queue tiers |
| Reverb | ^1.11 | Websockets, Pusher protocol |
| Octane | ^2.19 | FrankenPHP |
| Sanctum | ^4.3 | API tokens |
| Filament | ^5.7 | Back office |
| Pest | ^4.7 | Tests, including `arch()` |
| Larastan | ^3.10 | PHPStan level 8 + strict rules |
| Pint | ^1.27 | Laravel preset + strict extras |

## Mobile — `apps/mobile`

| Component | Version | Notes |
|-----------|---------|-------|
| Expo SDK | 57 | New Architecture enabled |
| React Native | 0.86.2 | |
| React | 19.2.3 | Pinned — `react-test-renderer` must match exactly |
| TypeScript | ~6.0.3 | Strict, plus `noUncheckedIndexedAccess`, `exactOptionalPropertyTypes` |
| Expo Router | latest | Typed routes |
| TanStack Query | ^5 | **All** server state |
| Zustand | ^5 | **Only** ephemeral client state |
| Reanimated | Expo-pinned | Worklet plugin must stay last in Babel |
| Skia | Expo-pinned | Map, replay, particles |
| MMKV | Expo-pinned | Non-sensitive cache only |
| SecureStore | Expo-pinned | Credentials only |
| Jest + jest-expo | latest | |

## Workspace

npm workspaces at the repository root: `apps/mobile`, `packages/*`.
`apps/api` is PHP and outside the npm workspace.

## Known toolchain traps

1. **`openapi-typescript` declares peer `typescript@^5.x`** but the Expo toolchain
   is on TS 6. Resolved by an explicit `overrides` entry in the root
   `package.json`. Do **not** "fix" this with `--legacy-peer-deps` — that would
   hide every future peer conflict too.
2. **`react-test-renderer` must match React exactly** (19.2.3). Installing it
   unpinned resolves to a newer React and fails.
3. **Metro must be monorepo-aware.** `metro.config.js` sets `watchFolders`,
   `nodeModulesPaths` and `disableHierarchicalLookup`. Removing the last one
   allows a second React copy and produces confusing hook errors.
4. **`react-native-worklets/plugin` must be the last Babel plugin.**
5. **`php artisan install:broadcasting` needs a TTY** and fails in an agent shell.
   Reverb is already wired; do not re-run it.
6. **Postgres is not reachable from the host PHP.** Run migrations through Docker
   or rely on CI. `phpunit.xml` uses SQLite in-memory, which is why the suite runs
   on the host.
