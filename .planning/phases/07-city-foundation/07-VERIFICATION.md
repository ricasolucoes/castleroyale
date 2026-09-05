---
phase: 07-city-foundation
verified: 2026-09-05T18:25:09Z
status: human_needed
score: 5/5 ROADMAP criteria verified; 1 plan-level must-have (generated art) blocked on external action
human_verification:
  - test: "Enable billing / raise the free-tier image-generation quota on the Google Cloud project behind GEMINI_API_KEY ('Gemini Jogos', project 436393374436), then run `set -a; source /Users/sierra/Dev/Jogos/.env; set +a; python3 tools/generate-city-assets.py`, open and conference all four generated PNGs against the project's asset checklist, then wire them into CityScene.tsx (city_ground.png behind the grid) and CitySlot.tsx (slot_empty_icon.png, slot_category_core.png, slot_category_economy.png replacing the MaterialCommunityIcons glyphs), and extend city-scene.test.tsx with the one-image / no-plus-circle-outline assertions per 07-04-PLAN Task 1."
    expected: "apps/mobile/assets/city/ contains 4 PNGs (RGBA, chroma removed) + 4 sibling .prompt.md provenance files; the scene renders the generated ground and glyphs instead of theme.color.bg.sunken + vector icons."
    why_human: "Verified independently: every Gemini image-generation model returns a hard 429 RESOURCE_EXHAUSTED with a free-tier daily quota of 0 on this GCP project — a billing/plan gate, not a transient rate limit, and not retryable by an agent in-session. Enabling billing is an action only a human with access to the Google Cloud console can take."
---

# Phase 07: City Foundation Verification Report

**Phase Goal:** A player owns a city with addressable building slots, rendered as a living scene rather than a list.
**Verified:** 2026-09-05T18:25:09Z
**Status:** human_needed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths (ROADMAP Success Criteria — the contract)

