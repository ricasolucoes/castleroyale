<?php

declare(strict_types=1);

use Game\Identity\Application\TokenIssuer;
use Game\Identity\Domain\Account;
use Illuminate\Support\Carbon;

/**
 * @return array{access_token: string, refresh_token: string}
 */
function constructionQueueTokens(string $suffix): array
{
    $account = Account::factory()->create();

    return app(TokenIssuer::class)->issue($account, [
        'device_id' => 'construction-queue-device-'.$suffix,
        'device_name' => 'Construction queue test device',
        'platform' => 'test',
        'ip' => '127.0.0.1',
    ]);
}

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

it('returns an empty array, not null, when nothing is building', function (): void {
    freezeClock('2026-09-06T12:00:00+00:00');
    $tokens = constructionQueueTokens('empty-queue');

    test()->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'ctq-empty-bootstrap-001')
        ->postJson('/api/v1/game/bootstrap');

    $city = test()->withToken($tokens['access_token'])->getJson('/api/v1/game/city');

    $city->assertOk()
        ->assertJsonPath('data.constructions', [])
        ->assertJsonPath('data.queue_limit', 4);
});

it('returns every concurrent order in completion order', function (): void {
    freezeClock('2026-09-06T12:00:00+00:00');
    $tokens = constructionQueueTokens('concurrent-orders');

    test()->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'ctq-concurrent-bootstrap-001')
        ->postJson('/api/v1/game/bootstrap');

    // Starter resources (500/500/500/250/100) cover the three level-2 costs
    // (food 80+0+0=80, wood 0+180+120=300, stone 100+160+60=320) with room to
    // spare — no resource grant needed.
    foreach (['lumber_mill', 'warehouse', 'farm'] as $index => $buildingCode) {
        test()->withToken($tokens['access_token'])
            ->withHeader('Idempotency-Key', "ctq-concurrent-upgrade-{$index}")
            ->postJson("/api/v1/game/city/buildings/{$buildingCode}/upgrade")
            ->assertStatus(201);
    }

    $city = test()->withToken($tokens['access_token'])->getJson('/api/v1/game/city');
    $city->assertOk()->assertJsonCount(3, 'data.constructions');

    $finishTimes = collect($city->json('data.constructions'))
        ->map(static fn (array $order): int => Carbon::parse($order['finishes_at'])->getTimestamp())
        ->values()
        ->all();
    $sorted = $finishTimes;
    sort($sorted);
    expect($finishTimes)->toBe($sorted);
});

it('echoes the configured ceiling', function (): void {
    freezeClock('2026-09-06T12:00:00+00:00');
    config(['game.limits.max_build_queue_slots' => 2]);
    $tokens = constructionQueueTokens('ceiling');

    test()->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'ctq-ceiling-bootstrap-001')
        ->postJson('/api/v1/game/bootstrap');

    $city = test()->withToken($tokens['access_token'])->getJson('/api/v1/game/city');
    $city->assertOk()->assertJsonPath('data.queue_limit', 2);
});

it('drops a completed order from the queue', function (): void {
    $clock = freezeClock('2026-09-06T12:00:00+00:00');
    $tokens = constructionQueueTokens('completed-order');

    test()->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'ctq-completed-bootstrap-001')
        ->postJson('/api/v1/game/bootstrap');

    test()->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'ctq-completed-upgrade-001')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade')
        ->assertStatus(201);

    $clock->advanceSeconds(21);

    $city = test()->withToken($tokens['access_token'])->getJson('/api/v1/game/city');
    $city->assertOk()
        ->assertJsonPath('data.constructions', [])
        ->assertJsonPath('data.slots.1.building.code', 'farm')
        ->assertJsonPath('data.slots.1.building.level', 2);
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
