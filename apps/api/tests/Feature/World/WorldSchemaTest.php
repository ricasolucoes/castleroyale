<?php

declare(strict_types=1);

use Game\World\Infrastructure\Tile;
use Game\World\Infrastructure\World;
use Illuminate\Database\UniqueConstraintViolationException;

function schemaWorld(string $code): World
{
    return World::create([
        'code' => $code,
        'name' => $code,
        'population' => 0,
        'capacity' => 10,
        'spawn_index' => 0,
        'is_open' => true,
    ]);
}

it('keeps tile coordinates unique inside a world but allows them in another world', function (): void {
    $firstWorld = schemaWorld('schema-world-one');
    $secondWorld = schemaWorld('schema-world-two');
    $region = Illuminate\Support\Str::ulid();
    $otherRegion = Illuminate\Support\Str::ulid();
    $now = now();

    Illuminate\Support\Facades\DB::table('regions')->insert([
        'id' => $region,
        'world_id' => $firstWorld->getKey(),
        'code' => 'r_0_0',
        'seed' => 'schema-seed',
        'generation_version' => 1,
        'region_x' => 0,
        'region_y' => 0,
        'min_x' => 0,
        'max_x' => 1,
        'min_y' => 0,
        'max_y' => 1,
        'boundary' => 'POLYGON((0 0,2 0,2 2,0 2,0 0))',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    Illuminate\Support\Facades\DB::table('regions')->insert([
        'id' => $otherRegion,
        'world_id' => $secondWorld->getKey(),
        'code' => 'r_0_0',
        'seed' => 'schema-seed',
        'generation_version' => 1,
        'region_x' => 0,
        'region_y' => 0,
        'min_x' => 0,
        'max_x' => 1,
        'min_y' => 0,
        'max_y' => 1,
        'boundary' => 'POLYGON((0 0,2 0,2 2,0 2,0 0))',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $attributes = [
        'region_id' => $region,
        'x' => 0,
        'y' => 0,
        'terrain' => 'plains',
        'generation_key' => 'schema-key',
    ];
    Tile::create(['world_id' => $firstWorld->getKey(), ...$attributes]);
    Tile::create(['world_id' => $secondWorld->getKey(), ...$attributes, 'region_id' => $otherRegion]);

    expect(fn () => Tile::create(['world_id' => $firstWorld->getKey(), ...$attributes]))
        ->toThrow(UniqueConstraintViolationException::class);
});
