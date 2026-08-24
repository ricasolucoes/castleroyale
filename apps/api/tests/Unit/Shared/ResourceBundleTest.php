<?php

declare(strict_types=1);

use Game\Shared\Domain\Economy\ResourceBundle;
use Game\Shared\Domain\Economy\ResourceType;
use Game\Shared\Domain\Exception\InvalidResourceAmount;

it('treats absent resources as zero', function (): void {
    $bundle = ResourceBundle::fromArray(['wood' => 100]);

    expect($bundle->get(ResourceType::Wood)->value)->toBe(100)
        ->and($bundle->get(ResourceType::Gold)->value)->toBe(0);
});

it('covers a cost it can fully pay', function (): void {
    $treasury = ResourceBundle::fromArray(['wood' => 100, 'stone' => 50]);
    $cost = ResourceBundle::fromArray(['wood' => 100, 'stone' => 50]);

    expect($treasury->covers($cost))->toBeTrue();
});

it('does not cover a cost it is one unit short of', function (): void {
    $treasury = ResourceBundle::fromArray(['wood' => 99]);
    $cost = ResourceBundle::fromArray(['wood' => 100]);

    expect($treasury->covers($cost))->toBeFalse();
});

it('reports exactly which resources are short', function (): void {
    $treasury = ResourceBundle::fromArray(['wood' => 10, 'iron' => 0]);
    $cost = ResourceBundle::fromArray(['wood' => 50, 'iron' => 20, 'stone' => 0]);

    expect($treasury->shortfallAgainst($cost))
        ->toEqualCanonicalizing([ResourceType::Wood, ResourceType::Iron]);
});

it('refuses to spend more than it holds', function (): void {
    ResourceBundle::fromArray(['wood' => 10])
        ->minus(ResourceBundle::fromArray(['wood' => 11]));
})->throws(InvalidResourceAmount::class);

it('round-trips through an array', function (): void {
    $raw = ['food' => 1, 'wood' => 2, 'stone' => 3, 'iron' => 4, 'gold' => 5];

    expect(ResourceBundle::fromArray($raw)->toArray())->toBe($raw);
});

it('adds two bundles componentwise', function (): void {
    $sum = ResourceBundle::fromArray(['wood' => 10, 'gold' => 1])
        ->plus(ResourceBundle::fromArray(['wood' => 5, 'food' => 7]));

    expect($sum->toArray())->toBe([
        'food' => 7, 'wood' => 15, 'stone' => 0, 'iron' => 0, 'gold' => 1,
    ]);
});
