<?php

declare(strict_types=1);

namespace Game\Shared\Interface\Console;

use Game\Shared\Infrastructure\GameData\GameDataCatalog;
use Illuminate\Console\Command;

/**
 * Loads and validates the versioned game-data bundle at import time.
 *
 * ADR-013: the dataset is validated in CI (packages/game-data/src/validate.ts)
 * and again on import. This command is the import-time half. Rules 1-4 below
 * mirror validate.ts; rules 5-7 exist only here because they cross the
 * dataset/catalogue/localization boundary the JS validator does not read.
 *
 * Every message names the offending code — "invalid dataset" is not an
 * acceptable failure message for a designer shipping a tuning pass.
 */
final class ImportGameDataCommand extends Command
{
    private const VALID_CATEGORIES = ['core', 'economy', 'military', 'defense', 'support'];

    private const VALID_COST_KEYS = ['food', 'wood', 'stone', 'iron', 'gold'];

    private const LOCALES = ['en', 'pt-BR', 'es'];

    protected $signature = 'game:import-data';

    protected $description = 'Load and validate the versioned game-data bundle';

    public function handle(GameDataCatalog $catalog): int
    {
        $buildings = $catalog->buildings();
        $technologies = $catalog->technologies();
        $problems = [
            ...$this->checkShape($buildings),
            ...$this->checkLevels($buildings),
            ...$this->checkDuplicates($buildings),
            ...$this->checkDanglingReferences($buildings),
            ...$this->checkPalaceGate($buildings),
            ...$this->checkTranslations($buildings),
            ...$this->checkStarterIntegrity($catalog),
            ...$this->checkTechnologyLevelSequence($technologies),
            ...$this->checkTechnologyMaxLevel($technologies),
            ...$this->checkTechnologyKeys($technologies),
        ];

        if ($problems !== []) {
            foreach ($problems as $problem) {
                $this->error($problem);
            }

            return self::FAILURE;
        }

        $levelCount = array_sum(array_map(
            static fn (array $building): int => is_array($building['levels'] ?? null) ? count($building['levels']) : 0,
            $buildings,
        ));

        $technologyLevelCount = array_sum(array_map(
            static fn (array $technology): int => is_array($technology['levels'] ?? null) ? count($technology['levels']) : 0,
            $technologies,
        ));

        $this->line(sprintf('buildings: %d definitions, %d levels', count($buildings), $levelCount));
        $this->line(sprintf('technologies: %d definitions, %d levels', count($technologies), $technologyLevelCount));
        $this->line(sprintf('city-slots: %d plots', count($catalog->citySlots())));
        $this->line(sprintf(
            'starter: %d buildings, %d unit stack%s',
            count($catalog->starterBuildings()),
            count($catalog->starterUnits()),
            count($catalog->starterUnits()) === 1 ? '' : 's',
        ));

        return self::SUCCESS;
    }

    /**
     * @param list<array<string, mixed>> $buildings
     * @return list<string>
     */
    private function checkShape(array $buildings): array
    {
        $problems = [];

        foreach ($buildings as $building) {
            $code = is_string($building['code'] ?? null) ? $building['code'] : null;
            $label = $code ?? '<unknown>';

            if ($code === null) {
                $problems[] = 'buildings: an entry is missing its "code"';

                continue;
            }

            foreach (['name_key', 'category', 'max_level', 'levels'] as $field) {
                if (! array_key_exists($field, $building)) {
                    $problems[] = "buildings: \"{$label}\" is missing \"{$field}\"";
                }
            }

            $category = $building['category'] ?? null;
            if (is_string($category) && ! in_array($category, self::VALID_CATEGORIES, true)) {
                $problems[] = "buildings: \"{$label}\" has an unknown category \"{$category}\"";
            }

            $nameKey = $building['name_key'] ?? null;
            if (is_string($nameKey) && $nameKey !== 'buildings.'.$label) {
                $problems[] = "buildings: \"{$label}\" has name_key \"{$nameKey}\", expected \"buildings.{$label}\"";
            }
        }

        return $problems;
    }

