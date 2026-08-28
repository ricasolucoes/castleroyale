<?php

declare(strict_types=1);

namespace Game\World\Infrastructure;

use Game\Shared\Infrastructure\Eloquent\Concerns\HasGameUlid;
use Illuminate\Database\Eloquent\Model;

final class World extends Model
{
    use HasGameUlid;

    protected $table = 'worlds';

    protected $fillable = ['code', 'name', 'population', 'capacity', 'spawn_index', 'is_open'];

    protected function casts(): array
    {
        return [
            'population' => 'integer',
            'capacity' => 'integer',
            'spawn_index' => 'integer',
            'is_open' => 'boolean',
        ];
    }
}
