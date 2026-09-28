<?php

use App\Enums\PostStatus;
use App\Enums\Role;
use App\Filament\Resources\PostResource\Pages\CreatePost;
use App\Filament\Resources\PostResource\Pages\EditPost;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => Role::Editor,
    ]);

    $this->actingAs($this->user);
});

it('notifies when a draft post is created', function () {
    Livewire::test(CreatePost::class)
        ->fillForm([
            'title' => 'Draft feedback post',
            'content' => 'Draft content',
            'status' => PostStatus::Draft->value,
        ])
        ->call('create')
        ->assertNotified('Post created');
});

it('uses publish wording when a published post is created', function () {
    Livewire::test(CreatePost::class)
        ->fillForm([
            'title' => 'Published feedback post',
            'content' => 'Published content',
            'status' => PostStatus::Published->value,
        ])
        ->call('create')
        ->assertNotified('Post published');
});

it('notifies when a draft post is saved', function () {
    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    Livewire::test(EditPost::class, ['record' => $post->getKey()])
        ->fillForm(['title' => 'Updated draft feedback post'])
        ->call('save')
        ->assertNotified('Post saved');
});

it('uses publish wording when a published post is saved', function () {
    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published,
    ]);

    Livewire::test(EditPost::class, ['record' => $post->getKey()])
        ->fillForm(['title' => 'Updated published feedback post'])
        ->call('save')
        ->assertNotified('Post published');
});
