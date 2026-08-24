# Anti-cheat

Cheating in this game means acquiring value the rules did not grant. The
architecture prevents most of it structurally; this document covers what remains
and how it is detected.

## Prevented by design

These are not "hard to cheat" — they are impossible through the API, because the
client is never given the authority:

- **Fabricating resources** — every mutation goes through the ledger, and balances
  must reconcile to it (ADR-010, Phase 08).
- **Winning a battle by claiming it** — the client sends an attack order and
  receives a result it did not compute (ADR-006, ADR-009).
- **Finishing timers early** — durations are server-computed and stored; the
  device clock is not an input (ADR-006).
- **Editing costs** — the server recomputes every cost from game data; the request
  carries only intent (ADR-013).

## Detected, not prevented

Some behaviour is legitimate in small amounts and abusive in large ones. These
produce **flags for human review**, not automatic bans.

| Signal | What it may indicate |
|--------|----------------------|
| Economic gain per hour beyond the simulated ceiling for a play profile | Exploit or undiscovered bug |
| Perfectly regular action intervals over long periods | Automation |
| Sustained activity beyond plausible human session length | Automation or account sharing |
| Many accounts sharing a device fingerprint feeding one account | Farming rings |
| Trade flow consistently one-directional between the same pair | Resource laundering |

Automatic bans on statistical signals punish unusual-but-legitimate players, so
sanctions require a moderator decision with a recorded rationale (Phase 35).

## Economy invariants

Continuously assertable properties. A violation is either a bug or an exploit, and
both need to be known immediately:

1. Ledger sum per city equals the city's stored balance.
2. Total resources in a world change only by documented faucets and sinks.
3. A trade conserves total resources minus tax.
4. No balance is negative.
5. No balance exceeds `ResourceAmount::MAX`.

Invariant 1 and 4 are enforced in code and asserted by property tests (Phase 08).
Invariants 2, 3 and 5 are monitored as metrics (Phase 36) and alerted on (Phase 49).

## What we deliberately do not do

- **Client integrity checks as a control.** The client holds no authority worth
  protecting. Attestation may later feed anomaly scoring; it will never gate an
  action, because a gate that runs on the attacker's device is not a gate.
- **Obscuring the API.** Security through obscurity delays a determined attacker
  by hours and costs us clarity forever.
