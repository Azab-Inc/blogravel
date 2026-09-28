<?php

use App\Enums\Plan;
use App\Enums\PostStatus;
use App\Enums\Role;
use App\Filament\Resources\PostResource\Pages\CreatePost;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

it('notifies and blocks post creation when the tenant reaches its plan limit', function () {
    config([
        'billing.enabled' => true,
        'billing.plans' => [
            'free' => ['posts' => 1, 'users' => 3],
            'pro' => ['posts' => null, 'users' => 10],
            'business' => ['posts' => null, 'users' => null],
        ],
    ]);

    $tenant = Tenant::factory()->create(['plan' => Plan::Free]);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);
    Post::factory()->create([
        'tenant_id' => $tenant->id,
        'author_id' => $user->id,
    ]);

    $this->actingAs($user);

    Livewire::test(CreatePost::class)
        ->fillForm([
            'title' => 'Blocked post',
            'content' => 'This should not be created.',
            'status' => PostStatus::Draft->value,
        ])
        ->call('create')
        ->assertNotified('Upgrade required');

    expect(Post::query()->where('title', 'Blocked post')->exists())->toBeFalse();
});

it('notifies and blocks user creation when the tenant reaches its plan limit', function () {
    config([
        'billing.enabled' => true,
        'billing.plans' => [
            'free' => ['posts' => 2, 'users' => 1],
            'pro' => ['posts' => null, 'users' => 10],
            'business' => ['posts' => null, 'users' => null],
        ],
    ]);

    $tenant = Tenant::factory()->create(['plan' => Plan::Free]);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => Role::Admin,
    ]);

    $this->actingAs($user);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'first_name' => 'Blocked',
            'last_name' => 'User',
            'email' => 'blocked-user@example.com',
            'password' => 'password123',
            'role' => Role::Author->value,
        ])
        ->call('create')
        ->assertNotified('Upgrade required');

    expect(User::query()->where('email', 'blocked-user@example.com')->exists())->toBeFalse();
});
