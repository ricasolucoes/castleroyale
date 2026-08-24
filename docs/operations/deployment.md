# Deployment

Production infrastructure is built in Phase 50; the store pipeline in Phase 51.
This document is the target design.

## Backend

Container images built from `infrastructure/docker/api/Dockerfile`, target
`production`: Octane on FrankenPHP, non-root user, config/route/event caches
baked, OPcache with `validate_timestamps=0`.

Four workload types from the same image:

| Workload | Command | Scales on |
|----------|---------|-----------|
| web | `octane:start` | Request latency |
| queue | `horizon` | Queue depth per tier |
| realtime | `reverb:start` | Websocket connections |
| scheduler | `schedule:work` | Exactly one instance |

The scheduler must be a **single** instance. Two schedulers double every
reconciler run — harmless where handlers are idempotent, which is why they must
be, but wasteful and confusing in metrics.

## Sequence

```
build image  →  push  →  migrate  →  roll web  →  roll queue  →  roll realtime
```

Migrations run **before** the new code, so they must be backward compatible with
the running version. A column rename is therefore two deploys: add and backfill,
then remove. Never one.

Rollback is redeploying the previous image. A migration that cannot be rolled
back must be flagged in review before it merges.

## Zero downtime

- Health checks gate traffic (`/api/v1/health`)
- Octane workers drain before termination
- Horizon finishes in-flight jobs on `SIGTERM`
- Reverb clients reconnect with backoff and jitter, then resync

## Configuration

Environment variables from a managed secret store (Phase 50). The repository
contains no secrets; CI scans for leaks.

`APP_ENV=production` forces `game.time_scale` to 1 and disables the debug menu
regardless of any flag — asserted by a test.

## Mobile

EAS build and submit (Phase 51). Signing credentials live in the build service,
never the repository.

**OTA policy** is documented and enforced: JavaScript-only changes may ship OTA;
anything native, and anything changing rules the server also enforces, requires a
store release. Shipping a rules change OTA that the server rejects is a
self-inflicted outage.

Staged rollout must be haltable, and rollback is rehearsed before launch.

## Environments

See [`environments.md`](environments.md). Configuration is never shared between
environments — a staging value in production is an incident.
