---
phase: 01-engineering-foundation
plan: 04
type: execute
wave: 4
depends_on: ["01-01", "01-02", "01-03"]
files_modified:
  - .github/workflows/ci.yml
  - package.json
  - CONTRIBUTING.md
  - .planning/codebase/CONCERNS.md
autonomous: false
requirements: [REQ-06, REQ-12]

must_haves:
  truths:
    - "The workflow runs three parallel jobs — backend, mobile, infrastructure — plus an aggregating status gate"
    - "The backend job runs pint --test, phpstan, pest, migrations forward-and-back against real postgis, a double seed, and the PostGIS-only suite"
    - "The mobile job runs typecheck, lint, tests, contract drift and game-data validation"
    - "The infrastructure job validates docker-compose.yml, builds the API image and scans for committed secrets without needing a paid action licence"
    - "A failure in any job fails the aggregate `ci-status` job, so a single required check protects the branch"
    - "Every command the workflow runs also runs locally through `make ci`"
  artifacts:
    - path: ".github/workflows/ci.yml"
      provides: "Backend / mobile / infrastructure jobs plus the ci-status aggregate gate"
      contains: "ci-status"
    - path: "package.json"
      provides: "Node engine range that matches what the toolchain actually needs"
      contains: "22.6"
  key_links:
    - from: ".github/workflows/ci.yml:backend"
      to: "apps/api/phpunit.postgres.xml"
      via: "pest --configuration=phpunit.postgres.xml against the postgis service container"
      pattern: "phpunit.postgres.xml"
    - from: ".github/workflows/ci.yml:ci-status"
      to: "needs.backend.result / needs.mobile.result / needs.infrastructure.result"
      via: "if: always() aggregation"
      pattern: "needs.backend.result"
    - from: ".github/workflows/ci.yml:infrastructure"
      to: "docker compose config"
      via: "compose validation with no apps/api/.env present"
      pattern: "docker compose config"
---

<objective>
Turn the CI workflow from something that looks right into something that is right.

`.github/workflows/ci.yml` exists from Phase 00 but has never executed. Reading it
against the repository as it actually is turns up four breakages:

1. The infrastructure job runs `docker compose config` on a checkout with no
   `apps/api/.env` — gitignored, so absent. (Plan 01-01 fixed the compose side; this
   plan makes the job robust regardless.)
2. `gitleaks/gitleaks-action@v2` requires a paid licence key for organisation-owned
   repositories and will fail there with no useful message.
3. The mobile job pins Node 20, but `packages/game-data` validates through
   `node --experimental-strip-types`, which needs Node ≥ 22.6.
4. There is no PostGIS-only test step and no aggregating status check, so branch
   protection has three checks to configure instead of one.

Purpose: satisfies ROADMAP Phase 01 success criterion 4 and puts REQ-06 (game data
validated in CI) and REQ-12 (every quality gate observable) behind an automated gate.

Output: a corrected workflow, a `make ci` that mirrors it exactly, and an honest
record of the one thing that cannot be demonstrated locally.
</objective>

<execution_context>
@/Users/sierra/.claude/get-shit-done/workflows/execute-plan.md
@/Users/sierra/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/ROADMAP.md
@.planning/STATE.md
@.planning/phases/01-engineering-foundation/01-CONTEXT.md
@.planning/codebase/TESTING.md
@.planning/codebase/CONCERNS.md
@.github/workflows/ci.yml
@CONTRIBUTING.md

<interfaces>
<!-- Contracts the executor needs. Do not go hunting for these. -->

Root `package.json` scripts the mobile job can call:
```
typecheck          npm run typecheck --workspaces --if-present
lint               npm run lint --workspaces --if-present
test               npm run test --workspaces --if-present
contracts:check    npm run check --workspace=@dominion/contracts
gamedata:validate  npm run validate --workspace=@dominion/game-data
```
`gamedata:validate` runs `node --experimental-strip-types src/validate.ts`. That flag
requires **Node ≥ 22.6**; the workflow currently pins Node 20. The script exits 0 with
"No data/ directory yet" until Phase 09 authors the first dataset — that is expected
and is still a real gate (it fails on malformed JSON the moment data exists).

