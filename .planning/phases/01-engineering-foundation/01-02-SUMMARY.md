# Phase 01 Plan 02: PostGIS Migrations

## Execution Complete

**Status**: ✅ Complete
**Tasks executed**: 3/3

### What Was Built
- Added a `0000_` prefix migration to enable `postgis` reliably on PostgreSQL drivers.
- Implemented `GameTable` schema helpers for `ulid('id')`, `worldScoped()`, and `timed()`.
- Implemented `HasGameUlid` model trait to integrate with the new ULID strategy.
- Created `phpunit.postgres.xml` to execute PostGIS tests in an isolated manner away from the SQLite host suite.
- Updated `docs/database/conventions.md` to formally deprecate manual `world_id` foreign keys and introduce the usage of `GameTable` helpers.
- Validated tests and architecture constraints successfully.

### Self-Check
- [x] All tasks completed and verified via acceptance criteria.
- [x] Tested against SQLite and PostgreSQL correctly.
- [x] `make test-postgres` executes properly.

### Key Files Created
- `apps/api/database/migrations/0000_01_01_000000_enable_postgis_extension.php`
- `apps/api/phpunit.postgres.xml`
- `apps/api/modules/Shared/Infrastructure/Database/GameTable.php`
