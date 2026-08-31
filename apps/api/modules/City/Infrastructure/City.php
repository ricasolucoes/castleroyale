<?php

declare(strict_types=1);

namespace Game\City\Infrastructure;

use Game\Shared\Infrastructure\Eloquent\Concerns\HasGameUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $world_id
 * @property string $player_id
 * @property string $name_key
 * @property int $x
 * @property int $y
 * @property int $food
 * @property int $wood
 * @property int $stone
 * @property int $iron
 * @property int $gold
 * @property int $food_capacity
 * @property int $wood_capacity
 * @property int $stone_capacity
 * @property int $iron_capacity
 * @property int $gold_capacity
 * @property Carbon|null $last_accrued_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class City extends Model
{
    use HasGameUlid;

    protected $table = 'cities';

    protected $fillable = [
        'world_id', 'player_id', 'name_key', 'x', 'y',
        'food', 'wood', 'stone', 'iron', 'gold',
        'food_capacity', 'wood_capacity', 'stone_capacity', 'iron_capacity', 'gold_capacity',
        'last_accrued_at',
    ];

    protected function casts(): array
    {
        return [
            'x' => 'integer',
            'y' => 'integer',
            'food' => 'integer',
            'wood' => 'integer',
            'stone' => 'integer',
            'iron' => 'integer',
            'gold' => 'integer',
            'food_capacity' => 'integer',
            'wood_capacity' => 'integer',
            'stone_capacity' => 'integer',
            'iron_capacity' => 'integer',
            'gold_capacity' => 'integer',
            'last_accrued_at' => 'datetime',
        ];
    }
}
