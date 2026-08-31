<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccountResource\Pages;

use App\Filament\Resources\AccountResource;
use Filament\Resources\Pages\ListRecords;

final class ListAccounts extends ListRecords
{
    protected static string $resource = AccountResource::class;
}
