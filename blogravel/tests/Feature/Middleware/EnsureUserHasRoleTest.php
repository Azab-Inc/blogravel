<?php

use App\Enums\Role;
use App\Models\User;

test('blocks non-admin from admin-only route', function () {
    $user = User::factory()->create(['role' => Role::Author]);
    $this->actingAs($user)->get('/admin/secret')->assertForbidden();
});

test('allows super_admin to admin-only route', function () {
    $user = User::factory()->create(['role' => Role::SuperAdmin]);
    $this->actingAs($user)->get('/admin/secret')->assertOk();
});

test('blocks editor from super_admin-only route', function () {
    $user = User::factory()->create(['role' => Role::Editor]);
    $this->actingAs($user)->get('/admin/secret')->assertForbidden();
});
