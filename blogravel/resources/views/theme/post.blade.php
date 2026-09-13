<x-theme::layouts.theme :tenant="$tenant">
    <article>
        <h1>{{ $post->title }}</h1>
        <div class="post-meta">
            By {{ $post->author->name }} · {{ $post->published_at->format('M j, Y') }}
            @if($post->categories->count())
                · @foreach($post->categories as $cat)
                    <a href="{{ request()->attributes->get('tenant_path_slug') ? route('theme.local.category', ['tenantSlug' => request()->attributes->get('tenant_path_slug'), 'slug' => $cat->slug]) : route('theme.category', ['slug' => $cat->slug]).'?tenant='.$tenant->id }}">{{ $cat->name }}</a>{{ $loop->last ? '' : ', ' }}
                @endforeach
            @endif
        </div>

        <div class="post-content">
            {!! $post->content !!}
        </div>

        @if($post->tags->count())
            <div class="post-tags">
                @foreach($post->tags as $tag)
                    <span class="tag">{{ $tag->name }}</span>
                @endforeach
            </div>
        @endif
    </article>

    <div class="back-link">
        <a href="{{ request()->attributes->get('tenant_path_slug') ? route('theme.local.home', ['tenantSlug' => request()->attributes->get('tenant_path_slug')]) : route('theme.home').'?tenant='.$tenant->id }}">← Back to all posts</a>
    </div>
</x-theme::layouts.theme>
