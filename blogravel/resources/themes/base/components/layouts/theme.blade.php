<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->name ?? config('app.name') }}</title>
    <meta name="description" content="{{ $tenant->name ?? config('app.name') }} — A Blogravel blog">
    <script>
        const savedTheme = localStorage.getItem('theme');
        document.documentElement.dataset.theme = savedTheme === 'dark' ? 'dark' : 'light';
    </script>

    @php
        $themeAssets = app()->getProvider(\App\Providers\ThemeServiceProvider::class)->getThemeAssets(
            request()->attributes->get('active_theme', config('theme.default', 'base')),
        );
    @endphp

    @foreach ($themeAssets['styles'] as $style)
        <link rel="stylesheet" href="{{ $style }}">
    @endforeach

    {{-- RSS/Atom auto-discovery --}}
    <link rel="alternate" type="application/rss+xml" title="{{ $tenant->name ?? 'Blog' }} — RSS" href="{{ route('feed.posts', ['posts']) }}?tenant={{ $tenant->id }}">
    <link rel="alternate" type="application/atom+xml" title="{{ $tenant->name ?? 'Blog' }} — Atom" href="{{ route('feed.posts', ['posts']) }}?format=atom&tenant={{ $tenant->id }}">
    <link rel="alternate" type="application/feed+json" title="{{ $tenant->name ?? 'Blog' }} — JSON Feed" href="{{ route('feed.posts', ['posts']) }}?format=json&tenant={{ $tenant->id }}">
</head>
<body class="base-theme">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <header>
        <div class="container">
            <h1><a href="{{ request()->attributes->get('tenant_path_slug') ? route('theme.local.home', ['tenantSlug' => request()->attributes->get('tenant_path_slug')]) : route('theme.home').'?tenant='.$tenant->id }}">{{ $tenant->name ?? 'Blog' }}</a></h1>
            <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation" aria-label="Toggle navigation">☰</button>
            <button class="theme-toggle" type="button" aria-label="Switch to dark mode">Dark mode</button>
            <nav id="primary-navigation" aria-label="Primary navigation">
                <a href="{{ request()->attributes->get('tenant_path_slug') ? route('theme.local.home', ['tenantSlug' => request()->attributes->get('tenant_path_slug')]) : route('theme.home').'?tenant='.$tenant->id }}">Home</a>
                <a href="{{ request()->attributes->get('tenant_path_slug') ? route('theme.local.subscribe', ['tenantSlug' => request()->attributes->get('tenant_path_slug')]) : route('theme.subscribe').'?tenant='.$tenant->id }}">Subscribe</a>
                <a href="{{ request()->attributes->get('tenant_path_slug') ? route('theme.local.contact', ['tenantSlug' => request()->attributes->get('tenant_path_slug')]) : route('theme.contact').'?tenant='.$tenant->id }}">Contact</a>
            </nav>
        </div>
    </header>

    <main id="main-content" class="container" tabindex="-1">
        {{ $slot }}
    </main>

    <div id="toast-region" class="toast-region" tabindex="-1" aria-label="Notifications"></div>

    <footer>
        <div class="container">
            Powered by <a href="https://blogravel.com">Blogravel</a>
        </div>
    </footer>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        const themeToggle = document.querySelector('.theme-toggle');

        const updateThemeToggle = () => {
            const activeTheme = document.documentElement.dataset.theme;
            const nextTheme = activeTheme === 'dark' ? 'light' : 'dark';

            themeToggle?.setAttribute('aria-label', `Switch to ${nextTheme} mode`);
            if (themeToggle) {
                themeToggle.textContent = `${nextTheme.charAt(0).toUpperCase()}${nextTheme.slice(1)} mode`;
            }
        };

        themeToggle?.addEventListener('click', () => {
            const activeTheme = document.documentElement.dataset.theme;
            const nextTheme = activeTheme === 'dark' ? 'light' : 'dark';

            document.documentElement.dataset.theme = nextTheme;
            localStorage.setItem('theme', nextTheme);
            updateThemeToggle();
        });

        updateThemeToggle();

        const navToggle = document.querySelector('.nav-toggle');
        const primaryNavigation = document.querySelector('#primary-navigation');

        navToggle?.addEventListener('click', () => {
            const isExpanded = navToggle.getAttribute('aria-expanded') === 'true';
            navToggle.setAttribute('aria-expanded', String(!isExpanded));
            primaryNavigation?.classList.toggle('open', !isExpanded);
        });

        navToggle?.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && navToggle.getAttribute('aria-expanded') === 'true') {
                navToggle.setAttribute('aria-expanded', 'false');
                primaryNavigation?.classList.remove('open');
                navToggle.focus();
            }
        });
    </script>
    @foreach ($themeAssets['scripts'] as $script)
        <script src="{{ $script }}"></script>
    @endforeach
</body>
</html>
