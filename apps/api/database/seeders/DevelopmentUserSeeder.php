<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Development accounts.
 *
 * Enough rows that a developer opening the back office sees a populated list
 * rather than a single admin. Deterministic emails so a re-run updates instead of
 * duplicating, and so a test can assert against a known address.
 *
 * Gameplay fixtures (players, cities, armies, alliances) are added by the phases
 * that own those entities — Phase 01 has no gameplay entities to seed.
 */
final class DevelopmentUserSeeder extends Seeder
{
    /**
     * @var list<array{email: string, name: string, is_staff: bool}>
     */
    private const ACCOUNTS = [
        ['email' => 'support@example.test', 'name' => 'Support Agent', 'is_staff' => true],
        ['email' => 'dev-alpha@example.test', 'name' => 'Alpha Tester', 'is_staff' => false],
        ['email' => 'dev-bravo@example.test', 'name' => 'Bravo Tester', 'is_staff' => false],
        ['email' => 'dev-charlie@example.test', 'name' => 'Charlie Tester', 'is_staff' => false],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing', 'development'])) {
            $this->command?->warn('Not a development environment — skipping development users.');

            return;
        }

        foreach (self::ACCOUNTS as $account) {
            $user = User::query()->firstOrNew(['email' => $account['email']]);

            $user->forceFill([
                'name' => $account['name'],
                'is_staff' => $account['is_staff'],
                'email_verified_at' => $user->exists ? $user->email_verified_at : now(),
                'password' => $user->exists ? $user->password : Hash::make('password'),
            ])->save();
        }

        $this->command?->info('Development users ready: '.count(self::ACCOUNTS).' accounts.');
    }
}
