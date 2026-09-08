<?php

declare(strict_types=1);

namespace Game\Technology\Infrastructure;

use Game\Shared\Infrastructure\Eloquent\Concerns\HasGameUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The timed triple for one research: from_level -> target_level, scheduled
 * between started_at and finishes_at, completed exactly once (completed_at).
 *
 * `city_id` records which city's resource balance paid for the research even
 * though `player_id` is the technology's real owner — see the migration
 * comment for the reasoning.
 *
 * @property string $id
 * @property string $world_id
 * @property string $player_id
 * @property string $city_id
 * @property string $technology_code
 * @property int $from_level
 * @property int $target_level
 * @property string|null $idempotency_key
 * @property Carbon|null $started_at
 * @property Carbon|null $finishes_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ResearchOrder extends Model
{
    use HasGameUlid;

    protected $table = 'research_orders';

    protected $fillable = [
        'world_id', 'player_id', 'city_id', 'technology_code', 'from_level', 'target_level',
        'idempotency_key', 'started_at', 'finishes_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'from_level' => 'integer',
            'target_level' => 'integer',
            'started_at' => 'datetime',
            'finishes_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
