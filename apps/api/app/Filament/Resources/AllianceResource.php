<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AllianceResource\Pages;
use App\Services\AdminAuditService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Game\Alliance\Infrastructure\Alliance;
use Game\Alliance\Infrastructure\AllianceMember;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class AllianceResource extends Resource
{
    protected static ?string $model = Alliance::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static string|UnitEnum|null $navigationGroup = 'Alliances & Social';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tag')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('world_id')->searchable(),
                Tables\Columns\TextColumn::make('leader_player_id')->searchable(),
                Tables\Columns\TextColumn::make('members')
                    ->state(fn (Alliance $record): string => "{$record->member_count} / {$record->max_members}"),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                Action::make('disband')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->form([
                        Textarea::make('reason')->required(),
                    ])
                    ->action(function (Alliance $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $before = [
                                'name' => $record->name,
                                'tag' => $record->tag,
                                'member_count' => $record->member_count,
                            ];

                            AllianceMember::where('alliance_id', $record->id)->delete();
                            $record->delete();

                            app(AdminAuditService::class)->record(
                                'alliance.disband',
                                Alliance::class,
                                $record->getKey(),
                                $before,
                                null,
                                (string) ($data['reason'] ?? ''),
                            );
                        });
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAlliances::route('/'),
        ];
    }
}
