# Documentation

## Start here

| If you are… | Read |
|-------------|------|
| An AI agent about to change code | [`../AGENTS.md`](../AGENTS.md) |
| New to the project | [`../README.md`](../README.md), then [`GLOSSARY.md`](GLOSSARY.md) |
| About to execute a phase | [`gsd/EXECUTION_RULES.md`](gsd/EXECUTION_RULES.md) |
| Wondering why something is the way it is | [`adr/README.md`](adr/README.md) |

## Map

### Decisions
- [`adr/`](adr/README.md) — 17 architecture decision records

### Architecture
- [`architecture/TDD.md`](architecture/TDD.md) — technical design overview
- [`architecture/overview.md`](architecture/overview.md) — system shape
- [`architecture/modules.md`](architecture/modules.md) — module boundaries
- [`architecture/data-flow.md`](architecture/data-flow.md) — how a request becomes state
- [`architecture/scalability.md`](architecture/scalability.md) — where the limits are

### Backend
- [`backend/architecture.md`](backend/architecture.md) — layering, the canonical command
- [`backend/jobs-and-queues.md`](backend/jobs-and-queues.md) — tiers and idempotency
- [`backend/schedulers.md`](backend/schedulers.md) — reconcilers

### Database
- [`database/conventions.md`](database/conventions.md) — binding rules for migrations
- [`database/schema.md`](database/schema.md) — entity map

### API
- [`api/api-guidelines.md`](api/api-guidelines.md) — envelope and error codes
- [`api/authentication.md`](api/authentication.md)
- [`api/idempotency.md`](api/idempotency.md)
- [`api/versioning.md`](api/versioning.md)

### Realtime
- [`realtime/architecture.md`](realtime/architecture.md) — channels and authorisation
- [`realtime/events.md`](realtime/events.md) — event catalogue

### Mobile
- [`mobile/architecture.md`](mobile/architecture.md) — the state boundary
- [`mobile/navigation.md`](mobile/navigation.md)
- [`mobile/state-management.md`](mobile/state-management.md)
- [`mobile/offline-strategy.md`](mobile/offline-strategy.md)

### Design
- [`design-system/tokens.md`](design-system/tokens.md)
- [`ui/screens.md`](ui/screens.md) — screen inventory
- [`ui/wireframes.md`](ui/wireframes.md)

### Game design
- [`game-design/GDD.md`](game-design/GDD.md) — the index
- core-loop · economy · buildings · units · heroes · combat · technology ·
  world · alliances · trade · nobility · progression · liveops

### Security
- [`security/threat-model.md`](security/threat-model.md) — 20 mapped threats
- [`security/anti-cheat.md`](security/anti-cheat.md)
- [`security/rate-limits.md`](security/rate-limits.md)

### Operations
- [`operations/environments.md`](operations/environments.md)
- [`operations/deployment.md`](operations/deployment.md)
- [`operations/observability.md`](operations/observability.md)
- [`operations/backups.md`](operations/backups.md)
- [`operations/disaster-recovery.md`](operations/disaster-recovery.md)

### The plan
- [`gsd/MASTER_PLAN.md`](gsd/MASTER_PLAN.md)
- [`gsd/DEPENDENCIES.md`](gsd/DEPENDENCIES.md) — graph and critical path
- [`gsd/STATUS.md`](gsd/STATUS.md)
- [`gsd/EXECUTION_RULES.md`](gsd/EXECUTION_RULES.md)
- [`gsd/DECISIONS.md`](gsd/DECISIONS.md)

The machine-readable plan is in [`../.planning/`](../.planning/) and is authoritative.

## Writing docs here

Explain **why**, not what. Code says what it does; documentation says why it is
that way and what breaks if you change it. If a document only restates the code,
delete it — it will rot and mislead.
