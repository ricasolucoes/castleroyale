---
phase: 01-engineering-foundation
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - docker-compose.yml
  - Makefile
  - scripts/stack-smoke.sh
  - README.md
  - docs/operations/environments.md
autonomous: true
requirements: [REQ-12]

must_haves:
  truths:
    - "`docker compose up -d --wait` exits 0 with api, postgres, redis, reverb, horizon, minio and mailpit all reporting healthy"
    - "`make setup` completes on a checkout that has no apps/api/.env and no running containers"
    - "`make dev` does not return successfully while any service is still unhealthy"
    - "`GET /api/v1/health` returns 200 with data.status == ok when called from inside the Docker network"
    - "`docker compose config --quiet` succeeds on a checkout with no apps/api/.env (so CI can validate the file)"
  artifacts:
    - path: "docker-compose.yml"
      provides: "Seven services, each with a healthcheck; env_file marked required:false"
      contains: "required: false"
    - path: "Makefile"
      provides: "setup/dev/health/smoke/test-postgres targets that work through Docker only"
      contains: "health:"
    - path: "scripts/stack-smoke.sh"
      provides: "Executable proof of success criteria 1 and 5"
      min_lines: 25
  key_links:
    - from: "Makefile:health"
      to: "docker compose exec -T api curl http://localhost:8000/api/v1/health"
      via: "compose exec inside the network"
      pattern: "exec -T api curl"
    - from: "scripts/stack-smoke.sh"
      to: "docker inspect .State.Health.Status"
      via: "per-service health assertion"
      pattern: "State.Health.Status"
    - from: "Makefile:setup"
      to: "docker compose up -d --wait"
      via: "api container must be running before `exec` based migrate/seed"
      pattern: "up -d --wait"
---

<objective>
Make the Docker stack the canonical, provably-working development environment.

Today `docker-compose.yml` and `Makefile` exist but are not correct: three services
have no healthcheck, `make setup` calls `docker compose exec api` before the `api`
container has ever been started, `make dev` returns before anything is healthy, and
`docker compose config` cannot even be parsed on a checkout without `apps/api/.env`
(which is gitignored — so the CI infrastructure job is dead on arrival).

Purpose: satisfies ROADMAP Phase 01 success criteria 1 and 5, and unblocks plans
01-02, 01-03 and 01-04 which all run their verification through `docker compose exec`.

REQ-12 (full observability) starts here, at the infrastructure layer: `make health`
and `scripts/stack-smoke.sh` turn the state of every dependency — database, cache,
redis, queue worker, websocket, object store, mail — into something a command
*reports* rather than something a developer assumes. A stack whose health cannot be
observed cannot have its logs, metrics or traces trusted either.

Output: a compose file where every service declares a healthcheck, a Makefile whose
targets actually run in the right order, and `scripts/stack-smoke.sh` which proves
criteria 1 and 5 in one command.
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
@.planning/codebase/CONCERNS.md
@docs/operations/environments.md
@AGENTS.md

<interfaces>
<!-- Contracts the executor needs. Do not go hunting for these. -->

The health endpoint response envelope (`Game\Shared\Interface\Http\ApiResponse::success`)
is exactly:

```json
{
  "data": { "status": "ok", "checks": { "database": true, "cache": true, "redis": true } },
  "meta": { "game": "...", "environment": "local", "server_time": "...", "versions": {...} }
}
```

`HealthController` sets `status` to `"ok"` only when **every** entry of `checks` is
`true`, and returns HTTP 503 otherwise. Therefore asserting HTTP 200 **and**
`"status":"ok"` is a complete proof of "all dependency checks true".

The API container image (`infrastructure/docker/api/Dockerfile`, target `development`)
contains: `php` 8.4 CLI, `curl`, `composer`, extensions `pdo_pgsql pgsql bcmath intl zip
opcache pcntl sockets redis`. It does **not** contain `nc`, `wget`, `jq` or `bash`
(it is Alpine — `/bin/sh`). Healthchecks must only use `curl` or `php`.

