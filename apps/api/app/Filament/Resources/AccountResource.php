<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AccountResource\Pages;
use App\Services\AdminAuditService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Game\Identity\Domain\Account;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Identity & Access';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('shield_expires_at')->dateTime()->sortable(),
                Tables\Columns\IconColumn::make('is_guest')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                Action::make('adjust_shield')
                    ->form([
                        DateTimePicker::make('expires_at')->required(),
                        Textarea::make('reason')->required(),
                    ])
                    ->action(function (Account $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $before = ['shield_expires_at' => $record->shield_expires_at?->toIso8601String()];
                            $record->update(['shield_expires_at' => $data['expires_at']]);
                            $fresh = $record->fresh();
                            $after = [
                                'shield_expires_at' => $fresh instanceof Account ? $fresh->shield_expires_at?->toIso8601String() : null,
                            ];
                            app(AdminAuditService::class)->record(
                                'account.adjust_shield',
                                Account::class,
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
            'index' => Pages\ListAccounts::route('/'),
        ];
    }
}
