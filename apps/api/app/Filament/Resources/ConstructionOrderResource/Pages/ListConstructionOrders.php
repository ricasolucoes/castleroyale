<?php

declare(strict_types=1);

namespace App\Filament\Resources\ConstructionOrderResource\Pages;

use App\Filament\Resources\ConstructionOrderResource;
use Filament\Resources\Pages\ListRecords;

final class ListConstructionOrders extends ListRecords
{
    protected static string $resource = ConstructionOrderResource::class;
}
