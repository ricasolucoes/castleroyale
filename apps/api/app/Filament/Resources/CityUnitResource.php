<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CityUnitResource\Pages;
use App\Services\AdminAuditService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Game\Military\Infrastructure\CityUnit;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class CityUnitResource extends Resource
{
    protected static ?string $model = CityUnit::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'Military & Warfare';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('world_id')->searchable(),
                Tables\Columns\TextColumn::make('city_id')->searchable(),
                Tables\Columns\TextColumn::make('unit_code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('quantity')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                Action::make('adjust_quantity')
                    ->form([
                        TextInput::make('quantity')->numeric()->minValue(0)->required(),
                        Textarea::make('reason')->required(),
                    ])
                    ->fillForm(fn (CityUnit $record): array => [
                        'quantity' => $record->quantity,
                    ])
                    ->action(function (CityUnit $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $before = ['quantity' => $record->quantity];
                            $record->update(['quantity' => (int) $data['quantity']]);
                            $fresh = $record->fresh();
                            $after = ['quantity' => $fresh instanceof CityUnit ? $fresh->quantity : null];

                            app(AdminAuditService::class)->record(
                                'military.adjust_garrison',
                                CityUnit::class,
                                $record->getKey(),
                                $before,
                                $after,
                                (string) ($data['reason'] ?? ''),
                            );
                        });
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCityUnits::route('/'),
        ];
    }
}
