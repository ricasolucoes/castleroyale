<?php

declare(strict_types=1);

use Game\City\Infrastructure\City;
use Game\Shared\Application\Error\ErrorCode;

it('lists available worlds and creates a named player with a starter city', function (): void {
    freezeClock('2026-08-28T12:00:00+00:00');

    $guest = $this->withHeader('Idempotency-Key', 'onboarding-guest-001')
        ->postJson('/api/v1/auth/guest');
    $auth = $this->withToken($guest->json('data.access_token'));

    $worlds = $auth->getJson('/api/v1/game/worlds');
    $worlds->assertOk()
        ->assertJsonPath('data.worlds.0.code', 'aurora')
        ->assertJsonPath('data.worlds.0.status', 'open')
        ->assertJsonPath('data.worlds.0.has_player', false);

    $worldId = $worlds->json('data.worlds.0.id');
    $bootstrap = $auth
        ->withHeader('Idempotency-Key', 'onboarding-bootstrap-001')
        ->postJson('/api/v1/game/bootstrap', ['world_id' => $worldId, 'name' => 'Aurelian']);

    $bootstrap->assertCreated()
        ->assertJsonPath('data.player.name', 'Aurelian')
        ->assertJsonPath('data.world.id', $worldId)
        ->assertJsonPath('data.city.player_id', $bootstrap->json('data.player.id'))
        ->assertJsonStructure(['data' => ['versions', 'realtime']])
        ->assertJsonMissingPath('data.realtime.secret');

    expect(City::query()->where('world_id', $worldId)->where('player_id', $bootstrap->json('data.player.id'))->exists())->toBeTrue();
});

it('returns conflict for a second onboarding attempt in the same world', function (): void {
    freezeClock();

    $guest = $this->withHeader('Idempotency-Key', 'onboarding-conflict-guest')
        ->postJson('/api/v1/auth/guest');
    $auth = $this->withToken($guest->json('data.access_token'));
    $worldId = $auth->getJson('/api/v1/game/worlds')->json('data.worlds.0.id');

    $auth->withHeader('Idempotency-Key', 'onboarding-conflict-first')
        ->postJson('/api/v1/game/bootstrap', ['world_id' => $worldId, 'name' => 'First Governor'])
        ->assertCreated();

    expect($auth
        ->withHeader('Idempotency-Key', 'onboarding-conflict-second')
        ->postJson('/api/v1/game/bootstrap', ['world_id' => $worldId, 'name' => 'Second Governor']))
        ->toBeApiError(ErrorCode::Conflict);
});

it('rejects denied names', function (): void {
    freezeClock();

    $guest = $this->withHeader('Idempotency-Key', 'onboarding-rules-guest')
        ->postJson('/api/v1/auth/guest');
    $authToken = $guest->json('data.access_token');
    $worldId = $this->withToken($authToken)->getJson('/api/v1/game/worlds')->json('data.worlds.0.id');

    expect($this->withToken($authToken)
        ->withHeader('Idempotency-Key', 'onboarding-rules-denied')
        ->postJson('/api/v1/game/bootstrap', ['world_id' => $worldId, 'name' => 'admin']))
        ->toBeApiError(ErrorCode::ContentRejected);

});
