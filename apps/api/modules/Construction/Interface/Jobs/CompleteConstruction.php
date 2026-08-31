<?php

declare(strict_types=1);

namespace Game\Construction\Interface\Jobs;

use Game\City\Infrastructure\City;
use Game\Construction\Application\ConstructionCompletionService;
use Game\Shared\Domain\Time\Clock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

final class CompleteConstruction implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $worldId,
        private readonly string $cityId,
        private readonly string $orderId,
    ) {}

    public function handle(Clock $clock, ConstructionCompletionService $completion): void
    {
        DB::transaction(function () use ($clock, $completion): void {
            $cityQuery = City::query()
                ->where('world_id', $this->worldId)
                ->whereKey($this->cityId);
            $cityQuery->getQuery()->lockForUpdate();
            /** @var City|null $city */
            $city = $cityQuery->first();

            if ($city === null) {
                return;
            }

            // The completion service guards completed_at, so a queue retry and
            // the reconciler can safely race without applying the level twice.
            $orderExists = DB::table('construction_orders')
                ->where('world_id', $this->worldId)
                ->where('city_id', $this->cityId)
                ->where('id', $this->orderId)
                ->whereNull('completed_at')
                ->exists();

            if (! $orderExists) {
                return;
            }

            $completion->completeOverdueLocked($city, $clock->now());
        });
    }
}
