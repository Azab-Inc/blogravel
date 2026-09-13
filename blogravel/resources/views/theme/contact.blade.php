<x-theme::layouts.theme :tenant="$tenant">
    <h1>Contact</h1>
    <p class="muted">Send us a message.</p>

    <form method="POST" action="{{ request()->attributes->get('tenant_path_slug')
        ? route('theme.local.contact.post', ['tenantSlug' => request()->attributes->get('tenant_path_slug')])
        : route('theme.contact.post', ['tenant' => $tenant->id]) }}">
        @csrf
        <x-theme::form-field name="name" label="Name" placeholder="Your name" :value="old('name')" />
        <x-theme::form-field name="email" label="Email" type="email" placeholder="you@example.com" :value="old('email')" />
        <x-theme::form-field name="message" label="Message" type="textarea" placeholder="Your message..." :value="old('message')" />
        <button type="submit" class="btn">Send Message</button>
    </form>
</x-theme::layouts.theme>
