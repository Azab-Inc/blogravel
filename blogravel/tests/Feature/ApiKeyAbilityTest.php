<?php

use App\Enums\ApiKeyAbility;
use App\Models\ApiKey;

it('rejects write request with read-only api key', function () {
    $key = ApiKey::factory()->create();
    $key->abilities = [ApiKeyAbility::Read];
    $key->save();

    $this->withHeader('X-Api-Key', $key->token)
        ->postJson('/api/v1/posts', ['title' => 'x'])
        ->assertForbidden();
});

it('rejects read request with write-only api key', function () {
    $key = ApiKey::factory()->create();
    $key->abilities = [ApiKeyAbility::Write];
    $key->save();

    $this->withHeader('X-Api-Key', $key->token)
        ->getJson('/api/v1/posts')
        ->assertForbidden();
});

it('rejects draft read with write-only api key', function () {
    $key = ApiKey::factory()->create();
    $key->abilities = [ApiKeyAbility::Write];
    $key->save();

    $this->withHeader('X-Api-Key', $key->token)
        ->getJson('/api/v1/drafts')
        ->assertForbidden();
});

it('allows read request with read ability', function () {
    $key = ApiKey::factory()->create();
    $key->abilities = [ApiKeyAbility::Read];
    $key->save();

    $this->withHeader('X-Api-Key', $key->token)
        ->getJson('/api/v1/posts')
        ->assertOk();
});

it('allows write request with write ability', function () {
    $key = ApiKey::factory()->create();
    $key->abilities = [ApiKeyAbility::Write];
    $key->save();

    $this->withHeader('X-Api-Key', $key->token)
        ->postJson('/api/v1/posts', ['title' => 'x'])
        ->assertSuccessful();
});

it('allows draft read with draft_read ability', function () {
    $key = ApiKey::factory()->create();
    $key->abilities = [ApiKeyAbility::DraftRead];
    $key->save();

    $this->withHeader('X-Api-Key', $key->token)
        ->getJson('/api/v1/drafts')
        ->assertOk();
});

it('allows request with multiple abilities', function () {
    $key = ApiKey::factory()->create();
    $key->abilities = [ApiKeyAbility::Read, ApiKeyAbility::Write];
    $key->save();

    $this->withHeader('X-Api-Key', $key->token)
        ->getJson('/api/v1/posts')
        ->assertOk();

    $this->withHeader('X-Api-Key', $key->token)
        ->postJson('/api/v1/posts', ['title' => 'x'])
        ->assertSuccessful();
});

it('rejects request with no api key header', function () {
    $this->getJson('/api/v1/posts')
        ->assertUnauthorized();
});

it('rejects request with invalid api key', function () {
    $this->withHeader('X-Api-Key', 'nonexistent-key')
        ->getJson('/api/v1/posts')
        ->assertUnauthorized();
});

it('rejects request with expired api key', function () {
    $key = ApiKey::factory()->create();
    $key->abilities = [ApiKeyAbility::Read];
    $key->expires_at = now()->subHour();
    $key->save();

    $this->withHeader('X-Api-Key', $key->token)
        ->getJson('/api/v1/posts')
        ->assertUnauthorized();
});
