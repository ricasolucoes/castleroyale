<?php

declare(strict_types=1);

namespace Game\Shared\Domain\Economy;

use Game\Shared\Domain\Exception\InvalidResourceAmount;

/**
 * A non-negative, integer quantity of a single resource.
 *
 * Floats are forbidden throughout the economy (ADR-010). Every production
 * rate, cost, tax and plunder result is expressed in whole units so that no
 * rounding drift can ever mint or destroy value. Arithmetic is checked: an
 * operation that would overflow or go negative raises instead of silently
 * wrapping.
 */
final class ResourceAmount
{
    /**
     * Hard ceiling for any single stored quantity.
     *
     * Chosen well below PHP_INT_MAX so intermediate sums in the simulation
     * cannot overflow, and so the value always fits a PostgreSQL `bigint`.
     */
    public const int MAX = 9_000_000_000_000_000;

    private function __construct(
        public readonly int $value,
    ) {}

    public static function of(int $value): self
    {
        if ($value < 0) {
            throw InvalidResourceAmount::negative($value);
        }

        if ($value > self::MAX) {
            throw InvalidResourceAmount::aboveCeiling($value, self::MAX);
        }

        return new self($value);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function plus(self $other): self
    {
        if ($this->value > self::MAX - $other->value) {
            throw InvalidResourceAmount::aboveCeiling($this->value + $other->value, self::MAX);
        }

        return new self($this->value + $other->value);
    }

    /**
     * Subtract, refusing to go below zero.
     *
     * Callers that legitimately expect a floor (for example plundering more
     * than a city holds) must use {@see subtractSaturating()} and say so.
     */
    public function minus(self $other): self
    {
        if ($other->value > $this->value) {
            throw InvalidResourceAmount::wouldGoNegative($this->value, $other->value);
        }

        return new self($this->value - $other->value);
    }

    public function subtractSaturating(self $other): self
    {
        return new self(max(0, $this->value - $other->value));
    }

    /**
     * Scale by a permille factor (1000 = 100%), truncating toward zero.
     *
     * Percentage-style modifiers (technology, hero bonuses, terrain) are
     * always applied through integer permille so the result is reproducible
     * on every platform. Truncation is deliberate and always favours the
     * house: bonuses round down.
     */
    public function scaledByPermille(int $permille): self
    {
        if ($permille < 0) {
            throw InvalidResourceAmount::negativeFactor($permille);
        }

        return self::of(intdiv($this->value * $permille, 1000));
    }

    public function isZero(): bool
    {
        return $this->value === 0;
    }

    public function isAtLeast(self $other): bool
    {
        return $this->value >= $other->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
