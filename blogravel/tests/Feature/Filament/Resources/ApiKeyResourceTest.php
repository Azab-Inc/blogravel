<?php

use App\Enums\ApiKeyAbility;
use App\Enums\Role;
use App\Filament\Resources\ApiKeyResource\Pages\ListApiKeys;
use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

it('creates API keys through a modal action', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);
    $this->actingAs($user);

    Livewire::test(ListApiKeys::class)
        ->assertActionExists('create')
        ->callAction('create', [
            'name' => 'Public API',
            'abilities' => [ApiKeyAbility::Read->value],
            'expires_at' => null,
        ]);

    $apiKey = ApiKey::query()->where('tenant_id', $tenant->id)->first();

    expect($apiKey)->not->toBeNull()
        ->and($apiKey->name)->toBe('Public API')
        ->and($apiKey->token)->not->toBeNull()
        ->and($apiKey->key_hash)->toBe(hash('sha256', $apiKey->token));
});