Published host ports (do not change them — README, docs and `.env.example` all
reference them): api 8080→8000, reverb 8081, postgres 5432, redis 6379,
minio 9000/9001, mailpit 1025/8025.
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Give every compose service a healthcheck and make the file parseable without a .env</name>
  <files>docker-compose.yml</files>

  <read_first>
    - docker-compose.yml (the file being modified — read it fully before editing)
    - infrastructure/docker/api/Dockerfile (what binaries exist inside the api image)
    - apps/api/.env.example (the variables the env_file supplies)
    - .planning/phases/01-engineering-foundation/01-CONTEXT.md (§ "Docker is the canonical environment")
  </read_first>

  <action>
Edit `docker-compose.yml` only. Four concrete changes:

**1. Make `env_file` optional on `api`, `horizon` and `reverb`.**

`apps/api/.env` is gitignored, so `docker compose config` fails on a fresh clone and
in CI. Replace every occurrence of:

```yaml
    env_file:
      - ./apps/api/.env
```

with the long form that tolerates a missing file:

```yaml
    env_file:
      - path: ./apps/api/.env
        required: false
```

**2. Add a healthcheck to `horizon`** (it currently has none). Insert after its
`depends_on` block:

```yaml
    healthcheck:
      # `horizon:status` prints "Horizon is running." and exits 0 only when the
      # master supervisor is up; a paused or crashed Horizon fails the grep.
      test: ["CMD-SHELL", "php artisan horizon:status | grep -q running"]
      interval: 15s
      timeout: 10s
      retries: 10
      start_period: 60s
```

**3. Add a healthcheck to `reverb`** (it currently has none). The image has no `nc`
and Reverb does not serve a plain HTTP health route, so probe the socket with PHP:

```yaml
    healthcheck:
      test: ["CMD-SHELL", "php -r 'exit(@fsockopen(\"127.0.0.1\", 8081) ? 0 : 1);'"]
      interval: 10s
      timeout: 5s
      retries: 12
      start_period: 20s
```

Also extend `reverb`'s `depends_on` to include postgres, so it starts in the same
order as the rest:

```yaml
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
```

**4. Add a healthcheck to `mailpit`** (it currently has none). Mailpit ships its own
readiness subcommand:

```yaml
    healthcheck:
      test: ["CMD", "/mailpit", "readyz"]
      interval: 10s
      timeout: 5s
      retries: 10
      start_period: 10s
```

Do **not** change: image tags, published ports, volume names, the `name: dominion`
project name, or the existing api/postgres/redis/minio healthchecks. Do not add new
services (a MinIO bucket bootstrap is out of scope for this phase).

After editing, run `docker compose config --quiet` from the repository root. It must
succeed **with `apps/api/.env` absent** — temporarily rename it to prove this:
`mv apps/api/.env apps/api/.env.bak && docker compose config --quiet && mv apps/api/.env.bak apps/api/.env`.
  </action>

  <verify>
    <automated>cd /Users/sierra/Dev/Jogos/MmoMobile && mv apps/api/.env apps/api/.env.bak 2>/dev/null; docker compose config --quiet; rc=$?; mv apps/api/.env.bak apps/api/.env 2>/dev/null; exit $rc</automated>
  </verify>

  <acceptance_criteria>
    - `docker compose config --quiet` exits 0 while `apps/api/.env` does not exist
    - `grep -c "required: false" docker-compose.yml` returns 3
    - `grep -c "healthcheck:" docker-compose.yml` returns 7
    - `docker compose config | grep -c 'healthcheck:'` returns 7 (one probe per service)
    - `grep -q "horizon:status | grep -q running" docker-compose.yml` succeeds
    - `grep -q "fsockopen" docker-compose.yml` succeeds
    - `grep -q "/mailpit" docker-compose.yml` succeeds
    - `grep -q "image: postgis/postgis:16-3.4" docker-compose.yml` still succeeds (image unchanged)
    - `grep -q '"8080:8000"' docker-compose.yml` still succeeds (ports unchanged)
  </acceptance_criteria>

  <done>Every one of the seven services declares a healthcheck, and the compose file parses on a checkout with no apps/api/.env.</done>
</task>

<task type="auto">
  <name>Task 2: Fix the Makefile so `make setup && make dev` genuinely works end to end</name>
  <files>Makefile</files>

  <read_first>
    - Makefile (the file being modified — read it fully; note `API := $(COMPOSE) exec -T api`)
    - docker-compose.yml (as updated by Task 1)
    - .planning/phases/01-engineering-foundation/01-CONTEXT.md (§ "Makefile targets" — thin wrappers only, no business logic)
  </read_first>

  <action>
