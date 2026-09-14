<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Models\Setting;
use App\Providers\ThemeServiceProvider;
use App\Services\AccountLifecycleService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Settings';

    public ?array $data = [];

    public static function getSlug(?Panel $panel = null): string
    {
        return 'settings';
    }

    public function mount(): void
    {
        $user = Auth::user();

        $this->fill([
            'data' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'authors_can_view_others_posts' => $this->getSetting('authors_can_view_others_posts', 'false') === 'true',
                'theme_enabled' => $this->getSetting('theme_enabled', 'true') === 'true',
                'active_theme' => $this->getSetting('active_theme', config('theme.default', 'base')),
            ],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Permissions')
                    ->collapsible()
                    ->schema([
                        Toggle::make('authors_can_view_others_posts')
                            ->label('Allow authors to view other authors\' draft posts')
                            ->helperText('When enabled, authors can see draft and pending posts from other authors in the same tenant. When disabled, authors can only see their own drafts and all published posts.')
                            ->default(false),
                    ]),
                Section::make('Site')
                    ->collapsible()
                    ->schema([
                        Toggle::make('theme_enabled')
                            ->label('Enable public theme frontend')
                            ->helperText('When enabled, visitors can view your blog via the public theme. When disabled, only the API is available (headless mode).')
                            ->default(true),
                        Select::make('active_theme')
                            ->label('Active Theme')
                            ->helperText('Select the theme used for your public blog frontend.')
                            ->options(fn () => collect(app()->getProvider(ThemeServiceProvider::class)?->getAvailableThemes() ?? [])
                                ->mapWithKeys(fn ($theme) => [$theme['name'] => $theme['name'].($theme['is_base'] ? ' (base)' : '')])
                                ->toArray())
                            ->default(config('theme.default', 'base'))
                            ->visible(fn (Get $get): bool => $get('theme_enabled')),
                    ]),
                Section::make('Account')
                    ->collapsible()
                    ->schema([
                        TextInput::make('first_name')
                            ->label('First Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->label('Last Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Section::make('Password')
                            ->description('Leave blank to keep current password.')
                            ->schema([
                                TextInput::make('password')
                                    ->label('New Password')
                                    ->password()
                                    ->revealable()
                                    ->dehydrated(false)
                                    ->maxLength(255),
                                TextInput::make('password_confirmation')
                                    ->label('Confirm New Password')
                                    ->password()
                                    ->revealable()
                                    ->dehydrated(false)
                                    ->maxLength(255)
                                    ->visible(true)
                                    ->required(fn (Get $get): bool => filled($get('password'))),
                                TextInput::make('current_password')
                                    ->label('Current Password')
                                    ->password()
                                    ->revealable()
                                    ->dehydrated(false)
                                    ->visible(fn (Get $get): bool => filled($get('password')) || ($get('email') !== Auth::user()->email)),
                            ]),
                        Section::make('Danger Zone')
                            ->description('Closing your account will soft-delete your profile. You have 30 days to recover it by contacting support.')
                            ->schema([
                                Placeholder::make('closure_warning')
                                    ->content(function () {
                                        $user = Auth::user();
                                        if (app(AccountLifecycleService::class)->isLastAdministrator($user)) {
                                            return 'You are the only administrator. Closing this account will also close your tenant. You have 30 days to recover your account and tenant by contacting support.';
                                        }

                                        return null;
                                    }),
                                Action::make('closeAccount')
                                    ->label('Close Account')
                                    ->color('danger')
                                    ->icon('heroicon-o-trash')
                                    ->requiresConfirmation()
                                    ->modalHeading('Close Account')
                                    ->modalDescription('Are you sure you want to close your account? If you are the last administrator, your tenant will also be closed. This action can be reversed within 30 days by contacting support.')
                                    ->modalSubmitActionLabel('Yes, Close My Account')
                                    ->form(fn (): array => $this->getCloseAccountForm())
                                    ->action(function (array $data, AccountLifecycleService $lifecycle): void {
                                        $this->closeAccount($data['tenant_confirmation'] ?? null, $lifecycle);
                                    }),
                            ]),
                    ]),

            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $user = Auth::user();
        $data = $this->data;

        $this->validate([
            'data.first_name' => ['required', 'string', 'max:255'],
            'data.last_name' => ['required', 'string', 'max:255'],
            'data.email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        $user->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
        ]);

        if (! empty($data['password'])) {
            if (! empty($data['current_password']) && Hash::check($data['current_password'], $user->password)) {
                $user->update(['password' => $data['password']]);
                $this->data['current_password'] = null;
                $this->data['password'] = null;
                $this->data['password_confirmation'] = null;
            }
        }

        $tenantId = auth()->user()->tenant_id;
        Setting::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'authors_can_view_others_posts'],
            ['value' => $data['authors_can_view_others_posts'] ? 'true' : 'false']
        );

        Setting::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'theme_enabled'],
            ['value' => $data['theme_enabled'] ? 'true' : 'false']
        );

        Setting::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'active_theme'],
            ['value' => $data['active_theme'] ?? config('theme.default', 'base')]
        );

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    public function closeAccount(?string $tenantConfirmation = null, ?AccountLifecycleService $lifecycle = null): void
    {
        $user = Auth::user();
        $lifecycle ??= app(AccountLifecycleService::class);

        $lifecycle->close($user, $tenantConfirmation);

        Auth::logout();

        Notification::make()
            ->title('Account closed')
            ->body('Your account has been closed. You have 30 days to recover it by contacting support.')
            ->success()
            ->send();

        $this->redirect(route('filament.admin.auth.login'));
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        return in_array($user->role, [Role::SuperAdmin, Role::Admin]);
    }

    protected function getCloseAccountForm(): array
    {
        $user = Auth::user();
        $tenant = $user->tenant;

        if ($tenant === null || ! app(AccountLifecycleService::class)->isLastAdministrator($user)) {
            return [];
        }

        return [
            TextInput::make('tenant_confirmation')
                ->label('Type the tenant name or slug to confirm')
                ->required()
                ->in(array_values(array_filter([$tenant->name, $tenant->slug]))),
        ];
    }

    protected function getSetting(string $key, ?string $default = null): ?string
    {
        $tenantId = auth()->user()->tenant_id;

        return Setting::where('tenant_id', $tenantId)
            ->where('key', $key)
            ->value('value') ?? $default;
    }
}
