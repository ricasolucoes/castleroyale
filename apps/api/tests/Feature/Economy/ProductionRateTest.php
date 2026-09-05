<?php

declare(strict_types=1);

namespace Tests\Feature\Economy;

use Game\City\Application\CityStateService;
use Game\City\Infrastructure\City;
use Game\Economy\Application\CityEconomyService;
use Game\Economy\Infrastructure\EconomyLedger;
use Game\Identity\Domain\Account;
use Game\Player\Application\GameBootstrapService;

it('publishes a starter city production rate of one unit per second per producer', function (): void {
    freezeClock('2026-08-28T00:00:00+00:00');
    $account = Account::factory()->create();
    $bootstrap = app(GameBootstrapService::class)->handle($account);
    $city = City::query()
        ->where('world_id', $bootstrap['world']['id'])
        ->whereKey($bootstrap['city']['id'])
        ->firstOrFail();

    expect(app(CityEconomyService::class)->ratesPerHour($city))->toBe([
        'food' => 3600,
        'wood' => 3600,
        'stone' => 3600,
        'iron' => 0,
        'gold' => 0,
    ]);
});

it('publishes a rate that exactly equals an hour of real accrual, cap included', function (): void {
    $clock = freezeClock('2026-08-28T00:00:00+00:00');
    $account = Account::factory()->create();
    $bootstrap = app(GameBootstrapService::class)->handle($account);
    $worldId = $bootstrap['world']['id'];
    $cityId = $bootstrap['city']['id'];

    $state = app(CityStateService::class)->handle($account);
    $rate = $state['resources']['rate']['food'];

    $clock->advanceSeconds(3600);
    $state = app(CityStateService::class)->handle($account);

    $rows = EconomyLedger::query()
        ->where('world_id', $worldId)
        ->where('city_id', $cityId)
        ->where('resource', 'food')
        ->where('reason', 'production.elapsed')
        ->get();
    $accrued = (int) $rows->sum('amount') + (int) $rows->sum('overflow_amount');

    expect($accrued)->toBe($rate)
        ->and($state['resources']['current']['food'])->toBe($state['resources']['capacity']['food']);
});

it('returns rate and capacity over HTTP with capacity taken from game data', function (): void {
    freezeClock('2026-08-28T00:00:00+00:00');
    $guest = test()->withHeader('Idempotency-Key', 'guest-production-rate-001')
        ->postJson('/api/v1/auth/guest');
    $tokens = $guest->json('data');

    test()->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'bootstrap-production-rate-001')
        ->postJson('/api/v1/game/bootstrap');

    $response = test()->withToken($tokens['access_token'])->getJson('/api/v1/game/city');

    $response->assertOk()
        ->assertJsonPath('data.resources.rate.food', 3600)
        ->assertJsonPath('data.resources.rate.gold', 0)
        ->assertJsonPath('data.resources.capacity.food', 1000)
        ->assertJsonPath('data.resources.capacity.gold', 500);
    expect(array_keys($response->json('data.resources.rate')))
        ->toBe(['food', 'wood', 'stone', 'iron', 'gold']);
});
