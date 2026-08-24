# Structure

```
/
├── apps/
│   ├── api/                      Laravel 13 — the authority
│   │   ├── app/                  Framework glue only (providers, contracts)
│   │   ├── modules/              Game code, namespace Game\  <- work happens here
│   │   │   ├── Shared/           Kernel: Clock, economy VOs, ErrorCode, envelope
│   │   │   └── Platform/         Health, feature flags, versioning
│   │   ├── config/game.php       Product identity, structural limits, versions
│   │   ├── database/migrations/
│   │   ├── routes/api.php        /api/v1 group
│   │   ├── routes/channels.php   Broadcast authorisation (deny by default)
│   │   └── tests/{Unit,Feature,Architecture}/
│   └── mobile/                   Expo SDK 57
│       ├── app/                  Expo Router file-based routes
│       └── src/                  Components, hooks, stores, api client
├── packages/
│   ├── contracts/                openapi.yaml + generated TS types
│   ├── game-data/                Versioned balance JSON + validator
│   ├── localization/             pt-BR, en, es catalogues
│   └── tooling/                  ESLint, tsconfig bases, design tokens
├── infrastructure/{docker,nginx,terraform,monitoring}/
├── docs/                         Human-facing documentation
│   ├── adr/                      17 decision records
│   ├── architecture/ api/ backend/ database/ security/
│   ├── game-design/ mobile/ realtime/ operations/
│   └── gsd/                      Narrative plan (dependency graph, rules, decisions)
└── .planning/                    Machine-readable GSD plan  <- /gsd:autonomous reads this
    ├── PROJECT.md ROADMAP.md STATE.md MILESTONES.md config.json
    ├── codebase/                 These files
    └── phases/NN-slug/           CONTEXT.md, PLAN.md, SUMMARY.md per phase
```

## Where new code goes

| Building | Location |
|----------|----------|
| A new domain (world, cities, combat…) | `apps/api/modules/<Module>/` |
| A pure rule or value object | `modules/<Module>/Domain/` |
| A use case / command handler | `modules/<Module>/Application/` |
| An Eloquent model or repository | `modules/<Module>/Infrastructure/` |
| A controller, request, resource, job | `modules/<Module>/Interface/` |
| A migration | `apps/api/database/migrations/` |
| A balance number | `packages/game-data/data/` — **never** in PHP |
| A structural limit | `apps/api/config/game.php` |
| A screen | `apps/mobile/app/` (route) + `apps/mobile/src/` (implementation) |
| An API type | Generated from `packages/contracts/openapi.yaml` — never hand-written |

## Two plan locations, on purpose

- `.planning/` is the **machine-readable** plan the GSD tooling parses. Edit
  `ROADMAP.md` and `STATE.md` here.
- `docs/gsd/` is the **human-facing** narrative: dependency graph, execution
  rules, decision log. It does not drive tooling.

Keep them consistent. `.planning/` wins if they disagree.
