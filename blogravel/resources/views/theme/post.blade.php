<x-layouts.theme :tenant="$tenant">
    <article>
        <h1>{{ $post->title }}</h1>
        <div class="post-meta">
            By {{ $post->author->name }} · {{ $post->published_at->format('M j, Y') }}
            @if($post->categories->count())
                · @foreach($post->categories as $cat)
                    <a href="{{ route('theme.category', ['slug' => $cat->slug]) }}?tenant={{ $tenant->id }}">{{ $cat->name }}</a>{{ $loop->last ? '' : ', ' }}
                @endforeach
            @endif
        </div>

        <div style="margin: 1.5rem 0; line-height: 1.8;">
            {!! $post->content !!}
        </div>

        @if($post->tags->count())
            <div style="margin-top: 1.5rem;">
                @foreach($post->tags as $tag)
                    <span class="tag">{{ $tag->name }}</span>
                @endforeach
            </div>
        @endif
    </article>

    <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--border);">
        <a href="{{ route('theme.home') }}?tenant={{ $tenant->id }}">← Back to all posts</a>
    </div>
</x-layouts.theme>
