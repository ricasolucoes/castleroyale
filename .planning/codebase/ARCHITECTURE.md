# Architecture

Full detail: `docs/architecture/overview.md`, `docs/backend/architecture.md`, ADR-001.

## Shape

A **modular monolith**. One Laravel deployable; domains are modules under
`apps/api/modules/` in the `Game\` namespace, with boundaries enforced by
architecture tests rather than by network calls.

```
Interface  ->  Application  ->  Domain
Infrastructure  ->  Domain
```

`Domain` depends on nothing but PHP and `Game\Shared\Domain`.

## The rule that overrides everything

**The server owns the truth** (ADR-006). The client sends *intent*; the server
computes *outcome*. If a value can be converted into power or resources, the
client never supplies it — not the cost, not the duration, not the result.

## Non-negotiables

These are enforced mechanically. Breaking one fails the build.

| Rule | Enforced by |
|------|-------------|
| Domain layer imports no `Illuminate\*` | `tests/Architecture/ArchitectureTest.php` |
| Domain never calls `now()`, `config()`, `auth()`, `request()`, `cache()` | same |
| Everything under `Game\` declares `strict_types` | same |
| No `dd`, `dump`, `var_dump`, `die`, `sleep` anywhere | same |
| No float for anything a player owns | `ResourceAmount` raises; PHPStan level 8 |
| Every gameplay query filters `world_id` | review + tests (ADR-012) |
| Time comes from the injected `Clock` | architecture test |

## Layering, applied honestly

Use the four layers **where they earn their keep**. A module whose job is a lookup
table does not need four folders holding one pass-through class each. ADR-001
rejects ceremonial structure explicitly. Prefer fewer, meaningful files.

## Cross-module communication

Domain events (ADR-007), past tense, naming a fact: `BuildingCompleted`,
`MarchArrived`, `BattleFinished`. Never reach into another module's Eloquent
models or `Domain` namespace.

**Queued listeners must be idempotent.** They are retried, and reconcilers race
them deliberately.

## The Shared kernel — use it, do not reinvent it

| Need | Use |
|------|-----|
| Current time | `Game\Shared\Domain\Time\Clock` (injected) |
| A resource quantity | `Game\Shared\Domain\Economy\ResourceAmount` |
| A basket of resources | `Game\Shared\Domain\Economy\ResourceBundle` |
| A player-safe failure | `Game\Shared\Application\Error\GameException` + `ErrorCode` |
| An API response | `Game\Shared\Interface\Http\ApiResponse` |

Adding a new error means appending to the `ErrorCode` enum **and** to
`packages/contracts/openapi.yaml`. Never invent an ad-hoc error shape.

## The canonical command

Every resource-spending action follows the locked shape in
`docs/backend/architecture.md`: transaction → `lockForUpdate` → recompute cost
server-side → re-check affordability **inside** the lock → mutate + ledger →
persist timing from `Clock`.

Idempotency is checked before the transaction. Locking and idempotency solve
different problems and both are required.

## Timed gameplay

Delayed job does the work; a scheduled reconciler catches what the job missed.
The completion path is guarded on `completed_at IS NULL`, never on `finishes_at`.
