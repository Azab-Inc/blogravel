@props(['categories', 'tenant'])

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