Edit `Makefile` only.

**Bug being fixed:** `setup` runs `$(COMPOSE) up -d postgres redis` and then calls
`$(MAKE) migrate`, but `migrate` is `docker compose exec -T api ...` and the `api`
container was never started. `make setup` therefore fails on a clean machine.

**Change 1 — `.PHONY`.** Add the new targets. The line becomes:

```make
.PHONY: help setup dev stop restart logs shell test test-arch test-postgres lint lint-fix \
        analyse migrate migrate-fresh seed reset health smoke gamedata contracts ci clean
```

**Change 2 — `setup`.** Replace the whole recipe with:

```make
setup: ## Build images, install dependencies, migrate and seed
	@test -f apps/api/.env || cp apps/api/.env.example apps/api/.env
	$(COMPOSE) build
	$(COMPOSE) up -d --wait postgres redis
	$(COMPOSE) run --rm api composer install
	$(COMPOSE) run --rm api php artisan key:generate --force
	npm install
	$(COMPOSE) up -d --wait
	$(MAKE) migrate
	$(MAKE) seed
	@$(MAKE) --no-print-directory health
	@echo ""
	@echo "  Ready.  make dev   ->  http://localhost:8080"
```

The `$(COMPOSE) up -d --wait` before `migrate` is the fix: `exec` needs a running
container.

**Change 3 — `dev`.** Replace the whole recipe with:

```make
dev: ## Start the full stack and block until every service reports healthy
	@test -f apps/api/.env || cp apps/api/.env.example apps/api/.env
	$(COMPOSE) up -d --wait
	@$(MAKE) --no-print-directory health
	@echo ""
	@echo "  API         http://localhost:8080"
	@echo "  Health      http://localhost:8080/api/v1/health"
	@echo "  Back office http://localhost:8080/admin"
	@echo "  Horizon     http://localhost:8080/horizon"
	@echo "  Mailpit     http://localhost:8025"
	@echo "  MinIO       http://localhost:9001"
```

**Change 4 — new `health` target.** Add it immediately after `dev`:

```make
health: ## Assert /api/v1/health returns 200 with every dependency check true
	@out="$$($(COMPOSE) exec -T api curl -fsS http://localhost:8000/api/v1/health)" \
		|| { echo "  health: request failed (is the api container up?)"; exit 1; }; \
	 echo "$$out" | grep -q '"status":"ok"' \
		|| { echo "  health: degraded -> $$out"; exit 1; }; \
	 echo "  health: ok"
```

`HealthController` only emits `"status":"ok"` when every entry of `checks` is true and
returns 503 otherwise, so `curl -fsS` + this grep is a complete proof of criterion 5.

**Change 5 — new `smoke` target.** Add it after `health`:

```make
smoke: ## Prove the stack: every service healthy + the health endpoint ok
	./scripts/stack-smoke.sh
```

**Change 6 — new `test-postgres` target.** Add it immediately after `test-arch`:

```make
test-postgres: ## PostGIS-only tests against real PostgreSQL (phpunit.postgres.xml)
	@$(COMPOSE) exec -T postgres psql -U dominion -d dominion -tc \
		"SELECT 1 FROM pg_database WHERE datname='dominion_test'" | grep -q 1 \
		|| $(COMPOSE) exec -T postgres createdb -U dominion dominion_test
	$(API) ./vendor/bin/pest --configuration=phpunit.postgres.xml
```

`phpunit.postgres.xml` is created by plan 01-02. This target is a thin wrapper only —
it is expected to fail with "configuration file not found" until 01-02 lands, and that
is fine; do not stub the config file here.

**Change 7 — `ci`.** Add the two new gates so `make ci` mirrors GitHub Actions:

```make
ci: lint analyse test ## Everything CI runs
	$(MAKE) test-postgres
	npm run typecheck
	npm test
```

