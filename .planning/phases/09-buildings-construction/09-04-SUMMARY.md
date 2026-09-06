---
phase: 09-buildings-construction
plan: 04
subsystem: api
tags: [laravel, pest, construction, unlocks, game-data, architecture-test]

# Dependency graph
requires:
  - phase: 09-buildings-construction
    provides: "09-01 — buildings.json requirements[] arrays (34 levels carry the Palace gate as data)"
  - phase: 09-buildings-construction
    provides: "09-02 — BuildingUpgradeService::start() locked transaction, BuildDuration::scaled()"
  - phase: 08-resources-economy
    provides: "CityEconomyService locked debit path — the spend the requirement check now guards"
provides:
  - "Game\\Construction\\Domain\\BuildingRequirement — typed parse of one requirements[] entry, returns null (never throws) on a malformed row"
  - "Game\\Construction\\Application\\BuildingRequirementEvaluator::unmet() — pure function of (buildingCode, targetLevel, currentLevels) returning building_code => level-it-needed"
  - "BUILDING_REQUIREMENTS_NOT_MET carries a missing[] detail naming every unmet prerequisite"
  - "Architecture test forbidding any building-code string literal inside modules/Construction"
affects: [09-03-completion-idempotency-reconciler, 10-technology-research, 12-training-system]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Generic data-driven gate: the evaluator names no building code, so Phase 10 technology prerequisites and Phase 12 training-building unlocks reuse it unchanged"
    - "Precedence between competing refusals asserted explicitly by test rather than left to statement order"

key-files:
  created:
    - apps/api/modules/Construction/Domain/BuildingRequirement.php
    - apps/api/modules/Construction/Application/BuildingRequirementEvaluator.php
    - apps/api/tests/Feature/Construction/BuildingRequirementsTest.php
  modified:
    - apps/api/modules/Construction/Application/BuildingUpgradeService.php
    - apps/api/tests/Architecture/ArchitectureTest.php

key-decisions:
  - "The requirement read happens INSIDE the city row lock already held by start(), not before it. A prerequisite true when the client rendered its sheet may be false by the time the cost is spent; the lock decides, not the client's snapshot. The read cannot observe a torn state because every CityBuilding mutation (ConstructionCompletionService::completeOverdueLocked) takes the same city lock first."
  - "Refusal precedence is fixed and tested: BUILDING_MAX_LEVEL beats an unmet requirement, and an unmet requirement beats BUILD_QUEUE_FULL. A player is told the permanent reason before the temporary one."
  - "BuildingRequirement::fromArray returns null on a malformed row rather than throwing — a bad data row must not 500 a live upgrade endpoint; the import-time validator in 09-01 is where malformed data is caught loudly."
  - "The architecture test greps modules/Construction for any building-code literal (palace|barracks|academy|warehouse|walls) rather than only 'palace' — a narrow test would pass while progression drifted into PHP one code at a time."

patterns-established:
  - "A gameplay gate reads its rule from game-data and names no content code in PHP; the architecture test is what keeps it that way."

requirements-completed: [REQ-05, REQ-06]

# Metrics
duration: ~20min (interrupted mid-Task-2 by an account spend limit; completed inline)
completed: 2026-09-06
---

# Phase 09 Plan 04: Building Requirements & Unlocks Summary

**The `requirements[]` arrays authored in 09-01 now actually gate an upgrade, evaluated inside the same lock that spends the cost, and `BUILDING_REQUIREMENTS_NOT_MET` names exactly which prerequisite is missing.**

## What was built

`BuildingRequirement` parses one `requirements[]` entry into a typed value object. `BuildingRequirementEvaluator::unmet()` is a pure function over the city's current building levels that returns the prerequisites the target level still needs — reading them from `GameDataCatalog::buildingLevel()`, naming no building code itself.

`BuildingUpgradeService::start()` calls the evaluator inside its existing locked transaction, after the max-level check and before the queue-slot check, throwing `BUILDING_REQUIREMENTS_NOT_MET` with a `missing[]` detail when anything is unsatisfied.

A new architecture test walks `modules/Construction` and fails on any building-code string literal, so the Palace gate cannot creep back into PHP.

## Verification

| Check | Result |
|---|---|
| `pest --filter=BuildingRequirements` | 6 passed, 28 assertions |
| Full API suite | 175 passed, 1326 assertions |
| PHPStan level 8 | 0 errors (152 files) |
| Pint | PASS, 219 files |

The six tests cover: refusal names the palace; the same upgrade succeeds once the palace is high enough; each level gates independently; a refusal spends nothing; `BUILDING_MAX_LEVEL` outranks an unmet requirement; an unmet requirement outranks `BUILD_QUEUE_FULL`.

## Issues encountered

Execution was interrupted mid-Task-2 by an account-level monthly spend limit (HTTP 429), not by any problem with the work. Task 1 was already committed (`12d6886`); Task 2's files were complete and correct on disk but unstaged. They were verified (tests, PHPStan, Pint all green) and committed as `34e364c` without modification.

## What this enables

Wave 3's `09-03` can now assert the full refusal matrix. Phase 10 (technology prerequisites) and Phase 12 (training-building unlocks) inherit the evaluator without change — it was written generic precisely so they would not need a second gate.
