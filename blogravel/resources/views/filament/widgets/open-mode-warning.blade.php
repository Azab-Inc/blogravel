<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center gap-3">
            <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-warning-500" />
            <div>
                <p class="text-sm font-medium text-gray-900 dark:text-white">
                    No API keys configured
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    The API is in open mode. Create an API key in
                    <a href="{{ route('filament.admin.resources.api-keys.index') }}" class="underline text-primary-600 hover:text-primary-500">
                        Settings &rarr; API Keys
                    </a>
                    to secure your endpoints.
                </p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
