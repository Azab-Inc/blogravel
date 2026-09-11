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
        :root { --bg: #fff; --fg: #1a1a1a; --accent: #2563eb; --muted: #6b7280; --border: #e5e7eb; --surface: #f9fafb; }
        @media (prefers-color-scheme: dark) { :root { --bg: #111827; --fg: #f9fafb; --accent: #60a5fa; --muted: #9ca3af; --border: #374151; --surface: #1f2937; } }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg); color: var(--fg); line-height: 1.6; }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .container { max-width: 800px; margin: 0 auto; padding: 0 1rem; }
        header { border-bottom: 1px solid var(--border); padding: 1rem 0; margin-bottom: 2rem; }
        header .container { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; }
        header h1 { font-size: 1.25rem; }
        header nav { display: flex; gap: 1rem; font-size: 0.875rem; }
        footer { border-top: 1px solid var(--border); padding: 1.5rem 0; margin-top: 3rem; text-align: center; font-size: 0.875rem; color: var(--muted); }
        .post-card { border: 1px solid var(--border); border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem; }
        .post-card h2 { font-size: 1.25rem; margin-bottom: 0.25rem; }
        .post-meta { font-size: 0.875rem; color: var(--muted); margin-bottom: 0.5rem; }
        .post-excerpt { margin-bottom: 0.5rem; }
        .tag { display: inline-block; background: var(--surface); border: 1px solid var(--border); padding: 0.125rem 0.5rem; border-radius: 4px; font-size: 0.75rem; margin-right: 0.25rem; }
        .btn { display: inline-block; background: var(--accent); color: #fff; padding: 0.5rem 1rem; border-radius: 6px; border: none; cursor: pointer; font-size: 0.875rem; }
        .btn:hover { opacity: 0.9; text-decoration: none; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem; }
        .form-group input, .form-group textarea { width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 6px; background: var(--bg); color: var(--fg); font-size: 1rem; }
        .form-group textarea { min-height: 150px; resize: vertical; }
        .pagination { display: flex; gap: 0.5rem; justify-content: center; margin-top: 2rem; }
        .pagination a, .pagination span { padding: 0.5rem 1rem; border: 1px solid var(--border); border-radius: 6px; }
        .pagination .active { background: var(--accent); color: #fff; border-color: var(--accent); }
        .success { background: #dcfce7; color: #166534; padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .sidebar { margin-top: 2rem; }
        .sidebar h3 { font-size: 1rem; margin-bottom: 0.5rem; }
        .sidebar ul { list-style: none; }
        .sidebar li { padding: 0.25rem 0; }
        @media (max-width: 640px) { .container { padding: 0 0.75rem; } }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1><a href="{{ route('theme.home') }}?tenant={{ $tenant->id }}">{{ $tenant->name ?? 'Blog' }}</a></h1>
            <nav>
                <a href="{{ route('theme.home') }}?tenant={{ $tenant->id }}">Home</a>
                <a href="{{ route('theme.subscribe') }}?tenant={{ $tenant->id }}">Subscribe</a>
                <a href="{{ route('theme.contact') }}?tenant={{ $tenant->id }}">Contact</a>
            </nav>
        </div>
    </header>

    <main class="container">
        {{ $slot }}
    </main>

    <footer>
        <div class="container">
            Powered by <a href="https://blogravel.com">Blogravel</a>
        </div>
    </footer>
</body>
</html>
