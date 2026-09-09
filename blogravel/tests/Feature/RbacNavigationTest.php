<?php

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->otherTenant = Tenant::factory()->create();
});

it('super admin can access all resource pages', function () {
    $user = User::factory()->create(['role' => Role::SuperAdmin, 'tenant_id' => $this->tenant->id]);

    $pages = ['posts', 'pages', 'categories', 'tags', 'media', 'users'];

    foreach ($pages as $resource) {
        $this->actingAs($user)
            ->get(route("filament.admin.resources.{$resource}.index"))
            ->assertOk();
    }
});

it('admin can access content resources and users in own tenant', function () {
    $user = User::factory()->create(['role' => Role::Admin, 'tenant_id' => $this->tenant->id]);

    $contentPages = ['posts', 'pages', 'categories', 'tags', 'media'];
    foreach ($contentPages as $resource) {
        $this->actingAs($user)
            ->get(route("filament.admin.resources.{$resource}.index"))
            ->assertOk();
    }

    // Users page also accessible for admin
    $this->actingAs($user)
        ->get(route('filament.admin.resources.users.index'))
        ->assertOk();
});

it('editor can access content resources and users page (sees only authors)', function () {
    $user = User::factory()->create(['role' => Role::Editor, 'tenant_id' => $this->tenant->id]);

    $contentPages = ['posts', 'pages', 'categories', 'tags', 'media'];
    foreach ($contentPages as $resource) {
        $this->actingAs($user)
            ->get(route("filament.admin.resources.{$resource}.index"))
            ->assertOk();
    }

    // Editor can see Users page (filtered to authors only by UserResource::getEloquentQuery)
    $this->actingAs($user)
        ->get(route('filament.admin.resources.users.index'))
        ->assertOk();
});

it('author can view posts page but is denied from other management resources', function () {
    $user = User::factory()->create(['role' => Role::Author, 'tenant_id' => $this->tenant->id]);

    // Posts page accessible for authors (viewAny returns true via PostPolicy)
    $this->actingAs($user)
        ->get(route('filament.admin.resources.posts.index'))
        ->assertOk();

    // Users, categories, tags, media pages denied
    $deniedPages = ['users', 'categories', 'tags', 'media', 'pages'];
    foreach ($deniedPages as $resource) {
        $this->actingAs($user)
            ->get(route("filament.admin.resources.{$resource}.index"))
            ->assertForbidden();
    }
});
