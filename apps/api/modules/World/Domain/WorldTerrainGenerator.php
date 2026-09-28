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
        $totalTiles = $width * $height;

        // Deterministic 32-bit integer seed hash
        $seedHash = ((int) hexdec(substr(hash('sha256', $seed), 0, 7))) & 0x7FFFFFFF;

        // Calculate target tile counts per terrain to match configured weights
        $targetCounts = [];
        $assignedCount = 0;
        $terrainNames = array_keys($weights);
        $lastTerrain = end($terrainNames);

        foreach ($weights as $name => $weight) {
            if ($name === $lastTerrain) {
                $targetCounts[$name] = max(0, $totalTiles - $assignedCount);
            } else {
                $count = (int) round(($weight / $totalWeight) * $totalTiles);
                $targetCounts[$name] = $count;
                $assignedCount += $count;
            }
        }

        // Generate grid coordinates, keys, and coherent terrain suitability scores
        $cells = [];
        $tileIndex = 0;

        for ($y = $minY; $y < $minY + $height; $y++) {
            for ($x = $minX; $x < $minX + $width; $x++) {
                $key = hash('sha256', $seed.'|'.$x.'|'.$y);

                // Multi-octave coherent noise channels
                $elevation = $this->fbm((float) $x * 0.08, (float) $y * 0.08, $seedHash, 3);
                $moisture = $this->fbm((float) $x * 0.1, (float) $y * 0.1, $seedHash + 4321, 3);
                $riverNoise = abs($this->fbm((float) $x * 0.05, (float) $y * 0.05, $seedHash + 8765, 2) * 2.0 - 1.0);

                // Road network: arterial paths across regional corridors
                $distToAxisX = min(abs($x % 16), 16 - abs($x % 16));
                $distToAxisY = min(abs($y % 16), 16 - abs($y % 16));
                $roadScore = 1.0 - min($distToAxisX, $distToAxisY) / 8.0;

                $cells[$tileIndex] = [
                    'index' => $tileIndex,
                    'x' => $x,
                    'y' => $y,
                    'key' => $key,
                    'elevation' => $elevation,
                    'moisture' => $moisture,
                    'river' => 1.0 - $riverNoise,
                    'road' => max(0.0, $roadScore),
                    'terrain' => '',
                ];
                $tileIndex++;
            }
        }

        // Assign specialized terrain types coherently based on their environmental affinity
        $remainingIndices = array_keys($cells);
        $terrainAssignments = array_fill(0, $totalTiles, '');

        // 1. Road assignment (if requested in weights)
        $roadCount = $targetCounts['road'] ?? 0;
        if ($roadCount > 0) {
            $count = min($roadCount, count($remainingIndices));
            usort($remainingIndices, static fn (int $a, int $b): int => $cells[$b]['road'] <=> $cells[$a]['road']);
            $chosen = array_splice($remainingIndices, 0, $count);
            foreach ($chosen as $idx) {
                $terrainAssignments[$idx] = 'road';
            }
        }

        // 2. River assignment (continuous water corridors)
        $riverCount = $targetCounts['river'] ?? 0;
        if ($riverCount > 0) {
            $count = min($riverCount, count($remainingIndices));
            usort($remainingIndices, static fn (int $a, int $b): int => $cells[$b]['river'] <=> $cells[$a]['river']);
            $chosen = array_splice($remainingIndices, 0, $count);
            foreach ($chosen as $idx) {
                $terrainAssignments[$idx] = 'river';
            }
        }

        // 3. Mountains assignment (highest elevation peaks and ridges)
        $mountainsCount = $targetCounts['mountains'] ?? 0;
        if ($mountainsCount > 0) {
            $count = min($mountainsCount, count($remainingIndices));
            usort($remainingIndices, static fn (int $a, int $b): int => $cells[$b]['elevation'] <=> $cells[$a]['elevation']);
            $chosen = array_splice($remainingIndices, 0, $count);
            foreach ($chosen as $idx) {
                $terrainAssignments[$idx] = 'mountains';
            }
        }

        // 4. Hills assignment (foothills and elevated slopes)
        $hillsCount = $targetCounts['hills'] ?? 0;
        if ($hillsCount > 0) {
            $count = min($hillsCount, count($remainingIndices));
            usort($remainingIndices, static fn (int $a, int $b): int => $cells[$b]['elevation'] <=> $cells[$a]['elevation']);
            $chosen = array_splice($remainingIndices, 0, $count);
            foreach ($chosen as $idx) {
                $terrainAssignments[$idx] = 'hills';
            }
        }

        // 5. Forest assignment (lush vegetation and high moisture)
        $forestCount = $targetCounts['forest'] ?? 0;
        if ($forestCount > 0) {
            $count = min($forestCount, count($remainingIndices));
            usort($remainingIndices, static fn (int $a, int $b): int => $cells[$b]['moisture'] <=> $cells[$a]['moisture']);
            $chosen = array_splice($remainingIndices, 0, $count);
            foreach ($chosen as $idx) {
                $terrainAssignments[$idx] = 'forest';
            }
        }

        // 6. Generic/Plains or any other terrain categories
        foreach ($targetCounts as $name => $targetCount) {
            if (in_array($name, ['road', 'river', 'mountains', 'hills', 'forest'], true)) {
                continue;
            }
            $count = min($targetCount, count($remainingIndices));
            $chosen = array_splice($remainingIndices, 0, $count);
            foreach ($chosen as $idx) {
                $terrainAssignments[$idx] = $name;
            }
        }

        // Any leftover gets filled with the first/default terrain
        $fallbackTerrain = array_key_first($weights) ?? 'plains';
        foreach ($remainingIndices as $idx) {
            $terrainAssignments[$idx] = $fallbackTerrain;
        }

        $tiles = [];
        for ($i = 0; $i < $totalTiles; $i++) {
            $tiles[] = [
                'x' => $cells[$i]['x'],
                'y' => $cells[$i]['y'],
                'terrain' => $terrainAssignments[$i] !== '' ? $terrainAssignments[$i] : $fallbackTerrain,
                'generation_key' => $cells[$i]['key'],
            ];
        }

        return $tiles;
    }

    private function fbm(float $x, float $y, int $seedOffset, int $octaves): float
    {
        $val = 0.0;
        $amp = 0.5;
        $freq = 1.0;
        $totalAmp = 0.0;

        for ($o = 0; $o < $octaves; $o++) {
            $val += $amp * $this->smoothNoise2D($x * $freq, $y * $freq, $seedOffset + $o * 1013);
            $totalAmp += $amp;
            $amp *= 0.5;
            $freq *= 2.0;
        }

        return $totalAmp > 0 ? $val / $totalAmp : $val;
    }

    private function smoothNoise2D(float $x, float $y, int $seedOffset): float
    {
        $ix = (int) floor($x);
        $iy = (int) floor($y);
        $fx = $x - $ix;
        $fy = $y - $iy;

        // Cubic Hermite spline interpolation: 3t^2 - 2t^3
        $wx = $fx * $fx * (3.0 - 2.0 * $fx);
        $wy = $fy * $fy * (3.0 - 2.0 * $fy);

        $v00 = $this->hash2D($ix, $iy, $seedOffset);
        $v10 = $this->hash2D($ix + 1, $iy, $seedOffset);
        $v01 = $this->hash2D($ix, $iy + 1, $seedOffset);
        $v11 = $this->hash2D($ix + 1, $iy + 1, $seedOffset);

        $top = $v00 + $wx * ($v10 - $v00);
        $bot = $v01 + $wx * ($v11 - $v01);

        return $top + $wy * ($bot - $top);
    }

    private function hash2D(int $x, int $y, int $seedOffset): float
    {
        $h = (($x * 374761393) ^ ($y * 668265263) ^ ($seedOffset * 1013904223)) & 0x7FFFFFFF;
        $h = (($h ^ ($h >> 13)) * 1274126177) & 0x7FFFFFFF;
        $h = ($h ^ ($h >> 16)) & 0x7FFFFFFF;

        return (float) $h / 2147483647.0;
    }
}
