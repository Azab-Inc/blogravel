<?php

use App\Enums\AiProviderType;
use App\Filament\Pages\AiSettings;
use App\Models\AiProvider;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;

it('ai settings page renders for admin', function () {
    $user = User::factory()->create(['has_email_authentication' => true]);
    $this->actingAs($user);

    $response = $this->get('/admin/ai-settings');
    $response->assertStatus(200);
});

it('shows configured providers and saved defaults', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'has_email_authentication' => true,
    ]);
    $this->actingAs($user);

    AiProvider::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Primary OpenAI',
        'enabled' => true,
    ]);
    AiProvider::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Disabled Ollama',
        'enabled' => false,
    ]);

    Setting::factory()->create([
        'tenant_id' => $tenant->id,
        'key' => 'ai_default_provider',
        'value' => (string) 1,
    ]);

    $response = $this->get('/admin/ai-settings');

    $response->assertStatus(200)
        ->assertSee('Configured Providers')
        ->assertSee('Default Provider')
        ->assertSee('Primary OpenAI')
        ->assertSee('Media generation is coming soon');
});

it('saves default provider and output types', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'has_email_authentication' => true,
    ]);
    $this->actingAs($user);

    $provider = AiProvider::factory()->create([
        'tenant_id' => $tenant->id,
        'enabled' => true,
    ]);

    Livewire::test(AiSettings::class)
        ->set('defaultProvider', (string) $provider->id)
        ->set('defaultOutputTypes', ['title', 'content'])
        ->call('saveDefaults')
        ->assertHasNoErrors();

    expect(Setting::where('tenant_id', $tenant->id)->where('key', 'ai_default_provider')->value('value'))
        ->toBe((string) $provider->id);
    expect(
        json_decode(Setting::where('tenant_id', $tenant->id)->where('key', 'ai_default_output_types')->value('value'), true)
    )->toBe(['title', 'content']);
});

it('scopes providers and settings to the current tenant', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'has_email_authentication' => true,
    ]);
    $this->actingAs($user);

    AiProvider::factory()->create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Other Tenant Provider',
    ]);
    Setting::factory()->create([
        'tenant_id' => $otherTenant->id,
        'key' => 'ai_default_provider',
        'value' => '999',
    ]);

    $response = $this->get('/admin/ai-settings');

    $response->assertStatus(200)
        ->assertDontSee('Other Tenant Provider')
        ->assertDontSee('999');
});

it('creates a provider via the repeater', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'has_email_authentication' => true,
    ]);
    $this->actingAs($user);

    Livewire::test(AiSettings::class)
        ->set('providers', [[
            'id' => null,
            'name' => 'New OpenAI',
            'type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-new-key',
            'model' => 'gpt-4o',
            'temperature' => '0.7',
            'max_tokens' => '2048',
            'custom_template' => null,
            'enabled' => true,
        ]])
        ->call('saveDefaults')
        ->assertHasNoErrors();

    $provider = AiProvider::query()->where('tenant_id', $tenant->id)->first();

    expect($provider)->not->toBeNull()
        ->and($provider->name)->toBe('New OpenAI')
        ->and($provider->type)->toBe(AiProviderType::OpenAi)
        ->and($provider->model)->toBe('gpt-4o')
        ->and($provider->api_key)->toBe('sk-new-key')
        ->and($provider->enabled)->toBeTrue();
});

it('edits an existing provider via the repeater', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'has_email_authentication' => true,
    ]);
    $this->actingAs($user);

    $provider = AiProvider::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Old Name',
        'model' => 'gpt-3.5-turbo',
    ]);

    Livewire::test(AiSettings::class)
        ->set('providers.0.name', 'Renamed Provider')
        ->set('providers.0.model', 'gpt-4o')
        ->call('saveDefaults')
        ->assertHasNoErrors();

    $provider->refresh();

    expect($provider->name)->toBe('Renamed Provider')
        ->and($provider->model)->toBe('gpt-4o');
});

it('deletes a provider when its repeater item is removed', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'has_email_authentication' => true,
    ]);
    $this->actingAs($user);

    $kept = AiProvider::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Kept Provider',
    ]);
    $removed = AiProvider::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Removed Provider',
    ]);

    Livewire::test(AiSettings::class)
        ->set('providers', [[
            'id' => $kept->id,
            'name' => $kept->name,
            'type' => $kept->type->value,
            'base_url' => $kept->base_url,
            'api_key' => '',
            'model' => $kept->model,
            'temperature' => '0.70',
            'max_tokens' => '2048',
            'custom_template' => null,
            'enabled' => true,
        ]])
        ->call('saveDefaults')
        ->assertHasNoErrors();

    expect(AiProvider::query()->where('tenant_id', $tenant->id)->pluck('id')->all())->toBe([$kept->id])
        ->and(AiProvider::query()->where('id', $removed->id)->exists())->toBeFalse();
});

it('deletes every provider when all repeater items are removed', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'has_email_authentication' => true,
    ]);
    $this->actingAs($user);

    AiProvider::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
    ]);

    Livewire::test(AiSettings::class)
        ->set('providers', [])
        ->call('saveDefaults')
        ->assertHasNoErrors();

    expect(AiProvider::query()->where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('preserves the stored api key when the repeater api key is blank', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'has_email_authentication' => true,
    ]);
    $this->actingAs($user);

    $provider = AiProvider::factory()->create([
        'tenant_id' => $tenant->id,
        'api_key' => 'sk-original-secret',
    ]);

    Livewire::test(AiSettings::class)
        ->set('providers.0.name', 'Still Same Key')
        ->set('providers.0.api_key', '')
        ->call('saveDefaults')
        ->assertHasNoErrors();

    $provider->refresh();

    expect($provider->api_key)->toBe('sk-original-secret')
        ->and($provider->name)->toBe('Still Same Key');
});

it('never exposes existing provider api keys in the page html', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'has_email_authentication' => true,
    ]);
    $this->actingAs($user);

    AiProvider::factory()->create([
        'tenant_id' => $tenant->id,
        'api_key' => 'sk-super-secret-value',
    ]);

    $response = $this->get('/admin/ai-settings');

    $response->assertStatus(200)
        ->assertDontSee('sk-super-secret-value');
});

it('requires model for each repeater item', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'has_email_authentication' => true,
    ]);
    $this->actingAs($user);

    Livewire::test(AiSettings::class)
        ->set('providers', [[
            'id' => null,
            'name' => 'No Model Provider',
            'type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-key',
            'model' => '',
            'temperature' => '0.7',
            'max_tokens' => '2048',
            'custom_template' => null,
            'enabled' => true,
        ]])
        ->call('saveDefaults')
        ->assertHasErrors(['providers.0.model']);
});

it('requires custom template when provider type is custom', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'has_email_authentication' => true,
    ]);
    $this->actingAs($user);

    Livewire::test(AiSettings::class)
        ->set('providers', [[
            'id' => null,
            'name' => 'Broken Custom',
            'type' => 'custom',
            'base_url' => 'https://api.example.com',
            'api_key' => 'sk-key',
            'model' => 'custom-model',
            'temperature' => '0.7',
            'max_tokens' => '2048',
            'custom_template' => '',
            'enabled' => true,
        ]])
        ->call('saveDefaults')
        ->assertHasErrors(['providers.0.custom_template']);
});
