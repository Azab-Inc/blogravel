<?php

namespace App\Filament\Resources;

use App\Enums\BackupContent;
use App\Enums\BackupDestination;
use App\Enums\NavGroup;
use App\Filament\Resources\BackupRuleResource\Pages;
use App\Jobs\CreateBackupJob;
use App\Models\BackupRule;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class BackupRuleResource extends Resource
{
    protected static ?string $model = BackupRule::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static UnitEnum|string|null $navigationGroup = NavGroup::Administration->value;

    protected static ?string $modelLabel = 'Backup Rule';

    protected static ?string $pluralModelLabel = 'Backup Rules';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Section::make('Backup Settings')->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('backup_content')
                    ->options(BackupContent::class)
                    ->required()
                    ->default(BackupContent::Both),

                Forms\Components\TextInput::make('schedule')
                    ->label('Cron Schedule')
                    ->required()
                    ->default('0 2 * * *')
                    ->helperText('e.g., 0 2 * * * = daily at 2am'),

                Forms\Components\Select::make('destination')
                    ->options(BackupDestination::class)
                    ->required()
                    ->default(BackupDestination::Email),

                Forms\Components\Toggle::make('enabled')
                    ->default(true),
            ]),

            Forms\Components\Section::make('FTP/SFTP Settings')
                ->collapsible()
                ->schema([
                    Forms\Components\TextInput::make('ftp_host')
                        ->label('Host'),
                    Forms\Components\TextInput::make('ftp_port')
                        ->label('Port')
                        ->numeric()
                        ->default(21),
                    Forms\Components\TextInput::make('ftp_user')
                        ->label('Username'),
                    Forms\Components\TextInput::make('ftp_pass')
                        ->label('Password')
                        ->password()
                        ->revealable(),
                    Forms\Components\TextInput::make('ftp_path')
                        ->label('Remote Path')
                        ->default('/'),
                ])
                ->visible(fn (Get $get) => in_array($get('destination'), ['ftp', 'both'])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('backup_content')
                    ->badge(),
                Tables\Columns\TextColumn::make('schedule'),
                Tables\Columns\TextColumn::make('destination')
                    ->badge(),
                Tables\Columns\IconColumn::make('enabled')
                    ->boolean(),
                Tables\Columns\TextColumn::make('last_run_at')
                    ->dateTime(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('runNow')
                    ->label('Run Now')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Run Backup Now')
                    ->modalDescription('This will immediately create a backup using this rule.')
                    ->action(function (BackupRule $record) {
                        CreateBackupJob::dispatch($record->id);

                        Notification::make()
                            ->title('Backup started')
                            ->body('The backup job has been queued.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListBackupRules::route('/'),
            'create' => Pages\CreateBackupRule::route('/create'),
            'edit' => Pages\EditBackupRule::route('/{record}/edit'),
        ];
    }
}
