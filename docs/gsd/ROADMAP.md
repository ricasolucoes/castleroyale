# Roadmap: Castle Royale

## Overview

Fifty-five numbered phases plus decimal insertions carry Castle Royale from an empty repository to a live-service
mobile MMO. The spine is deliberate: prove the server can be trusted before anything
multiplayer is built, prove the economy cannot be duplicated before players can trade,
and prove combat is deterministic before players can lose anything to it.

Phases 00-02 build the machine that builds the game, and Phase 02.1 adds the public
institutional surface. Phases 03-12 produce a single
player who can grow a city — the playable prototype. Phases 13-18 add heroes, armies,
movement and deterministic combat. Phases 19-27 turn it into a multiplayer world with
conquest, alliances and trade. Phases 28-35 add the retention and operations layer.
Phases 36-47 harden, measure and balance. Phases 48-54 ship it and keep it alive.

Dependencies are not a straight line. Several phases are genuinely parallelisable —
see the dependency graph in `docs/gsd/DEPENDENCIES.md`.

## Milestone v0.1: Foundation to Launch

All numbered and inserted phases belong to this milestone. Castle Royale starts at v0.1.0; v1.0.0 is
reserved for a mature product in production, not the first release.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 00: Repository Bootstrap** - Monorepo, Laravel API, Expo app, quality gates, full GSD plan
- [x] **Phase 01: Engineering Foundation** - Docker stack, CI, migrations against Postgres/PostGIS, dev seeds, Makefile (completed 2026-08-25)
- [x] **Phase 02: Design System & Mobile Shell** - Design tokens, core components, Expo Router navigation shell
- [x] **Phase 03: Identity & Authentication** - Accounts, tokens, device sessions, guest play, Apple/Google sign-in (backend complete; mobile auth shell remains) (completed 2026-08-28)
- [x] **Phase 04: Player Profile & Onboarding** - Player entity, world selection, first-run flow, private realtime channel (completed 2026-08-28)
- [x] **Phase 05: World Architecture** - Worlds, regions, tiles, coordinates, PostGIS indexing, viewport queries (completed 2026-08-28)
- [ ] **Phase 06: World Map Rendering** - Skia map canvas, pan/zoom, culling, LOD, tile cache, markers
- [ ] **Phase 07: City Foundation** - City entity, building slots, city screen, private city channel
- [ ] **Phase 08: Resources & Economy** - Production, storage caps, the ledger, atomic spending under concurrency
- [ ] **Phase 09: Buildings & Construction** - Building catalogue, upgrade queue, timers, completion jobs, reconciliation
- [ ] **Phase 10: Technology & Research** - Research tree with explicit dependencies, effects and a cycle validator
- [ ] **Phase 11: Unit System** - Unit catalogue, stats, counter matrix, power contribution
- [ ] **Phase 12: Training System** - Barracks queues, batch training, upkeep, cancellation and refunds
- [ ] **Phase 13: Heroes** - Hero entity, rarity, levels, skills, equipment, assignment bonuses
- [ ] **Phase 14: Army Composition** - Armies, formations, capacity, hero command, power calculation
- [ ] **Phase 15: March System** - March entity, travel time, states, arrival jobs, recall, march limits
- [ ] **Phase 16: PvE World Encounters** - NPC camps, resource nodes, gathering marches, respawn
- [ ] **Phase 17: Combat Engine V1** - Deterministic seeded simulation, damage model, casualties, compact replay
- [ ] **Phase 18: Battle Visualization** - Battle report screen, replay playback, casualty breakdown
- [ ] **Phase 19: PvP** - Player attacks, scouting, plunder, shields, protection rules
- [ ] **Phase 20: Siege & City Capture** - Walls, siege units, city occupation and capture rules
- [ ] **Phase 21: Territory System** - Territorial influence, strategic points, region control
- [ ] **Phase 22: Alliances** - Alliance entity, membership, ranks, granular permissions, donations
- [ ] **Phase 23: Alliance Territory** - Alliance-held territory, fortresses, shared borders
- [ ] **Phase 24: Rally & Reinforcements** - Coordinated multi-player attacks and defensive reinforcement
- [ ] **Phase 25: Chat & Social** - Global, alliance, private and rally chat with anti-spam and moderation hooks
- [ ] **Phase 26: Diplomacy** - War, peace, non-aggression pacts and alliance relations
- [ ] **Phase 27: Market & Trading** - NPC trade, player-to-player trade, transport marches, tax, anti-duplication
- [ ] **Phase 28: Quests & Achievements** - Tutorial, main, daily, weekly, achievement and alliance quests
- [ ] **Phase 29: Nobility** - Social rank progression with requirements and privileges
- [ ] **Phase 30: Rankings** - Leaderboards for power, alliances, PvP, territory, heroes and honour
- [ ] **Phase 31: Events & LiveOps** - Time-boxed events and feature flags configured without a deploy
- [ ] **Phase 32: Seasons** - Season lifecycle, rulesets, map modifiers, rewards and resets
- [ ] **Phase 33: Notifications** - Push notifications, in-app mail and per-category preferences
- [ ] **Phase 34: Admin & Game Master Tools** - Filament resources for every domain, with mandatory audit logging
- [ ] **Phase 35: Moderation** - Reporting, review queue, sanctions, ban enforcement and appeals
- [ ] **Phase 36: Analytics** - Product event pipeline decoupled from domain flow
- [ ] **Phase 37: Security & Anti-Cheat Hardening** - Threat model closure, exploit tests, rate limits, anomaly detection
- [ ] **Phase 38: Performance Optimization** - Query tuning, caching, N+1 elimination, mobile render budget
- [ ] **Phase 39: Load Testing** - Concurrency scenarios from 1k to 100k with documented methodology
- [ ] **Phase 40: Offline & Connectivity Resilience** - Cached reads, reconnection, event ordering and state reconciliation
- [ ] **Phase 41: Accessibility** - Touch targets, contrast, reduced motion, screen reader labels, text scaling
- [ ] **Phase 42: Localization** - pt-BR, en and es catalogues with completeness validation
- [ ] **Phase 43: Audio & Haptics** - Contextual sound, music and haptic feedback with player controls
- [ ] **Phase 44: Visual Polish** - City ambience, particles, transitions, loading states and empty states
- [ ] **Phase 45: Tutorial & FTUE Polish** - Guided first session, contextual hints and progressive disclosure
- [ ] **Phase 46: Economy Balance Pass** - Simulation-driven tuning of production, costs, sinks and pacing
- [ ] **Phase 47: Combat Balance Pass** - Counter tuning, hero impact, siege balance, win-rate analysis
- [ ] **Phase 48: Closed Alpha** - Invite-only build, feedback capture, crash reporting and stability triage
- [ ] **Phase 49: Beta** - Open beta, scaled infrastructure, live monitoring and hotfix path
- [ ] **Phase 50: Production Infrastructure** - Terraform, secrets, backups, PITR, restore drills and disaster recovery
- [ ] **Phase 51: Store Release Pipeline** - EAS build and submit, signing, staged rollout and OTA policy
- [ ] **Phase 52: Launch Readiness** - Go/no-go checklist, runbooks, on-call, support and privacy compliance
- [ ] **Phase 53: Global Launch** - Public release, world opening cadence and launch-window monitoring
- [ ] **Phase 54: Post-launch LiveOps** - Recurring event calendar, balance cadence, content pipeline and health reporting

## Phase Details

### Phase 00: Repository Bootstrap
**Goal**: A master repository that builds, tests and documents itself, with every later phase already planned.
**Depends on**: Nothing (first phase)
**Requirements**: REQ-01, REQ-06
**Milestone**: Foundation
**Success Criteria** (what must be TRUE):
  1. `composer install && ./vendor/bin/pest` passes from a clean clone of apps/api.
  2. `./vendor/bin/phpstan analyse` reports 0 errors at level 8 with strict rules.
  3. `npm install && npm run typecheck` passes at the monorepo root.
  4. `.planning/ROADMAP.md` lists all 55 phases and `gsd-tools roadmap analyze` parses every one.
  5. Every phase directory under `.planning/phases/` contains a CONTEXT.md.
**Plans**: 4 plans

Plans:
- [x] 00-01: Monorepo skeleton, git config, Laravel 13 + Expo installs
- [x] 00-02: Shared kernel: Clock, integer economy value objects, ErrorCode, API envelope
- [x] 00-03: Quality gates: Pint, PHPStan level 8, Pest with architecture tests
- [x] 00-04: Documentation corpus, ADRs and the complete GSD plan

### Phase 01: Engineering Foundation
**Goal**: Any developer runs `make setup && make dev` and gets a working API, database, queue, websocket and admin panel.
**Depends on**: Phase 00
**Requirements**: REQ-06, REQ-12
**Milestone**: Foundation
**Success Criteria** (what must be TRUE):
  1. `docker compose up -d` starts api, postgres+postgis, redis, reverb, horizon and minio, and all report healthy.
  2. `make migrate` applies every migration against PostgreSQL with the PostGIS extension enabled.
  3. `make seed` populates a browsable development dataset and is safe to run twice.
  4. GitHub Actions runs lint, static analysis, backend tests and mobile typecheck on push, and fails the build when any gate fails.
  5. `GET /api/v1/health` returns 200 with all dependency checks true when run inside the Docker network.
**Plans**: 4 plans

Plans:
- [x] 01-01: Docker development stack and Makefile targets
- [x] 01-02: PostgreSQL + PostGIS connection, migration baseline and ULID conventions
- [x] 01-03: Development seeders and reusable test fixtures
- [x] 01-04: GitHub Actions CI for backend, mobile and infrastructure

