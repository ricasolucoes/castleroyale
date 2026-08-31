<?php

declare(strict_types=1);

use Game\City\Infrastructure\City;
use Game\City\Infrastructure\CityBuilding;
use Game\Identity\Application\TokenIssuer;
use Game\Identity\Domain\Account;
use Game\Player\Application\GameBootstrapService;
use Game\Player\Infrastructure\Player;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\World\Infrastructure\World;
use Illuminate\Support\Facades\Broadcast;

function cityFoundationTokens(string $suffix): array
{
    $account = Account::factory()->create();

    return app(TokenIssuer::class)->issue($account, [
        'device_id' => 'city-device-'.$suffix,
        'device_name' => 'City test device',
        'platform' => 'test',
        'ip' => '127.0.0.1',
    ]);
}

it('persists stable starter building slots and returns them in city state', function (): void {
    $tokens = cityFoundationTokens('slots');
    $bootstrap = $this->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'city-foundation-bootstrap-slots')
        ->postJson('/api/v1/game/bootstrap')
        ->assertCreated();

    $cityId = $bootstrap->json('data.city.id');
    $city = $this->withToken($tokens['access_token'])->getJson('/api/v1/game/city');
    $slots = collect($city->json('data.buildings'))->pluck('slot');

    $city->assertOk()->assertJsonStructure(['data' => ['buildings' => [['slot', 'code']]]]);
    expect($slots)->toHaveCount(5)->toEqual($slots->unique());
    expect(CityBuilding::query()->where('world_id', $bootstrap->json('data.world.id'))->where('city_id', $cityId)->count())
        ->toBe(5);
});

it('returns TILE_OCCUPIED when a second player claims an occupied tile', function (): void {
    $world = World::create([
        'code' => 'occupied-city-world',
        'name' => 'Occupied City World',
        'capacity' => 10,
        'is_open' => true,
    ]);
    $first = Account::factory()->create();
    $second = Account::factory()->create();

    app(GameBootstrapService::class)->handle($first, (string) $world->getKey(), 'First City');
    $world->forceFill(['spawn_index' => 0])->save();

    $exception = null;
    try {
        app(GameBootstrapService::class)->handle($second, (string) $world->getKey(), 'Second City');
    } catch (GameException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(GameException::class)
        ->and($exception?->errorCode)->toBe(ErrorCode::TileOccupied);
});

it('authorises the city channel only for the owning account', function (): void {
    $world = World::create([
        'code' => 'city-channel-world',
        'name' => 'City Channel World',
        'capacity' => 10,
        'population' => 1,
        'spawn_index' => 1,
        'is_open' => true,
    ]);
    $owner = Account::factory()->create();
    $rival = Account::factory()->create();
    $player = Player::create(['world_id' => $world->getKey(), 'account_id' => $owner->getKey(), 'name' => 'City Owner']);
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
        ->and($callback($owner, (string) $city->getKey()))->toBeTrue()
        ->and($callback($rival, (string) $city->getKey()))->toBeFalse();
});

it('returns CITY_NOT_OWNED without leaking another city', function (): void {
    $owner = Account::factory()->create();
    $rival = Account::factory()->create();
    $owned = app(GameBootstrapService::class)->handle($owner)['city']['id'];

    $exception = null;
    try {
        app(Game\City\Application\CityStateService::class)->handle($rival, $owned);
    } catch (GameException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(GameException::class)
        ->and($exception?->errorCode)->toBe(ErrorCode::CityNotOwned);
});
