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

Phases 00 through 09 are done and committed (including the inserted Phase 02.1).
Phase 10 — Technology & Research — is the next executable phase.

Phase 09 (2026-09-07) made construction real. All five ROADMAP criteria verified
independently: the eighteen planned buildings load from versioned game data with the
Palace gate expressed as a `requirements[]` row rather than PHP, and a new architecture
test forbids any cost, duration, effect table or building-code literal inside
`modules/Construction`; starting an upgrade debits atomically and stamps `started_at` /
`finishes_at` from the injected Clock in UTC, with a test proving a request body carrying
its own timestamps, duration, cost and target level changes nothing; the completion job
run twice completes once; and killing the queue worker mid-timer leaves the reconciler to
finish every overdue order exactly once — a second pass and the resurrected job both
change nothing, with exactly one `CityStateChanged` across the whole sequence.
`BUILD_QUEUE_FULL` and `BUILDING_MAX_LEVEL` both return 400, and the queue ceiling is read
from config rather than hardcoded. Requirement evaluation runs inside the same lock that
spends the cost, so a prerequisite cannot be raced.

Phase 09 also corrected a false confidence in its own plan. The prescribed falsification —
remove the `whereNull('completed_at')` guard from `ConstructionCompletionService` and watch
the job test fail — does not fail, because `CompleteConstruction` short-circuits on its own
guard first. The service guard that the *reconciler* depends on was therefore unproven. A
fifth test calling the service directly was added; it does fail without the guard (two
`CityStateChanged` events instead of one), and the verifier reproduced that split
independently. The lesson is recorded: a falsification must target the specific guard, not
merely a guard.

The mobile client regained the upgrade CTA Phase 07 deliberately removed, now in five
mutually exclusive server-decided states, above a construction queue strip that shows
occupancy and jumps to whatever is building. Affordability is computed from
`resources.current` alone — never the interpolated resource bar, which ticks between polls
and would offer a button the server is about to refuse.

Phase 08 (2026-09-06) made the economy trustworthy. All five ROADMAP criteria verified:
production is still computed from elapsed server time on read (a city closed six hours and
one polled every minute reach the identical total); every ledger row now records a typed
`source` and `destination` party, with `EconomyLedger::record()` the only sanctioned write
path and `update()`/`delete()` guarded to throw; two competing spends resolve to exactly one
success and one INSUFFICIENT_RESOURCES, proven by an interleaved-race test that was
adversarially tampered with twice to confirm it fails without the race; storage caps hold and
WAREHOUSE_CAPACITY_EXCEEDED became reachable from real code for the first time; and summing
the ledger reproduces the balance exactly under a 120-step randomised property test seeded by
ECONOMY_PROPERTY_SEED so any red run is replayable. The mobile client gained a persistent
resource bar whose interpolation is clamped to capacity, never extrapolates backward, and is
never fed into an affordability check — the server still owns the truth.

Phase 07 (2026-09-05) gave a player a city with a fixed, addressable roster of 18 build
plots and replaced the card list with a real scene. All five ROADMAP criteria verified:
the one-city-per-tile rule is now enforced by the database constraint (the unique-index
violation is caught and returned as TILE_OCCUPIED, proven by a race test), `CityData.slots`
always returns the full roster with each plot empty or holding exactly one building, the
city screen measures its own frame at runtime and renders tappable plots holding a 44pt
touch floor, the private `city.{id}` channel is proven on one allow and three deny paths,
and CITY_NOT_OWNED returns 400 — never 404 — leaking nothing. REQ-01 is materially
advanced but still spans later phases, so it stays Active.

One item from Phase 07 is still outstanding and needs a human: the four generated
city-scene art assets. `tools/generate-city-assets.py` is written and ready, but the Gemini
"Jogos" GCP project (436393374436) has a hard image-generation quota of 0 — a billing gate,
not a rate limit — and the OpenAI fallback is out of credit. The scene ships on `bg.sunken`
with interim vector glyphs until billing is enabled and that script is run (UI-SPEC Flagged
Assumption 3). Phase 09's eighteen building glyphs ship the same way, which 09-CONTEXT.md
had already scoped: building *visuals* belong to Phase 44 (Visual Polish), not here.

(On 2026-09-06 four concept images — castle, village house, legendary sword, royal soldier —
were generated outside this pipeline and left untracked in `assets/`, each with a provenance
`.prompt.md`. They are marketing-scale concept art, not the chroma-keyed game-ready sprites
the asset rules describe, and they are not wired into the client.)

Phase 02 (2026-08-26) implemented the design system and mobile shell, validating REQ-08 (touch targets, tokens, premium UI) and REQ-13 (localization pt-BR, en, es). The mobile app now contains core components (built on the local `@castleroyale/tooling/design-tokens`; `@shopify/restyle` was removed in Phase 02 to avoid a second styling vocabulary), an Expo Router tab shell, light/dark mode support, and an automated touch-target testing suite.

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

Last updated: 2026-09-07