### Phase 02: Design System & Mobile Shell
**Goal**: A navigable, themed app shell with a documented component library the rest of the client is built from.
**Depends on**: Phase 00
**Requirements**: REQ-08, REQ-13
**Milestone**: Foundation
**Success Criteria** (what must be TRUE):
  1. Design tokens (colour, type, spacing, radius, elevation, motion) exist in one TypeScript source and no screen hardcodes a colour or spacing value.
  2. The five primary tabs render and navigate via Expo Router with typed routes.
  3. Core components (Button, Card, Panel, BottomSheet, ResourceCounter, Timer, Badge, Skeleton) render in a component gallery screen.
  4. Every interactive element has a touch target of at least 44x44 points, verified by a test.
  5. The app renders correctly in both light and dark themes with no unstyled flash on launch.
**Plans**: 4 plans

Plans:
- [x] 02-01: Design tokens and theme provider
- [x] 02-02: Core primitive components and the gallery screen
- [x] 02-03: Expo Router navigation shell with the five primary tabs
- [x] 02-04: Typography, iconography and haptic feedback primitives

### Phase 02.1: Institutional Site and Public Backend Surface (INSERTED)

**Goal**: The Laravel backend serves a production-ready institutional site that explains the game, supports players and publishes legal information without becoming a web game client.
**Depends on**: Phase 01, Phase 02
**Requirements**: REQ-16
**Milestone**: Foundation
**Success Criteria** (what must be TRUE):
  1. The root route and the documented public routes render branded Laravel views, no longer the stock Laravel welcome page, and use `config('game.name')` for the product name.
  2. Public pages exist in pt-BR, en and es for home, game/features, support, privacy and terms, with locale selection that never changes API or game-world state.
  3. The public layout uses the shared semantic design-token names through generated CSS variables; no institutional view contains a literal colour, spacing, radius or font-size value.
  4. Every public page has a unique title, description, canonical URL, language alternate links, accessible landmarks, keyboard-visible focus and a valid generated sitemap and robots response.
  5. The support form validates and rate-limits submissions, never exposes credentials or player-owned state, and records an observable success/failure outcome when mail is configured.
  6. Feature tests cover public route status/locales, legal-page availability, support validation/rate limiting, SEO metadata and the absence of the stock welcome view.
**Plans**: 3 plans

Plans:
- [x] 02.1-01: Laravel public site shell and localized page routes
- [x] 02.1-02: Token-backed visual system, SEO and legal content
- [x] 02.1-03: Support submission, observability and public-surface verification

### Phase 03: Identity & Authentication
**Goal**: Authenticated players can maintain identity across sessions using device-native biometrics and secure token storage.
**Depends on**: Phase 01
**Requirements**: REQ-01, REQ-10, REQ-14
**Milestone**: Playable Prototype
**Success Criteria** (what must be TRUE):
  1. A guest account is created without any user input and can later be upgraded to email/password without losing progress.
  2. Access tokens expire and refresh tokens rotate; a reused refresh token revokes the whole session family and returns TOKEN_EXPIRED.
  3. Credentials are stored only in SecureStore/Keychain on the device, never in MMKV or AsyncStorage, verified by a test.
  4. A player can list their device sessions and revoke one remotely; the revoked device receives DEVICE_SESSION_REVOKED on its next request.
  5. Ten failed sign-in attempts from one IP within a minute return RATE_LIMITED rather than INVALID_CREDENTIALS.
**Plans**: 5 plans

Plans:
- [x] 03-01: Accounts, credentials and the token model with rotation
- [x] 03-02: Device sessions, revocation and rate limiting
- [x] 03-03: Apple and Google sign-in plus guest upgrade
- [x] 03-04: Mobile auth flow, secure storage and session restoration
- [x] 03-05: Back office with audited game-master tooling and moderation

### Phase 04: Player Profile & Onboarding
**Goal**: An authenticated account becomes a named player inside a chosen world, with a private realtime channel.
**Depends on**: Phase 03
**Requirements**: REQ-15, REQ-13
**Milestone**: Playable Prototype
**Success Criteria** (what must be TRUE):
  1. An account can hold at most one player per world, enforced by a unique constraint, and a second attempt returns CONFLICT.
  2. A player picks a world from a list showing population and status; a full world returns WORLD_FULL and a closed one WORLD_CLOSED.
  3. Player names are validated, unique per world, and rejected content returns CONTENT_REJECTED.
  4. The `player.{id}` broadcast channel authorises only that player and denies everyone else, verified by a test.
  5. A new player lands on the city screen with a starting city already created.
**Plans**: 4 plans

Plans:
- [x] 04-01: Player entity, world membership and name validation
- [x] 04-02: World selection and capacity rules
- [x] 04-03: Private player channel authorisation and the client bootstrap document
- [x] 04-04: Mobile onboarding and first-run flow

### Phase 05: World Architecture
**Goal**: A persistent, queryable world of regions and tiles that the client can read a viewport of without ever loading the whole map.
**Depends on**: Phase 01
**Requirements**: REQ-04, REQ-15
**Milestone**: Playable Prototype
**Success Criteria** (what must be TRUE):
  1. A world generates deterministically from a seed: the same seed produces byte-identical terrain, verified by a test.
  2. A viewport query returns only tiles inside the requested bounds and refuses a request larger than the configured tile ceiling with VALIDATION_FAILED.
  3. A viewport query over a fully populated world returns in under 100ms at the 95th percentile, measured against a seeded benchmark dataset.
  4. Tile coordinates are unique per world and enforced by a database constraint.
  5. Region and tile geometry use PostGIS types with a spatial index that the query planner actually uses, verified by EXPLAIN in a test.
**Plans**: 4 plans

Plans:
- [x] 05-01: World, region and tile schema with PostGIS geometry
- [x] 05-02: Deterministic world generation from a seed
- [x] 05-03: Viewport and chunk query API with bounds validation
- [x] 05-04: Spatial indexing and query performance benchmarks

### Phase 06: World Map Rendering
**Goal**: A fluid, gesture-driven world map that stays at 60 FPS with thousands of entities on screen.
**Depends on**: Phase 05, Phase 02
**Requirements**: REQ-04, REQ-08
**Milestone**: Playable Prototype
**Success Criteria** (what must be TRUE):
  1. Panning and zooming a populated map holds 60 FPS on a mid-range device and never drops below 30 FPS, measured with the Expo performance monitor.
  2. Only tiles inside the viewport plus a one-screen margin are fetched, and revisiting a region reads from the client cache without a network call.
  3. Map entities are drawn on a Skia canvas in batches; there is no React component per tile or per marker, verified by an architecture test.
  4. Zoom levels change marker detail (level of detail) rather than rendering every entity at every zoom.
  5. Tapping a tile selects it and opens a detail sheet showing the tile's true server-side contents.
**Plans**: 4 plans

Plans:
- [ ] 06-01: Skia map canvas with pan, zoom and gesture handling
- [x] 06-02: Viewport culling, level of detail and sprite batching
- [ ] 06-03: Tile fetching, client cache and delta application
- [ ] 06-04: Map markers, selection and the target detail sheet

### Phase 07: City Foundation
**Goal**: A player owns a city with addressable building slots, rendered as a living scene rather than a list.
**Depends on**: Phase 04, Phase 05
**Requirements**: REQ-01, REQ-08
**Milestone**: Playable Prototype
**Success Criteria** (what must be TRUE):
  1. Every city occupies exactly one world tile and that tile cannot be claimed twice, enforced by a constraint returning TILE_OCCUPIED.
  2. A city exposes a fixed set of build slots, each either empty or holding exactly one building.
  3. The city screen renders the scene with tappable buildings and reflects server state after a pull-to-refresh.
  4. The `city.{id}` channel authorises the owner and denies non-owners, verified by a test.
  5. Requesting a city the player does not own returns CITY_NOT_OWNED, not 404, and never leaks its contents.
**Plans**: 4 plans

Plans:
- [ ] 07-01: City entity, tile claim and build slots
- [ ] 07-02: City state read API and authorisation rules
- [ ] 07-03: City scene rendering with tappable buildings
- [ ] 07-04: City realtime channel and live state updates

### Phase 08: Resources & Economy
**Goal**: Resources accrue over real time, are capped by storage, and can never be duplicated or spent twice.
**Depends on**: Phase 07
**Requirements**: REQ-02, REQ-09
**Milestone**: Playable Prototype
**Success Criteria** (what must be TRUE):
  1. Production is computed from elapsed server time on read; a city closed for six hours and one polled every minute reach the identical total.
  2. Every resource mutation writes a ledger row recording source, destination, resource, amount, reason and reference.
  3. Two concurrent spend requests for the same resources result in exactly one success and one INSUFFICIENT_RESOURCES, verified by a concurrency test.
  4. Resources never exceed warehouse capacity; overflow is discarded at the cap and recorded, and the API returns WAREHOUSE_CAPACITY_EXCEEDED where relevant.
  5. Summing the ledger for any city reproduces its current balance exactly, verified by a property test over random operation sequences.
**Plans**: 5 plans

Plans:
- [ ] 08-01: Resource storage, production rates and capacity
- [ ] 08-02: Elapsed-time production accrual on read
- [ ] 08-03: The transactional ledger and audit trail
- [ ] 08-04: Locked, idempotent spending and concurrency tests
- [ ] 08-05: Mobile resource bar with live client-side interpolation

### Phase 09: Buildings & Construction
**Goal**: A player queues building upgrades that cost resources, take server-controlled time, and complete reliably even if a worker dies.
**Depends on**: Phase 08, Phase 06
**Requirements**: REQ-05, REQ-06, REQ-09
**Milestone**: Playable Prototype
**Success Criteria** (what must be TRUE):
  1. The eighteen planned buildings load from versioned game data; no cost, duration or effect appears in PHP code, verified by an architecture test.
  2. Starting an upgrade debits resources atomically and writes started_at and finishes_at in UTC; the device clock is never read.
  3. A completion job is idempotent: running it twice completes the upgrade once, verified by a test.
  4. Killing the queue worker mid-timer and running the reconciler completes every overdue upgrade exactly once.
  5. Exceeding the build queue slot limit returns BUILD_QUEUE_FULL and a max-level building returns BUILDING_MAX_LEVEL.
