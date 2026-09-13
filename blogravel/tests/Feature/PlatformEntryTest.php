<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;

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

test('tenant host identity takes precedence over a different query tenant', function () {
    $hostTenant = Tenant::factory()->create(['name' => 'Host Bakery']);
    $queryTenant = Tenant::factory()->create(['name' => 'Query Bakery']);

    $response = $this->get("http://{$hostTenant->slug}.blogravel.com/?tenant={$queryTenant->id}");

    $response->assertOk()
        ->assertSee($hostTenant->name)
        ->assertDontSee($queryTenant->name);
});

test('unknown hosts cannot use a tenant parameter to render tenant data', function () {
    $tenant = Tenant::factory()->create();

    $this->get("http://unknown.blogravel.com/?tenant={$tenant->id}")
        ->assertNotFound();
});

test('tenant host identity scopes subscribe and contact posts', function () {
    Mail::fake();
    $hostTenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();

    $this->post("http://{$hostTenant->slug}.blogravel.com/subscribe/{$otherTenant->id}", [
        'email' => 'host@example.com',
    ])->assertOk();

    $this->post("http://{$hostTenant->slug}.blogravel.com/contact/{$otherTenant->id}", [
        'name' => 'Host User',
        'email' => 'host@example.com',
        'message' => 'Hello',
    ])->assertOk();

    $this->assertDatabaseHas('subscribers', [
        'email' => 'host@example.com',
        'tenant_id' => $hostTenant->id,
    ]);
    $this->assertDatabaseMissing('subscribers', [
        'email' => 'host@example.com',
        'tenant_id' => $otherTenant->id,
    ]);
});

test('legacy domain hosts resolve their own tenant', function () {
    $tenant = Tenant::factory()->create([
        'domain' => 'legacy.blogravel.test',
        'name' => 'Legacy Bakery',
    ]);
    $otherTenant = Tenant::factory()->create(['name' => 'Other Bakery']);

    $this->get("http://legacy.blogravel.test/?tenant={$otherTenant->id}")
        ->assertOk()
        ->assertSee($tenant->name)
        ->assertDontSee($otherTenant->name);
});
