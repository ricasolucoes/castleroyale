<?php

declare(strict_types=1);

use Game\City\Interface\Broadcasting\CityStateChanged;
use Game\Identity\Domain\Account;
use Game\Player\Application\GameBootstrapService;
use Game\Player\Infrastructure\Player;
use Game\Technology\Application\ResearchCompletionService;
use Game\Technology\Infrastructure\PlayerTechnology;
use Game\Technology\Infrastructure\ResearchOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * No research *start* command exists yet (that is 10-05), so these tests
 * build the ResearchOrder row directly rather than driving it through HTTP —
 * exactly what the read-path trap in this plan's context warns about: never
 * touch an HTTP endpoint between advancing the clock and the assertion under
 * test, or a service that completes overdue work on the read path finishes
 * the research before the assertion runs, and the test passes while proving
 * nothing.
 */
it('completes once when the service is called twice, guarded on completed_at', function (): void {
    $clock = freezeClock('2026-09-08T10:00:00+00:00');
    Event::fake([CityStateChanged::class]);

    $account = Account::factory()->create();
    $bootstrap = app(GameBootstrapService::class)->handle($account);
    $player = Player::query()->whereKey($bootstrap['player']['id'])->firstOrFail();

    $order = ResearchOrder::create([
        'world_id' => $bootstrap['world']['id'],
        'player_id' => $bootstrap['player']['id'],
        'city_id' => $bootstrap['city']['id'],
        'technology_code' => 'agriculture',
        'from_level' => 0,
        'target_level' => 1,
        'idempotency_key' => (string) Str::ulid(),
        'started_at' => $clock->now(),
        'finishes_at' => $clock->now(),
    ]);

    $service = app(ResearchCompletionService::class);
    $service->completeOverdueLocked($player, $clock->now());

    $completedAt = ResearchOrder::query()->whereKey($order->getKey())->value('completed_at');
    expect($completedAt)->not->toBeNull();

    // The load-bearing call: re-invoking with the same "now" must not raise the
    // level twice or re-broadcast. A missing whereNull('completed_at') guard is
    // invisible in the row data here (re-applying target_level and re-stamping
    // completed_at from a frozen clock both write the same value) — it shows up
    // only as a second broadcast.
    $service->completeOverdueLocked($player, $clock->now());

    $technology = PlayerTechnology::query()
        ->where('world_id', $bootstrap['world']['id'])
        ->where('player_id', $bootstrap['player']['id'])
        ->where('technology_code', 'agriculture')
        ->firstOrFail();

    expect($technology->level)->toBe(1)
        ->and(ResearchOrder::query()->whereKey($order->getKey())->value('completed_at'))->toEqual($completedAt);

    Event::assertDispatchedTimes(CityStateChanged::class, 1);
});

it('does nothing for another player in the same world', function (): void {
    $clock = freezeClock('2026-09-08T10:00:00+00:00');
    Event::fake([CityStateChanged::class]);

    $account = Account::factory()->create();
    $bootstrap = app(GameBootstrapService::class)->handle($account);

    $otherAccount = Account::factory()->create();
    $otherBootstrap = app(GameBootstrapService::class)->handle($otherAccount, $bootstrap['world']['id']);
    $otherPlayer = Player::query()->whereKey($otherBootstrap['player']['id'])->firstOrFail();

    ResearchOrder::create([
        'world_id' => $bootstrap['world']['id'],
        'player_id' => $bootstrap['player']['id'],
        'city_id' => $bootstrap['city']['id'],
        'technology_code' => 'agriculture',
        'from_level' => 0,
        'target_level' => 1,
        'idempotency_key' => (string) Str::ulid(),
        'started_at' => $clock->now(),
        'finishes_at' => $clock->now(),
    ]);

    // Every research query filters world_id and player_id — completing on
    // behalf of a different player in the same world must find nothing.
    app(ResearchCompletionService::class)->completeOverdueLocked($otherPlayer, $clock->now());

    expect(PlayerTechnology::query()->where('player_id', $bootstrap['player']['id'])->count())->toBe(0);
    Event::assertNotDispatched(CityStateChanged::class);
});
