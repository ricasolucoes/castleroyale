---
phase: 07-city-foundation
plan: 02
subsystem: api
tags: [openapi, contracts, laravel, typescript, pest, react-native]

# Dependency graph
requires:
  - phase: 07-01-city-slot-roster-tile-claim
    provides: GameDataCatalog::citySlots() strict 18-plot roster reader
provides:
  - "CitySlot and RealtimeConfig schemas in the OpenAPI contract (spec-first, ADR-017)"
  - "CityData.slots replacing CityData.buildings: full roster, every plot empty or occupied, every read"
  - "CityData.realtime carrying the same connection details already computed for GameBootstrap"
  - "HTTP-level proof that a foreign city read returns CITY_NOT_OWNED, no data key, no leak, never a 404"
affects: [07-03-city-scene-rendering, 07-04-city-scene-art-realtime, 09-construction]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Roster projection: build an occupied-only map keyed by slot, then walk the fixed roster once to produce a full-length array where absence is a first-class 'empty' entry, not an omission"
    - "Schema extraction: an inline nested object used in two places (GameBootstrap.realtime) becomes a named component ($ref) the moment a second consumer (CityData) needs it, per ADR-017"

key-files:
  created:
    - apps/api/tests/Feature/City/CityAuthorizationTest.php
  modified:
    - packages/contracts/openapi.yaml
    - packages/contracts/src/generated/api.ts
    - packages/contracts/src/index.ts
    - packages/contracts/package.json
    - package-lock.json
    - apps/api/modules/City/Application/CityStateService.php
    - apps/mobile/app/(tabs)/city.tsx
    - apps/api/tests/Feature/City/CityFoundationTest.php
    - apps/api/tests/Feature/Mvp/MvpGameplayTest.php

key-decisions:
  - "CityData.buildings is replaced, not supplemented, by CityData.slots — one source of truth for what occupies the city."
  - "CitySlot.building is required and nullable (not optional) so exactOptionalPropertyTypes + noUncheckedIndexedAccess give an unambiguous slot.building ? … : … at every call site."
  - "realtime moves onto CityData via a new named RealtimeConfig schema shared with GameBootstrap, extracted from what was an inline duplicate; no new server computation."
  - "Fixed a drifted package-lock.json that omitted openapi-typescript's own transitive dependencies, which made contracts:generate fail outright before any content change could be verified."
  - "Tests authenticating as two different accounts within one Pest method must call app('auth')->forgetGuards() between them — Sanctum caches the resolved user on the guard for the test's lifetime, an existing pattern already used in MvpGameplayTest's cross-account idempotency test."

patterns-established:
  - "Occupied-map-then-roster-walk: the shape every future roster-backed read (Phase 09 construction, Phase 07-03 scene) should follow when projecting a fixed set against sparse occupancy rows."

requirements-completed: [REQ-01, REQ-08]

# Metrics
duration: 11min
completed: 2026-09-05
---

# Phase 07 Plan 02: City state exposes the full slot roster Summary

**`GET /game/city` now returns `CityData.slots` — all 18 plots, roster-ordered, each one `empty` or `occupied` with a nullable `building` — replacing the occupied-only `buildings` array, plus a shared `RealtimeConfig` schema and an HTTP-level proof that a foreign city read never leaks anything.**

## Performance

- **Duration:** ~11 min
- **Started:** 2026-09-05T17:35:00Z (approx.)
- **Completed:** 2026-09-05T17:46:00Z
- **Tasks:** 3
- **Files modified:** 10 (1 created, 9 modified)

## Accomplishments

