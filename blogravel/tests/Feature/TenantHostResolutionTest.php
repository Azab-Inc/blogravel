<?php

use App\Http\Middleware\ResolveTenantHost;
use App\Models\Tenant;
use App\Services\TenantHostResolver;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    config([
        'tenancy.platform_domain' => 'blogravel.test',
        'tenancy.reserved_labels' => [' WWW ', ' ADMIN ', ' Api '],
    ]);
});

test('tenant factory generates unique slugs from tenant names', function () {
    $firstTenant = Tenant::factory()->create([
        'domain' => 'legacy.example',
        'name' => 'Acme Bakery',
    ]);
    $secondTenant = Tenant::factory()->create(['name' => 'Acme Bakery']);

    expect($firstTenant->slug)->toBe('acme-bakery')
        ->and($secondTenant->slug)->toBe('acme-bakery-2')
        ->and($firstTenant->domain)->toBe('legacy.example');
});

test('tenant names without slug content receive a deterministic fallback slug', function () {
    $tenant = Tenant::factory()->create([
        'name' => '!!!',
    ]);

    expect($tenant->slug)->toBe('tenant-'.$tenant->id);
});

test('reserved tenant names receive deterministic resolvable fallback slugs', function (string $name) {
    $tenant = Tenant::factory()->create(['name' => $name]);

    expect($tenant->slug)->toBe('tenant-'.$tenant->id)
        ->and(app(TenantHostResolver::class)->resolve($tenant->slug.'.blogravel.test')?->is($tenant))->toBeTrue();
})->with(['admin', 'API', 'www']);

test('explicit reserved slugs receive deterministic fallback slugs', function (string $slug) {
    $tenant = Tenant::factory()->create([
        'name' => 'Explicit Reserved Tenant',
        'slug' => $slug,
    ]);

    expect($tenant->slug)->toBe('tenant-'.$tenant->id);
})->with(['admin', 'API', 'www']);

