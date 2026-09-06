<?php

declare(strict_types=1);

use Game\City\Infrastructure\CityBuilding;
use Game\City\Interface\Broadcasting\CityStateChanged;
use Game\Construction\Application\ConstructionReconciler;
use Game\Construction\Infrastructure\ConstructionOrder;
use Game\Construction\Interface\Jobs\CompleteConstruction;
use Game\World\Infrastructure\World;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/**
 * Guest, bootstrap, and start upgrades on the named buildings.
 *
 * Declared locally under a name distinct from ConstructionCompletionTest.php's
 * `startFarmUpgrade`: PHPUnit require()s every *Test.php in one process, so two
 * top-level functions sharing a name would be a fatal "Cannot redeclare".
 *
 * @param list<string> $buildingCodes
 * @return array{tokens: array<string, string>, worldId: string, cityId: string}
 */
function startFarmUpgradeForReconciler(string $keyPrefix, array $buildingCodes = ['farm']): array
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

    foreach ($buildingCodes as $code) {
        test()->withToken($tokens['access_token'])
            ->withHeader('Idempotency-Key', $keyPrefix.'-upgrade-'.$code)
            ->postJson('/api/v1/game/city/buildings/'.$code.'/upgrade')
            ->assertStatus(201);
    }

    return [
        'tokens' => $tokens,
        'worldId' => (string) $bootstrap->json('data.world.id'),
        'cityId' => (string) $bootstrap->json('data.city.id'),
    ];
}

it('finishes an order exactly once after the worker dies', function (): void {
    $clock = freezeClock('2026-09-08T11:00:00+00:00');
    config(['queue.default' => 'redis']);
    // Queue::fake() captures the completion job and never runs it. That is the
    // worker dying.
    Queue::fake();
    Event::fake([CityStateChanged::class]);

    $ctx = startFarmUpgradeForReconciler('reconcile-single');
    $order = ConstructionOrder::query()->where('city_id', $ctx['cityId'])->firstOrFail();

    $clock->advanceSeconds(30);

    // No HTTP call here: the read path would complete the order itself and the
    // reconciler would then correctly find nothing, proving nothing.
    expect(ConstructionOrder::query()->whereKey($order->getKey())->value('completed_at'))->toBeNull();

    expect(app(ConstructionReconciler::class)->run())->toBe(1);

    $farm = CityBuilding::query()
        ->where('city_id', $ctx['cityId'])
        ->where('building_code', 'farm')
        ->firstOrFail();
    $completedAt = ConstructionOrder::query()->whereKey($order->getKey())->value('completed_at');

    expect($farm->level)->toBe(2)
        ->and($completedAt)->not->toBeNull();

    // A second reconciler pass finds nothing left to do.
    expect(app(ConstructionReconciler::class)->run())->toBe(0);

    expect(CityBuilding::query()->where('city_id', $ctx['cityId'])->where('building_code', 'farm')->value('level'))->toBe(2)
        ->and(ConstructionOrder::query()->whereKey($order->getKey())->value('completed_at'))->toEqual($completedAt);

    // The worker comes back from the dead and finally runs the job it was
    // holding. It must change nothing.
    $job = new CompleteConstruction($ctx['worldId'], $ctx['cityId'], (string) $order->getKey());
    app()->call([$job, 'handle']);

    expect(CityBuilding::query()->where('city_id', $ctx['cityId'])->where('building_code', 'farm')->value('level'))->toBe(2)
        ->and(ConstructionOrder::query()->whereKey($order->getKey())->value('completed_at'))->toEqual($completedAt);

    // One completion across a reconciler run, a duplicate reconciler run and a
    // late job.
    Event::assertDispatchedTimes(CityStateChanged::class, 1);
});

it('finishes every overdue order in the city in one run', function (): void {
    $clock = freezeClock('2026-09-08T11:00:00+00:00');
    config(['queue.default' => 'redis']);
    Queue::fake();
    Event::fake([CityStateChanged::class]);

    // farm (wood 120, stone 60), lumber_mill (food 80, stone 100) and warehouse
    // (wood 180, stone 160) together cost food 80, wood 300, stone 320 against a
    // 500/500/500 start — affordable without a grant.
    $ctx = startFarmUpgradeForReconciler('reconcile-many', ['farm', 'lumber_mill', 'warehouse']);

    expect(ConstructionOrder::query()->where('city_id', $ctx['cityId'])->count())->toBe(3);

    // The longest of the three is the warehouse at 25s.
    $clock->advanceSeconds(40);

    expect(app(ConstructionReconciler::class)->run())->toBe(3);

    foreach (['farm', 'lumber_mill', 'warehouse'] as $code) {
        expect(
            CityBuilding::query()->where('city_id', $ctx['cityId'])->where('building_code', $code)->value('level'),
        )->toBe(2);
    }

    // One event per completed order, not one per reconciler run.
    Event::assertDispatchedTimes(CityStateChanged::class, 3);
});

it('leaves an order that is not due yet alone', function (): void {
    $clock = freezeClock('2026-09-08T11:00:00+00:00');
    config(['queue.default' => 'redis']);
    Queue::fake();

    $ctx = startFarmUpgradeForReconciler('reconcile-early');
    $order = ConstructionOrder::query()->where('city_id', $ctx['cityId'])->firstOrFail();

    // farm level 2 takes 20s.
    $clock->advanceSeconds(5);

    expect(app(ConstructionReconciler::class)->run())->toBe(0)
        ->and(ConstructionOrder::query()->whereKey($order->getKey())->value('completed_at'))->toBeNull()
        ->and(CityBuilding::query()->where('city_id', $ctx['cityId'])->where('building_code', 'farm')->value('level'))->toBe(1);
});

it('ignores orders in a closed world', function (): void {
    $clock = freezeClock('2026-09-08T11:00:00+00:00');
    config(['queue.default' => 'redis']);
    Queue::fake();

    $ctx = startFarmUpgradeForReconciler('reconcile-closed');
    $order = ConstructionOrder::query()->where('city_id', $ctx['cityId'])->firstOrFail();

    $clock->advanceSeconds(30);

    World::query()->whereKey($ctx['worldId'])->update(['is_open' => false]);

    // run() scans open worlds only. This pins that as a deliberate boundary
    // rather than an accident — see the SUMMARY, which raises the consequence
    // (an order in a world closed for maintenance never completes) as a concern
    // for a later phase rather than widening the scope here.
    expect(app(ConstructionReconciler::class)->run())->toBe(0)
        ->and(ConstructionOrder::query()->whereKey($order->getKey())->value('completed_at'))->toBeNull()
        ->and(CityBuilding::query()->where('city_id', $ctx['cityId'])->where('building_code', 'farm')->value('level'))->toBe(1);
});
