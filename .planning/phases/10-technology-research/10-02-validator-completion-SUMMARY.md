---
phase: 10-technology-research
plan: 02
subsystem: game-data
tags: [validator, json-schema, node-test, ci, i18n, dag]

# Dependency graph
requires:
  - phase: 10-01-technology-catalogue-effects-model
    provides: "packages/game-data/data/technologies.json (a real DAG with a two-prerequisite node and a cross-category edge) and its translation keys, the real dataset this plan's tests pin as passing"
provides:
  - "packages/game-data/src/rules.ts — every validation rule as a pure, exported, individually testable function"
  - "The three previously-missing rules: checkTranslationKeys, checkRequirementSatisfiable (unlock-reachability), checkCrossDatasetReferences"
  - "packages/game-data/test/rules.test.ts — 12 tests under node's built-in test runner, one red-path per rule plus a real-dataset regression pin"
  - "npm run gamedata:validate wired as a thin CLI over rules.ts; the CI check that already ran it and npm test now cover the full rule set"
affects: [10-03-research-queue-effects, 10-05-research-command-reconciler, 10-04-mobile-technology-tree]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Validation rules are pure functions (dataset name + rows in, Problem[] out) — no filesystem access, no global mutable state — so every rule is testable without a fake data/ directory"
    - "node --experimental-strip-types --test is the test runner for this package; no jest, no second test framework for four files"
    - "REQUIREMENT_TYPE_BY_DATASET is inverted once (type -> dataset) to drive checkCrossDatasetReferences, rather than hardcoding a second type-to-dataset map"

key-files:
  created:
    - packages/game-data/src/rules.ts
    - packages/game-data/test/rules.test.ts
    - packages/game-data/test/fixtures/README.md
  modified:
    - packages/game-data/src/validate.ts
    - packages/game-data/package.json
    - packages/localization/locales/en/mvp.json
    - packages/localization/locales/pt-BR/mvp.json
    - packages/localization/locales/es/mvp.json

key-decisions:
  - "findCycle moved into rules.ts verbatim, per the plan's own trap — its DFS state/stack mechanics and cycle-path return were not touched, only relocated and exported"
  - "checkCrossDatasetReferences and checkRequirementSatisfiable take the full Dataset[] / a pre-built Map<string,number> respectively, computed once by the CLI, rather than each rule re-reading every dataset — keeps every rule a pure function of the data it's actually given"
  - "Task 4 (wire validator into CI) required no ci.yml change: the existing 'Mobile & packages' job already runs `npm run gamedata:validate` (its own 'Game data is valid' step) and `npm test` (its 'Tests' step, which is workspace-wide `--if-present`) — adding a 'test' script to game-data/package.json in Task 1 was sufficient for the existing CI to pick up the new suite automatically"

requirements-completed: [REQ-06]

# Metrics
duration: ~15min
completed: 2026-09-08
---

# Phase 10 Plan 02: Validator Completion Summary

**Completed the game data validator to all six rules `docs/game-design/technology.md` mandates — translation-key resolution, unlock-reachability, and cross-dataset references — extracted the existing rules into a pure, individually-tested module (`rules.ts`) under node's built-in test runner, and fixed a genuine pre-existing bug the new rule surfaced (a unit with no translated name anywhere).**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-09-08T16:15:00Z (approx.)
- **Completed:** 2026-09-08T16:31:03Z
- **Tasks:** 3 code tasks + 1 no-op verification task (Task 4)
- **Files modified:** 8 (3 created, 5 modified)

## Accomplishments
- `packages/game-data/src/rules.ts` — `checkDuplicateCodes`, `checkNumericFields`, `buildDependencyGraph`, `findCycle` (moved verbatim, not rewritten), `checkSameDatasetReferences`, plus the three new rules: `checkTranslationKeys`, `checkRequirementSatisfiable`, `checkCrossDatasetReferences`, and the `buildMaxLevelsByTypeAndCode` helper feeding the reachability check
- `validate.ts` reduced to a thin CLI: read `data/` and the locale catalogues, hand data to the pure rules, print, exit — same output contract, same early exits
- `packages/game-data/test/rules.test.ts` — 12 tests: one red-path per rule (each asserting the offending id is named in the message), a five-node cycle proving the detector is a real graph walk, a diamond shape proving it doesn't confuse "visited" with "on the stack", the deliberate lower-level self-requirement carve-out, and a regression pin loading the real `buildings.json`/`technologies.json`/`units.json`/locale catalogues and asserting zero problems
- Fixed a real bug the new translation-key rule caught immediately: `units.json`'s `militia` unit had a `name_key` (`units.militia`) that resolved in no locale catalogue at all — added the `units` namespace with a translated name to `en`/`pt-BR`/`es` `mvp.json`
- `npm run gamedata:validate` exits 0 against all 6 real datasets; `npm test` (root, workspace-wide) now runs the new suite via the existing CI "Tests" step with no CI file changes needed

## Task Commits

Each task was committed atomically:

1. **Task 1: Extract the rules into a testable module** - `c58e4e9` (refactor)
2. **Task 2: The three missing rules** - `9d1a1c6` (feat)
3. **Task 3: One red-path test per rule** - `16467ed` (test)
4. **Task 4: Wire the validator into CI** - no commit; verified already satisfied (see Deviations)

**Plan metadata:** pending (this commit)

