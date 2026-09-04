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

it('allows author to view own posts', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $post = Post::factory()->create(['author_id' => $user->id, 'tenant_id' => $tenant->id]);

    expect((new PostPolicy)->view($user, $post))->toBeTrue();
});

it('allows author to update own posts', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $post = Post::factory()->create(['author_id' => $user->id, 'tenant_id' => $tenant->id]);

    expect((new PostPolicy)->update($user, $post))->toBeTrue();
});

it('allows author to delete own posts', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $post = Post::factory()->create(['author_id' => $user->id, 'tenant_id' => $tenant->id]);

    expect((new PostPolicy)->delete($user, $post))->toBeTrue();
});

it('denies author from updating others posts', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $otherUser = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $post = Post::factory()->create(['author_id' => $otherUser->id, 'tenant_id' => $tenant->id]);

    expect((new PostPolicy)->update($user, $post))->toBeFalse();
});

it('denies author from deleting others posts', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $otherUser = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $post = Post::factory()->create(['author_id' => $otherUser->id, 'tenant_id' => $tenant->id]);

    expect((new PostPolicy)->delete($user, $post))->toBeFalse();
});

it('allows editor to update any post', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'editor', 'tenant_id' => $tenant->id]);
    $otherUser = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $post = Post::factory()->create(['author_id' => $otherUser->id, 'tenant_id' => $tenant->id]);

    expect((new PostPolicy)->update($user, $post))->toBeTrue();
});

it('allows editor to delete own posts', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'editor', 'tenant_id' => $tenant->id]);
    $post = Post::factory()->create(['author_id' => $user->id, 'tenant_id' => $tenant->id]);

    expect((new PostPolicy)->delete($user, $post))->toBeTrue();
});

it('denies editor from deleting others posts', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'editor', 'tenant_id' => $tenant->id]);
    $otherUser = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $post = Post::factory()->create(['author_id' => $otherUser->id, 'tenant_id' => $tenant->id]);

    expect((new PostPolicy)->delete($user, $post))->toBeFalse();
});

it('allows super_admin to do anything with posts', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'super_admin', 'tenant_id' => $tenant->id]);
    $otherUser = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $post = Post::factory()->create(['author_id' => $otherUser->id, 'tenant_id' => $tenant->id]);

    expect((new PostPolicy)->update($user, $post))->toBeTrue()
        ->and((new PostPolicy)->delete($user, $post))->toBeTrue()
        ->and((new PostPolicy)->forceDelete($user, $post))->toBeTrue();
});

it('allows all users to view any post', function () {
    $tenant = Tenant::factory()->create();
    $author = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $editor = User::factory()->create(['role' => 'editor', 'tenant_id' => $tenant->id]);
    $superAdmin = User::factory()->create(['role' => 'super_admin', 'tenant_id' => $tenant->id]);
    $post = Post::factory()->create(['author_id' => $author->id, 'tenant_id' => $tenant->id]);

    expect((new PostPolicy)->viewAny($author))->toBeTrue()
        ->and((new PostPolicy)->viewAny($editor))->toBeTrue()
        ->and((new PostPolicy)->viewAny($superAdmin))->toBeTrue()
        ->and((new PostPolicy)->view($author, $post))->toBeTrue()
        ->and((new PostPolicy)->view($editor, $post))->toBeTrue()
        ->and((new PostPolicy)->view($superAdmin, $post))->toBeTrue();
});

it('allows all users to create posts', function () {
    $tenant = Tenant::factory()->create();
    $author = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $editor = User::factory()->create(['role' => 'editor', 'tenant_id' => $tenant->id]);
    $superAdmin = User::factory()->create(['role' => 'super_admin', 'tenant_id' => $tenant->id]);

    expect((new PostPolicy)->create($author))->toBeTrue()
        ->and((new PostPolicy)->create($editor))->toBeTrue()
        ->and((new PostPolicy)->create($superAdmin))->toBeTrue();
});

it('allows super_admin to manage categories', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'super_admin', 'tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);

    expect((new CategoryPolicy)->create($user))->toBeTrue()
        ->and((new CategoryPolicy)->update($user, $category))->toBeTrue()
        ->and((new CategoryPolicy)->delete($user, $category))->toBeTrue()
        ->and((new CategoryPolicy)->forceDelete($user, $category))->toBeTrue();
});

it('allows editor to manage categories', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'editor', 'tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);

    expect((new CategoryPolicy)->create($user))->toBeTrue()
        ->and((new CategoryPolicy)->update($user, $category))->toBeTrue()
        ->and((new CategoryPolicy)->delete($user, $category))->toBeTrue()
        ->and((new CategoryPolicy)->forceDelete($user, $category))->toBeFalse();
});

