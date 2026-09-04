<?php

namespace App\Filament\Resources;

use App\Enums\ApiKeyAbility;
use App\Filament\Resources\ApiKeyResource\Pages;
use App\Models\ApiKey;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static UnitEnum|string|null $navigationGroup = 'Administration';

    protected static ?string $modelLabel = 'API Key';

    protected static ?string $pluralModelLabel = 'API Keys';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('token')
                    ->label('Token')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit')
                    ->hint(fn (?ApiKey $record) => $record?->token ? Str::mask($record->token, '*', 8) : '—'),
                CheckboxList::make('abilities')
                    ->options(ApiKeyAbility::class)
                    ->columns(3)
                    ->required(),
                DatePicker::make('expires_at')
                    ->label('Expires At')
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('abilities')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->value),
                TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->since()
                    ->placeholder('Never'),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->placeholder('Never'),
                IconColumn::make('is_expired')
                    ->label('Active')
                    ->getStateUsing(fn (ApiKey $record) => ! ($record->expires_at && $record->expires_at->isPast()))
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([

                DeleteBulkAction::make(),

            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApiKeys::route('/'),
            'create' => Pages\CreateApiKey::route('/create'),
            'edit' => Pages\EditApiKey::route('/{record}/edit'),
        ];
    }
}
