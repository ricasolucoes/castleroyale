<?php

declare(strict_types=1);

function viewportTestTokens(string $suffix): array
{
    return test()->withHeader('Idempotency-Key', 'viewport-guest-'.$suffix)
        ->postJson('/api/v1/auth/guest')
        ->assertCreated()
        ->json('data');
}

it('generates the default world once and serves bounded persisted tiles', function (): void {
    $tokens = viewportTestTokens('viewport');

    $this->withToken($tokens['access_token'])
        ->withHeader('Idempotency-Key', 'viewport-bootstrap-001')
        ->postJson('/api/v1/game/bootstrap')
        ->assertCreated();

    $this->artisan('game:generate-world', ['world' => 'aurora'])->assertExitCode(0);
    $this->artisan('game:generate-world', ['world' => 'aurora'])->assertExitCode(0);

    $response = $this->withToken($tokens['access_token'])
        ->getJson('/api/v1/game/world/viewport?min_x=0&max_x=1&min_y=0&max_y=1');

    $response->assertOk()->assertJsonStructure([
        'data' => [
            'bounds' => ['min_x', 'max_x', 'min_y', 'max_y'],
            'tiles' => [['id', 'region_id', 'x', 'y', 'terrain']],
        ],
    ]);

    expect($response->json('data.tiles'))->toHaveCount(4)
        ->and($response->json('data.tiles'))->each->toHaveKeys(['id', 'region_id', 'x', 'y', 'terrain']);

    foreach ($response->json('data.tiles') as $tile) {
        expect($tile['x'])->toBeIn([0, 1])
            ->and($tile['y'])->toBeIn([0, 1]);
    }
});

it('rejects a viewport larger than the server limit', function (): void {
    $tokens = viewportTestTokens('viewport-limit');

    $response = $this->withToken($tokens['access_token'])
        ->getJson('/api/v1/game/world/viewport?min_x=0&max_x=64&min_y=0&max_y=64');

    $response->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});
