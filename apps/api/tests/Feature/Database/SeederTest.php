<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DevelopmentUserSeeder;
use Database\Seeders\StaffUserSeeder;

it('seeds a browsable development dataset', function (): void {
    $this->artisan('db:seed')->assertSuccessful();

    expect(User::query()->count())->toBe(5)
        ->and(User::query()->where('is_staff', true)->count())->toBe(2)
        ->and(User::query()->where('email', config('game.admin_seed.email'))->exists())->toBeTrue();
});

it('is safe to run twice', function (): void {
    $this->artisan('db:seed')->assertSuccessful();
    $first = User::query()->orderBy('id')->pluck('id')->all();

    $this->artisan('db:seed')->assertSuccessful();
    $second = User::query()->orderBy('id')->pluck('id')->all();

    expect($second)->toBe($first)
        ->and(User::query()->count())->toBe(5);
});

it('persists the staff flag the back office authorises on', function (): void {
    // Regression: is_staff is absent from User::$fillable, so a mass-assigning
    // seeder either throws or silently creates an admin who cannot log in.
    $this->artisan('db:seed')->assertSuccessful();

    $admin = User::query()->where('email', config('game.admin_seed.email'))->sole();

    expect($admin->is_staff)->toBeTrue();
});

it('refuses to invent an admin password outside local', function (): void {
    $this->app->detectEnvironment(fn (): string => 'staging');
    config()->set('game.admin_seed.password', '');

    app(StaffUserSeeder::class)->run();

    expect(User::query()->where('email', config('game.admin_seed.email'))->exists())->toBeFalse();
});

it('does not create development accounts outside a development environment', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');

    app(DevelopmentUserSeeder::class)->run();

    expect(User::query()->count())->toBe(0);
});

it('never seeds reference data — that is game:import-data\'s job', function (): void {
    // REQ-06: balance data is authored in packages/game-data, validated in CI and
    // imported by its own command. A seeder that creates a building is a balance
    // number in PHP by another name (ADR-013).
    $forbidden = ['building', 'unit', 'technolog', 'game-data', 'game_data'];

    foreach (glob(database_path('seeders/*.php')) ?: [] as $file) {
        $contents = strtolower((string) file_get_contents($file));

        foreach ($forbidden as $needle) {
            expect(str_contains($contents, $needle))
                ->toBeFalse("{$file} references reference data ({$needle})");
        }
    }
});
