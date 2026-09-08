<?php

declare(strict_types=1);

namespace Game\Technology\Application;

use Game\Player\Infrastructure\Player;
use Game\Shared\Domain\Time\Clock;
use Game\Technology\Infrastructure\ResearchOrder;
use Game\World\Infrastructure\World;
use Illuminate\Support\Facades\DB;

/**
 * The safety net for research completion: if a worker died holding
 * {@see \Game\Technology\Interface\Jobs\CompleteResearch}, or Redis lost the
 * delayed entry, this finds every overdue research and finishes it.
 *
 * Mirrors {@see \Game\Construction\Application\ConstructionReconciler}
 * exactly, including scanning open worlds only — an order (here, a research)
 * in a world closed for maintenance never completes. Both reconcilers now
 * share this limitation deliberately: whichever phase fixes it fixes both,
 * rather than the two reconcilers silently diverging.
 */
final readonly class ResearchReconciler
{
    public function __construct(private Clock $clock, private ResearchCompletionService $completion) {}

    public function run(): int
    {
        $completed = 0;
        $now = $this->clock->now();

        foreach (World::query()->where('is_open', true)->pluck('id') as $worldId) {
            $playerIds = DB::table('research_orders')
                ->where('world_id', $worldId)
                ->whereNull('completed_at')
                ->where('finishes_at', '<=', $now)
                ->distinct()
                ->pluck('player_id');

            foreach ($playerIds as $playerId) {
                $completed += DB::transaction(function () use ($worldId, $playerId, $now): int {
                    $playerQuery = Player::query()
                        ->where('world_id', $worldId)
                        ->whereKey($playerId);
                    $playerQuery->getQuery()->lockForUpdate();
                    /** @var Player|null $player */
                    $player = $playerQuery->first();

                    if ($player === null) {
                        return 0;
                    }

                    $pendingQuery = ResearchOrder::query()
                        ->where('world_id', $worldId)
                        ->where('player_id', $playerId)
                        ->where('finishes_at', '<=', $now);
                    $pendingQuery->getQuery()->whereNull('completed_at');
                    $pending = $pendingQuery->getQuery()->count();
                    $this->completion->completeOverdueLocked($player, $now);

                    return $pending;
                });
            }
        }

        return $completed;
    }
}
