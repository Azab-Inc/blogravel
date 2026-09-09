<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\PostPolicy;
use App\Policies\TagPolicy;
use App\Policies\UserPolicy;

/*
 * Posts
 */

it('allows author to view own posts', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $post = Post::factory()->create(['author_id' => $user->id, 'tenant_id' => $tenant->id]);

    expect((new PostPolicy)->view($user, $post))->toBeTrue();
});

it('allows author to manage own posts but not others', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $ownPost = Post::factory()->create(['author_id' => $user->id, 'tenant_id' => $tenant->id]);
    $otherPost = Post::factory()->create(['tenant_id' => $tenant->id]);

    expect((new PostPolicy)->create($user))->toBeFalse()
        ->and((new PostPolicy)->update($user, $ownPost))->toBeTrue()
        ->and((new PostPolicy)->delete($user, $ownPost))->toBeTrue()
        ->and((new PostPolicy)->update($user, $otherPost))->toBeFalse()
        ->and((new PostPolicy)->delete($user, $otherPost))->toBeFalse();
});

it('allows admin to manage all posts within tenant', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id]);
    $post = Post::factory()->create(['author_id' => $admin->id, 'tenant_id' => $tenant->id]);

    expect((new PostPolicy)->create($admin))->toBeTrue()
        ->and((new PostPolicy)->update($admin, $post))->toBeTrue()
        ->and((new PostPolicy)->delete($admin, $post))->toBeTrue()
        ->and((new PostPolicy)->forceDelete($admin, $post))->toBeFalse();
});

it('allows super_admin to do anything with posts', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'super_admin', 'tenant_id' => $tenant->id]);
    $post = Post::factory()->create(['tenant_id' => $tenant->id]);

    expect((new PostPolicy)->create($user))->toBeTrue()
        ->and((new PostPolicy)->update($user, $post))->toBeTrue()
        ->and((new PostPolicy)->delete($user, $post))->toBeTrue()
        ->and((new PostPolicy)->forceDelete($user, $post))->toBeTrue();
});

it('allows admin and editor to view any post, author sees own + published', function () {
    $tenant = Tenant::factory()->create();
    $author = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $admin = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id]);
    $editor = User::factory()->create(['role' => 'editor', 'tenant_id' => $tenant->id]);
    $superAdmin = User::factory()->create(['role' => 'super_admin', 'tenant_id' => $tenant->id]);
    $post = Post::factory()->create(['author_id' => $author->id, 'tenant_id' => $tenant->id]);

    expect((new PostPolicy)->viewAny($author))->toBeTrue()
        ->and((new PostPolicy)->viewAny($admin))->toBeTrue()
        ->and((new PostPolicy)->viewAny($editor))->toBeTrue()
        ->and((new PostPolicy)->viewAny($superAdmin))->toBeTrue()
        ->and((new PostPolicy)->view($author, $post))->toBeTrue()
        ->and((new PostPolicy)->view($admin, $post))->toBeTrue()
        ->and((new PostPolicy)->view($editor, $post))->toBeTrue()
        ->and((new PostPolicy)->view($superAdmin, $post))->toBeTrue();
});

/*
 * Categories
 */

it('allows admin to manage categories', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);

    expect((new CategoryPolicy)->create($admin))->toBeTrue()
        ->and((new CategoryPolicy)->update($admin, $category))->toBeTrue()
        ->and((new CategoryPolicy)->delete($admin, $category))->toBeTrue()
        ->and((new CategoryPolicy)->forceDelete($admin, $category))->toBeFalse();
});

it('allows super_admin to force delete categories', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'super_admin', 'tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);

    expect((new CategoryPolicy)->forceDelete($user, $category))->toBeTrue();
});

it('denies author from managing categories', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);

    expect((new CategoryPolicy)->create($user))->toBeFalse()
        ->and((new CategoryPolicy)->update($user, $category))->toBeFalse()
        ->and((new CategoryPolicy)->delete($user, $category))->toBeFalse();
});

/*
 * Tags
 */

it('allows admin to manage tags', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id]);
    $tag = Tag::factory()->create(['tenant_id' => $tenant->id]);

    expect((new TagPolicy)->create($admin))->toBeTrue()
        ->and((new TagPolicy)->update($admin, $tag))->toBeTrue()
        ->and((new TagPolicy)->delete($admin, $tag))->toBeTrue()
        ->and((new TagPolicy)->forceDelete($admin, $tag))->toBeFalse();
});

