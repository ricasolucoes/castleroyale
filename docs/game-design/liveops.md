# LiveOps

Built in Phases 31–32, operated from Phase 54.

## The requirement

**An operator must be able to create, schedule, start and end an event without an
engineer and without a deploy.** If LiveOps needs engineering every week, the
LiveOps system has failed, and the demonstration of that end-to-end flow is the
acceptance criterion for Phase 31.

## Events

Time-boxed activities configured as data:

| Type | Shape |
|------|-------|
| Double resources | Global production modifier |
| Barbarian invasion | Spawns NPC camps world-wide |
| City siege | A defensive scenario |
| Seasonal war | Alliance-versus-alliance scoring |
| Alliance competition | Cooperative goals |
| World boss | A shared high-health target |
| Regional conquest | Territory scoring in a region |

An inactive event's endpoints return `EVENT_NOT_ACTIVE`.

## Feature flags

Database-backed, cached with a documented TTL. A disabled flag returns
`FEATURE_DISABLED` and the client hides the entry point — so a half-finished
feature can ship dark and be enabled for a cohort.

Flag changes propagate within the cache TTL. The window is documented; it is not
promised as instant.

## Seasons

A longer cycle with its own ruleset, rankings, rewards and map modifiers.

Season rules **override** base rules only where declared. What resets and what
persists must be documented explicitly and enforced by tests — ambiguity here is
what destroys player trust in a competitive cycle.

## Audit is not optional

Every event and flag change writes an audit record naming the operator, the
before value and the after value.

LiveOps without audit is how a server economy dies quietly: a modifier set to
1000% at 3am, noticed a week later, with no record of who or when.

## The content pipeline

The payoff of ADR-013 and ADR-015:

```
edit JSON in packages/game-data
  → validator runs in CI
  → review as a normal pull request
  → import command
  → version bump
  → live, no deploy
```

New buildings, units, heroes and technologies ship as **validated data**, not
code. Phase 54 demonstrates this end to end, including a rollback of a bad content
release within a documented window.

## Player health reporting

On a fixed schedule, not on request: retention, churn, economy inflation per
world, and match fairness. These are the metrics that reveal a game drifting
before players leave over it.
