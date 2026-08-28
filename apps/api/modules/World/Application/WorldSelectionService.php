<?php

declare(strict_types=1);

namespace Game\World\Application;

use Game\Identity\Domain\Account;
use Game\Player\Application\GameBootstrapService;
use Game\Player\Infrastructure\Player;
use Game\Shared\Infrastructure\GameData\GameDataCatalog;
use Game\World\Infrastructure\World;
use Illuminate\Support\Facades\DB;

final readonly class WorldSelectionService
{
    public function __construct(private GameDataCatalog $catalog, private GameBootstrapService $bootstrap) {}

    /**
     * @return array{worlds: list<array{id: string, code: string, name: string, population: int, capacity: int, status: string, has_player: bool}>}
     */
    public function list(Account $account): array
    {
        return DB::transaction(function () use ($account): array {
            $this->ensureDefaultWorld();

            $worldQuery = World::query();
            $worldQuery->getQuery()->orderBy('id');
            $worlds = $worldQuery->get();
            $worldIds = $worlds->modelKeys();
            $playerQuery = Player::query();
            $playerQuery->getQuery()
                ->whereIn('world_id', $worldIds)
                ->where('account_id', $account->getKey());
            $playerWorldIds = $playerQuery->pluck('world_id')
                ->map(static fn (mixed $worldId): string => (string) $worldId)
                ->flip();

            return [
                'worlds' => array_values($worlds->map(static function (World $world) use ($playerWorldIds): array {
                    $population = (int) $world->population;
                    $capacity = (int) $world->capacity;
                    $status = ! $world->is_open
                        ? 'closed'
                        : ($population >= $capacity ? 'full' : 'open');

                    return [
                        'id' => (string) $world->getKey(),
                        'code' => (string) $world->code,
                        'name' => (string) $world->name,
                        'population' => $population,
                        'capacity' => $capacity,
                        'status' => $status,
                        'has_player' => $playerWorldIds->has((string) $world->getKey()),
                    ];
                })->all()),
            ];
        });
    }

    public function ensureDefaultWorld(): World
    {
        $starter = $this->catalog->starter();
        $worldConfig = is_array($starter['world'] ?? null) ? $starter['world'] : [];
        $code = (string) config('game.world.default_code');

        $worldQuery = World::query()->where('code', $code);
        $worldQuery->getQuery()->lockForUpdate();
        /** @var World|null $world */
        $world = $worldQuery->first();
        if ($world !== null) {
            return $world;
        }

        return World::create([
            'code' => $code,
            'name' => (string) ($worldConfig['name'] ?? $code),
            'seed' => (string) ($worldConfig['seed'] ?? config('game.world.generation_seed', $code)),
            'population' => 0,
            'capacity' => max(1, (int) config('game.world.capacity')),
            'spawn_index' => 0,
            'is_open' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function select(Account $account, string $worldId, string $name): array
    {
        return $this->bootstrap->handle($account, $worldId, $name);
    }
}
