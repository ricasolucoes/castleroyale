<?php

declare(strict_types=1);

namespace Game\World\Application;

use DateTimeImmutable;
use Game\Shared\Domain\Time\Clock;
use Game\Shared\Infrastructure\GameData\GameDataCatalog;
use Game\World\Domain\WorldTerrainGenerator;
use Game\World\Infrastructure\World;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class WorldGenerationService
{
    public function __construct(
        private Clock $clock,
        private GameDataCatalog $catalog,
        private WorldTerrainGenerator $generator,
    ) {}

    /**
     * Generate a world exactly once. Retrying the command is safe and never
     * replaces terrain that may already have gameplay attached to it.
     *
     * @return array{world_id: string, seed: string, regions: int, tiles: int, generated: bool}
     */
    public function generate(World $world): array
    {
        return DB::transaction(function () use ($world): array {
            $worldQuery = World::query()->whereKey($world->getKey());
            $worldQuery->getQuery()->lockForUpdate();
            /** @var World $lockedWorld */
            $lockedWorld = $worldQuery->firstOrFail();
            $existingTiles = DB::table('tiles')->where('world_id', $lockedWorld->getKey())->count();
            if ($existingTiles > 0) {
                return [
                    'world_id' => (string) $lockedWorld->getKey(),
                    'seed' => (string) $lockedWorld->seed,
                    'regions' => DB::table('regions')->where('world_id', $lockedWorld->getKey())->count(),
                    'tiles' => $existingTiles,
                    'generated' => false,
                ];
            }

            $parameters = $this->catalog->worldGeneration();
            $seed = (string) ($lockedWorld->seed !== '' ? $lockedWorld->seed : ($parameters['seed'] ?? $lockedWorld->code));
            $tiles = $this->generator->generate($seed, $parameters);
            $regionWidth = max(1, (int) ($parameters['region_width'] ?? 1));
            $regionHeight = max(1, (int) ($parameters['region_height'] ?? 1));
            $timestamp = $this->clock->now();
            $groups = [];

            foreach ($tiles as $tile) {
                $regionX = $this->floorDivide($tile['x'], $regionWidth);
                $regionY = $this->floorDivide($tile['y'], $regionHeight);
                $key = $regionX.':'.$regionY;
                $groups[$key]['region_x'] = $regionX;
                $groups[$key]['region_y'] = $regionY;
                $groups[$key]['tiles'][] = $tile;
            }

            $regionCount = 0;
            $tileCount = 0;
            foreach ($groups as $group) {
                $regionId = (string) Str::ulid();
                $regionTiles = $group['tiles'];
                $xs = array_column($regionTiles, 'x');
                $ys = array_column($regionTiles, 'y');
                $region = [
                    'region_x' => $group['region_x'],
                    'region_y' => $group['region_y'],
                    'min_x' => min($xs),
                    'max_x' => max($xs),
                    'min_y' => min($ys),
                    'max_y' => max($ys),
                ];

                $this->insertRegion($regionId, (string) $lockedWorld->getKey(), $seed, $region, $timestamp);
                $regionCount++;
                $rows = [];
                foreach ($regionTiles as $tile) {
                    $rows[] = [
                        'id' => (string) Str::ulid(),
                        'world_id' => (string) $lockedWorld->getKey(),
                        'region_id' => $regionId,
                        'x' => $tile['x'],
                        'y' => $tile['y'],
                        'terrain' => $tile['terrain'],
                        'generation_key' => $tile['generation_key'],
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
                DB::table('tiles')->insert($rows);
                $tileCount += count($rows);
            }

            return [
                'world_id' => (string) $lockedWorld->getKey(),
                'seed' => $seed,
                'regions' => $regionCount,
                'tiles' => $tileCount,
                'generated' => true,
            ];
        });
    }

    /**
     * @param array{region_x: int, region_y: int, min_x: int, max_x: int, min_y: int, max_y: int} $region
     */
    private function insertRegion(
        string $id,
        string $worldId,
        string $seed,
        array $region,
        DateTimeImmutable $timestamp,
    ): void {
        $boundary = sprintf(
            'POLYGON((%d %d,%d %d,%d %d,%d %d,%d %d))',
            $region['min_x'],
            $region['min_y'],
            $region['max_x'] + 1,
            $region['min_y'],
            $region['max_x'] + 1,
            $region['max_y'] + 1,
            $region['min_x'],
            $region['max_y'] + 1,
            $region['min_x'],
            $region['min_y'],
        );
        $values = [
            'id' => $id,
            'world_id' => $worldId,
            'code' => sprintf('r_%d_%d', $region['region_x'], $region['region_y']),
            'seed' => $seed,
            'generation_version' => (int) config('game.versions.data', 1),
            'region_x' => $region['region_x'],
            'region_y' => $region['region_y'],
            'min_x' => $region['min_x'],
            'max_x' => $region['max_x'],
            'min_y' => $region['min_y'],
            'max_y' => $region['max_y'],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::insert(
                'insert into regions (id, world_id, code, seed, generation_version, region_x, region_y, min_x, max_x, min_y, max_y, boundary, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ST_GeomFromText(?, 4326), ?, ?)',
                [
                    $values['id'],
                    $values['world_id'],
                    $values['code'],
                    $values['seed'],
                    $values['generation_version'],
                    $region['region_x'],
                    $region['region_y'],
                    $values['min_x'],
                    $values['max_x'],
                    $values['min_y'],
                    $values['max_y'],
                    $boundary,
                    $values['created_at'],
                    $values['updated_at'],
                ],
            );

            return;
        }

        DB::table('regions')->insert([...$values, 'boundary' => $boundary]);
    }

    private function floorDivide(int $value, int $divisor): int
    {
        $quotient = intdiv($value, $divisor);

        return $value < 0 && $value % $divisor !== 0 ? $quotient - 1 : $quotient;
    }
}
