<?php

use App\Enums\Role;
use App\Filament\Pages\ApiDocumentation;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

it('renders API documentation for the authenticated tenant', function () {
    $tenant = Tenant::factory()->create(['slug' => 'northstar', 'domain' => 'northstar.blogravel.com']);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);

    $this->actingAs($user)
        ->get('/admin/api-documentation')
        ->assertOk()
        ->assertSee('northstar.blogravel.com');
});

it('allows superadmins to select a tenant for documentation examples', function () {
    $tenant = Tenant::factory()->create(['slug' => 'northstar', 'domain' => 'northstar.blogravel.com']);
    $superadmin = User::factory()->create([
        'tenant_id' => null,
        'role' => Role::SuperAdmin,
    ]);

    $this->actingAs($superadmin);

    Livewire::test(ApiDocumentation::class)
        ->set('selectedTenantSlug', $tenant->slug)
        ->assertSee('northstar.blogravel.com');
});

it('does not allow a tenant user to select another tenant', function () {
    $tenant = Tenant::factory()->create(['slug' => 'northstar']);
    $otherTenant = Tenant::factory()->create(['slug' => 'southstar']);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);

    $this->actingAs($user);

    Livewire::test(ApiDocumentation::class)
        ->set('selectedTenantSlug', $otherTenant->slug)
        ->assertDontSee('southstar.blogravel.com')
        ->assertSee('Choose a tenant');
});
