<x-filament-panels::page>
    @vite('resources/js/docs.js')

    @if (count($this->getTenantOptions()) > 0)
        <x-filament::section>
            <x-slot name="heading">Tenant examples</x-slot>
            <label for="selectedTenantSlug" class="fi-fo-field-wrp-label inline-flex text-sm font-medium">
                Tenant
            </label>
            <select id="selectedTenantSlug" wire:model.live="selectedTenantSlug" class="fi-select-input block w-full rounded-lg border-gray-300">
                <option value="">Choose a tenant</option>
                @foreach ($this->getTenantOptions() as $slug => $name)
                    <option value="{{ $slug }}">{{ $name }} ({{ $slug }})</option>
                @endforeach
            </select>
        </x-filament::section>
    @endif

    @if ($this->getSelectedTenantHost())
        <x-filament::section>
            <p>Examples are generated for <code>{{ $this->getSelectedTenantHost() }}</code>.</p>
        </x-filament::section>
        @include('docs.partials.content', ['documents' => $this->getDocuments()])
    @else
        <x-filament::section>
            <p>Choose a tenant to view generated API documentation.</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
