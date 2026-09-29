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
                font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
                color: #172033;
                background: #f7f8fa;
            }

            * { box-sizing: border-box; }
            body { margin: 0; }
            a { color: inherit; }
            code, .docs-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }

            .docs-topbar {
                position: sticky;
                top: 0;
                z-index: 4;
                display: flex;
                align-items: center;
                justify-content: space-between;
                min-height: 68px;
                padding: 0 2rem;
                border-bottom: 1px solid #e4e8ef;
                background: rgba(255, 255, 255, .86);
                backdrop-filter: blur(14px);
            }

            .docs-brand {
                display: inline-flex;
                align-items: center;
                gap: .7rem;
                color: #172033;
                font-weight: 800;
                letter-spacing: -.04em;
                text-decoration: none;
            }

            .docs-mark {
                display: grid;
                place-items: center;
                width: 27px;
                height: 27px;
                border-radius: 8px;
                color: white;
                background: #3156db;
                font-size: .8rem;
            }

            .docs-tenant {
                display: inline-flex;
                align-items: center;
                gap: .6rem;
                color: #8b96a8;
                font: .7rem ui-monospace, SFMono-Regular, Menlo, monospace;
            }

            .docs-tenant strong { color: #253452; font-weight: 500; }
            .docs-shell { display: grid; grid-template-columns: 220px minmax(0, 770px); gap: 5rem; max-width: 1120px; margin: 0 auto; padding: 5.5rem 2rem; }
            .docs-nav { position: sticky; top: 100px; align-self: start; }
            .docs-nav small { display: block; margin-bottom: 1rem; color: #98a3b4; font: .66rem ui-monospace, SFMono-Regular, Menlo, monospace; text-transform: uppercase; letter-spacing: .13em; }
            .docs-nav a { display: block; padding: .45rem .7rem; color: #7a869a; font-size: .82rem; text-decoration: none; border-left: 1px solid transparent; }
            .docs-nav a:hover, .docs-nav a:first-of-type { color: #3156db; border-left-color: #3156db; background: #edf1ff; }
            .docs-main { min-width: 0; }
            .docs-intro { margin-bottom: 4rem; }
            .docs-eyebrow { margin-bottom: 1rem; color: #3156db; font: .7rem ui-monospace, SFMono-Regular, Menlo, monospace; text-transform: uppercase; letter-spacing: .16em; }
            .docs-intro h1 { margin: 0; color: #13203a; font-size: clamp(2.8rem, 6vw, 5.2rem); letter-spacing: -.08em; line-height: .98; }
            .docs-intro p { max-width: 580px; margin: 1.5rem 0 0; color: #778399; font-size: 1.08rem; }
            .docs-section { padding-bottom: 5rem; margin: 0 0 5rem; border-bottom: 1px solid #e1e6ee; }
            .docs-section:last-child { border-bottom: 0; }
            .docs-section h1 { max-width: 760px; margin: 0 0 1.5rem; color: #13203a; font-size: 2.3rem; line-height: 1.02; letter-spacing: -.065em; }
            .docs-section h2 { margin: 3.5rem 0 1rem; color: #1d2b45; font-size: 1.45rem; letter-spacing: -.03em; }
            .docs-section h3 { margin: 2rem 0 .75rem; color: #283754; }
            .docs-section p, .docs-section li { color: #637089; line-height: 1.7; }
            .docs-section a { color: #3156db; text-decoration-thickness: 1px; text-underline-offset: 3px; }
            .docs-section table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; font-size: .92rem; }
            .docs-section th, .docs-section td { padding: .8rem .7rem; border-bottom: 1px solid #e2e7ef; text-align: left; }
            .docs-section th { color: #35425b; font-size: .76rem; text-transform: uppercase; letter-spacing: .08em; }
            .docs-section :not(pre) > code { padding: .1rem .3rem; border: 1px solid #dce3ee; border-radius: 5px; color: #3156db; background: #eef2ff; }
            pre { position: relative; overflow-x: auto; margin: 1.6rem 0; padding: 3.5rem 1.25rem 1.25rem; border: 1px solid #273149; border-radius: 14px; color: #dbe7f4; background: #111827; box-shadow: 0 16px 40px rgba(21, 31, 51, .12); }
            pre code { color: inherit; }
            .docs-code-block .code-copy-button { border-color: #46536d; border-radius: 7px; color: #c8d4e6; background: #1c2638; }
            .docs-code-block .code-copy-button:hover { background: #2b3850; }
            .docs-tenant-form { max-width: 560px; margin: 1.5rem 0 2rem; padding: 1.1rem 1.2rem; border: 1px solid #dce3ee; border-radius: 14px; background: #fff; box-shadow: 0 10px 30px rgba(30, 43, 70, .06); }
            .docs-tenant-form label { display: block; margin-bottom: .5rem; color: #35425b; font-size: .78rem; font-weight: 700; }
            .docs-tenant-form-row { display: flex; gap: .55rem; }
            .docs-tenant-form input { min-width: 0; flex: 1; padding: .7rem .8rem; border: 1px solid #dce3ee; border-radius: 8px; color: #253452; background: #fbfcfe; font: .76rem ui-monospace, SFMono-Regular, Menlo, monospace; }
            .docs-tenant-form input:focus { outline: 3px solid rgba(49, 86, 219, .16); border-color: #3156db; }
            .docs-tenant-form button { border: 0; border-radius: 8px; padding: .7rem 1rem; color: #fff; background: #3156db; font-weight: 700; cursor: pointer; }
            .docs-tenant-form button:hover { background: #2748bd; }
            .docs-tenant-form button:active { transform: translateY(1px); }
            .docs-error { margin: .8rem 0 0; color: #b42318 !important; font-size: .85rem; }
            .docs-empty { padding-bottom: 4rem; }

            @media (max-width: 800px) {
                .docs-topbar { padding: 0 1rem; }
                .docs-shell { display: block; padding: 3rem 1rem 7rem; }
                .docs-nav { position: static; margin-bottom: 3rem; }
                .docs-tenant { display: none; }
            }

            @media (max-width: 520px) {
                .docs-tenant-form-row { display: block; }
                .docs-tenant-form input, .docs-tenant-form button { width: 100%; }
                .docs-tenant-form button { margin-top: .55rem; }
            }
        </style>
    </head>
    <body>
        <header class="docs-topbar">
            <a class="docs-brand" href="{{ route('docs.api') }}">
                <span class="docs-mark">B</span>
                <span>Blogravel <span style="font-weight: 500; color: #8b96a8;">/ API</span></span>
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
