<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center gap-3">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="shrink-0 text-warning-500">
                <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
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
