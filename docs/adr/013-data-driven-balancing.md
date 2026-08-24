# ADR-013: Data-driven balancing

**Status:** Accepted
**Date:** 2026-08-24

## Context

Balance changes constantly and is the work of designers, not only engineers. If a
building's cost lives in a PHP class, every tuning pass is a code change, a code
review, a deploy and a risk. Worse, the numbers scatter: some in classes, some in
config, some inline in a migration, and nobody can answer "what is the actual
cost curve" without grepping.

## Decision

**No balance number appears in application code.**

Balance data is authored as **versioned JSON in `packages/game-data/`**, validated
in CI, and imported into the database by `php artisan game:import-data`. The
database is the runtime source of truth; the JSON is the reviewable one.

Datasets: `buildings`, `units`, `technologies`, `heroes`, `quests`, `events`,
`nobility`, `world` generation parameters.

A **validator** runs in CI and rejects a dataset containing:

- dependency cycles (technology trees, building requirements)
- negative costs or durations
- references to ids that do not exist
- duplicate ids
- missing translation keys
- unlock requirements that can never be satisfied

What stays in `config/game.php` is deliberately different: **structural limits**,
not balance. `max_concurrent_marches` bounds the worst case for the server;
`build_time` for a level 7 barracks is balance and lives in game data.

An architecture test asserts that no numeric literal used as a cost, duration or
effect appears in `Game\` code.

## Alternatives

**Balance in PHP config files.** Rejected: still a deploy, still not reviewable
by designers, and no validation.

**Balance in the database only, edited via admin UI.** Rejected as the *source*:
changes become unreviewable and unversioned, and there is no diff in a pull
request. The admin UI can *read* and stage, but JSON in git is the origin.

**A spreadsheet exported to JSON.** A reasonable future workflow, and compatible
with this decision since JSON remains the interchange format.

## Consequences

- A tuning pass is a data pull request: diffable, reviewable, revertable.
- Content can ship without a backend deploy (Phase 54), which is what makes
  LiveOps viable.
- The validator is load-bearing. A bad dataset that reaches production can break
  the game more thoroughly than a bad deploy, so validation runs in CI and again
  on import.
- Game data is versioned, and battles record the version they ran under, so a
  rebalance never rewrites history (ADR-015).
