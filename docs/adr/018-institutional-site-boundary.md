# ADR-018: Institutional site boundary

**Status:** Accepted
**Date:** 2026-08-27

## Context

The project is mobile-first and explicitly excludes web and desktop game clients,
but a public product still needs a home page, feature explanation, support and
legal notices. The existing Laravel root is only the stock welcome page, while the
same application already owns the API and Filament back office.

## Decision

Build the institutional site as a server-rendered Laravel surface in the existing
modular monolith. It may expose public, read-only product content, localized legal
pages, SEO metadata and a rate-limited support submission. It must not expose game
state, implement gameplay commands, authenticate as a second game client or bypass
the OpenAPI/API authority.

Use `config('game.name')` for the product name and the shared semantic design-token
source for visual values. Keep game administration in the Filament back office;
institutional content is versioned with the application until a separate CMS is
justified.

## Consequences

- Laravel owns three clearly separated surfaces: public web routes, `/api/v1` and
  the staff-only `/admin` panel.
- Public routes can ship before the mobile MMO is complete and cannot mutate player
  or world data.
- A future CMS or public account portal requires a new decision; it must not be
  smuggled into the institutional site phase.
