<?php

use App\Enums\ApiKeyAbility;
use App\Enums\Role;
use App\Models\ApiKey;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;

function createPostApiKey(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create(['role' => Role::SuperAdmin]);
    $apiKey = ApiKey::factory()->create([
        'tenant_id' => $tenant->id,
        'abilities' => [ApiKeyAbility::Read, ApiKeyAbility::Write],
    ]);

    return ['user' => $user, 'apiKey' => $apiKey, 'tenant' => $tenant];
}

test('returns paginated list of posts', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createPostApiKey();

    Post::factory()->count(3)->create([
        'tenant_id' => $user->tenant_id,
        'author_id' => $user->id,
    ]);

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->getJson('/api/v1/posts')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'slug', 'content', 'status', 'author', 'categories', 'tags'],
            ],
        ]);
});

test('returns single post with author categories and tags', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createPostApiKey();

    $category = Category::factory()->create(['tenant_id' => $user->tenant_id]);
    $tag = Tag::factory()->create(['tenant_id' => $user->tenant_id]);

    $post = Post::factory()->create([
        'tenant_id' => $user->tenant_id,
        'author_id' => $user->id,
    ]);
    $post->categories()->attach($category->id);
    $post->tags()->attach($tag->id);

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->getJson("/api/v1/posts/{$post->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id', 'title', 'slug', 'content', 'status',
                'author' => ['id', 'name'],
                'categories' => [['id', 'name']],
                'tags' => [['id', 'name']],
            ],
        ]);
});

test('returns 404 for nonexistent post', function () {
    ['apiKey' => $apiKey] = createPostApiKey();

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->getJson('/api/v1/posts/nonexistent-uuid')
        ->assertNotFound();
});

test('creates a post with valid data', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createPostApiKey();

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->postJson('/api/v1/posts', [
            'title' => 'Test Post',
            'content' => 'Test content here',
            'status' => 'draft',
        ])
        ->assertCreated()
        ->assertJsonStructure([
            'data' => ['id', 'title', 'slug', 'content', 'status'],
        ]);

    $this->assertDatabaseHas('posts', [
        'title' => 'Test Post',
        'tenant_id' => $user->tenant_id,
    ]);
});

test('validates title is required on create', function () {
    ['apiKey' => $apiKey] = createPostApiKey();

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->postJson('/api/v1/posts', [
            'content' => 'Content without title',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

test('validates content is required on create', function () {
    ['apiKey' => $apiKey] = createPostApiKey();

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->postJson('/api/v1/posts', [
            'title' => 'Title without content',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);
});

test('validates status must be draft scheduled or published on create', function () {
    ['apiKey' => $apiKey] = createPostApiKey();

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->postJson('/api/v1/posts', [
            'title' => 'Valid Title',
            'content' => 'Valid content',
            'status' => 'invalid_status',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('updates a post', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createPostApiKey();

    $post = Post::factory()->create([
        'tenant_id' => $user->tenant_id,
        'author_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->withHeader('X-Api-Key', $apiKey->token)
        ->putJson("/api/v1/posts/{$post->id}", [
            'title' => 'Updated Title',
            'content' => 'Updated content',
        ])
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => ['id', 'title', 'slug', 'content'],
        ]);

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'title' => 'Updated Title',
    ]);
});

test('validates status must be draft scheduled or published on update', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createPostApiKey();

    $post = Post::factory()->create([
        'tenant_id' => $user->tenant_id,
        'author_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->withHeader('X-Api-Key', $apiKey->token)
        ->putJson("/api/v1/posts/{$post->id}", [
            'status' => 'bad_status',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('soft deletes a post', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createPostApiKey();

    $post = Post::factory()->create([
        'tenant_id' => $user->tenant_id,
        'author_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->withHeader('X-Api-Key', $apiKey->token)
        ->deleteJson("/api/v1/posts/{$post->id}")
        ->assertSuccessful();

    $this->assertDatabaseMissing('posts', ['id' => $post->id]);
});
