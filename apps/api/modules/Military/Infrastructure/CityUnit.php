<?php

declare(strict_types=1);

namespace Game\Military\Infrastructure;

use Game\Shared\Infrastructure\Eloquent\Concerns\HasGameUlid;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $world_id
 * @property string $city_id
 * @property string $unit_code
 * @property int $quantity
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class CityUnit extends Model
{
    use HasGameUlid;

    protected $table = 'city_units';

    protected $fillable = ['world_id', 'city_id', 'unit_code', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }
}
