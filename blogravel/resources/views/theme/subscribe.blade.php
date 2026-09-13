<x-theme::layouts.theme :tenant="$tenant">
    <h1>Subscribe</h1>
    <p class="muted">Get notified when new posts are published.</p>

    <form method="POST" action="{{ request()->attributes->get('tenant_path_slug')
        ? route('theme.local.subscribe.post', ['tenantSlug' => request()->attributes->get('tenant_path_slug'), 'tenant' => $tenant->id])
        : route('theme.subscribe.post', ['tenant' => $tenant->id]) }}">
        @csrf
        <x-theme::form-field name="email" label="Email address" type="email" placeholder="you@example.com" :value="old('email')" />
        <button type="submit" class="btn">Subscribe</button>
    </form>
</x-theme::layouts.theme>
