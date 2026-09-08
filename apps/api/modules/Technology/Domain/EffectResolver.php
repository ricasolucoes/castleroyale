<?php

declare(strict_types=1);

namespace Game\Technology\Domain;

/**
 * The one place an effect descriptor becomes a number.
 *
 * Shared deliberately: buildings feed it today, technologies from Phase 10, and
 * hero bonuses from Phase 13 (docs/game-design/technology.md § Effects).
 *
 * The resolver is pure: no container, no config, no clock, no database, no
 * Laravel import of any kind.
 */
final readonly class EffectResolver
{
    /**
     * @param array<string,int> $baseline target => starting value
     * @param list<list<array{target:string,operation:string,value:int}>> $effectSets
     * @return array<string,int>
     */
    public static function resolve(array $baseline, array $effectSets): array
    {
        $totals = $baseline;

        // Pass one — add. A flat bonus is part of the base a percentage then
        // scales. Applying multiply first would make a technology's value
        // depend on the order buildings happened to be constructed in, which is
        // not a property a player could reason about.
        foreach ($effectSets as $effects) {
            foreach ($effects as $effect) {
                if ($effect['operation'] !== 'add') {
                    continue;
                }

                $target = $effect['target'];
                $totals[$target] = ($totals[$target] ?? 0) + $effect['value'];
            }
        }

        // Pass two — multiply. Every multiplier is a permille value where 1000
        // is the identity. Surplus (the distance above 1000) accumulates
        // additively per target, not multiplicatively: two +10% technologies
        // give +20%, not +21%. Multiplicative stacking compounds and makes
        // late-game balance unpredictable; additive stacking is what the design
        // doc's flat permille model implies.
        $surplus = [];
        foreach ($effectSets as $effects) {
            foreach ($effects as $effect) {
                if ($effect['operation'] !== 'multiply') {
                    continue;
                }

                $target = $effect['target'];
                $surplus[$target] = ($surplus[$target] ?? 0) + ($effect['value'] - 1000);
            }
        }

        foreach ($surplus as $target => $value) {
            $base = $totals[$target] ?? 0;
            // intdiv truncates toward zero, which for the non-negative totals
            // produced here is truncation downward as ADR-010 requires. A
            // negative total (possible only if a future `add` effect is
            // negative) would truncate toward zero rather than down — no such
            // case exists in the authored data today.
            $totals[$target] = intdiv($base * (1000 + $value), 1000);
        }

        return $totals;
    }
}
