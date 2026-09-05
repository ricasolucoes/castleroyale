<?php

declare(strict_types=1);

namespace Game\Player\Application;

use Game\City\Infrastructure\City;
use Game\City\Infrastructure\CityBuilding;
use Game\Economy\Infrastructure\EconomyLedger;
use Game\Identity\Domain\Account;
use Game\Player\Domain\PlayerNamePolicy;
use Game\Player\Infrastructure\Player;
use Game\Shared\Application\Error\ErrorCode;
use Game\Shared\Application\Error\GameException;
use Game\Shared\Domain\Time\Clock;
use Game\Shared\Infrastructure\GameData\GameDataCatalog;
use Game\World\Infrastructure\World;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class GameBootstrapService
{
    public function __construct(private Clock $clock, private GameDataCatalog $catalog) {}

    /**
     * @return array{
     *     player: array<string, mixed>,
     *     world: array<string, mixed>,
     *     city: array<string, mixed>,
     *     versions: array<string, mixed>,
     *     realtime: array<string, mixed>
     * }
     */
    public function handle(Account $account, ?string $worldId = null, ?string $requestedName = null): array
    {
        try {
            return DB::transaction(function () use ($account, $worldId, $requestedName): array {
                $starter = $this->catalog->starter();
                $worldConfig = is_array($starter['world'] ?? null) ? $starter['world'] : [];
                $playerConfig = is_array($starter['player'] ?? null) ? $starter['player'] : [];
                $cityConfig = is_array($starter['city'] ?? null) ? $starter['city'] : [];
                $worldCode = (string) config('game.world.default_code');

                $worldQuery = World::query();
                if ($worldId !== null) {
                    $worldQuery->whereKey($worldId);
                } else {
                    $worldQuery->where('code', $worldCode);
                }
                $worldQuery->getQuery()->lockForUpdate();
                /** @var World|null $world */
                $world = $worldQuery->first();
                if ($world === null) {
                    if ($worldId !== null) {
                        throw GameException::of(ErrorCode::NotFound, 'The selected world does not exist.');
                    }

                    $world = World::create([
                        'code' => $worldCode,
                        'name' => (string) ($worldConfig['name'] ?? $worldCode),
                        'seed' => (string) ($worldConfig['seed'] ?? config('game.world.generation_seed', $worldCode)),
                        'population' => 0,
                        'capacity' => max(1, (int) config('game.world.capacity')),
                        'spawn_index' => 0,
                        'is_open' => true,
                    ]);
                }

                if ($worldId !== null && ! $world->is_open) {
                    throw GameException::of(ErrorCode::WorldClosed, 'This world is closed.');
                }

                if ($worldId !== null && (int) $world->population >= (int) $world->capacity) {
                    throw GameException::of(ErrorCode::WorldFull, 'This world is full.');
                }

                /** @var Player|null $player */
                $player = Player::query()
                    ->where('world_id', $world->getKey())
                    ->where('account_id', $account->getKey())
                    ->first();

                if (! $world->is_open && $player === null) {
                    throw GameException::of(ErrorCode::WorldClosed, 'This world is closed.');
                }

                if ($player !== null && ($worldId !== null || $requestedName !== null)) {
                    throw GameException::of(ErrorCode::Conflict, 'This account already has a player in this world.');
                }

                if ($player === null && (int) $world->population >= (int) $world->capacity) {
                    throw GameException::of(ErrorCode::WorldFull, 'This world is full.');
                }

                if ($player === null) {
                    $playerName = $requestedName === null
                        ? $this->availableDefaultName((string) ($playerConfig['default_name'] ?? 'Governor'), $world)
                        : $this->validatedName($requestedName);
                    $nameQuery = Player::query()
                        ->where('world_id', $world->getKey())
                        ->where('name', $playerName);
                    if ($nameQuery->getQuery()->exists()) {
                        throw GameException::of(ErrorCode::Conflict, 'That player name is already in use in this world.');
                    }

                    $player = Player::create([
                        'world_id' => $world->getKey(),
                        'account_id' => $account->getKey(),
                        'name' => $playerName,
                    ]);
                }

                /** @var City|null $city */
                $city = City::query()
                    ->where('world_id', $world->getKey())
                    ->where('player_id', $player->getKey())
                    ->first();

                if ($city === null) {
                    $origin = is_array($cityConfig['origin'] ?? null) ? $cityConfig['origin'] : [];
                    $spawnStep = (int) ($worldConfig['spawn_step'] ?? 1);
                    $spawnIndex = (int) $world->spawn_index;
                    $cityX = (int) ($origin['x'] ?? 0) + ($spawnIndex * $spawnStep);
                    $cityY = (int) ($origin['y'] ?? 0);
                    $occupiedQuery = City::query()
                        ->where('world_id', $world->getKey())
                        ->where('x', $cityX)
                        ->where('y', $cityY);
                    if ($occupiedQuery->getQuery()->exists()) {
                        throw GameException::of(ErrorCode::TileOccupied, 'The city tile is already occupied.');
                    }
                    $resources = $this->catalog->starterValues('resources');
                    $capacity = $this->catalog->starterValues('capacity');

                    try {
                        $city = City::create([
                            'world_id' => $world->getKey(),
                            'player_id' => $player->getKey(),
                            'name_key' => (string) ($cityConfig['name_key'] ?? 'city.starter_name'),
                            'x' => $cityX,
                            'y' => $cityY,
                            'last_accrued_at' => $this->clock->now(),
                            ...$resources,
                            'food_capacity' => $capacity['food'],
                            'wood_capacity' => $capacity['wood'],
                            'stone_capacity' => $capacity['stone'],
                            'iron_capacity' => $capacity['iron'],
                            'gold_capacity' => $capacity['gold'],
                        ]);
                    } catch (QueryException $exception) {
                        // The SELECT above cannot see a row another transaction commits between the
                        // check and this INSERT. The unique index is the authority; translate its
                        // violation instead of leaking a 500.
                        //
                        // We match on the index name rather than re-querying, because PostgreSQL
                        // aborts the whole transaction after a constraint violation and any follow-up
                        // query would fail with 25P02.
                        $message = $exception->getMessage();
                        $isTileConflict = str_contains($message, 'cities_world_id_x_y_unique')
                            || str_contains($message, 'cities.world_id, cities.x, cities.y');

                        if (in_array($exception->getCode(), ['23000', '23505'], true) && $isTileConflict) {
                            throw GameException::of(ErrorCode::TileOccupied, 'The city tile is already occupied.');
                        }

                        throw $exception;
                    }

                    foreach ($this->catalog->starterBuildings() as $building) {
                        CityBuilding::create([
                            'world_id' => $world->getKey(),
                            'city_id' => $city->getKey(),
                            'slot' => $building['slot'],
                            'building_code' => $building['code'],
                            'level' => $building['level'],
                        ]);
                    }

                    foreach ($resources as $resource => $amount) {
                        if ($amount === 0) {
                            continue;
                        }

                        EconomyLedger::create([
                            'world_id' => $world->getKey(),
                            'city_id' => $city->getKey(),
                            'resource' => $resource,
                            'amount' => $amount,
                            'overflow_amount' => 0,
                            'reason' => 'starter.grant',
                            'reference' => $account->getKey(),
                            'economy_version' => (int) config('game.versions.economy', 1),
                        ]);
                    }

                    $world->forceFill([
                        'population' => (int) $world->population + 1,
                        'spawn_index' => $spawnIndex + 1,
                    ])->save();
                }

                return [
                    'player' => [
                        'id' => (string) $player->getKey(),
                        'name' => (string) $player->name,
                        'world_id' => (string) $world->getKey(),
                    ],
                    'world' => [
                        'id' => (string) $world->getKey(),
                        'code' => (string) $world->code,
                        'name' => (string) $world->name,
                        'population' => (int) $world->population,
                        'capacity' => (int) $world->capacity,
                        'status' => ! $world->is_open
                            ? 'closed'
                            : ((int) $world->population >= (int) $world->capacity ? 'full' : 'open'),
                    ],
                    'city' => [
                        'id' => (string) $city->getKey(),
                        'world_id' => (string) $world->getKey(),
                        'player_id' => (string) $player->getKey(),
                        'name_key' => (string) $city->name_key,
                        'x' => (int) $city->x,
                        'y' => (int) $city->y,
                    ],
                    'versions' => [
                        'data' => (int) config('game.versions.data'),
                        'economy' => (int) config('game.versions.economy'),
                        'combat' => (int) config('game.versions.combat'),
                    ],
                    'realtime' => [
                        'key' => (string) config('broadcasting.connections.reverb.key', ''),
                        'host' => (string) config('broadcasting.connections.reverb.options.host', ''),
                        'port' => (int) config('broadcasting.connections.reverb.options.port', 443),
                        'scheme' => (string) config('broadcasting.connections.reverb.options.scheme', 'https'),
                        'auth_endpoint' => rtrim((string) config('app.url'), '/').'/broadcasting/auth',
                    ],
                ];
            });
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '23505'], true)) {
                throw GameException::of(ErrorCode::Conflict, 'The player could not be created because it already exists.');
            }

            throw $exception;
        }
    }

    private function availableDefaultName(string $base, World $world): string
    {
        $candidate = $base;
        $suffix = 1;
        $nameQuery = Player::query()
            ->where('world_id', $world->getKey())
            ->where('name', $candidate);
        while ($nameQuery->getQuery()->exists()) {
            $suffix++;
            $candidate = $base.' '.$suffix;
            $nameQuery = Player::query()
                ->where('world_id', $world->getKey())
                ->where('name', $candidate);
        }

        return $candidate;
    }

    private function validatedName(string $name): string
    {
        $deniedNames = config('game.player.denied_names', []);
        if (! is_array($deniedNames)) {
            $deniedNames = [];
        }

        $policy = new PlayerNamePolicy(
            minimumLength: (int) config('game.player.name_min_length'),
            maximumLength: (int) config('game.player.name_max_length'),
            deniedNames: array_values(array_map(static fn (mixed $name): string => (string) $name, $deniedNames)),
        );
        $normalised = $policy->normalise($name);
        if (! $policy->accepts($normalised)) {
            throw GameException::of(ErrorCode::ContentRejected, 'That player name is not allowed.');
        }

        return $normalised;
    }
}
