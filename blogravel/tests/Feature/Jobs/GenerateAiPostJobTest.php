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

it('forces a published post back to draft when generating', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => json_encode([
                    'title' => 'Regenerated Title',
                    'content' => '<p>Fresh content</p>',
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
        'status' => PostStatus::Published,
        'content' => '<p>Old live content</p>',
    ]);

    Notification::fake();

    $job = new GenerateAiPostJob(
        $post->id,
        $provider->id,
        'gpt-4o',
        'Rewrite this',
        ['title', 'content'],
        []
    );

    $job->handle(app(AiService::class));

    $post->refresh();

    expect($post->status)->toBe(PostStatus::Draft)
        ->and($post->title)->toBe('Regenerated Title')
        ->and($post->content)->toBe('<p>Fresh content</p>');
});

it('notifies the author when the job fails unexpectedly', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $provider = AiProvider::factory()->create([
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

    $job->failed(new RuntimeException('boom'));

    Notification::assertSentTo($user, function (AiPostGenerated $notification) {
        return $notification->success === false
            && $notification->title === 'AI generation failed'
            && str_contains($notification->body, 'An unexpected error occurred: boom');
    });
});

it('does nothing when failed() runs for a deleted post', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $provider = AiProvider::factory()->create([
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

    $post->delete();
    $job->failed(new RuntimeException('boom'));

    Notification::assertNothingSent();
});

it('dispatches to a bounded pool queue derived from the tenant id', function () {
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

    $pool = crc32($tenant->id) % (int) config('queue.ai_generation.pools', 4);

    expect($job->queue)->toBe("ai-generation-{$pool}")
        ->and($pool)->toBeGreaterThanOrEqual(0)
        ->and($pool)->toBeLessThan(4);
});

it('dispatches different tenants into the valid pool range', function () {
    $provider = AiProvider::factory()->create();

    foreach (['tenant-a', 'tenant-b'] as $tenantId) {
        $post = Post::factory()->create([
            'tenant_id' => $tenantId,
            'author_id' => User::factory()->create(['tenant_id' => $tenantId])->id,
        ]);

        $job = new GenerateAiPostJob(
            $post->id,
            $provider->id,
            'gpt-4o',
            'test',
            ['title'],
            []
        );

        $pool = crc32($tenantId) % (int) config('queue.ai_generation.pools', 4);

        expect($job->queue)->toBe("ai-generation-{$pool}")
            ->and($pool)->toBeGreaterThanOrEqual(0)
            ->and($pool)->toBeLessThan(4);
    }
});
