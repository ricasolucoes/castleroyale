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
 * Renamed from ResearchCompletionServiceTest.php (10-03) to
 * ResearchScopeTest.php: `--filter=ResearchCompletion` (10-05's own
 * acceptance criterion, expecting exactly 4 passing) is a substring match on
 * the fully-qualified test class name, and "ResearchCompletionServiceTest"
 * contains "ResearchCompletion" — colliding with the new
 * ResearchCompletionTest.php this plan adds. Its "calls the service twice"
 * test is dropped as a duplicate: 10-05's ResearchCompletionTest.php proves
 * the exact same guard, driven through the real HTTP research command rather
 * than a hand-built ResearchOrder row. The world/player scoping test below is
 * unique and kept, renamed to a file with no colliding prefix.
 */
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