it('denies author from managing categories', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);

    expect((new CategoryPolicy)->create($user))->toBeFalse()
        ->and((new CategoryPolicy)->update($user, $category))->toBeFalse()
        ->and((new CategoryPolicy)->delete($user, $category))->toBeFalse()
        ->and((new CategoryPolicy)->forceDelete($user, $category))->toBeFalse();
});

it('allows all users to view any category', function () {
    $tenant = Tenant::factory()->create();
    $author = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $editor = User::factory()->create(['role' => 'editor', 'tenant_id' => $tenant->id]);
    $superAdmin = User::factory()->create(['role' => 'super_admin', 'tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);

    expect((new CategoryPolicy)->viewAny($author))->toBeTrue()
        ->and((new CategoryPolicy)->viewAny($editor))->toBeTrue()
        ->and((new CategoryPolicy)->viewAny($superAdmin))->toBeTrue()
        ->and((new CategoryPolicy)->view($author, $category))->toBeTrue()
        ->and((new CategoryPolicy)->view($editor, $category))->toBeTrue()
        ->and((new CategoryPolicy)->view($superAdmin, $category))->toBeTrue();
});

it('allows super_admin to manage tags', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'super_admin', 'tenant_id' => $tenant->id]);
    $tag = Tag::factory()->create(['tenant_id' => $tenant->id]);

    expect((new TagPolicy)->create($user))->toBeTrue()
        ->and((new TagPolicy)->update($user, $tag))->toBeTrue()
        ->and((new TagPolicy)->delete($user, $tag))->toBeTrue()
        ->and((new TagPolicy)->forceDelete($user, $tag))->toBeTrue();
});

it('allows editor to manage tags', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'editor', 'tenant_id' => $tenant->id]);
    $tag = Tag::factory()->create(['tenant_id' => $tenant->id]);

    expect((new TagPolicy)->create($user))->toBeTrue()
        ->and((new TagPolicy)->update($user, $tag))->toBeTrue()
        ->and((new TagPolicy)->delete($user, $tag))->toBeTrue()
        ->and((new TagPolicy)->forceDelete($user, $tag))->toBeFalse();
});

it('denies author from managing tags', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $tag = Tag::factory()->create(['tenant_id' => $tenant->id]);

    expect((new TagPolicy)->create($user))->toBeFalse()
        ->and((new TagPolicy)->update($user, $tag))->toBeFalse()
        ->and((new TagPolicy)->delete($user, $tag))->toBeFalse()
        ->and((new TagPolicy)->forceDelete($user, $tag))->toBeFalse();
});

it('allows all users to view any tag', function () {
    $tenant = Tenant::factory()->create();
    $author = User::factory()->create(['role' => 'author', 'tenant_id' => $tenant->id]);
    $editor = User::factory()->create(['role' => 'editor', 'tenant_id' => $tenant->id]);
    $superAdmin = User::factory()->create(['role' => 'super_admin', 'tenant_id' => $tenant->id]);
    $tag = Tag::factory()->create(['tenant_id' => $tenant->id]);

    expect((new TagPolicy)->viewAny($author))->toBeTrue()
        ->and((new TagPolicy)->viewAny($editor))->toBeTrue()
        ->and((new TagPolicy)->viewAny($superAdmin))->toBeTrue()
        ->and((new TagPolicy)->view($author, $tag))->toBeTrue()
        ->and((new TagPolicy)->view($editor, $tag))->toBeTrue()
        ->and((new TagPolicy)->view($superAdmin, $tag))->toBeTrue();
});

it('allows super_admin to manage users', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'super_admin', 'tenant_id' => $tenant->id]);
    $otherUser = User::factory()->create(['tenant_id' => $tenant->id]);

    expect((new UserPolicy)->viewAny($user))->toBeTrue()
        ->and((new UserPolicy)->view($user, $otherUser))->toBeTrue()
        ->and((new UserPolicy)->create($user))->toBeTrue()
        ->and((new UserPolicy)->update($user, $otherUser))->toBeTrue()
        ->and((new UserPolicy)->delete($user, $otherUser))->toBeTrue()
        ->and((new UserPolicy)->forceDelete($user, $otherUser))->toBeTrue();
});

it('denies editor from managing users', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'editor', 'tenant_id' => $tenant->id]);
    $otherUser = User::factory()->create(['tenant_id' => $tenant->id]);

    expect((new UserPolicy)->viewAny($user))->toBeFalse()
        ->and((new UserPolicy)->view($user, $otherUser))->toBeFalse()
        ->and((new UserPolicy)->create($user))->toBeFalse()
        ->and((new UserPolicy)->update($user, $otherUser))->toBeFalse()
        ->and((new UserPolicy)->delete($user, $otherUser))->toBeFalse()
        ->and((new UserPolicy)->forceDelete($user, $otherUser))->toBeFalse();
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
