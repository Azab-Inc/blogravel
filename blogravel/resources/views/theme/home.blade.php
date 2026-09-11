<x-theme::layouts.theme :tenant="$tenant">
    <div class="home-layout">
        <div class="post-list">
            @if($posts->isEmpty())
                <p>No posts yet.</p>
            @endif

            @foreach($posts as $post)
                <x-theme::post-card :post="$post" :tenant="$tenant" />
            @endforeach

            {{ $posts->links() }}
        </div>

        <x-theme::category-sidebar :categories="$categories" :tenant="$tenant" />
    </div>
</x-theme::layouts.theme>
