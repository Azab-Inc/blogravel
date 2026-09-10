<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['domain' => 'sorotest.com']);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->secret = 'soro-webhook-secret-'.Str::random(20);
    Config::set('webhooks.soro_secret', $this->secret);
});

it('creates a draft post from valid Soro payload', function () {
    $payload = [
        'title' => 'AI Generated Post',
        'content' => '<p>This is generated content</p>',
        'excerpt' => 'A short excerpt',
        'categories' => ['Laravel', 'PHP'],
        'tags' => ['tutorial', 'beginner'],
        'featured_image' => 'https://example.com/image.jpg',
        'metadata' => [
            'meta_title' => 'SEO Title',
            'meta_description' => 'SEO description',
        ],
        'status' => 'draft',
    ];

    $signature = hash_hmac('sha256', json_encode($payload), $this->secret);

    $response = $this->postJson(route('api.webhooks.soro').'?tenant='.$this->tenant->domain, $payload, [
        'X-Blogravel-Signature' => $signature,
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('posts', [
        'tenant_id' => $this->tenant->id,
        'title' => 'AI Generated Post',
        'status' => PostStatus::Draft->value,
    ]);
});

it('returns 401 for invalid HMAC signature', function () {
    $payload = ['title' => 'Test'];

    $response = $this->postJson(route('api.webhooks.soro').'?tenant='.$this->tenant->domain, $payload, [
        'X-Blogravel-Signature' => 'invalid-signature',
    ]);

    $response->assertUnauthorized();
});

it('returns 401 when signature header is missing', function () {
    $payload = ['title' => 'Test'];

    $response = $this->postJson(route('api.webhooks.soro').'?tenant='.$this->tenant->domain, $payload);

    $response->assertUnauthorized();
});

it('returns 422 for malformed payload', function () {
    $payload = ['title' => 'Missing content'];

    $signature = hash_hmac('sha256', json_encode($payload), $this->secret);

    $response = $this->postJson(route('api.webhooks.soro').'?tenant='.$this->tenant->domain, $payload, [
        'X-Blogravel-Signature' => $signature,
    ]);

    $response->assertUnprocessable();
});

it('returns 404 for unknown tenant', function () {
    $payload = [
        'title' => 'Test',
        'content' => '<p>Content</p>',
    ];

    $signature = hash_hmac('sha256', json_encode($payload), $this->secret);

    $response = $this->postJson(route('api.webhooks.soro').'?tenant=unknown-domain.com', $payload, [
        'X-Blogravel-Signature' => $signature,
    ]);

    $response->assertNotFound();
});

it('syncs categories and tags from payload', function () {
    $payload = [
        'title' => 'Post with Taxonomies',
        'content' => '<p>Content</p>',
        'categories' => ['Laravel'],
        'tags' => ['tutorial'],
    ];

    $signature = hash_hmac('sha256', json_encode($payload), $this->secret);

    $response = $this->postJson(route('api.webhooks.soro').'?tenant='.$this->tenant->domain, $payload, [
        'X-Blogravel-Signature' => $signature,
    ]);

    $response->assertCreated();

    $post = Post::where('tenant_id', $this->tenant->id)
        ->where('title', 'Post with Taxonomies')
        ->first();

    $this->assertNotNull($post);
    $this->assertTrue($post->categories->contains('name', 'Laravel'));
    $this->assertTrue($post->tags->contains('name', 'tutorial'));
});