`apps/api` commands (all run from `working-directory: apps/api`):
```
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --memory-limit=1G --no-progress
./vendor/bin/pest --group=arch
./vendor/bin/pest
./vendor/bin/pest --configuration=phpunit.postgres.xml   # created by plan 01-02
php artisan migrate --force / migrate:rollback --force / db:seed --force
```

`apps/api/phpunit.postgres.xml` (plan 01-02) forces `DB_CONNECTION=pgsql` and
`DB_DATABASE=dominion_test` with `force="true"`. The workflow therefore only needs to
supply `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`. The existing postgres
service container already uses `POSTGRES_DB: dominion_test`, user/password
`dominion`/`dominion` — those match.

`apps/api/phpunit.xml` sets `DB_CONNECTION=sqlite` **without** `force`, and PHPUnit
does not overwrite an environment variable that is already set. That is why the plain
`./vendor/bin/pest` step runs on SQLite while later steps can export pgsql.

Makefile target `ci` (plan 01-01):
```make
ci: lint analyse test
	$(MAKE) test-postgres
	npm run typecheck
	npm test
```

GitHub-hosted `ubuntu-latest` runners ship `docker`, `psql` (postgresql-client) and
`gh` preinstalled. No setup step is needed for any of them.
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Harden the backend job — real PostGIS gates, forward-and-back migrations, double seed</name>
  <files>.github/workflows/ci.yml</files>

  <read_first>
    - .github/workflows/ci.yml (the file being modified — read the whole backend job, including the service containers)
    - apps/api/phpunit.postgres.xml (created by plan 01-02 — what it forces and what it leaves to the environment)
    - apps/api/phpunit.xml (why the plain pest step still runs on SQLite)
    - apps/api/database/migrations/0000_01_01_000000_enable_postgis_extension.php (the migration this job must prove)
    - apps/api/database/seeders/DatabaseSeeder.php (what a double seed must not duplicate)
  </read_first>

  <action>
Edit `.github/workflows/ci.yml`. Backend job only.

**1. Add workflow-level permissions and a manual trigger.** Replace the `on:` block
and add `permissions` immediately after it:

```yaml
on:
  push:
    branches: [master, develop]
  pull_request:
  workflow_dispatch:

permissions:
  contents: read
```

`workflow_dispatch` matters: it is the only way to trigger a run without pushing.

**2. Replace the existing "Migrations against PostgreSQL + PostGIS" step** with this,
keeping the same `env:` block and adding to it:

```yaml
      - name: Migrations and seeds against PostgreSQL + PostGIS
        # The gate the local SQLite suite cannot provide. Forward, back, forward
        # proves every down() works; seeding twice proves idempotency.
        env:
          DB_CONNECTION: pgsql
          DB_HOST: 127.0.0.1
          DB_PORT: 5432
          DB_DATABASE: dominion_test
          DB_USERNAME: dominion
          DB_PASSWORD: dominion
          CACHE_STORE: redis
          REDIS_HOST: 127.0.0.1
          QUEUE_CONNECTION: redis
        run: |
          php artisan migrate --force
          php artisan migrate:rollback --force
          php artisan migrate --force
          php artisan db:seed --force
          php artisan db:seed --force

      - name: PostGIS extension is really enabled
        run: |
          PGPASSWORD=dominion psql -h 127.0.0.1 -U dominion -d dominion_test \
            -tAc "select postgis_version();" | tee /tmp/postgis.txt
          test -s /tmp/postgis.txt

      - name: Seeds are idempotent
        run: |
          COUNT=$(PGPASSWORD=dominion psql -h 127.0.0.1 -U dominion -d dominion_test \
            -tAc "select count(*) from users;")
          STAFF=$(PGPASSWORD=dominion psql -h 127.0.0.1 -U dominion -d dominion_test \
            -tAc "select count(*) from users where is_staff;")
          echo "users=$COUNT staff=$STAFF"
          test "$COUNT" -eq 5
          test "$STAFF" -eq 2

      - name: PostGIS-only test suite
        env:
          DB_HOST: 127.0.0.1
          DB_PORT: 5432
          DB_USERNAME: dominion
          DB_PASSWORD: dominion
        run: ./vendor/bin/pest --configuration=phpunit.postgres.xml
```

