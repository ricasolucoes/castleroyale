# Contributing

## Before you start

Read [`AGENTS.md`](AGENTS.md) — it applies to humans too. Then read the phase you
are working on in `.planning/`.

## Workflow

1. Branch from `master`: `feat/phase-07-city-foundation`
2. Work the phase plan. Do not widen scope.
3. Run every quality gate.
4. Open a pull request describing what changed and which success criteria it meets.

## Commits

Conventional commits, scoped by module, one logical change each:

```
feat(world): add region viewport query
fix(economy): re-check affordability inside the lock
test(combat): cover deterministic replay across versions
docs(adr): record content versioning decision
```

Types: `feat` `fix` `docs` `refactor` `test` `perf` `build` `ci` `chore`

**Never add `Co-Authored-By` trailers.** Never a single giant commit called
"updates".

## Quality gates

All of these must pass. Run them; do not assume.

```bash
cd apps/api
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --memory-limit=1G
./vendor/bin/pest

npm run typecheck && npm run lint && npm test
npm run contracts:check
```

The same gates run in GitHub Actions as three parallel jobs — **Backend**,
**Mobile & packages**, **Infrastructure** — behind one aggregate check, **CI**.
Make that single check the required one in branch protection.

`make ci` runs the same commands locally, including the PostgreSQL-only suite that
the SQLite host suite cannot cover.

The repository is <https://github.com/ricasolucoes/project-dominion>. CI runs on every
push to `master` and `develop` and on every pull request.

Branch protection is **not** enabled yet, deliberately: GSD phases commit directly to
`master`, and requiring a pull request would stall the build-out. When it is turned
on, the single required status check is **`CI`** (the `ci-status` aggregate) — not the
three individual jobs:

    gh api -X PUT repos/ricasolucoes/project-dominion/branches/master/protection \
      -F required_status_checks[strict]=true \
      -F 'required_status_checks[contexts][]=CI' \
      -F enforce_admins=false -F required_pull_request_reviews= -F restrictions=

## Code review checklist

- [ ] Does it trust the client anywhere? (cost, duration, result, quantity)
- [ ] Does it use a float for anything a player owns?
- [ ] Does every query filter `world_id`?
- [ ] Does resource spending lock and re-check **inside** the lock?
- [ ] Is the mutating command idempotent, and is that tested?
- [ ] Is every queued job that grants value idempotent?
- [ ] Are balance numbers in `packages/game-data/`, not PHP?
- [ ] Are new errors in the `ErrorCode` enum **and** `openapi.yaml`?
- [ ] Are new broadcast channels tested for allow **and** deny?
- [ ] Is there a named test for the security concern, not just an assertion?
- [ ] Are TS types generated rather than hand-written?
- [ ] Is server state in TanStack Query and not Zustand?
- [ ] Any hardcoded colour, spacing or font size?
- [ ] Any `TODO` without an entry in `.planning/codebase/CONCERNS.md`?

## Versioning

Starts at **v0.1.0**. `v1.0.0` is reserved for a mature product in production with
real players — never the first release. Features bump minor, fixes bump patch.

Closing a version: bump `package.json`, move `[Unreleased]` into a dated section in
`CHANGELOG.md`, commit, and create an annotated tag `vX.Y.Z`.

## Changelog

`CHANGELOG.md` uses the **Release Notes** format — `# Release Notes`, sections
`### ✨ Novidades`, `### 🎨 Melhorias`, `### 🐛 Correções`, `### 🔧 Técnico`, items
always as `- [x]` or `- [ ]`.

Never Keep-a-Changelog, even if a branch brings that format.

## Technical debt

Accepted debt needs an **ID**, a **reason**, an **impact** and a **target phase** in
`.planning/codebase/CONCERNS.md`. A `TODO` without one is a defect.

## Reporting a problem with the plan

If a locked decision is genuinely unworkable, do not quietly design around it.
Write an ADR, record it in `docs/gsd/DECISIONS.md`, then continue.
