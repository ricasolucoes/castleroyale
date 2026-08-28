<?php

declare(strict_types=1);

namespace Game\World\Domain;

use InvalidArgumentException;

final class WorldTerrainGenerator
{
    /**
     * @param array<string, mixed> $parameters
     * @return list<array{x: int, y: int, terrain: string, generation_key: string}>
     */
    public function generate(string $seed, array $parameters): array
    {
        $width = (int) ($parameters['map_width'] ?? 0);
        $height = (int) ($parameters['map_height'] ?? 0);
        $weights = is_array($parameters['terrain'] ?? null) ? $parameters['terrain'] : [];
        if ($width < 1 || $height < 1 || $weights === []) {
            throw new InvalidArgumentException('World generation parameters must define a non-empty map and terrain.');
        }

        $weights = array_map(static fn (mixed $weight): int => (int) $weight, $weights);
        ksort($weights);
        $totalWeight = array_sum($weights);
        if ($totalWeight < 1 || in_array(false, array_map(static fn (int $weight): bool => $weight > 0, $weights), true)) {
            throw new InvalidArgumentException('World terrain weights must be positive.');
        }

        $minX = -intdiv($width, 2);
        $minY = -intdiv($height, 2);
        $tiles = [];

        for ($y = $minY; $y < $minY + $height; $y++) {
            for ($x = $minX; $x < $minX + $width; $x++) {
                $key = hash('sha256', $seed.'|'.$x.'|'.$y);
                $pick = hexdec(substr($key, 0, 8)) % $totalWeight;
                $terrain = '';
                foreach ($weights as $name => $weight) {
                    $pick -= $weight;
                    if ($pick < 0) {
                        $terrain = $name;
                        break;
                    }
                }

                $tiles[] = [
                    'x' => $x,
                    'y' => $y,
                    'terrain' => $terrain,
                    'generation_key' => $key,
                ];
            }
        }

        return $tiles;
    }
}