**Plans**: 5 plans

Plans:
- [ ] 09-01: Building catalogue import from game data
- [ ] 09-02: Construction queue, cost debit and timer persistence
- [ ] 09-03: Completion jobs, idempotency and the overdue reconciler
- [ ] 09-04: Requirement and unlock evaluation
- [ ] 09-05: Mobile building detail, upgrade flow and construction queue UI

### Phase 10: Technology & Research
**Goal**: A player researches technologies from an acyclic tree whose effects measurably modify their empire.
**Depends on**: Phase 09
**Requirements**: REQ-06, REQ-05
**Milestone**: Playable Prototype
**Success Criteria** (what must be TRUE):
  1. The technology tree loads from game data and a validator rejects any dataset containing a dependency cycle, a negative cost or a dangling reference.
  2. Researching a locked technology returns TECHNOLOGY_LOCKED and a maxed one returns TECHNOLOGY_MAX_LEVEL.
  3. Only one research runs at a time per player; a second returns RESEARCH_IN_PROGRESS.
  4. A completed technology's effect is observable in a recomputed value, for example production rate rising by the documented amount.
  5. The technology tree screen renders dependencies and shows locked, available, in-progress and completed states distinctly.
**Plans**: 4 plans

Plans:
- [ ] 10-01: Technology schema, effects model and game data import
- [ ] 10-02: Dependency graph validation and the cycle detector
- [ ] 10-03: Research queue, timers and effect application
- [ ] 10-04: Mobile technology tree screen and detail view

### Phase 11: Unit System
**Goal**: A complete, data-driven unit roster with stats and a counter system richer than rock-paper-scissors.
**Depends on**: Phase 10
**Requirements**: REQ-06, REQ-03
**Milestone**: Playable Prototype
**Success Criteria** (what must be TRUE):
  1. Every unit loads from game data with attack, defence, health, speed, range, capacity, cost, training time, upkeep and counter bonuses.
  2. The counter matrix is data-driven and its effect is a documented multiplier, not a hardcoded branch, verified by an architecture test.
  3. Unit power contribution is computed by a pure, unit-tested function with no database access.
  4. A dataset with a duplicate unit id or a negative stat fails validation with a specific message naming the offending id.
  5. The unit catalogue screen lists every unit with its stats and counter relationships.
**Plans**: 4 plans

Plans:
- [ ] 11-01: Unit schema, stats and game data import
- [ ] 11-02: Counter matrix and modifier resolution
- [ ] 11-03: Unit power calculation
- [ ] 11-04: Mobile unit catalogue and detail cards

### Phase 12: Training System
**Goal**: A player trains troops in queues that cost resources and time and produce exactly the promised units.
**Depends on**: Phase 11, Phase 09
**Requirements**: REQ-05, REQ-09, REQ-02
**Milestone**: Playable Prototype
**Success Criteria** (what must be TRUE):
  1. Training a batch debits resources once and produces exactly the requested unit count on completion, verified by a ledger reconciliation test.
  2. Cancelling a training batch refunds the documented proportion and the refund appears in the ledger.
  3. Training capacity is bounded by the relevant building level; exceeding it returns TROOP_CAPACITY_EXCEEDED.
  4. A duplicate training command carrying the same Idempotency-Key produces one batch, not two.
  5. The training screen shows queue progress driven by server timestamps and survives an app restart without drift.
**Plans**: 4 plans

Plans:
- [ ] 12-01: Training queue schema, capacity and cost model
- [ ] 12-02: Batch training jobs, completion and idempotency
- [ ] 12-03: Cancellation, refunds and upkeep accounting
- [ ] 12-04: Mobile training screen and queue management

### Phase 13: Heroes
**Goal**: Heroes exist as collectible, levelling commanders whose bonuses measurably change army and city outcomes.
**Depends on**: Phase 12
**Requirements**: REQ-06, REQ-03
**Milestone**: Internal Alpha
**Success Criteria** (what must be TRUE):
  1. Heroes load from game data with rarity, class, attributes, skills, talents and specialisation.
  2. A hero can be assigned to exactly one role at a time (army command or city governance) and a second assignment returns CONFLICT.
  3. An assigned hero's bonus is observable in the recomputed army or city value it modifies.
  4. Hero experience and levelling are server-computed; a client-supplied level or experience value is ignored entirely, verified by a test.
  5. The hero roster and hero detail screens render rarity, stars, skills and equipment.
**Plans**: 5 plans

Plans:
- [ ] 13-01: Hero schema, rarity and game data import
- [ ] 13-02: Levelling, experience and skill unlocks
- [ ] 13-03: Assignment rules and bonus application
- [ ] 13-04: Equipment slots and stat contribution
- [ ] 13-05: Mobile hero roster, detail and equipment screens

### Phase 14: Army Composition
**Goal**: A player assembles named armies from garrison troops with a hero and formation, bounded by real capacity rules.
**Depends on**: Phase 13
**Requirements**: REQ-03, REQ-09
**Milestone**: Internal Alpha
**Success Criteria** (what must be TRUE):
  1. An army draws only from troops actually present in the city; requesting more returns INSUFFICIENT_TROOPS.
  2. Troops committed to an army are reserved and cannot be committed to a second army, returning ARMY_ALREADY_DEPLOYED.
  3. Army carrying capacity is the sum of its units' capacities and is enforced on every load operation.
  4. Army power is computed from units, hero and researched technology by a pure function, and the breakdown is returned to the client.
  5. The army composition screen shows a live power total and capacity as the player adjusts unit counts.
**Plans**: 4 plans

Plans:
- [ ] 14-01: Army entity, troop reservation and capacity
- [ ] 14-02: Formation model and hero command assignment
- [ ] 14-03: Army power calculation with a transparent breakdown
- [ ] 14-04: Mobile army composition and formation screens

### Phase 15: March System
**Goal**: Armies move across the world over server-computed time, arrive reliably, and can be recalled.
**Depends on**: Phase 14, Phase 06
**Requirements**: REQ-05, REQ-04, REQ-09
**Milestone**: Internal Alpha
**Success Criteria** (what must be TRUE):
  1. March duration is derived from distance and the slowest unit's speed on the server; a client-supplied duration is ignored.
  2. A march transitions scheduled → marching → arrived → returning → completed and no other transition is reachable, verified by a state machine test.
  3. Exceeding the concurrent march limit returns MARCH_LIMIT_REACHED.
  4. Recalling a march returns the army home over the elapsed travel time; recalling an already-engaged march returns MARCH_NOT_CANCELLABLE.
  5. Killing the worker mid-flight and running the reconciler resolves every overdue march exactly once.
**Plans**: 5 plans

Plans:
- [ ] 15-01: March entity, types and the state machine
- [ ] 15-02: Distance and travel time computation
- [ ] 15-03: Arrival jobs, reconciliation and recall
- [ ] 15-04: March limits and concurrency guards
- [ ] 15-05: Mobile march confirmation, live tracking and recall UI

### Phase 16: PvE World Encounters
**Goal**: The world holds NPC camps and resource nodes that give solo players something to do and a reason to march.
**Depends on**: Phase 15
**Requirements**: REQ-04, REQ-10
**Milestone**: Internal Alpha
**Success Criteria** (what must be TRUE):
  1. NPC camps spawn from game data at world generation and respawn on a documented schedule after being cleared.
  2. A gathering march occupies a resource node exclusively; a second march to an occupied node returns TILE_OCCUPIED.
  3. Gathered resources are capped by the army's carrying capacity and credited via the ledger on return.
  4. Attacking an NPC camp above the documented level difference returns INVALID_TARGET rather than a guaranteed loss.
  5. Cleared camps and depleted nodes disappear from the map for every client via a delta update, not a full reload.
**Plans**: 4 plans

Plans:
- [ ] 16-01: NPC camp and resource node entities with spawn rules
- [ ] 16-02: Gathering marches, capacity and node occupation
- [ ] 16-03: Camp clearing, rewards and respawn scheduling
- [ ] 16-04: Mobile target detail and gather flow

### Phase 17: Combat Engine V1
**Goal**: A deterministic battle simulator that produces identical results from identical inputs and stores a replayable, compact record.
**Depends on**: Phase 16, Phase 11
**Requirements**: REQ-03, REQ-01
**Milestone**: Internal Alpha
**Success Criteria** (what must be TRUE):
  1. Given two identical armies and the same simulation seed, running BattleSimulator repeatedly produces byte-identical event timelines and results.
  2. The simulator is pure: it performs no database, cache, clock or random-global access, verified by an architecture test.
  3. Randomness derives only from a server-generated seed the client never sees before resolution.
  4. A battle persists initial state, seed, commands and simulation_version, and replaying an old battle under a newer balance version still reproduces the original result.
  5. Two concurrent attacks on the same target resolve in a defined order without deadlock and without double-applying losses.
**Plans**: 5 plans

Plans:
- [ ] 17-01: Battle schema, participants and the versioned record
- [ ] 17-02: Deterministic seeded RNG and the pure simulation core
- [ ] 17-03: Damage, counters, terrain and morale resolution
- [ ] 17-04: Casualty, loot and experience application inside one transaction
- [ ] 17-05: Replay reconstruction and determinism test suite

