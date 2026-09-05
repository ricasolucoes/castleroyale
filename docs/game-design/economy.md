# Economy

## Resources

| Resource | Produced by | Primary use |
|----------|-------------|-------------|
| **Food** | Farm | Troop upkeep, training |
| **Wood** | Lumber Mill | Construction |
| **Stone** | Quarry | Construction, walls |
| **Iron** | Iron Mine | Troops, siege |
| **Gold** | Treasury, trade, plunder | Research, heroes, trade |

Secondary, non-tradeable measures: **Population** (capacity), **Influence**
(territory), **Honour** (PvP conduct, gates nobility).

## Rules

**Integers only.** No float touches a resource (ADR-010). Percentage modifiers use
integer permille and always truncate downward — bonuses round in the house's favour.

**Production accrues from elapsed server time on read**, not from a background
tick. A city closed for six hours and one polled every minute must reach the
identical total. This is the acceptance test of Phase 08.

**Units.** A `production.{resource}` effect in `packages/game-data` is **units per second**
— that is the figure `CityEconomyService::accrueLocked()` multiplies by elapsed
seconds. The API publishes `CityResources.rate` as **signed integer units per hour**
(`perSecond * 3600`) so a client can interpolate against a wall clock. The two
never disagree: an hour of accrual produces exactly the published rate, counting
the portion the warehouse cap discards.

**Storage is capped** by warehouse level. Overflow is discarded at the cap and
recorded — never silently dropped.

**Every mutation writes a ledger row**: source, destination, resource, amount,
reason, reference, timestamp, economy version. The ledger is append-only, and
summing it must reproduce the balance exactly.

## Faucets and sinks

Every faucet needs a matching sink, or the economy inflates until numbers stop
meaning anything.

| Faucets | Sinks |
|---------|-------|
| Building production | Construction |
| Gathering | Research |
| NPC camp rewards | Troop training |
| Plunder (transfer, not creation) | Troop upkeep |
| Quest and event rewards | Troop losses in combat |
| Trade (transfer minus tax) | Trade tax |
| | Hero recruitment and upgrades |
| | Nobility requirements |

**Plunder and trade are transfers, not faucets.** They move value between players;
they do not create it. Conservation is asserted by property tests (Phases 19, 27).

Net inflation is projected by simulation and must stay inside a documented band
(Phase 46).

## Upkeep

Troops consume food per hour. An army exceeding food production drains stores and
eventually starves, which caps standing armies without an arbitrary rule.

Upkeep is the primary sink that keeps late-game economies from spiralling.

## Protection against duplication

Threat T-01 and T-12. The controls:

1. All spending inside a transaction with `SELECT ... FOR UPDATE` on the owner.
2. Affordability re-checked **inside** the lock.
3. `Idempotency-Key` on every mutating command.
4. Resources in transit exist in exactly one place — never in both the city and
   the transport march.
5. Ledger reconciliation as a property test.
6. `CHECK (>= 0)` at the database level as the last line of defence.

## Versioning

Every ledger entry records the `economy_version` in force (ADR-015), so an audit
can say which cost table applied at the time.
