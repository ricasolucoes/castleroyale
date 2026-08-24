# ADR-007: Event-driven communication between modules

**Status:** Accepted
**Date:** 2026-08-24

## Context

Completing a building must update the city, recompute power, progress quests,
possibly grant a nobility rank, emit an analytics event and push a notification.
If the construction module calls all of those directly, it acquires a dependency
on half the codebase and every new feature edits it again.

## Decision

Modules publish **domain events** describing what happened, in past tense:
`BuildingCompleted`, `MarchArrived`, `BattleFinished`, `CityCaptured`,
`PlayerJoinedAlliance`, `RewardGranted`.

Rules:

- An event names a fact, not a request. `BuildingCompleted`, never `UpdatePower`.
- The module that owns the state publishes the event. Other modules subscribe.
- A listener that performs I/O or slow work is queued, on the tier appropriate to
  its urgency (ADR-014).
- **Queued listeners must be idempotent.** They will be retried, and after a
  failover they may run twice. Handlers that grant value must guard with a
  natural key or a dedupe record.
- Analytics listeners are always queued on the `analytics` tier and may never
  block or fail a domain transaction (Phase 36).

Events that must not be lost — reward grants, purchase fulfilment, ledger
mirroring — use a **transactional outbox**: the event row is written in the same
transaction as the state change and relayed afterwards. See `docs/backend/jobs-and-queues.md`.
The outbox is applied deliberately and sparingly, not to every event.

## Alternatives

**Direct service calls.** Simpler to trace, rejected for the coupling above.
Still used where the caller genuinely needs the result synchronously.

**A message broker (RabbitMQ, Kafka).** Rejected as premature: in-process events
plus Redis queues cover the need at this scale, and ADR-001 keeps extraction open.

**Outbox for every event.** Rejected: most events are safe to lose (a missed
analytics event is not a defect worth a table write per event).

## Consequences

- Adding a reaction to an existing fact means adding a listener, not editing the
  publisher.
- Tracing a flow requires reading listeners, not one call stack. The correlation
  id from `AttachRequestContext` is propagated into jobs so a single action can be
  followed end to end.
- Idempotency is a standing obligation on every queued listener, tested explicitly.
- Ordering is not guaranteed across queues. Anything order-dependent must either
  share a queue or carry an explicit sequence.
