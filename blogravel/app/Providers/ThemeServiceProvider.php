<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    private ?string $activeTheme = null;

    public function boot(): void
    {
        $this->registerBasePaths();

        View::creator('theme.*', function ($view) {
            $this->loadThemeAssets($view);
        });
    }

    protected function registerBasePaths(): void
    {
        $basePath = base_path(config('theme.themes_dir', 'resources/themes'));
        $baseTheme = config('theme.base', 'base');
        $baseDir = $basePath.DIRECTORY_SEPARATOR.$baseTheme;

        $baseComponents = $baseDir.DIRECTORY_SEPARATOR.'components';

        if (is_dir($baseComponents)) {
            View::addNamespace('theme-base', $baseComponents);
        }
    }

    public function setActiveTheme(string $theme): void
    {
        $this->activeTheme = $theme;

        $basePath = base_path(config('theme.themes_dir', 'resources/themes'));
        $themeDir = $basePath.DIRECTORY_SEPARATOR.$theme.DIRECTORY_SEPARATOR.'components';

        if ($theme !== config('theme.base', 'base') && is_dir($themeDir)) {
            View::addNamespace("theme-{$theme}", $themeDir);
        }
    }

    public function getActiveTheme(): ?string
    {
        return $this->activeTheme;
    }

    public function getThemePath(string $theme, string $component): ?string
    {
        $basePath = base_path(config('theme.themes_dir', 'resources/themes'));
        $themeDir = $basePath.DIRECTORY_SEPARATOR.$theme;

        $path = $themeDir.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.$component.'.blade.php';

        return file_exists($path) ? $path : null;
    }

    public function getThemeAssets(string $theme): array
    {
        $basePath = base_path(config('theme.themes_dir', 'resources/themes'));
        $themeDir = $basePath.DIRECTORY_SEPARATOR.$theme;
        $configPath = $themeDir.DIRECTORY_SEPARATOR.'theme.json';

        if (! file_exists($configPath)) {
            return ['styles' => [], 'scripts' => []];
        }

        $config = json_decode(file_get_contents($configPath), true);

        $assets = ['styles' => [], 'scripts' => []];

        foreach ($config['styles'] ?? [] as $style) {
            $assets['styles'][] = "/themes/{$theme}/{$style}";
        }

        foreach ($config['scripts'] ?? [] as $script) {
            $assets['scripts'][] = "/themes/{$theme}/{$script}";
        }

        return $assets;
    }

    public function getAvailableThemes(): array
    {
        $basePath = base_path(config('theme.themes_dir', 'resources/themes'));

        if (! is_dir($basePath)) {
            return [];
        }

        $themes = [];
        $baseTheme = config('theme.base', 'base');

        foreach (scandir($basePath) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (is_dir($basePath.DIRECTORY_SEPARATOR.$item)) {
                $themes[] = [
                    'name' => $item,
                    'is_base' => $item === $baseTheme,
                ];
            }
        }

        return $themes;
    }

    private function loadThemeAssets($view): void
    {
        // Assets are loaded via the layout component
    }
}
