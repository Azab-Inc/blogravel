<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\ApiDocumentation\ApiDocumentationRenderer;
use App\Services\ApiDocumentation\TenantDocumentationContext;
use Illuminate\View\View;
use InvalidArgumentException;

class ApiDocumentationController extends Controller
{
    public function __invoke(ApiDocumentationRenderer $renderer): View
    {
        $tenantSlug = request()->query('tenant');
        $prototype = app()->environment('local') && request()->boolean('prototype');
        $variant = in_array(request()->query('variant'), ['a', 'b', 'c'], true)
            ? request()->query('variant')
            : 'a';
        $error = null;
        $context = null;

        $isPrototypeTenant = $prototype
            && is_string($tenantSlug)
            && strtolower(trim($tenantSlug)) === 'prototype';

        if (is_string($tenantSlug) && trim($tenantSlug) !== '' && ! $isPrototypeTenant) {
            try {
                $context = TenantDocumentationContext::fromSlug($tenantSlug);
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
        }

        if ($prototype && $context === null && $error === null) {
            $context = TenantDocumentationContext::fromTenant(new Tenant([
                'slug' => 'prototype',
                'domain' => 'prototype.blogravel.com',
            ]));
        }

        return view('docs.api', [
            'documents' => $renderer->render($context),
            'tenantSlug' => $context?->slug ?? $tenantSlug,
            'error' => $error,
            'prototype' => $prototype,
            'variant' => $variant,
        ]);
    }
}
