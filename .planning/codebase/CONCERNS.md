# Concerns

Known limitations and debt. Each has an owner phase.

## Environment

**PostGIS is proven in CI and in the Docker stack; the host still cannot run it.**
On every push the Backend job runs migrations forward, back and forward again,
seeds twice, asserts `postgis_version()` and runs the PostGIS-only suite
(`phpunit.postgres.xml`) against `postgis/postgis:16-3.4`. Evidence — all four
jobs green, including the `CI` aggregate:
<https://github.com/ricasolucoes/castleroyale/actions/runs/32802315288>.
Locally the same suite runs inside the stack with `make test-postgres`.

The **local host** still has no `pdo_pgsql`, so a green `./vendor/bin/pest` on the
host is SQLite-only evidence. Do not claim a PostGIS migration works from the host
suite — use `make test-postgres` or the CI run.

## Accepted debt

| ID | Debt | Impact | Target phase |
|----|------|--------|--------------|
| DEBT-001 | Broadcast channel callbacks for alliance, battle and region still return `false`; player (Phase 04) and city (Phase 07) channels are implemented and tested both ways | Unimplemented channels remain unusable, which is the safe default | Each channel's owning phase (17, 22, 05) |
| DEBT-002 | `config/` and `tests/` excluded from PHPStan | Stock Laravel config and Pest's fluent API are not statically modelable; first-party code is fully covered | 37 |
| DEBT-003 | `App\Models\User` is a placeholder with no device sessions, guest support or social identity | Only supports back-office login | 03 |
| DEBT-004 | No OpenAPI spec written yet; `packages/contracts` is scaffolded but empty | Contract tests cannot run | 03 (first real endpoints) |
| DEBT-005 | `npm audit` reports moderate advisories in the Expo dependency tree | Transitive, no known exploit path in a mobile bundle | 37 |
| DEBT-006 | No Filament resources exist beyond the default dashboard | Back office is empty | 34 |
| DEBT-007 | Game data packages are scaffolded but hold no datasets or validator | Balance cannot be imported | 09 (first dataset), validator in 10 |

## Traps that have already cost time

Fixed during Phase 00 — do not reintroduce:

1. **`Date::use(Date::class)`** in a service provider breaks application boot with
   a confusing error. Do not try to force UTC that way; `APP_TIMEZONE` plus
   `date_default_timezone_set('UTC')` is correct.
2. **`env()` outside `config/`** returns `null` once config is cached. Always route
   through a `config()` value. Larastan catches this.
3. **A gate referencing a column that does not exist** (`$user->is_staff` before the
   migration). PHPStan catches it; add the migration in the same change.
4. **Unquoted heredocs in shell** execute backticks inside the content. Use
   `<<'EOF'` when writing docs or code containing backticks.

## Standing risks

- **Cross-world data leakage.** Every gameplay query must filter `world_id`. This
  is not enforced mechanically yet; it is a review obligation on every query.
- **Reconciler silence.** A reconciler that has to recover rows means something
  upstream failed. Emit a metric; do not let it heal invisibly.
- **Skia has no free accessibility.** The map is a canvas, so hit-testing and
  screen-reader support are built explicitly (Phases 06 and 41).
