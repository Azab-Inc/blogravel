<x-layouts.theme :tenant="$tenant">
    <h1>Message Sent!</h1>
    <div class="success">
        <p>Thank you for reaching out. We'll get back to you soon.</p>
    </div>
    <a href="{{ route('theme.home') }}?tenant={{ $tenant->id }}">← Back to home</a>
</x-layouts.theme>
