# Backups

> A backup that has never been restored is an optimistic theory stored somewhere
> expensive.

Implemented and, more importantly, **rehearsed** in Phase 50.

## What is backed up

| Store | Method | Frequency | Retention |
|-------|--------|-----------|-----------|
| PostgreSQL | Automated snapshot + WAL archiving (PITR) | Continuous WAL, daily base | 30 days |
| Object storage | Bucket versioning + lifecycle | Continuous | 90 days |
| Redis | **Not backed up** | — | — |
| Secrets | Managed store's own backup | Per provider | Per provider |

**Redis is deliberately excluded.** It holds cache, locks, queues and ephemeral
realtime state — nothing a player owns (ADR-005). If Redis is lost entirely, the
game degrades: caches miss, queues drain, sessions reconnect. Nothing is
corrupted. Restoring stale queue state would be *worse* than losing it, because
delayed jobs would fire against a world that has moved on. The reconcilers exist
precisely to recover that case.

## Point-in-time recovery

WAL archiving allows recovery to any moment within the retention window. This is
the control for the failure that actually matters: not a lost disk, but a bad
migration or a game-master mistake that corrupts data at a known time.

## Restore drills

A restore is performed, timed and recorded — not assumed:

| Drill | Frequency | Records |
|-------|-----------|---------|
| Full restore to a clean environment | Before launch, then quarterly | Measured RTO |
| PITR to an arbitrary timestamp | Before launch, then quarterly | Measured RPO |
| Object storage version recovery | Before launch | Success/failure |

Phase 52 requires a full restore within the target RTO as a launch gate. Not a
plan for one — an actual completed drill.

## Verification

- Restored databases are checked for schema version and row counts
- Ledger reconciliation runs against the restored data: summing the ledger must
  reproduce balances exactly. A restore that produces a drifting economy is a
  failed restore, even if the process reported success
- Drill results are recorded with date, duration and outcome

## What we do not rely on

- **Replicas as backups.** A replica faithfully replicates a `DROP TABLE`
- **A backup nobody has restored.** See the epigraph
