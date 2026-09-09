<?php

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['domain' => 'feedtest.com']);
    $this->author = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->category = Category::factory()->create(['tenant_id' => $this->tenant->id, 'slug' => 'laravel']);
    $this->post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->author->id,
        'status' => PostStatus::Published,
        'published_at' => now()->subDay(),
        'title' => 'My First Post',
        'content' => '<p>Hello world</p>',
        'excerpt' => 'A short excerpt',
    ]);
    $this->post->categories()->attach($this->category);
});

it('returns valid RSS 2.0 for published posts', function () {
    $response = $this->get(route('feed.posts', ['resource' => 'posts', 'format' => 'xml', 'tenant' => $this->tenant->id]));
    $response->assertOk()->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
    $body = $response->getContent();
    $this->assertStringContainsString('<rss', $body);
    $this->assertStringContainsString('My First Post', $body);
    $this->assertStringContainsString('<content:encoded>', $body);
});

it('returns valid Atom feed for published posts', function () {
    $response = $this->get(route('feed.posts', ['resource' => 'posts', 'format' => 'atom', 'tenant' => $this->tenant->id]));
    $response->assertOk()->assertHeader('Content-Type', 'application/atom+xml; charset=UTF-8');
    $body = $response->getContent();
    $this->assertStringContainsString('<feed', $body);
    $this->assertStringContainsString('My First Post', $body);
});

it('returns valid JSON Feed for published posts', function () {
    $response = $this->get(route('feed.posts', ['resource' => 'posts', 'format' => 'json', 'tenant' => $this->tenant->id]));
    $response->assertOk()->assertHeader('Content-Type', 'application/feed+json; charset=UTF-8');
    $json = $response->json();
    $this->assertArrayHasKey('version', $json);
    $this->assertArrayHasKey('items', $json);
    $this->assertEquals('My First Post', $json['items'][0]['title']);
    $this->assertArrayHasKey('content_html', $json['items'][0]);
});

it('excludes draft posts from feed', function () {
    Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => PostStatus::Draft,
    ]);

    $response = $this->get(route('feed.posts', ['resource' => 'posts', 'format' => 'json', 'tenant' => $this->tenant->id]));
    $this->assertEquals(1, count($response->json('items')));
});

it('returns RSS for category slug', function () {
    $response = $this->get(route('feed.category', ['format' => 'xml', 'slug' => 'laravel', 'tenant' => $this->tenant->id]));
    $response->assertOk();
    $body = $response->getContent();
    $this->assertStringContainsString('<rss', $body);
    $this->assertStringContainsString('My First Post', $body);
});

it('returns RSS for author id', function () {
    $response = $this->get(route('feed.author', ['format' => 'xml', 'author' => $this->author->id, 'tenant' => $this->tenant->id]));
    $response->assertOk();
    $body = $response->getContent();
    $this->assertStringContainsString('<rss', $body);
    $this->assertStringContainsString('My First Post', $body);
});

it('returns 404 for non-existent category', function () {
    $this->get(route('feed.category', ['format' => 'xml', 'slug' => 'nope', 'tenant' => $this->tenant->id]))
        ->assertNotFound();
});

it('returns 404 for unknown tenant', function () {
    $this->get(route('feed.posts', ['resource' => 'posts', 'format' => 'xml', 'tenant' => 'nonexistent']))
        ->assertNotFound();
});

it('RSS feed contains author name and categories', function () {
    $body = $this->get(route('feed.posts', ['resource' => 'posts', 'format' => 'xml', 'tenant' => $this->tenant->id]))->getContent();
    $this->assertStringContainsString('<dc:creator>'.$this->author->name.'</dc:creator>', $body);
    $this->assertStringContainsString('<category>'.$this->category->name.'</category>', $body);
});

it('JSON feed contains author and tags', function () {
    $json = $this->get(route('feed.posts', ['resource' => 'posts', 'format' => 'json', 'tenant' => $this->tenant->id]))->json();
    $this->assertStringContainsString($this->author->name, $json['items'][0]['authors'][0]['name']);
    $this->assertContains($this->category->name, $json['items'][0]['tags']);
});

it('auto-discovery tags present on welcome page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('type="application/rss+xml"')
        ->assertSee('type="application/atom+xml"');
});
