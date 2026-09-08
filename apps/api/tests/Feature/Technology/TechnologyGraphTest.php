<?php

declare(strict_types=1);

use Game\Shared\Domain\Exception\DomainException;
use Game\Shared\Infrastructure\GameData\GameDataCatalog;
use Game\Technology\Domain\TechnologyGraph;

/**
 * @param list<array<string,mixed>> $overrides technologies, in addition to a
 *                                             minimal well-formed baseline
 * @return list<array<string,mixed>>
 */
function technologyFixture(string $code, array $requirements = []): array
{
    return [
        'code' => $code,
        'name_key' => 'technologies.'.$code,
        'description_key' => 'technologies.'.$code.'_desc',
        'category' => 'economy',
        'max_level' => 1,
        'levels' => [
            [
                'level' => 1,
                'cost' => [],
                'research_time_seconds' => 10,
                'requirements' => $requirements,
                'effects' => [],
            ],
        ],
    ];
}

it('gives a technology with no prerequisite tier 0', function (): void {
    $graph = new TechnologyGraph([technologyFixture('a')]);

    expect($graph->tier('a'))->toBe(0);
});

it('gives a technology tier one more than its deepest prerequisite', function (): void {
    // c requires both a (tier 0) and b (tier 1) — c must be tier 2, the
    // max-not-min case. Getting this wrong puts a node in a lane before its
    // own prerequisite.
    $graph = new TechnologyGraph([
        technologyFixture('a'),
        technologyFixture('b', [['type' => 'technology', 'code' => 'a', 'level' => 1]]),
        technologyFixture('c', [
            ['type' => 'technology', 'code' => 'a', 'level' => 1],
            ['type' => 'technology', 'code' => 'b', 'level' => 1],
        ]),
    ]);

    expect($graph->tier('a'))->toBe(0)
        ->and($graph->tier('b'))->toBe(1)
        ->and($graph->tier('c'))->toBe(2);
});

it('ignores building requirements when computing tier', function (): void {
    $graph = new TechnologyGraph([
        technologyFixture('d', [['type' => 'building', 'code' => 'palace', 'level' => 2]]),
    ]);

    expect($graph->tier('d'))->toBe(0)
        ->and($graph->prerequisites('d'))->toBe([]);
});

it('serves prerequisites for a maxed technology', function (): void {
    $maxed = [
        'code' => 'maxed_tech',
        'name_key' => 'technologies.maxed_tech',
        'description_key' => 'technologies.maxed_tech_desc',
        'category' => 'economy',
        'max_level' => 3,
        'levels' => [
            [
                'level' => 1,
                'cost' => [],
                'research_time_seconds' => 10,
                'requirements' => [['type' => 'technology', 'code' => 'root', 'level' => 1]],
                'effects' => [],
            ],
            ['level' => 2, 'cost' => [], 'research_time_seconds' => 10, 'requirements' => [], 'effects' => []],
            ['level' => 3, 'cost' => [], 'research_time_seconds' => 10, 'requirements' => [], 'effects' => []],
        ],
    ];

    $graph = new TechnologyGraph([technologyFixture('root'), $maxed]);

    // TechnologyGraph never takes a "current level" argument at all — it
    // always reads level 1's requirements. This is the case the plan checker
    // caught: nesting prerequisite data only inside a next-level view would
    // make it null once the player has completed the technology.
    expect($graph->prerequisites('maxed_tech'))->toBe([['code' => 'root', 'level' => 1]]);
});

it('throws naming the code rather than hanging on a cyclic dataset', function (): void {
    $graph = new TechnologyGraph([
        technologyFixture('a', [['type' => 'technology', 'code' => 'b', 'level' => 1]]),
        technologyFixture('b', [['type' => 'technology', 'code' => 'a', 'level' => 1]]),
    ]);

    $caught = null;

    try {
        $graph->tier('a');
    } catch (DomainException $exception) {
        $caught = $exception;
    }

    expect($caught)->toBeInstanceOf(DomainException::class)
        ->and($caught->getMessage())->toContain('a')->toContain('b');
});

it('assigns every real authored technology a tier', function (): void {
    $graph = new TechnologyGraph(app(GameDataCatalog::class)->technologies());
    $tiers = $graph->tiers();

    expect($tiers)->not->toBeEmpty();

    foreach ($tiers as $code => $tier) {
        expect($code)->toBeString()->and($tier)->toBeInt()->and($tier)->toBeGreaterThanOrEqual(0);
    }

    // Otherwise 10-01 authored a flat tree and the whole lane layout is untested.
    expect(max($tiers))->toBeGreaterThanOrEqual(1);
});
