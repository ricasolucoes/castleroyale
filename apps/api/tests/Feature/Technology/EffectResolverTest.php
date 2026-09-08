<?php

declare(strict_types=1);

use Game\Technology\Domain\EffectResolver;

it('applies add before multiply', function (): void {
    // (100 + 50) * 1100 / 1000 = 165, not 100 * 1100 / 1000 + 50 = 160.
    $result = EffectResolver::resolve(
        ['production.food' => 100],
        [
            [['target' => 'production.food', 'operation' => 'add', 'value' => 50]],
            [['target' => 'production.food', 'operation' => 'multiply', 'value' => 1100]],
        ],
    );

    expect($result['production.food'])->toBe(165);
});

it('stacks two multipliers additively, not multiplicatively', function (): void {
    $result = EffectResolver::resolve(
        ['production.food' => 100],
        [
            [['target' => 'production.food', 'operation' => 'multiply', 'value' => 1100]],
            [['target' => 'production.food', 'operation' => 'multiply', 'value' => 1100]],
        ],
    );

    expect($result['production.food'])->toBe(120)->not->toBe(121);
});

it('truncates downward', function (): void {
    $result = EffectResolver::resolve(
        ['production.food' => 10],
        [[['target' => 'production.food', 'operation' => 'multiply', 'value' => 1105]]],
    );

    expect($result['production.food'])->toBe(11);
});

it('treats 1000 as the identity', function (): void {
    $result = EffectResolver::resolve(
        ['production.food' => 42],
        [[['target' => 'production.food', 'operation' => 'multiply', 'value' => 1000]]],
    );

    expect($result['production.food'])->toBe(42);
});

it('creates a target the baseline did not seed', function (): void {
    // march.speed is a target 10-01 authors but nothing reads yet — proving the
    // old array_key_exists guard really was removed.
    $result = EffectResolver::resolve(
        ['production.food' => 0],
        [[['target' => 'march.speed', 'operation' => 'add', 'value' => 50]]],
    );

    expect($result)->toHaveKey('march.speed')
        ->and($result['march.speed'])->toBe(50);
});

it('leaves an empty effect set equal to the baseline', function (): void {
    $baseline = ['production.food' => 10, 'storage.wood' => 500];

    expect(EffectResolver::resolve($baseline, []))->toBe($baseline);
});
