<?php

declare(strict_types=1);

use Game\Shared\Application\Error\ErrorCode;

it('keeps every error code unique', function (): void {
    $values = array_map(static fn (ErrorCode $c) => $c->value, ErrorCode::cases());

    expect($values)->toBe(array_unique($values));
});

it('uses SCREAMING_SNAKE_CASE for every code', function (): void {
    foreach (ErrorCode::cases() as $case) {
        expect($case->value)->toMatch('/^[A-Z][A-Z0-9_]*$/');
    }
});

it('maps every code to a valid HTTP status', function (): void {
    foreach (ErrorCode::cases() as $case) {
        expect($case->httpStatus())->toBeGreaterThanOrEqual(400)
            ->and($case->httpStatus())->toBeLessThan(600);
    }
});

it('only marks transient failures as retryable', function (): void {
    expect(ErrorCode::RateLimited->isRetryable())->toBeTrue()
        ->and(ErrorCode::InsufficientResources->isRetryable())->toBeFalse()
        ->and(ErrorCode::ValidationFailed->isRetryable())->toBeFalse();
});
