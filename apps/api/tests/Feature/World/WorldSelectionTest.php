<?php

declare(strict_types=1);

use Game\Player\Infrastructure\Player;
use Game\World\Infrastructure\World;
use Laravel\Sanctum\PersonalAccessToken;

function worldTestTokens(string $suffix): array
{
    return test()->withHeader('Idempotency-Key', 'world-guest-'.$suffix)
        ->postJson('/api/v1/auth/guest')
        ->assertCreated()
        ->json('data');
}

function makeWorld(string $code, int $capacity, int $population = 0, bool $isOpen = true): World
{
    return World::create([
        'code' => $code,
        'name' => ucfirst($code),
        'capacity' => $capacity,
        'population' => $population,
        'is_open' => $isOpen,
    ]);
}

it('lists world population, capacity, status, and account membership', function (): void {
    $tokens = worldTestTokens('list');
    $open = makeWorld('open-world', 10);
    $full = makeWorld('full-world', 2, 2);
    $closed = makeWorld('closed-world', 10, 4, false);
    $this->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'world-list-membership-001')
        ->postJson('/api/v1/game/worlds/'.$open->getKey().'/select', ['name' => 'Existing Governor'])
        ->assertCreated();

    $response = $this->withToken($tokens['access_token'])->getJson('/api/v1/game/worlds');

    $worlds = collect($response->json('data.worlds'))->keyBy('code');
    expect($worlds['open-world']['status'])->toBe('open')
        ->and($worlds['open-world']['has_player'])->toBeTrue()
        ->and($worlds['full-world']['status'])->toBe('full')
        ->and($worlds['closed-world']['status'])->toBe('closed');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'worlds' => [['id', 'code', 'name', 'population', 'capacity', 'status', 'has_player']],
            ],
        ]);
});

it('joins an open world through the real selection route and creates starter state', function (): void {
    $tokens = worldTestTokens('open');
    $world = makeWorld('selected-world', 10);

    $response = $this->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'world-select-001')
        ->postJson('/api/v1/game/worlds/'.$world->getKey().'/select', ['name' => 'Selected Governor']);

    $response->assertCreated()
        ->assertJsonPath('data.player.name', 'Selected Governor')
        ->assertJsonPath('data.world.id', $world->getKey())
        ->assertJsonStructure(['data' => ['city' => ['id']]]);

    expect($world->fresh()->population)->toBe(1);
});

it('returns the named world errors from selection', function (): void {
    $tokens = worldTestTokens('errors');
    $full = makeWorld('full-selection', 1, 1);
    $closed = makeWorld('closed-selection', 1, 0, false);

    $this->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'world-full-001')
        ->postJson('/api/v1/game/worlds/'.$full->getKey().'/select', ['name' => 'Governor One'])
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'WORLD_FULL');

    $this->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'world-closed-001')
        ->postJson('/api/v1/game/worlds/'.$closed->getKey().'/select', ['name' => 'Governor Two'])
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'WORLD_CLOSED');
});

it('does not exceed capacity when two accounts claim the last slot', function (): void {
    $firstTokens = worldTestTokens('capacity-one');
    $secondTokens = worldTestTokens('capacity-two');
    expect($firstTokens['access_token'])->not->toBe($secondTokens['access_token']);
    expect(PersonalAccessToken::findToken($firstTokens['access_token'])->tokenable_id)
        ->not->toBe(PersonalAccessToken::findToken($secondTokens['access_token'])->tokenable_id);
    $world = makeWorld('capacity-world', 1);

    $first = $this->postJson(
        '/api/v1/game/worlds/'.$world->getKey().'/select',
        ['name' => 'First Governor'],
        [
            'Authorization' => 'Bearer '.$firstTokens['access_token'],
            'Idempotency-Key' => 'world-capacity-001',
        ],
    );
    $second = $this->postJson(
        '/api/v1/game/worlds/'.$world->getKey().'/select',
        ['name' => 'Second Governor'],
        [
            'Authorization' => 'Bearer '.$secondTokens['access_token'],
            'Idempotency-Key' => 'world-capacity-002',
        ],
    );

    $first->assertCreated();
    expect(Player::query()->where('world_id', $world->getKey())->value('account_id'))
        ->toBe(PersonalAccessToken::findToken($firstTokens['access_token'])->tokenable_id);
    $second->assertStatus(400)->assertJsonPath('error.code', 'WORLD_FULL');
    expect($world->fresh()->population)->toBe(1);
});

it('replays world selection idempotently without increasing population twice', function (): void {
    $tokens = worldTestTokens('replay');
    $world = makeWorld('replay-world', 10);
    $request = fn () => $this->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'world-replay-001')
        ->postJson('/api/v1/game/worlds/'.$world->getKey().'/select', ['name' => 'Replay Governor']);

    $first = $request();
    $second = $request();

    $first->assertCreated();
    expect($second->json())->toEqual($first->json())
        ->and($world->fresh()->population)->toBe(1);
});
