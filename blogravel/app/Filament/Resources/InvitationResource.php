<?php

namespace App\Filament\Resources;

use App\Enums\Role;
use App\Filament\Resources\InvitationResource\Pages;
use App\Models\Invitation;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class InvitationResource extends Resource
{
    protected static ?string $model = Invitation::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static UnitEnum|string|null $navigationGroup = 'Administration';

    protected static ?string $modelLabel = 'Invitation';

    protected static ?string $pluralModelLabel = 'Invitations';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Radio::make('type')
                    ->label('Invitation Type')
                    ->options([
                        'email' => 'Email Invitation',
                        'shareable' => 'Shareable Link',
                    ])
                    ->default('email')
                    ->live()
                    ->inline(),
                TextInput::make('email')
                    ->email()
                    ->required(fn ($get) => $get('type') === 'email')
                    ->maxLength(255)
                    ->visible(fn ($get) => $get('type') === 'email'),
                Select::make('role')
                    ->options(collect(Role::cases())->reject(fn (Role $role) => $role === Role::SuperAdmin)->mapWithKeys(fn (Role $role) => [$role->value => $role->label()])->all())
                    ->required()
                    ->default(Role::Author->value)
                    ->native(false),
                DatePicker::make('expires_at')
                    ->label('Expires At')
                    ->required()
                    ->default(now()->addDays(7)),
                TextInput::make('token')
                    ->label('Shareable Link Token')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit')
                    ->hint(function (?Invitation $record) {
                        if (! $record || $record->type !== 'shareable') {
                            return '';
                        }

                        return route('invitations.accept', ['token' => $record->token]);
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->label('Email / Link')
                    ->getStateUsing(function (Invitation $record): string {
                        if ($record->type === 'shareable') {
                            return 'Shareable Link';
                        }

                        return $record->email;
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (Role $state): string => $state->label()),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'shareable' ? 'Shareable Link' : 'Email Invite')
                    ->color(fn (string $state): string => $state === 'shareable' ? 'warning' : 'primary'),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->placeholder('Never'),
                IconColumn::make('is_accepted')
                    ->label('Accepted')
                    ->getStateUsing(fn (Invitation $record) => $record->isAccepted())
                    ->boolean(),
                IconColumn::make('is_valid')
                    ->label('Valid')
                    ->getStateUsing(fn (Invitation $record) => $record->isValid())
                    ->boolean(),
                TextColumn::make('inviter.name')
                    ->label('Invited By')
                    ->placeholder('System'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvitations::route('/'),
            'create' => Pages\CreateInvitation::route('/create'),
            'edit' => Pages\EditInvitation::route('/{record}/edit'),
        ];
    }
}
