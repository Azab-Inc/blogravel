<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['domain' => 'overridetest.com']);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->themesDir = base_path('resources/themes');
});

afterEach(function () {
    File::deleteDirectory($this->themesDir.'/custom-test');
});

it('uses custom theme component when active_theme is set', function () {
    $customDir = $this->themesDir.'/custom-test/components';
    File::makeDirectory($customDir, 0755, true);
    file_put_contents($customDir.'/post-card.blade.php', '<div class="custom-post-card">{{$post->title}}</div>');

    Setting::create([
        'tenant_id' => $this->tenant->id,
        'key' => 'active_theme',
        'value' => 'custom-test',
    ]);

    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    $response = $this->get("/?tenant={$this->tenant->id}");

    $response->assertOk()
        ->assertSee('custom-post-card')
        ->assertSee($post->title);
});

it('falls back to base theme component when custom theme missing it', function () {
    $customDir = $this->themesDir.'/custom-test/components';
    File::makeDirectory($customDir, 0755, true);

    Setting::create([
        'tenant_id' => $this->tenant->id,
        'key' => 'active_theme',
        'value' => 'custom-test',
    ]);

    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    $response = $this->get("/?tenant={$this->tenant->id}");

    $response->assertOk()
        ->assertSee($post->title);
});

it('returns 404 when theme_enabled is false', function () {
    Setting::create([
        'tenant_id' => $this->tenant->id,
        'key' => 'theme_enabled',
        'value' => 'false',
    ]);

    $response = $this->get("/?tenant={$this->tenant->id}");

    $response->assertNotFound();
});

it('uses base theme by default when no active_theme setting', function () {
    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    $response = $this->get("/?tenant={$this->tenant->id}");

    $response->assertOk()
        ->assertSee($post->title);
});

it('registers custom theme assets from theme.json', function () {
    $customDir = $this->themesDir.'/custom-test';
    File::makeDirectory($customDir.'/components', 0755, true);
    file_put_contents($customDir.'/theme.json', json_encode([
        'name' => 'Custom Test',
        'styles' => ['custom.css'],
        'scripts' => ['custom.js'],
    ]));

    Setting::create([
        'tenant_id' => $this->tenant->id,
        'key' => 'active_theme',
        'value' => 'custom-test',
    ]);

    $response = $this->get("/?tenant={$this->tenant->id}");

    $response->assertOk()
        ->assertSee('/themes/custom-test/custom.css', escape: false)
        ->assertSee('/themes/custom-test/custom.js', escape: false);
});
