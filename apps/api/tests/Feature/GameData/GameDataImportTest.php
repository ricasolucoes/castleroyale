<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/**
 * game:import-data — the import-time half of ADR-013's "validated in CI and
 * again on import". Each failing test proves the command names the offending
 * code, not just that a dataset is "invalid".
 *
 * The two failure-path tests call the command through Artisan::call()/output()
 * rather than $this->artisan()->expectsOutputToContain(): Laravel's testing
 * helper backs each expectsOutputToContain() with its own Mockery expectation,
 * and one real output line can only satisfy one of them — asserting two
 * substrings that both live on the same single message line (as they do here)
 * silently fails the second one. A plain string search on the full captured
 * output has no such limit.
 */
function gameDataImportTempBundle(): string
{
    $tmp = sys_get_temp_dir().'/gamedata-'.uniqid();
    $realDataDir = (string) config('game.data_path').'/data';
    $tmpDataDir = $tmp.'/data';

    mkdir($tmpDataDir, 0o777, true);

    foreach (glob($realDataDir.'/*.json') ?: [] as $file) {
        copy($file, $tmpDataDir.'/'.basename($file));
    }

    return $tmp;
}

function gameDataImportCleanTempBundle(string $tmp): void
{
    foreach (glob($tmp.'/data/*.json') ?: [] as $file) {
        unlink($file);
    }
    rmdir($tmp.'/data');
    rmdir($tmp);
}

it('imports the shipped bundle without a single problem', function (): void {
    $this->artisan('game:import-data')
        ->expectsOutputToContain('buildings: 18 definitions')
        ->assertExitCode(0);
});

it('names the building whose palace gate is missing', function (): void {
    $tmp = gameDataImportTempBundle();

    try {
        $buildingsPath = $tmp.'/data/buildings.json';
        $buildings = json_decode((string) file_get_contents($buildingsPath), true);

        foreach ($buildings as &$building) {
            if ($building['code'] !== 'barracks') {
                continue;
            }

            foreach ($building['levels'] as &$level) {
                if ($level['level'] === 2) {
                    $level['requirements'] = [];
                }
            }
        }
        unset($building, $level);

        file_put_contents($buildingsPath, json_encode($buildings, JSON_PRETTY_PRINT));

        config(['game.data_path' => $tmp]);

        $exitCode = Artisan::call('game:import-data');
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('barracks')
            ->and($output)->toContain('palace gate');
    } finally {
        gameDataImportCleanTempBundle($tmp);
    }
});

it('names the building with an untranslated name_key', function (): void {
    $tmp = gameDataImportTempBundle();

    try {
        $buildingsPath = $tmp.'/data/buildings.json';
        $buildings = json_decode((string) file_get_contents($buildingsPath), true);

        $buildings[] = [
            'code' => 'observatory',
            'name_key' => 'buildings.observatory',
            'category' => 'support',
            'max_level' => 1,
            'levels' => [
                [
                    'level' => 1,
                    'cost' => [],
                    'build_time_seconds' => 0,
                    'requirements' => [],
                    'effects' => [],
                ],
            ],
        ];

        file_put_contents($buildingsPath, json_encode($buildings, JSON_PRETTY_PRINT));

        config(['game.data_path' => $tmp]);

        $exitCode = Artisan::call('game:import-data');
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('observatory')
            ->and($output)->toContain('en');
    } finally {
        gameDataImportCleanTempBundle($tmp);
    }
});
