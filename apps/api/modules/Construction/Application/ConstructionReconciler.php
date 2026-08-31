<?php

declare(strict_types=1);

namespace Game\Construction\Application;

use Game\City\Infrastructure\City;
use Game\Construction\Infrastructure\ConstructionOrder;
use Game\Shared\Domain\Time\Clock;
use Game\World\Infrastructure\World;
use Illuminate\Support\Facades\DB;

final readonly class ConstructionReconciler
{
    public function __construct(private Clock $clock, private ConstructionCompletionService $completion) {}

    public function run(): int
    {
        $completed = 0;
        $now = $this->clock->now();

        foreach (World::query()->where('is_open', true)->pluck('id') as $worldId) {
            $cityIds = DB::table('construction_orders')
                ->where('world_id', $worldId)
                ->whereNull('completed_at')
                ->where('finishes_at', '<=', $now)
                ->distinct()
                ->pluck('city_id');

            foreach ($cityIds as $cityId) {
                $completed += DB::transaction(function () use ($worldId, $cityId, $now): int {
                    $cityQuery = City::query()
                        ->where('world_id', $worldId)
                        ->whereKey($cityId);
                    $cityQuery->getQuery()->lockForUpdate();
                    /** @var City|null $city */
                    $city = $cityQuery->first();

                    if ($city === null) {
                        return 0;
                    }

                    $pendingQuery = ConstructionOrder::query()
                        ->where('world_id', $worldId)
                        ->where('city_id', $cityId)
                        ->where('finishes_at', '<=', $now);
                    $pendingQuery->getQuery()->whereNull('completed_at');
                    $pending = $pendingQuery->getQuery()->count();
                    $this->completion->completeOverdueLocked($city, $now);

                    return $pending;
                });
            }
        }

        return $completed;
    }
}
