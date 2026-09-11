<x-theme::layouts.theme :tenant="$tenant">
    <h1>Message Sent!</h1>
    <x-theme::success-alert message="Your message has been sent. We'll get back to you soon." />
    <a href="{{ route('theme.home') }}?tenant={{ $tenant->id }}">← Back to home</a>
</x-theme::layouts.theme>
