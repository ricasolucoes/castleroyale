<?php

declare(strict_types=1);

use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Illuminate\Support\Facades\Route;

it('renders a domain failure through the standard error envelope', function (): void {
    Route::middleware('api')->get('/api/v1/_test/insufficient', function (): void {
        throw GameException::of(
            ErrorCode::InsufficientResources,
            'Not enough resources.',
            ['missing' => ['wood', 'stone']],
        );
    });

    $this->getJson('/api/v1/_test/insufficient')
        ->assertStatus(400)
        ->assertExactJson([
            'error' => [
                'code' => 'INSUFFICIENT_RESOURCES',
                'message' => 'Not enough resources.',
                'details' => ['missing' => ['wood', 'stone']],
                'retryable' => false,
            ],
        ]);
});

it('maps an unknown route to NOT_FOUND rather than an HTML page', function (): void {
    $this->getJson('/api/v1/does-not-exist')
        ->assertStatus(404)
        ->assertJsonPath('error.code', ErrorCode::NotFound->value);
});

it('never leaks a data key alongside an error', function (): void {
    $body = $this->getJson('/api/v1/does-not-exist')->json();

    expect($body)->toHaveKey('error')
        ->and($body)->not->toHaveKey('data');
});
