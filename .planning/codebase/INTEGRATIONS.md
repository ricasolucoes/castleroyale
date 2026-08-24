# Integrations

## Internal services (Docker, Phase 01)

| Service | Host:port | Purpose |
|---------|-----------|---------|
| api | `localhost:8080` | Laravel |
| postgres | `postgres:5432` | PostgreSQL 16 + PostGIS 3 |
| redis | `redis:6379` | Cache, locks, queues |
| reverb | `localhost:8081` | Websockets |
| horizon | — | Queue supervisor |
| minio | `localhost:9000` | S3-compatible object storage |
| mailpit | `localhost:8025` | Mail catcher |

Inside the Docker network use service names; from the host use `localhost`.

## Endpoints that exist today

| Endpoint | Auth | Notes |
|----------|------|-------|
| `GET /api/v1/health` | none | Status, dependency checks, content versions |
| `GET /up` | none | Laravel framework health |
| `/admin` | session + `is_staff` | Filament back office |
| `/horizon` | `viewHorizon` gate | Queue dashboard |

Everything else is built by its phase.

## Third-party (planned, not integrated)

| Provider | Purpose | Phase |
|----------|---------|-------|
| Apple Sign In | Social auth — token verified **server-side** | 03 |
| Google Sign In | Social auth — token verified **server-side** | 03 |
| Expo Push | Push notifications | 33 |
| Sentry (or equivalent) | Crash reporting | 48 |
| OTLP collector | Traces and metrics | 14 config, 49 operationally |
| App Store / Play Console | Distribution via EAS | 51 |
| IAP providers | Purchases — receipts verified **server-side** | 50+ |

None are wired. Credentials are environment variables, absent by default.

## Integration rules

- **Never trust a client-supplied token or receipt.** Verify against the provider,
  server-side, every time (threat model T-09, T-20).
- **No secret in the repository.** Environment variables locally, a managed secret
  store in production (Phase 50). CI scans for leaks.
- **Every outbound call needs a timeout and a failure path.** A third-party outage
  must degrade a feature, never take down the API.
- **Analytics and crash reporting are best-effort.** They may never block or fail a
  gameplay request (Phase 36).