    /**
     * @param list<array<string, mixed>> $buildings
     * @return list<string>
     */
    private function checkLevels(array $buildings): array
    {
        $problems = [];

        foreach ($buildings as $building) {
            $code = (string) ($building['code'] ?? '<unknown>');
            $maxLevel = $building['max_level'] ?? null;
            $levels = is_array($building['levels'] ?? null) ? $building['levels'] : [];

            if (is_int($maxLevel) && count($levels) !== $maxLevel) {
                $problems[] = "buildings: \"{$code}\" has ".count($levels)." level(s) but max_level is {$maxLevel}";
            }

            $seenLevels = [];
            foreach ($levels as $level) {
                if (! is_array($level)) {
                    continue;
                }

                $levelNumber = $level['level'] ?? null;
                if (is_int($levelNumber)) {
                    $seenLevels[] = $levelNumber;
                }

                foreach ((array) ($level['cost'] ?? []) as $key => $value) {
                    if (! in_array($key, self::VALID_COST_KEYS, true)) {
                        $problems[] = "buildings: \"{$code}\" level {$levelNumber} has an unknown cost key \"{$key}\"";

                        continue;
                    }
                    if (! is_int($value) || $value < 0) {
                        $problems[] = "buildings: \"{$code}\" level {$levelNumber} has an invalid cost.{$key} (".json_encode($value).')';
                    }
                }

                $duration = $level['build_time_seconds'] ?? null;
                if (! is_int($duration) || $duration < 0) {
                    $problems[] = "buildings: \"{$code}\" level {$levelNumber} has an invalid build_time_seconds (".json_encode($duration).')';
                }
            }

            if (is_int($maxLevel)) {
                $expected = range(1, $maxLevel);
                sort($seenLevels);
                if ($seenLevels !== $expected) {
                    $problems[] = "buildings: \"{$code}\" levels are not exactly 1..{$maxLevel}";
                }
            }
        }

        return $problems;
    }

    /**
     * @param list<array<string, mixed>> $buildings
     * @return list<string>
     */
    private function checkDuplicates(array $buildings): array
    {
        $problems = [];
        $seen = [];

        foreach ($buildings as $building) {
            $code = $building['code'] ?? null;
            if (! is_string($code)) {
                continue;
            }

            if (in_array($code, $seen, true)) {
                $problems[] = "buildings: duplicate code \"{$code}\"";

                continue;
            }

            $seen[] = $code;
        }

        return $problems;
    }

    /**
     * @param list<array<string, mixed>> $buildings
     * @return list<string>
     */
    private function checkDanglingReferences(array $buildings): array
    {
        $problems = [];
        $maxLevelByCode = [];

        foreach ($buildings as $building) {
            $code = $building['code'] ?? null;
            if (is_string($code) && is_int($building['max_level'] ?? null)) {
                $maxLevelByCode[$code] = $building['max_level'];
            }
        }

        foreach ($buildings as $building) {
            $code = (string) ($building['code'] ?? '<unknown>');
            $levels = is_array($building['levels'] ?? null) ? $building['levels'] : [];

            foreach ($levels as $level) {
                if (! is_array($level)) {
                    continue;
                }

                $requirements = is_array($level['requirements'] ?? null) ? $level['requirements'] : [];
                foreach ($requirements as $requirement) {
                    if (! is_array($requirement) || ($requirement['type'] ?? null) !== 'building') {
                        continue;
                    }

                    $requiredCode = $requirement['code'] ?? null;
                    $requiredLevel = $requirement['level'] ?? null;

                    if (! is_string($requiredCode) || ! array_key_exists($requiredCode, $maxLevelByCode)) {
                        $problems[] = "buildings: \"{$code}\" requires unknown \"{$requiredCode}\"";

                        continue;
                    }

                    if (is_int($requiredLevel) && $requiredLevel > $maxLevelByCode[$requiredCode]) {
                        $problems[] = "buildings: \"{$code}\" requires \"{$requiredCode}\" level {$requiredLevel}, which exceeds its max_level {$maxLevelByCode[$requiredCode]}";
                    }
                }
            }
        }

        return $problems;
    }

