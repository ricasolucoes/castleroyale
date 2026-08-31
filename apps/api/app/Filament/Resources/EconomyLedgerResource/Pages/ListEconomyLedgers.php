<?php

declare(strict_types=1);

namespace App\Filament\Resources\EconomyLedgerResource\Pages;

use App\Filament\Resources\EconomyLedgerResource;
use Filament\Resources\Pages\ListRecords;

final class ListEconomyLedgers extends ListRecords
{
    protected static string $resource = EconomyLedgerResource::class;
}
