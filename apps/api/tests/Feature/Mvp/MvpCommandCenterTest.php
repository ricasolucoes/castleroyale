<?php

declare(strict_types=1);

use Game\Military\Infrastructure\CityUnit;

it('serves a bounded world view and a server-owned starting garrison', function (): void {
    freezeClock('2026-08-27T18:00:00+00:00');

    $guest = $this->withHeader('Idempotency-Key', 'command-center-guest-001')
        ->postJson('/api/v1/auth/guest');
    $tokens = $guest->json('data');

    $this->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'command-center-bootstrap-001')
        ->postJson('/api/v1/game/bootstrap')
        ->assertCreated();

    $world = $this->withToken($tokens['access_token'])->getJson('/api/v1/game/world');
    $world->assertOk()
        ->assertJsonPath('data.world.code', 'aurora')
        ->assertJsonPath('data.center.x', 0)
        ->assertJsonPath('data.center.y', 0)
        ->assertJsonPath('data.cities.0.is_player_city', true);

    $military = $this->withToken($tokens['access_token'])->getJson('/api/v1/game/military');
    $military->assertOk()
        ->assertJsonPath('data.units.0.code', 'militia')
        ->assertJsonPath('data.units.0.quantity', 20)
        ->assertJsonPath('data.total_power', 400);

    expect(CityUnit::query()->count())->toBe(1);
});

it('lets a player found an alliance and replays that mutation idempotently', function (): void {
    freezeClock('2026-08-27T18:00:00+00:00');

    $guest = $this->withHeader('Idempotency-Key', 'alliance-guest-001')
        ->postJson('/api/v1/auth/guest');
    $tokens = $guest->json('data');
    $auth = $this->withToken($tokens['access_token']);

    $before = $auth->getJson('/api/v1/game/alliance');
    $before->assertOk()
        ->assertJsonPath('data.alliance', null)
        ->assertJsonPath('data.can_create', true);

    $create = $auth
        ->withHeader('Idempotency-Key', 'alliance-create-001')
        ->postJson('/api/v1/game/alliance', ['name' => 'Aurora Guard', 'tag' => 'AUR']);
    $create->assertCreated()
        ->assertJsonPath('data.alliance.name', 'Aurora Guard')
        ->assertJsonPath('data.alliance.tag', 'AUR')
        ->assertJsonPath('data.alliance.role', 'leader')
        ->assertJsonPath('data.alliance.member_count', 1)
        ->assertJsonPath('data.members.0.role', 'leader');

    $replayed = $auth
        ->withHeader('Idempotency-Key', 'alliance-create-001')
        ->postJson('/api/v1/game/alliance', ['name' => 'Aurora Guard', 'tag' => 'AUR']);
    expect($replayed->json())->toEqual($create->json());

    $after = $auth->getJson('/api/v1/game/alliance');
    $after->assertOk()->assertJsonPath('data.can_create', false);

    $auth
        ->withHeader('Idempotency-Key', 'alliance-create-002')
        ->postJson('/api/v1/game/alliance', ['name' => 'Another Guard', 'tag' => 'AUR'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'ALREADY_IN_ALLIANCE');
});
