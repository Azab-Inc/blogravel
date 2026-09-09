<?php

use App\Enums\Role;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\PostPolicy;
use App\Policies\TagPolicy;

it('allows admin to create/edit/delete posts (same as editor)', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['role' => Role::Admin, 'tenant_id' => $tenant->id]);
    $post = Post::factory()->create(['author_id' => $admin->id, 'tenant_id' => $tenant->id]);

    expect((new PostPolicy)->create($admin))->toBeTrue()
        ->and((new PostPolicy)->update($admin, $post))->toBeTrue()
        ->and((new PostPolicy)->delete($admin, $post))->toBeTrue();
});

it('allows admin to create/edit/delete categories', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['role' => Role::Admin, 'tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);

    expect((new CategoryPolicy)->create($admin))->toBeTrue()
        ->and((new CategoryPolicy)->update($admin, $category))->toBeTrue()
        ->and((new CategoryPolicy)->delete($admin, $category))->toBeTrue()
        ->and((new CategoryPolicy)->forceDelete($admin, $category))->toBeFalse();
});

it('allows admin to create/edit/delete tags', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['role' => Role::Admin, 'tenant_id' => $tenant->id]);
    $tag = Tag::factory()->create(['tenant_id' => $tenant->id]);

    expect((new TagPolicy)->create($admin))->toBeTrue()
        ->and((new TagPolicy)->update($admin, $tag))->toBeTrue()
        ->and((new TagPolicy)->delete($admin, $tag))->toBeTrue()
        ->and((new TagPolicy)->forceDelete($admin, $tag))->toBeFalse();
});
