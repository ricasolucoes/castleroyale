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
    ->ignoring(['Game\Shared\Interface', 'Game\Shared\Infrastructure', 'Game\Shared\Application'])
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
    ->ignoring(['Game', 'App\Providers'])
    ->group('arch');

arch('no security smells')
    ->preset()
    ->security()
    ->group('arch');