### Phase 18: Battle Visualization
**Goal**: A player can see what happened in a battle and why, replayed from the compact server record.
**Depends on**: Phase 17, Phase 02
**Requirements**: REQ-08, REQ-03
**Milestone**: Internal Alpha
**Success Criteria** (what must be TRUE):
  1. A battle report shows both sides' composition, losses, loot and the deciding modifiers.
  2. Replay playback reconstructs the timeline from the stored seed and commands, not from a stored frame dump.
  3. The report renders correctly for a victory, a defeat and a draw, each visually distinct.
  4. Opening a battle the player did not participate in returns FORBIDDEN and reveals nothing.
  5. Replay animation holds 60 FPS on a mid-range device.
**Plans**: 4 plans

Plans:
- [ ] 18-01: Battle report data contract and read API
- [ ] 18-02: Battle report screen with casualty and modifier breakdown
- [ ] 18-03: Skia replay playback with timeline scrubbing
- [ ] 18-04: Victory and defeat presentation states

### Phase 19: PvP
**Goal**: Players attack each other under rules that make aggression meaningful without letting veterans farm beginners.
**Depends on**: Phase 18
**Requirements**: REQ-10, REQ-01
**Milestone**: Multiplayer Alpha
**Success Criteria** (what must be TRUE):
  1. A new player holds an unbreakable shield for the documented duration; attacking them returns PLAYER_PROTECTED.
  2. Attacking a player below the documented power ratio returns POWER_DIFFERENCE_TOO_LARGE.
  3. Attacking from under one's own shield either drops the shield or is refused with ATTACKER_PROTECTED — never both silently.
  4. Plunder is bounded by the target's plunderable resources and the attacker's carrying capacity, and both sides' ledgers reconcile exactly.
  5. Scouting returns information whose accuracy depends on the scout-versus-watchtower comparison, not the full truth by default.
**Plans**: 5 plans

Plans:
- [ ] 19-01: Attack marches, target validation and protection rules
- [ ] 19-02: Shields, beginner zones and power-difference gates
- [ ] 19-03: Scouting, intelligence accuracy and counter-intelligence
- [ ] 19-04: Plunder limits and ledger reconciliation
- [ ] 19-05: Mobile attack flow, scout report and incoming-attack warning

### Phase 20: Siege & City Capture
**Goal**: Cities can be besieged and captured under rules that are decisive but reversible enough to keep a server alive.
**Depends on**: Phase 19
**Requirements**: REQ-01, REQ-10
**Milestone**: Multiplayer Alpha
**Success Criteria** (what must be TRUE):
  1. Wall durability absorbs damage before troops take losses, and siege units apply their documented bonus against fortifications.
  2. Capturing a city transfers ownership atomically; a mid-transfer failure leaves the previous owner intact, verified by a transaction test.
  3. A captured city enters a documented protection window during which it cannot be captured again.
  4. A player cannot lose their last city; the attack resolves as a defeat rather than an account wipe.
  5. City capture emits CityCaptured, notifies both players, and appears in the world map delta for observers.
**Plans**: 5 plans

Plans:
- [ ] 20-01: Wall durability, defence structures and siege damage
- [ ] 20-02: Siege march type and occupation timer
- [ ] 20-03: Atomic capture, ownership transfer and protection window
- [ ] 20-04: Last-city safeguard and recovery mechanics
- [ ] 20-05: Mobile siege UI and capture notifications

### Phase 21: Territory System
**Goal**: Land ownership is a first-class, contestable resource rather than a cosmetic overlay.
**Depends on**: Phase 20
**Requirements**: REQ-04, REQ-07
**Milestone**: Multiplayer Alpha
**Success Criteria** (what must be TRUE):
  1. Territory influence is computed from controlled cities and strategic points by a documented, pure function.
  2. Capturing a strategic point changes regional control and the change is visible on every client's map via a delta update.
  3. Territory boundaries are stored as PostGIS geometry and a point-in-territory query uses the spatial index.
  4. Contested regions are represented explicitly rather than flickering between owners.
  5. Territory control grants the documented bonuses to its holder and they are observable in recomputed values.
**Plans**: 4 plans

Plans:
- [ ] 21-01: Territory and strategic point schema with PostGIS geometry
- [ ] 21-02: Influence calculation and region control resolution
- [ ] 21-03: Contested state handling and capture rules
- [ ] 21-04: Mobile territory overlay rendering

### Phase 22: Alliances
**Goal**: Players form alliances with real internal authority expressed as permissions, not role-name string checks.
**Depends on**: Phase 19
**Requirements**: REQ-07
**Milestone**: Multiplayer Alpha
**Success Criteria** (what must be TRUE):
  1. Every alliance action is gated by a named permission such as `alliance.invite`; no authorisation check compares a rank name, verified by an architecture test.
  2. An alliance cannot exceed its member cap, returning ALLIANCE_FULL, and a player cannot join two alliances, returning ALREADY_IN_ALLIANCE.
  3. Alliance names are unique per world, returning ALLIANCE_NAME_TAKEN on collision.
  4. A member lacking a permission receives ALLIANCE_PERMISSION_DENIED and the attempt is written to the alliance log.
  5. The `alliance.{id}` channel authorises only current members and drops a member immediately on kick.
**Plans**: 5 plans

Plans:
- [ ] 22-01: Alliance entity, membership and lifecycle
- [ ] 22-02: Rank and granular permission model
- [ ] 22-03: Invitations, applications and kick/promote flows
- [ ] 22-04: Donations, alliance treasury and alliance technology
- [ ] 22-05: Mobile alliance screens and member management

### Phase 23: Alliance Territory
**Goal**: Alliances hold and defend collective territory that confers shared benefits.
**Depends on**: Phase 22, Phase 21
**Requirements**: REQ-07, REQ-04
**Milestone**: Multiplayer Alpha
**Success Criteria** (what must be TRUE):
  1. Alliance territory is derived from member cities and alliance-held structures and recomputed when membership changes.
  2. Building inside allied territory grants the documented bonus and inside enemy territory the documented penalty.
  3. Only members holding `alliance.manage_territory` can claim or abandon territory.
  4. Alliance borders render as a distinct map overlay separable from personal territory.
  5. Losing the last alliance fortress releases the surrounding territory rather than stranding it.
**Plans**: 4 plans

Plans:
- [ ] 23-01: Alliance territory derivation and fortress structures
- [ ] 23-02: Territory bonuses, penalties and claim rules
- [ ] 23-03: Border computation and map overlay contract
- [ ] 23-04: Mobile alliance territory screen

### Phase 24: Rally & Reinforcements
**Goal**: Alliance members combine armies into a single rally attack or reinforce each other's cities.
**Depends on**: Phase 23
**Requirements**: REQ-07, REQ-03
**Milestone**: Multiplayer Alpha
**Success Criteria** (what must be TRUE):
  1. A rally has a join window; armies joining before it closes march as one combined force and late joiners are refused.
  2. Only a member with `alliance.start_rally` can start one.
  3. Rally losses are distributed back to each contributing player's records proportionally and reconcile exactly.
  4. Reinforcing troops defend the host city but remain owned by their sender and return home on recall.
  5. A rally whose leader disbands it returns every contributed army to its owner without loss.
**Plans**: 5 plans

Plans:
- [ ] 24-01: Rally entity, join window and army aggregation
- [ ] 24-02: Combined force resolution and proportional loss distribution
- [ ] 24-03: Reinforcement marches, garrison stacking and recall
- [ ] 24-04: Rally chat channel and coordination events
- [ ] 24-05: Mobile rally creation, join and tracking screens

### Phase 25: Chat & Social
**Goal**: Players communicate across scoped channels with rate limiting, blocking and an auditable trail.
**Depends on**: Phase 22
**Requirements**: REQ-07, REQ-14
**Milestone**: Multiplayer Alpha
**Success Criteria** (what must be TRUE):
  1. Messages exceeding the per-minute rate limit return RATE_LIMITED and are not delivered.
  2. A blocked sender's messages never reach the blocker, returning RECIPIENT_BLOCKED to the sender.
  3. A muted player receives CHAT_MUTED and their message is not persisted or broadcast.
  4. Every message is persisted with sender, channel, timestamp and world for moderation review.
  5. Chat history paginates without duplicates or gaps when new messages arrive during scrollback.
**Plans**: 4 plans

Plans:
- [ ] 25-01: Chat channel model, persistence and pagination
- [ ] 25-02: Realtime delivery and channel authorisation
- [ ] 25-03: Rate limiting, muting, blocking and anti-spam
- [ ] 25-04: Mobile chat UI with channel switching and history

### Phase 26: Diplomacy
**Goal**: Alliances hold formal relationships that change what their members are allowed to do to each other.
**Depends on**: Phase 24, Phase 25
**Requirements**: REQ-07
**Milestone**: Multiplayer Alpha
**Success Criteria** (what must be TRUE):
  1. Declaring war, peace or a NAP requires `alliance.diplomacy` and is refused otherwise.
  2. A NAP blocks attacks between the two alliances' members with INVALID_TARGET while it is active.
  3. Relationship changes take effect for every member immediately and are broadcast to both alliances.
  4. A proposed treaty requires acceptance by the other alliance; a unilateral proposal never binds.
  5. Every diplomatic change is written to both alliances' logs with actor, action and timestamp.
**Plans**: 4 plans

Plans:
- [ ] 26-01: Diplomatic relation model and state transitions
- [ ] 26-02: Treaty proposal, acceptance and expiry
- [ ] 26-03: Attack gating based on active relations
- [ ] 26-04: Mobile diplomacy screen and treaty flows

