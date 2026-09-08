<?php

declare(strict_types=1);

namespace Game\Technology\Interface\Http;

use Game\City\Infrastructure\CityBuilding;
use Game\Construction\Application\BuildingRequirementEvaluator;
use Game\Identity\Domain\Account;
use Game\Player\Application\GameBootstrapService;
use Game\Player\Infrastructure\Player;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Domain\Economy\ResourceType;
use Game\Shared\Domain\Time\Clock;
use Game\Shared\Infrastructure\GameData\GameDataCatalog;
use Game\Shared\Interface\Http\ApiResponse;
use Game\Technology\Application\ResearchCompletionService;
use Game\Technology\Domain\TechnologyGraph;
use Game\Technology\Infrastructure\PlayerTechnology;
use Game\Technology\Infrastructure\ResearchOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * `GET /game/technologies` — every technology with server-computed state,
 * always carrying its own `tier` and `prerequisites` regardless of the
 * player's current level.
 *
 * `prerequisites` is emitted at the technology level, not nested inside
 * `next_level`, precisely because `next_level` is null at max level and would
 * otherwise silently drop a completed technology's own unlock chain — the
 * defect the plan checker caught in this endpoint's original draft.
 */
final readonly class TechnologyTreeController
{
    public function __construct(
        private Clock $clock,
        private GameBootstrapService $bootstrap,
        private ResearchCompletionService $completion,
        private BuildingRequirementEvaluator $requirements,
        private GameDataCatalog $catalog,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $account = $request->user();
        if (! $account instanceof Account) {
            throw GameException::of(ErrorCode::Unauthenticated, 'Authentication is required.');
        }

        $bootstrap = $this->bootstrap->handle($account);
        $worldId = $bootstrap['world']['id'];
        $playerId = $bootstrap['player']['id'];
        $cityId = $bootstrap['city']['id'];

        $data = DB::transaction(function () use ($worldId, $playerId, $cityId): array {
            $playerQuery = Player::query()
                ->where('world_id', $worldId)
                ->whereKey($playerId);
            $playerQuery->getQuery()->lockForUpdate();
            /** @var Player|null $player */
            $player = $playerQuery->first();

            if ($player === null) {
                throw GameException::of(ErrorCode::CityNotOwned, 'The player was not found.');
            }

            $now = $this->clock->now();
            $this->completion->completeOverdueLocked($player, $now);

            // Constructed once per request — TechnologyGraph memoises tier()
            // internally, but building it once per technology would defeat that.
            $technologies = $this->catalog->technologies();
            $graph = new TechnologyGraph($technologies);

            /** @var array<string, int> $currentTechnologyLevels */
            $currentTechnologyLevels = PlayerTechnology::query()
                ->where('world_id', $worldId)
                ->where('player_id', $playerId)
                ->pluck('level', 'technology_code')
                ->map(static fn (mixed $level): int => (int) $level)
                ->all();

            /** @var array<string, int> $currentBuildingLevels */
            $currentBuildingLevels = CityBuilding::query()
                ->where('world_id', $worldId)
                ->where('city_id', $cityId)
                ->pluck('level', 'building_code')
                ->map(static fn (mixed $level): int => (int) $level)
                ->all();

            $openOrderQuery = ResearchOrder::query()
                ->where('world_id', $worldId)
                ->where('player_id', $playerId);
            $openOrderQuery->getQuery()->whereNull('completed_at');
            /** @var ResearchOrder|null $openOrder */
            $openOrder = $openOrderQuery->first();

            $rows = [];
            foreach ($technologies as $technology) {
                $code = (string) ($technology['code'] ?? '');
                if ($code === '') {
                    continue;
                }

                $level = $currentTechnologyLevels[$code] ?? 0;
                $maxLevel = (int) ($technology['max_level'] ?? $level);
                $isCompleted = $level >= $maxLevel;
                $isInProgress = ! $isCompleted && $openOrder !== null && $openOrder->technology_code === $code;

                $nextLevelData = $isCompleted ? null : $this->catalog->technologyLevel($code, $level + 1);
                $isLocked = ! $isCompleted && $nextLevelData !== null && $this->requirements->unmetFor(
                    'technology',
                    $code,
                    $level + 1,
                    $currentBuildingLevels,
                    $currentTechnologyLevels,
                ) !== [];

                $state = match (true) {
                    $isCompleted => 'completed',
                    $isInProgress => 'in_progress',
                    $isLocked => 'locked',
                    default => 'available',
                };

                $rows[] = [
                    'code' => $code,
                    'name_key' => (string) ($technology['name_key'] ?? $code),
                    'description_key' => (string) ($technology['description_key'] ?? $code.'_desc'),
                    'category' => (string) ($technology['category'] ?? ''),
                    'tier' => $graph->tier($code),
                    'prerequisites' => $graph->prerequisites($code),
                    'level' => $level,
                    'max_level' => $maxLevel,
                    'state' => $state,
                    'next_level' => $nextLevelData === null ? null : [
                        'cost' => $this->cost($nextLevelData),
                        'research_time_seconds' => (int) ($nextLevelData['research_time_seconds'] ?? 0),
                        'effects' => $this->effects($nextLevelData),
                    ],
                ];
            }

            return [
                'technologies' => $rows,
                'research' => $openOrder === null ? null : [
                    'technology_code' => $openOrder->technology_code,
                    'target_level' => (int) $openOrder->target_level,
                    'started_at' => $openOrder->started_at?->toDateTimeImmutable()->format(DATE_ATOM),
                    'finishes_at' => $openOrder->finishes_at?->toDateTimeImmutable()->format(DATE_ATOM),
                ],
                'server_time' => $now->format(DATE_ATOM),
            ];
        });

        return ApiResponse::success($data);
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
     * @param array<string, mixed> $level
     * @return list<array{target: string, operation: string, value: int}>
     */
    private function effects(array $level): array
    {
        $raw = is_array($level['effects'] ?? null) ? $level['effects'] : [];
        $effects = [];
        foreach ($raw as $effect) {
            if (! is_array($effect)) {
                continue;
            }

            $effects[] = [
                'target' => (string) ($effect['target'] ?? ''),
                'operation' => (string) ($effect['operation'] ?? ''),
                'value' => (int) ($effect['value'] ?? 0),
            ];
        }

        return $effects;
    }
}
