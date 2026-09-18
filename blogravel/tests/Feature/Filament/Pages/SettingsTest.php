<?php

use App\Enums\Role;
use App\Filament\Pages\Settings;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('settings page renders for admin', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);
    $this->actingAs($user);

    $response = $this->get('/admin/settings');
    $response->assertStatus(200);
});

it('renders the Filament action modal host', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);
    $this->actingAs($user);

    $this->get('/admin/settings')
        ->assertSee('class="fi-page"', false)
        ->assertSee('wire:partial="action-modals"', false);
});

it('settings page renders for superadmin', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::SuperAdmin,
    ]);
    $this->actingAs($user);

    $response = $this->get('/admin/settings');
    $response->assertStatus(200);
});

it('settings page is hidden from editor', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Editor,
    ]);
    $this->actingAs($user);

    $response = $this->get('/admin/settings');
    $response->assertStatus(403);
});

it('settings page is hidden from author', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Author,
    ]);
    $this->actingAs($user);

    $response = $this->get('/admin/settings');
    $response->assertStatus(403);
});

it('shows permissions section with toggle', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);
    $this->actingAs($user);

    $response = $this->get('/admin/settings');
    $response->assertStatus(200)
        ->assertSee('Site')
        ->assertSee('authors_can_view_others_posts');
});

it('saves the authors_can_view_others_posts toggle', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);
    $this->actingAs($user);

    Livewire::test(Settings::class)
        ->set('data.authors_can_view_others_posts', true)
        ->call('saveSite')
        ->assertHasNoErrors();

    $setting = Setting::where('tenant_id', $tenant->id)
        ->where('key', 'authors_can_view_others_posts')
        ->first();

    expect($setting)->not->toBeNull();
    expect($setting->value)->toBe('true');
});

it('saves a password change from Settings', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);
    $this->actingAs($user);

    Livewire::test(Settings::class)
        ->set('data.password', 'new-password-123')
        ->set('data.password_confirmation', 'new-password-123')
        ->set('data.current_password', 'password')
        ->call('saveAccount')
        ->assertHasNoErrors();

    expect(Hash::check('new-password-123', $user->refresh()->password))->toBeTrue();
});

it('saves site settings without requiring account fields', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);
    $this->actingAs($user);

    Livewire::test(Settings::class)
        ->set('data.authors_can_view_others_posts', true)
        ->set('data.theme_enabled', false)
        ->call('saveSite')
        ->assertHasNoErrors();

    expect(Setting::where('tenant_id', $tenant->id)->pluck('value', 'key')->all())
        ->toMatchArray([
            'authors_can_view_others_posts' => 'true',
            'theme_enabled' => 'false',
        ]);
});

it('defaults authors_can_view_others_posts to false', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);
    $this->actingAs($user);

    Livewire::test(Settings::class)
        ->assertSet('data.authors_can_view_others_posts', false);
});

it('scopes settings to the current tenant', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);
    $this->actingAs($user);

    Setting::factory()->create([
        'tenant_id' => $otherTenant->id,
        'key' => 'authors_can_view_others_posts',
        'value' => 'true',
    ]);

    Livewire::test(Settings::class)
        ->assertSet('data.authors_can_view_others_posts', false);
});

it('loads existing setting value for the tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);
    $this->actingAs($user);

    Setting::factory()->create([
        'tenant_id' => $tenant->id,
        'key' => 'authors_can_view_others_posts',
        'value' => 'true',
    ]);

    Livewire::test(Settings::class)
        ->assertSet('data.authors_can_view_others_posts', true);
});

it('toggling off saves false', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);
    $this->actingAs($user);

    Setting::factory()->create([
        'tenant_id' => $tenant->id,
        'key' => 'authors_can_view_others_posts',
        'value' => 'true',
    ]);

    Livewire::test(Settings::class)
        ->set('data.authors_can_view_others_posts', false)
        ->call('saveSite')
        ->assertHasNoErrors();

    $setting = Setting::where('tenant_id', $tenant->id)
        ->where('key', 'authors_can_view_others_posts')
        ->first();

    expect($setting->value)->toBe('false');
});

it('rejects mismatched tenant confirmation through the settings action', function () {
    $tenant = Tenant::factory()->create(['name' => 'Acme']);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);
    $this->actingAs($user);

    Livewire::test(Settings::class)
        ->callAction(
            TestAction::make('closeAccount')->schemaComponent(true),
            ['tenant_confirmation' => 'Wrong tenant'],
        )
        ->assertHasFormErrors(['tenant_confirmation']);

    expect(User::find($user->id))->not->toBeNull()
        ->and(Tenant::find($tenant->id))->not->toBeNull();
});

it('closes the tenant through the settings action with matching confirmation', function () {
    $tenant = Tenant::factory()->create(['name' => 'Acme']);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);
    $this->actingAs($user);

    Livewire::test(Settings::class)
        ->callAction(
            TestAction::make('closeAccount')->schemaComponent(true),
            ['tenant_confirmation' => $tenant->name],
        );

    $this->assertSoftDeleted('users', ['id' => $user->id]);
    $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
});

it('rejects direct settings closure without tenant confirmation', function () {
    $tenant = Tenant::factory()->create(['name' => 'Acme']);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);
    $this->actingAs($user);

    Livewire::test(Settings::class)
        ->call('closeAccount')
        ->assertHasErrors(['tenant_confirmation']);

    expect(User::find($user->id))->not->toBeNull()
        ->and(Tenant::find($tenant->id))->not->toBeNull();
});
