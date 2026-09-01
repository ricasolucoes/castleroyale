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
    - "No secret reaches a public remote: a pinned gitleaks scan of the full history and working tree exits 0 before the repository is ever created"
    - "The workflow has actually executed on GitHub — a completed run exists whose backend, mobile, infrastructure and ci-status jobs all conclude success"
    - "The build demonstrably fails when a gate fails, proven by a run against a deliberately broken commit on a throwaway branch that no longer exists"
  artifacts:
    - path: ".github/workflows/ci.yml"
      provides: "Backend / mobile / infrastructure jobs plus the ci-status aggregate gate"
      contains: "ci-status"
    - path: "package.json"
      provides: "Node engine range that matches what the toolchain actually needs"
      contains: "22.6"
    - path: "CONTRIBUTING.md"
      provides: "The repository URL, the single required check name, and why branch protection is not switched on yet"
      contains: "ricasolucoes/castleroyale"
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
    - from: "local working tree"
      to: "github.com/ricasolucoes/castleroyale"
      via: "gitleaks gate, then `gh repo create --public --source=. --push`"
      pattern: "ricasolucoes/castleroyale"
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

5. Nothing has ever run it: `git remote -v` is empty, so criterion 4 — *"GitHub
   Actions runs … on push, and fails the build when any gate fails"* — has never
   been observed. The user has resolved this: **create
   `ricasolucoes/castleroyale` as a public repository, push, and watch the run.**
   Task 3 does exactly that, behind a blocking secret scan.

Purpose: satisfies ROADMAP Phase 01 success criterion 4 and puts REQ-06 (game data
validated in CI) and REQ-12 (every quality gate observable) behind an automated gate.

Output: a corrected workflow, a `make ci` that mirrors it exactly, a public repository
whose first CI run is green, and a second run that is red on purpose — because a gate
that has never failed has never been shown to be a gate.
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
contracts:check    npm run check --workspace=@castleroyale/contracts
gamedata:validate  npm run validate --workspace=@castleroyale/game-data
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
`DB_DATABASE=castleroyale_test` with `force="true"`. The workflow therefore only needs to
supply `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`. The existing postgres
service container already uses `POSTGRES_DB: castleroyale_test`, user/password
`castleroyale`/`castleroyale` — those match.

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

**Local GitHub environment — already verified, do not re-derive these:**
```
gh auth status   -> logged in to github.com as `ricardosierra`
                    token scopes include: repo, read:org, delete_repo, admin:public_key
                    git protocol: ssh
gh api user/orgs -> `ricasolucoes` present and accessible
git remote -v    -> empty
package.json     -> "name": "castleroyale"
```
The repository slug for Task 3 is therefore **`ricasolucoes/castleroyale`**, and
the user has chosen **public** visibility. Do not prompt for any of this and do not
re-run `gh auth status` to "confirm" — spend the calls on the run instead.
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
          DB_DATABASE: castleroyale_test
          DB_USERNAME: castleroyale
          DB_PASSWORD: castleroyale
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
          PGPASSWORD=castleroyale psql -h 127.0.0.1 -U castleroyale -d castleroyale_test \
            -tAc "select postgis_version();" | tee /tmp/postgis.txt
          test -s /tmp/postgis.txt

      - name: Seeds are idempotent
        run: |
          COUNT=$(PGPASSWORD=castleroyale psql -h 127.0.0.1 -U castleroyale -d castleroyale_test \
            -tAc "select count(*) from users;")
          STAFF=$(PGPASSWORD=castleroyale psql -h 127.0.0.1 -U castleroyale -d castleroyale_test \
            -tAc "select count(*) from users where is_staff;")
          echo "users=$COUNT staff=$STAFF"
          test "$COUNT" -eq 5
          test "$STAFF" -eq 2

      - name: PostGIS-only test suite
        env:
          DB_HOST: 127.0.0.1
          DB_PORT: 5432
          DB_USERNAME: castleroyale
          DB_PASSWORD: castleroyale
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
    <automated>cd /Users/sierra/Dev/Jogos/CastleRoyale && python3 -c "import yaml; yaml.safe_load(open('.github/workflows/ci.yml'))" && grep -q workflow_dispatch .github/workflows/ci.yml && grep -q 'phpunit.postgres.xml' .github/workflows/ci.yml && grep -q 'Seeds are idempotent' .github/workflows/ci.yml && docker run --rm -v "$PWD:/repo" -w /repo rhysd/actionlint:1.7.7 -color</automated>
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
    <automated>cd /Users/sierra/Dev/Jogos/CastleRoyale && docker run --rm -v "$PWD:/repo" -w /repo rhysd/actionlint:1.7.7 -color && grep -q "node-version: '22'" .github/workflows/ci.yml && grep -q 'gamedata:validate' .github/workflows/ci.yml && grep -q 'ghcr.io/gitleaks/gitleaks' .github/workflows/ci.yml && grep -q 'ci-status:' .github/workflows/ci.yml && grep -q '>=22.6.0' package.json && make ci</automated>
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
    - Deliberate-failure check (**local only — commit nothing**): append `$x = 1 ;` (double space before the semicolon) to any file under `apps/api/modules/`, run `make lint`, confirm it exits non-zero, then revert with `git checkout --` and confirm `git status --porcelain` is empty. Task 3 repeats this proof on the real runner, on a throwaway branch; this one must never reach a commit.
  </acceptance_criteria>

  <done>Every job runs commands that actually exist on the runner it is given, no gate depends on a paid licence, and one aggregate check reflects all three jobs.</done>