## Files Created/Modified
- `packages/game-data/src/rules.ts` - every validation rule as an exported pure function
- `packages/game-data/src/validate.ts` - thin CLI over `rules.ts`; loads locale catalogues once, orchestrates all rules in the same order the pre-refactor script did
- `packages/game-data/package.json` - added `"type": "module"` (removes a Node runtime warning now that both `validate.ts` and the tests are ESM) and a `"test": "node --experimental-strip-types --test test/*.test.ts"` script
- `packages/game-data/test/rules.test.ts` - the 12-test red-path suite
- `packages/game-data/test/fixtures/README.md` - why every fixture is built in-memory, never stored under `data/`
- `packages/localization/locales/{en,pt-BR,es}/mvp.json` - added the `units` namespace (`militia`) so the real dataset satisfies the new translation-key rule

## Decisions Made
- Kept `findCycle` byte-for-byte per the plan's trap; only its location and export changed.
- Built `checkCrossDatasetReferences` and `checkRequirementSatisfiable`'s max-level map once, over all datasets, in the CLI — each rule stays a pure function of exactly the data passed to it, with no implicit re-reading of the filesystem inside a "rule."
- Task 4 needed no `ci.yml` edit: `npm run gamedata:validate` was already a required CI step (`Mobile & packages` job, "Game data is valid"), and the job's "Tests" step already runs workspace-wide `npm test`, which now includes game-data's new suite via the `"test"` script Task 1 added. Duplicating those two commands as new steps, as the plan's action block suggested, would have been redundant — the same reasoning as Task 1's "do not rewrite what already exists correctly."

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Fixed a pre-existing dangling translation key on `units.json`**
- **Found during:** Task 2 (wiring `checkTranslationKeys` and running `npm run gamedata:validate` against the real datasets)
- **Issue:** `units.json`'s only unit, `militia`, has `name_key: "units.militia"`, but no locale catalogue (`en`, `pt-BR`, `es`) had ever defined a `units` namespace — the key resolved to nothing everywhere. This predates this plan (units.json/schema were authored in an earlier phase, before any translation-key validation existed anywhere in the codebase — PHP's own `ImportGameDataCommand::checkTranslations` only ever checked `buildings`, never `units`). The new rule is working exactly as designed by catching it.
- **Fix:** Added a `"units": { "militia": "..." }` entry to `en`, `pt-BR`, and `es` `mvp.json`, following the same flat code-to-name-string convention already used for `buildings` and `technologies`.
- **Files modified:** `packages/localization/locales/en/mvp.json`, `packages/localization/locales/pt-BR/mvp.json`, `packages/localization/locales/es/mvp.json`
- **Verification:** `npm run gamedata:validate` now exits 0 with "Game data OK — 6 dataset(s) validated."; the new "accepts the real authored datasets" test asserts zero problems including units.json.
- **Committed in:** `9d1a1c6` (Task 2 commit)

**2. [Task 4 — no-op, not a fix] CI already satisfied the plan's objective**
- **Found during:** Task 4 (reading `.github/workflows/ci.yml` before adding the two suggested steps)
- **Issue:** The plan's action block assumed `gamedata:validate` and the game-data test suite were not yet wired into CI. In fact `npm run gamedata:validate` has run as a required step ("Game data is valid") in the "Mobile & packages" job since an earlier phase, and that same job's "Tests" step already runs workspace-wide `npm test` — which picks up game-data's tests automatically the moment Task 1 added a `"test"` script to its `package.json`.
- **Resolution:** No file change. Verified directly: `grep -c "gamedata:validate" .github/workflows/ci.yml` = 1, `grep -c "game-data" .github/workflows/ci.yml` = 2, YAML still parses, job count unchanged (`backend`, `mobile`, `infrastructure`, `ci-status`), and `npm test` from the repo root runs and passes all 12 new tests alongside the existing 86.
- **Impact:** None — the acceptance criteria and the underlying `must_haves` truth ("CI fails when a dataset is bad") were already true; adding duplicate steps would only have added redundant CI time.

---

**Total deviations:** 1 auto-fixed (blocking), 1 no-op task documented (Task 4 required no change)
**Impact on plan:** The translation-key fix was necessary for Task 2's own acceptance criteria ("the real datasets produce zero problems") and is exactly the class of bug this plan's rule exists to catch. No scope creep.

## Issues Encountered
None beyond the deviation above.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- `packages/game-data/src/rules.ts` exports every rule as a pure function; 10-03/10-05 can reuse `buildMaxLevelsByTypeAndCode`/`checkRequirementSatisfiable`'s reachability logic if research-queue validation ever needs the same shape server-side (it currently doesn't — `GameDataCatalog::technologyLevel()` from 10-01 is the server's read path).
- CI already fails on a bad dataset (validator) and a bad rule implementation (test suite) — no further CI wiring needed for this phase's remaining plans.
- No blockers for 10-03 (research queue), which depends only on 10-01 and touches disjoint files (`apps/api/modules/Technology/`, `apps/api/modules/Economy/`, migrations) from this plan's `packages/game-data/` and `.github/workflows/ci.yml` scope.

---
*Phase: 10-technology-research*
*Completed: 2026-09-08*

## Self-Check: PASSED

All 9 created/modified files verified present on disk; all three task commit hashes
(c58e4e9, 9d1a1c6, 16467ed) verified present in `git log`.
