<?php

declare(strict_types=1);

use Game\Identity\Domain\Account;
use Game\Player\Infrastructure\Player;
use Game\World\Infrastructure\World;
use Illuminate\Support\Facades\Broadcast;

it('authorises only the owner of a private player channel', function (): void {
    $world = World::create([
        'code' => 'channel-world',
        'name' => 'Channel World',
        'population' => 1,
        'capacity' => 10,
        'spawn_index' => 1,
        'is_open' => true,
    ]);
    $owner = Account::factory()->create();
    $rival = Account::factory()->create();
    $player = Player::create([
        'world_id' => $world->getKey(),
        'account_id' => $owner->getKey(),
        'name' => 'Channel Owner',
    ]);

    $callback = Broadcast::getChannels()->get('player.{playerId}');

    expect($callback)->toBeCallable()
        ->and($callback($owner, (string) $player->getKey()))->toBeTrue()
        ->and($callback($rival, (string) $player->getKey()))->toBeFalse();
});
