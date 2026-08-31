<?php

declare(strict_types=1);

namespace Game\City\Infrastructure;

use Game\Shared\Infrastructure\Eloquent\Concerns\HasGameUlid;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $world_id
 * @property string $city_id
 * @property string $slot
 * @property string $building_code
 * @property int $level
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class CityBuilding extends Model
{
    use HasGameUlid;

    protected $table = 'city_buildings';

    protected $fillable = ['world_id', 'city_id', 'slot', 'building_code', 'level'];

    protected function casts(): array
    {
        return ['level' => 'integer'];
    }
}
