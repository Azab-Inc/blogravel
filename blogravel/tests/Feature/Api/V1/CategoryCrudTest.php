<?php

use App\Enums\ApiKeyAbility;
use App\Models\ApiKey;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;

function createCategoryApiKey(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $apiKey = ApiKey::factory()->create([
        'tenant_id' => $tenant->id,
        'abilities' => [ApiKeyAbility::Read, ApiKeyAbility::Write],
    ]);

    return ['user' => $user, 'apiKey' => $apiKey, 'tenant' => $tenant];
}

test('returns paginated list of categories', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createCategoryApiKey();

    Category::factory()->count(3)->create([
        'tenant_id' => $user->tenant_id,
    ]);

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->getJson(route('categories.index'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'slug', 'posts_count'],
            ],
        ]);
});

test('returns single category with posts count', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createCategoryApiKey();

    $category = Category::factory()->create([
        'tenant_id' => $user->tenant_id,
    ]);

    Post::factory()->count(2)->create([
        'tenant_id' => $user->tenant_id,
        'author_id' => $user->id,
    ])->each(function ($post) use ($category) {
        $post->categories()->attach($category->id);
    });

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->getJson(route('categories.show', $category))
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'slug', 'posts_count'],
        ])
        ->assertJsonPath('data.posts_count', 2);
});

test('returns 404 for nonexistent category', function () {
    ['apiKey' => $apiKey] = createCategoryApiKey();

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->getJson(route('categories.show', 'nonexistent-uuid'))
        ->assertNotFound();
});

test('creates a category with valid data', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createCategoryApiKey();

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->postJson(route('categories.store'), [
            'name' => 'Technology',
        ])
        ->assertCreated()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'slug'],
        ]);

    $this->assertDatabaseHas('categories', [
        'name' => 'Technology',
        'tenant_id' => $user->tenant_id,
    ]);
});

test('validates name is required on category create', function () {
    ['apiKey' => $apiKey] = createCategoryApiKey();

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->postJson(route('categories.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('validates name max 255 on category create', function () {
    ['apiKey' => $apiKey] = createCategoryApiKey();

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->postJson(route('categories.store'), [
            'name' => str_repeat('a', 256),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('updates a category', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createCategoryApiKey();

    $category = Category::factory()->create([
        'tenant_id' => $user->tenant_id,
    ]);

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->putJson(route('categories.update', $category), [
            'name' => 'Updated Category',
        ])
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'slug'],
        ]);

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Updated Category',
    ]);
});

test('soft deletes a category', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createCategoryApiKey();

    $category = Category::factory()->create([
        'tenant_id' => $user->tenant_id,
    ]);

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->deleteJson(route('categories.destroy', $category))
        ->assertNoContent();

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});