    /**
     * @param list<array<string, mixed>> $buildings
     * @return list<string>
     */
    private function checkPalaceGate(array $buildings): array
    {
        $problems = [];

        foreach ($buildings as $building) {
            $code = (string) ($building['code'] ?? '<unknown>');
            $levels = is_array($building['levels'] ?? null) ? $building['levels'] : [];

            foreach ($levels as $level) {
                if (! is_array($level)) {
                    continue;
                }

                $levelNumber = $level['level'] ?? null;
                if (! is_int($levelNumber)) {
                    continue;
                }

                $requirements = is_array($level['requirements'] ?? null) ? $level['requirements'] : [];
                $hasPalaceGate = false;
                $hasAnyBuildingRequirement = false;

                foreach ($requirements as $requirement) {
                    if (! is_array($requirement) || ($requirement['type'] ?? null) !== 'building') {
                        continue;
                    }

                    $hasAnyBuildingRequirement = true;
                    if (
                        ($requirement['code'] ?? null) === 'palace'
                        && ($requirement['level'] ?? null) === $levelNumber
                    ) {
                        $hasPalaceGate = true;
                    }
                }

                if ($code === 'palace') {
                    if ($hasAnyBuildingRequirement) {
                        $problems[] = "buildings: \"palace\" level {$levelNumber} must not gate itself against a building requirement";
                    }

                    continue;
                }

                if ($levelNumber >= 2 && ! $hasPalaceGate) {
                    $problems[] = "buildings: \"{$code}\" level {$levelNumber} is missing the palace gate (requires palace level {$levelNumber})";
                }
            }
        }

        return $problems;
    }

