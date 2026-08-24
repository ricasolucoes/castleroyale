# Phase 01: Engineering Foundation - Context

**Gathered:** 2026-08-24
**Status:** Ready for planning
**Source:** Pre-written during Phase 00. These decisions are locked — do not re-open them
in discussion. If one is genuinely unworkable, write an ADR and record it in
`docs/gsd/DECISIONS.md` rather than quietly designing around it.

<domain>
## Phase Boundary

Any developer runs `make setup && make dev` and gets a working API, database, queue, websocket and admin panel.

**Depends on:** Phase 00
**Milestone:** Foundation

This phase is complete when every success criterion in `.planning/ROADMAP.md`
(Phase 01) is demonstrably true. Those criteria are the contract; anything
beyond them is out of scope for this phase.

**Planned work** (from the roadmap — the planner may split further, not wider):

1. Docker development stack and Makefile targets
2. PostgreSQL + PostGIS connection, migration baseline and ULID conventions
3. Development seeders and reusable test fixtures
4. GitHub Actions CI for backend, mobile and infrastructure

</domain>

<decisions>
## Implementation Decisions

### Docker is the canonical environment
- The host lacks pdo_pgsql, so `make dev` must work entirely through Docker. Do not add a host-PHP path as the primary flow.
- Compose services: api, postgres (postgis/postgis:16-3.4), redis:7, reverb, horizon, minio, mailpit.
- Every service declares a healthcheck; `make dev` waits for healthy before returning.
- Inside the network use service names (postgres, redis); from the host use localhost.

### Migrations against real PostGIS
- Add a migration that runs `CREATE EXTENSION IF NOT EXISTS postgis;` before any spatial migration.
- CI must run migrations against real Postgres+PostGIS, not SQLite. This is the point of the phase.
- Keep phpunit.xml on SQLite in-memory so the host suite still runs; tag PostGIS-only tests for the CI job.

### Makefile targets
- setup, dev, stop, test, lint, analyse, migrate, seed, reset, logs, shell.
- Targets are thin wrappers over docker compose exec — no business logic in the Makefile.

### CI structure
- Three jobs: backend (pint --test, phpstan, pest, migrations against postgres+postgis), mobile (typecheck, lint, test), infra (docker compose config, secret scan).
- Jobs run in parallel. Backend job uses a postgis service container.

### Seeds
- DatabaseSeeder already calls StaffUserSeeder. Add development fixtures behind an environment check.
- Seeders must be idempotent — safe to run twice (use updateOrCreate).
- Reference data (buildings, units) is NOT seeded; it is imported from packages/game-data by its own command.

### Non-negotiables (apply to every phase)

These are enforced by tests. Breaking one fails the build, so do not work around them.

- **The server owns the truth.** The client sends intent; the server computes the
  outcome. Never accept a cost, duration, result or quantity from the client.
- **Time comes from the injected `Clock`.** Never `now()`, never a client timestamp.
- **Money is integer.** Use `ResourceAmount` / `ResourceBundle`. No float, ever.
- **Every gameplay query filters `world_id`.** Omitting it is a cross-world leak.
- **Spending resources means:** transaction → `lockForUpdate` → recompute cost
  server-side → re-check affordability *inside* the lock → mutate + write ledger.
- **Mutating commands accept `Idempotency-Key`** and are tested for double-submit.
- **Queued jobs that grant value are idempotent**, guarded on `completed_at IS NULL`.
- **Balance numbers live in `packages/game-data/`**, never in PHP.
- **New errors are added to the `ErrorCode` enum and to `openapi.yaml`** — never
  invented inline.
- **Broadcast channels deny by default** and both allow and deny paths are tested.

### Claude's Discretion
- File and class layout within the module, as long as the layering rule holds
  and layers are not created ceremonially (ADR-001).
- Test structure and naming, as long as the obligations in
  `.planning/codebase/TESTING.md` are covered.
- How work is split across plans.

</decisions>

<specifics>
## Specific Ideas

No additional product references beyond the success criteria and the decisions
above. Follow the documented design direction; do not imitate any existing game.

</specifics>

<canonical_refs>
## Canonical References

**Read these before planning or implementing.**

### Always
- `.planning/codebase/ARCHITECTURE.md` — The non-negotiables and the layering rule
- `.planning/codebase/CONVENTIONS.md` — PHP/TS style, naming, commits, versioning
- `.planning/codebase/TESTING.md` — What every phase must test and how to run the gates
- `.planning/codebase/CONCERNS.md` — Known debt and traps that have already cost time

### This phase
- `docs/backend/architecture.md` — Module layout and the canonical command shape
- `docs/database/conventions.md` — Identifiers, world_id, time, money, indexes
- `.planning/codebase/CONCERNS.md` — The pdo_pgsql limitation this phase resolves
- `docs/adr/004-postgresql-postgis.md` — Why PostGIS and what must be proven
- `docs/operations/environments.md` — Environment matrix and config boundaries

### The plan itself
- `.planning/ROADMAP.md` §Phase 01 — goal, dependencies and success criteria
- `.planning/PROJECT.md` — requirements, constraints and key decisions
- `docs/gsd/EXECUTION_RULES.md` — how to execute a phase and when to stop

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable assets
- `Game\Shared\Domain\Time\Clock` — inject for any time. `FrozenClock` in tests.
- `Game\Shared\Domain\Economy\ResourceAmount` / `ResourceBundle` — all economy maths.
- `Game\Shared\Application\Error\ErrorCode` / `GameException` — player-safe failures.
- `Game\Shared\Interface\Http\ApiResponse` — the only response envelope.
- `tests/Architecture/ArchitectureTest.php` — extend when this phase adds a boundary.

### Established patterns
- Modules live in `apps/api/modules/<Module>/` under the `Game\` namespace.
- Timed work = delayed job + idempotent completion + scheduled reconciler.
- Cross-module communication is domain events, never direct model access.

</code_context>

<deferred>
## Deferred Ideas

**Explicitly out of scope for this phase:**
- Any gameplay entity
- Terraform / production infrastructure (Phase 50)
- Load testing (Phase 39)

**Belongs to a later phase:**
- Production Terraform — Phase 50
- Read replicas — Phase 38

</deferred>
