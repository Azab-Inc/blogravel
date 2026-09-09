<?php

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;

test('super admin can view the users page and sees all tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin, 'tenant_id' => $tenantA->id]);
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

    $response = $this->actingAs($superAdmin)
        ->get('/admin/users');

    $response->assertOk()
        ->assertSee($userA->email)
        ->assertSee($userB->email);
});

test('admin can view the users page and sees only their tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $admin = User::factory()->create(['role' => Role::Admin, 'tenant_id' => $tenantA->id]);
    $sameTenant = User::factory()->create(['tenant_id' => $tenantA->id]);
    $otherTenant = User::factory()->create(['tenant_id' => $tenantB->id]);

    $response = $this->actingAs($admin)
        ->get('/admin/users');

    $response->assertOk()
        ->assertSee($sameTenant->email)
        ->assertDontSee($otherTenant->email);
});

test('editor can view the users page and sees only authors in their tenant', function () {
    $tenantA = Tenant::factory()->create();
    $editor = User::factory()->create(['role' => Role::Editor, 'tenant_id' => $tenantA->id]);
    $author = User::factory()->create(['role' => Role::Author, 'tenant_id' => $tenantA->id]);
    $otherAdmin = User::factory()->create(['role' => Role::Admin, 'tenant_id' => $tenantA->id]);

    $response = $this->actingAs($editor)
        ->get('/admin/users');

    $response->assertOk()
        ->assertSee($author->email)
        ->assertDontSee($otherAdmin->email);
});

test('author is denied access to the users page', function () {
    $tenant = Tenant::factory()->create();
    $author = User::factory()->create(['role' => Role::Author, 'tenant_id' => $tenant->id]);

    $this->actingAs($author)
        ->get('/admin/users')
        ->assertForbidden();
});
