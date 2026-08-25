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

        $user = User::query()->firstOrNew(['email' => $email]);

        // forceFill, not fill: `is_staff` is deliberately absent from
        // User::$fillable so no future registration endpoint can mass-assign it,
        // and AppServiceProvider enables preventSilentlyDiscardingAttributes().
        $user->forceFill([
            'name' => 'Game Master',
            'password' => Hash::make($password),
            'is_staff' => true,
            'email_verified_at' => $user->exists ? $user->email_verified_at : now(),
            // Re-running the seeder must not invalidate an existing session.
            'remember_token' => $user->exists ? $user->remember_token : Str::random(10),
        ])->save();

        $this->command?->info("Staff user ready: {$email}");
    }
}
