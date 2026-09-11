<?php

namespace App\Filament\Resources;

use App\Enums\BackupStatus;
use App\Enums\NavGroup;
use App\Filament\Resources\BackupResource\Pages;
use App\Models\Backup;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class BackupResource extends Resource
{
    protected static ?string $model = Backup::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static UnitEnum|string|null $navigationGroup = NavGroup::Administration->value;

    protected static ?string $modelLabel = 'Backup';

    protected static ?string $pluralModelLabel = 'Backups';

    protected static ?int $navigationSort = 41;

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('filename')
                    ->searchable(),
                TextColumn::make('backupRule.name')
                    ->label('Rule'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (BackupStatus $state) => $state->color()),
                TextColumn::make('size_bytes')
                    ->label('Size')
                    ->formatStateUsing(fn ($state) => (new Backup(['size_bytes' => $state]))->sizeFormatted()),
                IconColumn::make('encrypted')
                    ->boolean(),
                TextColumn::make('delivered_via')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : '-'),
                TextColumn::make('created_at')
                    ->dateTime(),
                TextColumn::make('completed_at')
                    ->dateTime(),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', Auth::user()->tenant_id);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBackups::route('/'),
        ];
    }
}
