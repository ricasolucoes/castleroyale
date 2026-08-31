# Backend delivery plan

## Current audit — 2026-08-27

The backend runs locally on the development Mac through the root
`docker-compose.yml`. The current stack exposes the Laravel app on
`http://localhost:8080`, Reverb on `ws://localhost:8081`, Horizon on
`http://localhost:8080/horizon`, Mailpit on `http://localhost:8025` and MinIO on
`http://localhost:9001`. The containers are healthy in this workspace.

No Terraform, Kubernetes, cloud-provider, DNS or production deployment manifest was
found. Therefore production is **not currently hosted in a cloud environment**.
Phase 50 remains the gate that selects/provisions production, secrets, backups,
restore drills, TLS and network boundaries. Until then, `localhost` is the only
verified host.

## Target shape

One Laravel modular monolith owns three isolated surfaces:

1. **Institutional site** — public, server-rendered Blade pages, legal/support and
   SEO. It never reads or mutates game state.
2. **Game API** — `/api/v1`, contract-first OpenAPI, authoritative gameplay rules,
   PostgreSQL/PostGIS as truth, Redis only for cache/locks/queues, Reverb for
   realtime and Horizon for jobs.
3. **Operations** — `/admin` Filament, staff-only, audited GM actions and later
   moderation/analytics. No operator mutation bypasses the domain/application
   rules or the ledger.

The code organization is correct directionally: game code lives under
`apps/api/modules/<Module>` with Domain/Application/Infrastructure/Interface
boundaries; `apps/api/app` is framework glue; balance data belongs in
`packages/game-data`; the mobile contract comes from `packages/contracts`.
It is not complete yet: the API still has future phases, the back office currently
has only account/device-session resources, and the root site was still the Laravel
welcome page before Phase 02.1.

## GSD execution sequence

| Stream | GSD phases | Outcome |
|---|---:|---|
| Public presence | 02.1 | Localized institutional site, SEO, legal pages and support, without a game web client |
| Identity and playable API | 03–12 | Authenticated player, worlds, city, economy, construction, technology and units |
| Combat and multiplayer API | 13–27 | Heroes, armies, marches, PvE/PvP, territory, alliances, chat, diplomacy and trade |
| Retention and live operations | 28–37 | Quests, rankings, events, seasons, notifications, Filament GM tooling, moderation, analytics and anti-cheat |
| Hardening | 38–47 | Performance, load, connectivity, accessibility, localization, polish and balance passes |
| Release and hosting | 48–54 | Alpha/beta, Terraform production, store pipeline, launch readiness, global launch and post-launch operations |

The existing roadmap is the machine-readable source of truth. Phase 34 is the
complete management plan: it must add resources for players, cities, armies,
heroes, alliances, worlds, events and the ledger, with mandatory reason + audit
rows for economy/destructive actions. The current Filament panel is only a
foundation, not that finished management surface.

## Non-negotiable gates

- Every API mutation has an OpenAPI contract, named error code, idempotency and
  server-side outcome calculation.
- Every player-owned economy mutation runs in a transaction, locks the authoritative
  row, re-checks affordability and writes the ledger.
- Every world-scoped query carries `world_id`; no cross-world read is accepted.
- Every timed rule uses the injected `Game\\Shared\\Domain\\Time\\Clock`.
- Every admin economy/destructive mutation requires a reason and writes actor,
  action, target, before, after, reason, IP and UTC timestamp.
- Production is not “done” until real Postgres/PostGIS migrations, CI, backup/PITR,
  restore RTO/RPO, secret scanning, TLS and external checks have passed.

## Immediate next action

Execute Phase 02.1 plans, then resume Phase 03. Do not mark Phase 34 or Phase 50
complete based on the current `/admin` redirect or healthy local Docker stack;
their roadmap success criteria require the full resources, audit controls and
production recovery evidence.
