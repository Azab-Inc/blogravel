<?php

namespace App\Services\Feeds;

use App\Models\Post;

class FeedData
{
    public static function build(
        $query,
        string $feedTitle,
        string $feedDescription,
        string $feedUrl,
        string $feedLink,
        int $limit = 50,
    ): array {
        $posts = $query->limit($limit)->get();

        return [
            'title' => $feedTitle,
            'description' => $feedDescription,
            'url' => $feedUrl,
            'link' => $feedLink,
            'posts' => $posts->map(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'content' => $post->content,
                'excerpt' => $post->excerpt,
                'author' => $post->author->name,
                'published_at' => $post->published_at?->toRfc2822String() ?? $post->created_at->toRfc2822String(),
                'published_iso' => $post->published_at?->toRfc3339String() ?? $post->created_at->toRfc3339String(),
                'categories' => $post->categories->pluck('name')->values()->all(),
            ])->values()->all(),
        ];
    }
}
