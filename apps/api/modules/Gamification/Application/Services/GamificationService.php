<?php

declare(strict_types=1);

namespace Game\Gamification\Application\Services;

use Game\Gamification\Domain\Events\AchievementUnlocked;
use Game\Gamification\Domain\Events\PlayerLeveledUp;
use Game\Gamification\Infrastructure\Achievement;
use Game\Gamification\Infrastructure\PlayerAchievement;
use Game\Gamification\Infrastructure\Progression;
use Game\Player\Infrastructure\Player;
use Illuminate\Support\Facades\Event;

class GamificationService
{
    /**
     * Get or create progression for player.
     */
    public function getProgression(Player $player): Progression
    {
        return Progression::firstOrCreate(
            ['player_id' => $player->id],
            ['level' => 1, 'xp' => 0],
        );
    }

    /**
     * Add XP and calculate level ups.
     */
    public function addXp(Player $player, int $xpAmount): void
    {
        if ($xpAmount <= 0) {
            return;
        }

        $progression = $this->getProgression($player);
        $progression->xp += $xpAmount;

        $newLevel = $this->calculateLevel($progression->xp);

        if ($newLevel > $progression->level) {
            $progression->level = $newLevel;
            Event::dispatch(new PlayerLeveledUp($player, $newLevel, 0));
        }

        $progression->save();
    }

    /**
     * Unlock or increment an achievement.
     */
    public function progressAchievement(Player $player, string $internalId, int $steps = 1): void
    {
        $achievement = Achievement::where('internal_id', $internalId)->first();
        if ($achievement === null) {
            return;
        }

        $playerAchievement = PlayerAchievement::firstOrCreate(
            ['player_id' => $player->id, 'achievement_id' => $achievement->id],
            ['current_steps' => 0],
        );

        if ($playerAchievement->unlocked_at !== null) {
            return; // Already unlocked
        }

        $playerAchievement->current_steps += $steps;

        if ($playerAchievement->current_steps >= $achievement->max_steps) {
            $playerAchievement->current_steps = $achievement->max_steps;
            $playerAchievement->unlocked_at = now();

            if ($achievement->xp_reward > 0) {
                $this->addXp($player, $achievement->xp_reward);
            }

            Event::dispatch(new AchievementUnlocked($player, $achievement->internal_id, $achievement->xp_reward));
        }

        $playerAchievement->save();
    }

    /**
     * Very basic level curve: XP = (Level - 1) * 1000
     */
    private function calculateLevel(int $xp): int
    {
        return (int) floor($xp / 1000) + 1;
    }
}
