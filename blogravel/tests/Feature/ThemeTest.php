<?php

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['domain' => 'themetest.com']);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('renders home page with posts', function () {
    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    $response = $this->get("/?tenant={$this->tenant->id}");

    $response->assertOk()
        ->assertSee($post->title)
        ->assertSee($this->tenant->name);
});

it('renders single post page', function () {
    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    $response = $this->get("/post/{$post->slug}?tenant={$this->tenant->id}");

    $response->assertOk()
        ->assertSee($post->title)
        ->assertSee($post->content);
});

it('renders category page', function () {
    $category = Category::factory()->create(['tenant_id' => $this->tenant->id]);

    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);
    $post->categories()->attach($category);

    $response = $this->get("/category/{$category->slug}?tenant={$this->tenant->id}");

    $response->assertOk()
        ->assertSee($category->name)
        ->assertSee($post->title);
});

it('renders subscribe form', function () {
    $response = $this->get("/subscribe?tenant={$this->tenant->id}");

    $response->assertOk()
        ->assertSee('Subscribe')
        ->assertSee('email');
});

it('handles subscribe submission', function () {
    $response = $this->post("/subscribe/{$this->tenant->id}", [
        'email' => 'test@example.com',
    ]);

    $response->assertOk()
        ->assertSee('Subscribed');

    $this->assertDatabaseHas('subscribers', [
        'email' => 'test@example.com',
        'tenant_id' => $this->tenant->id,
    ]);
});

it('renders contact form', function () {
    $response = $this->get("/contact?tenant={$this->tenant->id}");

    $response->assertOk()
        ->assertSee('Contact')
        ->assertSee('name')
        ->assertSee('message');
});

it('includes RSS auto-discovery links', function () {
    $response = $this->get("/?tenant={$this->tenant->id}");

    $response->assertOk()
        ->assertSee('application/rss+xml', escape: false)
        ->assertSee('application/atom+xml', escape: false)
        ->assertSee('application/feed+json', escape: false);
});

it('returns 404 for non-existent post', function () {
    $response = $this->get("/post/non-existent?tenant={$this->tenant->id}");

    $response->assertNotFound();
});

it('returns 404 for non-existent tenant', function () {
    $response = $this->get('/?tenant=non-existent');

    $response->assertNotFound();
});
