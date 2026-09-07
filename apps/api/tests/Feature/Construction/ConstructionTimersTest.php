<?php

declare(strict_types=1);

use Game\City\Infrastructure\City;
use Game\Construction\Infrastructure\ConstructionOrder;
use Game\Economy\Infrastructure\EconomyLedger;

/**
 * Guest + bootstrap. Named distinctly from the helpers in the sibling
 * construction test files — PHPUnit loads them all into one process.
 *
 * @return array{token: string, worldId: string, cityId: string}
 */
function enterCityForTimers(string $keyPrefix): array
{
    $guest = test()->withHeader('Idempotency-Key', $keyPrefix.'-guest')
        ->postJson('/api/v1/auth/guest');
    $guest->assertStatus(201);
    $token = (string) $guest->json('data.access_token');

    $bootstrap = test()->withToken($token)
        ->withHeader('Idempotency-Key', $keyPrefix.'-bootstrap')
        ->postJson('/api/v1/game/bootstrap');
    $bootstrap->assertStatus(201);

    return [
        'token' => $token,
        'worldId' => (string) $bootstrap->json('data.world.id'),
        'cityId' => (string) $bootstrap->json('data.city.id'),
    ];
}

it('stamps the injected clock, in UTC', function (): void {
    freezeClock('2026-09-08T12:34:56+00:00');
    config(['game.time_scale' => 1]);

    $ctx = enterCityForTimers('timers-utc');

    $upgrade = test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'timers-utc-farm')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade');
    $upgrade->assertStatus(201);

    $order = ConstructionOrder::query()->where('city_id', $ctx['cityId'])->firstOrFail();

    // farm level 2 is 20s in the catalogue, at time_scale 1.
    expect($order->started_at?->toDateTimeImmutable()->format(DATE_ATOM))->toBe('2026-09-08T12:34:56+00:00')
        ->and($order->finishes_at?->toDateTimeImmutable()->format(DATE_ATOM))->toBe('2026-09-08T12:35:16+00:00');

    // The client is never handed a zone-ambiguous timestamp.
    $upgrade->assertJsonPath('data.construction.started_at', '2026-09-08T12:34:56+00:00')
        ->assertJsonPath('data.construction.finishes_at', '2026-09-08T12:35:16+00:00');
});

it('ignores every timestamp, duration and cost the client tries to dictate', function (): void {
    freezeClock('2026-09-08T12:34:56+00:00');
    config(['game.time_scale' => 1]);

    $ctx = enterCityForTimers('timers-client');

    $before = City::query()->whereKey($ctx['cityId'])->firstOrFail();

    $upgrade = test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'timers-client-farm')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade', [
            'started_at' => '1999-01-01T00:00:00+00:00',
            'finishes_at' => '1999-01-01T00:00:01+00:00',
            'build_time_seconds' => 0,
            'cost' => ['wood' => 0, 'stone' => 0],
            'target_level' => 99,
        ]);
    $upgrade->assertStatus(201);

    $order = ConstructionOrder::query()->where('city_id', $ctx['cityId'])->firstOrFail();

    // Exactly what the server would have computed with no body at all.
    expect($order->started_at?->toDateTimeImmutable()->format(DATE_ATOM))->toBe('2026-09-08T12:34:56+00:00')
        ->and($order->finishes_at?->toDateTimeImmutable()->format(DATE_ATOM))->toBe('2026-09-08T12:35:16+00:00')
        ->and($order->target_level)->toBe(2);

    // The catalogue's cost was debited, not the client's zeroes.
    $after = City::query()->whereKey($ctx['cityId'])->firstOrFail();
    expect($before->wood - $after->wood)->toBe(120)
        ->and($before->stone - $after->stone)->toBe(60);
});

it('writes one debit set and one order per command', function (): void {
    freezeClock('2026-09-08T12:34:56+00:00');

    $ctx = enterCityForTimers('timers-atomic');

    test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'timers-atomic-farm')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade')
        ->assertStatus(201);

    $ledgerRows = EconomyLedger::query()
        ->where('reason', 'building.upgrade')
        ->where('reference', 'timers-atomic-farm')
        ->get();

    // farm level 2 costs wood 120 and stone 60 — two non-zero resources, so two
    // rows, each carrying the command's own key as its reference.
    expect($ledgerRows)->toHaveCount(2)
        ->and($ledgerRows->pluck('resource')->sort()->values()->all())->toBe(['stone', 'wood']);

    // One command, one debit set, one order.
    expect(
        ConstructionOrder::query()
            ->where('city_id', $ctx['cityId'])
            ->where('idempotency_key', 'timers-atomic-farm')
            ->count(),
    )->toBe(1);
});
