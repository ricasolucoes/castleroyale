<?php

declare(strict_types=1);

use Game\Player\Infrastructure\Player;
use Game\World\Infrastructure\World;

function playerTokens(): array
{
    return test()->withHeader('Idempotency-Key', 'player-guest-'.uniqid())
        ->postJson('/api/v1/auth/guest')
        ->assertCreated()
        ->json('data');
}

function createPlayer(array $tokens, string $worldId, string $name, string $key): Illuminate\Testing\TestResponse
{
    return test()->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/game/bootstrap', ['world_id' => $worldId, 'name' => $name]);
}

it('creates one named player and its starter city in the selected world', function (): void {
    freezeClock('2026-08-28T12:00:00+00:00');
    $tokens = playerTokens();

    $response = createPlayer($tokens, World::create([
        'code' => 'alpha',
        'name' => 'Alpha',
        'capacity' => 10,
        'is_open' => true,
    ])->getKey(), 'Aurelian', 'player-create-001');

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['player', 'world', 'city', 'versions', 'realtime']])
        ->assertJsonPath('data.player.name', 'Aurelian')
        ->assertJsonPath('data.world.code', 'alpha');

    expect(Player::query()->where('world_id', $response->json('data.world.id'))->count())->toBe(1);
});

it('returns conflict when an account tries to create a second player in one world', function (): void {
    $tokens = playerTokens();
    $world = World::create(['code' => 'second-attempt', 'name' => 'Second Attempt', 'capacity' => 10, 'is_open' => true]);

    createPlayer($tokens, $world->getKey(), 'First Governor', 'player-create-002')->assertCreated();

    createPlayer($tokens, $world->getKey(), 'Second Governor', 'player-create-003')
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'CONFLICT');
});

it('keeps player names unique inside a world but allows them in another world', function (): void {
    $firstTokens = playerTokens();
    $secondTokens = playerTokens();
    $firstWorld = World::create(['code' => 'names-one', 'name' => 'Names One', 'capacity' => 10, 'is_open' => true]);
    $secondWorld = World::create(['code' => 'names-two', 'name' => 'Names Two', 'capacity' => 10, 'is_open' => true]);

    createPlayer($firstTokens, $firstWorld->getKey(), 'Shared Name', 'player-name-001')->assertCreated();
    createPlayer($secondTokens, $firstWorld->getKey(), 'Shared Name', 'player-name-002')
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'CONFLICT');
    createPlayer($secondTokens, $secondWorld->getKey(), 'Shared Name', 'player-name-003')->assertCreated();
});

it('rejects invalid and denied player names with content rejected', function (string $name): void {
    $tokens = playerTokens();
    $world = World::create(['code' => 'screening-'.strtolower(substr(md5($name), 0, 6)), 'name' => 'Screening', 'capacity' => 10, 'is_open' => true]);

    createPlayer($tokens, $world->getKey(), $name, 'player-screen-'.substr(md5($name), 0, 18))
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'CONTENT_REJECTED');
})->with(['ad', 'admin', 'bad/name', '  ']);

it('normalizes unicode whitespace and replays bootstrap idempotently', function (): void {
    freezeClock('2026-08-28T12:00:00+00:00');
    $tokens = playerTokens();
    $world = World::create(['code' => 'unicode', 'name' => 'Unicode', 'capacity' => 10, 'is_open' => true]);

    $first = createPlayer($tokens, $world->getKey(), "  Élan\tKeeper  ", 'player-replay-001');
    $second = createPlayer($tokens, $world->getKey(), "  Élan\tKeeper  ", 'player-replay-001');

    $first->assertCreated();
    expect($second->json())->toEqual($first->json())
        ->and(Player::query()->where('world_id', $world->getKey())->count())->toBe(1);
});
