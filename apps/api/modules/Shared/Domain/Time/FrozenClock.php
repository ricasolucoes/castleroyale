<?php

declare(strict_types=1);

namespace Game\Shared\Domain\Time;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Deterministic clock for tests and for the internal debug tooling.
 *
 * Lives in the domain (not the test folder) because time-travel is a first
 * class concern of this game: balance simulations and battle replays both
 * need to drive the clock explicitly.
 */
final class FrozenClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(DateTimeImmutable $now)
    {
        $this->now = $now->setTimezone(new DateTimeZone('UTC'));
    }

    public static function at(string $iso8601): self
    {
        return new self(new DateTimeImmutable($iso8601, new DateTimeZone('UTC')));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    /**
     * Advance the clock by a number of whole seconds.
     */
    public function advanceSeconds(int $seconds): void
    {
        $interval = new DateInterval('PT'.abs($seconds).'S');

        $this->now = $seconds < 0
            ? $this->now->sub($interval)
            : $this->now->add($interval);
    }
}
