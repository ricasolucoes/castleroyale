<?php

declare(strict_types=1);

namespace Game\Shared\Infrastructure\Time;

use DateTimeImmutable;
use DateTimeZone;
use Game\Shared\Domain\Time\Clock;

/**
 * Production clock. Always reports UTC regardless of server locale settings.
 */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