| # | Truth | Status | Evidence |
|---|---|---|---|
| 1 | Every city occupies exactly one world tile and that tile cannot be claimed twice, enforced by a constraint returning TILE_OCCUPIED | ✓ VERIFIED | `GameBootstrapService::handle()` keeps the `exists()` pre-check but wraps `City::create()` in a `try/catch (QueryException)` matching `cities_world_id_x_y_unique` and translating to `ErrorCode::TileOccupied`; the constraint itself is `apps/api/database/migrations/2026_08_27_010200_create_cities_table.php:26` (`unique(['world_id','x','y'])`). `CityTileClaimTest` proves both the pre-check path and, via an `eloquent.creating` listener that inserts a rival row between SELECT and INSERT, the actual race — asserting exactly one city survives. Both tests pass (`./vendor/bin/pest --filter=CityTileClaim` → 2/2). |
| 2 | A city exposes a fixed set of build slots, each either empty or holding exactly one building | ✓ VERIFIED | `packages/game-data/data/city-slots.json` holds exactly 18 `plot_NN` entries, validated in CI. `CityStateService::handle()` builds an occupied-map keyed by slot, then walks `GameDataCatalog::citySlots()` once to emit a full-length array where every plot is `{slot, status: empty|occupied, building: null|CityBuilding}` — never inferred from what's built. `CitySlot` OpenAPI schema requires `[slot, status, building]` with `building` required-and-nullable. `CityFoundationTest` asserts exactly 18 slots in roster order, 5 occupied / 13 empty, `building` null iff empty. |
| 3 | The city screen renders the scene with tappable buildings and reflects server state after a pull-to-refresh | ✓ VERIFIED | `apps/mobile/app/(tabs)/city.tsx` is a thin screen with no `Card`, no `useMutation`, no `/upgrade` (enforced by an architecture test reading the file source). `CityScene.tsx` measures its frame via `onLayout` on the scene `View` itself (not a hardcoded tab-bar/header constant) plus `useSafeAreaInsets()` for the top padding, and re-derives `computeSlotLayout()` on every measurement. `RefreshControl` is wired to `cityQuery.refetch()`. `CitySlot.tsx` renders one `Pressable` per slot with `accessibilityRole="button"` and `hitSlop=(minTouchTarget - size)/2`, guaranteeing an effective ≥44pt touch area even when the visual tile shrinks — proven directly by `city-scene.test.tsx`'s touch-target test computing `style.width + hitSlop*2 >= MIN_TOUCH_TARGET` for every rendered button. `city-scene.test.tsx` also proves exactly 18 buttons render for 18 server slots (none hardcoded) and that tapping opens the correct sheet content. |
| 4 | The `city.{id}` channel authorises the owner and denies non-owners, verified by a test | ✓ VERIFIED | `apps/api/routes/channels.php` implements `Broadcast::channel('city.{cityId}', ...)` for real (ownership join + `whereColumn('cities.world_id','players.world_id')`), not a stub returning `false`. `CityChannelAuthorizationTest` covers four cases — owner allow, rival deny, unknown-city deny (no existence oracle), and a world-drift deny that specifically isolates the `whereColumn` clause rather than re-testing the ownership join — all 4 pass. |
| 5 | Requesting a city the player does not own returns CITY_NOT_OWNED, not 404, and never leaks its contents | ✓ VERIFIED | Route is `Route::get('/game/city/{cityId}', [CityController::class,'show'])` with a plain string param — no implicit route-model binding, so there is no Laravel auto-404 path to leak through. `CityStateService::handle()` throws `ErrorCode::CityNotOwned` (default HTTP status 400, per `ErrorCode::httpStatus()`'s `default => 400` arm — never 404) when the owner+city+world query returns null. `CityAuthorizationTest` proves all three angles over real HTTP: the error code, that the raw response body contains neither the owner's city id, name_key nor any `plot_` string and that `data` is `null`, and that the status is explicitly not 404. |

**Score:** 5/5 ROADMAP success criteria verified.

### Plan-Level Must-Have Not Met (outside the ROADMAP's 5 criteria, but claimed by 07-04's plan)

| Truth (07-04-PLAN) | Status | Evidence |
|---|---|---|
| "The city scene is painted with generated art, not a flat placeholder, and every asset can be regenerated from its recorded prompt" | ✗ NOT MET (externally blocked) | `apps/mobile/assets/city/` does not exist on disk. `tools/generate-city-assets.py` is written, complete, and correct (reads `GEMINI_API_KEY` from env only, no key literal, chroma-removal + alpha-crop logic for the three icon assets, writes sibling `.prompt.md` provenance) but has never successfully run: every Gemini image-generation model (`gemini-3.1-flash-image`, `gemini-3-pro-image`, `gemini-3.1-flash-lite-image`) returns `429 RESOURCE_EXHAUSTED` with a free-tier daily quota of `0` on the `Gemini Jogos` GCP project (`436393374436`), confirmed non-transient by a control call to a text model on the same key returning a different error (503, not 429). `CityScene.tsx` still renders on `theme.color.bg.sunken`; `CitySlot.tsx` still renders `MaterialCommunityIcons` (`plus-circle-outline`, `castle`, `sprout-outline`) instead of the four generated PNGs. UI-SPEC "Flagged Assumption 3" and the 07-03 plan's own locked decision 3 explicitly designate this interim state as permitted pending 07-04's art — so this is a documented, scoped gap with a ready-to-run fix, not a silent placeholder, but the truth itself is not satisfied today. |

### Required Artifacts

| Artifact | Expected | Status | Details |
|---|---|---|---|
| `packages/game-data/data/city-slots.json` | 18-entry fixed plot roster, JSON array | ✓ VERIFIED | 18 `plot_01`..`plot_18` entries, validated by `npm run gamedata:validate`. |
| `packages/game-data/data/starter.json` | 5 starter buildings pinned to named plots | ✓ VERIFIED | `palace→plot_01`, `farm→plot_02`, `lumber_mill→plot_03`, `quarry→plot_04`, `warehouse→plot_05`. |
| `apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php` | `citySlots()` reader; strict `starterBuildings()` | ✓ VERIFIED | Reads `city-slots.json`; `starterBuildings()` throws on missing/unknown slot (no silent `?? $row['code']` fallback remains). |
| `apps/api/database/migrations/2026_09_05_000000_map_city_building_slots_to_plot_roster.php` | Legacy `slot=code` → plot remap | ✓ VERIFIED | Present, maps the 5 legacy rows both directions (up/down). |
| `apps/api/modules/Player/Application/GameBootstrapService.php` | Constraint-backed tile claim | ✓ VERIFIED | `QueryException` catch on `City::create()` matching `cities_world_id_x_y_unique`, translates to `TileOccupied`. |
| `apps/api/tests/Feature/City/CityTileClaimTest.php` | Pre-check + race proof | ✓ VERIFIED | 2 tests, both pass; race test uses `eloquent.creating` listener interleave. |
| `apps/api/tests/Feature/City/CitySlotRosterTest.php` | Roster persistence + shape + arch rule | ✓ VERIFIED | 3 tests pass (roster persisted to named plots, 18-entry roster shape, no `plot_NN` literal under `apps/api/modules/`). |
| `packages/contracts/openapi.yaml` — `CitySlot`, `RealtimeConfig` schemas | Fixed roster + shared realtime shape | ✓ VERIFIED | Both schemas present; `CityData.buildings` fully replaced by `slots` + `realtime`; `npm run contracts:check` clean. |
| `apps/api/modules/City/Application/CityStateService.php` | Roster-ordered slot projection | ✓ VERIFIED | Occupied-map-then-roster-walk producing `slots`; `realtime` carried onto `CityData`. |
| `apps/api/tests/Feature/City/CityAuthorizationTest.php` | HTTP-level CITY_NOT_OWNED + no-leak + never-404 | ✓ VERIFIED | 3 tests, all pass. |
| `apps/mobile/src/features/city/rendering/grid.ts` | Pure, count-agnostic `computeSlotLayout` | ✓ VERIFIED | No React/RN import; 44pt-floor column-decrement logic; 9/9 `city-grid.test.ts` cases pass. |
| `apps/mobile/src/features/city/components/CityScene.tsx` | Measured frame, grid, `RefreshControl` | ✓ VERIFIED | `onLayout` + `useSafeAreaInsets`; `RefreshControl` wired to `onRefresh`. |
| `apps/mobile/src/features/city/components/CitySlot.tsx` | One `Pressable` plot, empty/occupied | ✓ VERIFIED | `accessibilityRole="button"`, `hitSlop`, both states rendered; still on interim `MaterialCommunityIcons` (see art gap above). |
| `apps/mobile/src/features/city/state/citySelectionStore.ts` | Selection-identity-only Zustand store | ✓ VERIFIED | Holds only `selectedSlot`; no building/slot data. |
| `apps/api/modules/City/Interface/Broadcasting/CityStateChanged.php` | Queued private broadcast, identifiers only | ✓ VERIFIED | `PrivateChannel('city.'.$cityId)`, `broadcastAs() = 'city.state_changed'`, `broadcastQueue() = 'realtime'`, payload is 3 identifiers. |
| `apps/api/tests/Feature/City/CityChannelAuthorizationTest.php` | Allow + deny (x3) paths | ✓ VERIFIED | 4 tests, all pass, ≥40 lines. |
| `apps/mobile/src/features/city/realtime/cityChannel.ts` / `useCityRealtime.ts` | Injectable-socket handshake, cache invalidation | ✓ VERIFIED | Full Pusher-protocol handshake against an injectable `SocketFactory`; `useCityRealtime` invalidates `['game','city']` on any non-`pusher:` event, writes nothing to Zustand; 9 tests in `city-realtime.test.ts` pass. |
| `apps/mobile/assets/city/*.png` + `*.prompt.md` (4 each) | Generated ground + glyph art with provenance | ✗ MISSING | Directory does not exist. Blocked on external Gemini billing quota (see above). `tools/generate-city-assets.py` exists, is complete, and is ready to run once unblocked. |

### Key Link Verification

| From | To | Via | Status | Details |
|---|---|---|---|---|
| `GameBootstrapService::handle()` | `packages/game-data/data/city-slots.json` | `GameDataCatalog::starterBuildings()` validating slot against `citySlots()` | ✓ WIRED | Throws `RuntimeException` on an invalid/missing slot; confirmed by reading. |
| `GameBootstrapService::handle()` | `cities` unique(world_id,x,y) | `QueryException` catch → `ErrorCode::TileOccupied` | ✓ WIRED | Confirmed by code read + `CityTileClaimTest` passing. |
| `CityStateService::handle()` | `packages/game-data/data/city-slots.json` | `GameDataCatalog::citySlots()` driving response order/length | ✓ WIRED | Confirmed by code read: `foreach ($this->catalog->citySlots() as $slotCode)`. |
| `apps/mobile/app/(tabs)/city.tsx` | `CityData.slots` | Passed straight through to `CityScene` (07-03 removed the `flatMap` derivation entirely) | ✓ WIRED | `city.tsx` renders `<CityScene city={cityQuery.data} .../>`; `CityScene` consumes `city.slots` directly. |
| `apps/mobile/app/(tabs)/city.tsx` | `CityScene.tsx` | Tab screen renders `CityScene` instead of a Card list | ✓ WIRED | Confirmed; architecture test in `city-scene.test.tsx` enforces no `<Card`/`useMutation`/`/upgrade`/`city.buildings` regression. |
| `CityScene.tsx` | `grid.ts` | `computeSlotLayout` called with measured frame width | ✓ WIRED | Confirmed by code read. |
| `CitySlot.tsx` | `citySelectionStore.ts` | `onPress → selectSlot(slot) → sheet opens` | ✓ WIRED | Confirmed by code read + `city-scene.test.tsx` press assertions. |
| `apps/api/modules/Construction/Application/ConstructionCompletionService.php` | `CityStateChanged` | `dispatch()` after an order completes | ✓ WIRED | `CityStateChanged::dispatch(...)` called once per completed order inside `completeOverdueLocked`'s loop. |
| `apps/mobile/.../useCityRealtime.ts` | `POST /broadcasting/auth` | `pusher:subscribe` authorised with bearer token from `SecureStorage` | ✓ WIRED | Confirmed by code read + `city-realtime.test.ts`'s handshake assertions. |
| `CityScene.tsx` | `apps/mobile/assets/city/city_ground.png` | `Image` behind the measured frame | ✗ NOT WIRED | Asset does not exist; wiring deliberately not attempted (would break the Metro bundle / Jest suite per the SUMMARY's own reasoning). |

### Requirements Coverage

Note: this repo has no `.planning/REQUIREMENTS.md`; REQ-01 and REQ-08 are defined in `.planning/PROJECT.md`.

| Requirement | Source Plan(s) | Description | Status | Evidence |
|---|---|---|---|---|
| REQ-01 | 07-01, 07-02, 07-04 | Server-authoritative gameplay: no client-supplied outcome is ever trusted | ✓ SATISFIED | Tile occupancy is decided by the database unique constraint, not a client claim; city ownership is re-checked server-side on every read (`CityStateService`); the roster and slot contents are entirely server-computed and the client never infers slot count or occupancy. |
| REQ-08 | 07-02, 07-03, 07-04 | Premium-feeling mobile UI at 60 FPS, one-handed, offline-aware (already validated in Phase 02; this phase extends it to the city screen) | ✓ SATISFIED for the mechanics; art polish incomplete | The scene is a runtime-measured, edge-to-edge, no-Card grid with a guaranteed ≥44pt touch floor at any slot count, localized in 3 locales, and themed entirely off design tokens (no literal colour/spacing/font-size in the new components). The one open item is generated art (see gap above), which affects visual polish, not interactivity, layout correctness, or one-handed operability. |

No orphaned requirements: PROJECT.md maps only REQ-01 and REQ-08 to this phase, and both are declared across the four plans' `requirements:` frontmatter.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|---|---|---|---|---|
| `apps/mobile/src/features/city/components/CitySlot.tsx` | 60-64 | Interim `MaterialCommunityIcons` glyph (`plus-circle-outline`) standing in for generated art | ℹ️ Info | Explicitly documented as a permitted interim state (UI-SPEC Flagged Assumption 3, 07-03 locked decision 3); not a silent placeholder — a regeneration pipeline and full wiring instructions exist and are ready to execute. Does not block any ROADMAP success criterion. |

No blocker or warning-level anti-patterns (TODO/FIXME/empty-return/console-only stubs) were found in any file touched by this phase's four plans.

### Human Verification Required

#### 1. Generate and wire the four city-scene art assets

**Test:** Enable billing (or otherwise raise the free-tier image-generation quota) on the Google Cloud project behind `GEMINI_API_KEY` ("Gemini Jogos", project `436393374436`) — see `https://ai.google.dev/gemini-api/docs/rate-limits`. Then run:
```bash
set -a; source /Users/sierra/Dev/Jogos/.env; set +a
python3 tools/generate-city-assets.py
```
Open and inspect all four generated PNGs against the project's asset-conference checklist (chroma solid, silhouette readable at 64px, no text/watermark, ground art quiet enough not to compete with the tiles, the two category glyphs visually distinct). Then apply the wiring `07-04-city-scene-art-realtime-PLAN.md` Task 1 already specifies: `CityScene.tsx` renders `city_ground.png` as the first child of the measured frame; `CitySlot.tsx` replaces `plus-circle-outline` / `castle` / `sprout-outline` with the three generated glyphs; extend `city-scene.test.tsx` with the one-image and no-`plus-circle-outline` assertions.

**Expected:** `apps/mobile/assets/city/` contains 4 PNGs (3 RGBA with chroma removed, 1 painted scenery) + 4 sibling `.prompt.md` files naming the model id, ISO date and full prompt; the city scene visually renders the generated ground and glyphs instead of `theme.color.bg.sunken` and vector icons.

**Why human:** Independently confirmed this is a hard `429 RESOURCE_EXHAUSTED` with a free-tier daily quota of `0` for every Gemini image-generation model on this specific GCP project — a billing/plan gate, not a transient rate limit, and not something retryable or fixable from within an agent session. Only a human with Google Cloud console access to the "Gemini Jogos" project can lift it.

### Gaps Summary

Every one of the ROADMAP's five Phase 07 success criteria is independently verified against the actual codebase — not against SUMMARY claims — and all are backed by passing, targeted tests (20 relevant Pest tests + the full 143-test backend suite, plus 58 mobile Jest tests across 11 suites), with PHPStan, Pint, `npm run typecheck` and `npm run lint` all clean. The tile-claim race is proven by a genuine interleaving test, not just a happy-path check; the slot roster is verifiably fixed-length and roster-ordered, not derived from what's built; the city screen is a genuinely measured, tappable grid, not a relabeled list (enforced by an architecture test); the private channel is proven on all four angles, including the one nobody thinks to test (`world_id` drift); and `CITY_NOT_OWNED` is proven non-404 and leak-free at the HTTP layer with real assertions on the response body.

The one shortfall is scoped, single-issue, and honestly disclosed by the phase's own SUMMARY rather than hidden: `apps/mobile/assets/city/` has no generated art because the Gemini API key's backing GCP project has a confirmed zero image-generation quota. The generation pipeline (`tools/generate-city-assets.py`) is complete, correct, and carries no fabricated or half-generated output; the scene ships on the UI-SPEC's own designated interim state rather than silently placeholdered. This does not block any ROADMAP success criterion — the scene is functionally a scene, not a list, with or without painted art — so it is reported as `human_needed` rather than `gaps_found`: the remaining work is a human enabling billing plus re-running an already-written script and applying already-specified wiring, not new engineering.

---

*Verified: 2026-09-05T18:25:09Z*
*Verifier: Claude (gsd-verifier)*
