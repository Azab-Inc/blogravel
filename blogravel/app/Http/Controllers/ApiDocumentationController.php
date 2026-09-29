<?php

namespace App\Http\Controllers;

use App\Services\ApiDocumentation\ApiDocumentationRenderer;
use App\Services\ApiDocumentation\TenantDocumentationContext;
use Illuminate\View\View;
use InvalidArgumentException;

class ApiDocumentationController extends Controller
{
    public function __invoke(ApiDocumentationRenderer $renderer): View
    {
        $tenantSlug = request()->query('tenant');
        $error = null;
        $context = null;

        if (is_string($tenantSlug) && trim($tenantSlug) !== '') {
            try {
                $context = TenantDocumentationContext::fromSlug($tenantSlug);
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
        }

        return view('docs.api', [
            'documents' => $renderer->render($context),
            'tenantSlug' => $context?->slug ?? $tenantSlug,
            'error' => $error,
        ]);
    }
}
