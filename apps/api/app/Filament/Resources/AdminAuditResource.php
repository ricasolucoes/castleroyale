<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AdminAuditResource\Pages;
use App\Models\AdminAudit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class AdminAuditResource extends Resource
{
    protected static ?string $model = AdminAudit::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Audit & Logs';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('actor_user_id')->label('Actor User ID')->searchable(),
                Tables\Columns\TextColumn::make('action')->badge()->searchable()->sortable(),
                Tables\Columns\TextColumn::make('target_type')->limit(20)->searchable(),
                Tables\Columns\TextColumn::make('target_id')->searchable(),
                Tables\Columns\TextColumn::make('reason')->limit(40)->searchable(),
                Tables\Columns\TextColumn::make('ip_address')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminAudits::route('/'),
        ];
    }
}
