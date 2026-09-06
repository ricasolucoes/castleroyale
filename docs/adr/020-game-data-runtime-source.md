# ADR-020: The JSON bundle stays the runtime source of game data in Phase 09

**Status:** Accepted
**Date:** 2026-09-06

## Context

`09-CONTEXT.md` § Game data starts here calls for `php artisan game:import-data`.
ADR-013 states balance data is "authored as versioned JSON in
`packages/game-data/`, validated in CI, and imported into the database by
`php artisan game:import-data`. The database is the runtime source of truth; the
JSON is the reviewable one."

This project already shipped Phases 05-08 against `GameDataCatalog`, an adapter
that reads the JSON bundle from disk **at request time**. Seven consumers
(`CityBootstrapService`, `BuildingUpgradeService`, `CityStateService`,
`WorldGenerationService` and others across Phases 06-08) depend on its read
methods (`buildings()`, `starter()`, `citySlots()`, `effectsForBuildings()`, and
so on). None of them read from a database table of imported content — no such
table exists.

09-CONTEXT.md's own escape hatch applies here: "If one is genuinely unworkable,
write an ADR and record it in `docs/gsd/DECISIONS.md` rather than quietly
designing around it."

## Decision

**The versioned JSON bundle stays the runtime source of truth in Phase 09.**
`game:import-data` is real and load-bearing — it *loads and validates* the
bundle at import time (Task 2 of this plan), naming every problem it finds. It
is the import-time half of ADR-013's "validated in CI and again on import," and
it keeps the name `game:import-data` so a database-backed runtime path can be
slotted in behind it later without renaming anything a designer or CI job
already calls.

Balance still never appears in PHP; that half of ADR-013 is unchanged and is
now enforced harder than before, by the architecture test added in this plan
(`it('keeps cost, duration and effect tables out of PHP')`).

What changes from ADR-013's literal wording is narrow: the database is not yet
the thing `GameDataCatalog` reads from. The JSON bundle is both the reviewable
copy and, for now, the runtime copy.

## Alternatives

**Make the database the runtime source now, as ADR-013 originally specified.**
Rejected for Phase 09. This would require: a `buildings` (and eventually
`units`, `technologies`, ...) table and migration, an importer that upserts rows
keyed on `code`, a rewrite of `GameDataCatalog`'s seven consumers to query the
database instead of reading `data/*.json`, and new database bootstrapping
(seeding the imported rows) for every `RefreshDatabase` feature test that
touches a building. None of this is required by any Phase 09 success criterion,
and all of it would rewrite read paths that Phases 05-08 already prove correct
and that this phase's own construction-queue work (09-02 through 09-05) is
being built against concurrently.

**Read the JSON at request time forever, and never revisit this.** Rejected —
that would silently abandon ADR-013's stated design without a record, which is
exactly what 09-CONTEXT.md's escape hatch warns against.

## Consequences

- Content changes (a rebalanced building cost, a new locale string) still
  require a deploy until the database path exists. That trade is recorded here,
  not silent.
- `config/game.php`'s "Game data source" comment is amended (this plan, Task 3)
  to say the bundle is read at runtime and validated by `game:import-data`,
  with the database import deferred per ADR-020, rather than asserting the
  database is already the runtime source.
- The database becomes the runtime source when a phase actually needs content
  to ship without a deploy or to be staged from the back office — Phase 31
  (Events & LiveOps) or Phase 34 (Admin), whichever lands first. Until then, a
  database table nobody reads would be exactly the ceremonial structure
  ADR-001 rejects.
- `game:import-data`'s validation rules (shape, levels, duplicates, dangling
  references, the Palace gate, translations, starter integrity) do not change
  when the database path is added later — only what happens after validation
  succeeds (currently: nothing further; later: upsert into the database) does.
