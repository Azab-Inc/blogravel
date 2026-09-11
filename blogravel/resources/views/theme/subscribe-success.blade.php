<x-theme::layouts.theme :tenant="$tenant">
    <h1>Subscribed!</h1>
    <x-theme::success-alert message="You've been subscribed. We'll notify you when new posts are published." />
    <a href="{{ route('theme.home') }}?tenant={{ $tenant->id }}">← Back to home</a>
</x-theme::layouts.theme>
