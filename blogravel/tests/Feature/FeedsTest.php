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

it('serializes canonical RSS and Atom links on every tenant host', function () {
    $hostCases = [
        'generated' => [$this->tenant->slug.'.blogravel.com', []],
        'custom' => ['custom-feed.test', ['custom_domain' => 'custom-feed.test']],
        'legacy' => ['feedtest.com', ['custom_domain' => null]],
        'local' => ['localhost', ['custom_domain' => null]],
    ];

    foreach ($hostCases as [$expectedHost, $attributes]) {
        $this->tenant->update($attributes);

        foreach (['xml', 'atom'] as $format) {
            $response = $this->get("http://{$expectedHost}/feeds/posts?tenant={$this->tenant->id}&format={$format}");
            $response->assertOk();

            $xml = simplexml_load_string($response->getContent());
            expect($xml)->not->toBeFalse();

            if ($format === 'xml') {
                $canonicalUrl = (string) $xml->channel->link;
                $selfUrl = (string) $xml->xpath('//*[local-name() = "link" and @rel = "self"]')[0]['href'];
                $itemUrl = (string) $xml->channel->item->link;
            } else {
                $canonicalUrl = (string) $xml->xpath('//*[local-name() = "link" and @rel = "alternate"]')[0]['href'];
                $selfUrl = (string) $xml->xpath('//*[local-name() = "link" and @rel = "self"]')[0]['href'];
                $itemUrl = (string) $xml->xpath('//*[local-name() = "entry"]/*[local-name() = "link"]')[0]['href'];
            }

            expect(parse_url($canonicalUrl, PHP_URL_HOST))->toBe($expectedHost)
                ->and(parse_url($canonicalUrl, PHP_URL_PATH))->toBe('/')
                ->and(parse_url($selfUrl, PHP_URL_HOST))->toBe($expectedHost)
                ->and(parse_url($selfUrl, PHP_URL_PATH))->toBe('/feeds/posts')
                ->and(parse_url($selfUrl, PHP_URL_QUERY))->toContain('tenant='.$this->tenant->id)
                ->and(parse_url($itemUrl, PHP_URL_HOST))->toBe($expectedHost)
                ->and(parse_url($itemUrl, PHP_URL_FRAGMENT))->toBe('post-'.$this->post->slug);
        }
    }
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

it('uses the current tenant host for JSON feed links', function (string $host, array $tenantAttributes) {
    $tenant = Tenant::factory()->create($tenantAttributes);
    $author = User::factory()->create(['tenant_id' => $tenant->id]);
    Post::factory()->create([
        'tenant_id' => $tenant->id,
        'author_id' => $author->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    $response = $this->get("http://{$host}/feeds/posts?tenant={$tenant->id}&format=json");
    $json = $response->assertOk()->json();

    expect(parse_url($json['home_page_url'], PHP_URL_HOST))->toBe($host)
        ->and(parse_url($json['feed_url'], PHP_URL_HOST))->toBe($host)
        ->and(parse_url($json['items'][0]['url'], PHP_URL_HOST))->toBe($host);
})->with([
    'tenant host' => ['feed-tenant.blogravel.com', ['name' => 'Feed Tenant']],
    'custom domain' => ['custom-feed.test', ['name' => 'Custom Feed', 'custom_domain' => 'custom-feed.test']],
    'legacy domain' => ['legacy-feed.test', ['name' => 'Legacy Feed', 'domain' => 'legacy-feed.test']],
    'local host' => ['localhost', ['name' => 'Local Feed']],
]);

it('supports a bare IPv6 local host for feed tenant queries', function () {
    $response = $this->get("http://[::1]/feeds/posts?tenant={$this->tenant->id}&format=json");

    $response->assertOk();
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

it('does not let an unknown host use a tenant parameter to expose a feed', function () {
    $this->get("http://unknown.blogravel.com/feeds/posts?tenant={$this->tenant->id}")
        ->assertNotFound();
});

it('does not let the platform host use a tenant parameter to expose a feed', function () {
    $this->get("http://blogravel.com/feeds/posts?tenant={$this->tenant->id}")
        ->assertNotFound();
});

it('uses the tenant host instead of a different feed tenant parameter', function () {
    $otherTenant = Tenant::factory()->create(['name' => 'Other Feed Tenant']);

    $response = $this->get("http://{$this->tenant->slug}.blogravel.com/feeds/posts?tenant={$otherTenant->id}&format=json");

    $response->assertOk();
    expect($response->json('title'))->toContain($this->tenant->name)
        ->and($response->json('title'))->not->toContain($otherTenant->name);
});

it('preserves legacy domain feed resolution while ignoring a different tenant parameter', function () {
    $otherTenant = Tenant::factory()->create(['name' => 'Other Feed Tenant']);

    $response = $this->get("http://feedtest.com/feeds/posts?tenant={$otherTenant->id}&format=json");

    $response->assertOk();
    expect($response->json('title'))->toContain($this->tenant->name)
        ->and($response->json('title'))->not->toContain($otherTenant->name);
});

it('does not expose category feeds on non-tenant hosts', function (string $host) {
    $this->get("http://{$host}/feeds/categories/laravel?tenant={$this->tenant->id}&format=json")
        ->assertNotFound();
})->with([
    'admin.blogravel.com',
    'nested.acme-bakery.blogravel.com',
    'unknown.blogravel.com',
]);

it('does not expose author feeds on non-tenant hosts', function (string $host) {
    $this->get("http://{$host}/feeds/authors/{$this->author->id}?tenant={$this->tenant->id}&format=json")
        ->assertNotFound();
})->with([
    'admin.blogravel.com',
    'nested.acme-bakery.blogravel.com',
    'unknown.blogravel.com',
]);

it('keeps category feeds on the host tenant when the tenant parameter differs', function () {
    $otherTenant = Tenant::factory()->create(['name' => 'Other Category Tenant']);

    $response = $this->get("http://{$this->tenant->slug}.blogravel.com/feeds/categories/laravel?tenant={$otherTenant->id}&format=json");

    $response->assertOk();
    expect($response->json('title'))->toContain($this->tenant->name)
        ->and($response->json('title'))->not->toContain($otherTenant->name);
});

it('keeps author feeds on the host tenant when the tenant parameter differs', function () {
    $otherTenant = Tenant::factory()->create(['name' => 'Other Author Tenant']);

    $response = $this->get("http://{$this->tenant->slug}.blogravel.com/feeds/authors/{$this->author->id}?tenant={$otherTenant->id}&format=json");

    $response->assertOk();
    expect($response->json('title'))->toContain($this->tenant->name)
        ->and($response->json('title'))->not->toContain($otherTenant->name);
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
    $this->get("http://{$this->tenant->slug}.blogravel.com/")
        ->assertOk()
        ->assertSeeHtml('type="application/rss+xml"')
        ->assertSeeHtml('type="application/atom+xml"');
});
