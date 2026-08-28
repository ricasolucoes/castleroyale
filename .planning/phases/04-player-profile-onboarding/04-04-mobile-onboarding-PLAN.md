---
wave: 4
depends_on: ["04-02", "04-03"]
files_modified:
  - apps/mobile/app/
  - apps/mobile/src/features/onboarding/
  - apps/mobile/src/api/
  - apps/mobile/__tests__/
  - packages/localization/locales/
autonomous: true
---

# Plan 04-04: Mobile onboarding and first-run flow

## Goal
Guide a newly authenticated account through world selection and naming, then
land it on the server-created starter city.

## Tasks

1. Add generated-contract-backed world selection and player naming requests
   with stable idempotency keys.
2. Add world list and name form screens using existing design tokens and all
   supported locale catalogues, including full/closed/content errors.
3. Restore the bootstrap document through TanStack Query, preserve server
   state outside Zustand, and navigate to the city screen after success.
4. Add component and request tests for first-run navigation, retry and
   localized error states.

## Acceptance criteria

- A new player can choose an open world, choose a valid name and reach city.
- Full, closed and rejected-name errors are rendered by error code.
- Mobile typecheck, lint and tests pass.

## Verification

- Run mobile typecheck, lint and tests plus full backend and contract checks.
