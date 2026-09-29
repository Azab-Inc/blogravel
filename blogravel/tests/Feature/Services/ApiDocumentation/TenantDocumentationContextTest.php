<?php

use App\Models\Tenant;
use App\Services\ApiDocumentation\TenantDocumentationContext;

it('builds a tenant API host from a slug', function () {
    Tenant::factory()->create([
        'slug' => 'acmeio',
        'domain' => 'acmeio.blogravel.com',
    ]);

    $context = TenantDocumentationContext::fromSlug('AcmeIO');

    expect($context->slug)->toBe('acmeio')
        ->and($context->host())->toBe('acmeio.blogravel.com')
        ->and($context->url('/api/v1/posts'))->toBe('https://acmeio.blogravel.com/api/v1/posts');
});

it('uses a tenant custom domain when configured', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'acmeio',
        'custom_domain' => 'content.example.org',
    ]);

    expect(TenantDocumentationContext::fromTenant($tenant)->host())
        ->toBe('content.example.org');
});

it('rejects invalid tenant slugs', function () {
    expect(fn () => TenantDocumentationContext::fromSlug('not a tenant'))
        ->toThrow(InvalidArgumentException::class);
});
