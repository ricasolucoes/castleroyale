<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\EconomyLedgerResource\Pages;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Game\Economy\Infrastructure\EconomyLedger;
use UnitEnum;

class EconomyLedgerResource extends Resource
{
    protected static ?string $model = EconomyLedger::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string|UnitEnum|null $navigationGroup = 'Audit & Logs';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('world_id')->searchable(),
                Tables\Columns\TextColumn::make('city_id')->searchable(),
                Tables\Columns\TextColumn::make('resource')->badge()->sortable(),
                Tables\Columns\TextColumn::make('amount')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('overflow_amount')->numeric(),
                Tables\Columns\TextColumn::make('reason')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('reference')->limit(30),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('resource')
                    ->options([
                        'food' => 'Food',
                        'wood' => 'Wood',
                        'stone' => 'Stone',
                        'iron' => 'Iron',
                        'gold' => 'Gold',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEconomyLedgers::route('/'),
        ];
    }
}
