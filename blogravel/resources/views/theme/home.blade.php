<x-layouts.theme :tenant="$tenant">
    <div style="display: grid; grid-template-columns: 1fr; gap: 2rem;">
        @if($posts->isEmpty())
            <p>No posts yet.</p>
        @endif

        @foreach($posts as $post)
            <article class="post-card">
                <h2><a href="{{ route('theme.post', ['slug' => $post->slug]) }}?tenant={{ $tenant->id }}">{{ $post->title }}</a></h2>
                <div class="post-meta">
                    By {{ $post->author->name }} · {{ $post->published_at->format('M j, Y') }}
                    @if($post->categories->count())
                        · @foreach($post->categories as $cat)
                            <a href="{{ route('theme.category', ['slug' => $cat->slug]) }}?tenant={{ $tenant->id }}">{{ $cat->name }}</a>{{ $loop->last ? '' : ', ' }}
                        @endforeach
                    @endif
                </div>
                @if($post->excerpt)
                    <p class="post-excerpt">{{ $post->excerpt }}</p>
                @endif
                <a href="{{ route('theme.post', ['slug' => $post->slug]) }}?tenant={{ $tenant->id }}">Read more →</a>
            </article>
        @endforeach

        {{ $posts->links() }}
    </div>

    @if($categories->count())
        <aside class="sidebar">
            <h3>Categories</h3>
            <ul>
                @foreach($categories as $cat)
                    <li>
                        <a href="{{ route('theme.category', ['slug' => $cat->slug]) }}?tenant={{ $tenant->id }}">
                            {{ $cat->name }} ({{ $cat->posts_count }})
                        </a>
                    </li>
                @endforeach
            </ul>
        </aside>
    @endif
</x-layouts.theme>
