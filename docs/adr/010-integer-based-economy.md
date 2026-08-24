# ADR-010: Integer-only economy

**Status:** Accepted
**Date:** 2026-08-24

## Context

Resources accrue continuously, are modified by percentage bonuses from
technology, heroes and buildings, and are transferred between players. Any
representation that rounds will drift, and drift in an economy either mints value
from nothing or destroys player property. Both are unacceptable, and both are
discovered by players before they are discovered by developers.

## Decision

**Every economic quantity is a non-negative integer.** No `float` or `double`
touches a resource, cost, production rate, plunder amount or ledger entry.

Enforced by `Game\Shared\Domain\Economy\ResourceAmount`:

- Constructed only through `of()`, which rejects negatives and values above a
  ceiling chosen to keep intermediate sums inside `bigint`.
- `plus()` raises rather than overflowing; `minus()` raises rather than going
  negative. Going below zero requires calling `subtractSaturating()` explicitly,
  so a floor is always a deliberate decision at the call site.
- Percentage modifiers are applied as **integer permille** via
  `scaledByPermille()` — 1500 means 150%. Truncation is toward zero and always
  favours the house: bonuses round down.

`ResourceBundle` composes these per resource type and provides `covers()` and
`shortfallAgainst()` so a cost check and its error detail come from one place.

Database columns are `bigint`. Production is computed from elapsed seconds, so a
city polled every minute and one left closed for six hours reach the identical total.

## Alternatives

**Floating point.** Rejected: rounding drift is guaranteed, and it is
platform-dependent, which also breaks battle determinism (ADR-009).

**Decimal/BCMath.** Correct but slow, and it invites fractional resources — which
is a game design decision we do not want, not just a storage one. Whole units are
simpler for players to reason about too.

**Integers with float bonus multipliers cast at the end.** Rejected: this is
floating point with extra steps, and the cast point becomes an exploitable seam.

## Consequences

- Bonuses are inherently lossy at small magnitudes: a 0.1% bonus on 500 units
  yields zero. This is accepted and must inform balance design (Phase 46) — small
  percentage bonuses on small numbers are simply not meaningful.
- Every arithmetic path can raise. Callers must check affordability inside the
  transaction rather than catching exceptions as flow control.
- The ceiling is a real limit. A player cannot accumulate beyond it; the cap is a
  design constant, not an accident.
- Ledger sums must reconcile exactly to balances — a property test asserts this
  over random operation sequences (Phase 08).