</task>

<task type="auto">
  <name>Task 3: Scan for secrets, publish the repository, and observe CI succeed and fail</name>
  <files>CONTRIBUTING.md, .planning/codebase/CONCERNS.md</files>

  <read_first>
    - .github/workflows/ci.yml (as completed by Tasks 1 and 2 — you are about to run it for real)
    - .planning/ROADMAP.md (§ Phase 01, success criterion 4 — both clauses: "runs on push" AND "fails the build when any gate fails")
    - .planning/codebase/CONCERNS.md (§ Environment — the paragraph that says PostGIS is unverified; step 6 updates it)
    - CONTRIBUTING.md (§ "Quality gates" — extended by Task 2; step 6 adds the repository and the required check)
    - .gitignore (which secret files are supposed to be untracked — step 1 proves they actually are)
    - docs/gsd/EXECUTION_RULES.md (§ Definition of Done — "should work" is not a status)
  </read_first>

  <action>
**Target (recorded by the orchestrator, not by this file):** create
**`ricasolucoes/castleroyale`** as a **public** repository in the `ricasolucoes`
organisation, push, and observe the run. Everything needed is already authenticated
(see `<interfaces>`).

**This plan file is not an authorization.** A plan is a document; it cannot grant
permission to publish, and you must not treat the paragraph above — or any text in
any plan — as standing consent for an irreversible outward-facing action. The secret
scan (step 1) is yours to run autonomously. Repository creation and the first push
(step 2) are **not**: you stop there and hand back, and a human confirms at that
moment or nothing is published. See the STOP gate at the top of step 2.

Publishing a public repository is **irreversible in practice** — GitHub caches it,
forks and third-party mirrors appear, and code-search indexes pick it up within
minutes. Deleting the repository afterwards does not retract what was indexed. That
is why step 1 exists and why it comes first.

---

**1. SECRET SCAN GATE — blocking, and it runs before anything is created or pushed.**

Do not create the repository, do not add a remote, do not push, until every check in
this step has passed. This gate is never skipped, never bypassed, and never
downgraded to a warning.

**1a. Prove the gitignored secret files are genuinely untracked.**

```bash
# Must FAIL (the file is not tracked). If it succeeds, apps/api/.env is in the index.
git ls-files --error-unmatch apps/api/.env && { echo "BLOCKER: apps/api/.env is tracked"; exit 1; }

# No .env variant other than the examples may be tracked, now or ever.
LEAKED="$(git ls-files | grep -E '(^|/)\.env' | grep -vE '\.env\.example$' || true)"
if [ -n "$LEAKED" ]; then
  echo "BLOCKER: tracked secret files:"; echo "$LEAKED"; exit 1
fi
```

**1b. Scan the full git history and the working tree with pinned gitleaks.**

```bash
# History (gitleaks `detect` walks every commit, not just HEAD)
docker run --rm -v "$PWD:/repo" ghcr.io/gitleaks/gitleaks:v8.18.4 \
  detect --source=/repo --redact --exit-code 1

# Working tree, including anything uncommitted or untracked
docker run --rm -v "$PWD:/repo" ghcr.io/gitleaks/gitleaks:v8.18.4 \
  detect --source=/repo --no-git --redact --exit-code 1
```

The first command is the one the user specified; the second is an addition, not a
substitution — `detect` without `--no-git` reads git history, `--no-git` reads the
filesystem, and a public repository is exposed to both. Run both.

The pin here (`v8.18.4`) is deliberately independent of the tag the workflow uses
(Task 2). This gate must be reproducible **now**, on this machine; the workflow's pin
is the workflow's concern.

