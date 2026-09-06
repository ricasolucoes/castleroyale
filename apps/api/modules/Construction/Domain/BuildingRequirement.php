<?php

declare(strict_types=1);

namespace Game\Construction\Domain;

/**
 * A single unlock condition parsed out of game data — one entry of a
 * building level's `requirements[]` array in packages/game-data/data/buildings.json.
 *
 * docs/game-design/buildings.md § The Palace gate: "No building may exceed the
 * Palace level" is expressed as data (`{type: building, code: palace, level: N}`)
 * on every non-Palace level, never as a PHP branch on a building code (ADR-013).
 */
final readonly class BuildingRequirement
{
    private function __construct(
        public string $type,
        public string $code,
        public int $level,
    ) {}

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw): ?self
    {
        $type = $raw['type'] ?? null;
        $code = $raw['code'] ?? null;
        $level = $raw['level'] ?? null;

        // A malformed row is a dataset defect, caught by `game:import-data`. At
        // request time it must not become a 500 on a player's upgrade tap.
        if (! is_string($type) || ! is_string($code) || ! is_int($level) || $level < 1) {
            return null;
        }

        return new self($type, $code, $level);
    }

    /**
     * Whether a city holding `$currentLevel` of this requirement's building
     * satisfies it.
     *
     * Returning `false` for any type other than `building` is deliberate:
     * technology, nobility and player-level requirements have no owning
     * subsystem yet (Phases 10, 29, 04 respectively), so treating one as
     * satisfied here would silently unlock content no subsystem has actually
     * granted. The current dataset only authors `building` requirements, so
     * this branch is inert today — but it fails closed the day it is not.
     */
    public function isSatisfiedBy(int $currentLevel): bool
    {
        return $this->type === 'building' && $currentLevel >= $this->level;
    }
}
