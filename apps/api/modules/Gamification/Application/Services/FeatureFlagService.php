<?php

declare(strict_types=1);

namespace Game\Gamification\Application\Services;

use Game\Player\Infrastructure\Player;

class FeatureFlagService
{
    /** @var array<string, bool> */
    private array $flags = [
        'google_play_sidekick' => false,
        'game_stats' => true,
        'new_achievements' => true,
        'daily_quests' => true,
        'weekly_quests' => true,
        'streaks' => true,
        'seasons' => false,
        'social_challenges' => false,
        'new_rewards' => true,
    ];

    public function isEnabled(string $feature, ?Player $player = null): bool
    {
        return $this->flags[$feature] ?? false;
    }
}