it('allows super_admin to force delete tags', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'super_admin', 'tenant_id' => $tenant->id]);
    $tag = Tag::factory()->create(['tenant_id' => $tenant->id]);

    expect((new TagPolicy)->forceDelete($user, $tag))->toBeTrue();
});

it('denies author from managing tags', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $tag = Tag::factory()->create(['tenant_id' => $tenant->id]);

    expect((new TagPolicy)->create($user))->toBeFalse()
        ->and((new TagPolicy)->update($user, $tag))->toBeFalse()
        ->and((new TagPolicy)->delete($user, $tag))->toBeFalse();
});

/*
 * Users
 */

it('allows super_admin to manage all users', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'super_admin', 'tenant_id' => $tenant->id]);
    $otherUser = User::factory()->create(['tenant_id' => $tenant->id]);
    $crossTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

    expect((new UserPolicy)->viewAny($user))->toBeTrue()
        ->and((new UserPolicy)->view($user, $otherUser))->toBeTrue()
        ->and((new UserPolicy)->view($user, $crossTenantUser))->toBeTrue()
        ->and((new UserPolicy)->create($user))->toBeTrue()
        ->and((new UserPolicy)->update($user, $otherUser))->toBeTrue()
        ->and((new UserPolicy)->update($user, $crossTenantUser))->toBeTrue()
        ->and((new UserPolicy)->delete($user, $otherUser))->toBeTrue()
        ->and((new UserPolicy)->forceDelete($user, $otherUser))->toBeTrue();
});

it('allows admin to manage users within their tenant only', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id]);
    $sameTenantUser = User::factory()->create(['tenant_id' => $tenant->id]);
    $crossTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

    expect((new UserPolicy)->viewAny($admin))->toBeTrue()
        ->and((new UserPolicy)->view($admin, $sameTenantUser))->toBeTrue()
        ->and((new UserPolicy)->view($admin, $crossTenantUser))->toBeFalse()
        ->and((new UserPolicy)->create($admin))->toBeTrue()
        ->and((new UserPolicy)->update($admin, $sameTenantUser))->toBeTrue()
        ->and((new UserPolicy)->update($admin, $crossTenantUser))->toBeFalse()
        ->and((new UserPolicy)->delete($admin, $sameTenantUser))->toBeTrue()
        ->and((new UserPolicy)->delete($admin, $crossTenantUser))->toBeFalse();
});

it('denies admin from deleting themselves or super admins', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id]);
    $superAdmin = User::factory()->create(['role' => 'super_admin', 'tenant_id' => $tenant->id]);

    expect((new UserPolicy)->delete($admin, $admin))->toBeFalse()
        ->and((new UserPolicy)->delete($admin, $superAdmin))->toBeFalse();
});

it('allows editor to view authors but not manage users', function () {
    $tenant = Tenant::factory()->create();
    $editor = User::factory()->create(['role' => 'editor', 'tenant_id' => $tenant->id]);
    $author = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $otherEditor = User::factory()->create(['role' => 'editor', 'tenant_id' => $tenant->id]);

    expect((new UserPolicy)->viewAny($editor))->toBeTrue()
        ->and((new UserPolicy)->view($editor, $author))->toBeTrue()
        ->and((new UserPolicy)->view($editor, $otherEditor))->toBeFalse()
        ->and((new UserPolicy)->create($editor))->toBeFalse()
        ->and((new UserPolicy)->update($editor, $author))->toBeFalse()
        ->and((new UserPolicy)->delete($editor, $author))->toBeFalse()
        ->and((new UserPolicy)->forceDelete($editor, $author))->toBeFalse();
});

it('denies author from managing users', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $otherUser = User::factory()->create(['tenant_id' => $tenant->id]);

    expect((new UserPolicy)->viewAny($user))->toBeFalse()
        ->and((new UserPolicy)->view($user, $otherUser))->toBeFalse()
        ->and((new UserPolicy)->create($user))->toBeFalse()
        ->and((new UserPolicy)->update($user, $otherUser))->toBeFalse()
        ->and((new UserPolicy)->delete($user, $otherUser))->toBeFalse()
        ->and((new UserPolicy)->forceDelete($user, $otherUser))->toBeFalse();
});
