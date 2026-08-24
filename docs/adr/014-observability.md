# ADR-014: Observability via OpenTelemetry and structured logs

**Status:** Accepted
**Date:** 2026-08-24

## Context

When a player reports "my troops vanished", the answer must be reconstructable
from telemetry. The action began as a tap, became an HTTP request, spawned a
queued job, resolved a battle and wrote a ledger entry — across processes. Plain
text logs cannot connect those.

## Decision

**Structured logs, metrics and traces**, correlated by a single request id.

- `AttachRequestContext` middleware stamps every API request with a correlation
  id (accepting a sanitised inbound `X-Request-Id`) and echoes it back. The id is
  shared into the log context and propagated into queued jobs, so one player
  action is one searchable trace.
- Logs are **structured** (JSON in non-local environments). Never log passwords,
  tokens, authorization headers, payment data or personal information — asserted
  by a test in Phase 37.
- **OpenTelemetry** for traces and metrics, exported via OTLP. Disabled by default
  (`OTEL_ENABLED=false`) and enabled per environment.
- **Queue tiers are an observability tool as much as a scheduling one.** Six tiers
  — critical, gameplay, realtime, notifications, analytics, low — so a backed-up
  analytics queue is visibly distinct from a backed-up gameplay queue, and so slow
  work cannot delay a player's build command.
- **Domain metrics are first class**, not just infrastructure ones: economy
  inflation, battle counts and durations, march volume, queue depth per tier,
  websocket connection count.
- Slow queries above the threshold are logged with SQL and duration.

## Alternatives

**APM vendor SDK only (New Relic, Datadog agent).** Rejected as the primary
abstraction: it couples instrumentation to a vendor. OpenTelemetry exports to any
of them, including those vendors.

**Logs only.** Rejected: no percentiles, no distributed causality.

**Instrument later, when there is a problem.** Rejected: the incident is exactly
when you cannot add instrumentation.

## Consequences

- Every queued job must carry and restore the correlation id, or the trace breaks
  at the queue boundary. This is a standing requirement on job base classes.
- Structured logging costs discipline: log events with fields, not sentences.
- OpenTelemetry adds overhead when enabled; sampling is configured per environment
  and measured in Phase 39.
- Domain metrics must be emitted from domain events (ADR-007), not scraped from
  the database, so they do not add query load.