Keep every other target exactly as it is. No business logic in the Makefile — every
recipe stays a wrapper over `docker compose` or `npm`.
  </action>

  <verify>
    <automated>cd /Users/sierra/Dev/Jogos/MmoMobile && make -n setup >/dev/null && make -n dev >/dev/null && make -n health >/dev/null && make -n smoke >/dev/null && make -n test-postgres >/dev/null && echo MAKEFILE_PARSES</automated>
  </verify>

  <acceptance_criteria>
    - `make -n setup` exits 0 and its expansion contains `up -d --wait` before the line containing `php artisan migrate`
    - `grep -q '^health:' Makefile` succeeds
    - `grep -q '^smoke:' Makefile` succeeds
    - `grep -q '^test-postgres:' Makefile` succeeds
    - `grep -q 'phpunit.postgres.xml' Makefile` succeeds
    - `grep -c '\-\-wait' Makefile` returns at least 3
    - `grep -q 'test-postgres' Makefile && grep -q 'smoke' Makefile` inside the `.PHONY` line
    - `make help` lists `health`, `smoke` and `test-postgres` with their `##` descriptions
    - No recipe in the Makefile contains a `php -r` or `psql` statement other than the two specified above (no business logic)
  </acceptance_criteria>

  <done>`make -n setup`, `make -n dev`, `make -n health`, `make -n smoke` and `make -n test-postgres` all parse, and `setup` starts the api container before any `exec`-based target.</done>
</task>

<task type="auto">
  <name>Task 3: Add scripts/stack-smoke.sh and update the developer docs to match reality</name>
  <files>scripts/stack-smoke.sh, README.md, docs/operations/environments.md</files>

  <read_first>
    - Makefile (as updated by Task 2 — `smoke` calls this script)
    - docker-compose.yml (service names the script must assert on)
    - README.md (§ "Getting started", § "Running things", § "Troubleshooting")
    - docs/operations/environments.md (§ "Local")
    - AGENTS.md (§ "Environment limitations" — use quoted heredocs when writing files with backticks)
  </read_first>

  <action>
**1. Create `scripts/stack-smoke.sh`** (the directory exists and is empty). Write it
with a **quoted** heredoc (`<<'EOF'`) or the Write tool — the content contains `$`
and Go-template braces that must not be expanded by the shell.

```bash
#!/usr/bin/env bash
#
# Proves GSD Phase 01 success criteria 1 and 5:
#   1. every compose service reports healthy
#   5. GET /api/v1/health returns 200 with all dependency checks true,
#      called from inside the Docker network
#
# Usage: make smoke
set -euo pipefail

cd "$(dirname "$0")/.."

SERVICES=(api postgres redis reverb horizon minio mailpit)

echo "==> starting the stack"
docker compose up -d --wait

failed=0

echo "==> service health"
for svc in "${SERVICES[@]}"; do
  cid="$(docker compose ps -q "$svc" || true)"
  if [ -z "$cid" ]; then
    printf '  %-10s MISSING\n' "$svc"
    failed=1
    continue
  fi
  state="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}no-healthcheck{{end}}' "$cid")"
  if [ "$state" = "healthy" ]; then
    printf '  %-10s healthy\n' "$svc"
  else
    printf '  %-10s UNHEALTHY (%s)\n' "$svc" "$state"
    failed=1
  fi
done

echo "==> health endpoint (from inside the network)"
if body="$(docker compose exec -T api curl -fsS http://localhost:8000/api/v1/health)"; then
  echo "  $body"
  echo "$body" | grep -q '"status":"ok"' || { echo "  status is not ok"; failed=1; }
else
  echo "  request failed"
  failed=1
fi

if [ "$failed" -ne 0 ]; then
  echo "==> SMOKE FAILED"
  exit 1
fi

echo "==> SMOKE OK"
```

Then `chmod +x scripts/stack-smoke.sh`.

**2. Update `README.md`.**

In § "Running things", replace the single line

```
make dev · stop · migrate · seed · reset · logs · shell
```

with:

```
make dev · stop · migrate · seed · reset · logs · shell
make health          # /api/v1/health returns 200 with every check true
make smoke           # every service healthy + health endpoint ok
make test-postgres   # PostGIS-only tests against real PostgreSQL
```

In § "Getting started", add one line under the `make setup` / `make dev` block:

```
make smoke     # prove it: every service healthy, health endpoint ok
```

In § "Troubleshooting", add a new entry after the existing `could not find driver`
entry:

