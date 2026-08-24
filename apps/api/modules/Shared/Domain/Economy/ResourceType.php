<?php

declare(strict_types=1);

namespace Game\Shared\Domain\Economy;

/**
 * The canonical economic resources.
 *
 * Backed by short stable strings so the value survives database dumps,
 * analytics pipelines and the mobile client without depending on ordering.
 * New resources are appended; existing values are never renumbered or renamed.
 *
 * @see docs/game-design/economy.md
 */
enum ResourceType: string
{
    case Food = 'food';
    case Wood = 'wood';
    case Stone = 'stone';
    case Iron = 'iron';
    case Gold = 'gold';

    /**
     * Resources that are produced by city buildings and can be plundered.
     *
     * @return list<self>
     */
    public static function plunderable(): array
    {
        return [self::Food, self::Wood, self::Stone, self::Iron];
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    public function label(): string
    {
        return match ($this) {
            self::Food => 'Food',
            self::Wood => 'Wood',
            self::Stone => 'Stone',
            self::Iron => 'Iron',
            self::Gold => 'Gold',
        };
    }
}
