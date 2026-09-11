<x-layouts.theme :tenant="$tenant">
    <h1>Contact</h1>
    <p style="color: var(--muted); margin-bottom: 1.5rem;">Send us a message.</p>

    <form method="POST" action="{{ route('theme.contact.post', ['tenant' => $tenant->id]) }}">
        @csrf
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required placeholder="Your name" value="{{ old('name') }}">
        </div>
        @error('name')
            <p style="color: #dc2626; font-size: 0.875rem; margin-bottom: 1rem;">{{ $message }}</p>
        @enderror

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required placeholder="you@example.com" value="{{ old('email') }}">
        </div>
        @error('email')
            <p style="color: #dc2626; font-size: 0.875rem; margin-bottom: 1rem;">{{ $message }}</p>
        @enderror

        <div class="form-group">
            <label for="message">Message</label>
            <textarea id="message" name="message" required placeholder="Your message...">{{ old('message') }}</textarea>
        </div>
        @error('message')
            <p style="color: #dc2626; font-size: 0.875rem; margin-bottom: 1rem;">{{ $message }}</p>
        @enderror

        <button type="submit" class="btn">Send Message</button>
    </form>
</x-layouts.theme>
