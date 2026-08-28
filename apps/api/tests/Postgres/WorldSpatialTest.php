<?php

declare(strict_types=1);

use Game\World\Application\WorldGenerationService;
use Game\World\Infrastructure\World;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('uses the region GiST index for a polygon overlap query', function (): void {
    $worldId = (string) Str::ulid();
    $regionId = (string) Str::ulid();
    $now = now();
    DB::table('worlds')->insert([
        'id' => $worldId,
        'code' => 'spatial-world',
        'name' => 'Spatial World',
        'seed' => 'spatial-seed',
        'population' => 0,
        'capacity' => 100,
        'spawn_index' => 0,
        'is_open' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::insert(
        'insert into regions (id, world_id, code, seed, generation_version, region_x, region_y, min_x, max_x, min_y, max_y, boundary, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ST_GeomFromText(?, 4326), ?, ?)',
        [$regionId, $worldId, 'r_0_0', 'spatial-seed', 1, 0, 0, 0, 10, 0, 10, 'POLYGON((0 0,10 0,10 10,0 10,0 0))', $now, $now],
    );

    DB::statement('SET LOCAL enable_seqscan = off');
    $plan = DB::select(
        'EXPLAIN (FORMAT TEXT) SELECT id FROM regions WHERE world_id = ? AND ST_Intersects(boundary, ST_GeomFromText(?, 4326))',
        [$worldId, 'POLYGON((1 1,2 1,2 2,1 2,1 1))'],
    );
    $text = implode("\n", array_map(static fn (object $row): string => implode(' ', (array) $row), $plan));

    expect($text)->toContain('regions_boundary_gist');
})->group('postgres');

it('keeps the populated viewport query p95 below 100 milliseconds', function (): void {
    $world = World::create([
        'code' => 'benchmark-world',
        'name' => 'Benchmark World',
        'seed' => 'benchmark-seed',
        'population' => 0,
        'capacity' => 100,
        'spawn_index' => 0,
        'is_open' => true,
    ]);
    $result = app(WorldGenerationService::class)->generate($world);
    expect($result['tiles'])->toBe(4096);

    $durations = [];
    for ($sample = 0; $sample < 20; $sample++) {
        $started = hrtime(true);
        DB::table('tiles')
            ->where('world_id', $world->getKey())
            ->whereBetween('x', [-32, 31])
            ->whereBetween('y', [-32, 31])
            ->orderBy('y')
            ->orderBy('x')
            ->get(['id', 'x', 'y', 'terrain']);
        $durations[] = (hrtime(true) - $started) / 1_000_000;
    }

    sort($durations);
    $p95 = $durations[(int) ceil(count($durations) * 0.95) - 1];

    expect($p95)->toBeLessThan(100.0);
})->group('postgres');

it('uses the composite tile index for bounded coordinate lookup', function (): void {
    $worldId = (string) Str::ulid();
    $regionId = (string) Str::ulid();
    $now = now();
    DB::table('worlds')->insert([
        'id' => $worldId,
        'code' => 'tile-index-world',
        'name' => 'Tile Index World',
        'seed' => 'tile-seed',
        'population' => 0,
        'capacity' => 100,
        'spawn_index' => 0,
        'is_open' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::insert(
        'insert into regions (id, world_id, code, seed, generation_version, region_x, region_y, min_x, max_x, min_y, max_y, boundary, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ST_GeomFromText(?, 4326), ?, ?)',
        [$regionId, $worldId, 'r_0_0', 'tile-seed', 1, 0, 0, 0, 10, 0, 10, 'POLYGON((0 0,10 0,10 10,0 10,0 0))', $now, $now],
    );
    DB::table('tiles')->insert([
        'id' => (string) Str::ulid(),
        'world_id' => $worldId,
        'region_id' => $regionId,
        'x' => 2,
        'y' => 3,
        'terrain' => 'plains',
        'generation_key' => hash('sha256', 'tile-seed|2|3'),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::statement('SET LOCAL enable_seqscan = off');
    $plan = DB::select(
        'EXPLAIN (FORMAT TEXT) SELECT id FROM tiles WHERE world_id = ? AND x BETWEEN ? AND ? AND y BETWEEN ? AND ?',
        [$worldId, 0, 4, 0, 4],
    );
    $text = implode("\n", array_map(static fn (object $row): string => implode(' ', (array) $row), $plan));

    expect($text)->toMatch('/tiles_.*world_id.*x.*y/i');
})->group('postgres');
