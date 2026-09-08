---
gsd_state_version: 1.0
milestone: v0.1
milestone_name: Foundation to Launch
status: unknown
stopped_at: Completed 10-05-research-command-reconciler-PLAN.md (wave 3 of Phase 10 complete); wave 4 (10-04 mobile technology tree) next
last_updated: "2026-09-08T17:06:17.324Z"
progress:
  total_phases: 69
  completed_phases: 10
  total_plans: 50
  completed_plans: 50
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-08-25)

**Core value:** The server owns the truth — a player's empire is exactly what the server says it is, always.
**Current focus:** Phase 10 — technology-research

## Current Position

Phase: 10 (technology-research) — EXECUTING
Plan: 4 of 5 (wave 3 — 10-05 research command/reconciler — complete; wave 4, 10-04 mobile technology tree, next)

## Performance Metrics

**Velocity:**

- Total plans completed: 5
- Average duration: not yet measured
- Total execution time: not yet measured

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 00. Repository Bootstrap | 4/4 | - | - |
| 01. Engineering Foundation | 4/4 | ~2h | ~30min |

**Recent Trend:**

- Last 5 plans: Phase 01 (4 plans) + Phase 00
- Trend: Stable

*Updated after each plan completion*
| Phase 01-engineering-foundation P01 | 4 min | 3 tasks | 4 files |
| Phase 02 P01-design-tokens | 15 min | 2 tasks | 3 files |
| Phase 02-design-system-mobile-shell P02-02-core-components | 4 min | 3 tasks | 8 files |
| Phase 02-design-system-mobile-shell P02-03-advanced-components | 4 min | 3 tasks | 6 files |
| Phase 02-design-system-mobile-shell P02-04-navigation-shell | 5 min | 3 tasks | 8 files |
| Phase 02-design-system-mobile-shell P02-06-touch-target-tests | 2 min | 1 tasks | 1 files |
| Phase 02-design-system-mobile-shell P05-localization-integration | 4 min | 4 tasks | 10 files |
| Phase 02.1 P01 | 5 min | 2 tasks | 13 files |
| Phase 02.1 P02 | 8 min | 2 tasks | 16 files |
| Phase 06 P01 | 15 min | 2 tasks | 4 files |
| Phase 06 P02 | 3 min | 2 tasks | 7 files |
| Phase 06-world-map-rendering P06-04 | 30 min | 2 tasks | 4 files |
| Phase 07 P01 | 19min | 3 tasks | 9 files |
| Phase 07 P02 | 11min | 3 tasks | 10 files |
| Phase 07 P03 | 13min | 3 tasks | 11 files |
| Phase 07 P04 | 32min | 3 tasks | 11 files |
| Phase 08-resources-economy PP01 | 10min | 3 tasks | 6 files |
| Phase 08-resources-economy P02 | 12min | 3 tasks | 3 files |
| Phase 08-resources-economy P03 | 6min | 3 tasks | 10 files |
| Phase 08-resources-economy P05 | 25min | 3 tasks | 16 files |
| Phase 08-resources-economy P04 | 7min | 3 tasks | 2 files |
| Phase 09 P02 | 15min | 3 tasks | 9 files |
| Phase 09-buildings-construction P01 | 25min | 3 tasks | 14 files |
| Phase 10-technology-research P01 | 20min | 3 tasks | 8 files |
| Phase 10-technology-research P02 | 15min | 3 tasks | 8 files |
| Phase 10-technology-research P03 | 16min | 4 tasks | 12 files |
| Phase 10-technology-research P05 | 35min | 3 tasks | 15 files |

## Accumulated Context

### Roadmap Evolution

- Phase 02.1 inserted after Phase 02: Institutional Site and Public Backend Surface (URGENT)

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting current work:

