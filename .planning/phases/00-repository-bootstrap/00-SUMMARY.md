---
phase: 00-repository-bootstrap
plan: 00
subsystem: foundation
tags: [monorepo, laravel, expo, phpstan, pest, filament, horizon, reverb]
provides:
  - Monorepo with npm workspaces and a PHP modular monolith
  - Laravel 13.26 API with Sanctum, Horizon, Reverb, Octane and Filament
  - Shared kernel: Clock contract, integer-only economy value objects, ErrorCode catalogue, API envelope
  - Passing quality gates: Pint, PHPStan level 8 + strict rules, Pest with architecture tests
  - Expo SDK 57 mobile app with strict TypeScript and a monorepo-aware Metro config
  - Complete 55-phase GSD plan in .planning/
affects: [all phases]
tech-stack:
  added: [laravel/framework 13.26, filament 5.7, laravel/horizon, laravel/reverb, laravel/octane, laravel/sanctum, pestphp/pest 4.7, larastan 3.10, expo 57, react-native 0.86, @shopify/react-native-skia, react-native-mmkv, zustand, @tanstack/react-query]
  patterns: [modular monolith, domain/application/infrastructure/interface layering, value objects, server-authoritative time, single response envelope]
key-files:
  created:
    - apps/api/modules/Shared/Domain/Economy/ResourceAmount.php
    - apps/api/modules/Shared/Domain/Economy/ResourceBundle.php
    - apps/api/modules/Shared/Domain/Time/Clock.php
    - apps/api/modules/Shared/Application/Error/ErrorCode.php
    - apps/api/modules/Shared/Interface/Http/ApiResponse.php
    - apps/api/tests/Architecture/ArchitectureTest.php
    - apps/api/config/game.php
    - .planning/ROADMAP.md
  modified:
    - apps/api/bootstrap/app.php
    - apps/api/app/Providers/AppServiceProvider.php
key-decisions:
  - "Neutral Game\\ PHP namespace so the product name stays a config value"
  - "Integer-only economy with checked arithmetic; floats forbidden for player-owned value"
  - "PHPStan analyses first-party code only; Laravel stock config and Pest fluent API excluded (DEBT-002)"
  - "Phase 00 executed outside the GSD plan/execute loop, so this is a retrospective record"
duration: single session
completed: 2026-08-24
---

# Phase 00: Repository Bootstrap Summary

**A master repository that builds, tests and documents itself, with all 55 phases planned to an executable level.**

## Performance
- **Duration:** one session
- **Tasks:** 4 plan groups completed
- **Files created:** monorepo skeleton, Laravel app, Expo app, GSD plan

## Accomplishments
- Monorepo with npm workspaces (`apps/mobile`, `packages/*`) alongside the PHP API.
- Laravel 13.26.1 on PHP 8.4 with a `Game\` module namespace mapped to `modules/`.
- Horizon configured with six queue tiers: critical, gameplay, realtime, notifications, analytics, low.
- Filament back office gated behind an `is_staff` flag and `User::canAccessPanel()`.
- Shared kernel with the primitives every later phase depends on.
- Expo SDK 57 / RN 0.86 with strict TypeScript and monorepo-aware Metro.
- All 55 phases specified in `.planning/ROADMAP.md` with goals, dependencies and observable success criteria.

## Task Commits
1. **Monorepo, Laravel, Expo, shared kernel and quality gates** - `530f7db`

## Verification Results

Every gate was executed, not assumed:

| Gate | Command | Result |
|------|---------|--------|
| Tests | `./vendor/bin/pest` | 33 passed, 300 assertions |
| Static analysis | `./vendor/bin/phpstan analyse` | 0 errors (level 8 + strict rules) |
| Formatting | `./vendor/bin/pint --test` | passed |
| Migrations | `php artisan migrate` | 5 migrations applied |
| Seeds | `php artisan db:seed` | passed |
| Boot | `php artisan about` | boots, UTC, Reverb + Octane wired |
| Roadmap | `gsd-tools roadmap analyze` | 55 of 55 phases parsed |

## Decisions & Deviations

**Decisions:** recorded in `.planning/PROJECT.md` and `docs/adr/`.

**Deviations from the original brief:**
- The brief specified `docs/gsd/` for the plan. The installed GSD tooling reads `.planning/`,
  so the machine-readable plan lives there and `docs/gsd/` holds the human-facing narrative
  (dependency graph, execution rules, decision log). Writing only to `docs/gsd/` would have
  left `/gsd:autonomous` with nothing to read.
- Three defects were found and fixed during bootstrap rather than deferred: a `Date::use()`
  call that broke application boot, `env()` used inside a seeder (returns null once config is
  cached), and a Horizon gate referencing a non-existent column.

## Known Limitations

- The host lacks `pdo_pgsql` and the Docker daemon was stopped, so PostgreSQL/PostGIS
  migrations were **not** executed on this machine. Migrations were verified against SQLite.
  Phase 01 makes Docker the canonical environment and adds a CI job that runs migrations
  against real Postgres + PostGIS.
- `config/` and `tests/` are excluded from PHPStan (DEBT-002).
