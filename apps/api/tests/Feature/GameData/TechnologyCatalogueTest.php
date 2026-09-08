<?php

declare(strict_types=1);

use Game\Shared\Infrastructure\GameData\GameDataCatalog;
use Illuminate\Support\Facades\Artisan;

/**
 * The technology tree is read through the same catalogue that reads buildings
 * (10-01), and `game:import-data` validates and counts it. `gameDataImportTempBundle()`
 * and `gameDataImportCleanTempBundle()` are declared in GameDataImportTest.php —
 * reused here rather than reinvented, per that file's own bad-dataset technique.
 */
it('reads every authored technology from game data', function (): void {
    $technologies = app(GameDataCatalog::class)->technologies();

    expect($technologies)->toBeArray()
        ->and(count($technologies))->toBeGreaterThanOrEqual(16);

    foreach ($technologies as $technology) {
        expect($technology)->toHaveKeys(['code', 'category', 'levels'])
            ->and($technology['levels'])->toHaveCount(3);
    }
});

it('returns null for an unknown technology or an out-of-range level', function (): void {
    $catalog = app(GameDataCatalog::class);

    expect($catalog->technologyLevel('does_not_exist', 1))->toBeNull()
        ->and($catalog->technologyLevel('agriculture', 99))->toBeNull();
});

it('imports the technology catalogue and reports its size', function (): void {
    $exitCode = Artisan::call('game:import-data');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('technologies:');
});

it('names the technology whose levels do not match its max_level', function (): void {
    $tmp = gameDataImportTempBundle();

    try {
        $technologiesPath = $tmp.'/data/technologies.json';
        $technologies = json_decode((string) file_get_contents($technologiesPath), true);

        foreach ($technologies as &$technology) {
            if ($technology['code'] === 'agriculture') {
                $technology['max_level'] = 5;
            }
        }
        unset($technology);

        file_put_contents($technologiesPath, json_encode($technologies, JSON_PRETTY_PRINT));

        config(['game.data_path' => $tmp]);

        $exitCode = Artisan::call('game:import-data');
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('agriculture')
            ->and($output)->toContain('max_level 5');
    } finally {
        gameDataImportCleanTempBundle($tmp);
    }
});
