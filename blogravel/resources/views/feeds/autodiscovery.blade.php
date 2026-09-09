@php
    $feedUrls = [
        'rss' => route('feed.posts', ['resource' => 'posts', 'format' => 'xml', 'tenant' => request()->input('tenant', config('app.name'))]),
        'atom' => route('feed.posts', ['resource' => 'posts', 'format' => 'atom', 'tenant' => request()->input('tenant', config('app.name'))]),
    ];
@endphp
<link rel="alternate" type="application/rss+xml" title="{{ config('app.name') }} RSS" href="{{ $feedUrls['rss'] }}">
<link rel="alternate" type="application/atom+xml" title="{{ config('app.name') }} Atom" href="{{ $feedUrls['atom'] }}">
