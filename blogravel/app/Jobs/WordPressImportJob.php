<?php

namespace App\Jobs;

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WordPressImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    /** @var array<string, int> */
    public array $imported = ['posts' => 0, 'pages' => 0, 'categories' => 0, 'tags' => 0, 'comments' => 0, 'media' => 0];

    /** @var array<string, int> */
    public array $skipped = ['posts' => 0, 'pages' => 0, 'categories' => 0, 'tags' => 0, 'comments' => 0];

    public int $failed = 0;

    /**
     * @param  array  $items  Parsed items from WxrParser
     * @param  string  $tenantId  UUID of the tenant
     * @param  string  $userId  UUID of the user performing the import
     */
    public function __construct(
        public array $items,
        public string $tenantId,
        public string $userId,
    ) {}

    public function handle(): void
    {
        $this->processCategories();
        $this->processTags();
        $this->processPosts();
        $this->processPages();
        $this->processMedia();
    }

    public function progress(): array
    {
        return [
            'imported' => $this->imported,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
        ];
    }

    private function processCategories(): void
    {
        $categoryMap = [];

        foreach ($this->items as $item) {
            foreach ($item['categories'] ?? [] as $cat) {
                $slug = Str::slug($cat['name']);

                if (isset($categoryMap[$slug])) {
                    continue;
                }

                $category = Category::updateOrCreate(
                    ['tenant_id' => $this->tenantId, 'slug' => $slug],
                    ['name' => $cat['name']],
                );

                $categoryMap[$slug] = $category->id;
                $this->imported['categories']++;
            }
        }

        $this->categoryMap = $categoryMap;
    }

    private function processTags(): void
    {
        $tagMap = [];

        foreach ($this->items as $item) {
            foreach ($item['tags'] ?? [] as $tag) {
                $slug = Str::slug($tag['name']);

                if (isset($tagMap[$slug])) {
                    continue;
                }

                $tagModel = Tag::updateOrCreate(
                    ['tenant_id' => $this->tenantId, 'slug' => $slug],
                    ['name' => $tag['name']],
                );

                $tagMap[$slug] = $tagModel->id;
                $this->imported['tags']++;
            }
        }

        $this->tagMap = $tagMap;
    }

    private function processPosts(): void
    {
        foreach ($this->items as $item) {
            if (($item['type'] ?? '') !== 'post') {
                continue;
            }

            $slug = Str::slug($item['slug'] ?? $item['title'] ?? '');

            if (Post::where('tenant_id', $this->tenantId)->where('slug', $slug)->exists()) {
                $this->skipped['posts']++;

                continue;
            }

            try {
                $authorId = $this->findOrCreateAuthor($item['author'] ?? []);

                $post = Post::create([
                    'tenant_id' => $this->tenantId,
                    'author_id' => $authorId,
                    'title' => $item['title'] ?? '',
                    'slug' => $slug,
                    'content' => $item['content'] ?? '',
                    'excerpt' => $item['excerpt'] ?? null,
                    'status' => $this->mapPostStatus($item['status'] ?? 'draft'),
                    'published_at' => $item['published_at'] ?? null,
                ]);

                $this->syncCategories($post, $item['categories'] ?? []);
                $this->syncTags($post, $item['tags'] ?? []);
                $this->createComments($post, $item['comments'] ?? []);

                $this->imported['posts']++;
            } catch (\Throwable $e) {
                $this->failed++;
            }
        }
    }

    private function processPages(): void
    {
        foreach ($this->items as $item) {
            if (($item['type'] ?? '') !== 'page') {
                continue;
            }

            $slug = Str::slug($item['slug'] ?? $item['title'] ?? '');

            if (Page::where('tenant_id', $this->tenantId)->where('slug', $slug)->exists()) {
                $this->skipped['pages']++;

                continue;
            }

            try {
                Page::create([
                    'tenant_id' => $this->tenantId,
                    'title' => $item['title'] ?? '',
                    'slug' => $slug,
                    'content' => $item['content'] ?? '',
                    'status' => $this->mapPostStatus($item['status'] ?? 'draft'),
                ]);

                $this->imported['pages']++;
            } catch (\Throwable $e) {
                $this->failed++;
            }
        }
    }

    private function processMedia(): void
    {
        foreach ($this->items as $item) {
            foreach ($item['attachments'] ?? [] as $attachment) {
                $url = $attachment['url'] ?? '';

                if (empty($url)) {
                    continue;
                }

                try {
                    $contents = Http::timeout(30)->get($url)->body();
                    $filename = basename(parse_url($url, PHP_URL_PATH)) ?: 'attachment '.Str::random(8);
                    $path = 'media/'.$filename;

                    Storage::disk('public')->put($path, $contents);

                    Media::create([
                        'tenant_id' => $this->tenantId,
                        'name' => $attachment['name'] ?? $filename,
                        'file_path' => $path,
                        'url' => Storage::disk('public')->url($path),
                        'mime_type' => $attachment['mime_type'] ?? null,
                        'size' => strlen($contents),
                    ]);

                    $this->imported['media']++;
                } catch (\Throwable $e) {
                    $this->failed++;
                }
            }
        }
    }

    private function findOrCreateAuthor(array $authorData): string
    {
        $email = $authorData['email'] ?? null;

        if ($email) {
            $existing = User::where('email', $email)->first();

            if ($existing) {
                return $existing->id;
            }
        }

        $name = $authorData['name'] ?? 'Unknown Author';
        $parts = explode(' ', $name, 2);

        $user = User::create([
            'first_name' => $parts[0] ?? $name,
            'last_name' => $parts[1] ?? null,
            'name' => $name,
            'email' => $email ?? Str::random(8).'@imported.local',
            'password' => Str::random(32),
            'tenant_id' => $this->tenantId,
            'role' => Role::Author,
        ]);

        return $user->id;
    }

    private function mapPostStatus(string $wpStatus): PostStatus
    {
        return match ($wpStatus) {
            'publish', 'private' => PostStatus::Published,
            'draft', 'pending', 'trash' => PostStatus::Draft,
            default => PostStatus::Draft,
        };
    }

    private function syncCategories(Post $post, array $categories): void
    {
        $ids = [];

        foreach ($categories as $cat) {
            $slug = Str::slug($cat['name'] ?? '');

            if (isset($this->categoryMap[$slug])) {
                $ids[] = $this->categoryMap[$slug];
            }
        }

        $post->categories()->sync($ids);
    }

    private function syncTags(Post $post, array $tags): void
    {
        $ids = [];

        foreach ($tags as $tag) {
            $slug = Str::slug($tag['name'] ?? '');

            if (isset($this->tagMap[$slug])) {
                $ids[] = $this->tagMap[$slug];
            }
        }

        $post->tags()->sync($ids);
    }

    private function createComments(Post $post, array $comments): void
    {
        foreach ($comments as $comment) {
            try {
                Comment::create([
                    'tenant_id' => $this->tenantId,
                    'post_id' => $post->id,
                    'author_name' => $comment['author_name'] ?? 'Anonymous',
                    'author_email' => $comment['author_email'] ?? null,
                    'content' => $comment['content'] ?? '',
                    'status' => $this->mapCommentStatus($comment['status'] ?? 'pending'),
                ]);

                $this->imported['comments']++;
            } catch (\Throwable $e) {
                $this->failed++;
            }
        }
    }

    private function mapCommentStatus(string $wpStatus): CommentStatus
    {
        return match ($wpStatus) {
            'approved', '1' => CommentStatus::Approved,
            'spam' => CommentStatus::Spam,
            default => CommentStatus::Pending,
        };
    }
}
