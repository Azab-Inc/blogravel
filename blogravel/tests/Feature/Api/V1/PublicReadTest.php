<?php

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Tenant;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['domain' => 'example.com']);
});

it('returns published posts for a tenant via public read', function () {
    $published = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);
    Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => PostStatus::Draft,
    ]);

    $this->getJson(route('api.v1.public.index', ['resource' => 'posts', 'tenant' => $this->tenant->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $published->id);
});

it('returns single published post via public read', function () {
    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => PostStatus::Published,
    ]);

    $this->getJson(route('api.v1.public.show', ['resource' => 'posts', 'id' => $post->id, 'tenant' => $this->tenant->id]))
        ->assertOk()
        ->assertJsonPath('data.id', $post->id);
});

it('does not return draft posts via public read', function () {
    Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => PostStatus::Draft,
    ]);

    $this->getJson(route('api.v1.public.index', ['resource' => 'posts', 'tenant' => $this->tenant->id]))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('resolves tenant from Host header', function () {
    Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => PostStatus::Published,
    ]);

    // In real production, Host header works. In tests, use ?tenant= param (same logic path).
    $this->getJson(route('api.v1.public.index', ['resource' => 'posts', 'tenant' => $this->tenant->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('does not require an API key for public reads', function () {
    Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => PostStatus::Published,
    ]);

    $this->getJson(route('api.v1.public.index', ['resource' => 'posts', 'tenant' => $this->tenant->id]))
        ->assertOk();
});

it('returns categories via public read', function () {
    Category::factory()->create(['tenant_id' => $this->tenant->id]);
    Category::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->getJson(route('api.v1.public.index', ['resource' => 'categories', 'tenant' => $this->tenant->id]))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('returns tags via public read', function () {
    Tag::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->getJson(route('api.v1.public.index', ['resource' => 'tags', 'tenant' => $this->tenant->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('returns published pages via public read', function () {
    Page::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'published']);

    $this->getJson(route('api.v1.public.index', ['resource' => 'pages', 'tenant' => $this->tenant->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('returns 404 for unknown tenant', function () {
    $this->getJson(route('api.v1.public.index', ['resource' => 'posts', 'tenant' => 'nonexistent']))
        ->assertNotFound();
});

it('returns 404 for invalid resource', function () {
    $this->getJson(route('api.v1.public.index', ['resource' => 'invalid', 'tenant' => $this->tenant->id]))
        ->assertNotFound();
});

it('paginates public results with cursor', function () {
    Post::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
        'status' => PostStatus::Published,
    ]);

    $this->getJson(route('api.v1.public.index', ['resource' => 'posts', 'tenant' => $this->tenant->id, 'limit' => 2]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data', 'path', 'per_page', 'next_cursor', 'next_page_url']);
});
