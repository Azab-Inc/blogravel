<?php

namespace App\Filament\Actions;

use App\Enums\PostStatus;
use App\Jobs\GenerateAiPostJob;
use App\Models\AiProvider;
use App\Models\Post;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconPosition;
use Illuminate\Support\Str;

class GenerateAiPostAction extends Action
{
    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'generateAi')
            ->label('Generate with AI')
            ->icon('heroicon-o-sparkles')
            ->iconPosition(IconPosition::After)
            ->schema([
                Select::make('ai_provider_id')
                    ->label('Provider')
                    ->options(fn () => AiProvider::where('tenant_id', auth()->user()->tenant_id)
                        ->where('enabled', true)
                        ->pluck('name', 'id'))
                    ->default(fn () => Setting::where('tenant_id', auth()->user()->tenant_id)
                        ->where('key', 'ai_default_provider')
                        ->value('value'))
                    ->required(),
                TextInput::make('ai_model')
                    ->label('Model')
                    ->default(fn () => Setting::where('tenant_id', auth()->user()->tenant_id)
                        ->where('key', 'ai_last_model')
                        ->value('value'))
                    ->required()
                    ->placeholder('e.g. gpt-4o, llama3'),
                Textarea::make('ai_prompt')
                    ->label('Prompt')
                    ->rows(4)
                    ->required()
                    ->placeholder('Describe what you want to write about...'),
                Radio::make('ai_length_type')
                    ->label('Content Length')
                    ->options([
                        'paragraphs' => 'Paragraphs',
                        'characters' => 'Characters',
                    ])
                    ->default('paragraphs')
                    ->inline(),
                TextInput::make('ai_length_value')
                    ->label('Length Value')
                    ->default('4')
                    ->required(),
                CheckboxList::make('ai_output_types')
                    ->label('Generate')
                    ->options([
                        'title' => 'Title',
                        'content' => 'Content',
                        'excerpt' => 'Excerpt',
                        'categories' => 'Categories',
                        'tags' => 'Tags',
                    ])
                    ->default(['title', 'content', 'excerpt', 'categories', 'tags'])
                    ->columns(3),
            ])
            ->action(function (array $data, ?Post $record): void {
                $tenantId = auth()->user()->tenant_id;

                $provider = AiProvider::where('tenant_id', $tenantId)
                    ->where('id', $data['ai_provider_id'])
                    ->first();

                if (! $provider) {
                    Notification::make()
                        ->title('Provider not found')
                        ->danger()
                        ->send();

                    return;
                }

                Setting::updateOrCreate(
                    ['tenant_id' => $tenantId, 'key' => 'ai_last_model'],
                    ['value' => $data['ai_model']]
                );

                $post = $record ?? Post::create([
                    'tenant_id' => $tenantId,
                    'author_id' => auth()->id(),
                    'title' => 'AI Draft',
                    'slug' => Str::slug('AI Draft').'-'.uniqid(),
                    'content' => '',
                    'status' => PostStatus::Draft,
                ]);

                GenerateAiPostJob::dispatch(
                    $post->id,
                    $data['ai_provider_id'],
                    $data['ai_model'],
                    $data['ai_prompt'],
                    $data['ai_output_types'],
                    [
                        'length_type' => $data['ai_length_type'],
                        'length_value' => (int) $data['ai_length_value'],
                    ]
                );

                Notification::make()
                    ->title('Generating content')
                    ->body("Your post is being generated. You'll receive a notification when it's ready. The post will be saved as a draft.")
                    ->success()
                    ->send();
            });
    }
}
