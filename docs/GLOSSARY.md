# Glossary

Formal definitions. The point is that three developers do not use "city", "base"
and "kingdom" for the same table while nobody notices.

| Term | Definition | Not to be confused with |
|------|------------|-------------------------|
| **Account** | An identity that can sign in. Holds credentials and device sessions. | **Player** — an account's presence in one world |
| **Player** | An account's game presence inside one **World**. One per account per world. | **Account** |
| **World** | A shard. Independent map, economy, rankings, alliances and chat. Players never interact across worlds. | **Region** — a partition *inside* a world |
| **Region** | A partition within a World. The unit of realtime subscription, map fetching and territorial control. | **Territory** — who *owns* land |
| **Tile** | One addressable cell, `(world_id, x, y)`. Holds at most one occupant. | **Region** |
| **City** | A player-owned settlement on exactly one Tile. Has build slots, resources and a garrison. | **Base**, **Kingdom** — not used |
| **Territory** | Land under a player's or alliance's control, derived from cities and strategic points. Stored as PostGIS geometry. | **Region** — a fixed partition; territory is contested and changes |
| **Garrison** | Troops physically present in a city, including reinforcements from other players. | **Army** — a formed, deployable unit |
| **Army** | A named, formed group of troops with a formation and optionally a hero. Troops in an army are *reserved*. | **Garrison** |
| **Formation** | The arrangement of an Army, modifying combat resolution. | **Army composition** — which units, not how arranged |
| **March** | Any movement of an Army across the world. Types: attack, reinforce, gather, occupy, scout, rally, return, transport. | **Rally** — one specific kind |
| **Rally** | A coordinated attack combining several alliance members' armies into one force. | **Reinforcement** — defensive |
| **Reinforcement** | Troops sent to defend another player's city. They remain **owned by their sender**. | **Rally** |
| **Battle** | One resolved combat, simulated deterministically from a seed. Stored as inputs, not frames. | **Replay** — the reconstruction |
| **Replay** | A Battle re-derived by re-running the simulator on its stored inputs under the version it was fought with. | **Battle report** — the summary shown to a player |
| **Hero** | A collectible commander or governor. One assignment at a time. | **Unit** — anonymous, countable troops |
| **Unit** | A troop type (Legionaries, Archers…). Countable and anonymous. | **Hero** |
| **Alliance** | A player group with ranks, granular permissions, shared territory and diplomacy. | **Coalition** — not modelled |
| **Resource Node** | A world location that can be gathered from. Occupied exclusively while being gathered. | **NPC Camp** — fought, not gathered |
| **NPC Camp** | A hostile world location that can be attacked for rewards. Respawns. | **Resource Node** |
| **Technology** | A researchable improvement in an acyclic tree, with data-driven effects. | **Talent** — hero-specific |
| **Power** | An auditable score: `building + technology + army + hero + territory`. Stored as a **breakdown**, never one opaque number. | **Honour** |
| **Honour** | A measure of PvP conduct, gating Nobility. Can decrease. | **Power** |
| **Nobility** | A social rank ladder (Citizen → Emperor) with concrete privileges. | **Alliance rank** — internal to one alliance |
| **Ledger** | The append-only record of every resource mutation. Summing it must reproduce the balance exactly. | **Audit log** — administrative actions |
| **Audit log** | The immutable record of administrative and game-master actions. | **Ledger** |
| **Shield** | Time-boxed protection from attack. | **Walls** — a defensive structure |
| **Season** | A time-boxed competitive cycle with its own ruleset, rankings and rewards. | **Event** — shorter, narrower |
| **Event** | A time-boxed LiveOps activity configured without a deploy. | **Season** |
| **Game data** | Versioned balance JSON in `packages/game-data/`. The reviewable source of truth. | **Config** — structural limits in `config/game.php` |
| **Content version** | One of `data`, `combat`, `economy`. Recorded on battles and ledger entries so history stays reproducible. | **API version** (`/v1`) — the transport |
