# Alliances

Alliance politics is the retention engine. Players stay for the people, and
alliances are where the people are.

## Permissions, not role names

**No authorisation check may compare a rank name.** An architecture test enforces
this (Phase 22).

Ranks are bundles of named permissions, and both are data:

```
alliance.invite            alliance.kick
alliance.promote           alliance.diplomacy
alliance.start_rally       alliance.manage_territory
alliance.edit_notice       alliance.manage_treasury
```

Checking `if ($member->rank === 'officer')` is the pattern this forbids: it
scatters policy across the codebase and makes a new rank a code change. A denied
action returns `ALLIANCE_PERMISSION_DENIED` and is written to the alliance log.

## Membership

- Member cap from config; exceeding returns `ALLIANCE_FULL`
- One alliance per player: `ALREADY_IN_ALLIANCE`
- Names unique per world: `ALLIANCE_NAME_TAKEN`
- Invitations and applications both supported
- A kicked member is dropped from the alliance channel **immediately**

## Capabilities

| Capability | Phase |
|------------|-------|
| Membership, ranks, permissions, donations | 22 |
| Alliance technology funded by donations | 22 |
| Territory and fortresses | 23 |
| Rally attacks and reinforcement | 24 |
| Chat and announcements | 25 |
| Diplomacy: war, peace, NAP | 26 |

## Rallies

A rally combines several players' armies into one force.

- Requires `alliance.start_rally`
- Has a **join window**; late joiners are refused
- Losses are distributed back **proportionally** to each contributor and must
  reconcile exactly — a conservation property test, like plunder
- Disbanding returns every contributed army without loss

## Reinforcement

Reinforcing troops defend the host city but remain **owned by their sender** and
return home on recall. Ownership never transfers — otherwise reinforcement becomes
a troop-transfer exploit.

## Diplomacy

War, peace and non-aggression pacts require `alliance.diplomacy`.

A treaty is **bilateral**: a proposal binds nothing until the other alliance
accepts. An active NAP blocks attacks between members with `INVALID_TARGET`.

Every change writes to both alliances' logs with actor, action and timestamp.
