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
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
            Section::make('Backup Settings')->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Select::make('backup_content')
                    ->options(BackupContent::class)
                    ->required()
                    ->default(BackupContent::Both),

                Radio::make('schedule_mode')
                    ->label('Schedule format')
                    ->options([
                        'simple' => 'Simple schedule',
                        'cron' => 'Advanced cron expression',
                    ])
                    ->default('simple')
                    ->inline()
                    ->live()
                    ->required(),

                TextInput::make('schedule_interval')
                    ->label('Run every')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required(fn (Get $get): bool => $get('schedule_mode') === 'simple')
                    ->visible(fn (Get $get): bool => $get('schedule_mode') === 'simple'),

                Select::make('schedule_unit')
                    ->label('Unit')
                    ->options([
                        'minute' => 'Minute(s)',
                        'hour' => 'Hour(s)',
                        'day' => 'Day(s)',
                        'week' => 'Week(s)',
                        'month' => 'Month(s)',
                    ])
                    ->default('day')
                    ->live()
                    ->required(fn (Get $get): bool => $get('schedule_mode') === 'simple')
                    ->visible(fn (Get $get): bool => $get('schedule_mode') === 'simple'),

                TextInput::make('schedule_time')
                    ->label('At')
                    ->type('time')
                    ->default('02:00')
                    ->required(fn (Get $get): bool => $get('schedule_mode') === 'simple' && in_array($get('schedule_unit'), ['day', 'week', 'month'], true))
                    ->visible(fn (Get $get): bool => $get('schedule_mode') === 'simple' && in_array($get('schedule_unit'), ['day', 'week', 'month'], true)),

                Select::make('schedule_weekday')
                    ->label('On')
                    ->options([
                        1 => 'Monday',
                        2 => 'Tuesday',
                        3 => 'Wednesday',
                        4 => 'Thursday',
                        5 => 'Friday',
                        6 => 'Saturday',
                        7 => 'Sunday',
                    ])
                    ->default(1)
                    ->required(fn (Get $get): bool => $get('schedule_mode') === 'simple' && $get('schedule_unit') === 'week')
                    ->visible(fn (Get $get): bool => $get('schedule_mode') === 'simple' && $get('schedule_unit') === 'week'),

                Select::make('schedule_month_day')
                    ->label('On day')
                    ->options(array_combine(range(1, 28), range(1, 28)))
                    ->default(1)
                    ->required(fn (Get $get): bool => $get('schedule_mode') === 'simple' && $get('schedule_unit') === 'month')
                    ->visible(fn (Get $get): bool => $get('schedule_mode') === 'simple' && $get('schedule_unit') === 'month'),

                Placeholder::make('schedule_preview')
                    ->label('Preview')
                    ->content(function (Get $get): string {
                        $interval = (int) ($get('schedule_interval') ?: 1);
                        $unit = (string) ($get('schedule_unit') ?: 'day');
                        $time = (string) ($get('schedule_time') ?: '02:00');

                        if (in_array($unit, ['minute', 'hour'], true)) {
                            return sprintf('Every %d %s', $interval, $unit.($interval === 1 ? '' : 's'));
                        }

                        return sprintf('Every %d %s at %s', $interval, $unit.($interval === 1 ? '' : 's'), $time);
                    })
                    ->visible(fn (Get $get): bool => $get('schedule_mode') === 'simple'),

                TextInput::make('schedule')
                    ->label('Cron expression')
                    ->placeholder('0 2 * * *')
                    ->helperText('Example: 0 2 * * * runs every day at 2:00 AM.')
                    ->required(fn (Get $get): bool => $get('schedule_mode') === 'cron')
                    ->visible(fn (Get $get): bool => $get('schedule_mode') === 'cron'),

                Select::make('destination')
                    ->options(BackupDestination::class)
                    ->required()
                    ->live()
                    ->default(BackupDestination::Email),

                TextInput::make('email_recipient')
                    ->label('Email recipient')
                    ->email()
                    ->default(fn (): ?string => auth()->user()?->email)
                    ->required(function (Get $get): bool {
                        $destination = $get('destination');

                        if ($destination instanceof BackupDestination) {
                            $destination = $destination->value;
                        }

                        return in_array($destination, ['email', 'both'], true);
                    })
                    ->visible(function (Get $get): bool {
                        $destination = $get('destination');

                        if ($destination instanceof BackupDestination) {
                            $destination = $destination->value;
                        }

                        return in_array($destination, ['email', 'both'], true);
                    })
                    ->helperText('Backup notifications will be sent to this address.'),

                Toggle::make('enabled')
                    ->default(true),
            ]),

            Section::make('FTP/SFTP Settings')
                ->collapsible()
                ->schema([
                    TextInput::make('ftp_host')
                        ->label('Host'),
                    TextInput::make('ftp_port')
                        ->label('Port')
                        ->numeric()
                        ->default(21),
                    TextInput::make('ftp_user')
                        ->label('Username'),
                    TextInput::make('ftp_pass')
                        ->label('Password')
                        ->password()
                        ->revealable(),
                    TextInput::make('ftp_path')
                        ->label('Remote Path')
                        ->default('/'),
                ])
                ->visible(function (Get $get): bool {
                    $destination = $get('destination');

                    if ($destination instanceof BackupDestination) {
                        $destination = $destination->value;
                    }

                    return in_array($destination, ['ftp', 'both'], true);
                }),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('backup_content')
                    ->badge(),
                TextColumn::make('schedule'),
                TextColumn::make('destination')
                    ->badge(),
                IconColumn::make('enabled')
                    ->boolean(),
                TextColumn::make('last_run_at')
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
            'index' => Pages\ListBackupRules::route('/'),
            'create' => Pages\CreateBackupRule::route('/create'),
            'edit' => Pages\EditBackupRule::route('/{record}/edit'),
        ];
    }
}
