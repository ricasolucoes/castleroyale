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
