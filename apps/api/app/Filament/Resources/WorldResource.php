<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\WorldResource\Pages;
use App\Services\AdminAuditService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Game\World\Infrastructure\World;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class WorldResource extends Resource
{
    protected static ?string $model = World::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|UnitEnum|null $navigationGroup = 'Game Operations';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('population')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('capacity')->numeric()->sortable(),
                Tables\Columns\IconColumn::make('is_open')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                Action::make('toggle_open')
                    ->requiresConfirmation()
                    ->form([
                        Toggle::make('is_open')->label('World Open Status')->required(),
                        Textarea::make('reason')->required(),
                    ])
                    ->fillForm(fn (World $record): array => [
                        'is_open' => $record->is_open,
                    ])
                    ->action(function (World $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $before = ['is_open' => $record->is_open];
                            $record->update(['is_open' => (bool) $data['is_open']]);
                            $fresh = $record->fresh();
                            $after = ['is_open' => $fresh instanceof World ? $fresh->is_open : null];

                            app(AdminAuditService::class)->record(
                                'world.toggle_open',
                                World::class,
                                $record->getKey(),
                                $before,
                                $after,
                                (string) ($data['reason'] ?? ''),
                            );
                        });
                    }),

                Action::make('adjust_capacity')
                    ->form([
                        TextInput::make('capacity')->numeric()->minValue(1)->required(),
                        Textarea::make('reason')->required(),
                    ])
                    ->fillForm(fn (World $record): array => [
                        'capacity' => $record->capacity,
                    ])
                    ->action(function (World $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $before = ['capacity' => $record->capacity];
                            $record->update(['capacity' => (int) $data['capacity']]);
                            $fresh = $record->fresh();
                            $after = ['capacity' => $fresh instanceof World ? $fresh->capacity : null];

                            app(AdminAuditService::class)->record(
                                'world.adjust_capacity',
                                World::class,
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
            'index' => Pages\ListWorlds::route('/'),
        ];
    }
}
