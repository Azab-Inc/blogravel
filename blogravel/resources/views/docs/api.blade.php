<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Blogravel API documentation</title>
        @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/docs.js'])
    </head>
    <body class="docs-page">
        <header class="docs-topbar">
            <a class="docs-brand" href="{{ route('docs.api') }}">
                <span class="docs-mark">B</span>
                <span>Blogravel <span class="docs-brand-suffix">/ API</span></span>
            </a>
            @if ($tenantSlug)
                <div class="docs-tenant"><span>tenant</span><strong>{{ $tenantSlug }}</strong></div>
            @endif
        </header>

        <div class="docs-shell">
            <aside class="docs-nav" aria-label="API documentation sections">
                <small>On this page</small>
                @foreach ($documents as $document)
                    <a href="#{{ $document['slug'] }}">{{ $document['title'] }}</a>
                @endforeach
            </aside>

            <main class="docs-main">
                <div class="docs-intro">
                    <div class="docs-eyebrow">Developer documentation</div>
                    <h1>Build with the Blogravel API.</h1>
                    <p>Clear, copy-ready endpoints for publishing travel content, managing authors, and connecting your tenant.</p>
                </div>

                @if ($documents->isEmpty())
                    <section class="docs-section docs-empty" aria-labelledby="quick-start-heading">
                        <h2 id="quick-start-heading">Quick start</h2>
                        @include('docs.partials.tenant-form')
                        @if ($error)
                            <p class="docs-error" role="alert">{{ $error }}</p>
                        @else
                            <p>Enter your tenant slug to view generated documentation and copy-ready examples.</p>
                        @endif
                    </section>
                @else
                    @include('docs.partials.content', ['documents' => $documents])
                @endif
            </main>
        </div>
    </body>
</html>
