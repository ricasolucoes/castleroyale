<?php

declare(strict_types=1);

namespace Game\World\Infrastructure;

use Game\Shared\Infrastructure\Eloquent\Concerns\HasGameUlid;
use Illuminate\Database\Eloquent\Model;

final class Tile extends Model
{
    use HasGameUlid;

    protected $table = 'tiles';

    protected $fillable = ['world_id', 'region_id', 'x', 'y', 'terrain', 'generation_key'];

    protected function casts(): array
    {
        return ['x' => 'integer', 'y' => 'integer'];
    }
}
