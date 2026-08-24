# Security policy

## Reporting a vulnerability

Do **not** open a public issue.

Email the maintainers with: what you found, how to reproduce it, what an attacker
gains, and any suggested fix. Expect an acknowledgement within a few days.

Please do not exploit an issue beyond what is needed to demonstrate it, and do not
access, modify or delete other players' data.

## Scope

In scope: the API, the mobile client, the back office, the infrastructure
configuration in this repository.

Out of scope: decompiling the client (expected — it holds no authority worth
stealing), denial of service, social engineering, and anything requiring physical
access to a player's unlocked device.

## Posture

The governing assumption is that **the client is fully compromised**: decompiled,
traffic rewritten, clock forged, requests replayed. Every control is designed
against that assumption rather than patched in after it is demonstrated.

The full model — 20 mapped threats with their controls and owning phases — is in
[`docs/security/threat-model.md`](docs/security/threat-model.md).

Highlights:

- Server-authoritative gameplay; no client-supplied outcome is trusted (ADR-006)
- Integer-only economy with an append-only ledger (ADR-010)
- Transaction + row locking on every spend, re-checked inside the lock
- `Idempotency-Key` required on every mutating command
- Deterministic, replayable combat (ADR-009)
- Rotating refresh tokens with reuse detection (ADR-011)
- Deny-by-default broadcast channel authorisation
- Debug tooling double-gated by flag **and** environment

## What we ask of contributors

- Never commit a secret. `.env` is gitignored; CI scans for leaks.
- Never log a password, token, authorization header or personal information.
- Never add an endpoint that trusts a client-supplied outcome.
- Every security-relevant change needs a **named test**, not an assertion that it
  was considered.
