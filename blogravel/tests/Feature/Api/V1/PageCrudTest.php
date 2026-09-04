<?php

use App\Enums\ApiKeyAbility;
use App\Models\ApiKey;
use App\Models\Page;
use App\Models\Tenant;
use App\Models\User;

function createPageApiKey(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $apiKey = ApiKey::factory()->create([
        'tenant_id' => $tenant->id,
        'abilities' => [ApiKeyAbility::Read, ApiKeyAbility::Write],
    ]);

    return ['user' => $user, 'apiKey' => $apiKey, 'tenant' => $tenant];
}

test('returns paginated list of pages', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createPageApiKey();

    Page::factory()->count(3)->create([
        'tenant_id' => $user->tenant_id,
    ]);

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->getJson('/api/v1/pages')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'slug', 'content', 'status'],
            ],
        ]);
});

test('returns single page', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createPageApiKey();

    $page = Page::factory()->create([
        'tenant_id' => $user->tenant_id,
    ]);

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->getJson("/api/v1/pages/{$page->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'title', 'slug', 'content', 'status'],
        ]);
});

test('returns 404 for nonexistent page', function () {
    ['apiKey' => $apiKey] = createPageApiKey();

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->getJson('/api/v1/pages/nonexistent-uuid')
        ->assertNotFound();
});

test('creates a page with valid data', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createPageApiKey();

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->postJson('/api/v1/pages', [
            'title' => 'Test Page',
            'content' => 'Test page content',
            'status' => 'draft',
        ])
        ->assertCreated()
        ->assertJsonStructure([
            'data' => ['id', 'title', 'slug', 'content', 'status'],
        ]);

    $this->assertDatabaseHas('pages', [
        'title' => 'Test Page',
        'tenant_id' => $user->tenant_id,
    ]);
});

test('validates title is required on page create', function () {
    ['apiKey' => $apiKey] = createPageApiKey();

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->postJson('/api/v1/pages', [
            'content' => 'Content without title',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

test('validates content is required on page create', function () {
    ['apiKey' => $apiKey] = createPageApiKey();

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->postJson('/api/v1/pages', [
            'title' => 'Title without content',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);
});

test('validates status enum on page create', function () {
    ['apiKey' => $apiKey] = createPageApiKey();

    $this->withHeader('X-Api-Key', $apiKey->token)
        ->postJson('/api/v1/pages', [
            'title' => 'Valid Title',
            'content' => 'Valid content',
            'status' => 'invalid_status',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('updates a page', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createPageApiKey();

    $page = Page::factory()->create([
        'tenant_id' => $user->tenant_id,
    ]);

    $this->actingAs($user)
        ->withHeader('X-Api-Key', $apiKey->token)
        ->putJson("/api/v1/pages/{$page->id}", [
            'title' => 'Updated Page Title',
            'content' => 'Updated content',
        ])
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'title', 'slug', 'content'],
        ]);

    $this->assertDatabaseHas('pages', [
        'id' => $page->id,
        'title' => 'Updated Page Title',
    ]);
});

test('validates status enum on page update', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createPageApiKey();

    $page = Page::factory()->create([
        'tenant_id' => $user->tenant_id,
    ]);

    $this->actingAs($user)
        ->withHeader('X-Api-Key', $apiKey->token)
        ->putJson("/api/v1/pages/{$page->id}", [
            'status' => 'bad_status',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('soft deletes a page', function () {
    ['user' => $user, 'apiKey' => $apiKey] = createPageApiKey();

    $page = Page::factory()->create([
        'tenant_id' => $user->tenant_id,
    ]);

    $this->actingAs($user)
        ->withHeader('X-Api-Key', $apiKey->token)
        ->deleteJson("/api/v1/pages/{$page->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('pages', ['id' => $page->id]);
});
