<?php

declare(strict_types=1);

use Game\City\Infrastructure\CityBuilding;
use Game\Identity\Domain\Account;
use Game\Player\Application\GameBootstrapService;
use Game\Shared\Infrastructure\GameData\GameDataCatalog;

it('persists every starter building into a named roster plot', function (): void {
    $account = Account::factory()->create();
    $result = app(GameBootstrapService::class)->handle($account);

    $buildings = CityBuilding::query()
        ->where('world_id', $result['world']['id'])
        ->where('city_id', $result['city']['id'])
        ->orderBy('slot')
        ->get();

    $catalog = app(GameDataCatalog::class);
    $expectedByCode = collect($catalog->starterBuildings())->keyBy('code');

    expect($buildings->pluck('slot')->sort()->values()->all())
        ->toBe(['plot_01', 'plot_02', 'plot_03', 'plot_04', 'plot_05']);

    foreach ($buildings as $building) {
        expect($building->slot)->toBe($expectedByCode[$building->building_code]['slot']);
    }
});

it('exposes a fixed roster that does not depend on what is built', function (): void {
    $roster = app(GameDataCatalog::class)->citySlots();

    expect($roster)->toHaveCount(18)
        ->and($roster[0])->toBe('plot_01')
        ->and($roster[17])->toBe('plot_18')
        ->and($roster)->toBe(array_unique($roster));
});

it('keeps the slot roster out of PHP', function (): void {
    $modulesPath = __DIR__.'/../../../modules';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($modulesPath, FilesystemIterator::SKIP_DOTS),
    );

    $offenders = [];
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        if ($contents !== false && preg_match('/plot_\d{2}/', $contents) === 1) {
            $offenders[] = $file->getPathname();
        }
    }

    expect($offenders)->toBe([]);
})->group('arch');
