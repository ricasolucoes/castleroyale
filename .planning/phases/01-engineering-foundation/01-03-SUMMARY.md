# Phase 01 Plan 03: Seeders and Fixtures

## Execution Complete

**Status**: ✅ Complete
**Tasks executed**: 3/3

### What Was Built
- Fixed `StaffUserSeeder` to use `firstOrNew()` + `forceFill()` to bypass mass assignment constraints cleanly inside the seeder.
- Created `DevelopmentUserSeeder` with an idempotent set of development accounts.
- Documented in `DECISIONS.md` why `updateOrCreate()` was dropped in favor of `firstOrNew()` + `forceFill()`.
- Added the `staff` state to `UserFactory`.
- Created robust seeder idempotency, boundary, and user count tests.
- Extracted generic `freezeClock()`, `actingAsStaff()`, and `toBeApiError()` fixtures into `tests/Pest.php` and documented them in `TESTING.md`.

### Self-Check
- [x] All tasks completed and verified via acceptance criteria.
- [x] Seed idempotency test proves rows aren't duplicated and primary keys remain unchanged.
- [x] `docs/gsd/DECISIONS.md` log correctly tracks the deviation from the locked `01-CONTEXT.md` decision.

### Outputs
```text
docker compose exec -T postgres psql -U castleroyale -d castleroyale -c "select id, email, is_staff from users order by id;"
                  id                  |          email           | is_staff 
--------------------------------------+--------------------------+----------
 01j62ys5zztf22z4p07wshw4k0           | admin@example.test       | t
 01j62ys6d0gswr62kdbb92ntd7           | support@example.test     | t
 01j62ys6ezhsnk8d28mvtav30p           | dev-alpha@example.test   | f
 01j62ys6f807s2cghc8h398f6s           | dev-bravo@example.test   | f
 01j62ys6fgmghxsmpsk2q410z7           | dev-charlie@example.test | f
(5 rows)

docker compose exec -T postgres psql -U castleroyale -d castleroyale -c "select count(*) as users, count(*) filter (where is_staff) as staff from users;"
 users | staff 
-------+-------
     5 |     2
(1 row)
```
