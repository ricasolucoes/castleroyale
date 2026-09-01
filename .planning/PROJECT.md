# Castle Royale

## What This Is

A mobile MMO of empire building, territorial conquest and real-time strategic
warfare. A player starts with one small city, grows it into an empire, joins an
alliance, and competes with thousands of other players for control of a shared,
persistent world map.

Two client-facing surfaces plus one internal: a React Native app (the game), a
Laravel API (the authority), and a Filament back office (LiveOps and game
mastering). It is an original product — the genre is shared with games like
Empire War: Age of Heroes, nothing else is.

## Core Value

**The server owns the truth.** A player's empire — every resource, every troop,
every metre of territory — must be exactly what the server says it is, always,
even when the client is hostile. Everything else in this product is negotiable;
this is not.

## Requirements

### Validated

<!-- Shipped and confirmed valuable. -->

- [x] **REQ-08** — Premium-feeling mobile UI at 60 FPS, one-handed, offline-aware (Validated in Phase 02: Design System & Mobile Shell)
- [x] **REQ-13** — Localisation-ready from the first screen (pt-BR, en, es) (Validated in Phase 02: Design System & Mobile Shell)

### Active

<!-- Current scope. Building toward these. -->

- [ ] **REQ-01** — Server-authoritative gameplay: no client-supplied outcome is ever trusted
- [ ] **REQ-02** — Integer-only economy with an auditable ledger; no float touches a resource
- [ ] **REQ-03** — Deterministic, replayable battle simulation versioned against balance changes
- [ ] **REQ-04** — Persistent shared world map with viewport-scoped loading and delta updates
- [ ] **REQ-05** — Timed gameplay (build/research/train/march) anchored to server time only
- [ ] **REQ-06** — Data-driven balancing: no balance number hardcoded in application code
- [ ] **REQ-07** — Alliances with permission-based authority, rallies, territory and diplomacy
- [ ] **REQ-09** — Idempotent, concurrency-safe commands; no double-spend under any race
- [ ] **REQ-10** — New-player protection so beginners are not farmed by veterans
- [ ] **REQ-11** — LiveOps: events, seasons and feature flags without a backend deploy
- [ ] **REQ-12** — Full observability: structured logs, metrics, tracing, economy/combat telemetry
- [ ] **REQ-14** — Back office with audited game-master tooling and moderation
- [ ] **REQ-15** — Multi-world sharding so population can scale horizontally
- [ ] **REQ-16** — Public institutional presence in Laravel with localized, accessible, SEO-ready pages and a protected support channel

### Out of Scope

<!-- Explicit boundaries. Includes reasoning to prevent re-adding. -->

- **Real payment processing** — the store module is architected but no IAP provider is
  integrated before Phase 50; monetisation must never become load-bearing for the core loop
- **Microservices** — a modular monolith with enforced boundaries; extraction is a later
  option, not a starting position (ADR-001)
- **Cross-world play** — players belong to exactly one world; cross-world features would
  invalidate the sharding model before it has proven itself
- **Player-authored content** — no map editors or custom scenarios; the moderation and
  anti-cheat surface is already large enough
- **Web/desktop game clients** — mobile-first, and mobile-only, through launch. A public institutional site is allowed as a Laravel server-rendered surface; it is not a game client.
- **PvP real-time co-op battles** — battles resolve server-side; live tactical control by
  two humans simultaneously is explicitly deferred past Phase 54

## Context

**Greenfield.** Phase 00 is complete and committed: the monorepo, a working Laravel 13
API, an Expo mobile app, and passing quality gates. Nothing else exists yet.

**Genre constraints that shape the architecture:**

- Players expect a city to keep producing while the app is closed. Production is therefore
  computed from elapsed time on read, not ticked by a background loop.
- Timers are the core monetisation and pacing lever. They must be exact, resumable after a
  worker crash, and immune to device clock changes.
- A world map holds tens of thousands of entities. The client can never load all of it.
- Combat is the emotional payload. It must be reproducible so that a player who lost can be
  shown exactly why, and so a rebalance never rewrites history.
- Alliance politics is the retention engine. Permissions, chat and diplomacy are core, not
  social garnish.

**Anti-cheat posture:** assume the client is fully compromised. Assume request bodies are
edited, timestamps forged, and the app decompiled. Every design decision is made against
that assumption rather than patched for it later.

## Constraints

