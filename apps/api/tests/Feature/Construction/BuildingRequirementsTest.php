<?php

declare(strict_types=1);

use Game\City\Infrastructure\City;
use Game\City\Infrastructure\CityBuilding;
use Game\Construction\Infrastructure\ConstructionOrder;
use Game\Economy\Infrastructure\EconomyLedger;
use Game\Shared\Application\Error\ErrorCode;
use Illuminate\Support\Carbon;

/**
 * @return array{access_token: string, world_id: string, city_id: string}
 */
function bootstrapGuestCity(string $suffix): array
{
    $guest = test()->withHeader('Idempotency-Key', "brt-guest-{$suffix}")
        ->postJson('/api/v1/auth/guest');
    $token = (string) $guest->json('data.access_token');

    $bootstrap = test()->withToken($token)
        ->withHeader('Idempotency-Key', "brt-bootstrap-{$suffix}")
        ->postJson('/api/v1/game/bootstrap');

    return [
        'access_token' => $token,
        'world_id' => (string) $bootstrap->json('data.world.id'),
        'city_id' => (string) $bootstrap->json('data.city.id'),
    ];
}

/**
 * A test fixture adjusting a building's *level* directly is not a resource
 * mutation — the Phase 08 rule requiring fixtures to route through
 * debitLocked()/creditLocked() applies to balances, not building levels — so a
 * raw model write is the right tool here, not the upgrade endpoint (which
 * would itself be gated by the very requirement this fixture needs to bypass).
 */
function setBuildingLevel(string $worldId, string $cityId, string $buildingCode, int $level): void
{
    CityBuilding::query()
        ->where('world_id', $worldId)
        ->where('city_id', $cityId)
        ->where('building_code', $buildingCode)
        ->update(['level' => $level]);
}

it('refuses an upgrade past the palace level and names the palace', function (): void {
    freezeClock('2026-09-07T09:00:00+00:00');
    $ctx = bootstrapGuestCity('gate-palace-level');
    setBuildingLevel($ctx['world_id'], $ctx['city_id'], 'palace', 1);

    $response = test()->withToken($ctx['access_token'])
        ->withHeader('Idempotency-Key', 'brt-upgrade-gate-palace-level')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade');

    expect($response)->toBeApiError(ErrorCode::BuildingRequirementsNotMet);
    $response->assertJsonPath('error.details.missing.palace', 2);
});

it('allows the same upgrade once the palace is high enough', function (): void {
    freezeClock('2026-09-07T09:00:00+00:00');
    $ctx = bootstrapGuestCity('gate-palace-satisfied');
    // The starter palace is already level 3 (packages/game-data/data/starter.json)
    // — no fixture needed, this is the plain happy path.

    $response = test()->withToken($ctx['access_token'])
        ->withHeader('Idempotency-Key', 'brt-upgrade-gate-palace-satisfied')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade');

    $response->assertStatus(201);
    expect(ConstructionOrder::query()->where('world_id', $ctx['world_id'])->count())->toBe(1);
});

it('gates each level independently', function (): void {
    $clock = freezeClock('2026-09-07T09:00:00+00:00');
    $ctx = bootstrapGuestCity('gate-per-level');
    setBuildingLevel($ctx['world_id'], $ctx['city_id'], 'palace', 2);

    $first = test()->withToken($ctx['access_token'])
        ->withHeader('Idempotency-Key', 'brt-upgrade-gate-per-level-1')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade');
    $first->assertStatus(201);

    // Farm level 2's build_time_seconds is 20; advancing well past it and then
    // reading the city lets the overdue reconciler complete the order lazily
    // (QUEUE_CONNECTION=sync in tests means the delayed job was never
    // dispatched — see BuildingUpgradeService::start()).
    $clock->advanceSeconds(21);
    test()->withToken($ctx['access_token'])->getJson('/api/v1/game/city')->assertOk();

    $second = test()->withToken($ctx['access_token'])
        ->withHeader('Idempotency-Key', 'brt-upgrade-gate-per-level-2')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade');

    expect($second)->toBeApiError(ErrorCode::BuildingRequirementsNotMet);
    $second->assertJsonPath('error.details.missing.palace', 3);
});

