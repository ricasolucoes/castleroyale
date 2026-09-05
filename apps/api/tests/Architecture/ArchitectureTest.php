<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Architecture rules
|--------------------------------------------------------------------------
|
| These are the boundaries of the modular monolith, enforced automatically so
| they survive contact with deadlines. Every rule here corresponds to a
| decision recorded in docs/adr/.
|
*/

arch('domain layer stays free of the framework')
    ->expect('Game')
    ->toOnlyUse([
        'Game',
        // PHP itself.
        'DateTimeImmutable', 'DateTimeInterface', 'DateTimeZone', 'DateInterval',
        'RuntimeException', 'InvalidArgumentException', 'LogicException',
        'Stringable', 'JsonSerializable', 'Countable', 'IteratorAggregate',
        'ArrayIterator', 'Traversable', 'Throwable',
    ])
    ->ignoring([
        'Game\Shared\Interface',
        'Game\Shared\Infrastructure',
        'Game\Shared\Application',
        'Game\Alliance\Application',
        'Game\Alliance\Infrastructure',
        'Game\Alliance\Interface',
        'Game\Platform\Interface',
        'Game\City\Application',
        'Game\City\Infrastructure',
        'Game\City\Interface',
        'Game\Construction\Application',
        'Game\Construction\Infrastructure',
        'Game\Construction\Interface',
        'Game\Economy\Application',
        'Game\Economy\Infrastructure',
        'Game\Identity\Application',
        'Game\Identity\Domain',
        'Game\Identity\Infrastructure',
        'Game\Identity\Interface',
        'Game\Military\Application',
        'Game\Military\Infrastructure',
        'Game\Military\Interface',
        'Game\Player\Application',
        'Game\Player\Infrastructure',
        'Game\Player\Interface',
        'Game\World\Infrastructure',
        'Game\World\Application',
        'Game\World\Interface',
        'Game\Gamification\Application',
        'Game\Gamification\Infrastructure',
        'Game\Gamification\Interface',
    ])
    ->group('arch');

arch('nothing debugs in production')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die', 'exit', 'sleep'])
    ->not->toBeUsed()
    ->group('arch');

arch('domain never reaches for global helpers')
    ->expect(['now', 'today', 'request', 'auth', 'session', 'cache', 'config', 'env'])
    ->not->toBeUsedIn('Game\Shared\Domain')
    ->group('arch');

arch('value objects and enums are final')
    ->expect('Game\Shared\Domain')
    ->classes()
    ->toBeFinal()
    ->ignoring('Game\Shared\Domain\Exception')
    ->group('arch');

arch('every domain exception extends the domain base')
    ->expect('Game\Shared\Domain\Exception')
    ->toExtend('Game\Shared\Domain\Exception\DomainException')
    ->ignoring('Game\Shared\Domain\Exception\DomainException')
    ->group('arch');

arch('strict types everywhere')
    ->expect('Game')
    ->toUseStrictTypes()
    ->group('arch');

arch('app namespace stays thin framework glue')
    ->expect('App')
    ->toUseStrictTypes()
    ->group('arch');

arch('controllers are invokable or thin and never reused as services')
    ->expect('Game\Platform\Interface\Http')
    ->classes()
    ->toBeFinal()
    ->group('arch');

arch('laravel preset')
    ->preset()
    ->laravel()
    ->ignoring([
        'Game',
        'App\Providers',
        'App\Http\Controllers\InstitutionalSiteController',
        'App\Http\Controllers\InstitutionalSupportController',
    ])
    ->group('arch');

arch('no security smells')
    ->preset()
    ->security()
    ->group('arch');

it('keeps institutional code out of game domains and gameplay handlers', function (): void {
    $files = array_merge(
        glob(base_path('app/Http/Controllers/Institutional*.php')) ?: [],
        glob(base_path('app/Http/Requests/Institutional*.php')) ?: [],
        glob(base_path('app/Notifications/Institutional*.php')) ?: [],
    );

    foreach ($files as $file) {
        $source = file_get_contents($file);

        expect($source)->not->toMatch('/(?:use|new|app)\\s*\\(?\\s*Game\\\\/');
    }
})->group('arch');

it('routes every ledger write through EconomyLedger::record', function (): void {
    $offenders = [];
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('modules'), RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        if (str_ends_with((string) $file->getPathname(), 'Economy/Infrastructure/EconomyLedger.php')) {
            continue;
        }
        $contents = (string) file_get_contents((string) $file->getPathname());
        if (str_contains($contents, 'EconomyLedger::create(')) {
            $offenders[] = (string) $file->getPathname();
        }
    }

    expect($offenders)->toBe([]);
})->group('arch');

it('never updates or deletes economy_ledger rows from module code', function (): void {
    // A generic "->update(" / "->delete(" file walk is too noisy — those method names
    // are common across unrelated models in the same file — so this checks the one
    // thing that would actually bypass the append-only guard: a raw query builder
    // write against the table name itself.
    $offenders = [];
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('modules'), RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $contents = (string) file_get_contents((string) $file->getPathname());
        if (str_contains($contents, "DB::table('economy_ledger')->update(")
            || str_contains($contents, "DB::table('economy_ledger')->delete(")) {
            $offenders[] = (string) $file->getPathname();
        }
    }

    expect($offenders)->toBe([]);
})->group('arch');