### Phase 27: Market & Trading
**Goal**: Resources move between players through trades that are taxed, rate-limited and impossible to duplicate.
**Depends on**: Phase 22, Phase 15
**Requirements**: REQ-02, REQ-09
**Milestone**: Multiplayer Alpha
**Success Criteria** (what must be TRUE):
  1. A completed trade debits the sender and credits the receiver in one transaction; total resources in the world are unchanged minus tax, verified by a conservation test.
  2. Two concurrent accepts of the same market order result in one success and one MARKET_ORDER_UNAVAILABLE.
  3. Trade volume per player per period is capped, returning TRADE_LIMIT_REACHED.
  4. Resources in transit exist in exactly one place — never simultaneously in the sender's city and the transport march.
  5. A cancelled or expired order returns escrowed resources to the owner exactly once.
**Plans**: 5 plans

Plans:
- [ ] 27-01: Market order model, escrow and expiry
- [ ] 27-02: NPC trade with configured rates
- [ ] 27-03: Player-to-player trade, transport marches and tax
- [ ] 27-04: Trade limits, anti-duplication and conservation tests
- [ ] 27-05: Mobile market screens and trade flow

### Phase 28: Quests & Achievements
**Goal**: Players always have a next objective, from the first minute of the tutorial to long-term achievements.
**Depends on**: Phase 27
**Requirements**: REQ-06, REQ-11
**Milestone**: Closed Alpha
**Success Criteria** (what must be TRUE):
  1. Quest definitions load from game data; adding a quest requires no code change, verified by adding one in a test.
  2. Quest progress is derived from domain events, not polled, and a quest completed while offline is credited on next login.
  3. Claiming a reward twice returns REWARD_ALREADY_CLAIMED and credits it once, verified by a concurrency test.
  4. Daily and weekly quests reset on the documented UTC schedule regardless of player timezone.
  5. The tutorial quest chain walks a brand-new player from first login to a first successful attack without a dead end.
**Plans**: 5 plans

Plans:
- [ ] 28-01: Quest schema, types and game data import
- [ ] 28-02: Event-driven progress tracking and offline crediting
- [ ] 28-03: Reward claiming with idempotency and the reward ledger
- [ ] 28-04: Reset scheduling for daily and weekly quests
- [ ] 28-05: Mobile quest list, detail and claim UI

### Phase 29: Nobility
**Goal**: A visible social ladder that rewards sustained play with status and concrete privileges.
**Depends on**: Phase 28
**Requirements**: REQ-06
**Milestone**: Closed Alpha
**Success Criteria** (what must be TRUE):
  1. Rank requirements load from game data and are evaluated by a pure function against power, honour, territory and achievements.
  2. Promotion is granted automatically when requirements are met and is never granted twice for the same rank.
  3. Rank privileges are observable in a recomputed value or an unlocked action, not cosmetic only.
  4. Losing the underlying qualification applies the documented demotion or grace rule rather than silently keeping the rank.
  5. The nobility screen shows the current rank, the next rank and the exact remaining requirements.
**Plans**: 4 plans

Plans:
- [ ] 29-01: Nobility rank schema, requirements and privileges
- [ ] 29-02: Automatic promotion evaluation and demotion rules
- [ ] 29-03: Honour accrual and decay
- [ ] 29-04: Mobile nobility screen

### Phase 30: Rankings
**Goal**: Players can see where they stand on leaderboards that are fast, paginated and auditable.
**Depends on**: Phase 29
**Requirements**: REQ-12
**Milestone**: Closed Alpha
**Success Criteria** (what must be TRUE):
  1. Power is stored as an auditable breakdown (building, technology, army, hero, territory), never a single opaque number.
  2. A leaderboard page returns in under 150ms at the 95th percentile against a seeded dataset of at least 50,000 players.
  3. Rankings are computed on a schedule and their staleness is reported in the response meta.
  4. A player can look up their own rank directly without paging through the whole board.
  5. Ties resolve by a documented, stable tiebreaker so ordering does not flicker between requests.
**Plans**: 4 plans

Plans:
- [ ] 30-01: Power breakdown model and recomputation triggers
- [ ] 30-02: Ranking snapshot jobs and materialised leaderboards
- [ ] 30-03: Ranking read API with pagination and self-lookup
- [ ] 30-04: Mobile ranking screens

### Phase 31: Events & LiveOps
**Goal**: Operators launch, modify and end timed events and toggle features entirely from the back office.
**Depends on**: Phase 30
**Requirements**: REQ-11
**Milestone**: Closed Alpha
**Success Criteria** (what must be TRUE):
  1. An event is created, scheduled, started and ended from the back office with no code deploy, demonstrated end to end.
  2. An inactive event's endpoints return EVENT_NOT_ACTIVE.
  3. A disabled feature flag returns FEATURE_DISABLED and the mobile client hides the corresponding entry point.
  4. Flag evaluation is cached with the documented TTL and a change propagates within that window.
  5. Every event and flag change writes an audit record naming the operator, the before value and the after value.
**Plans**: 5 plans

Plans:
- [ ] 31-01: Event schema, scheduling and lifecycle
- [ ] 31-02: Feature flag store, evaluation and caching
- [ ] 31-03: Back office event and flag management with audit
- [ ] 31-04: Event reward distribution
- [ ] 31-05: Mobile events screen and flag-driven entry points

### Phase 32: Seasons
**Goal**: A world runs competitive seasons with their own rules, rankings and rewards.
**Depends on**: Phase 31
**Requirements**: REQ-11, REQ-15
**Milestone**: Closed Alpha
**Success Criteria** (what must be TRUE):
  1. A season has a start, end, ruleset and reward table, all configured as data.
  2. Season rules override base rules only where declared, and the override is observable in a recomputed value.
  3. Season end distributes rewards exactly once per player, verified by a re-run test.
  4. What resets and what persists across a season is explicitly documented and enforced by tests.
  5. Season standings are queryable after the season closes.
**Plans**: 4 plans

Plans:
- [ ] 32-01: Season schema, ruleset and lifecycle
- [ ] 32-02: Rule override resolution and map modifiers
- [ ] 32-03: Season end, reward distribution and archival
- [ ] 32-04: Mobile season screen and standings

### Phase 33: Notifications
**Goal**: Players are told what matters — an attack incoming, a build finished — and can control exactly what reaches them.
**Depends on**: Phase 31
**Requirements**: REQ-08, REQ-13
**Milestone**: Closed Alpha
**Success Criteria** (what must be TRUE):
  1. Every notification category can be independently enabled or disabled by the player and the preference is honoured server-side.
  2. A disabled category produces no push, verified by a test asserting nothing was dispatched.
  3. Notification copy is localised from the translation catalogue, never hardcoded.
  4. Push tokens are registered per device session and removed when that session is revoked.
  5. A city under attack produces a notification before the march lands, not after.
**Plans**: 4 plans

Plans:
- [ ] 33-01: Notification categories, preferences and the dispatch pipeline
- [ ] 33-02: Push token registration tied to device sessions
- [ ] 33-03: In-app mail and notification centre
- [ ] 33-04: Mobile notification settings and permission flow

### Phase 34: Admin & Game Master Tools
**Goal**: Operators can inspect and correct any game state through tooling that records who did what, when and why.
**Depends on**: Phase 31
**Requirements**: REQ-14, REQ-12
**Milestone**: Closed Alpha
**Success Criteria** (what must be TRUE):
  1. Every economy-touching or destructive admin action writes an audit row with actor, action, target, before, after, reason, IP and timestamp.
  2. An admin action without a stated reason is refused, verified by a test.
  3. A non-staff account reaching the admin path receives a 403 and no panel markup, verified by a test.
  4. Back office resources exist for players, cities, armies, heroes, alliances, worlds, events and the ledger.
  5. Granting resources through the back office appears in the same player ledger as gameplay income.
**Plans**: 5 plans

Plans:
- [ ] 34-01: Staff roles, permissions and panel access control
- [ ] 34-02: Audit log model and the mandatory-reason interceptor
- [ ] 34-03: Filament resources for player, city, army and alliance domains
- [ ] 34-04: Economy admin, ledger inspection and simulation reports
- [ ] 34-05: Game master actions: grant, ban, move, terminate march, start event

### Phase 35: Moderation
**Goal**: Abuse can be reported, reviewed and acted on with a complete evidence trail.
**Depends on**: Phase 34, Phase 25
**Requirements**: REQ-14
**Milestone**: Closed Alpha
**Success Criteria** (what must be TRUE):
  1. A report captures the reporter, target, category, evidence snapshot and timestamp.
  2. A banned account's requests return ACCOUNT_BANNED and its realtime channels are disconnected immediately.
  3. Sanctions have explicit durations and expire automatically without manual intervention.
  4. Every moderation decision records the moderator and the rationale.
  5. A player can be muted from chat without being blocked from gameplay, and the distinction is enforced.
**Plans**: 4 plans

Plans:
- [ ] 35-01: Report model, evidence capture and the review queue
- [ ] 35-02: Sanction types, durations and automatic expiry
- [ ] 35-03: Ban enforcement across API and realtime
- [ ] 35-04: Back office moderation queue and decision logging

### Phase 36: Analytics
**Goal**: Product events flow to analytics without any gameplay path depending on the analytics pipeline.
**Depends on**: Phase 33
**Requirements**: REQ-12
**Milestone**: Beta
**Success Criteria** (what must be TRUE):
  1. Analytics events are emitted from domain event listeners on the analytics queue; no domain transaction blocks on analytics, verified by an architecture test.
  2. An analytics pipeline outage does not fail or slow any gameplay request, verified by a test with the dispatcher throwing.
  3. Every event in the documented catalogue carries a stable name, a schema version and a player and world identifier.
  4. No analytics payload contains a password, token, email or IP address, verified by a test.
  5. Events are queryable in the back office for the funnel from player_created to first battle.
**Plans**: 4 plans

Plans:
- [ ] 36-01: Event catalogue, schema versioning and naming
- [ ] 36-02: Decoupled listener dispatch on the analytics queue
- [ ] 36-03: Payload sanitisation and PII exclusion tests
- [ ] 36-04: Back office funnel and retention reporting

