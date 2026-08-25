<?php

declare(strict_types=1);

use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Domain\Time\Clock;

it('freezes the clock the container hands to game code', function (): void {
    $clock = freezeClock('2026-06-01T12:00:00+00:00');

    expect(app(Clock::class))->toBe($clock)
        ->and(app(Clock::class)->now()->format(DATE_ATOM))->toBe('2026-06-01T12:00:00+00:00');
});

it('freezes the clock the api reports as server time', function (): void {
    freezeClock('2026-06-01T12:00:00+00:00');

    $this->getJson('/api/v1/health')
        ->assertJsonPath('meta.server_time', '2026-06-01T12:00:00+00:00');
});

it('advances a frozen clock by whole seconds', function (): void {
    $clock = freezeClock('2026-06-01T12:00:00+00:00');
    $clock->advanceSeconds(90);

    expect(app(Clock::class)->now()->format(DATE_ATOM))->toBe('2026-06-01T12:01:30+00:00');
});

it('authenticates a staff account for back-office assertions', function (): void {
    $user = actingAsStaff();

    expect($user->is_staff)->toBeTrue()
        ->and(auth()->id())->toBe($user->id);
});

it('asserts an error envelope by code', function (): void {
    expect($this->getJson('/api/v1/does-not-exist'))->toBeApiError(ErrorCode::NotFound);
});