- `packages/contracts/openapi.yaml` gained a `CitySlot` schema (`slot`, `status`, nullable `building`) and a `RealtimeConfig` schema extracted from the inline object previously duplicated only inside `GameBootstrap`; `CityData.buildings` is gone, replaced by `CityData.slots` and `CityData.realtime`. The generated TypeScript matches the spec byte for byte (`npm run contracts:check` passes clean).
- `CityStateService::handle()` now collects occupied buildings into a slot-keyed map, then walks `GameDataCatalog::citySlots()` once to produce the full 18-entry roster projection — every plot is a first-class entry, `status` is `empty` or `occupied`, `building` is `null` iff empty.
- The mobile city screen (`apps/mobile/app/(tabs)/city.tsx`) derives its building list via `city.slots.flatMap(...)`, keeping the MVP screen green ahead of the 07-03 rewrite, with no non-null assertions.
- `CityFoundationTest` proves the roster contract at HTTP level: 18 slots in roster order matching `GameDataCatalog::citySlots()`, 5 occupied / 13 empty, `building` null iff empty, `plot_01` holding `palace`.
- `MvpGameplayTest`'s four farm assertions moved from `data.buildings.0.*` to `data.slots.1.*` (farm sits at `plot_02`, roster index 1) without touching the upgrade endpoint or its resource-debit/idempotency coverage.
- New `CityAuthorizationTest` proves, over real HTTP with two independently bootstrapped accounts, that reading a foreign city returns `CITY_NOT_OWNED` with no `data` key, leaks no city id/name_key/`plot_` string in the body, and is never a 404.

## Task Commits

Each task was committed atomically:

1. **Task 1: Add CitySlot and RealtimeConfig to the OpenAPI contract and regenerate types** - `f777abf` (feat)
2. **Task 2: Project the roster in CityStateService and keep the mobile screen compiling** - `34223a5` (feat)
3. **Task 3: Prove the slot contract and the CITY_NOT_OWNED boundary over HTTP** - `bead3e7` (test)

**Plan metadata:** (this commit, following)

## Files Created/Modified

- `packages/contracts/openapi.yaml` - `CitySlot` and `RealtimeConfig` schemas added; `CityData.buildings` replaced by `slots` + `realtime`; `GameBootstrap.realtime` now `$ref`s the shared schema
- `packages/contracts/src/generated/api.ts` - regenerated via `npm run contracts:generate` (ADR-017, never hand-edited)
- `packages/contracts/src/index.ts` - exports `CitySlot`, `CityBuilding`, `RealtimeConfig`
- `packages/contracts/package.json` / `package-lock.json` - `openapi-typescript` range tightened to the installed `^7.13.0`; repaired a drifted lockfile that omitted the tool's own transitive dependencies
- `apps/api/modules/City/Application/CityStateService.php` - occupied-map-then-roster-walk projection; `slots` and `realtime` in the response
- `apps/mobile/app/(tabs)/city.tsx` - `buildings` derived via `city.slots.flatMap(...)`
- `apps/api/tests/Feature/City/CityFoundationTest.php` - roster-shape assertions (18 entries, order, occupied/empty split, palace at plot_01)
- `apps/api/tests/Feature/Mvp/MvpGameplayTest.php` - four assertion paths moved to `data.slots.1.*`
- `apps/api/tests/Feature/City/CityAuthorizationTest.php` - new: `CITY_NOT_OWNED`, no-leak, never-404 proofs over HTTP

## Decisions Made

