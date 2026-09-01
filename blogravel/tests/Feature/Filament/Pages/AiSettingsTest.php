<?php

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
