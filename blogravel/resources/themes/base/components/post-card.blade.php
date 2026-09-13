@props(['post', 'tenant'])

<article class="post-card">
    <h2><a href="{{ request()->attributes->get('tenant_path_slug') ? route('theme.local.post', ['tenantSlug' => request()->attributes->get('tenant_path_slug'), 'slug' => $post->slug]) : route('theme.post', ['slug' => $post->slug]).'?tenant='.$tenant->id }}">{{ $post->title }}</a></h2>
    <div class="post-meta">
        By {{ $post->author->name }} · {{ $post->published_at->format('M j, Y') }}
        @if($post->categories->count())
            · @foreach($post->categories as $cat)
                <a href="{{ request()->attributes->get('tenant_path_slug') ? route('theme.local.category', ['tenantSlug' => request()->attributes->get('tenant_path_slug'), 'slug' => $cat->slug]) : route('theme.category', ['slug' => $cat->slug]).'?tenant='.$tenant->id }}">{{ $cat->name }}</a>{{ $loop->last ? '' : ', ' }}
            @endforeach
        @endif
    </div>
    @if($post->excerpt)
        <p class="post-excerpt">{{ $post->excerpt }}</p>
    @endif
    <a href="{{ request()->attributes->get('tenant_path_slug') ? route('theme.local.post', ['tenantSlug' => request()->attributes->get('tenant_path_slug'), 'slug' => $post->slug]) : route('theme.post', ['slug' => $post->slug]).'?tenant='.$tenant->id }}">Read more →</a>
</article>
