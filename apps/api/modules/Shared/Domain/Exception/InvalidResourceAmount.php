<?php

declare(strict_types=1);

namespace Game\Shared\Domain\Exception;

final class InvalidResourceAmount extends DomainException
{
    public static function negative(int $value): self
    {
        return new self("A resource amount cannot be negative, got {$value}.");
    }

    public static function aboveCeiling(int $value, int $ceiling): self
    {
        return new self("A resource amount cannot exceed {$ceiling}, got {$value}.");
    }

    public static function wouldGoNegative(int $available, int $requested): self
    {
        return new self("Cannot subtract {$requested} from {$available} without going negative.");
    }

    public static function negativeFactor(int $permille): self
    {
        return new self("A scaling factor cannot be negative, got {$permille} permille.");
    }
}