- [Phase 00]: Modular monolith with a neutral `Game\` namespace — the product name is config, never code (ADR-001, ADR-002)
- [Phase 00]: Integer-only economy with checked arithmetic; floats are forbidden for anything a player owns (ADR-010)
- [Phase 00]: Server-authoritative time via a `Clock` contract; game rules never call `now()` (ADR-006)
- [Phase 00]: PHPStan analyses first-party code only — Laravel's stock config and Pest's fluent API are excluded (DEBT-002)
- [Phase 00]: GSD `research` disabled — every phase ships with CONTEXT.md and canonical references
- [Phase 01]: Repository will be published as **public** at `ricasolucoes/castleroyale`
  (user decision, 2026-08-24). The publish is gated: gitleaks scan runs autonomously,
  then the executor STOPS and hands back for a live human go-ahead before
  `gh repo create`. Plan files are explicitly not authorization for this.

- [Phase 01]: Plan 01-03 replaces the locked `updateOrCreate` seeder mechanism with
  `firstOrNew` + `forceFill` — `preventSilentlyDiscardingAttributes()` throws on the
  non-fillable `is_staff`. To be recorded in `docs/gsd/DECISIONS.md` during execution.

- [Phase 02]: Removed @shopify/restyle to adhere strictly to local @castleroyale/tooling/design-tokens with minimal runtime overhead
- [Phase 02]: Mocked `@gorhom/bottom-sheet` instead of `react-native-reanimated` because the failure originates deep in the reanimated/worklets setup, and for the purpose of the touch target test, we only need to verify that our wrapper correctly passes props down and mounts.
- [Phase 02.1]: Use query-string locale selection with an allow-list and restore the previous application locale after every request.
- [Phase 02.1]: Keep institutional copy in versioned Laravel locale catalogues and render every page through one shared Blade layout.
- [Phase 02.1]: Use a checked-in CSS artifact adjacent to the TypeScript token source so Laravel Vite can consume the same semantic vocabulary without changing the mobile contract.
- [Phase 02.1]: Build canonical and hreflang URLs from configured app.url and explicit locale query parameters.
- [Phase 03]: Use Sanctum token families with rotating refresh secrets; a replay revokes the family before returning TOKEN_EXPIRED.
- [Phase 03]: Verify Apple and Google identity tokens server-side against configured issuer, audience and JWKS; persist only the verified subject.
- [Phase 06]: Used Zustand to store only selected coordinates; derived the selected tile directly from TanStack Query's viewport cache.
- [Phase 06]: Added 44pt circular hit testing inside the Skia canvas onTouchEnd to reliably intercept taps near compact markers.
- [Phase 06]: Delegated interaction resolution logic to Skia's tap handler, skipping separate React Native pressables for map entities.
- [Phase 07]: Wrapped City::create in a QueryException catch matching the cities_world_id_x_y_unique index name so the unique index (not the exists() pre-check) is the authority refusing a double tile claim
- [Phase 07]: Replaced CityData.buildings with CityData.slots (full 18-plot roster, empty or occupied, building required-and-nullable) rather than supplementing it, and extracted realtime into a shared RealtimeConfig schema referenced by both GameBootstrap and CityData
- [Phase 07]: Tests that switch bearer tokens between different accounts within one Pest method must call app('auth')->forgetGuards() first — Sanctum caches the resolved user on the guard for the test's lifetime
- [Phase 07]: Removed the upgrade CTA from the city scene client-side only; backend /game/city/buildings/{code}/upgrade route and its MvpGameplayTest coverage are untouched, Phase 09 reintroduces the button
- [Phase 07]: City scene frame is measured via onLayout on the scene container itself, never a hardcoded tab-bar/header pixel constant
- [Phase 07]: City scene ships on bg.sunken pending 07-04's generated city_ground.png; not a silent placeholder
- [Phase 07]: Gemini image generation is blocked by a persistent daily quota of 0 on every image model for the shared GCP project; city scene ships on bg.sunken with interim vector glyphs per UI-SPEC Flagged Assumption 3 until billing is enabled and tools/generate-city-assets.py is run
- [Phase 07]: city.state_changed dispatches from ConstructionCompletionService::completeOverdueLocked (the single site an order actually completes), once per completed order, covering both the lazy read-path and the queued CompleteConstruction job
- [Phase 07]: Realtime transport (cityChannel.ts) uses an injectable socket factory over the plain Pusher protocol Reverb speaks, adding no npm dependency; reconnection, backoff and sequence-gap resync stay Phase 40's
- [Phase 08]: Wire rate unit is signed integer units per hour (perSecond * 3600); game-data production effect stays per-second
- [Phase 08]: OverflowPolicy (DiscardAtCap | Refuse) makes WAREHOUSE_CAPACITY_EXCEEDED reachable as an all-or-nothing pre-flight check on creditLocked, defaulting to unchanged discard-at-cap behavior
- [Phase 08]: LedgerParty value object (city:{ulid} / system:{name}) is the sole shape for a ledger counterparty; EconomyLedger::record() is the only sanctioned write path, enforced by an architecture test and Eloquent update/delete guards
- [Phase 08-resources-economy]: ResourceBar owns the top safe-area inset and the resource-type icon mapping (resourceIcons.ts); CityScene drops insets.top and its duplicated resource row
- [Phase 08-resources-economy]: Exported ResourceRate from @castleroyale/contracts (08-01 added the schema but never re-exported the type)
- [Phase 08-resources-economy P04]: Concurrency proof over SQLite :memory: fires a one-shot Event::listen on `eloquent.retrieved` for Player at the exact seam between BuildingUpgradeController's committed bootstrap transaction and BuildingUpgradeService::start()'s own locked transaction; manually verified by disabling the listener and observing the test fail before restoring it — the Phase 07 CityTileClaimTest technique, now applied to resource spending
- [Phase 08-resources-economy P04]: Test fixtures that need to pre-adjust a city balance for contention purposes go through the same locked debitLocked()/creditLocked() path as gameplay, never a raw DB::table write — a raw write broke the plan's own ledger-reconciliation assertion because the starter grant (500/500/500/250/100) wasn't reflected in a compensating ledger row
- [Phase 08-resources-economy P04]: Property test seeds mt_srand from ECONOMY_PROPERTY_SEED (falling back to random_int), and every failure assertion prints the seed so a red CI run is replayable with one exported variable
- [Phase 09]: [Phase 09 P02]: A building code occupies at most one construction slot today; the client resolves the busy slot by matching building_code against slots[].building.code, not a slot field on Construction (09-UI-SPEC.md Flagged Assumption 2, unchanged).
- [Phase 09]: [Phase 09 P02]: BuildDuration::scaled() is the single place game.time_scale is applied, shared by CityStateService's preview and BuildingUpgradeService's scheduler, so the two can never drift.
- [Phase 09 P01]: Starter Palace raised to level 3 (was 1) so the newly-live Palace gate does not retroactively invalidate Phase 07/08's tested farm/lumber_mill/quarry/warehouse upgrade progressions; Palace itself carries no building requirement at any level.
- [Phase 09 P01]: iron_mine and treasury effects use value:1 at every level (no scaling curve), matching the four pre-existing producers' placeholder balance — the curve is Phase 46's (Economy Balance Pass) to design.
- [Phase 09 P01]: ADR-020 defers ADR-013's database-import clause — packages/game-data JSON stays the runtime source of truth through Phase 09; game:import-data is still the real, load-bearing import-time validator. Revisit at Phase 31 (Events & LiveOps) or Phase 34 (Admin), whichever lands first.
- [Phase 09 P01]: Failure messages that must contain a literal locale name (for grep-based verification) are built with explicit per-locale if/return branches, not string interpolation — interpolating a loop variable puts the variable reference, not its value, into the file's source bytes.
- [Phase 09 P01]: $this->artisan(...)->expectsOutputToContain() cannot assert two substrings that both live on the same single output line — each call backs a separate Mockery expectation on doWrite(), and one real call only satisfies one of them. Use Artisan::call() + Artisan::output() with plain str_contains-based assertions instead when a failure message must be checked for multiple substrings at once.
- [Phase 10]: [Phase 10 P01]: Technology dataset reuses buildings.schema.json's requirement/effect/resourceCost $defs byte-identical; alliance technologies (diplomatic_relations, trade_agreements) are Phase-22-deferred taxonomy placeholders using inert unit.defense/march.speed targets rather than an off-contract alliance.* target.
- [Phase 10-technology-research]: [Phase 10 P03] research_orders_one_open_per_player is an unconditional partial unique index (no driver guard) — verified on both SQLite and PostgreSQL.
- [Phase 10-technology-research]: [Phase 10 P03] EffectResolver::resolve() is shared by GameDataCatalog::effectsFor() for both buildings and technologies; effectsForBuildings() kept as a thin wrapper, no remaining direct caller but signature preserved for future/back-compat use.
- [Phase 10-technology-research]: [Phase 10 P02] Every validator rule in packages/game-data/src/rules.ts is a pure function (data in, Problem[] out); validate.ts is a thin CLI. findCycle moved verbatim, not rewritten. Task 4 (wire validator into CI) required no ci.yml change — the existing "Mobile & packages" job already ran gamedata:validate and workspace-wide npm test, which picked up game-data's new test script automatically.
- [Phase 10-technology-research]: [Phase 10 P02] Fixed a pre-existing bug the new checkTranslationKeys rule caught: units.json's "militia" name_key resolved in no locale catalogue at all (PHP's ImportGameDataCommand::checkTranslations only ever validated buildings, never units). Added a "units" namespace to en/pt-BR/es mvp.json.
- [Phase 10-technology-research]: Renamed ResearchCompletionServiceTest.php to ResearchScopeTest.php and dropped its duplicated idempotency test — Pest's --filter substring-matched the old name against the new ResearchCompletionTest.php, breaking 10-05's own acceptance criterion
- [Phase 10-technology-research]: ResearchEffectTest.php resolves GameDataCatalog::effectsFor() against synthetic unpersisted CityBuilding rows to survive integer truncation of the placeholder +1/second building production, while still driving the real HTTP research command and reconciler for the PlayerTechnology data
- [Phase 10-technology-research]: BuildingRequirementEvaluator::unmetFor(type, code, targetLevel, buildingLevels, technologyLevels) generalises the Palace-gate mechanism for both buildings and technologies; unmet() is now a thin wrapper preserving 09-04's array<string,int> contract

### Pending Todos

None yet.

### Blockers/Concerns

- [Phase 00 → resolved in Phase 01] Host PHP still lacks `pdo_pgsql`; Docker is now the canonical
  environment and CI runs migrations/seeds/PostGIS suite against `postgis/postgis:16-3.4`
  (evidence in `.planning/codebase/CONCERNS.md` § Environment). Never trust a host-only green suite.

- [Phase 01 → done] `ricasolucoes/castleroyale` is PUBLIC with `origin` configured; the publish
  gate is closed. Auto-mode denies pushing new branches / opening PRs — plan negative CI checks as
  human checkpoints, not autonomous steps.

- [Housekeeping] Untracked `roadmap.json` (stray `roadmap analyze` dump) and the tracked SQLite file
  `apps/api/castleroyale` (modified by runs) sit in the working tree; both should probably be removed /
  gitignored — left untouched pending the user's call.

- [Roadmap] Phases 55–67 (Google Play Sidekick, 13 phases) were appended to ROADMAP.md outside the
  autonomous session and committed as `e04193d`; they depend on Phase 54 and will be picked up by
  the autonomous loop after Phase 54 unless removed or moved to their own milestone.

- [Phase 07-04] BOTH image-generation routes are billing-blocked; verified 2026-09-05. (a) Gemini: 429 RESOURCE_EXHAUSTED, quota metric generate_content_free_tier_input_token_count with limit 0, on every image model, for the shared 'Gemini Jogos' GCP project (436393374436) backing GEMINI_API_KEY. (b) OpenAI fallback (tried at the user's explicit direction, overriding the Jogos CLAUDE.md 'sempre Gemini' rule): 429 credit_balance_exhausted, 'You have no credits remaining' — account-wide, text and image alike, so gpt-image-1 is not an option either. Do not retry either key until credit/billing is added. Human action needed: enable billing / raise the free-tier image quota, then run 'python3 tools/generate-city-assets.py' and apply the wiring steps in 07-04-city-scene-art-realtime-PLAN.md Task 1. City scene ships on bg.sunken with interim vector glyphs until then (UI-SPEC Flagged Assumption 3).

## Session Continuity

Last session: 2026-09-08T17:06:17.320Z
Stopped at: Completed 10-05-research-command-reconciler-PLAN.md (wave 3 of Phase 10 complete).
  10-05 shipped POST /game/technologies/{code}/research (locked debit, fixed
  max/locked/in-progress/insufficient-resources refusal precedence),
  GET /game/technologies (tier, prerequisites, server-computed state), the
  ResearchReconciler/CompleteResearch job pair mirroring Construction's, and
  proof of ROADMAP criterion 4 (a completed technology's effect observable in
  a recomputed production rate, not merely a database row) — see
  10-05-research-command-reconciler-SUMMARY.md for detail. Full pest suite 223
  passed; PHPStan 0 errors; Pint clean; npm run contracts:check exits 0.
Resume with: `/gsd:autonomous --from 10` (execute wave 4: 10-04 mobile technology tree, depends on
  10-01, 10-03, 10-05 — all now done)
Resume file: None

Wave plan for 10 (from each plan's depends_on frontmatter):
  wave 1: 10-01 (catalogue, schema, effects model)                — no shared files — DONE
  wave 2: 10-02 (validator completion) ∥ 10-03 (research queue)   — both depend only on 10-01 — DONE
  wave 3: 10-05 (research command/reconciler)                     — depends on 10-01, 10-03 — DONE
  wave 4: 10-04 (mobile technology tree)                          — depends on 10-01, 10-03, 10-05 — next

**Phase 01 evidence:** `01-VERIFICATION.md` (passed 5/5), four SUMMARY.md files, CI run
https://github.com/ricasolucoes/castleroyale/actions/runs/32802315288 (green, 4 jobs).
