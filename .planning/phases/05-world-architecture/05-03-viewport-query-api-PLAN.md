---
wave: 3
depends_on: ["05-01", "05-02"]
files_modified:
  - apps/api/modules/World/Application/
  - apps/api/modules/World/Interface/Http/
  - apps/api/routes/api.php
  - apps/api/tests/Feature/World/
  - packages/contracts/openapi.yaml
  - packages/contracts/src/generated/api.ts
---

# Plan 05-03: Viewport and chunk query API

## Goal

Expose bounded, world-scoped viewport reads that return only tiles within the
requested rectangle.

## Tasks

1. Add a typed viewport request/response contract with integer bounds, chunk
   metadata and tile records.
2. Validate the configured maximum tile count before querying; return the
   canonical `VALIDATION_FAILED` code instead of silently clamping.
3. Query by authenticated player world and `(x, y)` bounds, with stable ordering
   and a bounded result set; never load the whole map.
4. Add tests for inclusive bounds, cross-world isolation, oversized bounds,
   invalid rectangles and the success/error envelopes.

## Acceptance criteria

- Only tiles inside the requested bounds are returned.
- A request larger than `game.limits.world_viewport_max_tiles` returns
  `VALIDATION_FAILED`.
- A player cannot use a viewport request to read another world.

## Verification

Run World viewport feature tests, contract generation/drift check, full Pest,
PHPStan, Pint and the mobile/workspace typecheck if the client wrapper changes.
