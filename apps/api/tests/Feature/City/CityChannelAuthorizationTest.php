<?php

declare(strict_types=1);

use Game\City\Infrastructure\City;
use Game\Identity\Domain\Account;
use Game\Player\Infrastructure\Player;
use Game\World\Infrastructure\World;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;

function cityChannelWorld(string $code): World
{
    return World::create([
        'code' => $code,
        'name' => $code,
        'population' => 1,
        'capacity' => 10,
        'spawn_index' => 1,
        'is_open' => true,
    ]);
}

it('allows the owning account onto its private city channel', function (): void {
    $world = cityChannelWorld('city-channel-allow');
    $owner = Account::factory()->create();
    $player = Player::create([
        'world_id' => $world->getKey(),
        'account_id' => $owner->getKey(),
        'name' => 'Channel Owner',
    ]);
    $city = City::create([
        'world_id' => $world->getKey(),
        'player_id' => $player->getKey(),
        'name_key' => 'city.starter_name',
        'x' => 0,
        'y' => 0,
        'last_accrued_at' => now(),
    ]);

    $callback = Broadcast::getChannels()->get('city.{cityId}');

    expect($callback)->toBeCallable()
        ->and($callback($owner, (string) $city->getKey()))->toBeTrue();
});

it('denies a rival account on another player\'s city channel', function (): void {
    $world = cityChannelWorld('city-channel-rival');
    $owner = Account::factory()->create();
    $rival = Account::factory()->create();
    $player = Player::create([
        'world_id' => $world->getKey(),
        'account_id' => $owner->getKey(),
        'name' => 'Channel Owner',
    ]);
    $city = City::create([
        'world_id' => $world->getKey(),
        'player_id' => $player->getKey(),
        'name_key' => 'city.starter_name',
        'x' => 1,
        'y' => 1,
        'last_accrued_at' => now(),
    ]);

    $callback = Broadcast::getChannels()->get('city.{cityId}');

    expect($callback($rival, (string) $city->getKey()))->toBeFalse();
});

it('denies an unknown city id without leaking whether it exists', function (): void {
    $account = Account::factory()->create();
    $unknownCityId = (string) Str::ulid();

    $callback = Broadcast::getChannels()->get('city.{cityId}');

    expect($callback($account, $unknownCityId))->toBeFalse();
});

it('denies when the city\'s own owning player belongs to a different world than the city record claims', function (): void {
    // The `whereColumn('cities.world_id', 'players.world_id')` clause is the
    // one nobody exercises until it leaks: the ownership join alone
    // (`players.account_id = account`) already denies a rival owned by a
    // *different* player (see the previous test), so that path can never
    // isolate this clause. To actually prove it, the SAME player must own
    // the city, but the city row's own `world_id` must have drifted from its
    // owning player's `world_id` -- exactly the data-integrity gap the
    // clause exists to catch.
    $worldA = cityChannelWorld('city-channel-world-a');
    $worldB = cityChannelWorld('city-channel-world-b');

    $account = Account::factory()->create();
    $player = Player::create([
        'world_id' => $worldA->getKey(),
        'account_id' => $account->getKey(),
        'name' => 'Drifted Owner',
    ]);
    // The city is owned by $player (so the ownership join matches) but its
    // world_id disagrees with the owning player's own world_id.
    $driftedCity = City::create([
        'world_id' => $worldB->getKey(),
        'player_id' => $player->getKey(),
        'name_key' => 'city.starter_name',
        'x' => 3,
        'y' => 3,
        'last_accrued_at' => now(),
    ]);

    $callback = Broadcast::getChannels()->get('city.{cityId}');

    expect($callback($account, (string) $driftedCity->getKey()))->toBeFalse();
});