`DB_CONNECTION` and `DB_DATABASE` are deliberately omitted from the last step —
`phpunit.postgres.xml` forces both.

**3. Leave untouched:** the service containers, the `setup-php` step (its extension
list already includes `pdo_pgsql`), `composer validate --strict`, the cache step, the
Pint / PHPStan / arch / pest steps, and `defaults.run.working-directory: apps/api`.
Note that the `psql` steps run in `apps/api` — that is harmless, `psql` needs no files.

**4. Add no `continue-on-error` anywhere.** A gate that cannot fail is not a gate.
  </action>

  <verify>
    <automated>cd /Users/sierra/Dev/Jogos/MmoMobile && python3 -c "import yaml; yaml.safe_load(open('.github/workflows/ci.yml'))" && grep -q workflow_dispatch .github/workflows/ci.yml && grep -q 'phpunit.postgres.xml' .github/workflows/ci.yml && grep -q 'Seeds are idempotent' .github/workflows/ci.yml && docker run --rm -v "$PWD:/repo" -w /repo rhysd/actionlint:1.7.7 -color</automated>
  </verify>

  <acceptance_criteria>
    - `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/ci.yml'))"` exits 0 (valid YAML)
    - `actionlint` reports no findings
    - `grep -q 'workflow_dispatch:' .github/workflows/ci.yml` succeeds
    - `grep -q 'permissions:' .github/workflows/ci.yml` succeeds and `contents: read` follows it
    - `grep -q 'migrate:rollback --force' .github/workflows/ci.yml` succeeds
    - `grep -c 'php artisan db:seed --force' .github/workflows/ci.yml` returns 2 (seeded twice)
    - `grep -q 'select postgis_version' .github/workflows/ci.yml` succeeds
    - `grep -q 'test "$COUNT" -eq 5' .github/workflows/ci.yml` succeeds
    - `grep -q 'pest --configuration=phpunit.postgres.xml' .github/workflows/ci.yml` succeeds
    - `grep -q 'continue-on-error' .github/workflows/ci.yml` returns nothing
    - The `postgis/postgis:16-3.4` service container and the `setup-php` extension list are unchanged
  </acceptance_criteria>

  <done>The backend job proves PostGIS is enabled, that every migration reverses, that seeding twice is safe, and that the PostgreSQL-only suite passes — none of which the SQLite host suite can show.</done>
</task>

<task type="auto">
  <name>Task 2: Fix the mobile and infrastructure jobs and add the aggregate status gate</name>
  <files>.github/workflows/ci.yml, package.json, CONTRIBUTING.md</files>

  <read_first>
    - .github/workflows/ci.yml (the file being modified — read the mobile and infrastructure jobs in full)
    - package.json (the file being modified — the `engines` block and the script names)
    - packages/game-data/package.json (the `validate` script and the Node flag it needs)
    - docker-compose.yml (as updated by plan 01-01 — `env_file: required: false`, seven healthchecks)
    - CONTRIBUTING.md (the file being modified — § "Quality gates")
  </read_first>

  <action>
Edit `.github/workflows/ci.yml`, `package.json` and `CONTRIBUTING.md`.

**1. Mobile job — Node 22 and the game-data gate.** Replace the whole `mobile` job
with:

