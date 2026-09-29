@once
    <style>
        .docs-code-block { position: relative; }
        .code-copy-button {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            z-index: 1;
            padding: 0.25rem 0.6rem;
            border: 1px solid currentColor;
            border-radius: 0.25rem;
            cursor: pointer;
        }
    </style>
@endonce

@foreach ($documents as $document)
    <section id="{{ $document['slug'] }}" class="docs-section">
        {!! $document['html'] !!}
    </section>
@endforeach
