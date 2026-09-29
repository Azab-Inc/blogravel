<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Blogravel API documentation</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
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
                overflow-x: auto;
                padding: 1rem;
                border-radius: 0.5rem;
                color: #fff8ed;
                background: #102542;
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
                @foreach ($documents as $document)
                    <section id="{{ $document['slug'] }}" class="docs-section">
                        {!! $document['html'] !!}
                    </section>
                @endforeach
            </main>
        </div>
    </body>
</html>
