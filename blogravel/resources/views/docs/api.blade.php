<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Blogravel API documentation</title>
        @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/docs.js'])
        <style>
            :root {
                color-scheme: light;
                font-family: Inter, ui-sans-serif, system-ui, sans-serif;
                line-height: 1.6;
                color: #102542;
                background: #fff8ed;
            }

            body { margin: 0; }
            a { color: #102542; }
            code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
            pre {
                position: relative;
                overflow-x: auto;
                padding: 3rem 1rem 1rem;
                border-radius: 0.5rem;
                color: #fff8ed;
                background: #102542;
            }
            .code-copy-button {
                position: absolute;
                top: 0.75rem;
                right: 0.75rem;
                padding: 0.25rem 0.6rem;
                border: 1px solid #eadfce;
                border-radius: 0.25rem;
                color: #fff8ed;
                background: transparent;
                cursor: pointer;
            }
            :not(pre) > code {
                padding: 0.1rem 0.3rem;
                border-radius: 0.25rem;
                background: #eadfce;
            }
            .docs-shell { display: grid; grid-template-columns: 15rem minmax(0, 52rem); gap: 3rem; max-width: 72rem; margin: 0 auto; padding: 3rem 1.5rem; }
            .docs-nav { position: sticky; top: 1.5rem; align-self: start; }
            .docs-nav ul { padding: 0; list-style: none; }
            .docs-nav li { margin: 0.5rem 0; }
            .docs-section { padding-bottom: 3rem; margin-bottom: 3rem; border-bottom: 1px solid #c9b99f; }
            .docs-section:last-child { border-bottom: 0; }
            @media (max-width: 760px) {
                .docs-shell { display: block; padding: 2rem 1rem; }
                .docs-nav { position: static; margin-bottom: 2rem; }
            }
        </style>
    </head>
    <body>
        <div class="docs-shell">
            <aside class="docs-nav" aria-label="API documentation sections">
                <a href="{{ route('docs.api') }}"><strong>Blogravel API</strong></a>
                <ul>
                    @foreach ($documents as $document)
                        <li><a href="#{{ $document['slug'] }}">{{ $document['title'] }}</a></li>
                    @endforeach
                </ul>
            </aside>

            <main>
                <section class="docs-tenant-picker" aria-labelledby="tenant-picker-heading">
                    <h1 id="tenant-picker-heading">Blogravel API</h1>
                    <p>Enter your tenant slug to generate copy-ready API examples for that tenant.</p>
                    <form method="GET" action="{{ route('docs.api') }}">
                        <label for="tenant">Tenant slug</label>
                        <div>
                            <input id="tenant" name="tenant" value="{{ $tenantSlug }}" placeholder="acmeio" required pattern="[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?">
                            <button type="submit">Generate examples</button>
                        </div>
                    </form>
                    @if ($error)
                        <p role="alert">{{ $error }}</p>
                    @elseif ($documents->isEmpty())
                        <p>Enter your tenant slug to view generated documentation.</p>
                    @endif
                </section>

                @include('docs.partials.content', ['documents' => $documents])
            </main>
        </div>
    </body>
</html>
