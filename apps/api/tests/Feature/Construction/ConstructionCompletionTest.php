<?php

declare(strict_types=1);

use Game\City\Infrastructure\City;
use Game\City\Infrastructure\CityBuilding;
use Game\City\Interface\Broadcasting\CityStateChanged;
use Game\Construction\Application\ConstructionCompletionService;
use Game\Construction\Infrastructure\ConstructionOrder;
use Game\Construction\Interface\Jobs\CompleteConstruction;
use Game\Economy\Infrastructure\EconomyLedger;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * Guest, bootstrap and start a farm upgrade over HTTP, so the whole request
 * pipeline produces the order these tests then act on.
 *
 * Construction-specific, so it lives here rather than in tests/Pest.php.
 *
 * @return array{tokens: array<string, string>, worldId: string, cityId: string}
 */
function startFarmUpgrade(string $keyPrefix): array
{
    $guest = test()->withHeader('Idempotency-Key', $keyPrefix.'-guest')
        ->postJson('/api/v1/auth/guest');
    $guest->assertStatus(201);

    /** @var array<string, string> $tokens */
    $tokens = $guest->json('data');

    $bootstrap = test()->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', $keyPrefix.'-bootstrap')
        ->postJson('/api/v1/game/bootstrap');
    $bootstrap->assertStatus(201);

    $upgrade = test()->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', $keyPrefix.'-upgrade')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade');
    $upgrade->assertStatus(201);

    return [
        'tokens' => $tokens,
        'worldId' => (string) $bootstrap->json('data.world.id'),
        'cityId' => (string) $bootstrap->json('data.city.id'),
    ];
}

it('completes the upgrade once when the job runs twice', function (): void {
    $clock = freezeClock('2026-09-08T10:00:00+00:00');
    // start() only dispatches when the queue is not sync, and phpunit.xml forces
    // sync. Switching the connection is what makes the job path reachable at all.
    config(['queue.default' => 'redis']);
    Queue::fake();
    Event::fake([CityStateChanged::class]);

    $ctx = startFarmUpgrade('completion-idem');
    Queue::assertPushed(CompleteConstruction::class);

    $order = ConstructionOrder::query()->where('city_id', $ctx['cityId'])->firstOrFail();

    // farm level 2 is 20s in the catalogue.
    $clock->advanceSeconds(30);

    // Deliberately no HTTP call between here and the assertions: GET /game/city
    // and POST .../upgrade both complete overdue orders on the read path, which
    // would finish the order before the job could and make this test pass while
    // proving nothing.
    $job = new CompleteConstruction($ctx['worldId'], $ctx['cityId'], (string) $order->getKey());

    app()->call([$job, 'handle']);
    $firstCompletedAt = ConstructionOrder::query()->whereKey($order->getKey())->value('completed_at');
    expect($firstCompletedAt)->not->toBeNull();

    app()->call([$job, 'handle']);

    $farm = CityBuilding::query()
        ->where('city_id', $ctx['cityId'])
        ->where('building_code', 'farm')
        ->firstOrFail();

    expect($farm->level)->toBe(2)
        ->and(ConstructionOrder::query()->whereKey($order->getKey())->value('completed_at'))
        ->toEqual($firstCompletedAt);

    Event::assertDispatchedTimes(CityStateChanged::class, 1);

    // Completion grants the level; the debit happened once, at start(). Farm
    // level 2 costs wood 120 and stone 60 — two non-zero resources, two rows.
    expect(EconomyLedger::query()->where('reason', 'building.upgrade')->count())->toBe(2);
});

it('completes once when the service itself is called twice', function (): void {
    // Test 1 above exercises the JOB, which short-circuits on its own
    // `$orderExists ... whereNull('completed_at')` guard and therefore never
    // reaches the service a second time. That leaves
    // ConstructionCompletionService's own `whereNull('completed_at')` — the
    // guard the reconciler depends on, since it calls the service directly —
    // unproven. This test closes that gap by calling the service twice.
    $clock = freezeClock('2026-09-08T10:00:00+00:00');
    Event::fake([CityStateChanged::class]);

    $ctx = startFarmUpgrade('completion-service-idem');
    $clock->advanceSeconds(30);

    $city = City::query()
        ->where('world_id', $ctx['worldId'])
        ->whereKey($ctx['cityId'])
        ->firstOrFail();

    $service = app(ConstructionCompletionService::class);
    $service->completeOverdueLocked($city, $clock->now());
    $completedAt = ConstructionOrder::query()->where('city_id', $ctx['cityId'])->value('completed_at');

    $service->completeOverdueLocked($city, $clock->now());

    $farm = CityBuilding::query()
        ->where('city_id', $ctx['cityId'])
        ->where('building_code', 'farm')
        ->firstOrFail();

    expect($farm->level)->toBe(2)
        ->and(ConstructionOrder::query()->where('city_id', $ctx['cityId'])->value('completed_at'))
        ->toEqual($completedAt);

    // The load-bearing assertion. Re-applying `level = target_level` is
    // idempotent in value and re-stamping `completed_at` from a frozen clock
    // writes the same instant, so a missing guard is invisible in the row data —
    // it shows up only as a second broadcast telling every client the city
    // changed when it did not.
    Event::assertDispatchedTimes(CityStateChanged::class, 1);
});

it('is a no-op for an order that no longer exists', function (): void {
    $clock = freezeClock('2026-09-08T10:00:00+00:00');
    config(['queue.default' => 'redis']);
    Queue::fake();

    $ctx = startFarmUpgrade('completion-missing');
    $order = ConstructionOrder::query()->where('city_id', $ctx['cityId'])->firstOrFail();
    $orderId = (string) $order->getKey();

    ConstructionOrder::query()->whereKey($order->getKey())->delete();
    $clock->advanceSeconds(30);

    $job = new CompleteConstruction($ctx['worldId'], $ctx['cityId'], $orderId);
    app()->call([$job, 'handle']);

    $farm = CityBuilding::query()
        ->where('city_id', $ctx['cityId'])
        ->where('building_code', 'farm')
        ->firstOrFail();

    expect($farm->level)->toBe(1);
});

it('does nothing for a city addressed in another world', function (): void {
    $clock = freezeClock('2026-09-08T10:00:00+00:00');
    config(['queue.default' => 'redis']);
    Queue::fake();

    $ctx = startFarmUpgrade('completion-crossworld');
    $order = ConstructionOrder::query()->where('city_id', $ctx['cityId'])->firstOrFail();

    $clock->advanceSeconds(30);

    // The city id is real; the world id is not. Every construction query is
    // scoped by both, so a job that names the wrong world must find nothing
    // rather than reaching across the tenant boundary.
    $job = new CompleteConstruction((string) Str::ulid(), $ctx['cityId'], (string) $order->getKey());
    app()->call([$job, 'handle']);

    $farm = CityBuilding::query()
        ->where('city_id', $ctx['cityId'])
        ->where('building_code', 'farm')
        ->firstOrFail();

    expect($farm->level)->toBe(1)
        ->and(ConstructionOrder::query()->whereKey($order->getKey())->value('completed_at'))->toBeNull();
});
