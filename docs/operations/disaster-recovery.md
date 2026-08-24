# Disaster recovery

Documented and rehearsed in Phases 50 and 52.

## Objectives

| Objective | Target | Verified by |
|-----------|--------|-------------|
| **RPO** — acceptable data loss | ≤ 5 minutes | PITR drill |
| **RTO** — acceptable downtime | ≤ 2 hours | Full restore drill |

These are targets until a drill measures them. Phase 50 records the measured
values against these numbers; if reality is worse, the *documented* target changes
to the measured one rather than the measurement being quietly discarded.

## Scenarios

| Scenario | Response | Expected impact |
|----------|----------|-----------------|
| Web instance fails | Health check removes it; replicas absorb | None |
| All web instances fail | Redeploy previous image | Full outage until restored |
| Queue worker fails | Horizon restarts; **reconcilers recover overdue work** | Delayed completions, no loss |
| Redis lost entirely | Recreate; caches refill, sessions reconnect | Degraded, **not corrupted** |
| Reverb lost | Clients fall back to polling and reconnect with backoff | Degraded realtime |
| Database primary fails | Promote replica | Up to RPO of loss |
| Database corrupted by a bad migration | PITR to just before it | Up to RPO of loss |
| Corrupted by a game-master action | PITR, informed by the audit log timestamp | Up to RPO of loss |
| Region outage | Restore into another region from backup | Up to RTO |

The row that matters most is Redis: because it is never authoritative, its total
loss is a degradation rather than an incident. That property was bought
deliberately in ADR-005.

## Roles

| Role | Owns |
|------|------|
| **Incident commander** | Decisions and sequencing. One person, explicitly named |
| **Operations** | Executes recovery |
| **Communications** | Player-facing status |
| **Scribe** | Timeline for the postmortem |

For a small team one person may hold several roles — but the roles are named
explicitly at the start of the incident, not assumed.

## Procedure

1. **Declare.** Name the incident commander. Ambiguity about who decides is the
   most expensive part of most incidents.
2. **Assess.** Scope, player impact, data-loss risk.
3. **Communicate.** Status to players early, even without a cause.
4. **Stabilise.** Stop the bleeding before finding the root cause. Maintenance
   mode is preferable to a corrupting economy.
5. **Recover.** Follow the scenario runbook.
6. **Verify.** Health checks, **ledger reconciliation**, spot-check player state.
7. **Resume.** Lift maintenance, monitor closely.
8. **Postmortem.** Blameless, within a week, with actions that have owners.

## After a restore

Always, before resuming play:

- Ledger reconciliation across every world
- Reconcilers run to complete work orphaned by the gap
- Queue depth checked for a backlog spike
- Battles in the affected window spot-checked for replay integrity

A restore that resumes play without ledger reconciliation can put a duplication
bug into production and call it a recovery.

## Communication

Players are told what happened, what was affected and what was restored. If
progress was lost, say how much. A silent rollback is discovered by players and
costs more trust than the outage did.