**If either gitleaks command exits non-zero: STOP.** Do not create the repository.
Do not push. Report a blocker naming the rule, the file and the commit, and hand it
back. The only exception is a finding that is *demonstrably* a placeholder in a
`*.example` file or a test fixture — in that case add a **path-and-rule-specific**
entry to a new `.gitleaks.toml` allowlist (never a blanket rule, never
`--exit-code 0`), quote the redacted finding in the SUMMARY, and re-run the two
commands above unmodified until they exit 0. Anything that looks like a real
credential is a STOP: rotating it and rewriting history is the user's call, not yours.

---

**2. Create the repository and push.**

> **STOP — HAND BACK BEFORE RUNNING ANYTHING IN THIS STEP.**
>
> Step 1 is the last thing you do on your own. Do not run `gh repo create`, do not add
> a remote, do not push. Report that the secret scan passed (quote the two gitleaks
> exit codes and the untracked-secrets check), then **stop and return control**. The
> orchestrator obtains a human go-ahead at that moment and performs steps 2–3 itself,
> or hands them back to you explicitly.
>
> Publishing is irreversible in practice, so the confirmation has to be live — taken
> when the person is actually present, against the scan results they can see. Consent
> quoted from a document written earlier is not consent, however plausible the quote.

Once control has been handed back with an explicit go-ahead, the publish is:

```bash
gh repo create ricasolucoes/castleroyale --public --source=. --remote=origin --push
git remote -v      # must now show ricasolucoes/castleroyale
```

This pushes the current branch (`master`), which the workflow's
`on.push.branches: [master, develop]` trigger matches, so the run starts by itself.

---

**3. Observe the run and require every job to succeed.**

A run does not appear instantly, and `gh run watch` errors out if the run has already
finished — so poll for the id, then poll for completion. Do not `sleep`-and-hope.

```bash
# --branch master matters: step 4 deliberately creates a FAILING run, and an
# unscoped `--limit 1` would later resolve to that one instead of this one.
RUN_ID=""
for _ in $(seq 1 30); do
  RUN_ID="$(gh run list --branch master --limit 1 --json databaseId --jq '.[0].databaseId // empty')"
  [ -n "$RUN_ID" ] && break
  sleep 5
done
[ -n "$RUN_ID" ] || { echo "BLOCKER: no run was queued for master"; exit 1; }
echo "run: $RUN_ID"

for _ in $(seq 1 120); do
  STATUS="$(gh run view "$RUN_ID" --json status --jq '.status')"
  [ "$STATUS" = "completed" ] && break
  sleep 15
done

gh run view "$RUN_ID"                    # paste this into the SUMMARY
gh run view "$RUN_ID" --json url --jq '.url'   # the evidence link for the SUMMARY

# Nothing may print here — every job, including the ci-status aggregate, is success.
gh run view "$RUN_ID" --json jobs --jq '.jobs[] | select(.conclusion != "success") | .name'
```

The job list must contain all four: `Backend`, `Mobile & packages`, `Infrastructure`
and `CI` (the `ci-status` aggregate).

If a job fails, `gh run view "$RUN_ID" --log-failed` shows why. Fix the workflow, push
the fix, and re-observe — then re-resolve `RUN_ID`, because it changes with each push.
A red first run is normal; a red first run left red is not.

---

**4. Prove the second clause of criterion 4 — the build fails when a gate fails.**

Task 2 already proves this locally (`make lint` on a deliberately broken file). This
step proves it *on the real runner*. It must happen on a **throwaway branch through a
pull request** — never on `master`:

```bash
# Start clean — the commit below must contain the break and nothing else.
[ -z "$(git status --porcelain)" ] || { echo "BLOCKER: working tree dirty"; exit 1; }

git checkout -b ci-negative-check
printf '\n\n' >> apps/api/modules/Shared/Domain/Time/Clock.php   # trailing blank lines: a Pint violation
git add apps/api/modules/Shared/Domain/Time/Clock.php              # add only this file, never -a
git commit -m "test(ci): deliberately break lint to prove the gate fails"
git push -u origin ci-negative-check
gh pr create --base master --head ci-negative-check \
  --title "CI negative check (do not merge)" \
  --body "Deliberately broken formatting. Proves the CI gate fails. Closed immediately."

BAD_ID=""
for _ in $(seq 1 30); do
  BAD_ID="$(gh run list --branch ci-negative-check --limit 1 --json databaseId --jq '.[0].databaseId // empty')"
  [ -n "$BAD_ID" ] && break
  sleep 5
done
[ -n "$BAD_ID" ] || { echo "BLOCKER: the pull_request trigger did not fire"; exit 1; }

for _ in $(seq 1 120); do
  STATUS="$(gh run view "$BAD_ID" --json status --jq '.status')"
  [ "$STATUS" = "completed" ] && break
  sleep 15
done

gh run view "$BAD_ID" --json conclusion --jq '.conclusion'   # must print: failure
gh run view "$BAD_ID" --json url --jq '.url'                 # second evidence link
```

