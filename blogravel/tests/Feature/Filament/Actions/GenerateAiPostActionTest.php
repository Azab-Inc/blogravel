<?php

use App\Enums\PostStatus;
use App\Enums\Role;
use App\Filament\Resources\PostResource\Pages\CreatePost;
use App\Filament\Resources\PostResource\Pages\EditPost;
use App\Jobs\GenerateAiPostJob;
use App\Models\AiProvider;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Queue;

it('renders the generate ai post action on the post create page', function () {
    $user = User::factory()->create(['has_email_authentication' => true, 'role' => Role::Author]);

    $this->actingAs($user);

    $this->get('/admin/posts/create')->assertOk();
});

it('shows the generate ai post action on the post form', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'has_email_authentication' => true,
        'role' => Role::Author,
    ]);

    $this->actingAs($user);

    Livewire::test(CreatePost::class)
        ->assertActionVisible(TestAction::make('generateAi')->schemaComponent(true));
});

it('opens the generation modal with all configuration fields', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'has_email_authentication' => true,
        'role' => Role::Author,
    ]);

    $this->actingAs($user);

    Livewire::test(CreatePost::class)
        ->mountAction(TestAction::make('generateAi')->schemaComponent(true))
        ->assertMountedActionModalSee('Provider')
        ->assertMountedActionModalSee('Model')
        ->assertMountedActionModalSee('Prompt')
        ->assertMountedActionModalSee('Content Length')
        ->assertMountedActionModalSee('Length Value')
        ->assertMountedActionModalSee('Generate');
});

it('creates a draft post and dispatches the generation job when the action runs on the create page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'has_email_authentication' => true,
        'role' => Role::Author,
    ]);
    $provider = AiProvider::factory()->create([
        'tenant_id' => $tenant->id,
        'enabled' => true,
    ]);

    Queue::fake();
    $this->actingAs($user);

    Livewire::test(CreatePost::class)
        ->callAction(
            TestAction::make('generateAi')->schemaComponent(true),
            [
                'ai_provider_id' => $provider->id,
                'ai_model' => 'gpt-4o',
                'ai_prompt' => 'Write about travel in Japan',
                'ai_length_type' => 'paragraphs',
                'ai_length_value' => '4',
                'ai_output_types' => ['title', 'content'],
            ],
        )
        ->assertNotified('Generating content');

    $post = Post::query()
        ->where('tenant_id', $tenant->id)
        ->where('author_id', $user->id)
        ->first();

    expect($post)->not->toBeNull()
        ->and($post->status)->toBe(PostStatus::Draft)
        ->and($post->title)->toBe('AI Draft');

    Queue::assertPushed(GenerateAiPostJob::class, function (GenerateAiPostJob $job) use ($post, $provider): bool {
        return $job->postId === $post->id
            && $job->providerId === $provider->id
            && $job->model === 'gpt-4o'
            && $job->prompt === 'Write about travel in Japan'
            && $job->outputTypes === ['title', 'content']
            && $job->options === ['length_type' => 'paragraphs', 'length_value' => 4];
    });

    expect(Setting::query()
        ->where('tenant_id', $tenant->id)
        ->where('key', 'ai_last_model')
        ->value('value'))->toBe('gpt-4o');
});

it('reuses the existing record and dispatches the generation job when the action runs on the edit page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'has_email_authentication' => true,
        'role' => Role::Author,
    ]);
    $provider = AiProvider::factory()->create([
        'tenant_id' => $tenant->id,
        'enabled' => true,
    ]);
    $post = Post::factory()->create([
        'tenant_id' => $tenant->id,
        'author_id' => $user->id,
        'status' => PostStatus::Published,
    ]);

    Queue::fake();
    $this->actingAs($user);

    Livewire::test(EditPost::class, ['record' => $post->getKey()])
        ->callAction(
            TestAction::make('generateAi')->schemaComponent(true),
            [
                'ai_provider_id' => $provider->id,
                'ai_model' => 'llama3',
                'ai_prompt' => 'Write about beaches',
                'ai_length_type' => 'characters',
                'ai_length_value' => '500',
                'ai_output_types' => ['title', 'content', 'excerpt'],
            ],
        )
        ->assertNotified('Generating content');

    expect(Post::query()->where('tenant_id', $tenant->id)->count())->toBe(1);

    Queue::assertPushed(GenerateAiPostJob::class, function (GenerateAiPostJob $job) use ($post, $provider): bool {
        return $job->postId === $post->id
            && $job->providerId === $provider->id
            && $job->model === 'llama3';
    });
});

it('rejects a provider from another tenant with a validation error and dispatches nothing', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'has_email_authentication' => true,
        'role' => Role::Author,
    ]);
    $provider = AiProvider::factory()->create([
        'tenant_id' => $otherTenant->id,
        'enabled' => true,
    ]);

    Queue::fake();
    $this->actingAs($user);

    Livewire::test(CreatePost::class)
        ->callAction(
            TestAction::make('generateAi')->schemaComponent(true),
            [
                'ai_provider_id' => $provider->id,
                'ai_model' => 'gpt-4o',
                'ai_prompt' => 'Write about travel in Japan',
                'ai_length_type' => 'paragraphs',
                'ai_length_value' => '4',
                'ai_output_types' => ['title', 'content'],
            ],
        )
        ->assertHasActionErrors(['ai_provider_id']);

    Queue::assertNotPushed(GenerateAiPostJob::class);
    expect(Post::query()->where('tenant_id', $tenant->id)->count())->toBe(0);
});
