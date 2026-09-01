<?php

namespace App\Filament\Pages;

use App\Models\AiProvider;
use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class AiSettings extends Page
{
    protected string $view = 'filament.pages.ai-settings';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static UnitEnum|string|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'AI Settings';

    public ?array $providers = [];

    public ?string $defaultProvider = null;

    public ?array $defaultOutputTypes = [];

    public function mount(): void
    {
        $tenantId = auth()->user()->tenant_id;

        $this->providers = AiProvider::where('tenant_id', $tenantId)
            ->get()
            ->map(fn (AiProvider $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type->value,
                'model' => $p->model,
                'base_url' => $p->base_url,
                'api_key' => $p->api_key,
                'temperature' => $p->temperature,
                'max_tokens' => $p->max_tokens,
                'custom_template' => $p->custom_template,
                'enabled' => $p->enabled,
            ])
            ->toArray();

        $this->defaultProvider = Setting::where('tenant_id', $tenantId)
            ->where('key', 'ai_default_provider')
            ->value('value');

        $this->defaultOutputTypes = json_decode(
            Setting::where('tenant_id', $tenantId)
                ->where('key', 'ai_default_output_types')
                ->value('value') ?? '[]',
            true
        );
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
                            ->content(fn () => (string) count($this->providers)),
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

        Setting::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'ai_default_provider'],
            ['value' => $this->defaultProvider]
        );

        Setting::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'ai_default_output_types'],
            ['value' => json_encode($this->defaultOutputTypes)]
        );

        Notification::make()
            ->title('Defaults saved')
            ->success()
            ->send();
    }
}
