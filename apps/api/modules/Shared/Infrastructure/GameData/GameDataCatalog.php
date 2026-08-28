<?php

declare(strict_types=1);

namespace Game\Shared\Infrastructure\GameData;

use Game\City\Infrastructure\CityBuilding;
use Game\Shared\Domain\Economy\ResourceType;
use Illuminate\Support\Collection;
use JsonException;
use RuntimeException;

/**
 * Reads the reviewable game-data bundle used by the MVP.
 *
 * The database owns player state, while this adapter keeps balance values out
 * of PHP and gives application services one typed-ish boundary to the JSON
 * content. The import command from the full construction phase can replace
 * this adapter without changing the gameplay API.
 */
final class GameDataCatalog
{
    /**
     * @return array<string, mixed>
     */
    public function starter(): array
    {
        return $this->read('starter.json');
    }

    /**
     * @return array{code: string, map_width: int, map_height: int, region_width: int, region_height: int, seed: string, terrain: array<string, int>}
     */
    public function worldGeneration(): array
    {
        $generation = $this->read('world.json');
        $terrain = $generation['terrain'] ?? null;

        if (
            ! is_string($generation['code'] ?? null)
            || ! is_int($generation['map_width'] ?? null)
            || ! is_int($generation['map_height'] ?? null)
            || ! is_int($generation['region_width'] ?? null)
            || ! is_int($generation['region_height'] ?? null)
            || ! is_string($generation['seed'] ?? null)
            || ! is_array($terrain)
        ) {
            throw new RuntimeException('World generation data is invalid.');
        }

        $weights = [];
        foreach ($terrain as $name => $weight) {
            if (! is_string($name) || ! is_int($weight)) {
                throw new RuntimeException('World terrain distribution is invalid.');
            }
            $weights[$name] = $weight;
        }

        return [
            'code' => $generation['code'],
            'map_width' => $generation['map_width'],
            'map_height' => $generation['map_height'],
            'region_width' => $generation['region_width'],
            'region_height' => $generation['region_height'],
            'seed' => $generation['seed'],
            'terrain' => $weights,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildings(): array
    {
        $rows = $this->read('buildings.json');
        $buildings = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $buildings[] = $row;
            }
        }

        return $buildings;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function building(string $code): ?array
    {
        foreach ($this->buildings() as $building) {
            if (($building['code'] ?? null) === $code) {
                return $building;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buildingLevel(string $code, int $level): ?array
    {
        $building = $this->building($code);
        if ($building === null || ! is_array($building['levels'] ?? null)) {
            return null;
        }

        foreach ($building['levels'] as $levelData) {
            if (is_array($levelData) && (int) ($levelData['level'] ?? 0) === $level) {
                /** @var array<string, mixed> $levelData */
                return $levelData;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function units(): array
    {
        $rows = $this->read('units.json');
        $units = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $units[] = $row;
            }
        }

        return $units;
    }

    /**
     * @return list<array{code: string, quantity: int}>
     */
    public function starterUnits(): array
    {
        $starter = $this->starter();
        $rows = $starter['city'] ?? null;
        $units = is_array($rows) && is_array($rows['units'] ?? null) ? $rows['units'] : [];
        $result = [];

        foreach ($units as $row) {
            if (! is_array($row) || ! isset($row['code'], $row['quantity'])) {
                continue;
            }

            $result[] = ['code' => (string) $row['code'], 'quantity' => (int) $row['quantity']];
        }

        return $result;
    }

    /**
     * @param Collection<int, CityBuilding> $buildings
     * @return array<string, int>
     */
    public function effectsForBuildings(Collection $buildings): array
    {
        $effects = [];
        foreach (ResourceType::all() as $resource) {
            $effects['production.'.$resource->value] = 0;
            $effects['storage.'.$resource->value] = 0;
        }

        foreach ($buildings as $cityBuilding) {
            $level = $this->buildingLevel($cityBuilding->building_code, (int) $cityBuilding->level);
            if ($level === null || ! is_array($level['effects'] ?? null)) {
                continue;
            }

            foreach ($level['effects'] as $effect) {
                if (! is_array($effect)) {
                    continue;
                }

                $target = (string) ($effect['target'] ?? '');
                $operation = (string) ($effect['operation'] ?? '');
                $value = (int) ($effect['value'] ?? 0);

                if ($operation === 'add' && array_key_exists($target, $effects)) {
                    $effects[$target] += $value;
                }
            }
        }

        return $effects;
    }

    /**
     * @return array<string, int>
     */
    public function starterValues(string $section): array
    {
        $starter = $this->starter();
        $city = $starter['city'] ?? null;
        $values = is_array($city) && is_array($city[$section] ?? null) ? $city[$section] : [];
        $result = [];

        foreach (ResourceType::all() as $resource) {
            $result[$resource->value] = (int) ($values[$resource->value] ?? 0);
        }

        return $result;
    }

    /**
     * @return list<array{code: string, level: int}>
     */
    public function starterBuildings(): array
    {
        $starter = $this->starter();
        $city = $starter['city'] ?? null;
        $rows = is_array($city) && is_array($city['buildings'] ?? null) ? $city['buildings'] : [];
        $result = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['code'], $row['level'])) {
                continue;
            }

            $result[] = ['code' => (string) $row['code'], 'level' => (int) $row['level']];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function read(string $file): array
    {
        $path = rtrim((string) config('game.data_path'), '/').'/data/'.$file;
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Game data file is unavailable: '.$file);
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('Game data file must contain an object or array: '.$file);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
