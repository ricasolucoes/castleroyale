<?php

declare(strict_types=1);

namespace Tests\Feature\City;

use Illuminate\Support\Carbon;

it('previews the same build duration the server will actually schedule', function (): void {
    freezeClock('2026-09-06T12:00:00+00:00');

    // First pass: time_scale = 3. Catalogue duration for farm level 2 is 20s, so
    // the previewed and scheduled duration must both be 20/3 = 6.
    config(['game.time_scale' => 3]);

    $guest = test()->withHeader('Idempotency-Key', 'ctq-preview-scale3-guest-001')
        ->postJson('/api/v1/auth/guest');
    $tokens = $guest->json('data');
    test()->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'ctq-preview-scale3-bootstrap-001')
        ->postJson('/api/v1/game/bootstrap');

    $city = test()->withToken($tokens['access_token'])->getJson('/api/v1/game/city');
    $farmSlot = collect($city->json('data.slots'))->firstWhere('building.code', 'farm');
    $previewedDuration = $farmSlot['building']['build_time_seconds'];
    expect($previewedDuration)->toBe(6);

    $upgrade = test()->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'ctq-preview-scale3-upgrade-001')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade');
    $upgrade->assertStatus(201);
    $started = Carbon::parse($upgrade->json('data.construction.started_at'));
    $finishes = Carbon::parse($upgrade->json('data.construction.finishes_at'));
    expect((int) abs($finishes->diffInSeconds($started)))->toBe($previewedDuration);

    // Second pass: time_scale = 1 on a fresh account, so the first order's queue
    // state cannot interfere. Proves the scale is actually applied, not merely
    // that two numbers happen to agree at scale 1.
    config(['game.time_scale' => 1]);
    app('auth')->forgetGuards();

    $guest2 = test()->withHeader('Idempotency-Key', 'ctq-preview-scale1-guest-001')
        ->postJson('/api/v1/auth/guest');
    $tokens2 = $guest2->json('data');
    test()->withToken($tokens2['access_token'])
        ->withHeader('Idempotency-Key', 'ctq-preview-scale1-bootstrap-001')
        ->postJson('/api/v1/game/bootstrap');

    $city2 = test()->withToken($tokens2['access_token'])->getJson('/api/v1/game/city');
    $farmSlot2 = collect($city2->json('data.slots'))->firstWhere('building.code', 'farm');
    $previewedDuration2 = $farmSlot2['building']['build_time_seconds'];
    expect($previewedDuration2)->toBe(20);

    $upgrade2 = test()->withToken($tokens2['access_token'])
        ->withHeader('Idempotency-Key', 'ctq-preview-scale1-upgrade-001')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade');
    $upgrade2->assertStatus(201);
    $started2 = Carbon::parse($upgrade2->json('data.construction.started_at'));
    $finishes2 = Carbon::parse($upgrade2->json('data.construction.finishes_at'));
    expect((int) abs($finishes2->diffInSeconds($started2)))->toBe($previewedDuration2);
});

it('cannot let the time accelerator escape local', function (): void {
    // config/game.php has forced time_scale to 1 outside `local` since the
    // bootstrap commit, but nothing has ever exercised it. Re-evaluate the
    // config file directly rather than trusting the already-booted container,
    // because APP_ENV is `testing` for the whole suite. Laravel's env()
    // helper resolves through a repository whose adapters check $_ENV,
    // $_SERVER and putenv()/getenv() in order — mutating only one leaves the
    // others holding the original `testing` value, so all three move together.
    $set = static function (string $key, string $value): void {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    };
    $unset = static function (string $key): void {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    };
    $evaluate = static function (string $appEnv, string $debugScale) use ($set, $unset): int {
        $previousEnv = $_ENV['APP_ENV'] ?? null;
        $previousScale = $_ENV['DEBUG_TIME_SCALE'] ?? null;
        $set('APP_ENV', $appEnv);
        $set('DEBUG_TIME_SCALE', $debugScale);

        try {
            return (int) (require base_path('config/game.php'))['time_scale'];
        } finally {
            $previousEnv === null ? $unset('APP_ENV') : $set('APP_ENV', $previousEnv);
            $previousScale === null ? $unset('DEBUG_TIME_SCALE') : $set('DEBUG_TIME_SCALE', $previousScale);
        }
    };

    expect($evaluate('local', '60'))->toBe(60);      // a developer may accelerate
    expect($evaluate('production', '60'))->toBe(1);  // production may not
    expect($evaluate('staging', '60'))->toBe(1);      // nor may anything else
});
