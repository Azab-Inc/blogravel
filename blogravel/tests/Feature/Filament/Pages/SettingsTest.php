<?php

use App\Enums\Role;
use App\Filament\Pages\Settings;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
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
        ->assertSee('Permissions')
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
        ->call('save')
        ->assertHasNoErrors();

    $setting = Setting::where('tenant_id', $tenant->id)
        ->where('key', 'authors_can_view_others_posts')
        ->first();

    expect($setting)->not->toBeNull();
    expect($setting->value)->toBe('true');
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
        ->call('save')
        ->assertHasNoErrors();

    $setting = Setting::where('tenant_id', $tenant->id)
        ->where('key', 'authors_can_view_others_posts')
        ->first();

    expect($setting->value)->toBe('false');
});