```yaml
  mobile:
    name: Mobile & packages
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-node@v4
        with:
          # 22.6+ — packages/game-data validates through
          # `node --experimental-strip-types`, which does not exist on Node 20.
          node-version: '22'
          cache: npm

      - run: npm ci

      - name: Typecheck
        run: npm run typecheck

      - name: Lint
        run: npm run lint

      - name: Tests
        run: npm test

      - name: Contracts match the OpenAPI spec
        run: npm run contracts:check

      - name: Game data is valid
        # REQ-06: balance lives in packages/game-data and is validated here, never
        # hand-checked. Exits 0 with "no datasets yet" until Phase 09.
        run: npm run gamedata:validate
```

**2. Root `package.json` — align `engines` with what the toolchain needs.** Change:

```json
  "engines": {
    "node": ">=20.19.0",
    "npm": ">=10.0.0"
  },
```

to:

```json
  "engines": {
    "node": ">=22.6.0",
    "npm": ">=10.0.0"
  },
```

22.6 is the version that introduced `--experimental-strip-types`. Leaving 20 there
documents a version the repository does not actually support.

**3. Infrastructure job — no licensed action, no missing .env, and prove the
healthchecks.** Replace the whole `infrastructure` job with:

```yaml
  infrastructure:
    name: Infrastructure
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Lint the workflows themselves
        run: |
          docker run --rm -v "$PWD:/repo" -w /repo rhysd/actionlint:1.7.7 -color

      - name: Validate compose file without a developer .env
        # apps/api/.env is gitignored, so it is absent here. The compose file marks
        # it `required: false` (plan 01-01) precisely so this works.
        run: docker compose config --quiet

      - name: Every service declares a healthcheck
        run: |
          services=$(docker compose config --services | wc -l)
          probes=$(docker compose config | grep -c 'healthcheck:')
          echo "services=$services probes=$probes"
          test "$services" -eq "$probes"

      - name: Build API image
        run: docker build -f infrastructure/docker/api/Dockerfile --target development .

      - name: Scan for committed secrets
        # The marketplace action requires a paid licence for organisation-owned
        # repositories; the image does not.
        run: |
          docker run --rm -v "$PWD:/repo" ghcr.io/gitleaks/gitleaks:v8.21.2 \
            detect --source=/repo --redact --verbose --exit-code 1
```

If `ghcr.io/gitleaks/gitleaks:v8.21.2` 404s, pin the newest published v8 tag instead
and record the tag you used in the SUMMARY. Do not switch back to the action.

**4. Add the aggregate gate** as the last job in the file:

```yaml
  ci-status:
    name: CI
    runs-on: ubuntu-latest
    if: always()
    needs: [backend, mobile, infrastructure]
    steps:
      - name: Fail if any gate failed
        # One required status check for branch protection instead of three, and it
        # cannot pass by a job being skipped or cancelled.
        run: |
          echo "backend=${{ needs.backend.result }}"
          echo "mobile=${{ needs.mobile.result }}"
          echo "infrastructure=${{ needs.infrastructure.result }}"
          test "${{ needs.backend.result }}" = "success"
          test "${{ needs.mobile.result }}" = "success"
          test "${{ needs.infrastructure.result }}" = "success"
```

**5. Update `CONTRIBUTING.md` § "Quality gates".** Append after the existing code
block:

```
The same gates run in GitHub Actions as three parallel jobs — **Backend**,
**Mobile & packages**, **Infrastructure** — behind one aggregate check, **CI**.
Make that single check the required one in branch protection.

`make ci` runs the same commands locally, including the PostgreSQL-only suite that
the SQLite host suite cannot cover.
```

