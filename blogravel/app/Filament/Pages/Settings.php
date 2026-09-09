<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Models\Setting;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static UnitEnum|string|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->fill([
            'data' => [
                'authors_can_view_others_posts' => $this->getSetting('authors_can_view_others_posts', 'false') === 'true',
            ],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('General')
                    ->collapsible()
                    ->schema([
                        // Placeholder for future general settings
                    ]),
                Section::make('Permissions')
                    ->collapsible()
                    ->schema([
                        Toggle::make('data.authors_can_view_others_posts')
                            ->label('Allow authors to view other authors\' draft posts')
                            ->helperText('When enabled, authors can see draft and pending posts from other authors in the same tenant. When disabled, authors can only see their own drafts and all published posts.')
                            ->default(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $tenantId = auth()->user()->tenant_id;

        Setting::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'authors_can_view_others_posts'],
            ['value' => $this->data['authors_can_view_others_posts'] ? 'true' : 'false']
        );

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        return in_array($user->role, [Role::SuperAdmin, Role::Admin]);
    }

    protected function getSetting(string $key, ?string $default = null): ?string
    {
        $tenantId = auth()->user()->tenant_id;

        return Setting::where('tenant_id', $tenantId)
            ->where('key', $key)
            ->value('value') ?? $default;
    }
}