test('slug generation retries after a concurrent database uniqueness collision', function () {
    $inserted = false;
    Event::listen('eloquent.creating: '.Tenant::class, function (Tenant $tenant) use (&$inserted): void {
        if ($inserted || $tenant->name !== 'Concurrent Bakery') {
            return;
        }

        $inserted = true;
        DB::table('tenants')->insert([
            'id' => (string) Str::uuid(),
            'domain' => 'concurrent.example',
            'slug' => 'concurrent-bakery',
            'custom_domain' => null,
            'name' => 'Concurrent Bakery',
            'plan' => 'free',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $tenant = Tenant::factory()->create(['name' => 'Concurrent Bakery']);

    expect($tenant->slug)->toBe('concurrent-bakery-2');
});

test('tenant migration backfills existing rows before enforcing slug constraints', function () {
    Artisan::call('migrate:rollback', ['--step' => 2]);

    $firstId = (string) Str::uuid();
    $secondId = (string) Str::uuid();
    DB::table('tenants')->insert([
        [
            'id' => $firstId,
            'domain' => 'first.legacy.test',
            'name' => 'Legacy Bakery',
            'plan' => 'free',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => $secondId,
            'domain' => 'second.legacy.test',
            'name' => 'Legacy Bakery',
            'plan' => 'free',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    Artisan::call('migrate', ['--force' => true]);

    $migratedTenants = DB::table('tenants')->orderBy('domain')->get();

    expect($migratedTenants)->toHaveCount(2)
        ->and($migratedTenants->pluck('slug')->all())->toBe(['legacy-bakery', 'legacy-bakery-2'])
        ->and($migratedTenants->pluck('domain')->all())->toBe(['first.legacy.test', 'second.legacy.test']);
});

test('tenant migration avoids reserved labels while backfilling existing rows', function () {
    Artisan::call('migrate:rollback', ['--step' => 2]);

    $tenants = collect(['Admin', 'API', 'WWW'])->mapWithKeys(function (string $name): array {
        $id = (string) Str::uuid();

        DB::table('tenants')->insert([
            'id' => $id,
            'domain' => strtolower($name).'.legacy.test',
            'name' => $name,
            'plan' => 'free',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$name => $id];
    });

    Artisan::call('migrate', ['--force' => true]);

    foreach ($tenants as $id) {
        expect(DB::table('tenants')->where('id', $id)->value('slug'))
            ->toBe('tenant-'.$id);
    }
});

test('tenant migration keeps the lowest id custom domain and clears normalized collisions', function () {
    Artisan::call('migrate:rollback', ['--step' => 1]);

    $canonicalId = '00000000-0000-0000-0000-000000000001';
    $conflictingId = '00000000-0000-0000-0000-000000000002';
    DB::table('tenants')->insert([
        [
            'id' => $conflictingId,
            'domain' => 'conflicting.legacy.test',
            'slug' => 'conflicting-tenant',
            'custom_domain' => ' WWW.Example.TEST ',
            'name' => 'Conflicting Tenant',
            'plan' => 'free',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => $canonicalId,
            'domain' => 'canonical.legacy.test',
            'slug' => 'canonical-tenant',
            'custom_domain' => 'www.example.test',
            'name' => 'Canonical Tenant',
            'plan' => 'free',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    Artisan::call('migrate', ['--force' => true]);

    expect(DB::table('tenants')->where('id', $canonicalId)->value('custom_domain'))
        ->toBe('www.example.test')
        ->and(DB::table('tenants')->where('id', $conflictingId)->value('custom_domain'))
        ->toBeNull();

    expect(fn () => DB::table('tenants')->insert([
        'id' => '00000000-0000-0000-0000-000000000003',
        'domain' => 'third.legacy.test',
        'slug' => 'third-tenant',
        'custom_domain' => 'www.example.test',
        'name' => 'Third Tenant',
        'plan' => 'free',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
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

test('custom domains are normalized before persistence and uniqueness is case insensitive', function () {
    $tenant = Tenant::factory()->create([
        'name' => 'Acme Bakery',
        'custom_domain' => ' WWW.ACME.TEST ',
    ]);

    expect($tenant->custom_domain)->toBe('www.acme.test');

    expect(fn () => Tenant::factory()->create([
        'name' => 'Other Bakery',
        'custom_domain' => 'WWW.ACME.TEST',
    ]))->toThrow(QueryException::class);
});

test('database rejects case-colliding custom domains from raw inserts', function () {
    $tenant = Tenant::factory()->create([
        'name' => 'Raw Domain Tenant',
        'custom_domain' => 'raw-domain.test',
    ]);

    expect(fn () => DB::table('tenants')->insert([
        'id' => (string) Str::uuid(),
        'domain' => 'raw-domain-other.test',
        'slug' => 'raw-domain-other',
        'custom_domain' => 'RAW-DOMAIN.TEST',
        'name' => 'Raw Collision Tenant',
        'plan' => 'free',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect($tenant->fresh()->custom_domain)->toBe('raw-domain.test');
});

test('PostgreSQL enforces the functional custom-domain uniqueness index', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Requires the pgsql test driver.');
    }

    expect(DB::selectOne("SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'tenants' AND indexname = 'tenants_custom_domain_lower_unique'"))
        ->not->toBeNull();

    Tenant::factory()->create([
        'name' => 'PostgreSQL Domain Tenant',
        'custom_domain' => 'postgres-domain.test',
    ]);

    expect(fn () => DB::table('tenants')->insert([
        'id' => (string) Str::uuid(),
        'domain' => 'postgres-domain-other.test',
        'slug' => 'postgres-domain-other',
        'custom_domain' => 'POSTGRES-DOMAIN.TEST',
        'name' => 'PostgreSQL Collision Tenant',
        'plan' => 'free',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
})->group('pgsql');

test('reserved platform labels are rejected before custom and legacy domain matching', function () {
    Tenant::factory()->create([
        'name' => 'Custom Admin Tenant',
        'custom_domain' => 'admin.blogravel.test',
    ]);
    Tenant::factory()->create([
        'name' => 'Legacy Admin Tenant',
        'domain' => 'admin.blogravel.test',
    ]);

    expect(app(TenantHostResolver::class)->resolve('admin.blogravel.test'))->toBeNull();
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

test('unknown hosts cannot reach the admin web surface', function () {
    $this->get('http://unknown.blogravel.test/admin')
        ->assertNotFound();
});

test('unknown hosts cannot reach the admin secret route before authentication', function () {
    $this->get('http://unknown.blogravel.test/admin/secret')
        ->assertNotFound();
});

test('reserved and malformed hosts cannot reach the debug web surface', function (string $host) {
    $this->get("http://{$host}/debug/session-check")
        ->assertNotFound();
})->with([
    'admin.blogravel.test',
    'nested.acme-bakery.blogravel.test',
]);

test('host resolution never returns another tenant for a different host', function () {
    $tenantA = Tenant::factory()->create(['name' => 'Acme Bakery']);
    $tenantB = Tenant::factory()->create(['name' => 'Beta Bakery']);

    $resolvedTenant = app(TenantHostResolver::class)->resolve('acme-bakery.blogravel.test');

    expect($resolvedTenant?->is($tenantA))->toBeTrue()
        ->and($resolvedTenant?->is($tenantB))->toBeFalse();
});