### Phase 37: Security & Anti-Cheat Hardening
**Goal**: Every threat in the threat model has either a control or a documented, accepted risk.
**Depends on**: Phase 36
**Requirements**: REQ-01, REQ-09
**Milestone**: Beta
**Success Criteria** (what must be TRUE):
  1. Each threat in docs/security/threat-model.md maps to a named test or a written risk acceptance, verified by a coverage check.
  2. A test suite attempts resource duplication, replay, IDOR, mass assignment and clock manipulation and every attempt fails safely.
  3. Rate limits are enforced per endpoint class and a bypass attempt via header spoofing is rejected.
  4. Anomalous economic gain triggers a flag visible in the back office rather than a silent accumulation.
  5. No debug endpoint, debug menu or seeded credential is reachable when APP_ENV is production, verified by a test.
**Plans**: 5 plans

Plans:
- [ ] 37-01: Exploit test suite: duplication, replay, IDOR, mass assignment
- [ ] 37-02: Rate limit enforcement and bypass resistance
- [ ] 37-03: Anomaly detection and economy alerting
- [ ] 37-04: Production hardening checks and secret scanning
- [ ] 37-05: Threat model coverage audit

### Phase 38: Performance Optimization
**Goal**: The game meets its stated performance budget on both server and device, measured rather than asserted.
**Depends on**: Phase 37
**Requirements**: REQ-08, REQ-04
**Milestone**: Beta
**Success Criteria** (what must be TRUE):
  1. No API endpoint issues an N+1 query, verified by a test asserting query counts on the heaviest endpoints.
  2. The world map viewport, city state and ranking endpoints meet their documented 95th-percentile budgets against a seeded large dataset.
  3. The mobile app cold-starts to an interactive city screen within the documented budget on a mid-range device.
  4. Map interaction holds 60 FPS with the documented worst-case entity count.
  5. Every slow query above the threshold is logged with its SQL and duration.
**Plans**: 4 plans

Plans:
- [ ] 38-01: Query profiling, index tuning and N+1 elimination
- [ ] 38-02: Cache strategy for read-heavy endpoints
- [ ] 38-03: Mobile render profiling and re-render elimination
- [ ] 38-04: Asset loading, bundle size and startup budget

### Phase 39: Load Testing
**Goal**: Capacity claims are backed by reproducible measurements rather than optimism.
**Depends on**: Phase 38
**Requirements**: REQ-15, REQ-12
**Milestone**: Beta
**Success Criteria** (what must be TRUE):
  1. A documented, re-runnable load scenario exists for 1k, 5k, 10k, 50k and 100k concurrent users.
  2. Each run reports throughput, latency percentiles, error rate and queue depth.
  3. A rally involving the documented maximum participants resolves without timeout or deadlock.
  4. Results are recorded with the exact commit, dataset and infrastructure used, so a later run is comparable.
  5. Any tier the system fails is documented as a known limit, not omitted.
**Plans**: 4 plans

Plans:
- [ ] 39-01: Load test harness and scenario definitions
- [ ] 39-02: Realistic traffic modelling and seeded datasets
- [ ] 39-03: Websocket and queue saturation scenarios
- [ ] 39-04: Benchmark methodology documentation and results log

### Phase 40: Offline & Connectivity Resilience
**Goal**: A player on a bad connection sees honest state and never performs a server-authoritative action that silently fails.
**Depends on**: Phase 38
**Requirements**: REQ-08, REQ-01
**Milestone**: Beta
**Success Criteria** (what must be TRUE):
  1. The client displays Offline, Reconnecting, Synchronizing and Connected states and each is reachable in a test.
  2. No server-authoritative action is presented as confirmed while offline; it is queued or refused explicitly.
  3. Websocket reconnection uses exponential backoff with jitter and recovers without a manual restart.
  4. Realtime events carry a sequence number and a detected gap triggers a state resync rather than silent divergence.
  5. On reconnect, server state overwrites client state on every conflict, verified by a test.
**Plans**: 5 plans

Plans:
- [ ] 40-01: Connectivity state machine and status banner
- [ ] 40-02: Cached read strategy and staleness display
- [ ] 40-03: Websocket reconnection, backoff and resubscription
- [ ] 40-04: Event sequencing, gap detection and resync
- [ ] 40-05: Conflict resolution favouring server authority

### Phase 41: Accessibility
**Goal**: The game is playable by people who need larger text, higher contrast, less motion or a screen reader.
**Depends on**: Phase 40
**Requirements**: REQ-08
**Milestone**: Beta
**Success Criteria** (what must be TRUE):
  1. Every interactive element meets the minimum touch target and has an accessibility label, verified by an automated sweep test.
  2. Text and essential UI meet WCAG AA contrast in both themes, verified by a token contrast test.
  3. Enabling reduce-motion replaces every non-essential animation with an instant transition.
  4. No state is communicated by colour alone; each colour-coded state also carries an icon or text.
  5. The app remains usable at the largest supported system font scale without clipped or unreachable controls.
**Plans**: 4 plans

Plans:
- [ ] 41-01: Touch target and accessibility label audit with automated tests
- [ ] 41-02: Contrast validation across both themes
- [ ] 41-03: Reduced motion support across animations
- [ ] 41-04: Text scaling and layout resilience

### Phase 42: Localization
**Goal**: Every player-visible string comes from a translation catalogue in the player's language.
**Depends on**: Phase 41
**Requirements**: REQ-13
**Milestone**: Beta
**Success Criteria** (what must be TRUE):
  1. No user-facing literal string remains in a component or a controller, verified by a lint rule and an architecture test.
  2. A missing translation key fails CI rather than silently rendering the key at runtime.
  3. All three locales (pt-BR, en, es) are complete for shipped surfaces, verified by a completeness check.
  4. Numbers, dates and durations format per locale, not per hardcoded pattern.
  5. Switching language takes effect without an app restart.
**Plans**: 5 plans

Plans:
- [ ] 42-01: Translation catalogue structure and loading
- [ ] 42-02: String extraction and the no-hardcoded-string lint rule
- [ ] 42-03: Completeness validation in CI
- [ ] 42-04: Locale-aware number, date and duration formatting
- [ ] 42-05: Server-side localisation for notifications and mail

### Phase 43: Audio & Haptics
**Goal**: Actions feel physical through layered sound and haptics the player can turn off.
**Depends on**: Phase 42
**Requirements**: REQ-08
**Milestone**: Beta
**Success Criteria** (what must be TRUE):
  1. Every documented key action produces the specified combination of sound, haptic and visual feedback.
  2. Music and effects volumes are independently controllable and persist across restarts.
  3. Audio respects the device silent switch and ducks correctly for other apps.
  4. Haptics are disabled entirely when the player turns them off or the device does not support them.
  5. No audio asset blocks the first interactive frame on cold start.
**Plans**: 4 plans

Plans:
- [ ] 43-01: Audio engine, asset loading and volume channels
- [ ] 43-02: Contextual sound mapping for key actions
- [ ] 43-03: Haptic feedback patterns and preferences
- [ ] 43-04: Settings UI for audio and haptics

### Phase 44: Visual Polish
**Goal**: The game looks and feels like a commercial product rather than a themed CRUD app.
**Depends on**: Phase 43
**Requirements**: REQ-08
**Milestone**: Beta
**Success Criteria** (what must be TRUE):
  1. The city scene is alive: ambient animation, smoke, banners and lighting all present without dropping below the frame budget.
  2. No screen uses a bare generic spinner; every loading state is a skeleton, a progress indicator or cached content.
  3. Every list and panel has a designed empty state and a designed error state with a meaningful retry.
  4. Screen transitions are consistent and interruptible, never blocking input.
  5. Particle and animation density automatically reduces on low-end devices to hold the frame budget.
**Plans**: 4 plans

Plans:
- [ ] 44-01: City ambience, lighting and ambient animation
- [ ] 44-02: Particle systems with device-tier scaling
- [ ] 44-03: Screen transitions and shared element motion
- [ ] 44-04: Skeleton, empty and error states across all screens

### Phase 45: Tutorial & FTUE Polish
**Goal**: A brand-new player understands the game and reaches their first meaningful win without external help.
**Depends on**: Phase 44, Phase 28
**Requirements**: REQ-08
**Milestone**: Beta
**Success Criteria** (what must be TRUE):
  1. A new player completes the guided sequence to a first successful attack with no dead end, verified end to end.
  2. The tutorial is resumable: quitting mid-sequence and returning continues from the same step.
  3. Advanced UI is progressively disclosed rather than presented all at once on first launch.
  4. The tutorial can be skipped and skipping does not leave unreachable state or unclaimed rewards.
  5. Every tutorial step is localised and driven by data, not hardcoded sequencing.
**Plans**: 4 plans

Plans:
- [ ] 45-01: Tutorial step engine with resumable state
- [ ] 45-02: Guided overlays, spotlights and contextual hints
- [ ] 45-03: Progressive disclosure rules per screen
- [ ] 45-04: Skip path and completion reconciliation

### Phase 46: Economy Balance Pass
**Goal**: The economy is tuned against simulation rather than intuition, with sinks that actually absorb supply.
**Depends on**: Phase 45
**Requirements**: REQ-06, REQ-02
**Milestone**: Beta
**Success Criteria** (what must be TRUE):
  1. A simulator projects resource curves for casual, average and heavy play profiles over the documented horizon.
  2. Every faucet has a matching documented sink and net inflation stays inside the documented band in simulation.
  3. Progression pacing hits the documented milestones for each play profile.
  4. A balance change is applied by editing versioned game data and bumping its version, with no code change.
  5. Rebalancing does not alter the outcome of any previously recorded battle replay, verified by a regression test.
**Plans**: 4 plans

