<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminAuditResource\Pages;

use App\Filament\Resources\AdminAuditResource;
use Filament\Resources\Pages\ListRecords;

final class ListAdminAudits extends ListRecords
{
    protected static string $resource = AdminAuditResource::class;
}
