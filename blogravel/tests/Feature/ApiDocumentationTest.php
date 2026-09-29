<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

it('renders the API documentation from Markdown source files', function () {
    Tenant::factory()->create(['slug' => 'acmeio', 'domain' => 'acmeio.blogravel.com']);

    $response = $this->get('/docs/api?tenant=acmeio');

    $response->assertOk()
        ->assertSee('Blogravel API')
        ->assertSee('TypeScript')
        ->assertSee('JavaScript')
        ->assertSee('PHP')
        ->assertSee('.NET')
        ->assertSee('application/problem+json', escape: false)
        ->assertSee('acmeio.blogravel.com')
        ->assertDontSee('your-tenant.example')
        ->assertSee('<th>Authentication</th>', escape: false)
        ->assertSee('<td>Sanctum bearer token</td>', escape: false);

    foreach ([
        'overview.md',
        'authentication.md',
        'pagination.md',
        'errors.md',
        'public-endpoints.md',
        'authenticated-endpoints.md',
        'webhooks.md',
    ] as $document) {
        expect(file_exists(base_path("docs/api/{$document}")))->toBeTrue();
    }
});

it('renders tenant-specific API examples from the selected slug', function () {
    Tenant::factory()->create(['slug' => 'northstar', 'domain' => 'northstar.blogravel.com']);

    $this->get('/docs/api?tenant=northstar')
        ->assertOk()
        ->assertSee('northstar.blogravel.com')
        ->assertDontSee('acmeio.blogravel.com');
});

it('rejects unknown tenant slugs', function () {
    $this->get('/docs/api?tenant=unknown')
        ->assertOk()
        ->assertSee('The tenant could not be found.')
        ->assertDontSee('unknown.blogravel.com');
});

it('prompts for a tenant before rendering tenant-specific examples', function () {
    $this->get('/docs/api')
        ->assertOk()
        ->assertSee('Enter your tenant slug')
        ->assertDontSee('acmeio.blogravel.com');
});

it('documents routes that exist in the API route collection', function () {
    Tenant::factory()->create(['slug' => 'acmeio', 'domain' => 'acmeio.blogravel.com']);

    $apiRouteNames = [
        'api.v1.public.index',
        'api.v1.public.show',
        'api.subscribe',
        'api.confirm',
        'api.unsubscribe',
        'api.subscribers.destroy',
        'api.webhooks.soro',
        'api.webhooks.stripe',
        'api.v1.login',
        'api.v1.logout',
        'posts.index',
        'posts.store',
        'posts.show',
        'posts.update',
        'posts.destroy',
        'pages.index',
        'pages.store',
        'pages.show',
        'pages.update',
        'pages.destroy',
        'categories.index',
        'categories.store',
        'categories.show',
        'categories.update',
        'categories.destroy',
        'tags.index',
        'tags.store',
        'tags.show',
        'tags.update',
        'tags.destroy',
        'api.v1.drafts',
        'api.v1.drafts.show',
        'api.v1.drafts.preview',
        'api.v1.drafts.preview-url',
    ];

    foreach ($apiRouteNames as $routeName) {
        expect(Route::getRoutes()->getByName($routeName))->not->toBeNull();
    }

    $documentedRoutes = [
        'api.v1.public.index' => '/api/v1/public/{resource}',
        'api.v1.public.show' => '/api/v1/public/{resource}/{id}',
        'api.v1.login' => '/api/v1/login',
        'api.v1.drafts' => '/api/v1/drafts',
        'api.webhooks.soro' => '/api/v1/webhooks/soro',
    ];

    $response = $this->get('/docs/api?tenant=acmeio');

    foreach ($documentedRoutes as $routeName => $path) {
        $response->assertSee($path);
    }
});
