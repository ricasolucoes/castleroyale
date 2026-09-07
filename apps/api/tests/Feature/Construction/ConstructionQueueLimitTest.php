<?php

declare(strict_types=1);

use Game\City\Infrastructure\City;
use Game\City\Infrastructure\CityBuilding;
use Game\Construction\Infrastructure\ConstructionOrder;
use Game\Economy\Infrastructure\EconomyLedger;
use Game\Shared\Application\Error\ErrorCode;

/**
 * Guest + bootstrap, returning the tokens and ids every test here needs.
 *
 * Named distinctly from the helpers in the sibling construction test files —
 * PHPUnit loads them all into one process.
 *
 * @return array{token: string, worldId: string, cityId: string}
 */
function enterCityForQueueLimit(string $keyPrefix): array
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

it('refuses a fifth concurrent order with BUILD_QUEUE_FULL', function (): void {
    // Frozen so nothing completes mid-test and frees a slot.
    freezeClock('2026-09-08T13:00:00+00:00');

    $ctx = enterCityForQueueLimit('queue-full');

    // The four starter non-Palace buildings fill the queue, and the Palace is
    // already at its max level — so a fifth distinct upgradeable target has to
    // be seeded. Using barracks doubles as end-to-end proof that a building
    // added to the catalogue by 09-01, never present in a starter city, works.
    CityBuilding::create([
        'world_id' => $ctx['worldId'],
        'city_id' => $ctx['cityId'],
        'slot' => 'plot_06',
        'building_code' => 'barracks',
        'level' => 1,
    ]);

    // Combined cost food 180, wood 400, stone 320 against 500/500/500.
    foreach (['farm', 'lumber_mill', 'quarry', 'warehouse'] as $code) {
        test()->withToken($ctx['token'])
            ->withHeader('Idempotency-Key', 'queue-full-'.$code)
            ->postJson('/api/v1/game/city/buildings/'.$code.'/upgrade')
            ->assertStatus(201);
    }

    $ledgerCountBefore = EconomyLedger::query()->count();
    $balancesBefore = City::query()->whereKey($ctx['cityId'])->firstOrFail()
        ->only(['food', 'wood', 'stone', 'iron', 'gold']);

    // The barracks is ALSO unaffordable here (wood 100 left against a 200 cost),
    // but the queue check deliberately runs before the affordability check, so
    // the player is told the real reason they cannot start it.
    $fifth = test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'queue-full-barracks')
        ->postJson('/api/v1/game/city/buildings/barracks/upgrade');

    expect($fifth)->toBeApiError(ErrorCode::BuildQueueFull);
    $fifth->assertStatus(400);

    expect(ConstructionOrder::query()->where('city_id', $ctx['cityId'])->count())->toBe(4)
        ->and(EconomyLedger::query()->count())->toBe($ledgerCountBefore)
        ->and(City::query()->whereKey($ctx['cityId'])->firstOrFail()->only(['food', 'wood', 'stone', 'iron', 'gold']))
        ->toBe($balancesBefore);
});

it('reads the queue ceiling from config', function (): void {
    freezeClock('2026-09-08T13:00:00+00:00');
    config(['game.limits.max_build_queue_slots' => 2]);

    $ctx = enterCityForQueueLimit('queue-config');

    foreach (['farm', 'lumber_mill'] as $code) {
        test()->withToken($ctx['token'])
            ->withHeader('Idempotency-Key', 'queue-config-'.$code)
            ->postJson('/api/v1/game/city/buildings/'.$code.'/upgrade')
            ->assertStatus(201);
    }

    // The ceiling is two now, so the third fails where it would have succeeded
    // against the default of four. The limit is configuration, not a constant.
    $third = test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'queue-config-quarry')
        ->postJson('/api/v1/game/city/buildings/quarry/upgrade');

    expect($third)->toBeApiError(ErrorCode::BuildQueueFull);
    $third->assertStatus(400);

    expect(ConstructionOrder::query()->where('city_id', $ctx['cityId'])->count())->toBe(2);
});

it('reports CITY_BUSY for a second order on the same building', function (): void {
    freezeClock('2026-09-08T13:00:00+00:00');

    $ctx = enterCityForQueueLimit('queue-busy');

    test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'queue-busy-farm-1')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade')
        ->assertStatus(201);

    // A different key, so this is a new command rather than a replay of the
    // first (Phase 08's EconomyConcurrencyTest already covers same-key replay).
    // Three queue slots are still free, so a full queue is not the reason.
    $second = test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'queue-busy-farm-2')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade');

    expect($second)->toBeApiError(ErrorCode::CityBusy);

    expect(ConstructionOrder::query()->where('city_id', $ctx['cityId'])->count())->toBe(1);
});

it('refuses an upgrade past the maximum level', function (): void {
    freezeClock('2026-09-08T13:00:00+00:00');

    $ctx = enterCityForQueueLimit('queue-max');

    // The starter Palace is level 3, which is its max_level.
    $response = test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'queue-max-palace')
        ->postJson('/api/v1/game/city/buildings/palace/upgrade');

    expect($response)->toBeApiError(ErrorCode::BuildingMaxLevel);
    $response->assertStatus(400);

    expect(ConstructionOrder::query()->where('city_id', $ctx['cityId'])->count())->toBe(0)
        ->and(EconomyLedger::query()->where('reason', 'building.upgrade')->count())->toBe(0);
});

it('refuses a catalogue building the city does not have', function (): void {
    freezeClock('2026-09-08T13:00:00+00:00');

    $ctx = enterCityForQueueLimit('queue-absent');

    // The tavern exists in the catalogue after 09-01 but not in this city.
    // The refusal must not be a 500, and must not be a 404 that leaks whether
    // a code is in the catalogue at all.
    $response = test()->withToken($ctx['token'])
        ->withHeader('Idempotency-Key', 'queue-absent-tavern')
        ->postJson('/api/v1/game/city/buildings/tavern/upgrade');

    expect($response)->toBeApiError(ErrorCode::BuildingRequirementsNotMet);

    expect(ConstructionOrder::query()->where('city_id', $ctx['cityId'])->count())->toBe(0);
});
