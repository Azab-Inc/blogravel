<?php

namespace App\Filament\Resources;

use App\Enums\Role;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static UnitEnum|string|null $navigationGroup = 'Administration';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('last_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->maxLength(255)
                    ->visibleOn('create')
                    ->suffixAction(Action::make('generatePassword')
                        ->icon('heroicon-m-sparkles')
                        ->label('Generate secure password')
                        ->action(fn ($set) => $set('password', Str::password(16)))),
                Select::make('role')
                    ->options(collect(Role::cases())->reject(fn (Role $role) => $role === Role::SuperAdmin)->mapWithKeys(fn (Role $role) => [$role->value => $role->label()])->all())
                    ->required()
                    ->native(false)
                    ->disabledOn('edit'),
                Checkbox::make('can_invite')
                    ->label('Can invite users')
                    ->default(false),
                TextInput::make('tenant_id')
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->getStateUsing(fn (User $record): string => trim($record->first_name.' '.$record->last_name))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (Role $state): string => $state->label())
                    ->sortable(),
                IconColumn::make('can_invite')
                    ->label('Can Invite')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        return match ($user->role) {
            Role::SuperAdmin => $query,
            Role::Admin => $query->where('tenant_id', $user->tenant_id),
            Role::Editor => $query
                ->where('tenant_id', $user->tenant_id)
                ->where('role', Role::Author->value),
            default => $query->whereKey($user->id),
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