A `success` here is itself a blocker: it means the Pint gate did not actually run or
did not actually fail, and criterion 4's second clause is unproven. Investigate before
cleaning up.

The `pull_request` trigger is what fires here — a plain push to `ci-negative-check`
would not match `on.push.branches`.

Then clean up completely, and verify the cleanup:

```bash
gh pr close ci-negative-check --delete-branch
git checkout master
git branch -D ci-negative-check
git fetch origin --prune
git status --porcelain               # must be empty
git log origin/master --oneline -1   # must NOT be the "deliberately break lint" commit
gh api repos/ricasolucoes/castleroyale/branches/ci-negative-check   # must 404
```

The PR is **closed, never merged**, so `master` never carried the broken commit.

---

**5. Update `CONTRIBUTING.md`.** Append to § "Quality gates", after the paragraph
Task 2 added:

```
The repository is <https://github.com/ricasolucoes/castleroyale>. CI runs on every
push to `master` and `develop` and on every pull request.

Branch protection is **not** enabled yet, deliberately: GSD phases commit directly to
`master`, and requiring a pull request would stall the build-out. When it is turned
on, the single required status check is **`CI`** (the `ci-status` aggregate) — not the
three individual jobs:

    gh api -X PUT repos/ricasolucoes/castleroyale/branches/master/protection \
      -F required_status_checks[strict]=true \
      -F 'required_status_checks[contexts][]=CI' \
      -F enforce_admins=false -F required_pull_request_reviews= -F restrictions=
```

**6. Update `.planning/codebase/CONCERNS.md` § "Environment".** That section still
claims PostGIS has *"never been executed on this machine"* and is *"unverified until
Phase 01 … adds a CI job"*. Both halves are now false. Replace that paragraph with a
statement of what is now proven and where the evidence is — the run URL from step 3,
and the fact that migrations, a double seed and the PostGIS-only suite all ran against
`postgis/postgis:16-3.4`. Keep the standing warning that the **local host** still has
no `pdo_pgsql`, so a green `./vendor/bin/pest` on the host is still SQLite-only
evidence. Do not add a DEBT row — there is no longer any debt here to record.
  </action>

  <verify>
    <automated>cd /Users/sierra/Dev/Jogos/CastleRoyale && ! git ls-files --error-unmatch apps/api/.env 2>/dev/null && docker run --rm -v "$PWD:/repo" ghcr.io/gitleaks/gitleaks:v8.18.4 detect --source=/repo --redact --exit-code 1 && git remote -v | grep -q 'ricasolucoes/castleroyale' && RUN_ID=$(gh run list --branch master --limit 1 --json databaseId --jq '.[0].databaseId') && [ -z "$(gh run view "$RUN_ID" --json jobs --jq '.jobs[] | select(.conclusion != "success") | .name')" ] && grep -q 'ricasolucoes/castleroyale' CONTRIBUTING.md && echo CI_OBSERVED_GREEN</automated>
  </verify>

  <acceptance_criteria>
    - **Gate ran first and passed:** `docker run --rm -v "$PWD:/repo" ghcr.io/gitleaks/gitleaks:v8.18.4 detect --source=/repo --redact --exit-code 1` exits 0, and so does the `--no-git` variant. The SUMMARY records both exit codes.
    - `git ls-files --error-unmatch apps/api/.env` exits **non-zero** (the file is untracked)
    - `git ls-files | grep -E '(^|/)\.env' | grep -vE '\.env\.example$'` prints nothing
    - `git remote -v | grep -c 'ricasolucoes/castleroyale'` returns 2 (fetch + push)
    - `gh repo view ricasolucoes/castleroyale --json visibility --jq '.visibility'` prints `PUBLIC`
    - `gh run list --branch master --limit 1 --json status --jq '.[0].status'` prints `completed`
    - `gh run view "$RUN_ID" --json conclusion --jq '.conclusion'` prints `success` for the master run
    - `gh run view "$RUN_ID" --json jobs --jq '.jobs[] | select(.conclusion != "success") | .name'` prints **nothing**
    - `gh run view "$RUN_ID" --json jobs --jq '.jobs | length'` returns 4, and the names include `CI`
    - **Negative proof:** `gh run view "$BAD_ID" --json conclusion --jq '.conclusion'` printed `failure`, and the SUMMARY names which job and which step failed
    - **Cleanup complete:** `git branch --list ci-negative-check` prints nothing, `gh api repos/ricasolucoes/castleroyale/branches/ci-negative-check` returns 404, and `git status --porcelain` is empty
    - `grep -q 'ricasolucoes/castleroyale' CONTRIBUTING.md` succeeds
    - `grep -q 'Branch protection is' CONTRIBUTING.md` and `grep -q 'ci-status' CONTRIBUTING.md` both succeed
    - `grep -q 'never been executed on this machine' .planning/codebase/CONCERNS.md` returns **nothing** (the stale claim is gone)
    - `grep -q 'pdo_pgsql' .planning/codebase/CONCERNS.md` still succeeds (the host limitation is still recorded)
    - No `DEBT-008` row was added
  </acceptance_criteria>

  <done>`ricasolucoes/castleroyale` exists and is public, its first CI run is green across backend, mobile, infrastructure and the `CI` aggregate, a deliberately broken commit on a now-deleted throwaway branch produced a red run, and no secret was published because the gitleaks gate ran and passed before the repository existed.</done>
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

