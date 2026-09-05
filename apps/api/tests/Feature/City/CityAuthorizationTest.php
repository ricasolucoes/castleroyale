<?php

declare(strict_types=1);

use Game\Identity\Application\TokenIssuer;
use Game\Identity\Domain\Account;
use Game\Shared\Application\Error\ErrorCode;

function cityAuthorizationTokens(string $suffix): array
{
    $account = Account::factory()->create();

    return app(TokenIssuer::class)->issue($account, [
        'device_id' => 'city-auth-device-'.$suffix,
        'device_name' => 'City authorization test device',
        'platform' => 'test',
        'ip' => '127.0.0.1',
    ]);
}

it('refuses another player\'s city with CITY_NOT_OWNED', function (): void {
    $ownerTokens = cityAuthorizationTokens('owner');
    $rivalTokens = cityAuthorizationTokens('rival');

    $ownerBootstrap = $this->withToken($ownerTokens['access_token'])
        ->withHeader('Idempotency-Key', 'city-authorization-owner-bootstrap')
        ->postJson('/api/v1/game/bootstrap')
        ->assertCreated();
    // Sanctum caches the resolved user on the guard for the life of the test;
    // forget it before authenticating as a different account in-process.
    app('auth')->forgetGuards();
    $this->withToken($rivalTokens['access_token'])
        ->withHeader('Idempotency-Key', 'city-authorization-rival-bootstrap')
        ->postJson('/api/v1/game/bootstrap')
        ->assertCreated();

    $ownerCityId = $ownerBootstrap->json('data.city.id');

    $response = $this->withToken($rivalTokens['access_token'])
        ->getJson('/api/v1/game/city/'.$ownerCityId);

    expect($response)->toBeApiError(ErrorCode::CityNotOwned);
});

it('leaks nothing about a city it refuses', function (): void {
    $ownerTokens = cityAuthorizationTokens('leak-owner');
    $rivalTokens = cityAuthorizationTokens('leak-rival');

    $ownerBootstrap = $this->withToken($ownerTokens['access_token'])
        ->withHeader('Idempotency-Key', 'city-authorization-leak-owner-bootstrap')
        ->postJson('/api/v1/game/bootstrap')
        ->assertCreated();
    // Sanctum caches the resolved user on the guard for the life of the test;
    // forget it before authenticating as a different account in-process.
    app('auth')->forgetGuards();
    $this->withToken($rivalTokens['access_token'])
        ->withHeader('Idempotency-Key', 'city-authorization-leak-rival-bootstrap')
        ->postJson('/api/v1/game/bootstrap')
        ->assertCreated();

    $ownerCityId = $ownerBootstrap->json('data.city.id');
    $ownerNameKey = $ownerBootstrap->json('data.city.name_key');

    $response = $this->withToken($rivalTokens['access_token'])
        ->getJson('/api/v1/game/city/'.$ownerCityId);

    expect($response->getContent())->not->toContain($ownerCityId)
        ->and($response->getContent())->not->toContain($ownerNameKey)
        ->and($response->getContent())->not->toContain('plot_')
        ->and($response->json('data'))->toBeNull();
});

it('never answers 404 for a city that exists but is not yours', function (): void {
    $ownerTokens = cityAuthorizationTokens('404-owner');
    $rivalTokens = cityAuthorizationTokens('404-rival');

    $ownerBootstrap = $this->withToken($ownerTokens['access_token'])
        ->withHeader('Idempotency-Key', 'city-authorization-404-owner-bootstrap')
        ->postJson('/api/v1/game/bootstrap')
        ->assertCreated();
    // Sanctum caches the resolved user on the guard for the life of the test;
    // forget it before authenticating as a different account in-process.
    app('auth')->forgetGuards();
    $this->withToken($rivalTokens['access_token'])
        ->withHeader('Idempotency-Key', 'city-authorization-404-rival-bootstrap')
        ->postJson('/api/v1/game/bootstrap')
        ->assertCreated();

    $ownerCityId = $ownerBootstrap->json('data.city.id');

    $response = $this->withToken($rivalTokens['access_token'])
        ->getJson('/api/v1/game/city/'.$ownerCityId);

    expect($response->status())->not->toBe(404);
});
