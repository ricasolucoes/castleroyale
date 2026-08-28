<?php

declare(strict_types=1);

namespace Game\City\Infrastructure;

use Game\Shared\Infrastructure\Eloquent\Concerns\HasGameUlid;
use Illuminate\Database\Eloquent\Model;

final class CityBuilding extends Model
{
    use HasGameUlid;

    protected $table = 'city_buildings';

    protected $fillable = ['world_id', 'city_id', 'building_code', 'level'];

    protected function casts(): array
    {
        return ['level' => 'integer'];
    }
}
