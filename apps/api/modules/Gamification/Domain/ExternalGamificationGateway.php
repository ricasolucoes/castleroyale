<?php

declare(strict_types=1);

namespace Game\Gamification\Domain;

interface ExternalGamificationGateway
{
    /**
     * @return array{status: string, message?: string, data?: mixed}
     */
    public function submitScore(string $leaderboardId, string $playerName, float|int $score, ?string $googleId = null): array;

    /**
     * @return array{status: string, game?: string, leaderboards?: list<array<string, mixed>>}
     */
    public function getLeaderboards(?string $gameSlug = null): array;

    /**
     * @return array{status: string, data?: list<array<string, mixed>>}
     */
    public function getGames(): array;
}
