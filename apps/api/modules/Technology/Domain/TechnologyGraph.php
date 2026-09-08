<?php

declare(strict_types=1);

namespace Game\Technology\Domain;

use Game\Shared\Domain\Exception\DomainException;

/**
 * The technology tree's tier (topological depth) and unlock-prerequisite
 * computation.
 *
 * `10-UI-SPEC.md` calls `tier` "the single most load-bearing field in this
 * contract": the approved Layout Strategy renders one horizontal lane per
 * non-empty tier within each category, and without a tier there are no lanes
 * and no tree — only a flat list.
 *
 * Constructed from the catalogue's raw technology array rather than
 * `GameDataCatalog` itself, so this class stays framework-free and
 * unit-testable exactly like {@see \Game\Construction\Domain\BuildDuration}.
 */
final class TechnologyGraph
{
    /** @var array<string,int> */
    private array $tierCache = [];

    /** @var array<string,true> in-flight walk, keyed by insertion order */
    private array $inProgress = [];

    /** @param list<array<string,mixed>> $technologies the catalogue array */
    public function __construct(private readonly array $technologies) {}

    /**
     * Topological depth. 0 for a technology with no technology prerequisite,
     * otherwise 1 + the maximum tier of its technology prerequisites — max, not
     * min, because a technology gated on two prerequisites of different depth
     * cannot render in a lane before either of them.
     *
     * Depth-first with memoisation, over technology-type requirements only: a
     * `building` requirement does not create a technology tier, so a technology
     * gated solely on a building is still tier 0 within this graph.
     *
     * A cycle in the data does not hang or overflow the stack. 10-02's
     * validator rejects cycles at authoring time and CI runs it, so one cannot
     * normally reach production, but a validator that runs elsewhere is not a
     * reason for this class to be fragile: an in-progress set is tracked during
     * the walk and re-entry throws, naming the code, rather than recursing.
     */
    public function tier(string $code): int
    {
        if (array_key_exists($code, $this->tierCache)) {
            return $this->tierCache[$code];
        }

        if (isset($this->inProgress[$code])) {
            $path = implode(' -> ', [...array_keys($this->inProgress), $code]);

            throw new class('Technology dependency cycle detected: '.$path) extends DomainException {};
        }

        $this->inProgress[$code] = true;

        $depth = 0;
        foreach ($this->prerequisites($code) as $prerequisite) {
            $depth = max($depth, 1 + $this->tier($prerequisite['code']));
        }

        unset($this->inProgress[$code]);

        return $this->tierCache[$code] = $depth;
    }

    /**
     * The technology-type requirements of this technology's FIRST level,
     * served regardless of the player's current level.
     *
     * A technology's unlock prerequisites are a property of the technology,
     * not of whichever level the player happens to be looking at next — nesting
     * this data only inside a "next level" view would make it null for a
     * maxed technology, losing its ability to render its own prerequisite
     * caption and requires-chips.
     *
     * @return list<array{code:string,level:int}>
     */
    public function prerequisites(string $code): array
    {
        $firstLevel = $this->levelOne($code);
        if ($firstLevel === null || ! is_array($firstLevel['requirements'] ?? null)) {
            return [];
        }

        $result = [];
        foreach ($firstLevel['requirements'] as $requirement) {
            if (! is_array($requirement) || ($requirement['type'] ?? null) !== 'technology') {
                continue;
            }

            $result[] = [
                'code' => (string) ($requirement['code'] ?? ''),
                'level' => (int) ($requirement['level'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @return array<string,int> code => tier, for every technology
     */
    public function tiers(): array
    {
        $tiers = [];
        foreach ($this->technologies as $technology) {
            $code = (string) ($technology['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $tiers[$code] = $this->tier($code);
        }

        return $tiers;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function levelOne(string $code): ?array
    {
        foreach ($this->technologies as $technology) {
            if (($technology['code'] ?? null) !== $code || ! is_array($technology['levels'] ?? null)) {
                continue;
            }

            foreach ($technology['levels'] as $level) {
                if (is_array($level) && (int) ($level['level'] ?? 0) === 1) {
                    /** @var array<string,mixed> $level */
                    return $level;
                }
            }
        }

        return null;
    }
}
