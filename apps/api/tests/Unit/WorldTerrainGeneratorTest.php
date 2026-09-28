<?php

declare(strict_types=1);

use Game\World\Domain\WorldTerrainGenerator;

it('generates byte-identical terrain for the same seed and parameters', function (): void {
    $parameters = [
        'map_width' => 8,
        'map_height' => 8,
        'terrain' => ['plains' => 700, 'forest' => 300],
    ];
    $generator = new WorldTerrainGenerator;

    $first = json_encode($generator->generate('seed-001', $parameters), JSON_THROW_ON_ERROR);
    $second = json_encode($generator->generate('seed-001', $parameters), JSON_THROW_ON_ERROR);

    expect($first)->toBe($second);
});

it('changes terrain keys when the seed changes', function (): void {
    $parameters = [
        'map_width' => 4,
        'map_height' => 4,
        'terrain' => ['plains' => 1000],
    ];
    $generator = new WorldTerrainGenerator;

    expect($generator->generate('seed-a', $parameters)[0]['generation_key'])
        ->not->toBe($generator->generate('seed-b', $parameters)[0]['generation_key']);
});

it('generates all configured terrain types according to weights', function (): void {
    $parameters = [
        'map_width' => 16,
        'map_height' => 16,
        'terrain' => [
            'plains' => 600,
            'forest' => 160,
            'hills' => 100,
            'mountains' => 70,
            'river' => 40,
            'road' => 30,
        ],
    ];
    $generator = new WorldTerrainGenerator;
    $tiles = $generator->generate('seed-distribution-01', $parameters);

    $terrains = array_unique(array_column($tiles, 'terrain'));
    sort($terrains);

    expect($terrains)->toBe(['forest', 'hills', 'mountains', 'plains', 'river', 'road']);
    expect(count($tiles))->toBe(256);
});

it('produces spatially coherent terrain clusters rather than isolated noise', function (): void {
    $parameters = [
        'map_width' => 32,
        'map_height' => 32,
        'terrain' => [
            'plains' => 600,
            'forest' => 200,
            'mountains' => 200,
        ],
    ];
    $generator = new WorldTerrainGenerator;
    $tiles = $generator->generate('seed-coherence-test', $parameters);

    // Build coordinate lookup table
    $grid = [];
    foreach ($tiles as $tile) {
        $grid[$tile['x']][$tile['y']] = $tile['terrain'];
    }

    // Count same-type orthogonal neighbors for forest and mountain tiles
    $matchingNeighbors = 0;
    $totalNonPlains = 0;

    foreach ($tiles as $tile) {
        if ($tile['terrain'] === 'plains') {
            continue;
        }
        $totalNonPlains++;
        $x = $tile['x'];
        $y = $tile['y'];
        $type = $tile['terrain'];

        foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dx, $dy]) {
            if (($grid[$x + $dx][$y + $dy] ?? null) === $type) {
                $matchingNeighbors++;
            }
        }
    }

    // In coherent terrain, non-plains tiles have a significantly higher neighbor clustering rate
    $averageMatchingNeighbors = $matchingNeighbors / max(1, $totalNonPlains);
    expect($averageMatchingNeighbors)->toBeGreaterThan(1.0);
});
