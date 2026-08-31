# Observability

Implements ADR-014.

## Correlation

`AttachRequestContext` stamps every API request with a request id, accepts a
sanitised inbound `X-Request-Id`, echoes it back, and shares it into the log
context.

**Jobs must carry and restore that id**, or the trace breaks at the queue
boundary — which is exactly where the interesting failures happen.

One player tap should be one searchable trace, from HTTP through the job that
completed the work.

## Logs

Structured (JSON outside local). Log events with fields, not sentences.

**Never logged:** passwords, tokens, authorization headers, payment data, personal
information. Asserted by a test (threat model T-17).

Slow queries above the threshold are logged with SQL and duration.

## Metrics

**Infrastructure:** request latency percentiles, error rate, queue depth per tier,
job duration, websocket connections, database connections, cache hit rate.

**Domain metrics matter as much** — these are the ones that reveal a broken game
rather than a broken server:

| Metric | Reveals |
|--------|---------|
| Economy inflation per world | Duplication exploit or bad balance |
| Battles per hour, duration distribution | Combat health |
| March volume and failure rate | March system health |
| Reconciler recovery count | Something upstream is failing |
| Ledger reconciliation drift | A duplication bug, immediately |

Domain metrics are emitted from **domain events** (ADR-007), not scraped from the
database, so they add no query load.

## The reconciler signal

A reconciler that has to recover rows means a job did not run. It heals the
player's experience — and it must **not** heal silently. Every reconciler emits a
count, and a non-zero count is visible on the dashboard (Phase 49).

## Tracing

OpenTelemetry over OTLP, `OTEL_ENABLED=false` by default and enabled per
environment. Sampling configured per environment and measured in Phase 39.

## Dashboards and alerts

Built in Phase 49. Alerts fire on documented thresholds and reach an **on-call
human** — an alert nobody receives is not an alert.

## Audit

Distinct from logging. Every administrative or economy-touching action writes an
immutable audit row: actor, action, target, before, after, reason, IP, timestamp.

An admin action without a stated reason is **refused** (Phase 34).

## Access control

- **Horizon** (`/horizon`) exposes job payloads — player ids, resource deltas,
  battle seeds. Gated by `viewHorizon`; staff only outside local.
- **Filament** (`/admin`) requires `is_staff`.
- Neither is ever public.

## Institutional public surface

The public health check is GET /up; the institutional routes are read-only and
must remain available for site.home, site.features, site.support, site.privacy,
site.terms, site.sitemap and site.robots.

Support submissions emit structured events with locale, outcome and a correlation
identifier:

| Signal | Meaning | Operator response |
|--------|---------|-------------------|
| institutional_support_submission_succeeded | Valid mail accepted by the configured transport | Confirm normal delivery if a user reports a delay |
| validation failure | Input rejected before mail delivery | Review abuse patterns; do not inspect or log secrets |
| rate limit (429) | Email/IP pair exceeded 5 requests per minute | Check for abuse and leave the limiter in place unless the threshold is intentionally changed |
| institutional_support_submission_failed | Mail transport failed | Check mail credentials, transport health and queue/SMTP logs; the form shows a safe retry message |

These events intentionally exclude passwords, tokens, account/world identifiers,
raw headers and full message content.
