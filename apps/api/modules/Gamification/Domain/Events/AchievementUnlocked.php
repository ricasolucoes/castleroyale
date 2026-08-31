<?php

declare(strict_types=1);

namespace Game\Gamification\Domain\Events;

use Game\Player\Domain\Models\Player;

final readonly class AchievementUnlocked
{
    public function __construct(
        public Player $player,
        public string $achievementId,
        public int $xpGranted
    ) {}
}
