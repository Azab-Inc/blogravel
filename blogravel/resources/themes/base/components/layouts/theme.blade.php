<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->name ?? config('app.name') }}</title>
    <meta name="description" content="{{ $tenant->name ?? config('app.name') }} — A Blogravel blog">

    {{-- RSS/Atom auto-discovery --}}
    <link rel="alternate" type="application/rss+xml" title="{{ $tenant->name ?? 'Blog' }} — RSS" href="{{ route('feed.posts', ['posts']) }}?tenant={{ $tenant->id }}">
    <link rel="alternate" type="application/atom+xml" title="{{ $tenant->name ?? 'Blog' }} — Atom" href="{{ route('feed.posts', ['posts']) }}?format=atom&tenant={{ $tenant->id }}">
    <link rel="alternate" type="application/feed+json" title="{{ $tenant->name ?? 'Blog' }} — JSON Feed" href="{{ route('feed.posts', ['posts']) }}?format=json&tenant={{ $tenant->id }}">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #fff;
            --fg: #1a1a1a;
            --accent: #1d4ed8;
            --button-bg: #1d4ed8;
            --muted: #6b7280;
            --border: #e5e7eb;
            --surface: #f9fafb;
            --radius: 8px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #111827;
                --fg: #f9fafb;
                --accent: #93c5fd;
                --button-bg: #2563eb;
                --muted: #9ca3af;
                --border: #374151;
                --surface: #1f2937;
            }
        }

        html[data-theme="light"] {
            --bg: #fff;
            --fg: #1a1a1a;
            --accent: #1d4ed8;
            --button-bg: #1d4ed8;
            --muted: #6b7280;
            --border: #e5e7eb;
            --surface: #f9fafb;
        }

        html[data-theme="dark"] {
            --bg: #111827;
            --fg: #f9fafb;
            --accent: #93c5fd;
            --button-bg: #2563eb;
            --muted: #9ca3af;
            --border: #374151;
            --surface: #1f2937;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--fg);
            line-height: 1.6;
            font-size: clamp(0.95rem, 0.9rem + 0.25vw, 1rem);
        }

        a { color: var(--accent); text-decoration: underline; text-underline-offset: 0.15em; }
        a:hover { text-decoration: underline; }

        :focus-visible {
            outline: 3px solid var(--accent);
            outline-offset: 3px;
        }

        .skip-link {
            position: absolute;
            left: 1rem;
            top: 0;
            transform: translateY(-150%);
            background: var(--bg);
            color: var(--fg);
            padding: 0.75rem 1rem;
            border: 2px solid var(--accent);
            border-radius: var(--radius);
            z-index: 10;
        }

        .skip-link:focus {
            transform: translateY(1rem);
        }

        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        @media (min-width: 640px) {
            .container { padding: 0 1.5rem; }
        }

        /* Header & Navigation */
        header {
            border-bottom: 1px solid var(--border);
            padding: 1rem 0;
            margin-bottom: 2rem;
        }

        header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        header h1 { font-size: 1.25rem; }

        .nav-toggle {
            display: none;
            background: none;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 0.5rem;
            cursor: pointer;
            color: var(--fg);
            font-size: 1.25rem;
            line-height: 1;
            min-width: 44px;
            min-height: 44px;
            align-items: center;
            justify-content: center;
        }

        .nav-toggle[aria-expanded="true"] { background: var(--surface); }

        .theme-toggle {
            background: none;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            color: var(--fg);
            font-size: 0.875rem;
            min-height: 44px;
        }

        .theme-toggle:hover { background: var(--surface); }

        @media (max-width: 639px) {
            .nav-toggle { display: flex; }

            header nav {
                display: none;
                width: 100%;
                flex-direction: column;
                gap: 0;
                border-top: 1px solid var(--border);
                padding-top: 0.5rem;
                margin-top: 0.5rem;
            }

            header nav.open { display: flex; }

            header nav a {
                padding: 0.75rem 0;
                border-bottom: 1px solid var(--border);
                min-height: 44px;
                display: flex;
                align-items: center;
            }

            header nav a:last-child { border-bottom: none; }
        }

        @media (min-width: 640px) {
            header nav {
                display: flex;
                gap: 1rem;
                font-size: 0.875rem;
            }
        }

        /* Footer */
        footer {
            border-top: 1px solid var(--border);
            padding: 1.5rem 0;
            margin-top: 3rem;
            text-align: center;
            font-size: 0.875rem;
            color: var(--muted);
        }

        /* Post List Layout */
        .post-list {
            display: grid;
            gap: 1.5rem;
        }

        /* Post Card */
        .post-card {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem;
            box-shadow: var(--shadow-sm);
            transition: box-shadow 0.15s ease;
        }

        .post-card:hover { box-shadow: var(--shadow-md); }

        .post-card h2 {
            font-size: clamp(1.1rem, 1rem + 0.5vw, 1.25rem);
            margin-bottom: 0.25rem;
            line-height: 1.3;
        }

        .post-meta {
            font-size: 0.875rem;
            color: var(--muted);
            margin-bottom: 0.5rem;
        }

        .post-excerpt {
            margin-bottom: 0.5rem;
            color: var(--fg);
        }

        /* Post Content (single post page) */
        .post-content {
            margin: 1.5rem 0;
            line-height: 1.8;
        }

        .post-content img {
            max-width: 100%;
            height: auto;
            border-radius: var(--radius);
        }

        .post-content h2,
        .post-content h3 {
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .post-content p {
            margin-bottom: 1rem;
        }

        .post-tags {
            margin-top: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        /* Tags */
        .tag {
            display: inline-block;
            background: var(--surface);
            border: 1px solid var(--border);
            padding: 0.25rem 0.625rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }

        /* Buttons */
        .btn {
            display: inline-block;
            background: var(--button-bg);
            color: #fff;
            padding: 0.625rem 1.25rem;
            border-radius: var(--radius);
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            min-height: 44px;
            transition: opacity 0.15s ease;
        }

        .btn:hover { opacity: 0.9; text-decoration: none; }

        /* Forms */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.375rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.625rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--bg);
            color: var(--fg);
            font-size: 1rem;
            min-height: 44px;
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
            border-color: var(--accent);
        }

        .form-error {
            color: #dc2626;
            font-size: 0.875rem;
            margin-top: 0.375rem;
        }

        @media (prefers-color-scheme: dark) {
            html:not([data-theme]) .form-error,
            html[data-theme="dark"] .form-error { color: #fca5a5; }
        }

        /* Pagination */
        .pagination {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            min-width: 44px;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .pagination .active {
            background: var(--button-bg);
            color: #fff;
            border-color: var(--button-bg);
        }

        /* Success Alert */
        .success {
            background: #dcfce7;
            color: #166534;
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
        }

        @media (prefers-color-scheme: dark) {
            html:not([data-theme]) .success,
            html[data-theme="dark"] .success {
                background: #064e3b;
                color: #a7f3d0;
            }
        }

        /* Sidebar */
        .sidebar {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        .sidebar h3 {
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .sidebar ul { list-style: none; }

        .sidebar li {
            padding: 0.375rem 0;
        }

        .sidebar a {
            display: flex;
            justify-content: space-between;
            padding: 0.25rem 0;
            min-height: 44px;
            align-items: center;
        }

        /* Back Link */
        .back-link {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }

        /* Muted Text */
        .muted {
            color: var(--muted);
            margin-bottom: 1.5rem;
        }

        /* Responsive: Tablet+ sidebar layout */
        @media (min-width: 640px) {
            .home-layout {
                display: grid;
                grid-template-columns: 1fr 200px;
                gap: 2rem;
                align-items: start;
            }

            .home-layout .sidebar {
                position: sticky;
                top: 1rem;
                margin-top: 0;
                padding-top: 0;
                border-top: none;
            }
        }
    </style>
</head>
<body>
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

    <footer>
        <div class="container">
            Powered by <a href="https://blogravel.com">Blogravel</a>
        </div>
    </footer>
    <script>
        const themeToggle = document.querySelector('.theme-toggle');
        const savedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        if (savedTheme === 'light' || savedTheme === 'dark') {
            document.documentElement.dataset.theme = savedTheme;
        }

        const updateThemeToggle = () => {
            const activeTheme = document.documentElement.dataset.theme || (systemPrefersDark ? 'dark' : 'light');
            const nextTheme = activeTheme === 'dark' ? 'light' : 'dark';

            themeToggle?.setAttribute('aria-label', `Switch to ${nextTheme} mode`);
            if (themeToggle) {
                themeToggle.textContent = `${nextTheme.charAt(0).toUpperCase()}${nextTheme.slice(1)} mode`;
            }
        };

        themeToggle?.addEventListener('click', () => {
            const activeTheme = document.documentElement.dataset.theme || (systemPrefersDark ? 'dark' : 'light');
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
</body>
</html>
