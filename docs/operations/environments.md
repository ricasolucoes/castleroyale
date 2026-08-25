# Environments

| Environment | Purpose | Data | Debug tooling |
|-------------|---------|------|---------------|
| **local** | Developer machine, Docker | Seeded fixtures | Enabled |
| **testing** | Automated test runs | Ephemeral, in-memory | Enabled |
| **development** | Shared integration server | Seeded, resettable | Enabled |
| **staging** | Production mirror | Anonymised copy or seeded | **Disabled** |
| **production** | Live | Real | **Disabled, enforced** |

Configurations are never mixed. A staging value in production is an incident.

## Debug tooling is double-gated

`config('game.debug_menu.enabled')` requires **both** `DEBUG_MENU_ENABLED=true`
**and** `APP_ENV` in `local`/`testing`/`development`.

A single flag is one typo away from an exposed economy. Phase 37 asserts by test
that no debug endpoint, debug menu or seeded credential is reachable when
`APP_ENV=production`.

The same applies to `game.time_scale`, which is forced to 1 outside local.

## Configuration boundaries

| Kind | Where | Example |
|------|-------|---------|
| Secrets | Env → managed secret store in production | `DB_PASSWORD`, `REVERB_APP_SECRET` |
| Structural limits | `config/game.php` | `max_concurrent_marches` |
| Balance | `packages/game-data/` | Building costs, unit stats |
| Runtime toggles | Database feature flags | `new_map_renderer` |

Balance is never in config. Structural limits are never in game data. Mixing them
is the mistake ADR-013 exists to prevent.

## Local

Docker is the canonical environment (Phase 01) — the host may lack `pdo_pgsql`.

```bash
make setup   # build images, install deps, migrate, seed
make dev     # start the stack, wait for healthy, assert /api/v1/health
make smoke   # prove criteria 1 and 5 in one command
make test    # run the suite
```

| Service | Host port |
|---------|-----------|
| API | 8080 |
| Reverb | 8081 |
| PostgreSQL | 5432 |
| Redis | 6379 |
| MinIO | 9000 |
| Mailpit | 8025 |

| Service | Healthcheck |
|---------|-------------|
| api | `curl -fsS http://localhost:8000/api/v1/health` |
| postgres | `pg_isready -U dominion -d dominion` |
| redis | `redis-cli ping` |
| reverb | PHP `fsockopen` on 8081 |
| horizon | `php artisan horizon:status` |
| minio | `mc ready local` |
| mailpit | `/mailpit readyz` |

`make dev` uses `docker compose up -d --wait`, so it cannot return successfully while
any service is unhealthy.

## Production

Provisioned from Terraform; a plan against live state must show **no drift**
(Phase 50). Secrets from a managed store, never the repository. Backups with PITR
and a **rehearsed** restore drill — an untested backup is a theory.
