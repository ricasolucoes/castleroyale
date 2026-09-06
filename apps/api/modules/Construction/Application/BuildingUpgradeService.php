<?php

declare(strict_types=1);

namespace Game\Construction\Application;

use DateInterval;
use Game\City\Infrastructure\City;
use Game\City\Infrastructure\CityBuilding;
use Game\Construction\Domain\BuildDuration;
use Game\Construction\Infrastructure\ConstructionOrder;
use Game\Construction\Interface\Jobs\CompleteConstruction;
use Game\Economy\Application\CityEconomyService;
use Game\Economy\Domain\LedgerParty;
use Game\Identity\Domain\Account;
use Game\Player\Infrastructure\Player;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Domain\Economy\ResourceBundle;
use Game\Shared\Domain\Time\Clock;
use Game\Shared\Infrastructure\GameData\GameDataCatalog;
use Illuminate\Support\Facades\DB;

final readonly class BuildingUpgradeService
{
    public function __construct(
        private Clock $clock,
        private GameDataCatalog $catalog,
        private CityEconomyService $economy,
        private ConstructionCompletionService $completion,
    ) {}

    /**
     * @return array{id: string, building_code: string, from_level: int, target_level: int, started_at: string, finishes_at: string}
     */
    public function start(Account $account, string $worldId, string $cityId, string $buildingCode, string $idempotencyKey): array
    {
        return DB::transaction(function () use ($account, $worldId, $cityId, $buildingCode, $idempotencyKey): array {
            /** @var Player|null $player */
            $player = Player::query()
                ->where('world_id', $worldId)
                ->where('account_id', $account->getKey())
                ->first();

            $cityQuery = City::query()
                ->where('world_id', $worldId)
                ->where('player_id', $player?->getKey())
                ->whereKey($cityId);
            $cityQuery->getQuery()->lockForUpdate();
            /** @var City|null $city */
            $city = $cityQuery->first();

            if ($player === null || $city === null) {
                throw GameException::of(ErrorCode::CityNotOwned, 'The city is not owned by this player.');
            }

            $now = $this->clock->now();
            $this->completion->completeOverdueLocked($city, $now);
            $this->economy->accrueLocked($city, $now);

            $buildingQuery = CityBuilding::query()
                ->where('world_id', $worldId)
                ->where('city_id', $city->getKey())
                ->where('building_code', $buildingCode);
            $buildingQuery->getQuery()->lockForUpdate();
            /** @var CityBuilding|null $building */
            $building = $buildingQuery->first();
            $definition = $this->catalog->building($buildingCode);

            if ($building === null || $definition === null) {
                throw GameException::of(ErrorCode::BuildingRequirementsNotMet, 'This building is not available.');
            }

            $fromLevel = (int) $building->level;
            $targetLevel = $fromLevel + 1;
            $maxLevel = (int) ($definition['max_level'] ?? $fromLevel);
            if ($targetLevel > $maxLevel) {
                throw GameException::of(ErrorCode::BuildingMaxLevel, 'This building has reached its maximum level.');
            }

            $activeOrdersQuery = ConstructionOrder::query()
                ->where('world_id', $worldId)
                ->where('city_id', $city->getKey());
            $activeOrdersQuery->getQuery()->whereNull('completed_at');
            $activeOrders = $activeOrdersQuery->getQuery()->count();
            if ($activeOrders >= max(1, (int) config('game.limits.max_build_queue_slots'))) {
                throw GameException::of(ErrorCode::BuildQueueFull, 'The construction queue is full.');
            }

            $sameBuildingBusyQuery = ConstructionOrder::query()
                ->where('world_id', $worldId)
                ->where('city_id', $city->getKey())
                ->where('building_code', $buildingCode);
            $sameBuildingBusyQuery->getQuery()->whereNull('completed_at');
            $sameBuildingIsBusy = $sameBuildingBusyQuery->getQuery()->exists();
            if ($sameBuildingIsBusy) {
                throw GameException::of(ErrorCode::CityBusy, 'This building is already being upgraded.');
            }

            $target = $this->catalog->buildingLevel($buildingCode, $targetLevel);
            if ($target === null) {
                throw GameException::of(ErrorCode::BuildingRequirementsNotMet, 'This upgrade is not available.');
            }

            $rawCost = is_array($target['cost'] ?? null) ? $target['cost'] : [];
            $cost = ResourceBundle::fromArray(array_map(static fn (mixed $value): int => (int) $value, $rawCost));
            $balances = ResourceBundle::fromArray($this->economy->balances($city));
            if (! $balances->covers($cost)) {
                throw GameException::of(
                    ErrorCode::InsufficientResources,
                    'Not enough resources.',
                    ['missing' => array_map(static fn ($resource): string => $resource->value, $balances->shortfallAgainst($cost))],
                );
            }

            $this->economy->debitLocked(
                $city,
                $cost,
                'building.upgrade',
                $idempotencyKey,
                LedgerParty::system('construction'),
            );
            $duration = BuildDuration::scaled(
                (int) ($target['build_time_seconds'] ?? 0),
                (int) config('game.time_scale'),
            );
            $finishesAt = $now->add(new DateInterval('PT'.$duration.'S'));
            $order = ConstructionOrder::create([
                'world_id' => $worldId,
                'city_id' => $city->getKey(),
                'building_code' => $buildingCode,
                'from_level' => $fromLevel,
                'target_level' => $targetLevel,
                'idempotency_key' => $idempotencyKey,
                'started_at' => $now,
                'finishes_at' => $finishesAt,
            ]);

            if (config('queue.default') !== 'sync') {
                CompleteConstruction::dispatch($worldId, $city->getKey(), $order->getKey())
                    ->onQueue('gameplay')
                    ->delay($finishesAt)
                    ->afterCommit();
            }

            return [
                'id' => (string) $order->getKey(),
                'building_code' => $buildingCode,
                'from_level' => $fromLevel,
                'target_level' => $targetLevel,
                'started_at' => $now->format(DATE_ATOM),
                'finishes_at' => $finishesAt->format(DATE_ATOM),
            ];
        });
    }
}
