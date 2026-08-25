# Decision log

Decisions made *during execution* that were not already settled in an ADR or a
phase CONTEXT. This is the append-only record of how the plan changed contact
with reality.

**When to add an entry**

- A locked CONTEXT decision turned out to be unworkable (also write an ADR).
- A phase was descoped, extended or reordered.
- Technical debt was accepted.
- An idea was deferred to a later phase.

**When not to add an entry**

- Routine implementation choices already inside Claude's discretion.
- Anything an ADR already answers.

## Format

```
### YYYY-MM-DD — Phase NN — Short title

**Type:** Change | Deferral | Debt | Descope
**What:** ...
**Why:** ...
**Impact:** which phases or documents this affects
**ADR:** ADR-NNN if one was written, otherwise "none needed"
```

---

## Entries

### 2026-08-24 — Phase 00 — The GSD plan lives in `.planning/`, not `docs/gsd/`

**Type:** Change
**What:** The original brief specified `docs/gsd/` for the phase plan. The
machine-readable plan was written to `.planning/` instead; `docs/gsd/` holds the
human-facing narrative (this file, execution rules, the dependency graph).
**Why:** The installed GSD tooling (`gsd-tools.cjs`, `/gsd:autonomous`) reads
`.planning/ROADMAP.md` and `.planning/STATE.md`. Writing only to `docs/gsd/` would
have produced a plan no tool could execute — the autonomous run would have found
nothing and re-planned from scratch, defeating the purpose of planning ahead.
**Impact:** All phases. `.planning/` is authoritative; keep `docs/gsd/` consistent
with it.
**ADR:** none needed — a tooling-path decision, not an architectural one.

### 2026-08-24 — Phase 00 — DEBT-002: PHPStan analyses first-party code only

**Type:** Debt
**What:** `config/` and `tests/` are excluded from PHPStan analysis.
**Why:** Laravel's stock `config/*.php` files are framework-owned declarative
arrays that trip strict rules with pure noise, and Pest's fluent API
(`$this->getJson()`, `arch()->expect()`) is not statically modelable without a
dedicated plugin. Analysing them produced 30+ errors, none of which indicated a
real defect in our code.
**Impact:** `app/`, `modules/`, `database/` and `routes/` are fully covered at
level 8 with strict rules. Config correctness is covered by `artisan config:show`
and by the suite; test correctness by the suite itself.
**ADR:** none needed.

### 2026-08-24 — Phase 00 — GSD research disabled in config

**Type:** Change
**What:** `.planning/config.json` sets `workflow.research: false`.
**Why:** Every phase ships with a pre-written CONTEXT.md carrying locked decisions
and canonical references. Running `gsd-phase-researcher` would spend tokens
re-deriving settled decisions and risks introducing web-sourced approaches that
contradict the recorded architecture.
**Impact:** All phases plan directly from CONTEXT + roadmap criteria. If a phase
genuinely needs research, enable it for that phase specifically.
**ADR:** none needed.

### 2026-08-24 — Phase 00 — PostGIS migrations unverified on this machine

**Type:** Debt
**What:** PostgreSQL/PostGIS migrations were never executed locally. The host PHP
lacks `pdo_pgsql` and the Docker daemon was stopped; migrations were verified
against SQLite only.
**Why:** Environmental, not a design choice.
**Impact:** Phase 01 makes Docker the canonical development environment and adds a
CI job running migrations against real Postgres + PostGIS. Until that job is green,
no PostGIS-specific behaviour should be described as verified.
**ADR:** none needed — see ADR-004 for the PostGIS decision itself.
### 2026-08-24 — Phase 01 — Seeder idempotency uses firstOrNew + forceFill, not updateOrCreate

**Type:** Change
**What:** `01-CONTEXT.md` § Seeds specifies `updateOrCreate` as the idempotency
mechanism for seeders. `StaffUserSeeder` and `DevelopmentUserSeeder` use
`firstOrNew()` + `forceFill()` + `save()` instead. The requirement itself is
unchanged and still tested: seeding twice produces the same rows with the same
primary keys.
**Why:** `updateOrCreate()` mass-assigns, and `is_staff` is deliberately absent from
`User::$fillable` so no future registration endpoint can escalate a account to staff.
`AppServiceProvider` enables `Model::preventSilentlyDiscardingAttributes()`, so the
attribute is not silently dropped — it throws `MassAssignmentException` and
`php artisan db:seed` fails outright. Adding `is_staff` to `$fillable` would trade a
seeder convenience for a privilege-escalation surface. `forceFill()` bypasses
mass-assignment protection at the one call site that is allowed to, inside a seeder.
**Impact:** Every seeder in every later phase. The idempotency pattern for this
project is `firstOrNew` + `forceFill` + `save`, with `$user->exists ?` guards on any
attribute a re-run must not overwrite. Covered by
`tests/Feature/Database/SeederTest.php`.
**ADR:** none needed — an implementation detail of a seeder, not an architectural
decision. ADR-016 and the mass-assignment posture are unchanged.
