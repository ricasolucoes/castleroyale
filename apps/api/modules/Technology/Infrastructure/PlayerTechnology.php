<?php

declare(strict_types=1);

namespace Game\Technology\Infrastructure;

use Game\Shared\Infrastructure\Eloquent\Concerns\HasGameUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A player's current level in one technology. One row per (world, player,
 * technology) — the level-per-entity shape CityBuilding already establishes
 * for buildings, applied to a player rather than a city because technology is
 * player-wide, not per-city.
 *
 * @property string $id
 * @property string $world_id
 * @property string $player_id
 * @property string $technology_code
 * @property int $level
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class PlayerTechnology extends Model
{
    use HasGameUlid;

    protected $table = 'player_technologies';

    protected $fillable = ['world_id', 'player_id', 'technology_code', 'level'];

    protected function casts(): array
    {
        return ['level' => 'integer'];
    }
}
