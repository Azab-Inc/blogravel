<?php

namespace App\Filament\Pages;

use App\Enums\AiProviderType;
use App\Models\AiProvider;
use App\Models\Setting;
use BackedEnum;
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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class AiSettings extends Page
{
    protected string $view = 'filament.pages.ai-settings';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static UnitEnum|string|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'AI Settings';

    public int $providerCount = 0;

    public ?string $defaultProvider = null;

    public ?array $defaultOutputTypes = [];

    public ?array $providers = [];

    public function mount(): void
    {
        $tenantId = auth()->user()->tenant_id;

        $this->providerCount = AiProvider::where('tenant_id', $tenantId)->count();

        $this->defaultProvider = Setting::where('tenant_id', $tenantId)
            ->where('key', 'ai_default_provider')
            ->value('value');

        $this->defaultOutputTypes = json_decode(
            Setting::where('tenant_id', $tenantId)
                ->where('key', 'ai_default_output_types')
                ->value('value') ?? '[]',
            true
        );

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
    }

    public function form(Schema $schema): Schema
    {
        $enabledProviders = AiProvider::where('tenant_id', auth()->user()->tenant_id)
            ->where('enabled', true)
            ->pluck('name', 'id')
            ->toArray();

        return $schema
            ->components([
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
                                TextInput::make('name')
                                    ->label('Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),
                                Select::make('type')
                                    ->label('Type')
                                    ->options(collect(AiProviderType::cases())->mapWithKeys(fn (AiProviderType $type): array => [$type->value => $type->label()])->all())
                                    ->required()
                                    ->live()
                                    ->default(AiProviderType::OpenAi->value)
                                    ->columnSpan(1),
                                TextInput::make('base_url')
                                    ->label('Base URL')
                                    ->nullable()
                                    ->url()
                                    ->placeholder(fn (Get $get): ?string => match ($get('type')) {
                                        AiProviderType::Ollama->value => 'http://localhost:11434',
                                        AiProviderType::Custom->value => 'https://api.example.com',
                                        default => 'https://api.openai.com/v1',
                                    })
                                    ->columnSpan(2),
                                TextInput::make('api_key')
                                    ->label('API Key')
                                    ->password()
                                    ->revealable()
                                    ->nullable()
                                    ->helperText('Leave blank to keep the current API key.')
                                    ->columnSpan(2),
                                TextInput::make('model')
                                    ->label('Model')
                                    ->required()
                                    ->placeholder('e.g. gpt-4o, llama3')
                                    ->columnSpan(1),
                                TextInput::make('temperature')
                                    ->label('Temperature')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(2)
                                    ->default(0.7)
                                    ->step(0.1)
                                    ->columnSpan(1),
                                TextInput::make('max_tokens')
                                    ->label('Max Tokens')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(2048)
                                    ->columnSpan(1),
                                Toggle::make('enabled')
                                    ->label('Enabled')
                                    ->default(true)
                                    ->columnSpan(1),
                                Textarea::make('custom_template')
                                    ->label('Custom Template')
                                    ->nullable()
                                    ->rows(6)
                                    ->visible(fn (Get $get): bool => $get('type') === AiProviderType::Custom->value)
                                    ->required(fn (Get $get): bool => $get('type') === AiProviderType::Custom->value)
                                    ->columnSpan(2),
                            ]),
                    ]),
                Section::make('Defaults')
                    ->schema([
                        Select::make('defaultProvider')
                            ->label('Default Provider')
                            ->options($enabledProviders)
                            ->nullable(),
                        CheckboxList::make('defaultOutputTypes')
                            ->label('Default Output Types')
                            ->options([
                                'title' => 'Title',
                                'content' => 'Content',
                                'excerpt' => 'Excerpt',
                                'categories' => 'Categories',
                                'tags' => 'Tags',
                            ])
                            ->columns(3),
                    ]),
                Section::make('Media Generation')
                    ->schema([
                        Placeholder::make('coming_soon')
                            ->label('Coming Soon')
                            ->content('Media generation is coming soon. AI-powered image and video creation will be available in a future update.'),
                    ]),
            ]);
    }

    public function saveDefaults(): void
    {
        $tenantId = auth()->user()->tenant_id;

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
                $this->addError("providers.{$key}.custom_template", 'The custom template is required for custom providers.');

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

                if (! empty(($item['api_key'] ?? ''))) {
                    $attributes['api_key'] = $item['api_key'];
                }

                if (! empty($item['id'])) {
                    $provider = AiProvider::where('tenant_id', $tenantId)
                        ->where('id', $item['id'])
                        ->first();

                    if ($provider) {
                        $provider->update($attributes);
                        $submittedIds[] = $provider->id;
                    }
                } else {
                    $attributes['api_key'] = $attributes['api_key'] ?? '';
                    $provider = AiProvider::create($attributes);
                    $submittedIds[] = $provider->id;
                }
            }

            AiProvider::where('tenant_id', $tenantId)
                ->whereNotIn('id', $submittedIds)
                ->delete();
        });

        Setting::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'ai_default_provider'],
            ['value' => $this->defaultProvider]
        );

        Setting::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'ai_default_output_types'],
            ['value' => json_encode($this->defaultOutputTypes)]
        );

        $this->providerCount = AiProvider::where('tenant_id', $tenantId)->count();

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
