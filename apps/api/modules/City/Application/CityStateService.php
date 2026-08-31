<?php

declare(strict_types=1);

namespace Game\City\Application;

use Game\City\Infrastructure\City;
use Game\City\Infrastructure\CityBuilding;
use Game\Construction\Application\ConstructionCompletionService;
use Game\Construction\Infrastructure\ConstructionOrder;
use Game\Economy\Application\CityEconomyService;
use Game\Identity\Domain\Account;
use Game\Player\Application\GameBootstrapService;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Domain\Economy\ResourceType;
use Game\Shared\Domain\Time\Clock;
use Game\Shared\Infrastructure\GameData\GameDataCatalog;
use Illuminate\Support\Facades\DB;

final readonly class CityStateService
{
    public function __construct(
        private Clock $clock,
        private GameBootstrapService $bootstrap,
        private ConstructionCompletionService $completion,
        private CityEconomyService $economy,
        private GameDataCatalog $catalog,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Account $account, ?string $requestedCityId = null): array
    {
        $bootstrap = $this->bootstrap->handle($account);
        $worldId = $bootstrap['world']['id'];
        $cityId = $requestedCityId ?? $bootstrap['city']['id'];
        $playerId = $bootstrap['player']['id'];

        return DB::transaction(function () use ($bootstrap, $worldId, $cityId, $playerId): array {
            $cityQuery = City::query()
                ->where('world_id', $worldId)
                ->where('player_id', $playerId)
                ->whereKey($cityId);
            $cityQuery->getQuery()->lockForUpdate();
            /** @var City|null $city */
            $city = $cityQuery->first();

            if ($city === null) {
                throw GameException::of(ErrorCode::CityNotOwned, 'The city is not owned by this player.');
            }

            $now = $this->clock->now();
            $this->completion->completeOverdueLocked($city, $now);
            $this->economy->accrueLocked($city, $now);
            $city->refresh();

            $buildings = [];
            $cityBuildingsQuery = CityBuilding::query()
                ->where('world_id', $worldId)
                ->where('city_id', $city->getKey());
            $cityBuildingsQuery->getQuery()->orderBy('building_code');
            $cityBuildings = $cityBuildingsQuery->get();

            foreach ($cityBuildings as $cityBuilding) {
                $definition = $this->catalog->building($cityBuilding->building_code);
                if ($definition === null) {
                    continue;
                }

                $level = (int) $cityBuilding->level;
                $maxLevel = (int) ($definition['max_level'] ?? $level);
                $next = $level < $maxLevel ? $this->catalog->buildingLevel($cityBuilding->building_code, $level + 1) : null;
                $buildings[] = [
                    'slot' => (string) $cityBuilding->slot,
                    'code' => $cityBuilding->building_code,
                    'name_key' => (string) ($definition['name_key'] ?? $cityBuilding->building_code),
                    'category' => (string) ($definition['category'] ?? 'city'),
                    'level' => $level,
                    'max_level' => $maxLevel,
                    'next_level_cost' => $next === null ? $this->emptyBundle() : $this->cost($next),
                    'build_time_seconds' => $next === null ? 0 : (int) ($next['build_time_seconds'] ?? 0),
                ];
            }

            $constructionQuery = ConstructionOrder::query()
                ->where('world_id', $worldId)
                ->where('city_id', $city->getKey());
            $constructionQuery->getQuery()->whereNull('completed_at')->orderBy('finishes_at');
            /** @var ConstructionOrder|null $construction */
            $construction = $constructionQuery->first();

            return [
                'player' => $bootstrap['player'],
                'world' => $bootstrap['world'],
                'city' => [
                    'id' => (string) $city->getKey(),
                    'name_key' => (string) $city->name_key,
                    'x' => (int) $city->x,
                    'y' => (int) $city->y,
                ],
                'resources' => [
                    'current' => $this->economy->balances($city),
                    'capacity' => $this->economy->capacities($city),
                ],
                'buildings' => $buildings,
                'construction' => $construction === null ? null : [
                    'id' => (string) $construction->getKey(),
                    'building_code' => (string) $construction->building_code,
                    'from_level' => (int) $construction->from_level,
                    'target_level' => (int) $construction->target_level,
                    'started_at' => $construction->started_at?->toDateTimeImmutable()->format(DATE_ATOM),
                    'finishes_at' => $construction->finishes_at?->toDateTimeImmutable()->format(DATE_ATOM),
                ],
                'server_time' => $now->format(DATE_ATOM),
            ];
        });
    }

    /**
     * @param array<string, mixed> $level
     * @return array<string, int>
     */
    private function cost(array $level): array
    {
        $raw = is_array($level['cost'] ?? null) ? $level['cost'] : [];
        $cost = [];
        foreach (ResourceType::all() as $resource) {
            $cost[$resource->value] = (int) ($raw[$resource->value] ?? 0);
        }

        return $cost;
    }

    /**
     * @return array<string, int>
     */
    private function emptyBundle(): array
    {
        $bundle = [];
        foreach (ResourceType::all() as $resource) {
            $bundle[$resource->value] = 0;
        }

        return $bundle;
    }
}
