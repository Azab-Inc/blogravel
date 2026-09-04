<?php

use App\Enums\ApiKeyAbility;
use App\Models\ApiKey;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;

function createTagApiKey(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $apiKey = ApiKey::factory()->create([
        'tenant_id' => $tenant->id,
        'abilities' => [ApiKeyAbility::Read, ApiKeyAbility::Write],
    ]);

    return ['user' => $user, 'apiKey' => $apiKey, 'tenant' => $tenant];
}

test('returns paginated list of tags', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createTagApiKey();

    Tag::factory()->count(3)->create([
        'tenant_id' => $user->tenant_id,
    ]);

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->getJson('/api/v1/tags')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'slug', 'posts_count'],
            ],
        ]);
});

test('returns single tag with posts count', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createTagApiKey();

    $tag = Tag::factory()->create([
        'tenant_id' => $user->tenant_id,
    ]);

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->getJson("/api/v1/tags/{$tag->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'slug', 'posts_count'],
        ])
        ->assertJsonPath('data.posts_count', 0);
});

test('returns 404 for nonexistent tag', function () {
    ['apiKey' => $apiKey] = createTagApiKey();

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->getJson('/api/v1/tags/nonexistent-uuid')
        ->assertNotFound();
});

test('creates a tag with valid data', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createTagApiKey();

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->postJson('/api/v1/tags', [
            'name' => 'Laravel',
        ])
        ->assertCreated()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'slug'],
        ]);

    $this->assertDatabaseHas('tags', [
        'name' => 'Laravel',
        'tenant_id' => $user->tenant_id,
    ]);
});

test('validates name is required on tag create', function () {
    ['apiKey' => $apiKey] = createTagApiKey();

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->postJson('/api/v1/tags', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('validates name max 255 on tag create', function () {
    ['apiKey' => $apiKey] = createTagApiKey();

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->postJson('/api/v1/tags', [
            'name' => str_repeat('a', 256),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('updates a tag', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createTagApiKey();

    $tag = Tag::factory()->create([
        'tenant_id' => $user->tenant_id,
    ]);

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->putJson("/api/v1/tags/{$tag->id}", [
            'name' => 'Updated Tag',
        ])
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'slug'],
        ]);

    $this->assertDatabaseHas('tags', [
        'id' => $tag->id,
        'name' => 'Updated Tag',
    ]);
});

test('soft deletes a tag', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createTagApiKey();

    $tag = Tag::factory()->create([
        'tenant_id' => $user->tenant_id,
    ]);

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->deleteJson("/api/v1/tags/{$tag->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
});
