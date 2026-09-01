<?php

use App\Enums\AiProviderType;
use App\Enums\PostStatus;
use App\Jobs\GenerateAiPostJob;
use App\Models\AiProvider;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AiPostGenerated;
use App\Services\AiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

it('generates content and updates post', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => json_encode([
                    'title' => 'Generated Title',
                    'content' => '<p>Generated content</p>',
                    'excerpt' => 'Generated excerpt.',
                ])]],
            ],
        ], 200),
    ]);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $provider = AiProvider::factory()->create([
        'type' => AiProviderType::OpenAi,
        'base_url' => 'https://api.openai.com/v1',
        'tenant_id' => $tenant->id,
    ]);

    $post = Post::factory()->create([
        'tenant_id' => $tenant->id,
        'author_id' => $user->id,
        'status' => PostStatus::Draft,
    ]);

    Notification::fake();

    $job = new GenerateAiPostJob(
        $post->id,
        $provider->id,
        'gpt-4o',
        'Write about Laravel',
        ['title', 'content', 'excerpt'],
        ['length_type' => 'paragraphs', 'length_value' => 4]
    );

    $job->handle(app(AiService::class));

    $post->refresh();

    expect($post->title)->toBe('Generated Title')
        ->and($post->content)->toBe('<p>Generated content</p>')
        ->and($post->excerpt)->toBe('Generated excerpt.');

    Notification::assertSentTo($user, AiPostGenerated::class);
});

it('sends error notification on failure', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([], 429),
    ]);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $provider = AiProvider::factory()->create([
        'type' => AiProviderType::OpenAi,
        'base_url' => 'https://api.openai.com/v1',
        'tenant_id' => $tenant->id,
    ]);

    $post = Post::factory()->create([
        'tenant_id' => $tenant->id,
        'author_id' => $user->id,
    ]);

    Notification::fake();

    $job = new GenerateAiPostJob(
        $post->id,
        $provider->id,
        'gpt-4o',
        'test',
        ['title'],
        []
    );

    $job->handle(app(AiService::class));

    Notification::assertSentTo($user, function (AiPostGenerated $notification, array $channels, object $notifiable) {
        return $notification->title === 'AI generation failed';
    });
});

it('dispatches to tenant-specific queue', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $provider = AiProvider::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $post = Post::factory()->create([
        'tenant_id' => $tenant->id,
        'author_id' => $user->id,
    ]);

    $job = new GenerateAiPostJob(
        $post->id,
        $provider->id,
        'gpt-4o',
        'test',
        ['title'],
        []
    );

    expect($job->queue)->toBe("ai-generation-{$tenant->id}");
});
