<?php

declare(strict_types=1);

namespace Game\World\Infrastructure;

use Game\Shared\Infrastructure\Eloquent\Concerns\HasGameUlid;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $seed
 * @property int $population
 * @property int $capacity
 * @property int $spawn_index
 * @property bool $is_open
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class World extends Model
{
    use HasGameUlid;

    protected $table = 'worlds';

    protected $fillable = ['code', 'name', 'seed', 'population', 'capacity', 'spawn_index', 'is_open'];

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