- **Tech stack (backend)**: PHP 8.4, Laravel 13, PostgreSQL + PostGIS, Redis, Horizon,
  Reverb, Octane, Filament — fixed by the project brief
- **Tech stack (mobile)**: React Native + Expo, TypeScript strict, Expo Router, TanStack
  Query, Zustand, Reanimated, Gesture Handler, Skia, MMKV — fixed by the project brief
- **Architecture**: modular monolith, `Domain / Application / Infrastructure / Interface`
  layering applied only where it earns its keep (ADR-001)
- **Quality gates**: Pint, PHPStan level 8 + strict rules, Pest (incl. architecture tests),
  TypeScript strict, ESLint — all must pass before a phase is DONE
- **Data integrity**: PostgreSQL is the only source of economic truth; Redis is never
  authoritative for anything a player owns
- **Time**: every persisted timestamp is UTC; the device clock is never an input
- **Security**: no secret in the repository; no debug tooling reachable in production
- **Local environment**: the host lacks `pdo_pgsql` and the Docker daemon may be stopped —
  Postgres/PostGIS work runs in Docker or CI, never assumed available on the host

## Key Decisions

<!-- Decisions that constrain future work. Add throughout project lifecycle. -->

| Date | Decision | Why | ADR |
|------|----------|-----|-----|
| 2026-08-24 | Modular monolith, not microservices | One deployable, enforced module boundaries, extract later if load demands it | ADR-001 |
| 2026-08-24 | Neutral `Game\` PHP namespace | The product name is a config value; renaming must never touch code | ADR-002 |
| 2026-08-24 | PostgreSQL + PostGIS for the world | Spatial indexing for viewport queries; one database, not two | ADR-004 |
| 2026-08-24 | Redis for cache/locks/queues only | Never authoritative for player-owned state | ADR-005 |
| 2026-08-24 | Server-authoritative everything | The client is assumed hostile | ADR-006 |
| 2026-08-24 | Deterministic seeded battle simulation | Replayability, auditability, and cheat detection | ADR-009 |
| 2026-08-24 | Integer-only economy | Float rounding drift mints or destroys value | ADR-010 |
| 2026-08-24 | ULID identifiers on game entities | Non-enumerable, sortable, safe to expose | ADR-016 |
| 2026-08-24 | Balance data in versioned JSON, imported to DB | Reviewable in PRs, hot-swappable, replay-safe | ADR-013, ADR-015 |
| 2026-08-24 | Hand-authored OpenAPI, generated TS types | One contract both sides verify against | ADR-017 |
| 2026-08-24 | GSD research disabled in config | Phases ship with CONTEXT + canonical refs; research would re-derive settled decisions | — |
| 2026-08-27 | Public institutional site is a Laravel surface, not a web game client | The project needs discoverability, legal pages and player support without duplicating the mobile game client or weakening the modular monolith boundary | ADR-018 |

## Current Understanding

Phases 00, 01, and 02 are done and committed. Phase 02.1 is now the next executable
phase for the public institutional surface; Phase 03 remains the next gameplay API
phase after it.

Phase 02 (2026-08-26) implemented the design system and mobile shell, validating REQ-08 (touch targets, tokens, premium UI) and REQ-13 (localization pt-BR, en, es). The mobile app now contains core components (built strictly with `@shopify/restyle`), an Expo Router tab shell, light/dark mode support, and an automated touch-target testing suite.

Phase 01 (2026-08-25) made Docker the canonical environment — seven healthy services,
migrations and a double seed proven against real PostGIS, `/api/v1/health` reporting every
dependency, and GitHub Actions gating lint, static analysis, backend tests and mobile
typecheck behind one `CI` check (green run + two recorded red runs). The repository is
public at `ricasolucoes/castleroyale`. REQ-06 and REQ-12 are advanced (CI validates
`packages/game-data`; health checks exist) but not yet validated — both span many phases.

On 2026-08-25, 13 phases (55–67, "Google Play Sidekick") were appended to ROADMAP.md
after Phase 54; the roadmap now lists 68 phases.

The plan is deliberately front-loaded: all original 55 phases are specified in ROADMAP.md with
goals, dependencies and observable success criteria, and every phase carries a
pre-written CONTEXT.md locking its implementation decisions. Executing agents are expected
to read and follow, not to re-plan. A genuine limitation discovered mid-flight is recorded
as an ADR or a DECISIONS entry — it is not silently designed around.

Last updated: 2026-08-27
