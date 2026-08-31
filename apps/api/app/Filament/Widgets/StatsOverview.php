<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

final class StatsOverview extends BaseWidget
{
    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $accountsCount = DB::table('accounts')->count();
        $playersCount = DB::table('players')->count();
        $openWorldsCount = DB::table('worlds')->where('is_open', true)->count();
        $totalWorldsCount = DB::table('worlds')->count();
        $citiesCount = DB::table('cities')->count();
        $alliancesCount = DB::table('alliances')->count();
        $auditsCount = DB::table('admin_audits')->count();

        return [
            Stat::make('Total Accounts', (string) $accountsCount)
                ->description('Registered accounts')
                ->descriptionIcon('heroicon-m-user-group'),
            Stat::make('Active Players', (string) $playersCount)
                ->description('Players across worlds')
                ->descriptionIcon('heroicon-m-user'),
            Stat::make('Open Worlds', (string) $openWorldsCount)
                ->description('Total: '.$totalWorldsCount)
                ->descriptionIcon('heroicon-m-globe-alt'),
            Stat::make('Total Cities', (string) $citiesCount)
                ->description('Player settlements')
                ->descriptionIcon('heroicon-m-building-office-2'),
            Stat::make('Alliances', (string) $alliancesCount)
                ->description('Active alliances')
                ->descriptionIcon('heroicon-m-flag'),
            Stat::make('Admin Audits', (string) $auditsCount)
                ->description('Recorded actions')
                ->descriptionIcon('heroicon-m-clipboard-document-list'),
        ];
    }
}
