@props(['post', 'tenant'])

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
