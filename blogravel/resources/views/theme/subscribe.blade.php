<x-layouts.theme :tenant="$tenant">
    <h1>Subscribe</h1>
    <p style="color: var(--muted); margin-bottom: 1.5rem;">Get notified when new posts are published.</p>

    <form method="POST" action="{{ route('theme.subscribe.post', ['tenant' => $tenant->id]) }}">
        @csrf
        <div class="form-group">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" required placeholder="you@example.com" value="{{ old('email') }}">
        </div>
        @error('email')
            <p style="color: #dc2626; font-size: 0.875rem; margin-bottom: 1rem;">{{ $message }}</p>
        @enderror
        <button type="submit" class="btn">Subscribe</button>
    </form>
</x-layouts.theme>
