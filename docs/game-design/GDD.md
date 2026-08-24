# Game design document

The index. Each subsystem has its own document; this one connects them and states
the pillars they must serve.

## Concept

A mobile MMO of empire building, territorial conquest and real-time strategic
warfare. A player starts with one small city in a persistent, shared world and
competes with thousands of others for control of it.

Original product. The genre is shared; nothing else is.

## Pillars

**1. Meaningful commitment.** Sending an army is a real decision — it takes time,
it can be lost, and it leaves home undefended. Nothing important is free or
instant.

**2. Strategy over spreadsheet.** A player who brings the countering unit, the
right terrain and the right commander should beat a bigger army. Composition,
timing and position must matter more than raw totals.

**3. People are the endgame.** Alliances, rallies and diplomacy are the retention
engine. The most memorable moments should involve other humans.

**4. Fair by construction.** The server owns the truth. A new player is never a
free farm. Nobody can buy an outcome the rules do not sell.

## Core loop

```
Collect → Build → Research → Train → Recruit → Explore → Fight
   → Take territory → Ally → War → Dominate
```

Detail: [`progression.md`](progression.md).

## Subsystems

| Document | Covers |
|----------|--------|
| [`world.md`](world.md) | Worlds, regions, tiles, terrain, generation, beginner zones |
| [`economy.md`](economy.md) | Resources, production, ledger, faucets and sinks |
| [`buildings.md`](buildings.md) | The 18 buildings, the Palace gate, construction |
| [`technology.md`](technology.md) | The research DAG and data-driven effects |
| [`units.md`](units.md) | Roster, stats, and why counters are not rock-paper-scissors |
| [`heroes.md`](heroes.md) | Roles, progression, the one-assignment rule |
| [`combat.md`](combat.md) | Deterministic resolution, what decides a battle |
| [`alliances.md`](alliances.md) | Permissions, rallies, reinforcement, diplomacy |
| [`trade.md`](trade.md) | Market, transport, conservation guarantees |
| [`nobility.md`](nobility.md) | The social ladder and its privileges |
| [`progression.md`](progression.md) | Pacing, power, protecting new players |
| [`liveops.md`](liveops.md) | Events, seasons, the content pipeline |

## Design constraints that are non-negotiable

These are engineering decisions with design consequences. Design works within
them rather than around them.

| Constraint | Design consequence |
|------------|--------------------|
| Integer economy (ADR-010) | A 0.1% bonus on 500 units is zero. Small percentage modifiers on small numbers are not meaningful — do not design them |
| Deterministic combat (ADR-009) | Every input that affects a battle must be knowable up front. No hidden live inputs |
| Data-driven balance (ADR-013) | Any number a designer wants to tune must be data. If it needs code, it is not balance |
| Server-authoritative (ADR-006) | No instant client-side feedback for anything that spends. Design for a round trip |
| A player cannot lose their last city | Total elimination is not a design tool |

## Monetisation

Deliberately unplanned before Phase 50. The architecture keeps `Store` isolated,
and **nothing in the core loop may depend on it**.

Hero acquisition economics are settled alongside the store, not in Phase 13, for
exactly this reason.

## Explicitly out of scope

- Real-time tactical control by two humans simultaneously
- Cross-world play
- Player-authored content
- Web or desktop clients
