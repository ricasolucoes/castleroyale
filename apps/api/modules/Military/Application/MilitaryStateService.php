<?php

declare(strict_types=1);

namespace Game\Military\Application;

use Game\City\Infrastructure\City;
use Game\Identity\Domain\Account;
use Game\Military\Infrastructure\CityUnit;
use Game\Player\Application\GameBootstrapService;
use Game\Shared\Domain\Time\Clock;
use Game\Shared\Infrastructure\GameData\GameDataCatalog;
use Illuminate\Support\Facades\DB;

final readonly class MilitaryStateService
{
    public function __construct(
        private Clock $clock,
        private GameBootstrapService $bootstrap,
        private GameDataCatalog $catalog,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Account $account): array
    {
        $bootstrap = $this->bootstrap->handle($account);
        $worldId = $bootstrap['world']['id'];
        $cityId = $bootstrap['city']['id'];

        return DB::transaction(function () use ($bootstrap, $worldId, $cityId): array {
            $cityQuery = City::query()->where('world_id', $worldId)->whereKey($cityId);
            $cityQuery->getQuery()->lockForUpdate();
            /** @var City $city */
            $city = $cityQuery->firstOrFail();

            foreach ($this->catalog->starterUnits() as $starterUnit) {
                CityUnit::query()->firstOrCreate(
                    [
                        'world_id' => $worldId,
                        'city_id' => $city->getKey(),
                        'unit_code' => $starterUnit['code'],
                    ],
                    ['quantity' => $starterUnit['quantity']],
                );
            }

            $rowsQuery = CityUnit::query()
                ->where('world_id', $worldId)
                ->where('city_id', $city->getKey());
            $rowsQuery->getQuery()->orderBy('unit_code');
            $rows = $rowsQuery->get();
            $units = [];
            $totalPower = 0;

            foreach ($this->catalog->units() as $definition) {
                $code = (string) ($definition['code'] ?? '');
                $row = $rows->firstWhere('unit_code', $code);
                $quantity = $row === null ? 0 : (int) $row->quantity;
                $power = (int) ($definition['power'] ?? 0);
                $totalPower += $quantity * $power;
                $units[] = [
                    'code' => $code,
                    'name_key' => (string) ($definition['name_key'] ?? $code),
                    'unit_class' => (string) ($definition['class'] ?? 'infantry'),
                    'quantity' => $quantity,
                    'power_per_unit' => $power,
                    'total_power' => $quantity * $power,
                ];
            }

            return [
                'player' => $bootstrap['player'],
                'world' => $bootstrap['world'],
                'city' => [
                    'id' => (string) $city->getKey(),
                    'name_key' => (string) $city->name_key,
                ],
                'units' => $units,
                'total_power' => $totalPower,
                'server_time' => $this->clock->now()->format(DATE_ATOM),
            ];
        });
    }
}
