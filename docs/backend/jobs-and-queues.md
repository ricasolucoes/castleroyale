# Jobs and queues

## Tiers

Six Horizon tiers, configured in `config/horizon.php`. Separation is both a
scheduling and an observability decision (ADR-014): a backed-up analytics queue
must look different from a backed-up gameplay queue.

| Tier | Purpose | Tries | Timeout |
|------|---------|-------|---------|
| `critical` | Ledger writes, purchases — must not be lost or reordered | 5 | 30s |
| `gameplay` | Construction, research, training, marches, battles | 3 | 60s |
| `realtime` | Broadcast fan-out; short, loss-tolerant | 2 | 15s |
| `notifications` | Push and mail | 3 | 30s |
| `analytics` | Product events; entirely best-effort | 2 | 120s |
| `low` | Exports, housekeeping, backfills | 2 | 600s |

Choosing a tier is a design decision, not a default. A job on the wrong tier can
starve gameplay.

## Idempotency

**Every job that grants value must be idempotent.** Jobs are retried on failure,
and after a worker failover a job may run twice. The reconciler also races the
job deliberately.

The standard guard is a state column:

```php
public function handle(Clock $clock): void
{
    DB::transaction(function () use ($clock): void {
        $upgrade = BuildingUpgrade::query()
            ->lockForUpdate()
            ->find($this->upgradeId);

        // Already completed — by an earlier attempt or by the reconciler.
        if ($upgrade === null || $upgrade->completed_at !== null) {
            return;
        }

        $upgrade->complete($clock->now());
    });
}
```

Never guard on `finishes_at` alone; it does not record whether the work was done.

## Correlation

Jobs carry the request id from `AttachRequestContext` and restore it into the log
context, so one player action is one searchable trace across processes (ADR-014).

## Failure handling

- Retries use exponential backoff.
- Exhausted jobs land in `failed_jobs` and are pruned after 7 days.
- A failed **gameplay** job is a player-visible defect and must alert (Phase 49).
- A failed **analytics** job is not, and must never fail the request that caused it.

## Transactional outbox

For events whose loss is unacceptable — reward grants, purchase fulfilment,
ledger mirroring — the event row is written **in the same transaction** as the
state change and relayed afterwards by a dedicated worker.

Applied deliberately and sparingly (ADR-007). Most events are safe to lose; a
table write per analytics event is not a trade worth making.

## Rules

- Jobs receive **identifiers, not models**. A serialised model is stale by the
  time the job runs.
- Jobs re-read and re-validate. The world changed between dispatch and execution.
- No job assumes it is the only one running.
- Long work belongs on `low`, never on `gameplay`.
