<?php

use App\Http\Middleware\ResolveTenantHost;
use App\Models\Tenant;
use App\Services\TenantHostResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    config([
        'tenancy.platform_domain' => 'blogravel.test',
        'tenancy.reserved_labels' => ['www', 'admin', 'api'],
    ]);
});

test('tenant factory generates unique slugs from tenant names', function () {
    $firstTenant = Tenant::factory()->create(['name' => 'Acme Bakery']);
    $secondTenant = Tenant::factory()->create(['name' => 'Acme Bakery']);

    expect($firstTenant->slug)->toBe('acme-bakery')
        ->and($secondTenant->slug)->toBe('acme-bakery-2');
});

test('resolver matches a tenant by its generated platform host', function () {
    $tenant = Tenant::factory()->create(['name' => 'Acme Bakery']);

    $resolvedTenant = app(TenantHostResolver::class)->resolve('acme-bakery.blogravel.test');

    expect($resolvedTenant?->is($tenant))->toBeTrue();
});

test('resolver matches a tenant by its exact custom domain', function () {
    $tenant = Tenant::factory()->create([
        'name' => 'Acme Bakery',
        'custom_domain' => 'www.acme.test',
    ]);

    $resolvedTenant = app(TenantHostResolver::class)->resolve('WWW.ACME.TEST');

    expect($resolvedTenant?->is($tenant))->toBeTrue();
});

test('resolver rejects unknown reserved and malformed hosts', function (string $host) {
    Tenant::factory()->create(['name' => 'Acme Bakery']);

    expect(app(TenantHostResolver::class)->resolve($host))->toBeNull();
})->with([
    'unknown.blogravel.test',
    'admin.blogravel.test',
    'nested.acme-bakery.blogravel.test',
    'acme_bakery.blogravel.test',
    'blogravel.test.evil.test',
]);

test('middleware passes the platform root through without a tenant', function () {
    $request = Request::create('https://blogravel.test/');
    $middleware = app(ResolveTenantHost::class);

    $response = $middleware->handle($request, function (Request $request) {
        expect($request->attributes->get('tenant'))->toBeNull();

        return new Response('root');
    });

    expect($response->getContent())->toBe('root');
});

test('middleware sets the resolved tenant on valid tenant hosts', function () {
    $tenant = Tenant::factory()->create(['name' => 'Acme Bakery']);
    $request = Request::create('https://acme-bakery.blogravel.test/');
    $middleware = app(ResolveTenantHost::class);

    $middleware->handle($request, function (Request $request) use ($tenant) {
        expect($request->attributes->get('tenant')->is($tenant))->toBeTrue();

        return new Response('tenant');
    });
});

test('middleware returns a clear not found response for invalid tenant hosts', function () {
    $request = Request::create('https://unknown.blogravel.test/');
    $middleware = app(ResolveTenantHost::class);

    $middleware->handle($request, fn () => new Response('unexpected'));
})->throws(NotFoundHttpException::class, 'Tenant host not found.');

test('host resolution never returns another tenant for a different host', function () {
    $tenantA = Tenant::factory()->create(['name' => 'Acme Bakery']);
    $tenantB = Tenant::factory()->create(['name' => 'Beta Bakery']);

    $resolvedTenant = app(TenantHostResolver::class)->resolve('acme-bakery.blogravel.test');

    expect($resolvedTenant?->is($tenantA))->toBeTrue()
        ->and($resolvedTenant?->is($tenantB))->toBeFalse();
});
