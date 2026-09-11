<x-layouts.theme :tenant="$tenant">
    <h1>Category: {{ $category->name }}</h1>
    <p style="color: var(--muted); margin-bottom: 1.5rem;">{{ $posts->total() }} posts in this category</p>

    @if($posts->isEmpty())
        <p>No posts in this category yet.</p>
    @endif

    @foreach($posts as $post)
        <article class="post-card">
            <h2><a href="{{ route('theme.post', ['slug' => $post->slug]) }}?tenant={{ $tenant->id }}">{{ $post->title }}</a></h2>
            <div class="post-meta">
                By {{ $post->author->name }} · {{ $post->published_at->format('M j, Y') }}
            </div>
            @if($post->excerpt)
                <p class="post-excerpt">{{ $post->excerpt }}</p>
            @endif
            <a href="{{ route('theme.post', ['slug' => $post->slug]) }}?tenant={{ $tenant->id }}">Read more →</a>
        </article>
    @endforeach

    {{ $posts->links() }}

    <div style="margin-top: 2rem;">
        <a href="{{ route('theme.home') }}?tenant={{ $tenant->id }}">← Back to all posts</a>
    </div>
</x-layouts.theme>
