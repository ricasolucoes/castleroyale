<?php

declare(strict_types=1);

namespace Game\Construction\Application;

use Game\Construction\Domain\BuildingRequirement;
use Game\Shared\Infrastructure\GameData\GameDataCatalog;

/**
 * Evaluates a building level's data-declared `requirements[]` against a
 * city's current building levels.
 *
 * Generic on purpose: the Palace gate is just the one requirement every
 * non-Palace level happens to carry today. Phase 10 (technology
 * prerequisites) and Phase 12 (training buildings) reuse this evaluator for
 * their own unlock rules without any change here.
 */
final readonly class BuildingRequirementEvaluator
{
    public function __construct(
        private GameDataCatalog $catalog,
    ) {}

    /**
     * @param array<string, int> $currentLevels building_code => level, for one city
     * @return array<string, int> building_code => level it needed
     */
    public function unmet(string $buildingCode, int $targetLevel, array $currentLevels): array
    {
        $missing = [];
        foreach ($this->unmetFor('building', $buildingCode, $targetLevel, $currentLevels, []) as $code => $requirement) {
            $missing[$code] = $requirement['level'];
        }

        return $missing;
    }

    /**
     * The generalised gate: evaluates a building's or a technology's
     * data-declared `requirements[]` at a specific target level against a
     * player's current building AND technology levels.
     *
     * `$type` ('building' or 'technology') selects which catalogue method
     * supplies the target level's own `requirements[]`. Each individual
     * requirement is then checked against the map matching ITS OWN type, so a
     * technology may require a building and a building may require a
     * technology, without either caller needing to know about the other's
     * data (10-CONTEXT.md's cross-module requirement case).
     *
     * @param array<string, int> $currentBuildingLevels building_code => level, for one city
     * @param array<string, int> $currentTechnologyLevels technology_code => level, for one player
     * @return array<string, array{type: string, level: int}> requirement code => what it needed
     */
    public function unmetFor(
        string $type,
        string $code,
        int $targetLevel,
        array $currentBuildingLevels,
        array $currentTechnologyLevels,
    ): array {
        $level = match ($type) {
            'building' => $this->catalog->buildingLevel($code, $targetLevel),
            'technology' => $this->catalog->technologyLevel($code, $targetLevel),
            default => null,
        };

        if ($level === null) {
            return [];
        }

        $rawRequirements = $level['requirements'] ?? null;
        if (! is_array($rawRequirements)) {
            return [];
        }

        $missing = [];
        foreach ($rawRequirements as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $requirement = BuildingRequirement::fromArray($raw);
            if ($requirement === null) {
                continue;
            }

            $currentLevel = match ($requirement->type) {
                'building' => $currentBuildingLevels[$requirement->code] ?? 0,
                'technology' => $currentTechnologyLevels[$requirement->code] ?? 0,
                // 'nobility' and 'player_level' requirements have no owning map
                // yet (Phases 29 and 04 respectively). Skipping them here is
                // correct today because no authored dataset gates on them, but
                // it is a deliberate gap, not an oversight — treating an
                // unrecognised type as satisfied would silently unlock content
                // no subsystem has actually granted the day one is authored.
                default => null,
            };

            if ($currentLevel === null) {
                continue;
            }

            if ($currentLevel < $requirement->level) {
                $missing[$requirement->code] = ['type' => $requirement->type, 'level' => $requirement->level];
            }
        }

        return $missing;
    }
}
