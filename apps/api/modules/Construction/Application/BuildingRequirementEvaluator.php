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
        $level = $this->catalog->buildingLevel($buildingCode, $targetLevel);
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

            $currentLevel = $currentLevels[$requirement->code] ?? 0;
            if (! $requirement->isSatisfiedBy($currentLevel)) {
                $missing[$requirement->code] = $requirement->level;
            }
        }

        return $missing;
    }
}
