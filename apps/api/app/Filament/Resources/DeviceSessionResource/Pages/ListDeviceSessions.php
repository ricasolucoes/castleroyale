<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeviceSessionResource\Pages;

use App\Filament\Resources\DeviceSessionResource;
use Filament\Resources\Pages\ListRecords;

final class ListDeviceSessions extends ListRecords
{
    protected static string $resource = DeviceSessionResource::class;
}
