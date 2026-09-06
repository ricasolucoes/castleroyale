<?php

declare(strict_types=1);

use Game\City\Infrastructure\City;
use Game\City\Infrastructure\CityBuilding;
use Game\Construction\Infrastructure\ConstructionOrder;
use Game\Identity\Application\TokenIssuer;
use Game\Identity\Domain\Account;

it('lets a guest enter a starter city and play the construction loop', function (): void {
    $clock = freezeClock('2026-08-27T18:00:00+00:00');

    $guest = $this->withHeader('Idempotency-Key', 'guest-mvp-001')
        ->postJson('/api/v1/auth/guest');

    $guest->assertStatus(201)->assertJsonStructure([
        'data' => ['access_token', 'refresh_token'],
    ]);
    $tokens = $guest->json('data');

    $bootstrap = $this->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'bootstrap-mvp-001')
        ->postJson('/api/v1/game/bootstrap');

    $bootstrap->assertStatus(201)->assertJsonStructure([
        'data' => [
            'player' => ['id', 'name', 'world_id'],
            'world' => ['id', 'code', 'name'],
            'city' => ['id', 'world_id', 'player_id', 'name_key', 'x', 'y'],
        ],
    ]);
    $cityId = $bootstrap->json('data.city.id');
    $worldId = $bootstrap->json('data.world.id');

    $city = $this->withToken($tokens['access_token'])->getJson('/api/v1/game/city');
    $city->assertOk()
        ->assertJsonPath('data.city.id', $cityId)
        ->assertJsonPath('data.resources.current.food', 500)
        ->assertJsonPath('data.resources.current.wood', 500)
        ->assertJsonPath('data.resources.current.stone', 500)
        ->assertJsonPath('data.slots.1.slot', 'plot_02')
        ->assertJsonPath('data.slots.1.status', 'occupied')
        ->assertJsonPath('data.slots.1.building.code', 'farm')
        ->assertJsonPath('data.slots.1.building.level', 1)
        ->assertJsonCount(0, 'data.constructions');

    $clock->advanceSeconds(10);

    $accrued = $this->withToken($tokens['access_token'])->getJson('/api/v1/game/city');
    $accrued->assertOk()
        ->assertJsonPath('data.resources.current.food', 510)
        ->assertJsonPath('data.resources.current.wood', 510)
        ->assertJsonPath('data.resources.current.stone', 510)
        ->assertJsonPath('data.server_time', '2026-08-27T18:00:10+00:00');

    $upgrade = $this->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'upgrade-farm-mvp-001')
        ->postJson('/api/v1/game/city/buildings/farm/upgrade');

    $upgrade->assertStatus(201)
        ->assertJsonPath('data.construction.building_code', 'farm')
        ->assertJsonPath('data.construction.from_level', 1)
        ->assertJsonPath('data.construction.target_level', 2);

    $afterDebit = City::query()->where('world_id', $worldId)->whereKey($cityId)->firstOrFail();
    expect($afterDebit->food)->toBe(510)
        ->and($afterDebit->wood)->toBe(390)
        ->and($afterDebit->stone)->toBe(450)
        ->and(ConstructionOrder::query()->where('world_id', $worldId)->where('city_id', $cityId)->count())->toBe(1);

    $clock->advanceSeconds(20);

    $completed = $this->withToken($tokens['access_token'])->getJson('/api/v1/game/city');
    $completed->assertOk()
        ->assertJsonPath('data.slots.1.building.code', 'farm')
        ->assertJsonPath('data.slots.1.building.level', 2)
        ->assertJsonCount(0, 'data.constructions');

    expect(CityBuilding::query()
        ->where('world_id', $worldId)
        ->where('city_id', $cityId)
        ->where('building_code', 'farm')
        ->value('level'))->toBe(2);
});

it('replays idempotent mutations without creating duplicate game state', function (): void {
    freezeClock('2026-08-27T18:00:00+00:00');

    $first = $this->withHeader('Idempotency-Key', 'guest-mvp-replay-001')
        ->postJson('/api/v1/auth/guest');
    $second = $this->withHeader('Idempotency-Key', 'guest-mvp-replay-001')
        ->postJson('/api/v1/auth/guest');

    $first->assertStatus(201);
    $second->assertStatus(201);
    expect($second->json())->toEqual($first->json())
        ->and(Account::query()->count())->toBe(1);

    $tokens = $first->json('data');
    $bootstrap = $this->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'bootstrap-mvp-replay-001')
        ->postJson('/api/v1/game/bootstrap');
    $replayedBootstrap = $this->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'bootstrap-mvp-replay-001')
        ->postJson('/api/v1/game/bootstrap');

    expect($replayedBootstrap->json())->toEqual($bootstrap->json())
        ->and(City::query()->count())->toBe(1)
        ->and(CityBuilding::query()->count())->toBe(5);
});

it('rejects a gameplay mutation without an idempotency key', function (): void {
    freezeClock('2026-08-27T18:00:00+00:00');

    $guest = $this->withHeader('Idempotency-Key', 'guest-mvp-validation-001')
        ->postJson('/api/v1/auth/guest');
    $tokens = $guest->json('data');

    $this->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', '')
        ->postJson('/api/v1/game/bootstrap')
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REQUIRED');
});

it('does not replay one account idempotency result to another account', function (): void {
    freezeClock('2026-08-27T18:00:00+00:00');

    $first = app(TokenIssuer::class)->issue(Account::factory()->create(), [
        'device_id' => 'scope-first-device',
        'platform' => 'test',
    ]);
    $second = app(TokenIssuer::class)->issue(Account::factory()->create(), [
        'device_id' => 'scope-second-device',
        'platform' => 'test',
    ]);
    $firstBootstrap = $this->withToken($first['access_token'])
        ->withHeader('Idempotency-Key', 'bootstrap-cross-account-001')
        ->postJson('/api/v1/game/bootstrap')
        ->assertCreated();
    app('auth')->forgetGuards();
    $secondBootstrap = $this->withToken($second['access_token'])
        ->withHeader('Idempotency-Key', 'bootstrap-cross-account-001')
        ->postJson('/api/v1/game/bootstrap')
        ->assertCreated();

    expect($secondBootstrap->json('data.player.id'))
        ->not->toBe($firstBootstrap->json('data.player.id'));
});