- **`buildings` replaced, not supplemented:** avoids two sources of truth for the same fact on `CityData`; the upgrade endpoint and its behaviour are untouched, only the read shape and the test assertion paths that observe it moved.
- **`building` required-and-nullable:** with `exactOptionalPropertyTypes` + `noUncheckedIndexedAccess`, this is unambiguous at the call site; an optional field would force distinguishing "absent" from "null" for no benefit, and any client written against the UI-SPEC's looser optional shape still validates against this stricter one.
- **`realtime` extracted to a named schema, not duplicated:** the connection details were already computed once by `GameBootstrapService`; naming the schema lets `CityData` reference the same shape without adding server computation, and sets up 07-04 to subscribe from the city query directly.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Repaired a drifted package-lock.json that made `contracts:generate` fail outright**
- **Found during:** Task 1 (regenerating TypeScript types from the OpenAPI spec)
- **Issue:** `npm run contracts:generate` failed with `ERR_MODULE_NOT_FOUND` for `yargs-parser`, then `ansi-colors`, one at a time. `package-lock.json` listed `openapi-typescript@7.13.0`'s dependencies (`@redocly/openapi-core`, `ansi-colors`, `change-case`, `parse-json@^8`, `supports-color`, `yargs-parser`) as semver ranges but had no corresponding lockfile package nodes for them, so `npm install` never materialized them in `node_modules` even after a clean reinstall of that subtree.
- **Fix:** Removed `packages/contracts/node_modules`, then ran `npm install --workspace=@castleroyale/contracts openapi-typescript@^7.5.0 --save-dev` to force npm to re-resolve the full dependency tree; this correctly rewrote the lockfile entries and installed the missing transitive packages. `packages/contracts/package.json`'s declared range tightened from `^7.5.0` to `^7.13.0` (the version already in use) as a side effect.
- **Files modified:** `package-lock.json`, `packages/contracts/package.json`
- **Verification:** `npm run contracts:generate` succeeds; `npm run contracts:check` exits 0 with the generated file staged; `npm run typecheck` and `npm run lint` (repo root) pass.
- **Commit:** `f777abf` (Task 1 commit)

**2. [Rule 1 - Bug] Added `app('auth')->forgetGuards()` between different-account requests in the new authorization test**
- **Found during:** Task 3 (writing `CityAuthorizationTest`)
- **Issue:** The first draft of the test authenticated as an owner account, bootstrapped, then switched to a rival token and bootstrapped again within the same Pest test method. Both bootstrap responses returned the *same* player/world/city id — Sanctum's guard caches the resolved user for the life of the test process, so switching bearer tokens mid-test without clearing the guard silently re-authenticates as the first account. This produced a false-positive 200 (not `CITY_NOT_OWNED`) on the very check the test exists to prove.
- **Fix:** Added `app('auth')->forgetGuards();` immediately after the owner's bootstrap call and before authenticating as the rival, mirroring the existing pattern already used in `MvpGameplayTest`'s `'does not replay one account idempotency result to another account'` test.
- **Files modified:** `apps/api/tests/Feature/City/CityAuthorizationTest.php`
- **Verification:** Diagnosed with a throwaway debug test (`fwrite(STDERR, ...)`, not committed) confirming distinct player/city ids only after adding `forgetGuards()`; `./vendor/bin/pest --filter=CityAuthorization` passes 3/3 with the fix in place.
- **Commit:** `bead3e7` (Task 3 commit)

---

**Total deviations:** 2 auto-fixed (1 blocking dependency repair, 1 test-authoring bug)
**Impact on plan:** Both were necessary to complete the plan's own verification steps — neither touches production behaviour beyond the intended contract/service changes. No scope creep.

## Issues Encountered

None beyond the two auto-fixed deviations above.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Plan 07-03 (city scene rendering) can render directly off `CityData.slots` — a fixed-length array it never needs to infer a count from — and subscribe to realtime using `CityData.realtime`, the same shape `GameBootstrap` already exposes.
- Plan 07-04 (city scene art + realtime) has `RealtimeConfig` available from the city read itself, without a second round-trip to bootstrap.
- No blockers. All six quality gates are green: `./vendor/bin/pest` (139 passed, 1096 assertions), `phpstan analyse` (0 errors), `pint --test` (passed), `npm run typecheck`, `npm run lint`, and `npm test` (8 suites, 35 tests) from the repo root.

---
*Phase: 07-city-foundation*
*Completed: 2026-09-05*

## Self-Check: PASSED

All created files found on disk; all three task commits (`f777abf`, `34223a5`, `bead3e7`) found in git history.