    /**
     * @param list<array<string, mixed>> $buildings
     * @return list<string>
     */
    private function checkTranslations(array $buildings): array
    {
        $problems = [];
        $catalogues = [];

        foreach (self::LOCALES as $locale) {
            $path = rtrim((string) config('game.localization_path'), '/').'/locales/'.$locale.'/mvp.json';
            $contents = is_file($path) ? file_get_contents($path) : false;
            $decoded = $contents !== false ? json_decode($contents, true) : null;
            $catalogues[$locale] = is_array($decoded) ? $decoded : [];
        }

        foreach ($buildings as $building) {
            $code = (string) ($building['code'] ?? '<unknown>');
            $nameKey = $building['name_key'] ?? null;
            if (! is_string($nameKey)) {
                continue;
            }

            foreach (self::LOCALES as $locale) {
                $value = $this->resolveDottedKey($catalogues[$locale], $nameKey);
                if (! is_string($value) || $value === '') {
                    $problems[] = $this->missingTranslationMessage($code, $locale, $nameKey);
                }
            }
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private function checkStarterIntegrity(GameDataCatalog $catalog): array
    {
        $problems = [];
        $slots = $catalog->citySlots();
        $maxLevelByCode = [];

        foreach ($catalog->buildings() as $building) {
            $code = $building['code'] ?? null;
            if (is_string($code) && is_int($building['max_level'] ?? null)) {
                $maxLevelByCode[$code] = $building['max_level'];
            }
        }

        foreach ($catalog->starterBuildings() as $entry) {
            $code = $entry['code'];
            $level = $entry['level'];
            $slot = $entry['slot'];

            if (! array_key_exists($code, $maxLevelByCode)) {
                $problems[] = "starter: building \"{$code}\" is not in the catalogue";

                continue;
            }

            if ($level < 1 || $level > $maxLevelByCode[$code]) {
                $problems[] = "starter: building \"{$code}\" has level {$level}, outside 1..{$maxLevelByCode[$code]}";
            }

            if (! in_array($slot, $slots, true)) {
                $problems[] = "starter: building \"{$code}\" is assigned unknown slot \"{$slot}\"";
            }
        }

        return $problems;
    }

    /**
     * `levels[]` must be a contiguous 1..N sequence, where N is the number of
     * authored levels — independent of what `max_level` claims (that is
     * checkTechnologyMaxLevel's job).
     *
     * @param list<array<string, mixed>> $technologies
     * @return list<string>
     */
    private function checkTechnologyLevelSequence(array $technologies): array
    {
        $problems = [];

        foreach ($technologies as $technology) {
            $code = (string) ($technology['code'] ?? '<unknown>');
            $levels = is_array($technology['levels'] ?? null) ? $technology['levels'] : [];

            $levelNumbers = [];
            foreach ($levels as $level) {
                if (is_array($level) && is_int($level['level'] ?? null)) {
                    $levelNumbers[] = $level['level'];
                }
            }

            $sorted = $levelNumbers;
            sort($sorted);
            $expected = range(1, count($levelNumbers));

            if ($sorted !== $expected) {
                $problems[] = sprintf(
                    'technology "%s" has levels [%s]; expected a contiguous 1..%d',
                    $code,
                    implode(',', $levelNumbers),
                    count($levelNumbers),
                );
            }
        }

        return $problems;
    }

    /**
     * `max_level` must equal the number of authored levels.
     *
     * @param list<array<string, mixed>> $technologies
     * @return list<string>
     */
    private function checkTechnologyMaxLevel(array $technologies): array
    {
        $problems = [];

        foreach ($technologies as $technology) {
            $code = (string) ($technology['code'] ?? '<unknown>');
            $maxLevel = $technology['max_level'] ?? null;
            $levels = is_array($technology['levels'] ?? null) ? $technology['levels'] : [];

            if (is_int($maxLevel) && count($levels) !== $maxLevel) {
                $problems[] = "technology \"{$code}\" declares max_level {$maxLevel} but authors ".count($levels).' levels';
            }
        }

        return $problems;
    }

    /**
     * `name_key` must equal `technologies.<code>` and `description_key` must
     * equal `technologies.<code>_desc`.
     *
     * @param list<array<string, mixed>> $technologies
     * @return list<string>
     */
    private function checkTechnologyKeys(array $technologies): array
    {
        $problems = [];

        foreach ($technologies as $technology) {
            $code = (string) ($technology['code'] ?? '<unknown>');

            $nameKey = $technology['name_key'] ?? null;
            $expectedNameKey = 'technologies.'.$code;
            if (is_string($nameKey) && $nameKey !== $expectedNameKey) {
                $problems[] = "technology \"{$code}\" has name_key \"{$nameKey}\"; expected \"{$expectedNameKey}\"";
            }

            $descriptionKey = $technology['description_key'] ?? null;
            $expectedDescriptionKey = 'technologies.'.$code.'_desc';
            if (is_string($descriptionKey) && $descriptionKey !== $expectedDescriptionKey) {
                $problems[] = "technology \"{$code}\" has description_key \"{$descriptionKey}\"; expected \"{$expectedDescriptionKey}\"";
            }
        }

        return $problems;
    }

    /**
     * Spells the locale out per-branch (not interpolated) so the offending
     * locale reads directly in the failure line and in a grep of this file.
     */
    private function missingTranslationMessage(string $code, string $locale, string $nameKey): string
    {
        if ($locale === 'en') {
            return "buildings: \"{$code}\" has no en translation for \"{$nameKey}\"";
        }

        if ($locale === 'pt-BR') {
            return "buildings: \"{$code}\" has no pt-BR translation for \"{$nameKey}\"";
        }

        return "buildings: \"{$code}\" has no es translation for \"{$nameKey}\"";
    }

    /**
     * @param array<string, mixed> $catalogue
     */
    private function resolveDottedKey(array $catalogue, string $dottedKey): mixed
    {
        $segments = explode('.', $dottedKey);
        $cursor = $catalogue;

        foreach ($segments as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }
}