Then run `actionlint` and `make ci` locally.
  </action>

  <verify>
    <automated>cd /Users/sierra/Dev/Jogos/MmoMobile && docker run --rm -v "$PWD:/repo" -w /repo rhysd/actionlint:1.7.7 -color && grep -q "node-version: '22'" .github/workflows/ci.yml && grep -q 'gamedata:validate' .github/workflows/ci.yml && grep -q 'ghcr.io/gitleaks/gitleaks' .github/workflows/ci.yml && grep -q 'ci-status:' .github/workflows/ci.yml && grep -q '>=22.6.0' package.json && make ci</automated>
  </verify>

  <acceptance_criteria>
    - `actionlint` reports no findings
    - `grep -q "node-version: '22'" .github/workflows/ci.yml` succeeds
    - `grep -q 'npm run gamedata:validate' .github/workflows/ci.yml` succeeds
    - `grep -q 'gitleaks/gitleaks-action' .github/workflows/ci.yml` returns nothing (licensed action removed)
    - `grep -q 'ghcr.io/gitleaks/gitleaks:v8' .github/workflows/ci.yml` succeeds
    - `grep -q 'rhysd/actionlint' .github/workflows/ci.yml` succeeds
    - `grep -q "grep -c 'healthcheck:'" .github/workflows/ci.yml` succeeds
    - `grep -q 'ci-status:' .github/workflows/ci.yml` succeeds and its `needs:` lists backend, mobile and infrastructure
    - `grep -q 'if: always()' .github/workflows/ci.yml` succeeds
    - `grep -q '">=22.6.0"' package.json` succeeds
    - `grep -q 'Mobile & packages' CONTRIBUTING.md` succeeds
    - `make ci` exits 0
    - Deliberate-failure check: append `$x = 1 ;` (double space before the semicolon) to any file under `apps/api/modules/`, run `make lint`, confirm it exits non-zero, then revert the file with `git checkout --`
  </acceptance_criteria>

  <done>Every job runs commands that actually exist on the runner it is given, no gate depends on a paid licence, and one aggregate check reflects all three jobs.</done>
</task>

<task type="checkpoint:decision" gate="blocking">
  <name>Task 3: Decide how success criterion 4 is proven — this repository has no git remote</name>
  <files>.planning/codebase/CONCERNS.md, CONTRIBUTING.md</files>

  <read_first>
    - .github/workflows/ci.yml (as completed by Tasks 1 and 2)
    - .planning/ROADMAP.md (§ Phase 01, success criterion 4)
    - .planning/codebase/CONCERNS.md (the file that will record this if it stays open)
    - docs/gsd/EXECUTION_RULES.md (§ Definition of Done — "should work" is not a status)
  </read_first>

  <decision>
`git remote -v` returns nothing: this repository exists only on this machine. Success
criterion 4 says *"GitHub Actions runs lint, static analysis, backend tests and mobile
typecheck on push, and fails the build when any gate fails."* That cannot be observed
without a remote, and whether this code is pushed to GitHub is the user's call, not
Claude's.

Everything that can be verified locally already has been: `actionlint` is clean, the
YAML parses, and `make ci` runs the same commands the workflow runs and passes.
  </decision>

  <context>
What is still unproven if no run happens: that the runner's `setup-php` extension list
resolves, that the `postgis/postgis:16-3.4` service container becomes healthy in CI,
that `npm ci` succeeds on a clean cache, and that the `ci-status` aggregation reports
correctly. These are exactly the things that only a real run shows.
  </context>

  <options>
    <option id="push-and-observe">
      <name>Add a remote, push, and watch the run</name>
      <pros>Criterion 4 becomes demonstrably true. `gh run watch` output goes straight into the SUMMARY. Every remaining unknown is resolved now rather than in Phase 50.</pros>
      <cons>Publishes the repository (choose private). Requires the user to name the repository and confirm the push.</cons>
    </option>
    <option id="defer-with-debt">
      <name>Accept local verification and record the gap as debt</name>
      <pros>Nothing is published. `make ci` still gates every commit locally.</pros>
      <cons>Criterion 4 stays unproven, so Phase 01 cannot honestly be marked complete against it. The first real run will surface its problems later, when more is riding on it.</cons>
    </option>
  </options>

  <action>
If the user selects **push-and-observe**:

```bash
gh repo create <name> --private --source=. --remote=origin --push
gh workflow run ci.yml --ref master
gh run watch
gh run view --log-failed        # only if something failed
```

Paste the final `gh run view` summary into `01-04-SUMMARY.md`. Then flip the branch
protection note in CONTRIBUTING.md from advice to fact.

If the user selects **defer-with-debt**:

