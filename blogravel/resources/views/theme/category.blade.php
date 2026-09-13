<x-theme::layouts.theme :tenant="$tenant">
    <h1>Category: {{ $category->name }}</h1>
    <p class="muted">{{ $posts->total() }} posts in this category</p>

    @if($posts->isEmpty())
        <p>No posts in this category yet.</p>
    @endif

    @foreach($posts as $post)
        <x-theme::post-card :post="$post" :tenant="$tenant" />
    @endforeach

    {{ $posts->links() }}

    <div class="back-link">
        <a href="{{ request()->attributes->get('tenant_path_slug') ? route('theme.local.home', ['tenantSlug' => request()->attributes->get('tenant_path_slug')]) : route('theme.home').'?tenant='.$tenant->id }}">← Back to all posts</a>
    </div>
</x-theme::layouts.theme>
