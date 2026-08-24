<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Entry point for every seed.
 *
 * Two distinct jobs, deliberately kept apart:
 *
 *   Reference data — buildings, units, technologies, heroes. Imported from
 *   `packages/game-data` by `php artisan game:import-data`, NOT seeded here.
 *   It is content, it is versioned, and production needs it too.
 *
 *   Development fixtures — a populated world with players, cities, armies and
 *   alliances so a developer can open the app and see a real game in the
 *   first minute. Local and testing only, never production.
 *
 * See docs/backend/architecture.md and GSD Phase 01.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            StaffUserSeeder::class,
        ]);

        if (app()->environment(['local', 'testing', 'development'])) {
            // Populated by the phases that own each subsystem. Each seeder is
            // additive and safe to re-run; see GSD Phase 01 task P01-BE-006.
            $this->call([
                //
            ]);
        }
    }
}
