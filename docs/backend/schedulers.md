# Schedulers

## Philosophy

Timed gameplay is driven by **delayed queue jobs**, not by polling. A city with a
building finishing in four hours dispatches one job with a four-hour delay; it is
not visited by a cron sweep every minute.

The schedule is the **safety net**, not the mechanism.

## Why reconcilers exist

A delayed job can be lost:

- the worker holding it is killed mid-execution
- Redis fails over and the delayed entry is not persisted
- a deploy drains workers at the wrong moment
- the job throws past its retry budget

Without reconciliation, the player's building is simply never finished. That is
unacceptable and, worse, invisible — nobody files a bug for a timer that quietly
never fired.

## The pattern

Each timed subsystem registers a reconciler that finds rows where
`finishes_at <= now()` and `completed_at IS NULL`, and completes them.

```php
Schedule::command('game:reconcile-construction')
    ->everyMinute()
    ->withoutOverlapping();
```

Reconcilers **race the job they back up**. Both may run; the completion path is
idempotent, so exactly one takes effect. This is the design, not a hazard.

A reconciler that has to do work is a signal something failed upstream. Each one
emits a metric of how many rows it recovered, and a non-zero count should be
visible (Phase 49) rather than silently healing.

## Registered by phase

| Reconciler | Phase |
|------------|-------|
| Construction | 09 |
| Research | 10 |
| Training | 12 |
| Marches | 15 |
| Battles | 17 |
| Shield expiry | 19 |
| Market order expiry | 27 |
| Quest resets | 28 |
| Sanction expiry | 35 |

## Standing schedule

Registered in Phase 00:

- `horizon:snapshot` every five minutes — queue metrics
- `queue:prune-failed --hours=168` daily
- `sanctum:prune-expired --hours=24` daily

## Rules

- Every scheduled command uses `withoutOverlapping()`. A slow run must not stack.
- Reconcilers are idempotent and safe to run at any frequency.
- Nothing on the schedule may take longer than its interval; if it does, it belongs
  on the `low` queue with the schedule only dispatching it.
- All schedule times are UTC.
