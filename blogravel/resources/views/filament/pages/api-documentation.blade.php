<x-filament-panels::page>
    @vite('resources/js/docs.js')

    <div class="blogravel-api-docs">
        <header class="blogravel-api-docs__hero">
            <div class="blogravel-api-docs__eyebrow">Developer documentation · API v1</div>
            <h2>Build and publish with the Blogravel API.</h2>
            <p>Copy-ready endpoints for content, authors, subscribers, webhooks, and every tenant in your workspace.</p>
        </header>

        @if (count($this->getTenantOptions()) > 0)
            <section class="blogravel-api-docs__tenant">
                <div class="blogravel-api-docs__tenant-heading">
                    <h3>Choose a tenant</h3>
                    <span>Examples are generated live</span>
                </div>
                <select id="selectedTenantSlug" wire:model.live="selectedTenantSlug" class="blogravel-api-docs__select">
                    <option value="">Choose a tenant</option>
                    @foreach ($this->getTenantOptions() as $slug => $name)
                        <option value="{{ $slug }}">{{ $name }} ({{ $slug }})</option>
                    @endforeach
                </select>
                @if ($this->getSelectedTenantHost())
                    <p class="blogravel-api-docs__host">Requests target <code>{{ $this->getSelectedTenantHost() }}</code></p>
                @endif
            </section>
        @endif

        @if ($this->getSelectedTenantHost())
            @include('docs.partials.content', ['documents' => $this->getDocuments(), 'tenantSlug' => $this->selectedTenantSlug])
        @else
            <div class="blogravel-api-docs__empty">
                <p>Choose a tenant to view generated API documentation.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
