<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ConstructionOrderResource\Pages;
use App\Services\AdminAuditService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Game\City\Infrastructure\CityBuilding;
use Game\Construction\Infrastructure\ConstructionOrder;
use Game\Shared\Domain\Time\Clock;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class ConstructionOrderResource extends Resource
{
    protected static ?string $model = ConstructionOrder::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string|UnitEnum|null $navigationGroup = 'Economy & Cities';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('world_id')->searchable(),
                Tables\Columns\TextColumn::make('city_id')->searchable(),
                Tables\Columns\TextColumn::make('building_code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('levels')
                    ->state(fn (ConstructionOrder $record): string => "Lvl {$record->from_level} → {$record->target_level}"),
                Tables\Columns\TextColumn::make('started_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('finishes_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('completed_at')->dateTime()->sortable(),
            ])
            ->actions([
                Action::make('complete_now')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('reason')->required(),
                    ])
                    ->visible(fn (ConstructionOrder $record): bool => $record->completed_at === null)
                    ->action(function (ConstructionOrder $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $now = app(Clock::class)->now();
                            $before = [
                                'completed_at' => $record->completed_at?->toIso8601String(),
                            ];

                            $record->update(['completed_at' => $now]);

                            $building = CityBuilding::where('city_id', $record->city_id)
                                ->where('building_code', $record->building_code)
                                ->first();

                            if ($building !== null) {
                                $building->update(['level' => $record->target_level]);
                            }

                            $fresh = $record->fresh();
                            $after = [
                                'completed_at' => $fresh instanceof ConstructionOrder ? $fresh->completed_at?->toIso8601String() : null,
                            ];

                            app(AdminAuditService::class)->record(
                                'construction.complete_now',
                                ConstructionOrder::class,
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
            'index' => Pages\ListConstructionOrders::route('/'),
        ];
    }
}
