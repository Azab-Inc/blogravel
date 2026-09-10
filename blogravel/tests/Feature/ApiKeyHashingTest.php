<?php

use App\Enums\ApiKeyAbility;
use App\Models\ApiKey;
use Illuminate\Support\Str;

it('hashes the token on creation', function () {
    $key = ApiKey::factory()->create([
        'token' => null,
    ]);

    $this->assertNotNull($key->key_hash);
    $this->assertNull($key->token);
});

it('middleware authenticates via key_hash', function () {
    $key = ApiKey::factory()->withTenantUser()->create([
        'abilities' => [ApiKeyAbility::Read],
        'token' => null,
    ]);

    $plaintext = 'test-api-key-'.Str::random(20);
    $key->setPlaintext($plaintext);

    $this->withHeader('X-Api-Key', $plaintext)
        ->getJson('/api/v1/posts')
        ->assertOk();
});

it('middleware rejects invalid key', function () {
    $this->withHeader('X-Api-Key', 'nonexistent-key')
        ->getJson('/api/v1/posts')
        ->assertUnauthorized();
});

it('defaults to 100 requests per minute', function () {
    $key = ApiKey::factory()->withTenantUser()->create([
        'abilities' => [ApiKeyAbility::Read],
        'token' => null,
        'rate_limit_per_minute' => null,
    ]);

    $plaintext = 'test-api-key-'.Str::random(20);
    $key->setPlaintext($plaintext);

    // 100 requests should pass
    for ($i = 0; $i < 100; $i++) {
        $this->withHeader('X-Api-Key', $plaintext)
            ->getJson('/api/v1/posts');
    }

    // 101st should be rate limited
    $this->withHeader('X-Api-Key', $plaintext)
        ->getJson('/api/v1/posts')
        ->assertStatus(429);
});

it('respects per-key rate limit', function () {
    $key = ApiKey::factory()->withTenantUser()->create([
        'abilities' => [ApiKeyAbility::Read],
        'token' => null,
        'rate_limit_per_minute' => 5,
    ]);

    $plaintext = 'test-api-key-'.Str::random(20);
    $key->setPlaintext($plaintext);

    // 5 requests should pass
    for ($i = 0; $i < 5; $i++) {
        $this->withHeader('X-Api-Key', $plaintext)
            ->getJson('/api/v1/posts');
    }

    // 6th should be rate limited
    $this->withHeader('X-Api-Key', $plaintext)
        ->getJson('/api/v1/posts')
        ->assertStatus(429);
});

it('rate limits are per-key not global', function () {
    $key1 = ApiKey::factory()->withTenantUser()->create([
        'abilities' => [ApiKeyAbility::Read],
        'token' => null,
        'rate_limit_per_minute' => 2,
    ]);

    $key2 = ApiKey::factory()->withTenantUser()->create([
        'abilities' => [ApiKeyAbility::Read],
        'token' => null,
        'rate_limit_per_minute' => 2,
    ]);

    $plaintext1 = 'test-api-key-1-'.Str::random(20);
    $plaintext2 = 'test-api-key-2-'.Str::random(20);
    $key1->setPlaintext($plaintext1);
    $key2->setPlaintext($plaintext2);

    // Exhaust key1's limit
    for ($i = 0; $i < 2; $i++) {
        $this->withHeader('X-Api-Key', $plaintext1)
            ->getJson('/api/v1/posts');
    }

    // key1 should be limited
    $this->withHeader('X-Api-Key', $plaintext1)
        ->getJson('/api/v1/posts')
        ->assertStatus(429);

    // key2 should still work
    $this->withHeader('X-Api-Key', $plaintext2)
        ->getJson('/api/v1/posts')
        ->assertOk();
});

it('draft_read ability allows fetching drafts', function () {
    $key = ApiKey::factory()->create([
        'abilities' => [ApiKeyAbility::DraftRead],
        'token' => null,
    ]);

    $plaintext = 'test-api-key-'.Str::random(20);
    $key->setPlaintext($plaintext);

    $this->withHeader('X-Api-Key', $plaintext)
        ->getJson('/api/v1/drafts')
        ->assertOk();
});
