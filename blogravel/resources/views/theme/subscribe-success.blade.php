<x-layouts.theme :tenant="$tenant">
    <h1>Subscribed!</h1>
    <div class="success">
        <p>Thank you for subscribing to {{ $tenant->name }}. You'll receive notifications when new posts are published.</p>
    </div>
    <a href="{{ route('theme.home') }}?tenant={{ $tenant->id }}">← Back to home</a>
</x-layouts.theme>
