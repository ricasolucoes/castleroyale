<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Bootstraps a back-office account.
 *
 * Outside local/testing the password is required to come from the
 * environment; the seeder refuses to invent one rather than silently
 * creating a known-credential admin on a shared box.
 */
final class StaffUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('game.admin_seed.email');
        $password = (string) config('game.admin_seed.password');

        if ($password === '') {
            if (! app()->environment(['local', 'testing'])) {
                $this->command?->warn('ADMIN_SEED_PASSWORD not set — skipping staff user seed.');

                return;
            }

            $password = 'password';
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Game Master',
                'password' => Hash::make($password),
                'is_staff' => true,
                'remember_token' => Str::random(10),
            ],
        );

        $this->command?->info("Staff user ready: {$email}");
    }
}