Plans:
- [ ] 46-01: Economy simulator and play profile modelling
- [ ] 46-02: Faucet and sink audit with inflation bands
- [ ] 46-03: Progression pacing tuning
- [ ] 46-04: Balance data versioning and replay regression tests

### Phase 47: Combat Balance Pass
**Goal**: No single composition dominates, and the counter system rewards thought rather than raw numbers.
**Depends on**: Phase 46
**Requirements**: REQ-03, REQ-06
**Milestone**: Beta
**Success Criteria** (what must be TRUE):
  1. A batch simulator runs the documented matchup matrix and reports win rates per composition.
  2. No composition exceeds the documented win-rate ceiling across the matrix.
  3. Hero contribution stays inside the documented influence band — decisive but not sole determinant.
  4. Siege units meaningfully outperform other units against fortifications and underperform in the open field.
  5. Every balance change is data-only and old replays still reproduce their original results.
**Plans**: 5 plans

Plans:
- [ ] 47-01: Batch matchup simulator and win-rate reporting
- [ ] 47-02: Counter multiplier tuning
- [ ] 47-03: Hero and technology influence bands
- [ ] 47-04: Siege and fortification balance
- [ ] 47-05: Replay regression suite across versions

### Phase 48: Closed Alpha
**Goal**: A small invited group plays a real server and their problems are captured and triaged.
**Depends on**: Phase 47
**Requirements**: REQ-12, REQ-14
**Milestone**: Closed Alpha Release
**Success Criteria** (what must be TRUE):
  1. An invite-only build is distributed to testers through the documented channel and installs cleanly.
  2. Crash reporting captures a symbolicated stack trace with the build and commit identifiers.
  3. In-app feedback reaches a triage queue with device, build and player context attached.
  4. A staging world runs continuously for the documented alpha window without unplanned data loss.
  5. Every critical and high issue found is logged with a reproduction and an owning phase.
**Plans**: 4 plans

Plans:
- [ ] 48-01: Invite distribution and build gating
- [ ] 48-02: Crash reporting and symbolication
- [ ] 48-03: In-app feedback capture and triage queue
- [ ] 48-04: Alpha stability monitoring and issue log

### Phase 49: Beta
**Goal**: A larger population plays continuously while the team watches the metrics that matter and can ship a fix quickly.
**Depends on**: Phase 48
**Requirements**: REQ-12, REQ-15
**Milestone**: Beta Release
**Success Criteria** (what must be TRUE):
  1. The beta world sustains the documented concurrent population without degradation, measured not assumed.
  2. Dashboards cover request latency, error rate, queue depth, websocket connections and economy inflation.
  3. Alerts fire on the documented thresholds and reach an on-call human.
  4. A hotfix can be deployed and verified within the documented window, demonstrated at least once.
  5. Retention, funnel and economy metrics are reported for the beta cohort.
**Plans**: 4 plans

Plans:
- [ ] 49-01: Beta infrastructure scaling and capacity plan
- [ ] 49-02: Operational dashboards and alerting
- [ ] 49-03: Hotfix pipeline and rollback rehearsal
- [ ] 49-04: Beta cohort metrics reporting

### Phase 50: Production Infrastructure
**Goal**: Production infrastructure is reproducible from code and its recovery procedures have actually been tested.
**Depends on**: Phase 49
**Requirements**: REQ-15, REQ-12
**Milestone**: Release Candidate
**Success Criteria** (what must be TRUE):
  1. The production environment is provisioned from Terraform and a plan against live state shows no drift.
  2. Automated backups run on the documented schedule with point-in-time recovery enabled.
  3. A restore drill has been performed and its measured RTO and RPO are recorded against the targets.
  4. Secrets live in a managed secret store; the repository contains none, verified by a scanner in CI.
  5. TLS, CORS, security headers and network boundaries match the documented baseline, verified by an external scan.
**Plans**: 5 plans

Plans:
- [ ] 50-01: Terraform modules for the production environment
- [ ] 50-02: Secret management and rotation
- [ ] 50-03: Backup, PITR and a rehearsed restore drill
- [ ] 50-04: Disaster recovery runbook with RPO and RTO validation
- [ ] 50-05: Network, TLS and security header baseline

### Phase 51: Store Release Pipeline
**Goal**: A signed build reaches both stores through a repeatable pipeline with a safe rollback path.
**Depends on**: Phase 50
**Requirements**: REQ-08
**Milestone**: Release Candidate
**Success Criteria** (what must be TRUE):
  1. A production build is produced and submitted to both stores from the pipeline without manual file handling.
  2. Signing credentials are managed by the build service and never stored in the repository.
  3. Store metadata, screenshots and privacy declarations are complete for all three locales.
  4. Over-the-air update policy is documented and enforced: what may ship OTA and what requires a store review.
  5. A staged rollout can be halted and rolled back, demonstrated in a rehearsal.
**Plans**: 4 plans

Plans:
- [ ] 51-01: EAS build profiles and signing configuration
- [ ] 51-02: Automated submission and staged rollout
- [ ] 51-03: Store metadata and privacy declarations per locale
- [ ] 51-04: OTA update policy and rollback rehearsal

### Phase 52: Launch Readiness
**Goal**: Every launch prerequisite is verified and signed off, with named owners for the first days.
**Depends on**: Phase 51
**Requirements**: REQ-12, REQ-14
**Milestone**: Release Candidate
**Success Criteria** (what must be TRUE):
  1. A go/no-go checklist exists with every item either verified or explicitly waived by a named owner.
  2. Incident runbooks cover the documented top failure scenarios with named roles and escalation paths.
  3. Account deletion, data export and consent flows work end to end and meet the documented retention policy.
  4. Support tooling lets an operator resolve the documented common player issues without a database console.
  5. A full restore from backup into a clean environment has been performed within the target RTO.
**Plans**: 4 plans

Plans:
- [ ] 52-01: Go/no-go checklist and sign-off
- [ ] 52-02: Incident runbooks and on-call rotation
- [ ] 52-03: Privacy compliance: deletion, export, consent, retention
- [ ] 52-04: Support tooling and common-issue playbooks

### Phase 53: Global Launch
**Goal**: The game is publicly available and the first worlds open under active monitoring.
**Depends on**: Phase 52
**Requirements**: REQ-15, REQ-12
**Milestone**: Launch
**Success Criteria** (what must be TRUE):
  1. The app is publicly downloadable in the target regions and a fresh install reaches the city screen successfully.
  2. World opening follows the documented cadence and a filling world triggers the next one automatically.
  3. Launch-window monitoring is staffed and every alert has a documented response.
  4. The first live incident, if any, is handled through the runbook and written up as a postmortem.
  5. Day-one retention and crash-free session rate are measured and reported against targets.
**Plans**: 4 plans

Plans:
- [ ] 53-01: Public release and regional availability
- [ ] 53-02: World opening cadence and capacity automation
- [ ] 53-03: Launch-window monitoring and on-call staffing
- [ ] 53-04: Launch metrics reporting and postmortem process

### Phase 54: Post-launch LiveOps
**Goal**: The game runs as a live service with a repeatable rhythm for content, balance and player health.
**Depends on**: Phase 53
**Requirements**: REQ-11, REQ-12
**Milestone**: LiveOps
**Success Criteria** (what must be TRUE):
  1. A recurring event calendar is scheduled and executes without engineering involvement.
  2. A balance change reaches production through the data pipeline within the documented cadence and without a code deploy.
  3. Player health metrics — retention, churn, economy inflation, match fairness — are reported on a fixed schedule.
  4. New content (buildings, units, heroes, technologies) ships as validated data rather than code, demonstrated once end to end.
  5. A rollback of a bad content release is demonstrated and completes within the documented window.
**Plans**: 4 plans

Plans:
- [ ] 54-01: Recurring event calendar and automation
- [ ] 54-02: Balance change cadence and content release pipeline
- [ ] 54-03: Player health reporting
- [ ] 54-04: Content rollback procedure and rehearsal

## Progress

**Execution Order:**
Phases execute in numeric order: 00 → 01 → 02 → ... → 53 → 54

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 00. Repository Bootstrap | 4/4 | Complete | 2026-08-24 |
| 01. Engineering Foundation | 4/4 | Complete    | 2026-08-25 |
| 02. Design System & Mobile Shell | 1/4 | Complete    | 2026-08-26 |
| 03. Identity & Authentication | 3/4 | Complete    | 2026-08-28 |
| 04. Player Profile & Onboarding | 0/4 | Complete    | 2026-08-28 |
| 05. World Architecture | 0/4 | Complete    | 2026-08-28 |
| 06. World Map Rendering | 1/4 | In Progress|  |
| 07. City Foundation | 0/4 | Not started | - |
| 08. Resources & Economy | 0/5 | Not started | - |
| 09. Buildings & Construction | 0/5 | Not started | - |
| 10. Technology & Research | 0/4 | Not started | - |
| 11. Unit System | 0/4 | Not started | - |
| 12. Training System | 0/4 | Not started | - |
| 13. Heroes | 0/5 | Not started | - |
| 14. Army Composition | 0/4 | Not started | - |
| 15. March System | 0/5 | Not started | - |
| 16. PvE World Encounters | 0/4 | Not started | - |
| 17. Combat Engine V1 | 0/5 | Not started | - |
| 18. Battle Visualization | 0/4 | Not started | - |
| 19. PvP | 0/5 | Not started | - |
| 20. Siege & City Capture | 0/5 | Not started | - |
| 21. Territory System | 0/4 | Not started | - |
| 22. Alliances | 0/5 | Not started | - |
| 23. Alliance Territory | 0/4 | Not started | - |
| 24. Rally & Reinforcements | 0/5 | Not started | - |
| 25. Chat & Social | 0/4 | Not started | - |
| 26. Diplomacy | 0/4 | Not started | - |
| 27. Market & Trading | 0/5 | Not started | - |
| 28. Quests & Achievements | 0/5 | Not started | - |
| 29. Nobility | 0/4 | Not started | - |
| 30. Rankings | 0/4 | Not started | - |
| 31. Events & LiveOps | 0/5 | Not started | - |
| 32. Seasons | 0/4 | Not started | - |
| 33. Notifications | 0/4 | Not started | - |
| 34. Admin & Game Master Tools | 0/5 | Not started | - |
| 35. Moderation | 0/4 | Not started | - |
| 36. Analytics | 0/4 | Not started | - |
| 37. Security & Anti-Cheat Hardening | 0/5 | Not started | - |
| 38. Performance Optimization | 0/4 | Not started | - |
| 39. Load Testing | 0/4 | Not started | - |
| 40. Offline & Connectivity Resilience | 0/5 | Not started | - |
| 41. Accessibility | 0/4 | Not started | - |
| 42. Localization | 0/5 | Not started | - |
| 43. Audio & Haptics | 0/4 | Not started | - |
| 44. Visual Polish | 0/4 | Not started | - |
| 45. Tutorial & FTUE Polish | 0/4 | Not started | - |
| 46. Economy Balance Pass | 0/4 | Not started | - |
| 47. Combat Balance Pass | 0/5 | Not started | - |
| 48. Closed Alpha | 0/4 | Not started | - |
| 49. Beta | 0/4 | Not started | - |
| 50. Production Infrastructure | 0/5 | Not started | - |
| 51. Store Release Pipeline | 0/4 | Not started | - |
| 52. Launch Readiness | 0/4 | Not started | - |
| 53. Global Launch | 0/4 | Not started | - |
| 54. Post-launch LiveOps | 0/4 | Not started | - |

