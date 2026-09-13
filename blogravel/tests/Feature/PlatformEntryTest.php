<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    config([
        'session.domain' => '.blogravel.test',
    ]);
});

test('guest requests to the platform root redirect to the Filament login route', function () {
    $response = $this->get('http://blogravel.com/');

    $response->assertRedirectToRoute('filament.admin.auth.login');
});

test('authenticated requests to the platform root redirect to the Filament dashboard route', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('http://blogravel.com/');

    $response->assertRedirectToRoute('filament.admin.pages.dashboard');
});

test('tenant hosts resolve before theme rendering', function () {
    $tenant = Tenant::factory()->create(['name' => 'Acme Bakery']);
    $customTheme = base_path('resources/themes/custom-test');
    File::makeDirectory($customTheme.'/components', 0755, true);
    File::put($customTheme.'/components/post-card.blade.php', '<div class="custom-theme">{{ $post->title }}</div>');

    $author = User::factory()->forTenant($tenant)->create();
    $post = Post::factory()->create([
        'tenant_id' => $tenant->id,
        'author_id' => $author->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    Setting::create([
        'tenant_id' => $tenant->id,
        'key' => 'active_theme',
        'value' => 'custom-test',
    ]);

    try {
        $response = $this->get('http://acme-bakery.blogravel.com/');

        $response->assertOk()
            ->assertSee('custom-theme')
            ->assertSee($post->title);
    } finally {
        File::deleteDirectory(base_path('resources/themes/custom-test'));
    }
});

test('root responses use the configured session cookie domain', function () {
    $response = $this->get('http://blogravel.com/');

    expect(collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'))
        ?->getDomain())->toBe('.blogravel.test');
});
