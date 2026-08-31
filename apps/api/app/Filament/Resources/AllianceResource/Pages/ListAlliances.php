<?php

declare(strict_types=1);

namespace App\Filament\Resources\AllianceResource\Pages;

use App\Filament\Resources\AllianceResource;
use Filament\Resources\Pages\ListRecords;

final class ListAlliances extends ListRecords
{
    protected static string $resource = AllianceResource::class;
}
