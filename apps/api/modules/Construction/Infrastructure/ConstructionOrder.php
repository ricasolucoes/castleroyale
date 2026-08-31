<?php

declare(strict_types=1);

namespace Game\Construction\Infrastructure;

use Game\Shared\Infrastructure\Eloquent\Concerns\HasGameUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $world_id
 * @property string $city_id
 * @property string $building_code
 * @property int $from_level
 * @property int $target_level
 * @property string|null $idempotency_key
 * @property Carbon|null $started_at
 * @property Carbon|null $finishes_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ConstructionOrder extends Model
{
    use HasGameUlid;

    protected $table = 'construction_orders';

    protected $fillable = [
        'world_id', 'city_id', 'building_code', 'from_level', 'target_level',
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
