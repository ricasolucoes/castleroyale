<?php

declare(strict_types=1);

namespace Game\Shared\Infrastructure\GameData;

use Game\City\Infrastructure\CityBuilding;
use Game\Shared\Domain\Economy\ResourceType;
use Game\Technology\Domain\EffectResolver;
use Game\Technology\Infrastructure\PlayerTechnology;
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
    public function technologies(): array
    {
        $rows = $this->read('technologies.json');
        $technologies = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $technologies[] = $row;
            }
        }

        return $technologies;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function technology(string $code): ?array
    {
        foreach ($this->technologies() as $technology) {
            if (($technology['code'] ?? null) === $code) {
                return $technology;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function technologyLevel(string $code, int $level): ?array
    {
        $technology = $this->technology($code);
        if ($technology === null || ! is_array($technology['levels'] ?? null)) {
            return null;
        }

        foreach ($technology['levels'] as $levelData) {
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
        return $this->effectsFor($buildings, collect());
    }

    /**
     * @param Collection<int, PlayerTechnology> $technologies
     * @return array<string, int>
     */
    public function effectsForTechnologies(Collection $technologies): array
    {
        return $this->effectsFor(collect(), $technologies);
    }

    /**
     * Every building's and every technology's current-level effects, resolved
     * through the one shared {@see EffectResolver}.
     *
     * Unlike the accumulator this replaces, an effect target the baseline did
     * not seed is no longer silently dropped — 10-01 authors targets like
     * `build.speed` and `march.speed` that no consumer reads yet, and silently
     * discarding an unknown target would hide a typo forever. Consumers simply
     * read only the keys they know.
     *
     * @param Collection<int, CityBuilding> $buildings
     * @param Collection<int, PlayerTechnology> $technologies
     * @return array<string, int>
     */
    public function effectsFor(Collection $buildings, Collection $technologies): array
    {
        $baseline = [];
        foreach (ResourceType::all() as $resource) {
            $baseline['production.'.$resource->value] = 0;
            $baseline['storage.'.$resource->value] = 0;
        }

        $effectSets = [];

        foreach ($buildings as $cityBuilding) {
            $effectSets[] = $this->levelEffects(
                $this->buildingLevel($cityBuilding->building_code, (int) $cityBuilding->level),
            );
        }

        foreach ($technologies as $playerTechnology) {
            $effectSets[] = $this->levelEffects(
                $this->technologyLevel($playerTechnology->technology_code, (int) $playerTechnology->level),
            );
        }

        return EffectResolver::resolve($baseline, $effectSets);
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
     * The fixed build-plot roster.
     *
     * Order is the roster order the client renders in — never re-sorted downstream.
     *
     * @return list<string>
     */
    public function citySlots(): array
    {
        $rows = $this->read('city-slots.json');
        $slots = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! is_string($row['code'] ?? null)) {
                throw new RuntimeException('City slot roster entry is invalid.');
            }

            $slots[] = $row['code'];
        }

        if ($slots === []) {
            throw new RuntimeException('City slot roster is empty.');
        }

        return $slots;
    }

    /**
     * @return list<array{slot: string, code: string, level: int}>
     */
    public function starterBuildings(): array
    {
        $starter = $this->starter();
        $city = $starter['city'] ?? null;
        $rows = is_array($city) && is_array($city['buildings'] ?? null) ? $city['buildings'] : [];
        $roster = $this->citySlots();
        $result = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['code'], $row['level'])) {
                continue;
            }

            $slot = $row['slot'] ?? null;
            if (! is_string($slot) || ! in_array($slot, $roster, true)) {
                throw new RuntimeException('Starter building "'.((string) $row['code']).'" has no valid slot.');
            }

            $result[] = ['slot' => $slot, 'code' => (string) $row['code'], 'level' => (int) $row['level']];
        }

        return $result;
    }

    /**
     * @param array<string, mixed>|null $level
     * @return list<array{target:string,operation:string,value:int}>
     */
    private function levelEffects(?array $level): array
    {
        if ($level === null || ! is_array($level['effects'] ?? null)) {
            return [];
        }

        $effects = [];
        foreach ($level['effects'] as $effect) {
            if (! is_array($effect)) {
                continue;
            }

            $effects[] = [
                'target' => (string) ($effect['target'] ?? ''),
                'operation' => (string) ($effect['operation'] ?? ''),
                'value' => (int) ($effect['value'] ?? 0),
            ];
        }

        return $effects;
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
