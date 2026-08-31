<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CityResource\Pages;
use App\Services\AdminAuditService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Game\City\Infrastructure\City;
use Game\Economy\Infrastructure\EconomyLedger;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|UnitEnum|null $navigationGroup = 'Economy & Cities';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('player_id')->searchable(),
                Tables\Columns\TextColumn::make('world_id')->searchable(),
                Tables\Columns\TextColumn::make('name_key')->label('City Name'),
                Tables\Columns\TextColumn::make('coordinates')
                    ->state(fn (City $record): string => "({$record->x}, {$record->y})"),
                Tables\Columns\TextColumn::make('food')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('wood')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('stone')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('iron')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('gold')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('last_accrued_at')->dateTime()->sortable(),
            ])
            ->actions([
                Action::make('grant_resources')
                    ->form([
                        TextInput::make('food')->numeric()->default(0),
                        TextInput::make('wood')->numeric()->default(0),
                        TextInput::make('stone')->numeric()->default(0),
                        TextInput::make('iron')->numeric()->default(0),
                        TextInput::make('gold')->numeric()->default(0),
                        Textarea::make('reason')->required(),
                    ])
                    ->action(function (City $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $before = [
                                'food' => $record->food,
                                'wood' => $record->wood,
                                'stone' => $record->stone,
                                'iron' => $record->iron,
                                'gold' => $record->gold,
                            ];

                            $foodAdd = (int) ($data['food'] ?? 0);
                            $woodAdd = (int) ($data['wood'] ?? 0);
                            $stoneAdd = (int) ($data['stone'] ?? 0);
                            $ironAdd = (int) ($data['iron'] ?? 0);
                            $goldAdd = (int) ($data['gold'] ?? 0);

                            $record->update([
                                'food' => max(0, $record->food + $foodAdd),
                                'wood' => max(0, $record->wood + $woodAdd),
                                'stone' => max(0, $record->stone + $stoneAdd),
                                'iron' => max(0, $record->iron + $ironAdd),
                                'gold' => max(0, $record->gold + $goldAdd),
                            ]);

                            $reason = (string) ($data['reason'] ?? '');

                            $grants = [
                                'food' => $foodAdd,
                                'wood' => $woodAdd,
                                'stone' => $stoneAdd,
                                'iron' => $ironAdd,
                                'gold' => $goldAdd,
                            ];

                            foreach ($grants as $resource => $amount) {
                                if ($amount !== 0) {
                                    EconomyLedger::create([
                                        'world_id' => $record->world_id,
                                        'city_id' => $record->id,
                                        'resource' => $resource,
                                        'amount' => $amount,
                                        'overflow_amount' => 0,
                                        'reason' => 'gm_grant',
                                        'reference' => 'admin_action:'.$reason,
                                        'economy_version' => 1,
                                    ]);
                                }
                            }

                            $fresh = $record->fresh();
                            $after = [
                                'food' => $fresh instanceof City ? $fresh->food : null,
                                'wood' => $fresh instanceof City ? $fresh->wood : null,
                                'stone' => $fresh instanceof City ? $fresh->stone : null,
                                'iron' => $fresh instanceof City ? $fresh->iron : null,
                                'gold' => $fresh instanceof City ? $fresh->gold : null,
                            ];

                            app(AdminAuditService::class)->record(
                                'city.grant_resources',
                                City::class,
                                $record->getKey(),
                                $before,
                                $after,
                                $reason,
                            );
                        });
                    }),

                Action::make('teleport')
                    ->form([
                        TextInput::make('x')->numeric()->required(),
                        TextInput::make('y')->numeric()->required(),
                        Textarea::make('reason')->required(),
                    ])
                    ->fillForm(fn (City $record): array => [
                        'x' => $record->x,
                        'y' => $record->y,
                    ])
                    ->action(function (City $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $before = ['x' => $record->x, 'y' => $record->y];
                            $record->update([
                                'x' => (int) $data['x'],
                                'y' => (int) $data['y'],
                            ]);
                            $fresh = $record->fresh();
                            $after = [
                                'x' => $fresh instanceof City ? $fresh->x : null,
                                'y' => $fresh instanceof City ? $fresh->y : null,
                            ];

                            app(AdminAuditService::class)->record(
                                'city.teleport',
                                City::class,
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
            'index' => Pages\ListCities::route('/'),
        ];
    }
}
