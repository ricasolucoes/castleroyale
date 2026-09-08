<?php

declare(strict_types=1);

use Game\City\Interface\Broadcasting\CityStateChanged;
use Game\Player\Infrastructure\Player;
use Game\Technology\Application\ResearchCompletionService;
use Game\Technology\Application\ResearchReconciler;
use Game\Technology\Infrastructure\PlayerTechnology;
use Game\Technology\Infrastructure\ResearchOrder;
use Game\Technology\Interface\Jobs\CompleteResearch;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/**
 * Guest, bootstrap and start an agriculture research over HTTP.
 *
 * Named distinctly from the helpers in the sibling Technology/Construction
 * test files — PHPUnit loads every *Test.php in one process, so a name shared
 * with a sibling file is a fatal redeclare.
 *
 * @return array{tokens: array<string, string>, worldId: string, playerId: string, cityId: string}
 */
function startAgricultureResearchForCompletion(string $keyPrefix): array
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

    test()->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', $keyPrefix.'-research')
        ->postJson('/api/v1/game/technologies/agriculture/research')
        ->assertStatus(201);

    return [
        'tokens' => $tokens,
        'worldId' => (string) $bootstrap->json('data.world.id'),
        'playerId' => (string) $bootstrap->json('data.player.id'),
        'cityId' => (string) $bootstrap->json('data.city.id'),
    ];
}

it('completes once when the job runs twice', function (): void {
    $clock = freezeClock('2026-09-08T10:00:00+00:00');
    // start() only dispatches when the queue is not sync, and phpunit.xml
    // forces sync. Switching the connection is what makes the job path
    // reachable at all.
    config(['queue.default' => 'redis']);
    Queue::fake();
    Event::fake([CityStateChanged::class]);

    $ctx = startAgricultureResearchForCompletion('completion-job');
    Queue::assertPushed(CompleteResearch::class);

    $order = ResearchOrder::query()->where('city_id', $ctx['cityId'])->firstOrFail();

    // agriculture level 1 is 30s in the catalogue.
    $clock->advanceSeconds(31);

    // Deliberately no HTTP call between here and the assertions: GET
    // /game/technologies and the research endpoints both complete overdue
    // research on the read path, which would finish it before the job could
    // and make this test pass while proving nothing.
    $job = new CompleteResearch($ctx['worldId'], $ctx['playerId'], (string) $order->getKey());

    app()->call([$job, 'handle']);
    $firstCompletedAt = ResearchOrder::query()->whereKey($order->getKey())->value('completed_at');
    expect($firstCompletedAt)->not->toBeNull();

    app()->call([$job, 'handle']);

    $technology = PlayerTechnology::query()
        ->where('world_id', $ctx['worldId'])
        ->where('player_id', $ctx['playerId'])
        ->where('technology_code', 'agriculture')
        ->firstOrFail();

    expect($technology->level)->toBe(1)
        ->and(ResearchOrder::query()->whereKey($order->getKey())->value('completed_at'))
        ->toEqual($firstCompletedAt);

    Event::assertDispatchedTimes(CityStateChanged::class, 1);
});

it('completes once when the service itself is called twice', function (): void {
    // The test above exercises the JOB, which short-circuits on its own
    // `$orderExists ... whereNull('completed_at')` guard and therefore never
    // reaches the service a second time. That leaves
    // ResearchCompletionService's own `whereNull('completed_at')` guard — the
    // one the reconciler depends on, since it calls the service directly —
    // unproven by the job test alone (09-03's finding, applied here).
    $clock = freezeClock('2026-09-08T10:00:00+00:00');
    Event::fake([CityStateChanged::class]);

    $ctx = startAgricultureResearchForCompletion('completion-service');
    $clock->advanceSeconds(31);

    $player = Player::query()->whereKey($ctx['playerId'])->firstOrFail();
    $service = app(ResearchCompletionService::class);
    $service->completeOverdueLocked($player, $clock->now());
    $completedAt = ResearchOrder::query()->where('player_id', $ctx['playerId'])->value('completed_at');
    expect($completedAt)->not->toBeNull();

    $service->completeOverdueLocked($player, $clock->now());

    $technology = PlayerTechnology::query()
        ->where('world_id', $ctx['worldId'])
        ->where('player_id', $ctx['playerId'])
        ->where('technology_code', 'agriculture')
        ->firstOrFail();

    expect($technology->level)->toBe(1)
        ->and(ResearchOrder::query()->where('player_id', $ctx['playerId'])->value('completed_at'))
        ->toEqual($completedAt);

    // The load-bearing assertion: re-applying target_level and re-stamping
    // completed_at from a frozen clock both write the same value, so a
    // missing guard is invisible in the row data — it shows up only as a
    // second broadcast.
    Event::assertDispatchedTimes(CityStateChanged::class, 1);
});

