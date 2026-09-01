# ADR-002: Laravel as the authoritative backend

**Status:** Accepted
**Date:** 2026-08-24

## Context

The server must be the sole authority over game state (ADR-006). It needs a
mature job queue for timed gameplay, websockets for live updates, a database
layer with real transaction control, and an admin panel — all delivered by a
small team.

PHP 8.4 and Laravel 13 were fixed by the project brief.

## Decision

Use **Laravel 13 on PHP 8.4** as the single authoritative backend, with:

- **Horizon** for queue supervision across six priority tiers
- **Reverb** for websockets (ADR-008)
- **Octane** for persistent workers where the throughput justifies it
- **Sanctum** for API token authentication (ADR-011)
- **Filament** for the back office

The PHP root namespace for game code is **`Game\`**, mapped to `modules/`. It is
deliberately neutral: the product name is a configuration value in
`config/game.php`, so renaming the game never touches a single namespace.

`App\` stays thin — framework glue only. Game rules live in `Game\`.

## Alternatives

**Node/TypeScript backend sharing types with the client.** Rejected: the brief
fixes PHP, and shared types would tempt us to trust client-shaped data.

**Go or Elixir for the realtime and simulation layer.** Genuinely attractive for
determinism and concurrency, and rejected only for now: a second language doubles
the operational surface before there is a measured need. ADR-001 keeps the door open.

**Namespacing modules under the product name (`CastleRoyale\`).** Rejected: the game
has a working title. Baking it into thousands of namespace declarations makes the
eventual rename a mechanical risk for zero benefit.

## Consequences

- One language, one dependency manager, one deployment story for the whole server.
- PHP is not a natural fit for a tight simulation loop. The battle simulator is
  therefore written as pure, allocation-conscious PHP with no I/O, and its
  performance is measured in Phase 39 rather than assumed.
- `Game\` never needs renaming. `config('game.name')` is the only place the product
  name appears, and an architecture test keeps it that way.
- Filament gives a back office nearly free, but it is Livewire-based and
  session-authenticated — a different auth path from the API, which must be gated
  independently (`User::canAccessPanel()`).
