<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantHostResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResolveTheme
{
    public function __construct(private TenantHostResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant) {
            $activeTheme = $this->getActiveTheme($tenant);
            $this->registerThemeNamespaces($activeTheme);

            $request->attributes->set('tenant', $tenant);
            $request->attributes->set('active_theme', $activeTheme);
        }

        return $next($request);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $attributeTenant = $request->attributes->get('tenant');
        if ($attributeTenant instanceof Tenant) {
            return $attributeTenant;
        }

        $routeTenant = $request->route('tenant');
        if ($routeTenant instanceof Tenant) {
            return $routeTenant;
        }

        $pathSlug = $request->route('tenantSlug');
        if (is_string($pathSlug)) {
            if (! $this->resolver->isLocalHost($request->getHost())) {
                abort(404, 'Tenant not found.');
            }

            return $this->resolver->resolveSlug($pathSlug);
        }

        $host = strtolower($request->getHost());
        if (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }

        $tenant = Tenant::where('domain', $host)->first();
        if ($tenant) {
            return $tenant;
        }

        $param = $request->input('tenant') ?? $routeTenant;
        if (is_string($param) && $param !== '') {
            $tenantQuery = Tenant::where('domain', $param);

            if (Str::isUuid($param)) {
                $tenantQuery->orWhere('id', $param);
            }

            return $tenantQuery->first();
        }

        return null;
    }

    private function getActiveTheme(Tenant $tenant): string
    {
        $theme = $tenant->settings
            ->where('key', 'active_theme')
            ->first()?->value;

        return $theme ?: config('theme.default', 'base');
    }

    private function registerThemeNamespaces(string $activeTheme): void
    {
        $basePath = base_path(config('theme.themes_dir', 'resources/themes'));
        $baseTheme = config('theme.base', 'base');

        $baseDir = $basePath.DIRECTORY_SEPARATOR.$baseTheme;
        $themeDir = $basePath.DIRECTORY_SEPARATOR.$activeTheme;

        if ($activeTheme !== $baseTheme && is_dir($themeDir.DIRECTORY_SEPARATOR.'components')) {
            Blade::anonymousComponentPath($themeDir.DIRECTORY_SEPARATOR.'components', 'theme');
            View::addNamespace('theme', $themeDir.DIRECTORY_SEPARATOR.'components');
        }

        if (is_dir($baseDir.DIRECTORY_SEPARATOR.'components')) {
            Blade::anonymousComponentPath($baseDir.DIRECTORY_SEPARATOR.'components', 'theme');
            View::addNamespace('theme', $baseDir.DIRECTORY_SEPARATOR.'components');
        }
    }
}
