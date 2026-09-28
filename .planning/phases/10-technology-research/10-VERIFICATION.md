---
phase: 10-technology-research
verified: 2026-09-08T17:44:42Z
status: gaps_found
score: 4/5 truths verified (1 partial)
gaps:
  - truth: "A completed technology's effect is observable in a recomputed value, for example production rate rising by the documented amount (ROADMAP criterion 4 / the phase goal's 'measurably modify their empire' claim)"
    status: partial
    reason: >
      The resolver, persistence, HTTP research flow, reconciler and idempotent
      completion are all genuinely built and correctly wired — EffectResolver's
      add-then-multiply-permille arithmetic is unit-tested and the pipeline is
      exercised end-to-end through real HTTP calls and a real database row.
      But with the ACTUAL authored game data, no technology in the tree
      produces any observable change to any value a player can see today.
      Every authored technology effect (all 48 levels, across all 16
      technologies) uses operation "multiply"; none use "add". Of the eight
      effect targets authored, only production.* and storage.* are consumed
      by any live code path (GameDataCatalog::effectsFor() /
      CityEconomyService); the other six (unit.attack, unit.defense,
      march.speed, build.speed, scout.range, siege.damage) are read by no
      code yet. And the two live targets that ARE consumed (production.food
      via agriculture, production.iron via mining) are multiplied against a
      real building baseline of exactly 1 — every production building's
      authored effect is a flat add:1 at every level (a Phase 09 /
      Phase-46-deferred placeholder) — so intdiv(1 * permille, 1000) = 1 for
      every authored permille value (1100/1200/1300), truncating the entire
      effect back to zero observable change. I reproduced this directly via
      `php artisan tinker`: GameDataCatalog::effectsFor() with a real farm
      level 1 returns production.food = 1 both before AND after applying a
      real completed agriculture level-1 PlayerTechnology row. A player who
      researches Agriculture to level 3 (spending real resources and real
      time) sees zero change to their food rate today.
      ResearchEffectTest.php, the test written specifically to prove this
      criterion, discloses the truncation trap in its own comments and works
      around it by feeding GameDataCatalog::effectsFor() a synthetic,
      never-persisted CityBuilding with production=100 instead of the real
      farm. That proves the resolver's arithmetic is correct in isolation,
      but it does not assert a change on the real persisted city via
      CityEconomyService::ratesPerHour() — which is what "observable in a
      recomputed value" means for the goal as stated. This is a documented,
      project-wide, pre-existing deferral (10-CONTEXT.md and 09-CONTEXT.md
      both explicitly defer "balance values beyond plausible placeholders" to
      Phase 46), not a defect Phase 10 introduced through sloppiness — but it
      does mean the phase goal is not actually true in the live game today.
    artifacts:
      - path: "apps/api/tests/Feature/Technology/ResearchEffectTest.php"
        issue: "Proves the resolver's math only against a synthetic 100-unit baseline; never asserts a change on the real city's CityEconomyService::ratesPerHour() after a real completed research"
      - path: "packages/game-data/data/technologies.json"
        issue: "All 48 authored technology levels use operation \"multiply\"; none use \"add\", so every effect is subject to the placeholder-building truncation trap and none can currently produce any observable player-facing change"
      - path: "packages/game-data/data/buildings.json"
        issue: "Inherited Phase 09 placeholder: every production building's effect is a flat add:1 at every level, which silently reduces any multiplier effect (even +30%) to +0 via intdiv truncation (ADR-010)"
    missing:
      - "Either raise at least one live-consumed building's base production above the truncation threshold, or author at least one tier-1 technology effect using operation \"add\" against a currently-consumed target (production.* or storage.*), so a real player who completes a real research sees a real, non-zero change in a real recomputed value today, not only in a synthetic test fixture"
      - "Add a test that calls CityEconomyService::ratesPerHour() (or the equivalent live read path) on the actual persisted starter city, before and after a real HTTP-driven, reconciler-completed research, and asserts a nonzero, correctly-computed difference — proving the goal against the live pipeline rather than a synthetic stand-in"
---

# Phase 10: Technology & Research Verification Report