it('costs nothing when the worker dies: the reconciler finishes the research exactly once', function (): void {
    $clock = freezeClock('2026-09-08T10:00:00+00:00');
    config(['queue.default' => 'redis']);
    // Queue::fake() captures the completion job and never runs it. That is the
    // worker dying.
    Queue::fake();
    Event::fake([CityStateChanged::class]);

    $ctx = startAgricultureResearchForCompletion('completion-reconciler');
    $order = ResearchOrder::query()->where('city_id', $ctx['cityId'])->firstOrFail();

    $clock->advanceSeconds(31);

    // No HTTP call here: the read path would complete the order itself and
    // the reconciler would then correctly find nothing, proving nothing.
    expect(ResearchOrder::query()->whereKey($order->getKey())->value('completed_at'))->toBeNull();

    expect(app(ResearchReconciler::class)->run())->toBe(1);

    $technology = PlayerTechnology::query()
        ->where('world_id', $ctx['worldId'])
        ->where('player_id', $ctx['playerId'])
        ->where('technology_code', 'agriculture')
        ->firstOrFail();
    $completedAt = ResearchOrder::query()->whereKey($order->getKey())->value('completed_at');

    expect($technology->level)->toBe(1)
        ->and($completedAt)->not->toBeNull();

    // A second reconciler pass finds nothing left to do.
    expect(app(ResearchReconciler::class)->run())->toBe(0);

    expect(PlayerTechnology::query()->where('player_id', $ctx['playerId'])->where('technology_code', 'agriculture')->value('level'))->toBe(1)
        ->and(ResearchOrder::query()->whereKey($order->getKey())->value('completed_at'))->toEqual($completedAt);

    // The worker comes back from the dead and finally runs the job it was
    // holding. It must change nothing.
    $job = new CompleteResearch($ctx['worldId'], $ctx['playerId'], (string) $order->getKey());
    app()->call([$job, 'handle']);

    expect(PlayerTechnology::query()->where('player_id', $ctx['playerId'])->where('technology_code', 'agriculture')->value('level'))->toBe(1)
        ->and(ResearchOrder::query()->whereKey($order->getKey())->value('completed_at'))->toEqual($completedAt);

    // One completion across a reconciler run, a duplicate reconciler run and a
    // late job.
    Event::assertDispatchedTimes(CityStateChanged::class, 1);
});

it('leaves a research that is not due yet alone', function (): void {
    $clock = freezeClock('2026-09-08T10:00:00+00:00');
    config(['queue.default' => 'redis']);
    Queue::fake();

    $ctx = startAgricultureResearchForCompletion('completion-early');
    $order = ResearchOrder::query()->where('city_id', $ctx['cityId'])->firstOrFail();

    // agriculture level 1 takes 30s.
    $clock->advanceSeconds(5);

    expect(app(ResearchReconciler::class)->run())->toBe(0)
        ->and(ResearchOrder::query()->whereKey($order->getKey())->value('completed_at'))->toBeNull()
        ->and(PlayerTechnology::query()->where('player_id', $ctx['playerId'])->where('technology_code', 'agriculture')->exists())->toBeFalse();
});
