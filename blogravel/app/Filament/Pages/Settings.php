<?php

namespace App\Filament\Pages;

use App\Enums\AiProviderType;
use App\Enums\NavGroup;
use App\Enums\Role;
use App\Models\AiProvider;
use App\Models\Setting;
use App\Models\Tenant;
use App\Providers\ThemeServiceProvider;
use App\Services\AccountLifecycleService;
use App\Services\DataExportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static UnitEnum|string|null $navigationGroup = NavGroup::Administration->value;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Settings';

    public ?array $data = [];

    public int $providerCount = 0;

    public ?string $defaultProvider = null;

    public ?array $defaultOutputTypes = [];

    public ?array $providers = [];

    public static function getSlug(?Panel $panel = null): string
    {
        return 'settings';
    }

    public function mount(): void
    {
        $user = Auth::user();

        $tenantId = $user->tenant_id;

        $this->providerCount = AiProvider::where('tenant_id', $tenantId)->count();
        $this->defaultProvider = $this->getSetting('ai_default_provider');
        $this->defaultOutputTypes = json_decode($this->getSetting('ai_default_output_types', '[]') ?? '[]', true);
        $this->providers = AiProvider::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get()
            ->map(fn (AiProvider $provider): array => [
                'id' => $provider->id,
                'name' => $provider->name,
                'type' => $provider->type->value,
                'base_url' => $provider->base_url,
                'api_key' => '',
                'model' => $provider->model,
                'temperature' => $provider->temperature,
                'max_tokens' => $provider->max_tokens,
                'custom_template' => $provider->custom_template,
                'enabled' => $provider->enabled,
            ])
            ->values()
            ->toArray();

        $this->fill([
            'data' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'authors_can_view_others_posts' => $this->getSetting('authors_can_view_others_posts', 'false') === 'true',
                'theme_enabled' => $this->getSetting('theme_enabled', 'true') === 'true',
                'active_theme' => $this->getSetting('active_theme', config('theme.default', 'base')),
                'defaultProvider' => $this->defaultProvider,
                'defaultOutputTypes' => $this->defaultOutputTypes,
                'providers' => $this->providers,
            ],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make([
                    'default' => 1,
                    'md' => 2,
                ])->schema([
                    Grid::make([
                        'default' => 1,
                    ])->schema([
                        Section::make('Site')
                            ->columns(1)
                            ->collapsible()
                            ->schema([
                                Toggle::make('authors_can_view_others_posts')
                                    ->label('Allow authors to view other authors\' draft posts')
                                    ->helperText('When enabled, authors can see draft and pending posts from other authors in the same tenant. When disabled, authors can only see their own drafts and all published posts.')
                                    ->default(false),
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
                                Action::make('saveSite')
                                    ->label('Save Site')
                                    ->action(function (): void {
                                        $this->saveSite();
                                    }),
                            ]),
                        Section::make('Account')
                            ->columns(1)
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
                                            ->action(function (array $data, AccountLifecycleService $lifecycle, DataExportService $exports): void {
                                                $format = $data['export_format'] ?? 'none';
                                                if ($format !== 'none') {
                                                    $user = Auth::user();
                                                    if ($user->tenant !== null) {
                                                        $exports->queue($user->tenant, $user, $format);
                                                    }
                                                }

                                                $this->closeAccount($data['tenant_confirmation'] ?? null, $lifecycle);
                                            }),
                                        Action::make('exportTenantData')
                                            ->label('Export tenant data')
                                            ->icon('heroicon-o-arrow-down-tray')
                                            ->form([
                                                Select::make('tenant_id')
                                                    ->label('Tenant')
                                                    ->options(fn (): array => $this->getExportableTenants())
                                                    ->default(fn (): ?string => Auth::user()->tenant_id)
                                                    ->required(fn (): bool => Auth::user()->isSuperAdmin())
                                                    ->visible(fn (): bool => Auth::user()->isSuperAdmin()),
                                                Select::make('format')
                                                    ->label('Format')
                                                    ->options([
                                                        'csv' => 'CSV',
                                                        'xlsx' => 'XLSX',
                                                    ])
                                                    ->default('csv')
                                                    ->required(),
                                            ])
                                            ->action(function (array $data, DataExportService $exports): void {
                                                $user = Auth::user();
                                                $tenant = $this->tenantForExport($data['tenant_id'] ?? null);
                                                $identifier = $exports->queue($tenant, $user, $data['format']);

                                                Notification::make()
                                                    ->title('Export queued')
                                                    ->body('Export '.$identifier.' will be available for download for 24 hours.')
                                                    ->success()
                                                    ->send();
                                            }),
                                    ]),
                                Action::make('saveAccount')
                                    ->label('Save Account')
                                    ->action(function (): void {
                                        $this->saveAccount();
                                    }),
                            ]),
                    ]),
                    Grid::make([
                        'default' => 1,
                    ])->schema([
                        Section::make('AI')
                            ->collapsible()
                            ->schema([
                                Section::make('Providers')
                                    ->schema([
                                        Placeholder::make('provider_count')
                                            ->label('Configured Providers')
                                            ->content(fn (): string => (string) $this->providerCount),
                                        Repeater::make('providers')
                                            ->label('')
                                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                            ->addActionLabel('Add Provider')
                                            ->columns(2)
                                            ->schema([
                                                Hidden::make('id'),
                                                TextInput::make('name')->label('Name')->required()->maxLength(255),
                                                Select::make('type')
                                                    ->label('Type')
                                                    ->options(collect(AiProviderType::cases())->mapWithKeys(fn (AiProviderType $type): array => [$type->value => $type->label()])->all())
                                                    ->required()->live()->default(AiProviderType::OpenAi->value),
                                                TextInput::make('base_url')
                                                    ->label('Base URL')->nullable()->url()->columnSpan(2)
                                                    ->placeholder(fn (Get $get): ?string => match ($get('type')) {
                                                        AiProviderType::Ollama->value => 'http://localhost:11434',
                                                        AiProviderType::Custom->value => 'https://api.example.com',
                                                        default => 'https://api.openai.com/v1',
                                                    }),
                                                TextInput::make('api_key')->label('API Key')->password()->revealable()->nullable()->helperText('Leave blank to keep the current API key.')->columnSpan(2),
                                                TextInput::make('model')->label('Model')->required()->placeholder('e.g. gpt-4o, llama3'),
                                                TextInput::make('temperature')->label('Temperature')->numeric()->minValue(0)->maxValue(2)->default(0.7)->step(0.1),
                                                TextInput::make('max_tokens')->label('Max Tokens')->numeric()->minValue(1)->default(2048),
                                                Toggle::make('enabled')->label('Enabled'),
                                                Textarea::make('custom_template')->label('Custom Template')->nullable()->rows(6)->visible(fn (Get $get): bool => $get('type') === AiProviderType::Custom->value)->required(fn (Get $get): bool => $get('type') === AiProviderType::Custom->value)->columnSpan(2),
                                            ]),
                                    ]),
                                Section::make('Defaults')
                                    ->schema([
                                        Select::make('defaultProvider')->label('Default Provider')->options(fn (): array => AiProvider::where('tenant_id', Auth::user()->tenant_id)->where('enabled', true)->pluck('name', 'id')->toArray())->nullable(),
                                        CheckboxList::make('defaultOutputTypes')->label('Default Output Types')->options([
                                            'title' => 'Title', 'content' => 'Content', 'excerpt' => 'Excerpt', 'categories' => 'Categories', 'tags' => 'Tags',
                                        ])->columns(3),
                                    ]),
                                Section::make('Media Generation')
                                    ->schema([
                                        Placeholder::make('coming_soon')->label('Coming Soon')->content('Media generation is coming soon. AI-powered image and video creation will be available in a future update.'),
                                    ]),
                                Action::make('saveAiSettings')
                                    ->label('Save AI Settings')
                                    ->action(function (): void {
                                        $this->saveAiSettings();
                                    }),
                            ]),

                    ]),
                ]),
            ])
            ->statePath('data');
    }

    public function saveSite(): void
    {
        $data = $this->data;
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
            ->title('Site settings saved')
            ->success()
            ->send();
    }

    public function saveAiSettings(): void
    {
        $this->defaultProvider = $this->data['defaultProvider'] ?? null;
        $this->defaultOutputTypes = $this->data['defaultOutputTypes'] ?? [];
        $this->providers = $this->data['providers'] ?? [];
        $tenantId = Auth::user()->tenant_id;

        $this->validate([
            'defaultProvider' => ['nullable', 'string'],
            'defaultOutputTypes' => ['nullable', 'array'],
            'defaultOutputTypes.*' => ['string', 'in:title,content,excerpt,categories,tags'],
            'providers' => ['present', 'array'],
            'providers.*.name' => ['required', 'string', 'max:255'],
            'providers.*.type' => ['required', 'string', 'in:'.implode(',', array_map(fn (AiProviderType $type) => $type->value, AiProviderType::cases()))],
            'providers.*.base_url' => ['nullable', 'url'],
            'providers.*.api_key' => ['nullable', 'string'],
            'providers.*.model' => ['required', 'string', 'max:255'],
            'providers.*.temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'providers.*.max_tokens' => ['required', 'integer', 'min:1'],
            'providers.*.custom_template' => ['nullable', 'string'],
        ]);

        foreach ($this->providers ?? [] as $key => $item) {
            if (($item['type'] ?? null) === AiProviderType::Custom->value && blank($item['custom_template'] ?? null)) {
                throw ValidationException::withMessages([
                    "providers.{$key}.custom_template" => 'The custom template is required for custom providers.',
                ]);
            }
        }

        DB::transaction(function () use ($tenantId): void {
            $submittedIds = [];

            foreach ($this->providers ?? [] as $item) {
                $attributes = [
                    'tenant_id' => $tenantId,
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'base_url' => $item['base_url'] ?? null,
                    'model' => $item['model'],
                    'temperature' => $item['temperature'] ?? 0.7,
                    'max_tokens' => $item['max_tokens'] ?? 2048,
                    'custom_template' => $item['type'] === AiProviderType::Custom->value ? ($item['custom_template'] ?? null) : null,
                    'enabled' => (bool) ($item['enabled'] ?? false),
                ];

                if (! empty($item['api_key'] ?? '')) {
                    $attributes['api_key'] = $item['api_key'];
                }

                if (! empty($item['id'])) {
                    $provider = AiProvider::where('tenant_id', $tenantId)->where('id', $item['id'])->first();
                    if ($provider) {
                        $provider->update($attributes);
                        $submittedIds[] = $provider->id;
                    }
                } else {
                    $attributes['api_key'] = $attributes['api_key'] ?? '';
                    $submittedIds[] = AiProvider::create($attributes)->id;
                }
            }

            AiProvider::where('tenant_id', $tenantId)->whereNotIn('id', $submittedIds)->delete();
        });

        Setting::updateOrCreate(['tenant_id' => $tenantId, 'key' => 'ai_default_provider'], ['value' => $this->defaultProvider]);
        Setting::updateOrCreate(['tenant_id' => $tenantId, 'key' => 'ai_default_output_types'], ['value' => json_encode($this->defaultOutputTypes)]);
        $this->providerCount = AiProvider::where('tenant_id', $tenantId)->count();

        Notification::make()->title('AI settings saved')->success()->send();
    }

    public function saveAccount(): void
    {
        $user = Auth::user();
        $data = $this->data;

        $this->validate([
            'data.first_name' => ['required', 'string', 'max:255'],
            'data.last_name' => ['required', 'string', 'max:255'],
            'data.email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'data.password' => ['nullable', 'string', 'max:255'],
            'data.password_confirmation' => ['required_with:data.password', 'same:data.password'],
            'data.current_password' => ['required_with:data.password', 'current_password:web'],
        ]);

        $user->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
        ]);

        if (! empty($data['password'])) {
            $user->update(['password' => $data['password']]);
            $this->data['current_password'] = null;
            $this->data['password'] = null;
            $this->data['password_confirmation'] = null;
        }

        Notification::make()
            ->title('Account settings saved')
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

        if ($tenant === null) {
            return [];
        }

        $form = [
            Select::make('export_format')
                ->label('Export a copy before closing')
                ->options([
                    'none' => 'No export',
                    'csv' => 'CSV',
                    'xlsx' => 'XLSX',
                ])
                ->default('none')
                ->required(),
        ];

        if (app(AccountLifecycleService::class)->isLastAdministrator($user)) {
            $form[] = TextInput::make('tenant_confirmation')
                ->label('Type the tenant name or slug to confirm')
                ->required()
                ->in(array_values(array_filter([$tenant->name, $tenant->slug])));
        }

        return $form;
    }

    protected function getSetting(string $key, ?string $default = null): ?string
    {
        $tenantId = auth()->user()->tenant_id;

        return Setting::where('tenant_id', $tenantId)
            ->where('key', $key)
            ->value('value') ?? $default;
    }

    /** @return array<string, string> */
    protected function getExportableTenants(): array
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin()) {
            return $user->tenant_id === null
                ? []
                : Tenant::whereKey($user->tenant_id)->pluck('name', 'id')->all();
        }

        return Tenant::withoutGlobalScopes()
            ->withTrashed()
            ->get()
            ->filter(fn (Tenant $tenant): bool => $tenant->deleted_at === null || ! $tenant->deleted_at->isBefore(now()->subDays(30)))
            ->sortBy('name')
            ->mapWithKeys(fn (Tenant $tenant): array => [
                $tenant->getKey() => $tenant->name.($tenant->trashed() ? ' (recoverable)' : ''),
            ])
            ->all();
    }

    protected function tenantForExport(?string $tenantId): Tenant
    {
        $user = Auth::user();

        if ($user->isSuperAdmin() && $tenantId !== null) {
            return Tenant::withoutGlobalScopes()->withTrashed()->findOrFail($tenantId);
        }

        return Tenant::findOrFail($user->tenant_id);
    }
}
