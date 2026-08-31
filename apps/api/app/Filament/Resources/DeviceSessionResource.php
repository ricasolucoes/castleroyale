<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceSessionResource\Pages;
use App\Services\AdminAuditService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Game\Identity\Domain\DeviceSession;
use Game\Shared\Domain\Time\Clock;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class DeviceSessionResource extends Resource
{
    protected static ?string $model = DeviceSession::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static string|UnitEnum|null $navigationGroup = 'Identity & Access';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('account_id')->searchable(),
                Tables\Columns\TextColumn::make('device_id')->searchable(),
                Tables\Columns\TextColumn::make('device_name'),
                Tables\Columns\TextColumn::make('platform')->badge(),
                Tables\Columns\TextColumn::make('last_seen_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('revoked_at')->dateTime()->sortable(),
            ])
            ->actions([
                Action::make('revoke')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('reason')->required(),
                    ])
                    ->action(function (DeviceSession $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $before = ['revoked_at' => $record->revoked_at?->toIso8601String()];
                            $record->update(['revoked_at' => app(Clock::class)->now()]);
                            $fresh = $record->fresh();
                            $after = [
                                'revoked_at' => $fresh instanceof DeviceSession ? $fresh->revoked_at?->toIso8601String() : null,
                            ];
                            app(AdminAuditService::class)->record(
                                'device_session.revoke',
                                DeviceSession::class,
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
            'index' => Pages\ListDeviceSessions::route('/'),
        ];
    }
}
