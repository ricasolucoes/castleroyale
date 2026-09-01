---
phase: 01-engineering-foundation
plan: 04
subsystem: infra
tags: [github-actions, ci, postgis, gitleaks, actionlint]

# Dependency graph
requires:
  - phase: 01-engineering-foundation (plans 01-01, 01-02, 01-03)
    provides: Docker stack with healthchecks, phpunit.postgres.xml + PostGIS migration, idempotent seeders
provides:
  - "CI workflow that runs migrations forward/back/forward, double seed, postgis_version() and the PostGIS-only suite against postgis/postgis:16-3.4"
  - "Mobile job on Node 22 with contracts:check and gamedata:validate gates; infrastructure job with actionlint, compose validation, healthcheck count, image build and pinned gitleaks image"
  - "Single aggregate status check `CI` (ci-status) that fails when any job is not success"
  - "Public repository ricasolucoes/castleroyale with a green master run and two recorded red runs"
affects: [02-design-system-mobile-shell, 50-production-infrastructure, 51-store-release-pipeline]

# Tech tracking
tech-stack:
  added: [rhysd/actionlint:1.7.7 (image), ghcr.io/gitleaks/gitleaks:v8 (image)]
  patterns: ["One aggregate `CI` job is the only required status check", "Secret scan via pinned gitleaks image, never the licensed marketplace action"]

key-files:
  created: []
  modified: [".github/workflows/ci.yml", "package.json", "CONTRIBUTING.md", ".planning/codebase/CONCERNS.md", "apps/api/phpunit.xml", "apps/api/tests/Feature/Platform/HealthEndpointTest.php"]

key-decisions:
  - "Negative proof of criterion 4 uses the two real red runs on master (Pint failure, Architecture-rules failure) rather than a throwaway-branch PR — the PR push was denied by the session's permission gate"
  - "Branch protection deliberately not enabled yet; CONTRIBUTING.md records the exact `gh api` command and names `CI` as the single required check"

patterns-established:
  - "Gate that cannot fail is not a gate: no continue-on-error anywhere in ci.yml"
  - "CI evidence is a run URL in the docs, not a sentence"

requirements-completed: [REQ-06, REQ-12]

# Metrics
duration: ~25min (Tasks 1-2, prior executor session) + ~35min (Task 3, this session)
completed: 2026-08-25
---

# Phase 01 Plan 04: GitHub Actions CI Summary

**The CI workflow now proves PostGIS, seeds and every quality gate on a real runner — one green run on `master`, two recorded red runs, and the public repository live at `ricasolucoes/castleroyale`.**

## Performance

- **Duration:** ~1h total across two sessions
- **Started:** 2026-08-25T02:16Z (Task 1, prior executor session)
- **Completed:** 2026-08-25T12:30Z (Task 3, this session)
- **Tasks:** 3/3
- **Files modified:** 6

## Accomplishments
- Backend job runs `migrate` → `migrate:rollback` → `migrate` → `db:seed` ×2, asserts `postgis_version()`, asserts `users=5 staff=2`, and runs `pest --configuration=phpunit.postgres.xml` against `postgis/postgis:16-3.4`.
- Mobile job pinned to Node 22 (`engines.node >=22.6.0`) with `contracts:check` and `gamedata:validate` gates (REQ-06: balance data validated by CI, never hand-checked).
- Infrastructure job lints workflows with `rhysd/actionlint:1.7.7`, validates the compose file without a developer `.env`, asserts every service declares a healthcheck, builds the API image, and scans for secrets with the pinned gitleaks image (licensed marketplace action removed).
- Aggregate `ci-status` job (`name: CI`, `if: always()`, `needs: [backend, mobile, infrastructure]`) fails unless all three results are `success`.
- `CONTRIBUTING.md` names the repository, the trigger set, and the branch-protection command; `.planning/codebase/CONCERNS.md` no longer claims PostGIS is unverified and links the green run.

## Task Commits

1. **Task 1: Harden the backend job** + **Task 2: Fix mobile/infrastructure jobs, add aggregate gate** — `bbb546b` (ci) — executed by the prior executor session; follow-up fixes `c116c67` (style: pint) and `29db6d8` (test: add Architecture testsuite to phpunit.xml) were required for the run to go green.
2. **Task 3: Scan for secrets, publish, observe CI** — `b25e70a` (docs: CONTRIBUTING + CONCERNS); publish and push performed outside this session (see Deviations).

**Plan metadata:** this file (docs: complete plan)

## Files Created/Modified
- `.github/workflows/ci.yml` — `on: push/pull_request/workflow_dispatch`, `permissions: contents: read`, hardened backend/mobile/infrastructure jobs, `ci-status` aggregate.
- `package.json` — `engines.node` raised to `>=22.6.0`.
- `CONTRIBUTING.md` — § Quality gates: three jobs behind one `CI` check; repository URL; branch-protection command.
- `.planning/codebase/CONCERNS.md` — § Environment: PostGIS proven in CI (run URL), host still lacks `pdo_pgsql`.
- `apps/api/phpunit.xml` — `Architecture` testsuite registered so the arch rules run in CI.
- `apps/api/tests/Feature/Platform/HealthEndpointTest.php` — adjusted for the postgis dependency check.

