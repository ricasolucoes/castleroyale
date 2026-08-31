<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PlayerResource\Pages;
use App\Services\AdminAuditService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Game\Player\Infrastructure\Player;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class PlayerResource extends Resource
{
    protected static ?string $model = Player::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static string|UnitEnum|null $navigationGroup = 'Game Operations';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('world_id')->searchable(),
                Tables\Columns\TextColumn::make('account_id')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                Action::make('rename_player')
                    ->form([
                        TextInput::make('name')
                            ->required()
                            ->minLength(3)
                            ->maxLength(24),
                        Textarea::make('reason')->required(),
                    ])
                    ->action(function (Player $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $before = ['name' => $record->name];
                            $record->update(['name' => (string) $data['name']]);
                            $fresh = $record->fresh();
                            $after = ['name' => $fresh instanceof Player ? $fresh->name : null];

                            app(AdminAuditService::class)->record(
                                'player.rename',
                                Player::class,
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
            'index' => Pages\ListPlayers::route('/'),
        ];
    }
}
