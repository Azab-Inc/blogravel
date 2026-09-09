<?php

use App\Enums\Role;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;

test('users in tenant a cannot see tenant b\'s posts', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->forTenant($tenantA)->create(['role' => Role::Author]);

    Post::factory()->create(['tenant_id' => $tenantA->id, 'author_id' => $userA->id]);
    Post::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($userA);

    $posts = Post::all();

    expect($posts)->toHaveCount(1);
    expect($posts->first()->tenant_id)->toBe($tenantA->id);
});

test('users in tenant a cannot see tenant b\'s users', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->forTenant($tenantA)->create(['role' => Role::Author]);
    User::factory()->count(3)->forTenant($tenantB)->create();

    $this->actingAs($userA);

    $users = User::all();

    expect($users)->toHaveCount(1);
    expect($users->first()->id)->toBe($userA->id);
});

test('superadmin sees all tenants\' posts', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);

    $userA = User::factory()->forTenant($tenantA)->create(['role' => Role::Author]);
    Post::factory()->create(['tenant_id' => $tenantA->id, 'author_id' => $userA->id]);
    Post::factory()->count(2)->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($superAdmin);

    $posts = Post::all();

    expect($posts)->toHaveCount(3);
});

test('superadmin sees all tenants\' users', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    User::factory()->count(2)->forTenant($tenantA)->create();
    User::factory()->count(2)->forTenant($tenantB)->create();

    $this->actingAs($superAdmin);

    $users = User::all();

    expect($users)->toHaveCount(5);
});

test('unauthenticated queries are not scoped', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->forTenant($tenantA)->create(['role' => Role::Author]);
    User::factory()->count(3)->forTenant($tenantB)->create();

    $users = User::all();

    expect($users)->toHaveCount(4);
});
