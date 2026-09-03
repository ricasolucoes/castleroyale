<?php

declare(strict_types=1);

namespace Game\Gamification\Domain\Events;

use Game\Player\Infrastructure\Player;

final readonly class AchievementUnlocked
{
    public function __construct(
        public Player $player,
        public string $achievementId,
        public int $xpGranted,
    ) {}
}
