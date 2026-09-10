<x-filament-widgets::widget>
    <div class="rounded-xl bg-warning-50 dark:bg-warning-500/10 p-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <x-heroicon-o-arrow-up-circle class="h-6 w-6 text-warning-600 dark:text-warning-400" />
            <div>
                <p class="font-semibold text-warning-800 dark:text-warning-200">
                    You're on the Free plan
                </p>
                <p class="text-sm text-warning-600 dark:text-warning-400">
                    Upgrade to unlock more posts, larger uploads, and more users.
                </p>
            </div>
        </div>
        <x-filament::button
            tag="a"
            href="/admin/billing"
            color="warning"
            size="sm"
        >
            Upgrade Plan
        </x-filament::button>
    </div>
</x-filament-widgets::widget>
