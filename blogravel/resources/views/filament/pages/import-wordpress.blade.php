<div>
    <form wire:submit="import">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Start Import
            </x-filament::button>
        </div>
    </form>
</div>
