<?php

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;

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

it('uses only the authenticated superadmin tenant for documentation examples', function () {
    $tenant = Tenant::factory()->create(['slug' => 'northstar', 'domain' => 'northstar.blogravel.com']);
    $otherTenant = Tenant::factory()->create(['slug' => 'southstar', 'domain' => 'southstar.blogravel.com']);
    $superadmin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::SuperAdmin,
    ]);

    $this->actingAs($superadmin)
        ->get('/admin/api-documentation')
        ->assertSee('northstar.blogravel.com')
        ->assertDontSee($otherTenant->domain)
        ->assertDontSee('selectedTenantSlug');
});

it('does not expose a tenant selector to tenant users', function () {
    $tenant = Tenant::factory()->create(['slug' => 'northstar', 'domain' => 'northstar.blogravel.com']);
    $otherTenant = Tenant::factory()->create(['slug' => 'southstar', 'domain' => 'southstar.blogravel.com']);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);

    $this->actingAs($user);

    $this->get('/admin/api-documentation')
        ->assertSee('northstar.blogravel.com')
        ->assertDontSee($otherTenant->domain)
        ->assertDontSee('selectedTenantSlug');
});