**Phase Goal:** A player researches technologies from an acyclic tree whose effects measurably modify their empire.
**Verified:** 2026-09-08T17:44:42Z
**Status:** gaps_found
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | The technology tree loads from game data and a cycle/negative-cost/dangling-reference validator rejects bad datasets, wired into CI | ✓ VERIFIED | `technologies.json` (16 techs, 8 categories, real DAG) read via `GameDataCatalog::technologies()`/`technologyLevel()`; `rules.ts`'s `findCycle` is a real DFS (5-node cycle test + diamond non-cycle test both pass); `TechnologyGraph::tier()` independently re-detects a cycle at runtime and throws naming the code (defense in depth, not just authoring-time); `npm run gamedata:validate` exits 0 against real data; `npm test` (root) — 12/12 game-data tests green including "accepts the real authored datasets"; validator already wired into the existing required CI job |
| 2 | Researching a locked technology returns `TECHNOLOGY_LOCKED`; a maxed one returns `TECHNOLOGY_MAX_LEVEL`, both HTTP 400 | ✓ VERIFIED | `ResearchService::start()` checks max-level then locked then in-progress then afford; `ResearchQueueTest` — "refuses a locked technology and names the missing prerequisite", "refuses a maxed technology with TECHNOLOGY_MAX_LEVEL", "reports TECHNOLOGY_MAX_LEVEL over an unmet prerequisite" all pass live against Docker/Postgres |
| 3 | Only one research runs at a time per player; a second returns `RESEARCH_IN_PROGRESS` | ✓ VERIFIED | `research_orders_one_open_per_player` partial unique index (DB-level invariant, proven on both SQLite and Postgres per 10-03's SUMMARY); `ResearchQueueTest`'s "refuses a second concurrent research with RESEARCH_IN_PROGRESS" and "reports TECHNOLOGY_LOCKED over RESEARCH_IN_PROGRESS" pass |
| 4 | A completed technology's effect is observable in a recomputed value | ⚠️ PARTIAL | See gap above. The resolver/pipeline is correct and tested, but with real authored data the effect is invisible in the live game — reproduced directly via tinker (production.food stays at 1 before and after a real completed Agriculture research) |
| 5 | The technology tree screen renders dependencies and shows locked/available/in-progress/completed distinctly | ✓ VERIFIED | `TechnologyNodeCard` — 4 states differentiated by border style + badge shape/presence, icon stays `text.secondary` in every state (no colour-only signal); `TechnologyDetailSheet` — 6-way CTA precedence with cross-category prerequisite chips (name + category); Academy-only entry point; `apps/mobile` full suite 16/16 suites, 105/105 tests green including `technology-tree.test.tsx` and `technology-cta.test.tsx` |

**Score:** 4/5 truths fully verified, 1 partial

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `packages/game-data/schema/technologies.schema.json` | JSON Schema 2020-12 contract, `$defs` shared byte-identical with buildings | ✓ VERIFIED | 77 lines, present; SUMMARY's own diff-assertion check passed at build time |
| `packages/game-data/data/technologies.json` | 16-tech DAG, 8 categories, integer permille | ✓ VERIFIED | 485 lines; confirmed 16 technologies, all 8 categories present, two-prerequisite node (`logistics_core`), cross-category edge (`siege_engineering`→`masonry`) |
| `apps/api/modules/Shared/Infrastructure/GameData/GameDataCatalog.php` | `technologies()`/`technologyLevel()`/`effectsFor()` | ✓ VERIFIED | Present, tested (`TechnologyCatalogueTest`, `EffectResolverTest`), null-vs-throw contract matches `buildingLevel()` |
| `packages/game-data/src/rules.ts` | All 8 validation rules as pure exported functions | ✓ VERIFIED | 326 lines; `findCycle` moved verbatim; 3 new rules (`checkTranslationKeys`, `checkRequirementSatisfiable`, `checkCrossDatasetReferences`) present and tested |
| `packages/game-data/test/rules.test.ts` | ≥11 red-path tests, message-content assertions | ✓ VERIFIED | 12 tests, all pass, includes 5-node cycle + diamond non-cycle + real-dataset regression pin |
| `apps/api/modules/Technology/Domain/EffectResolver.php` | Pure add-then-multiply permille resolver | ✓ VERIFIED | 72 lines, framework-free, 6 tests pass (identity, truncation, additive stacking) |
| `apps/api/modules/Technology/Domain/TechnologyGraph.php` | tier() + prerequisites() surviving max level | ✓ VERIFIED | 145 lines, 6 tests pass including cycle-throws and max-tier-observed-2 on real data |
| `apps/api/modules/Technology/Application/ResearchService.php` | The command: lock, refusals, timers | ✓ VERIFIED | Mirrors `BuildingUpgradeService::start()`; 8 `ResearchQueueTest` tests pass live |
| `apps/api/modules/Technology/Interface/Http/TechnologyTreeController.php` | `GET /game/technologies` with tier/prerequisites/state | ✓ VERIFIED | 199 lines; `tier`/`prerequisites` present unconditionally (survive `next_level: null`); `contracts:check` clean |
| `apps/api/modules/Technology/Application/ResearchReconciler.php` + `CompleteResearch` job | Dead-worker-safe completion | ✓ VERIFIED | 4 `ResearchCompletionTest` tests pass; falsification performed and restored (guard removal breaks exactly the service-direct test, confirmed by SUMMARY and independently by the full suite still being green with the guard present) |
| `apps/mobile/src/features/technology/components/TechnologyNodeCard.tsx` | 4 shape-distinct states | ✓ VERIFIED | 185 lines; code review confirms border-style + badge-shape differentiation, no colour-only signal, `accessibilityRole="button"` present |
| `apps/mobile/src/features/technology/components/TechnologyDetailSheet.tsx` | 6-way CTA precedence, cross-category chips | ✓ VERIFIED | Code review confirms precedence order (max → active → locked → busy → unaffordable → enabled) and category-labelled prerequisite chips |
| `apps/mobile/__tests__/technology-cta.test.tsx` / `technology-tree.test.tsx` | Named tests per state | ✓ VERIFIED | 19 new tests, all pass; full mobile suite 16/16 suites, 105/105 tests |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `TechnologyTreeController.php` | `Game\Technology\Domain\TechnologyGraph` | constructs graph once per request, reads `tier()`/`prerequisites()` | ✓ WIRED | `grep` confirms `use` + instantiation at line 76 |
| `ResearchReconciler.php` | `ResearchCompletionService::completeOverdueLocked` | dead-worker safety net | ✓ WIRED | Confirmed by grep and by passing "costs nothing when the worker dies" test |
| `packages/game-data/src/validate.ts` | `packages/game-data/src/rules.ts` | CLI imports and runs the rule functions | ✓ WIRED | `from './rules.ts'` import confirmed |
| `apps/mobile/app/technology.tsx` | `useTechnologyQuery` | screen reads the server tree | ✓ WIRED | Import + call confirmed |
| `CitySlotDetailSheet.tsx` | `apps/mobile/app/technology.tsx` | Academy's "Open Research" pushes `/technology` | ✓ WIRED | `router.push('/technology')` confirmed, gated to `building.code === 'academy'` |
| `ResearchService::start()` | `EffectResolver` (via `GameDataCatalog::effectsFor`) | technology completion changes `CityEconomyService` rates | ⚠️ WIRED BUT INERT WITH REAL DATA | The wiring is real and correct; the numeric effect is truncated to zero by placeholder building base values (see gap above) |

### Requirements Coverage

| Requirement | Source Plan(s) | Description | Status | Evidence |
|-------------|-----------------|--------------|--------|----------|
| REQ-06 | 10-01, 10-02, 10-03, 10-04 | Data-driven balancing: no balance number hardcoded in application code | ✓ SATISFIED | Technology costs/durations/effects live exclusively in `technologies.json`; architecture test "keeps cost, duration and effect tables out of PHP" passes; validator (CI-gated) rejects bad data at authoring time |
| REQ-05 | 10-03, 10-04, 10-05 | Timed gameplay anchored to server time only | ✓ SATISFIED (research scope) | `ResearchService::start()` stamps `started_at`/`finishes_at` from the injected `Clock` in UTC (`+00:00` asserted in tests); `BuildDuration::scaled()` reused, not reimplemented; client renders `Timer` off server timestamps with skew correction. (REQ-05 spans build/research/train/march project-wide and remains "Active" in PROJECT.md; only the research slice is this phase's to close.) |

**Process note (not a functional gap):** Plans 10-03 and 10-05 self-declare `requirements: [REQ-06, REQ-09]` / `[REQ-05, REQ-06, REQ-09]` in their frontmatter, but ROADMAP.md's Phase 10 entry officially maps only `REQ-06, REQ-05` to this phase. REQ-09 (idempotent, concurrency-safe commands) is genuinely evidenced here too — the DB-level one-open-research invariant, the idempotency-key debit, and the job/service/reconciler idempotency tests all materially advance it — but since it isn't in the phase's official requirement set, it isn't scored above. This is a metadata inconsistency between plan frontmatter and ROADMAP, not a missing capability; no orphaned requirement exists in the other direction (both REQ-06 and REQ-05 are claimed by at least one plan).

### Anti-Patterns Found

None. Scanned all core Technology-module PHP files, the mobile technology feature components, `rules.ts`, and `technologies.json` for TODO/FIXME/HACK/PLACEHOLDER/"not implemented" markers — none found. No stubbed handlers, no `return null`/`return []` short-circuits standing in for real logic, no console-log-only implementations.

### Human Verification Required

None strictly required for the built mechanism — all automated checks pass. The one item worth a human/product decision is not a "does it work" question but a "should it ship visible-but-currently-invisible" question:

1. **Whether Phase 10 should ship without any player-visible research payoff**

**Test:** Guest → bootstrap → research Agriculture to level 3 (spend ~730 wood/stone/food total, wait ~170s scaled) → observe the food rate on the resource bar.
**Expected (per phase goal):** The food production rate visibly increases.
**Actual today:** The rate does not change at all — confirmed by direct `php artisan tinker` reproduction of `GameDataCatalog::effectsFor()` before/after a real completed research.
**Why human:** This is a product/scheduling call (ship now and fix via Phase 46's balance pass, or hold and add one non-truncated effect now) rather than a code defect that can be "fixed" unilaterally — the placeholder building values are a deliberate, documented, project-wide deferral.

### Gaps Summary

Four of five observable truths are fully and independently verified against the live system: the technology tree is genuinely acyclic and defended in two independent places (a CI-gated authoring-time DFS validator with a proven 5-node-cycle/diamond-non-cycle test, and a runtime cycle guard in `TechnologyGraph::tier()`); the three refusal codes and their fixed precedence are proven live against Docker/Postgres; the one-research-per-player rule is a database invariant, not an application convention; and the mobile tree screen renders all four states and six CTA variants distinctly, pinned by 19 new tests, all green.

The remaining truth — "a completed technology's effect is observable in a recomputed value" — is mechanically correct (the resolver, the persistence, the HTTP flow, and the reconciler are all real and well-tested) but is not actually true against the live game's authored data today. Every one of the 16 technologies' 48 levels uses a `multiply` effect, and the two effect targets any code currently reads (`production.food`, `production.iron`) are multiplied against a real building baseline of exactly 1 (every production building's Phase-09-authored effect is a flat `add:1` at every level). `intdiv(1 * permille, 1000)` equals 1 for every authored permille value, so the entire effect truncates to zero change — independently reproduced here via `php artisan tinker` against the real `GameDataCatalog`. The test written to prove this criterion, `ResearchEffectTest.php`, discloses this honestly in its own comments and substitutes a synthetic, never-persisted 100-unit building baseline to make the arithmetic observable — which proves the resolver works, but does not prove a real player experiences any observable change today. This is a genuine, if narrow, gap between the phase's stated goal and the live system's actual behavior, rooted in a documented, pre-existing, project-wide decision (10-CONTEXT.md/09-CONTEXT.md both defer "balance values beyond plausible placeholders" to Phase 46) rather than sloppy execution — but it should be tracked and closed (or explicitly re-scoped) rather than silently accepted.

---

*Verified: 2026-09-08T17:44:42Z*
*Verifier: Claude (gsd-verifier)*
