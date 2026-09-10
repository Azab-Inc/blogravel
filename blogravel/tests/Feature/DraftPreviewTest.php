<?php

use App\Enums\ApiKeyAbility;
use App\Enums\PostStatus;
use App\Models\ApiKey;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['domain' => 'drafttest.com']);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('lists drafts with draft_read API key', function () {
    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    $key = ApiKey::factory()->create(['tenant_id' => $this->tenant->id]);
    $key->abilities = [ApiKeyAbility::DraftRead];
    $key->save();

    $this->withHeader('X-Api-Key', $key->token)
        ->getJson('/api/v1/drafts')
        ->assertOk()
        ->assertJsonFragment(['id' => $post->id]);
});

it('does not list drafts without draft_read ability', function () {
    Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    $key = ApiKey::factory()->create(['tenant_id' => $this->tenant->id]);
    $key->abilities = [ApiKeyAbility::Read];
    $key->save();

    $this->withHeader('X-Api-Key', $key->token)
        ->getJson('/api/v1/drafts')
        ->assertForbidden();
});

it('does not list drafts without API key', function () {
    Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    $this->getJson('/api/v1/drafts')
        ->assertUnauthorized();
});

it('shows draft with draft_read API key', function () {
    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    $key = ApiKey::factory()->create(['tenant_id' => $this->tenant->id]);
    $key->abilities = [ApiKeyAbility::DraftRead];
    $key->save();

    $this->withHeader('X-Api-Key', $key->token)
        ->getJson("/api/v1/drafts/{$post->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $post->id]);
});

it('shows draft with signed URL', function () {
    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    $expiresAt = now()->addHours(24);
    $token = Crypt::encryptString(json_encode([
        'post_id' => $post->id,
        'tenant_id' => $post->tenant_id,
        'expires_at' => $expiresAt->toIso8601String(),
    ]));

    $this->getJson("/api/v1/drafts/{$post->id}/preview?token={$token}&expires={$expiresAt->timestamp}")
        ->assertOk()
        ->assertJsonFragment(['id' => $post->id]);
});

it('rejects expired signed URL', function () {
    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    $expiresAt = now()->subHour();
    $token = Crypt::encryptString(json_encode([
        'post_id' => $post->id,
        'tenant_id' => $post->tenant_id,
        'expires_at' => $expiresAt->toIso8601String(),
    ]));

    $this->getJson("/api/v1/drafts/{$post->id}/preview?token={$token}&expires={$expiresAt->timestamp}")
        ->assertStatus(410);
});

it('rejects signed URL for wrong post', function () {
    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    $otherPost = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    $expiresAt = now()->addHours(24);
    $token = Crypt::encryptString(json_encode([
        'post_id' => $post->id,
        'tenant_id' => $post->tenant_id,
        'expires_at' => $expiresAt->toIso8601String(),
    ]));

    $this->getJson("/api/v1/drafts/{$otherPost->id}/preview?token={$token}&expires={$expiresAt->timestamp}")
        ->assertStatus(401);
});

it('rejects unsigned request without API key', function () {
    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    $this->getJson("/api/v1/drafts/{$post->id}")
        ->assertUnauthorized();
});

it('does not show published posts as drafts', function () {
    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    $key = ApiKey::factory()->create(['tenant_id' => $this->tenant->id]);
    $key->abilities = [ApiKeyAbility::DraftRead];
    $key->save();

    $this->withHeader('X-Api-Key', $key->token)
        ->getJson("/api/v1/drafts/{$post->id}")
        ->assertNotFound();
});

it('enforces tenant isolation for draft listing', function () {
    $otherTenant = Tenant::factory()->create(['domain' => 'other.com']);

    Post::factory()->create([
        'tenant_id' => $otherTenant->id,
        'author_id' => User::factory()->create(['tenant_id' => $otherTenant->id])->id,
        'status' => PostStatus::Draft,
    ]);

    $key = ApiKey::factory()->create(['tenant_id' => $this->tenant->id]);
    $key->abilities = [ApiKeyAbility::DraftRead];
    $key->save();

    $response = $this->withHeader('X-Api-Key', $key->token)
        ->getJson('/api/v1/drafts');

    $response->assertOk();
    $this->assertCount(0, $response->json('data'));
});

it('enforces tenant isolation for signed URL', function () {
    $otherTenant = Tenant::factory()->create(['domain' => 'other.com']);

    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    $expiresAt = now()->addHours(24);
    $token = Crypt::encryptString(json_encode([
        'post_id' => $post->id,
        'tenant_id' => $otherTenant->id,
        'expires_at' => $expiresAt->toIso8601String(),
    ]));

    $this->getJson("/api/v1/drafts/{$post->id}/preview?token={$token}&expires={$expiresAt->timestamp}")
        ->assertStatus(401);
});

it('generates preview URL with write API key', function () {
    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    $key = ApiKey::factory()->create(['tenant_id' => $this->tenant->id]);
    $key->abilities = [ApiKeyAbility::Write];
    $key->save();

    $response = $this->withHeader('X-Api-Key', $key->token)
        ->postJson("/api/v1/drafts/{$post->id}/preview-url");

    $response->assertOk()
        ->assertJsonStructure(['url', 'expires_at']);
});

it('rejects signed URL without token', function () {
    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    $this->getJson("/api/v1/drafts/{$post->id}/preview")
        ->assertStatus(401);
});
