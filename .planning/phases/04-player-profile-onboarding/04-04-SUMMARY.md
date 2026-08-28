---
phase: 04-player-profile-onboarding
plan: 04
subsystem: mobile
tags: [expo, onboarding, world-selection, first-run]
provides: [mobile-onboarding, session-routing]
affects: [05-world-architecture, 07-city-foundation]
---

# Phase 04 Plan 04 Summary

The mobile app now restores sessions through the authenticated world list,
routes accounts without a player to onboarding, renders world availability and
population using design tokens, submits the selected world and name through the
generated API contract, and lands on the city screen after the server creates
the starter state. Errors are rendered by machine-readable code and server
state remains in TanStack Query.

## Validation

- `npm run typecheck` → passed for all workspaces.
- `npm run lint` → passed for all workspaces with zero warnings.
- `npm test -- --runInBand` → 4 suites, 12 tests passed.
- `cd apps/api && ./vendor/bin/pest` → 109 tests, 916 assertions passed.
- `cd apps/api && ./vendor/bin/phpstan analyse --memory-limit=1G` → passed, 0 errors.
- `cd apps/api && ./vendor/bin/pint --test` → passed.
- `make migrate` → `Nothing to migrate` in Docker.
- `make test-postgres` → 5 tests, 6 assertions passed.
- isolated-index `npm run contracts:check` → passed; `git diff --check` → passed.
