<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enables PostGIS before anything else runs.
 *
 * Named `0000_` so it sorts ahead of every other migration — a spatial column in
 * a later phase must never be the thing that discovers the extension is missing
 * (ADR-004).
 *
 * Skipped on any non-PostgreSQL driver: the default test suite runs on SQLite
 * in-memory because the host has no pdo_pgsql (see .planning/codebase/CONCERNS.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis;');
    }

    public function down(): void
    {
        // Deliberately a no-op. Dropping the extension would cascade into every
        // geometry column in the database, and the postgis/postgis image ships it
        // regardless — so rolling back must not remove it. `migrate:rollback` in CI
        // depends on this being safe.
    }
};