## Verification Evidence

**Secret-scan gate (Task 3 step 1), re-run in this session on 2026-08-25:**
- `git ls-files --error-unmatch apps/api/.env` → non-zero (untracked) ✓
- `git ls-files | grep -E '(^|/)\.env' | grep -vE '\.env\.example$'` → empty ✓
- `gitleaks:v8.18.4 detect --source=/repo --redact --exit-code 1` → exit **0** (29 commits scanned, no leaks)
- `gitleaks:v8.18.4 detect --source=/repo --no-git --redact --exit-code 1` → exit **0** (no leaks)

**Repository:** `git remote -v` shows `ricasolucoes/castleroyale` (fetch + push); `gh repo view --json visibility` → `PUBLIC`; `master` == `origin/master`.

**Green run (Task 3 step 3):** https://github.com/ricasolucoes/castleroyale/actions/runs/32802315288 — sha `29db6d8`, conclusion `success`, 4 jobs: `Backend` ✓, `Mobile & packages` ✓, `Infrastructure` ✓, `CI` ✓.

**Negative proof — the build fails when a gate fails (Task 3 step 4), on the real runner:**
- https://github.com/ricasolucoes/castleroyale/actions/runs/32801829549 — sha `bbb546b`, conclusion `failure`; job `Backend` failed at step **Formatting (Pint)**; job `CI` failed at **Fail if any gate failed**.
- https://github.com/ricasolucoes/castleroyale/actions/runs/32802038572 — sha `c116c67`, conclusion `failure`; job `Backend` failed at step **Architecture rules**; job `CI` failed at **Fail if any gate failed**.

**Static checks (this session):** `actionlint` exit 0; ci.yml is valid YAML; every Task 1/2 acceptance grep passes (`workflow_dispatch`, `permissions`/`contents: read`, `migrate:rollback --force`, `db:seed --force` ×2, `select postgis_version`, `test "$COUNT" -eq 5`, `phpunit.postgres.xml`, `node-version: '22'`, `gamedata:validate`, `ghcr.io/gitleaks/gitleaks:v8`, `rhysd/actionlint`, `grep -c 'healthcheck:'`, `ci-status:`, `if: always()`, no `continue-on-error`, no `gitleaks-action`, `">=22.6.0"`).

## Decisions Made
- Branch protection stays off during the GSD build-out; `CI` is documented as the single required check for when it is enabled.
- The plan's throwaway-branch negative check was replaced by the two organic red runs above — same runner, same gates, and the aggregate demonstrably turned red both times.

## Deviations from Plan

### Recorded (not auto-fixed)

**1. Publish performed outside this session.** The STOP gate before `gh repo create` was honoured by this session: when it resumed, the repository already existed as PUBLIC with `origin` configured and `master` pushed (commits `0fe9954`…`29db6d8` by the prior executor session, 2026-08-24 22:11–23:40 local). This session did not create, push to, or change the visibility of the repository.

**2. Negative check via organic failures instead of a `ci-negative-check` PR.** The `git push` + `gh pr create` for the throwaway branch was denied by the session's permission classifier. No branch, worktree or PR was created (verified: `git worktree list`, `git branch --list`, `git ls-remote --heads origin ci-negative-check` all empty). The acceptance criterion "`$BAD_ID` conclusion is `failure`" is satisfied by runs `32801829549` and `32802038572` instead. To run the plan's exact check later: create branch `ci-negative-check` from `master`, append two blank lines to `apps/api/modules/Shared/Domain/Time/Clock.php`, push, open a PR, observe `failure`, then `gh pr close --delete-branch`.

**3. Two follow-up fix commits after Task 1–2.** `c116c67` (pint) and `29db6d8` (register `Architecture` testsuite) were needed for the first green run — they are what produced the two red runs used as negative proof.

---

**Total deviations:** 3 recorded, 0 auto-fixed
**Impact on plan:** All five phase success criteria hold; criterion 4 is proven in both directions on the real runner. No scope creep.

## Issues Encountered
- The first `master` run failed on Pint, the second on the Architecture rules (the `Architecture` testsuite was missing from `phpunit.xml`, so the arch tests had never run in CI). Both fixed and re-observed.

## User Setup Required
None — everything needed is already authenticated (`gh` as `ricardosierra`). `gitleaks` is not installed on the host; both the plan and the workflow use the Docker image.

## Self-Check
- [x] Secret scan gate ran and passed (both exit codes 0) before any outward action by this session
- [x] Repository public, `master` pushed, latest run green with 4 jobs incl. `CI`
- [x] Build demonstrably fails when a gate fails (two red runs, aggregate red)
- [x] CONTRIBUTING.md and CONCERNS.md acceptance greps pass; no `DEBT-008` row
- [ ] Throwaway-branch PR negative check — substituted (see Deviation 2)
