<?php

use App\Enums\PostStatus;
use App\Enums\Role;
use App\Filament\Resources\PostResource;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->otherTenant = Tenant::factory()->create();

    $this->author = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => Role::Author,
    ]);
    $this->otherAuthor = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => Role::Author,
    ]);
    $this->editor = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => Role::Editor,
    ]);

    $this->ownDraft = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->author->id,
        'status' => PostStatus::Draft,
    ]);
    $this->otherDraft = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->otherAuthor->id,
        'status' => PostStatus::Draft,
    ]);
    $this->publishedPost = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->otherAuthor->id,
        'status' => PostStatus::Published,
    ]);
});

it('author sees only own drafts and published posts when toggle is off', function () {
    Setting::factory()->create([
        'tenant_id' => $this->tenant->id,
        'key' => 'authors_can_view_others_posts',
        'value' => 'false',
    ]);

    $this->actingAs($this->author);

    $query = PostResource::getEloquentQuery();
    $postIds = $query->pluck('id')->toArray();

    expect($postIds)->toContain($this->ownDraft->id);
    expect($postIds)->toContain($this->publishedPost->id);
    expect($postIds)->not->toContain($this->otherDraft->id);
});

it('author sees all tenant posts when toggle is on', function () {
    Setting::factory()->create([
        'tenant_id' => $this->tenant->id,
        'key' => 'authors_can_view_others_posts',
        'value' => 'true',
    ]);

    $this->actingAs($this->author);

    $query = PostResource::getEloquentQuery();
    $postIds = $query->pluck('id')->toArray();

    expect($postIds)->toContain($this->ownDraft->id);
    expect($postIds)->toContain($this->otherDraft->id);
    expect($postIds)->toContain($this->publishedPost->id);
});

it('editor sees all tenant posts regardless of toggle', function () {
    Setting::factory()->create([
        'tenant_id' => $this->tenant->id,
        'key' => 'authors_can_view_others_posts',
        'value' => 'false',
    ]);

    $this->actingAs($this->editor);

    $query = PostResource::getEloquentQuery();
    $postIds = $query->pluck('id')->toArray();

    expect($postIds)->toContain($this->ownDraft->id);
    expect($postIds)->toContain($this->otherDraft->id);
    expect($postIds)->toContain($this->publishedPost->id);
});

it('does not show other tenant posts', function () {
    $otherAuthor = User::factory()->create([
        'tenant_id' => $this->otherTenant->id,
        'role' => Role::Author,
    ]);
    $otherTenantPost = Post::factory()->create([
        'tenant_id' => $this->otherTenant->id,
        'author_id' => $otherAuthor->id,
        'status' => PostStatus::Published,
    ]);

    $this->actingAs($this->author);

    $query = PostResource::getEloquentQuery();
    $postIds = $query->pluck('id')->toArray();

    expect($postIds)->not->toContain($otherTenantPost->id);
});

it('defaults to private when no toggle setting exists', function () {
    $this->actingAs($this->author);

    $query = PostResource::getEloquentQuery();
    $postIds = $query->pluck('id')->toArray();

    expect($postIds)->toContain($this->ownDraft->id);
    expect($postIds)->toContain($this->publishedPost->id);
    expect($postIds)->not->toContain($this->otherDraft->id);
});
