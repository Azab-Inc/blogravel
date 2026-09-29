<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Blogravel API documentation</title>
        @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/docs.js'])
        <style>
            @import url('https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap');

            :root {
                color-scheme: light;
                font-family: Manrope, ui-sans-serif, system-ui, sans-serif;
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

            /* Throwaway prototype styles. Remove after choosing a direction. */
            .prototype-page { min-height: 100vh; background: #f7f8fa; color: #172033; }
            .prototype-page * { box-sizing: border-box; }
            .prototype-page code, .prototype-page .mono { font-family: 'DM Mono', ui-monospace, monospace; }
            .prototype-page pre { padding: 3.5rem 1.25rem 1.25rem; border: 1px solid #273149; border-radius: 14px; background: #111827; color: #dbe7f4; box-shadow: 0 16px 40px rgba(21, 31, 51, .12); }
            .prototype-page pre code { color: inherit; }
            .prototype-page .docs-code-block .code-copy-button { border-color: #46536d; color: #c8d4e6; border-radius: 7px; background: #1c2638; }
            .prototype-page .docs-code-block .code-copy-button:hover { background: #2b3850; }
            .prototype-page .docs-section { border-bottom: 0; margin: 0; padding: 0 0 5rem; }
            .prototype-page .docs-section h1 { max-width: 760px; margin: 0 0 1.5rem; font-size: clamp(2.3rem, 5vw, 4.5rem); line-height: 1.02; letter-spacing: -.065em; color: #13203a; }
            .prototype-page .docs-section h2 { margin: 3.5rem 0 1rem; font-size: 1.45rem; letter-spacing: -.03em; color: #1d2b45; }
            .prototype-page .docs-section h3 { margin: 2rem 0 .75rem; color: #283754; }
            .prototype-page .docs-section p, .prototype-page .docs-section li { color: #637089; }
            .prototype-page .docs-section a { color: #3156db; text-decoration-thickness: 1px; text-underline-offset: 3px; }
            .prototype-page .docs-section table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; font-size: .92rem; }
            .prototype-page .docs-section th, .prototype-page .docs-section td { padding: .8rem .7rem; border-bottom: 1px solid #e2e7ef; text-align: left; }
            .prototype-page .docs-section th { color: #35425b; font-size: .76rem; text-transform: uppercase; letter-spacing: .08em; }
            .prototype-page .docs-section :not(pre) > code { border: 1px solid #dce3ee; color: #3156db; background: #eef2ff; }
            .prototype-topbar { display: flex; align-items: center; justify-content: space-between; min-height: 68px; padding: 0 2rem; border-bottom: 1px solid #e4e8ef; background: rgba(255,255,255,.84); backdrop-filter: blur(14px); position: sticky; top: 0; z-index: 4; }
            .prototype-brand { display: inline-flex; align-items: center; gap: .7rem; color: #172033; font-weight: 800; letter-spacing: -.04em; text-decoration: none; }
            .prototype-mark { display: grid; place-items: center; width: 27px; height: 27px; border-radius: 8px; color: white; background: #3156db; font-size: .8rem; }
            .prototype-tenant { display: inline-flex; align-items: center; gap: .6rem; color: #6a7588; font: .7rem 'DM Mono', monospace; }
            .prototype-tenant strong { color: #253452; font-weight: 500; }
            .prototype-a-shell { display: grid; grid-template-columns: 220px minmax(0, 770px); gap: 5rem; max-width: 1120px; margin: 0 auto; padding: 5.5rem 2rem; }
            .prototype-a-nav { position: sticky; top: 100px; align-self: start; }
            .prototype-a-nav small, .prototype-b-nav small { display: block; margin-bottom: 1rem; color: #98a3b4; font: .66rem 'DM Mono', monospace; text-transform: uppercase; letter-spacing: .13em; }
            .prototype-a-nav a { display: block; padding: .45rem .7rem; color: #7a869a; font-size: .82rem; text-decoration: none; border-left: 1px solid transparent; }
            .prototype-a-nav a:hover, .prototype-a-nav a:first-of-type { color: #3156db; border-left-color: #3156db; background: #edf1ff; }
            .prototype-a-intro { margin-bottom: 4rem; }
            .prototype-a-intro .eyebrow, .prototype-b-hero .eyebrow { margin-bottom: 1rem; color: #3156db; font: .7rem 'DM Mono', monospace; text-transform: uppercase; letter-spacing: .16em; }
            .prototype-a-intro h1, .prototype-b-hero h1 { margin: 0; color: #13203a; font-size: clamp(2.8rem, 6vw, 5.2rem); letter-spacing: -.08em; line-height: .98; }
            .prototype-a-intro p, .prototype-b-hero p { max-width: 580px; margin: 1.5rem 0 0; color: #778399; font-size: 1.08rem; }
            .prototype-b-shell { max-width: 1400px; margin: 0 auto; padding: 4.5rem 2.5rem 7rem; }
            .prototype-b-hero { display: grid; grid-template-columns: minmax(0, 1fr) 310px; gap: 5rem; align-items: end; padding: 2rem 0 5rem; border-bottom: 1px solid #dfe5ed; }
            .prototype-b-hero .tenant-card { padding: 1.2rem; border: 1px solid #dce3ee; border-radius: 14px; background: white; box-shadow: 0 10px 30px rgba(30, 43, 70, .06); }
            .prototype-b-hero .tenant-card label { display: block; margin-bottom: .5rem; color: #6c7890; font-size: .76rem; font-weight: 700; }
            .prototype-b-hero .tenant-card form { display: flex; gap: .5rem; }
            .prototype-b-hero .tenant-card input { min-width: 0; flex: 1; padding: .65rem .75rem; border: 1px solid #dce3ee; border-radius: 8px; font: .75rem 'DM Mono', monospace; }
            .prototype-b-hero .tenant-card button { border: 0; border-radius: 8px; padding: .65rem .8rem; color: white; background: #3156db; font-weight: 700; cursor: pointer; }
            .prototype-b-content { display: grid; grid-template-columns: minmax(0, 1fr) 230px; gap: 4rem; padding-top: 4rem; }
            .prototype-b-nav { position: sticky; top: 100px; align-self: start; order: 2; }
            .prototype-b-nav a { display: block; padding: .35rem 0; color: #758198; font-size: .76rem; text-decoration: none; }
            .prototype-b-nav a:hover { color: #3156db; }
            .prototype-b-main .docs-section { padding-bottom: 4rem; margin-bottom: 4rem; border-bottom: 1px solid #e1e6ee; }
            .prototype-b-main .docs-section:last-child { border-bottom: 0; }
            .prototype-b-main .docs-section h1 { font-size: 2.3rem; }
            .prototype-b-main .docs-section h2 { font-size: 1.25rem; }
            .prototype-b-main .docs-section pre { margin: 1.6rem 0; }
            .prototype-c-page { background: #0d1117; color: #b9c4d2; }
            .prototype-c-page .prototype-topbar { border-color: #202938; background: rgba(13,17,23,.88); }
            .prototype-c-page .prototype-brand, .prototype-c-page .prototype-tenant strong { color: #e8edf4; }
            .prototype-c-page .prototype-mark { color: #07110f; background: #6ee7b7; }
            .prototype-c-shell { display: grid; grid-template-columns: 260px minmax(0, 850px); gap: 4rem; max-width: 1250px; margin: 0 auto; padding: 4rem 2rem 8rem; }
            .prototype-c-nav { position: sticky; top: 100px; align-self: start; padding: 1.2rem; border: 1px solid #202938; border-radius: 12px; background: #121923; }
            .prototype-c-nav small { display: block; margin-bottom: 1rem; color: #6ee7b7; font: .65rem 'DM Mono', monospace; text-transform: uppercase; letter-spacing: .14em; }
            .prototype-c-nav a { display: block; padding: .48rem .6rem; color: #77859a; font: .76rem 'DM Mono', monospace; text-decoration: none; border-radius: 6px; }
            .prototype-c-nav a:hover, .prototype-c-nav a:first-of-type { color: #d9fff0; background: #1a2a2b; }
            .prototype-c-main .docs-section { padding-bottom: 4rem; margin-bottom: 4rem; border-bottom: 1px solid #202938; }
            .prototype-c-main .docs-section:last-child { border-bottom: 0; }
            .prototype-c-main .docs-section h1 { color: #f1f5f9; font-size: clamp(2.6rem, 5vw, 4.8rem); }
            .prototype-c-main .docs-section h2 { color: #e0e8f1; font: 500 1.3rem 'DM Mono', monospace; letter-spacing: -.04em; }
            .prototype-c-main .docs-section h3 { color: #cce8dc; }
            .prototype-c-main .docs-section p, .prototype-c-main .docs-section li { color: #8998aa; }
            .prototype-c-main .docs-section a { color: #6ee7b7; }
            .prototype-c-main .docs-section th, .prototype-c-main .docs-section td { border-color: #202938; }
            .prototype-c-main .docs-section th { color: #6ee7b7; }
            .prototype-c-main .docs-section :not(pre) > code { border-color: #28453d; color: #9af2c9; background: #162822; }
            .prototype-c-main pre { border-color: #263345; background: #080c12; box-shadow: none; }
            .prototype-switcher { position: fixed; left: 50%; bottom: 22px; z-index: 10; display: flex; align-items: center; gap: .8rem; transform: translateX(-50%); padding: .55rem .7rem; border: 1px solid #273149; border-radius: 999px; color: #d8e1ee; background: #111827; box-shadow: 0 14px 40px rgba(15,23,42,.26); font: .7rem 'DM Mono', monospace; }
            .prototype-switcher a { display: grid; place-items: center; width: 26px; height: 26px; border-radius: 50%; color: #c8d4e6; text-decoration: none; background: #1c2638; }
            .prototype-switcher a:hover { background: #3156db; color: white; }
            .prototype-switcher strong { min-width: 140px; text-align: center; font-weight: 500; }
            @media (max-width: 800px) {
                .prototype-topbar { padding: 0 1rem; }
                .prototype-a-shell, .prototype-c-shell { display: block; padding: 3rem 1rem 7rem; }
                .prototype-a-nav, .prototype-c-nav, .prototype-b-nav { position: static; margin-bottom: 3rem; }
                .prototype-b-shell { padding: 2rem 1rem 7rem; }
                .prototype-b-hero, .prototype-b-content { display: block; }
                .prototype-b-hero { padding-bottom: 3rem; }
                .prototype-b-hero .tenant-card { margin-top: 2rem; }
                .prototype-b-nav { padding-top: 2rem; border-top: 1px solid #dfe5ed; }
            }
        </style>
    </head>
    <body class="{{ $prototype ? 'prototype-page prototype-'.$variant.'-page' : '' }}">
        @if ($prototype)
            <header class="prototype-topbar">
                <a class="prototype-brand" href="{{ route('docs.api', array_filter(['tenant' => $tenantSlug, 'prototype' => 1, 'variant' => $variant])) }}">
                    <span class="prototype-mark">B</span>
                    <span>Blogravel <span style="font-weight: 500; color: #8b96a8;">/ API</span></span>
                </a>
                <div class="prototype-tenant"><span>tenant</span><strong>{{ $tenantSlug ?: 'not selected' }}</strong></div>
            </header>
            @if ($variant === 'a')
                <div class="prototype-a-shell">
                    <aside class="prototype-a-nav" aria-label="Prototype A API sections">
                        <small>On this page</small>
                        @foreach ($documents as $document)
                            <a href="#{{ $document['slug'] }}">{{ $document['title'] }}</a>
                        @endforeach
                    </aside>
                    <main>
                        <div class="prototype-a-intro">
                            <div class="eyebrow">Developer documentation</div>
                            <h1>Build with the Blogravel API.</h1>
                            <p>Clear, copy-ready endpoints for publishing travel content, managing authors, and connecting your tenant.</p>
                        </div>
                        @include('docs.partials.content', ['documents' => $documents])
                    </main>
                </div>
            @elseif ($variant === 'b')
                <div class="prototype-b-shell">
                    <section class="prototype-b-hero">
                        <div>
                            <div class="eyebrow">Reference · v1</div>
                            <h1>The API reference<br>for your publication.</h1>
                            <p>Scan the concept, copy the request, ship the integration. Every example is scoped to your tenant.</p>
                        </div>
                        <div class="tenant-card">
                            <label for="tenant">Documentation tenant</label>
                            <form method="GET" action="{{ route('docs.api') }}">
                                <input id="tenant" name="tenant" value="{{ $tenantSlug }}" placeholder="acmeio" required>
                                <button type="submit">Load</button>
                            </form>
                        </div>
                    </section>
                    <div class="prototype-b-content">
                        <main class="prototype-b-main">@include('docs.partials.content', ['documents' => $documents])</main>
                        <aside class="prototype-b-nav" aria-label="Prototype B API sections">
                            <small>Contents</small>
                            @foreach ($documents as $document)
                                <a href="#{{ $document['slug'] }}">{{ $document['title'] }}</a>
                            @endforeach
                        </aside>
                    </div>
                </div>
            @else
                <div class="prototype-c-shell">
                    <aside class="prototype-c-nav" aria-label="Prototype C API sections">
                        <small>~/blogravel/api</small>
                        @foreach ($documents as $document)
                            <a href="#{{ $document['slug'] }}">{{ $document['title'] }}</a>
                        @endforeach
                    </aside>
                    <main class="prototype-c-main">@include('docs.partials.content', ['documents' => $documents])</main>
                </div>
            @endif
            <nav class="prototype-switcher" aria-label="Documentation prototype variants">
                @php
                    $variants = ['a' => 'Tailwind classic', 'b' => 'API reference', 'c' => 'Dark terminal'];
                    $variantKeys = array_keys($variants);
                    $currentIndex = array_search($variant, $variantKeys, true);
                    $previous = $variantKeys[($currentIndex - 1 + count($variantKeys)) % count($variantKeys)];
                    $next = $variantKeys[($currentIndex + 1) % count($variantKeys)];
                @endphp
                <a href="{{ route('docs.api', array_filter(['tenant' => $tenantSlug, 'prototype' => 1, 'variant' => $previous])) }}" aria-label="Previous prototype">←</a>
                <strong>{{ strtoupper($variant) }} · {{ $variants[$variant] }}</strong>
                <a href="{{ route('docs.api', array_filter(['tenant' => $tenantSlug, 'prototype' => 1, 'variant' => $next])) }}" aria-label="Next prototype">→</a>
            </nav>
        @else
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
        @endif
    </body>
</html>
