<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Current Plan --}}
        <x-filament::section>
            <x-slot name="heading">
                Current Plan
            </x-slot>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Plan</p>
                    <p class="text-lg font-semibold">{{ ucfirst($currentPlan) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Posts</p>
                    <p class="text-lg font-semibold">
                        {{ $postCount }} / {{ $postLimit ?? '∞' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Users</p>
                    <p class="text-lg font-semibold">
                        {{ $userCount }} / {{ $userLimit ?? '∞' }}
                    </p>
                </div>
            </div>
        </x-filament::section>

        {{-- Available Plans --}}
        <x-filament::section>
            <x-slot name="heading">
                Available Plans
            </x-slot>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                @foreach ($this->getPlans() as $key => $plan)
                    <div class="rounded-lg border p-4 {{ $currentPlan === $key ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/10' : 'border-gray-200 dark:border-gray-700' }}">
                        <h3 class="text-lg font-semibold">{{ $plan['label'] }}</h3>
                        <p class="text-2xl font-bold">{{ $plan['price'] }}</p>
                        <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                            <li>{{ $plan['posts'] }}</li>
                            <li>{{ $plan['image_size'] }}</li>
                            <li>{{ $plan['users'] }}</li>
                        </ul>
                        @if ($currentPlan !== $key)
                            <x-filament::button
                                class="mt-4"
                                tag="a"
                                href="https://billing.stripe.com/{{ $key }}"
                                target="_blank"
                                size="sm"
                            >
                                {{ in_array($key, ['pro', 'business']) ? 'Upgrade' : 'Downgrade' }}
                            </x-filament::button>
                        @else
                            <x-filament::badge class="mt-4" color="success">
                                Current Plan
                            </x-filament::badge>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
