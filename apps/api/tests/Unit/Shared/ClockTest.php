<?php

declare(strict_types=1);

use Game\Shared\Domain\Time\FrozenClock;
use Game\Shared\Infrastructure\Time\SystemClock;

it('reports the system clock in UTC', function (): void {
    expect((new SystemClock)->now()->getTimezone()->getName())->toBe('UTC');
});

it('does not move unless told to', function (): void {
    $clock = FrozenClock::at('2026-01-01T00:00:00+00:00');
    $first = $clock->now();

    expect($clock->now()->getTimestamp())->toBe($first->getTimestamp());
});

it('advances by whole seconds', function (): void {
    $clock = FrozenClock::at('2026-01-01T00:00:00+00:00');
    $clock->advanceSeconds(3_600);

    expect($clock->now()->format(DATE_ATOM))->toBe('2026-01-01T01:00:00+00:00');
});

it('normalises any input timezone to UTC', function (): void {
    $clock = new FrozenClock(new DateTimeImmutable('2026-01-01T00:00:00', new DateTimeZone('America/Sao_Paulo')));

    expect($clock->now()->format(DATE_ATOM))->toBe('2026-01-01T03:00:00+00:00');
});
