---
phase: 04-player-profile-onboarding
plan: 02
subsystem: world
tags: [world, capacity, selection, idempotency]
provides: [world-list, world-selection, capacity-errors]
---

# Phase 04 Plan 02 Summary

Authenticated clients can list worlds with population, structural capacity,
open/full/closed status and account membership. Selection is an idempotent
transaction that locks the selected world and computes capacity server-side;
closed worlds return `WORLD_CLOSED` and full worlds return `WORLD_FULL`.

Validation is covered by `WorldSelectionTest`, including capacity isolation and
replay behavior.
