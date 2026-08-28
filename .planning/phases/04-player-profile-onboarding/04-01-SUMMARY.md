---
phase: 04-player-profile-onboarding
plan: 01
subsystem: player
tags: [player, onboarding, names, world-scoping]
provides: [player-membership, player-name-policy, starter-player-tests]
---

# Phase 04 Plan 01 Summary

The Player entity is a ULID-backed, world-scoped presence separate from the
identity account. Database uniqueness enforces one player per account/world and
one name per world. The application validates Unicode-safe names against length,
format and a configured deny list, returning `CONTENT_REJECTED` for rejected
content and `CONFLICT` for duplicate membership or names.

Validation is covered by `PlayerMembershipTest` and `PlayerOnboardingTest`.
