<?php

declare(strict_types=1);

namespace Game\Shared\Domain\Time;

use DateTimeImmutable;

/**
 * The single source of "now" for the game domain.
 *
 * Game rules must never call `now()`, `time()` or read a client-supplied
 * timestamp. Every duration (construction, research, training, marching) is
 * anchored to this clock so that time is server-authoritative and tests can
 * freeze it deterministically.
 *
 * @see FrozenClock for the test double.
 */
interface Clock
{
    /**
     * Current instant, always in UTC.
     */
    public function now(): DateTimeImmutable;
}
