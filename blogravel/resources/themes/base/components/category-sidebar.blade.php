@props(['categories', 'tenant'])

@if($categories->count())
    <aside class="sidebar">
        <h2>Categories</h2>
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
