<?php

declare(strict_types=1);

namespace Game\Technology\Interface\Jobs;

use Game\Player\Infrastructure\Player;
use Game\Shared\Domain\Time\Clock;
use Game\Technology\Application\ResearchCompletionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

final class CompleteResearch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $worldId,
        private readonly string $playerId,
        private readonly string $orderId,
    ) {}

    public function handle(Clock $clock, ResearchCompletionService $completion): void
    {
        DB::transaction(function () use ($clock, $completion): void {
            $playerQuery = Player::query()
                ->where('world_id', $this->worldId)
                ->whereKey($this->playerId);
            $playerQuery->getQuery()->lockForUpdate();
            /** @var Player|null $player */
            $player = $playerQuery->first();

            if ($player === null) {
                return;
            }

            // The completion service guards completed_at, so a queue retry and
            // the reconciler can safely race without applying the level twice.
            $orderExists = DB::table('research_orders')
                ->where('world_id', $this->worldId)
                ->where('player_id', $this->playerId)
                ->where('id', $this->orderId)
                ->whereNull('completed_at')
                ->exists();

            if (! $orderExists) {
                return;
            }

            $completion->completeOverdueLocked($player, $clock->now());
        });
    }
}