it('spends nothing when it refuses', function (): void {
    freezeClock('2026-09-07T09:00:00+00:00');
    $ctx = bootstrapGuestCity('gate-no-side-effects');
    setBuildingLevel($ctx['world_id'], $ctx['city_id'], 'palace', 1);

    $before = City::query()->whereKey($ctx['city_id'])->firstOrFail();
    $balancesBefore = [
        'food' => (int) $before->food,
        'wood' => (int) $before->wood,
        'stone' => (int) $before->stone,
        'iron' => (int) $before->iron,
        'gold' => (int) $before->gold,
    ];
    $ledgerCountBefore = EconomyLedger::query()->where('world_id', $ctx['world_id'])->count();

    $response = test()->withToken($ctx['access_token'])
        ->withHeader('Idempotency-Key', 'brt-upgrade-gate-no-side-effects')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade');

    expect($response)->toBeApiError(ErrorCode::BuildingRequirementsNotMet);

    $after = City::query()->whereKey($ctx['city_id'])->firstOrFail();
    expect((int) $after->food)->toBe($balancesBefore['food'])
        ->and((int) $after->wood)->toBe($balancesBefore['wood'])
        ->and((int) $after->stone)->toBe($balancesBefore['stone'])
        ->and((int) $after->iron)->toBe($balancesBefore['iron'])
        ->and((int) $after->gold)->toBe($balancesBefore['gold']);

    expect(EconomyLedger::query()->where('world_id', $ctx['world_id'])->count())->toBe($ledgerCountBefore);
    expect(ConstructionOrder::query()->where('world_id', $ctx['world_id'])->count())->toBe(0);
});

it('reports BUILDING_MAX_LEVEL over an unmet requirement', function (): void {
    freezeClock('2026-09-07T09:00:00+00:00');
    $ctx = bootstrapGuestCity('gate-max-level-wins');
    setBuildingLevel($ctx['world_id'], $ctx['city_id'], 'palace', 1);
    setBuildingLevel($ctx['world_id'], $ctx['city_id'], 'farm', 3);

    $response = test()->withToken($ctx['access_token'])
        ->withHeader('Idempotency-Key', 'brt-upgrade-gate-max-level-wins')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade');

    // farm is already at max_level (3): the permanent "cannot ever be built
    // higher" answer must win over the palace gate, which would otherwise also
    // refuse this request.
    expect($response)->toBeApiError(ErrorCode::BuildingMaxLevel);
});

it('reports BUILDING_REQUIREMENTS_NOT_MET over BUILD_QUEUE_FULL', function (): void {
    freezeClock('2026-09-07T09:00:00+00:00');
    $ctx = bootstrapGuestCity('gate-requirements-before-queue');
    setBuildingLevel($ctx['world_id'], $ctx['city_id'], 'palace', 1);
    config(['game.limits.max_build_queue_slots' => 1]);

    // Created directly, not through the upgrade endpoint — a real quarry
    // upgrade would itself be refused by the same palace gate this fixture
    // needs to bypass in order to fill the queue.
    ConstructionOrder::create([
        'world_id' => $ctx['world_id'],
        'city_id' => $ctx['city_id'],
        'building_code' => 'quarry',
        'from_level' => 1,
        'target_level' => 2,
        'idempotency_key' => 'brt-fixture-quarry-busy',
        'started_at' => Carbon::now(),
        'finishes_at' => Carbon::now()->addMinutes(5),
    ]);

    $response = test()->withToken($ctx['access_token'])
        ->withHeader('Idempotency-Key', 'brt-upgrade-gate-requirements-before-queue')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade');

    // The queue is full *and* the requirement is unmet — the permanent reason
    // (you cannot build this yet, at any queue depth) must win over the
    // temporary one (the queue will clear).
    expect($response)->toBeApiError(ErrorCode::BuildingRequirementsNotMet);
});
