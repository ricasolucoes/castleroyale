<?php

declare(strict_types=1);

namespace Game\Construction\Domain;

/**
 * The one place `game.time_scale` is applied.
 *
 * The preview the player reads before tapping Upgrade and the deadline the server
 * schedules must be the same number. They were computed in two places and drifted
 * apart in local development, where the scale is not 1 (09-UI-SPEC.md § Data
 * Contract Dependency #2). The scale is never sent to the client — the server
 * returns an already-scaled duration instead.
 */
final readonly class BuildDuration
{
    public static function scaled(int $rawSeconds, int $timeScale): int
    {
        return intdiv(max(0, $rawSeconds), max(1, $timeScale));
    }
}
