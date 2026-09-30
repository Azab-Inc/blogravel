@foreach ($documents as $document)
    <section id="{{ $document['slug'] }}" class="docs-section">
        @if ($document['slug'] === 'overview')
            {!! str_replace('<h2>Quick start</h2>', '<h2>Quick start</h2>'.(($showTenantForm ?? true) ? view('docs.partials.tenant-form', ['tenantSlug' => $tenantSlug])->render() : ''), $document['html']) !!}
        @else
            {!! $document['html'] !!}
        @endif
    </section>
@endforeach
