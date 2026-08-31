<?php

declare(strict_types=1);

namespace App\Filament\Resources\CityResource\Pages;

use App\Filament\Resources\CityResource;
use Filament\Resources\Pages\ListRecords;

final class ListCities extends ListRecords
{
    protected static string $resource = CityResource::class;
}
