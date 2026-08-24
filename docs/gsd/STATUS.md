# Status

**Authoritative source:** `.planning/STATE.md` and `.planning/ROADMAP.md`.
This file is the human-readable mirror. If they disagree, `.planning/` wins.

Regenerate the live view with:

```bash
node ~/.claude/get-shit-done/bin/gsd-tools.cjs roadmap analyze
```

---

## Now

| | |
|---|---|
| **Milestone** | v0.1 — Foundation to Launch |
| **Complete** | 1 of 55 phases |
| **Next executable** | **Phase 01 — Engineering Foundation** |
| **Blocked** | Nothing |

```
Progress  [░░░░░░░░░░]  2%
```

---

## Legend

| Status | Meaning |
|--------|---------|
| **DONE** | Every success criterion verified; summary written |
| **READY** | Dependencies complete; can start now |
| **BLOCKED BY n** | Waiting on phase n |

Every phase already has a pre-written CONTEXT.md, so none is ever blocked on
discussion — only on its dependencies.

---

## Phases

| # | Phase | Status |
|---|-------|--------|
| 00 | Repository Bootstrap | **DONE** |
| 01 | Engineering Foundation | **READY** |
| 02 | Design System & Mobile Shell | READY (needs only 00) |
| 03 | Identity & Authentication | BLOCKED BY 01 |
| 04 | Player Profile & Onboarding | BLOCKED BY 03 |
| 05 | World Architecture | BLOCKED BY 01 |
| 06 | World Map Rendering | BLOCKED BY 05, 02 |
| 07 | City Foundation | BLOCKED BY 04, 05 |
| 08 | Resources & Economy | BLOCKED BY 07 |
| 09 | Buildings & Construction | BLOCKED BY 08, 06 |
| 10 | Technology & Research | BLOCKED BY 09 |
| 11 | Unit System | BLOCKED BY 10 |
| 12 | Training System | BLOCKED BY 11, 09 |
| 13 | Heroes | BLOCKED BY 12 |
| 14 | Army Composition | BLOCKED BY 13 |
| 15 | March System | BLOCKED BY 14, 06 |
| 16 | PvE World Encounters | BLOCKED BY 15 |
| 17 | Combat Engine V1 | BLOCKED BY 16, 11 |
| 18 | Battle Visualization | BLOCKED BY 17, 02 |
| 19 | PvP | BLOCKED BY 18 |
| 20 | Siege & City Capture | BLOCKED BY 19 |
| 21 | Territory System | BLOCKED BY 20 |
| 22 | Alliances | BLOCKED BY 19 |
| 23 | Alliance Territory | BLOCKED BY 22, 21 |
| 24 | Rally & Reinforcements | BLOCKED BY 23 |
| 25 | Chat & Social | BLOCKED BY 22 |
| 26 | Diplomacy | BLOCKED BY 24, 25 |
| 27 | Market & Trading | BLOCKED BY 22, 15 |
| 28 | Quests & Achievements | BLOCKED BY 27 |
| 29 | Nobility | BLOCKED BY 28 |
| 30 | Rankings | BLOCKED BY 29 |
| 31 | Events & LiveOps | BLOCKED BY 30 |
| 32 | Seasons | BLOCKED BY 31 |
| 33 | Notifications | BLOCKED BY 31 |
| 34 | Admin & Game Master Tools | BLOCKED BY 31 |
| 35 | Moderation | BLOCKED BY 34, 25 |
| 36 | Analytics | BLOCKED BY 33 |
| 37 | Security & Anti-Cheat Hardening | BLOCKED BY 36 |
| 38 | Performance Optimization | BLOCKED BY 37 |
| 39 | Load Testing | BLOCKED BY 38 |
| 40 | Offline & Connectivity Resilience | BLOCKED BY 38 |
| 41 | Accessibility | BLOCKED BY 40 |
| 42 | Localization | BLOCKED BY 41 |
| 43 | Audio & Haptics | BLOCKED BY 42 |
| 44 | Visual Polish | BLOCKED BY 43 |
| 45 | Tutorial & FTUE Polish | BLOCKED BY 44, 28 |
| 46 | Economy Balance Pass | BLOCKED BY 45 |
| 47 | Combat Balance Pass | BLOCKED BY 46 |
| 48 | Closed Alpha | BLOCKED BY 47 |
| 49 | Beta | BLOCKED BY 48 |
| 50 | Production Infrastructure | BLOCKED BY 49 |
| 51 | Store Release Pipeline | BLOCKED BY 50 |
| 52 | Launch Readiness | BLOCKED BY 51 |
| 53 | Global Launch | BLOCKED BY 52 |
| 54 | Post-launch LiveOps | BLOCKED BY 53 |

---

## Phase 00 verification

Every gate was executed, not assumed:

| Gate | Result |
|------|--------|
| `pest` | 33 passed, 300 assertions |
| `phpstan analyse` | 0 errors (level 8 + strict rules) |
| `pint --test` | passed |
| `composer validate --strict` | passed |
| `artisan migrate` | passed (SQLite) |
| `npm run typecheck` | passed |
| `npm run lint` | passed |
| `npm test` | 7 passed |
| `npm run contracts:check` | passed |
| `npm run gamedata:validate` | passed |
| `docker compose config` | valid |

**Not verified on this machine:** migrations against PostgreSQL + PostGIS. The
host lacks `pdo_pgsql` and the Docker daemon was stopped. Phase 01 makes Docker
canonical and CI runs the real thing.

---

## Open debt

Tracked in `.planning/codebase/CONCERNS.md`: DEBT-001 through DEBT-007.
