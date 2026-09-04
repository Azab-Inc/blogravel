<?php

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Jobs\WordPressImportJob;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('creates categories from WXR items', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $items = [
        [
            'type' => 'post',
            'title' => 'Test Post',
            'content' => '<p>Content</p>',
            'slug' => 'test-post',
            'status' => 'published',
            'published_at' => now(),
            'author' => ['name' => 'Author', 'email' => 'author@test.com'],
            'categories' => [
                ['name' => 'Laravel', 'slug' => 'laravel'],
                ['name' => 'PHP', 'slug' => 'php'],
            ],
            'tags' => [],
            'comments' => [],
            'attachments' => [],
        ],
    ];

    $job = new WordPressImportJob($items, $tenant->id, $user->id);
    $job->handle();

    expect(Category::where('tenant_id', $tenant->id)->count())->toBe(2)
        ->and(Category::where('tenant_id', $tenant->id)->pluck('name')->toArray())->toContain('Laravel', 'PHP');
});

it('creates tags from WXR items', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $items = [
        [
            'type' => 'post',
            'title' => 'Test Post',
            'content' => '<p>Content</p>',
            'slug' => 'test-post',
            'status' => 'published',
            'published_at' => now(),
            'author' => ['name' => 'Author', 'email' => 'author@test.com'],
            'categories' => [],
            'tags' => [
                ['name' => 'tutorial', 'slug' => 'tutorial'],
                ['name' => 'beginner', 'slug' => 'beginner'],
            ],
            'comments' => [],
            'attachments' => [],
        ],
    ];

    $job = new WordPressImportJob($items, $tenant->id, $user->id);
    $job->handle();

    expect(Tag::where('tenant_id', $tenant->id)->count())->toBe(2)
        ->and(Tag::where('tenant_id', $tenant->id)->pluck('name')->toArray())->toContain('tutorial', 'beginner');
});

it('creates posts with correct status mapping', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $items = [
        [
            'type' => 'post',
            'title' => 'Published Post',
            'content' => '<p>Published</p>',
            'slug' => 'published-post',
            'status' => 'publish',
            'published_at' => now(),
            'author' => ['name' => 'Author', 'email' => 'author@test.com'],
            'categories' => [],
            'tags' => [],
            'comments' => [],
            'attachments' => [],
        ],
        [
            'type' => 'post',
            'title' => 'Draft Post',
            'content' => '<p>Draft</p>',
            'slug' => 'draft-post',
            'status' => 'draft',
            'published_at' => null,
            'author' => ['name' => 'Author', 'email' => 'author@test.com'],
            'categories' => [],
            'tags' => [],
            'comments' => [],
            'attachments' => [],
        ],
    ];

    $job = new WordPressImportJob($items, $tenant->id, $user->id);
    $job->handle();

    $published = Post::where('tenant_id', $tenant->id)->where('slug', 'published-post')->first();
    $draft = Post::where('tenant_id', $tenant->id)->where('slug', 'draft-post')->first();

    expect($published->status)->toBe(PostStatus::Published)
        ->and($draft->status)->toBe(PostStatus::Draft);
});

it('creates pages', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $items = [
        [
            'type' => 'page',
            'title' => 'About Page',
            'content' => '<p>About us</p>',
            'slug' => 'about',
            'status' => 'publish',
            'published_at' => now(),
            'author' => ['name' => 'Author', 'email' => 'author@test.com'],
            'categories' => [],
            'tags' => [],
            'comments' => [],
            'attachments' => [],
        ],
    ];

    $job = new WordPressImportJob($items, $tenant->id, $user->id);
    $job->handle();

    $page = Page::where('tenant_id', $tenant->id)->where('slug', 'about')->first();

    expect($page)->not->toBeNull()
        ->and($page->title)->toBe('About Page')
        ->and($page->content)->toBe('<p>About us</p>')
        ->and($page->status)->toBe(PostStatus::Published);
});

it('creates comments with correct status', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $items = [
        [
            'type' => 'post',
            'title' => 'Post With Comments',
            'content' => '<p>Content</p>',
            'slug' => 'post-with-comments',
            'status' => 'publish',
            'published_at' => now(),
            'author' => ['name' => 'Author', 'email' => 'author@test.com'],
            'categories' => [],
            'tags' => [],
            'comments' => [
                [
                    'author_name' => 'Commenter',
                    'author_email' => 'commenter@test.com',
                    'content' => 'Great post!',
                    'status' => 'approved',
                ],
                [
                    'author_name' => 'Spammer',
                    'author_email' => 'spam@test.com',
                    'content' => 'Buy stuff',
                    'status' => 'spam',
                ],
            ],
            'attachments' => [],
        ],
    ];

    $job = new WordPressImportJob($items, $tenant->id, $user->id);
    $job->handle();

    $post = Post::where('tenant_id', $tenant->id)->where('slug', 'post-with-comments')->first();
    $comments = Comment::where('tenant_id', $tenant->id)->where('post_id', $post->id)->get();

    expect($comments)->toHaveCount(2)
        ->and($comments->firstWhere('author_name', 'Commenter')->status)->toBe(CommentStatus::Approved)
        ->and($comments->firstWhere('author_name', 'Spammer')->status)->toBe(CommentStatus::Spam);
});

it('is idempotent and skips existing posts', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Post::factory()->create([
        'tenant_id' => $tenant->id,
        'slug' => 'existing-post',
        'author_id' => $user->id,
    ]);

    $items = [
        [
            'type' => 'post',
            'title' => 'Existing Post',
            'content' => '<p>Updated content</p>',
            'slug' => 'existing-post',
            'status' => 'publish',
            'published_at' => now(),
            'author' => ['name' => 'Author', 'email' => 'author@test.com'],
            'categories' => [],
            'tags' => [],
            'comments' => [],
            'attachments' => [],
        ],
    ];

    $job = new WordPressImportJob($items, $tenant->id, $user->id);
    $job->handle();

    expect(Post::where('tenant_id', $tenant->id)->where('slug', 'existing-post')->count())->toBe(1)
        ->and($job->skipped['posts'])->toBe(1);
});

it('handles media attachments', function () {
    Storage::fake('public');
    Http::fake([
        'example.com/image.jpg' => Http::response('image-content', 200),
    ]);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $items = [
        [
            'type' => 'attachment',
            'title' => 'Test Image',
            'content' => '',
            'slug' => 'test-image',
            'status' => 'publish',
            'published_at' => now(),
            'author' => ['name' => 'Author', 'email' => 'author@test.com'],
            'categories' => [],
            'tags' => [],
            'comments' => [],
            'attachments' => [
                [
                    'url' => 'https://example.com/image.jpg',
                    'name' => 'Test Image',
                    'mime_type' => 'image/jpeg',
                ],
            ],
        ],
    ];

    $job = new WordPressImportJob($items, $tenant->id, $user->id);
    $job->handle();

    $media = Media::where('tenant_id', $tenant->id)->first();

    expect($media)->not->toBeNull()
        ->and($media->name)->toBe('Test Image')
        ->and($media->mime_type)->toBe('image/jpeg')
        ->and($job->imported['media'])->toBe(1);

    Storage::disk('public')->assertExists($media->file_path);
});