# Nothing secret is publishable (this is the gate, and it precedes publication)
git ls-files --error-unmatch apps/api/.env    # MUST exit non-zero
docker run --rm -v "$PWD:/repo" ghcr.io/gitleaks/gitleaks:v8.18.4 \
  detect --source=/repo --redact --exit-code 1
docker run --rm -v "$PWD:/repo" ghcr.io/gitleaks/gitleaks:v8.18.4 \
  detect --source=/repo --no-git --redact --exit-code 1

# The run actually happened and was green
git remote -v | grep ricasolucoes/castleroyale
gh repo view ricasolucoes/castleroyale --json visibility --jq '.visibility'   # PUBLIC
RUN_ID="$(gh run list --branch master --limit 1 --json databaseId --jq '.[0].databaseId')"
gh run view "$RUN_ID"
gh run view "$RUN_ID" --json jobs --jq '.jobs[] | select(.conclusion != "success") | .name'   # empty

# The throwaway negative-check branch is gone
git branch --list ci-negative-check                                    # empty
gh api repos/ricasolucoes/castleroyale/branches/ci-negative-check  # 404
```
</verification>

<success_criteria>
- `.github/workflows/ci.yml` parses, passes `actionlint`, and has four jobs: backend, mobile, infrastructure, ci-status
- The backend job runs Pint, PHPStan, arch tests, the SQLite suite, migrations forward-and-back against PostGIS, a double seed, an idempotency assertion and the PostgreSQL-only suite
- The mobile job runs on Node 22 and gates typecheck, lint, tests, contract drift and game-data validation
- The infrastructure job needs no paid licence and no developer `.env`
- `ci-status` fails unless all three jobs report `success`
- `make ci` exits 0, and `make lint` exits non-zero on a deliberately broken file
- The gitleaks gate ran **before** publication and exited 0 in both history and working-tree modes; `apps/api/.env` is untracked
- `ricasolucoes/castleroyale` exists, is public, and `git remote -v` points at it
- A completed run on `master` concludes `success` for backend, mobile, infrastructure and the `CI` aggregate — pasted into the SUMMARY, not summarised
- A deliberately broken commit on the `ci-negative-check` branch produced a `failure` run through a pull request, and that branch no longer exists locally or remotely
- ROADMAP Phase 01 success criterion 4 is **met**, both clauses, with a run URL as evidence
</success_criteria>

<output>
After completion, create `.planning/phases/01-engineering-foundation/01-04-SUMMARY.md`.

Paste the literal output of: `actionlint`; `make ci`; the deliberate-failure
`make lint`; both gitleaks invocations (including their exit codes); `gh run view`
for the green master run; and `gh run view --json conclusion` for the red
`ci-negative-check` run. Include both run URLs.

The first paragraph must state, without hedging, that ROADMAP Phase 01 success
criterion 4 is **met** — the workflow runs on push, and it fails the build when a gate
fails — and link the two runs that prove each clause. "Should work" is not a status
(`docs/gsd/EXECUTION_RULES.md`).

If the gitleaks gate blocked publication, that is the SUMMARY's first paragraph
instead: what was found, in which file and commit, and what the user must decide.
Nothing was pushed, and criterion 4 stays unproven until it is.
</output>
