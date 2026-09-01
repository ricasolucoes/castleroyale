# Master plan

## What this is

Castle Royale is planned end to end **before** execution begins. All 55
phases carry a goal, dependencies, observable success criteria and pre-locked
implementation decisions.

An agent executing a phase should find nearly everything already decided. It
reads, it builds, it validates — it does not re-plan.

## Where the plan lives

| Path | Role |
|------|------|
| `.planning/ROADMAP.md` | **Authoritative.** All 55 phases, parsed by the tooling |
| `.planning/PROJECT.md` | Product context, requirements, constraints |
| `.planning/STATE.md` | Live position |
| `.planning/phases/NN-*/NN-CONTEXT.md` | Locked decisions per phase |
| `.planning/codebase/` | Stack, architecture, conventions, testing, concerns |
| `docs/gsd/` | This narrative layer |
| `docs/adr/` | The 17 decisions everything rests on |

The original brief asked for `docs/gsd/`. The installed GSD tooling reads
`.planning/`, so the machine-readable plan lives there — see
[`DECISIONS.md`](DECISIONS.md). Keep both consistent; `.planning/` wins.

## Shape of the plan

**55 phases**, one milestone (v0.1), grouped into nine stages:

| Stage | Phases | Delivers |
|-------|--------|----------|
| Foundation | 00–02 | A repository that builds, tests and documents itself |
| Playable Prototype | 03–12 | One player grows a city in a real world |
| Internal Alpha | 13–18 | Heroes, armies, movement, deterministic combat |
| Multiplayer Alpha | 19–27 | Conquest, alliances, diplomacy, trade |
| Closed Alpha | 28–35 | Retention systems and operator tooling |
| Beta | 36–47 | Hardened, measured, balanced, accessible |
| Alpha/Beta Release | 48–49 | Real players on real servers |
| Release Candidate | 50–52 | Reproducible production, tested recovery |
| Launch & LiveOps | 53–54 | Public, then running as a live service |

**238 plans** and **275 success criteria** across the milestone. The critical
path is 42 phases deep — see [`DEPENDENCIES.md`](DEPENDENCIES.md).

## The ordering principle

The sequence is not arbitrary. Each stage proves something before the next
depends on it:

1. **Prove the machine works** before building the game (00–02).
2. **Prove the server can be trusted** before anything is multiplayer (03–07).
3. **Prove the economy cannot be duplicated** before players can trade (08).
4. **Prove combat is deterministic** before players can lose anything to it (17).
5. **Prove it holds up** before real players arrive (36–47).

Building alliances before the economy is safe would mean rewriting alliances
once it is.

## Executing a phase

See [`EXECUTION_RULES.md`](EXECUTION_RULES.md) for the full protocol, the
Definition of Ready and the Definition of Done.

The short version:

```
Execute GSD Phase NN
  → read STATE, ROADMAP §NN, CONTEXT, canonical refs
  → validate dependencies (stop if unmet)
  → build: migrations → domain → application → interface → tests → docs
  → run every gate, actually
  → update STATE and ROADMAP, write SUMMARY
  → mark DONE only if it genuinely is
```

## Running it autonomously

```bash
/gsd:autonomous
```

Because every phase already has a CONTEXT.md, the discuss step is skipped and
each phase goes straight to planning against decisions that are already made.
Research is disabled in `.planning/config.json` for the same reason — there is
nothing left to discover that the plan has not already settled.

## What must not happen

- Re-planning something already decided
- Silently designing around a locked decision (write an ADR instead)
- Marking a phase DONE without running its validations
- Widening scope beyond a phase's success criteria
- A `TODO` with no entry in `.planning/codebase/CONCERNS.md`