Add a row to the "Accepted debt" table in `.planning/codebase/CONCERNS.md`:

```
| DEBT-008 | CI has never executed — the repository has no git remote | Workflow is lint-clean and mirrored by `make ci`, but the runner environment is unproven | The phase that adds the remote (at the latest Phase 50) |
```

and add a matching line under `## Environment`:

```
**GitHub Actions has never run.** `git remote -v` is empty. `.github/workflows/ci.yml`
passes `actionlint` and every command it runs passes locally via `make ci`, but no run
has been observed. Do not report "CI is green" until one has.
```

Then record in `01-04-SUMMARY.md` that ROADMAP Phase 01 success criterion 4 is
**partially met**: the workflow is written and locally verified, the run is not
observed. Do not mark it done.
  </action>

  <acceptance_criteria>
    - Exactly one of the two branches above was executed, and the SUMMARY names which
    - If **push-and-observe**: `gh run list --limit 1` shows a completed run and its conclusion is pasted verbatim into the SUMMARY
    - If **defer-with-debt**: `grep -q 'DEBT-008' .planning/codebase/CONCERNS.md` succeeds AND `grep -q 'GitHub Actions has never run' .planning/codebase/CONCERNS.md` succeeds
    - In either case the SUMMARY states explicitly whether criterion 4 is met or partially met — no ambiguous wording
  </acceptance_criteria>

  <verify>
    <automated>{ git remote -v | grep -q origin && gh run list --limit 1 | grep -qi completed; } || { grep -q 'DEBT-008' .planning/codebase/CONCERNS.md && grep -q 'GitHub Actions has never run' .planning/codebase/CONCERNS.md; }</automated>
  </verify>

  <done>Either a real GitHub Actions run has been observed and its conclusion pasted into the SUMMARY, or DEBT-008 is recorded in CONCERNS.md and the SUMMARY states that criterion 4 is only partially met.</done>

  <resume-signal>Select: push-and-observe, or defer-with-debt</resume-signal>
</task>

</tasks>

<verification>
```bash
# The workflow is well-formed
python3 -c "import yaml; yaml.safe_load(open('.github/workflows/ci.yml'))"
docker run --rm -v "$PWD:/repo" -w /repo rhysd/actionlint:1.7.7 -color

# Every command the workflow runs, run locally
make ci

# The gates actually fail (do not skip this — a gate that cannot fail is not a gate)
printf '\n' >> apps/api/modules/Shared/Domain/Time/Clock.php   # trailing blank line
make lint    # MUST exit non-zero
git checkout -- apps/api/modules/Shared/Domain/Time/Clock.php
make lint    # MUST exit 0

# The compose validation the infrastructure job performs
mv apps/api/.env apps/api/.env.bak
docker compose config --quiet
test "$(docker compose config --services | wc -l)" -eq "$(docker compose config | grep -c 'healthcheck:')"
mv apps/api/.env.bak apps/api/.env
```
</verification>

<success_criteria>
- `.github/workflows/ci.yml` parses, passes `actionlint`, and has four jobs: backend, mobile, infrastructure, ci-status
- The backend job runs Pint, PHPStan, arch tests, the SQLite suite, migrations forward-and-back against PostGIS, a double seed, an idempotency assertion and the PostgreSQL-only suite
- The mobile job runs on Node 22 and gates typecheck, lint, tests, contract drift and game-data validation
- The infrastructure job needs no paid licence and no developer `.env`
- `ci-status` fails unless all three jobs report `success`
- `make ci` exits 0, and `make lint` exits non-zero on a deliberately broken file
- Task 3 resolved one way or the other, and the SUMMARY says plainly whether criterion 4 is met
</success_criteria>

<output>
After completion, create `.planning/phases/01-engineering-foundation/01-04-SUMMARY.md`.
Paste the literal output of `actionlint`, of `make ci`, and of the deliberate-failure
`make lint`. State the Task 3 decision and its consequence for success criterion 4 in
the first paragraph — not buried at the end.
</output>