```
**`make setup` fails with "service api is not running"**
Fixed in Phase 01: `setup` now runs `docker compose up -d --wait` before any
`exec`-based target. If you see this on an old checkout, run `make dev` first.
```

**3. Update `docs/operations/environments.md`.** In § "Local", extend the command
block to:

```bash
make setup   # build images, install deps, migrate, seed
make dev     # start the stack, wait for healthy, assert /api/v1/health
make smoke   # prove criteria 1 and 5 in one command
make test    # run the suite
```

and add this table immediately under the existing host-port table:

```
| Service | Healthcheck |
|---------|-------------|
| api | `curl -fsS http://localhost:8000/api/v1/health` |
| postgres | `pg_isready -U dominion -d dominion` |
| redis | `redis-cli ping` |
| reverb | PHP `fsockopen` on 8081 |
| horizon | `php artisan horizon:status` |
| minio | `mc ready local` |
| mailpit | `/mailpit readyz` |

`make dev` uses `docker compose up -d --wait`, so it cannot return successfully while
any service is unhealthy.
```

Do not restructure either document. Add only the sections described above.
  </action>

  <verify>
    <automated>cd /Users/sierra/Dev/Jogos/MmoMobile && bash -n scripts/stack-smoke.sh && test -x scripts/stack-smoke.sh && grep -q 'make smoke' README.md && grep -q 'make smoke' docs/operations/environments.md && echo SMOKE_SCRIPT_AND_DOCS_OK</automated>
  </verify>

  <acceptance_criteria>
    - `test -x scripts/stack-smoke.sh` succeeds (executable bit set)
    - `bash -n scripts/stack-smoke.sh` exits 0
    - `head -1 scripts/stack-smoke.sh` equals `#!/usr/bin/env bash`
    - `grep -q 'set -euo pipefail' scripts/stack-smoke.sh` succeeds
    - `grep -q 'State.Health.Status' scripts/stack-smoke.sh` succeeds
    - `grep -q 'SERVICES=(api postgres redis reverb horizon minio mailpit)' scripts/stack-smoke.sh` succeeds
    - `grep -q '"status":"ok"' scripts/stack-smoke.sh` succeeds
    - `grep -q 'make health' README.md` and `grep -q 'make test-postgres' README.md` both succeed
    - `grep -q 'Healthcheck' docs/operations/environments.md` succeeds and the table lists all seven services
  </acceptance_criteria>

  <done>`make smoke` is a single command that proves success criteria 1 and 5, and README plus environments.md describe the stack that now exists.</done>
</task>

</tasks>

<verification>
Run these **inside the repository root**, with the Docker daemon running. If the
daemon is stopped, start it (`open -a Docker` on macOS) and wait for it before
continuing — a stopped daemon is not a reason to skip these. Do not report this
plan complete without pasting the output of `make smoke`.

```bash
# Static — no daemon needed
docker compose config --quiet
make -n setup && make -n dev && make -n health && make -n smoke
bash -n scripts/stack-smoke.sh

# Live — daemon required
make setup            # must complete without "service api is not running"
make smoke            # must print "==> SMOKE OK"
make stop && make dev # must return only after every service is healthy
make health           # must print "  health: ok"
```

Negative check (the gate must actually fail):

```bash
docker compose stop redis
make health           # MUST exit non-zero (redis check false -> status degraded -> 503)
docker compose start redis
```
</verification>

<success_criteria>
- `docker compose config --quiet` exits 0 with `apps/api/.env` absent
- All seven services declare a healthcheck; `docker compose up -d --wait` exits 0
- `make setup` completes on a clean environment (no `.env`, no containers)
- `make dev` blocks until healthy and then reports `health: ok`
- `make smoke` prints `==> SMOKE OK` and exits 0
- `make health` exits non-zero when a dependency is stopped
- README and `docs/operations/environments.md` document `health`, `smoke` and `test-postgres`
</success_criteria>

<output>
After completion, create `.planning/phases/01-engineering-foundation/01-01-SUMMARY.md`.
Include the literal pasted output of `make smoke` and of the negative `make health`
check — "should work" is not a status (AGENTS.md, docs/gsd/EXECUTION_RULES.md).
</output>
