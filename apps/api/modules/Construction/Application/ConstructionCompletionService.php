<?php

declare(strict_types=1);

namespace Game\Construction\Application;

use DateTimeImmutable;
use Game\City\Infrastructure\City;
use Game\City\Infrastructure\CityBuilding;
use Game\City\Interface\Broadcasting\CityStateChanged;
use Game\Construction\Infrastructure\ConstructionOrder;
use Illuminate\Support\Collection;

final class ConstructionCompletionService
{
    public function completeOverdueLocked(City $city, DateTimeImmutable $now): void
    {
        $ordersQuery = ConstructionOrder::query()
            ->where('world_id', $city->world_id)
            ->where('city_id', $city->getKey())
            ->where('finishes_at', '<=', $now);
        $ordersQuery->getQuery()->whereNull('completed_at')->lockForUpdate();
        /** @var Collection<int, ConstructionOrder> $orders */
        $orders = $ordersQuery->get();

        foreach ($orders as $order) {
            /** @var CityBuilding|null $building */
            $buildingQuery = CityBuilding::query()
                ->where('world_id', $city->world_id)
                ->where('city_id', $city->getKey())
                ->where('building_code', $order->building_code);
            $buildingQuery->getQuery()->lockForUpdate();
            $building = $buildingQuery->first();

            if ($building !== null) {
                $building->update(['level' => $order->target_level]);
            }

            $order->forceFill(['completed_at' => $now])->save();

            CityStateChanged::dispatch(
                (string) $city->getKey(),
                (string) $city->world_id,
                $now->format(DATE_ATOM),
            );
        }
    }
}
