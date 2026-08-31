<?php

use App\Enums\ApiKeyAbility;
use App\Models\ApiKey;
use App\Models\Tenant;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

it('rejects write without write ability', function () {
    $key = ApiKey::factory()->create([
        'tenant_id' => $this->tenant->id,
        'abilities' => [ApiKeyAbility::Read],
    ]);

    $this->withHeader('X-Api-Key', $key->token)
        ->postJson('/api/v1/posts', [
            'title' => 'Test Post',
            'content' => 'Test content',
        ])
        ->assertForbidden();
});

it('rejects read without read ability', function () {
    $key = ApiKey::factory()->create([
        'tenant_id' => $this->tenant->id,
        'abilities' => [ApiKeyAbility::Write],
    ]);

    $this->withHeader('X-Api-Key', $key->token)
        ->getJson('/api/v1/posts')
        ->assertForbidden();
});

it('rejects draft read without draft_read ability', function () {
    $key = ApiKey::factory()->create([
        'tenant_id' => $this->tenant->id,
        'abilities' => [ApiKeyAbility::Read],
    ]);

    $this->withHeader('X-Api-Key', $key->token)
        ->getJson('/api/v1/drafts')
        ->assertForbidden();
});

it('rejects expired api key', function () {
    $key = ApiKey::factory()->create([
        'tenant_id' => $this->tenant->id,
        'abilities' => [ApiKeyAbility::Read],
        'expires_at' => now()->subHour(),
    ]);

    $this->withHeader('X-Api-Key', $key->token)
        ->getJson('/api/v1/posts')
        ->assertUnauthorized();
});

it('rejects invalid api key', function () {
    $this->withHeader('X-Api-Key', 'nonexistent-key')
        ->getJson('/api/v1/posts')
        ->assertUnauthorized();
});

it('rejects request without api key header', function () {
    $this->getJson('/api/v1/posts')
        ->assertUnauthorized();
});

it('rejects write for expired api key', function () {
    $key = ApiKey::factory()->create([
        'tenant_id' => $this->tenant->id,
        'abilities' => [ApiKeyAbility::Write],
        'expires_at' => now()->subHour(),
    ]);

    $this->withHeader('X-Api-Key', $key->token)
        ->postJson('/api/v1/posts', [
            'title' => 'Test Post',
            'content' => 'Test content',
        ])
        ->assertUnauthorized();
});

it('rejects draft read for expired api key', function () {
    $key = ApiKey::factory()->create([
        'tenant_id' => $this->tenant->id,
        'abilities' => [ApiKeyAbility::DraftRead],
        'expires_at' => now()->subHour(),
    ]);

    $this->withHeader('X-Api-Key', $key->token)
        ->getJson('/api/v1/drafts')
        ->assertUnauthorized();
});

it('allows draft read with draft_read ability', function () {
    $key = ApiKey::factory()->create([
        'tenant_id' => $this->tenant->id,
        'abilities' => [ApiKeyAbility::DraftRead],
    ]);

    $this->withHeader('X-Api-Key', $key->token)
        ->getJson('/api/v1/drafts')
        ->assertOk();
});

it('validates api key token uniqueness', function () {
    $key1 = ApiKey::factory()->create([
        'tenant_id' => $this->tenant->id,
        'abilities' => [ApiKeyAbility::Read],
    ]);

    $key2 = ApiKey::factory()->create([
        'tenant_id' => $this->tenant->id,
        'abilities' => [ApiKeyAbility::Read],
    ]);

    expect($key1->token)->not->toBe($key2->token);
});
