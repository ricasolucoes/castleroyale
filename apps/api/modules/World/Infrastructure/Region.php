<?php

declare(strict_types=1);

namespace Game\World\Infrastructure;

use Game\Shared\Infrastructure\Eloquent\Concerns\HasGameUlid;
use Illuminate\Database\Eloquent\Model;

final class Region extends Model
{
    use HasGameUlid;

    protected $table = 'regions';

    protected $fillable = [
        'world_id', 'code', 'seed', 'generation_version', 'region_x', 'region_y', 'min_x', 'max_x', 'min_y', 'max_y', 'boundary',
    ];

    protected function casts(): array
    {
        return [
            'generation_version' => 'integer',
            'region_x' => 'integer',
            'region_y' => 'integer',
            'min_x' => 'integer',
            'max_x' => 'integer',
            'min_y' => 'integer',
            'max_y' => 'integer',
        ];
    }
}