## Milestones

| Milestone | Phases | Delivers |
|-----------|--------|----------|
| Foundation | 00-02 | A repository that builds, tests and documents itself |
| Playable Prototype | 03-12 | One player grows a city in a real world |
| Internal Alpha | 13-18 | Heroes, armies, movement and deterministic combat |
| Multiplayer Alpha | 19-27 | Conquest, alliances, diplomacy and trade |
| Closed Alpha | 28-35 | Retention systems and full operator tooling |
| Beta | 36-47 | Hardened, measured, balanced and accessible |
| Closed Alpha Release | 48 | Invited players on a real server |
| Beta Release | 49 | Open population under live monitoring |
| Release Candidate | 50-52 | Reproducible production and a tested recovery path |
| Launch | 53 | Publicly available |
| LiveOps | 54 | Running as a live service |
| Google Play Games & Gamification | 55-67 | Gamification engine and Google Play Games Sidekick integrated |

### Phase 55: Google Play Sidekick: Fase 0 — Discovery

**Goal:** Mapear o projeto inteiro (arquitetura, gameplay, backend, Android) e gerar a auditoria de compatibilidade inicial e riscos para o Google Play Games Sidekick.
**Requirements**: Play Games Sidekick, Gamification Engine
**Depends on:** Phase 54
**Milestone**: Google Play Games & Gamification
**Success Criteria**:
  1. (Ver objetivos definidos em CONTEXT.md)
**Plans:** 1/4 plans executed

Plans:
- [ ] TBD (run /gsd-plan-phase 55 to break down)

### Phase 56: Google Play Sidekick: Fase 1 — Fundação

**Goal:** Estabelecer a infraestrutura básica (Domain Events, Gamification Service) e Feature Flags sem espalhar dependências do Google Play pelo código.
**Requirements**: Play Games Sidekick, Gamification Engine
**Depends on:** Phase 55
**Milestone**: Google Play Games & Gamification
**Success Criteria**:
  1. (Ver objetivos definidos em CONTEXT.md)
**Plans:** 0 plans

Plans:
- [ ] TBD (run /gsd-plan-phase 56 to break down)

### Phase 57: Google Play Sidekick: Fase 2 — Play Games Services

**Goal:** Implementar robustamente o PGS v2 com fallback, tratamento de lifecycle, autenticação e Recall API.
**Requirements**: Play Games Sidekick, Gamification Engine
**Depends on:** Phase 56
**Milestone**: Google Play Games & Gamification
**Success Criteria**:
  1. (Ver objetivos definidos em CONTEXT.md)
**Plans:** 0 plans

Plans:
- [ ] TBD (run /gsd-plan-phase 57 to break down)

### Phase 58: Google Play Sidekick: Fase 3 — Achievements

**Goal:** Criar uma Achievement Engine conectada ao Google Play com pelo menos 40 conquistas, incluindo 4 alcançáveis na primeira hora.
**Requirements**: Play Games Sidekick, Gamification Engine
**Depends on:** Phase 57
**Milestone**: Google Play Games & Gamification
**Success Criteria**:
  1. (Ver objetivos definidos em CONTEXT.md)
**Plans:** 0 plans

Plans:
- [ ] TBD (run /gsd-plan-phase 58 to break down)

### Phase 59: Google Play Sidekick: Fase 4 — Game Stats

**Goal:** Instrumentar Game Stats avançados (Progress e Repetitive) gerando Schemas e CSVs compatíveis com Play Console.
**Requirements**: Play Games Sidekick, Gamification Engine
**Depends on:** Phase 58
**Milestone**: Google Play Games & Gamification
**Success Criteria**:
  1. (Ver objetivos definidos em CONTEXT.md)
**Plans:** 0 plans

Plans:
- [ ] TBD (run /gsd-plan-phase 59 to break down)

### Phase 60: Google Play Sidekick: Fase 5 — Gamificação avançada

**Goal:** Criar XP centralizado, Levels, Quests, Streaks (loops diários e semanais), Collections e Rewards seguros.
**Requirements**: Play Games Sidekick, Gamification Engine
**Depends on:** Phase 59
**Milestone**: Google Play Games & Gamification
**Success Criteria**:
  1. (Ver objetivos definidos em CONTEXT.md)
**Plans:** 0 plans

Plans:
- [ ] TBD (run /gsd-plan-phase 60 to break down)

### Phase 61: Google Play Sidekick: Fase 6 — Social

**Goal:** Integrar Leaderboards, Social Challenges e Progressão competitiva caso aplicável ao jogo.
**Requirements**: Play Games Sidekick, Gamification Engine
**Depends on:** Phase 60
**Milestone**: Google Play Games & Gamification
**Success Criteria**:
  1. (Ver objetivos definidos em CONTEXT.md)
**Plans:** 0 plans

Plans:
- [ ] TBD (run /gsd-plan-phase 61 to break down)

### Phase 62: Google Play Sidekick: Fase 7 — LiveOps

**Goal:** Permitir configuração Server-Driven (Seasons, Daily/Weekly Quests) para operar o jogo sem depender de atualizações de app.
**Requirements**: Play Games Sidekick, Gamification Engine
**Depends on:** Phase 61
**Milestone**: Google Play Games & Gamification
**Success Criteria**:
  1. (Ver objetivos definidos em CONTEXT.md)
**Plans:** 0 plans

Plans:
- [ ] TBD (run /gsd-plan-phase 62 to break down)

### Phase 63: Google Play Sidekick: Fase 8 — Sidekick

**Goal:** Validar UI, ciclo de vida e overlay do Play Games Sidekick em todos os fluxos e imersões sem quebrar UX/controles.
**Requirements**: Play Games Sidekick, Gamification Engine
**Depends on:** Phase 62
**Milestone**: Google Play Games & Gamification
**Success Criteria**:
  1. (Ver objetivos definidos em CONTEXT.md)
**Plans:** 0 plans

Plans:
- [ ] TBD (run /gsd-plan-phase 63 to break down)

### Phase 64: Google Play Sidekick: Fase 9 — Segurança

**Goal:** Auditar e fechar vulnerabilidades de economy (reward abuse, replay, spoofing, cheating).
**Requirements**: Play Games Sidekick, Gamification Engine
**Depends on:** Phase 63
**Milestone**: Google Play Games & Gamification
**Success Criteria**:
  1. (Ver objetivos definidos em CONTEXT.md)
**Plans:** 0 plans

Plans:
- [ ] TBD (run /gsd-plan-phase 64 to break down)

### Phase 65: Google Play Sidekick: Fase 10 — QA

**Goal:** Construir testes end-to-end de lifecycle (offline, sync, reconexão, reinstalação, dupla autenticação).
**Requirements**: Play Games Sidekick, Gamification Engine
**Depends on:** Phase 64
**Milestone**: Google Play Games & Gamification
**Success Criteria**:
  1. (Ver objetivos definidos em CONTEXT.md)
**Plans:** 0 plans

Plans:
- [ ] TBD (run /gsd-plan-phase 65 to break down)

### Phase 66: Google Play Sidekick: Fase 11 — Performance

**Goal:** Monitorar e documentar métricas de Google Play Games Level Up (FPS, ANR, Battery, Memória).
**Requirements**: Play Games Sidekick, Gamification Engine
**Depends on:** Phase 65
**Milestone**: Google Play Games & Gamification
**Success Criteria**:
  1. (Ver objetivos definidos em CONTEXT.md)
**Plans:** 0 plans

Plans:
- [ ] TBD (run /gsd-plan-phase 66 to break down)

### Phase 67: Google Play Sidekick: Fase 12 — Release

**Goal:** Orquestrar lançamento e rollout no Play Console (Internal -> Closed -> Production) e checklist de Rollback.
**Requirements**: Play Games Sidekick, Gamification Engine
**Depends on:** Phase 66
**Milestone**: Google Play Games & Gamification
**Success Criteria**:
  1. (Ver objetivos definidos em CONTEXT.md)
**Plans:** 0 plans

Plans:
- [ ] TBD (run /gsd-plan-phase 67 to break down)
