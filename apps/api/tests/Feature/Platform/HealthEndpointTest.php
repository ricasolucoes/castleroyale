<?php

declare(strict_types=1);

it('reports healthy with the standard success envelope', function (): void {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['status', 'checks'],
            'meta' => ['game', 'environment', 'server_time', 'versions'],
        ])
        ->assertJsonPath('data.status', 'ok');
});

it('exposes the content versions the client needs to pin against', function (): void {
    $this->getJson('/api/v1/health')
        ->assertJsonPath('meta.versions.data', config('game.versions.data'))
        ->assertJsonPath('meta.versions.combat', config('game.versions.combat'))
        ->assertJsonPath('meta.versions.economy', config('game.versions.economy'));
});

it('reports server time in UTC ISO-8601', function (): void {
    $time = $this->getJson('/api/v1/health')->json('meta.server_time');

    expect($time)->toBeString()
        ->and(new DateTimeImmutable((string) $time))->toBeInstanceOf(DateTimeImmutable::class)
        ->and($time)->toEndWith('+00:00');
});

it('echoes a correlation id on every response', function (): void {
    $response = $this->getJson('/api/v1/health');

    expect($response->headers->get('X-Request-Id'))->not->toBeEmpty();
});

it('honours a well-formed inbound correlation id', function (): void {
    $response = $this->getJson('/api/v1/health', ['X-Request-Id' => 'abcdef123456']);

    expect($response->headers->get('X-Request-Id'))->toBe('abcdef123456');
});

it('rejects a malformed correlation id and generates its own', function (): void {
    $response = $this->getJson('/api/v1/health', ['X-Request-Id' => 'bad id with spaces!']);

    expect($response->headers->get('X-Request-Id'))->not->toBe('bad id with spaces!');
});
