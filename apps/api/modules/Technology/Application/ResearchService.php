<?php

declare(strict_types=1);

namespace Game\Technology\Application;

use DateInterval;
use Game\City\Infrastructure\City;
use Game\City\Infrastructure\CityBuilding;
use Game\Construction\Application\BuildingRequirementEvaluator;
use Game\Construction\Domain\BuildDuration;
use Game\Economy\Application\CityEconomyService;
use Game\Economy\Domain\LedgerParty;
use Game\Identity\Domain\Account;
use Game\Player\Infrastructure\Player;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Domain\Economy\ResourceBundle;
use Game\Shared\Domain\Time\Clock;
use Game\Shared\Infrastructure\GameData\GameDataCatalog;
use Game\Technology\Infrastructure\PlayerTechnology;
use Game\Technology\Infrastructure\ResearchOrder;
use Game\Technology\Interface\Jobs\CompleteResearch;
use Illuminate\Support\Facades\DB;

/**
 * Starts a server-computed research: the debit, the refusal precedence and
 * the timers. Modelled directly on
 * {@see \Game\Construction\Application\BuildingUpgradeService::start()} — the
 * transaction shape, the lock order and the `queue.default !== 'sync'` dispatch
 * guard are copied verbatim, not re-derived.
 */
final readonly class ResearchService
{
    public function __construct(
        private Clock $clock,
        private GameDataCatalog $catalog,
        private CityEconomyService $economy,
        private ResearchCompletionService $completion,
        private BuildingRequirementEvaluator $requirements,
    ) {}

    /**
     * @return array{id: string, technology_code: string, from_level: int, target_level: int, started_at: string, finishes_at: string}
     */
    public function start(Account $account, string $worldId, string $cityId, string $technologyCode, string $idempotencyKey): array
    {
        return DB::transaction(function () use ($account, $worldId, $cityId, $technologyCode, $idempotencyKey): array {
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
            // Otherwise a player whose research finished but whose job has not
            // run yet would be wrongly told RESEARCH_IN_PROGRESS.
            $this->completion->completeOverdueLocked($player, $now);
            // Elapsed production must be credited before the affordability
            // check below reads the city's balance — exactly why
            // BuildingUpgradeService::start() accrues before it checks cost.
            $this->economy->accrueLocked($city, $now);

            $definition = $this->catalog->technology($technologyCode);
            if ($definition === null) {
                throw GameException::of(ErrorCode::TechnologyLocked, 'This technology is not available.');
            }

            // Requirements and the current level are read inside the same lock
            // that will spend the cost — a prerequisite true when the client
            // rendered its sheet may not be true now.
            /** @var array<string, int> $currentTechnologyLevels */
            $currentTechnologyLevels = PlayerTechnology::query()
                ->where('world_id', $worldId)
                ->where('player_id', $player->getKey())
                ->pluck('level', 'technology_code')
                ->map(static fn (mixed $level): int => (int) $level)
                ->all();

            /** @var array<string, int> $currentBuildingLevels */
            $currentBuildingLevels = CityBuilding::query()
                ->where('world_id', $worldId)
                ->where('city_id', $city->getKey())
                ->pluck('level', 'building_code')
                ->map(static fn (mixed $level): int => (int) $level)
                ->all();

            $currentLevel = $currentTechnologyLevels[$technologyCode] ?? 0;
            $targetLevel = $currentLevel + 1;
            $maxLevel = (int) ($definition['max_level'] ?? $currentLevel);

            // Precedence is a decision, not an accident: the permanent reason
            // (maxed) is told before the structural one (locked), before the
            // transient one (in progress), before the economic one (cannot
            // afford).
            if ($targetLevel > $maxLevel) {
                throw GameException::of(ErrorCode::TechnologyMaxLevel, 'This technology has reached its maximum level.');
            }

            $unmet = $this->requirements->unmetFor(
                'technology',
                $technologyCode,
                $targetLevel,
                $currentBuildingLevels,
                $currentTechnologyLevels,
            );
            if ($unmet !== []) {
                throw GameException::of(
                    ErrorCode::TechnologyLocked,
                    'This technology is not available yet.',
                    ['missing' => array_map(static fn (array $requirement): int => $requirement['level'], $unmet)],
                );
            }

            $openOrderQuery = ResearchOrder::query()
                ->where('world_id', $worldId)
                ->where('player_id', $player->getKey());
            $openOrderQuery->getQuery()->whereNull('completed_at');
            if ($openOrderQuery->getQuery()->exists()) {
                throw GameException::of(ErrorCode::ResearchInProgress, 'A research is already in progress.');
            }

            $target = $this->catalog->technologyLevel($technologyCode, $targetLevel);
            if ($target === null) {
                throw GameException::of(ErrorCode::TechnologyLocked, 'This technology level is not available.');
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
                'technology.research',
                $idempotencyKey,
                LedgerParty::system('research'),
            );

            $duration = BuildDuration::scaled(
                (int) ($target['research_time_seconds'] ?? 0),
                (int) config('game.time_scale'),
            );
            $finishesAt = $now->add(new DateInterval('PT'.$duration.'S'));
            $order = ResearchOrder::create([
                'world_id' => $worldId,
                'player_id' => $player->getKey(),
                'city_id' => $city->getKey(),
                'technology_code' => $technologyCode,
                'from_level' => $currentLevel,
                'target_level' => $targetLevel,
                'idempotency_key' => $idempotencyKey,
                'started_at' => $now,
                'finishes_at' => $finishesAt,
            ]);

            if (config('queue.default') !== 'sync') {
                CompleteResearch::dispatch($worldId, (string) $player->getKey(), (string) $order->getKey())
                    ->onQueue('gameplay')
                    ->delay($finishesAt)
                    ->afterCommit();
            }

            return [
                'id' => (string) $order->getKey(),
                'technology_code' => $technologyCode,
                'from_level' => $currentLevel,
                'target_level' => $targetLevel,
                'started_at' => $now->format(DATE_ATOM),
                'finishes_at' => $finishesAt->format(DATE_ATOM),
            ];
        });
    }
}
