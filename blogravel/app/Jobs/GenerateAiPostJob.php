<?php

namespace App\Jobs;

use App\Exceptions\AiGenerationException;
use App\Models\AiProvider;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\AiPostGenerated;
use App\Services\AiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class GenerateAiPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $postId,
        public string $providerId,
        public string $model,
        public string $prompt,
        public array $outputTypes,
        public array $options,
    ) {
        $post = Post::find($postId);

        if ($post) {
            $this->onQueue("ai-generation-{$post->tenant_id}");
        }
    }

    public function handle(AiService $aiService): void
    {
        $post = Post::findOrFail($this->postId);
        $provider = AiProvider::findOrFail($this->providerId);
        $user = User::findOrFail($post->author_id);

        $provider->model = $this->model;

        try {
            $result = $aiService->generate(
                $provider,
                $this->prompt,
                $this->outputTypes,
                $this->options
            );
        } catch (AiGenerationException $e) {
            $user->notify(new AiPostGenerated(
                false,
                'AI generation failed',
                "Post [{$post->title}] — {$e->getMessage()}"
            ));

            return;
        }

        $updateData = [];

        if (isset($result['title'])) {
            $updateData['title'] = $result['title'];
        }

        if (isset($result['content'])) {
            $updateData['content'] = $result['content'];
        }

        if (isset($result['excerpt'])) {
            $updateData['excerpt'] = $result['excerpt'];
        }

        if (! empty($updateData)) {
            $post->update($updateData);
        }

        if (isset($result['categories']) && is_array($result['categories'])) {
            foreach ($result['categories'] as $categoryName) {
                $category = Category::firstOrCreate(
                    ['tenant_id' => $post->tenant_id, 'slug' => Str::slug($categoryName)],
                    ['name' => $categoryName]
                );
                $post->categories()->syncWithoutDetaching([$category->id]);
            }
        }

        if (isset($result['tags']) && is_array($result['tags'])) {
            foreach ($result['tags'] as $tagName) {
                $tag = Tag::firstOrCreate(
                    ['tenant_id' => $post->tenant_id, 'slug' => Str::slug($tagName)],
                    ['name' => $tagName]
                );
                $post->tags()->syncWithoutDetaching([$tag->id]);
            }
        }

        $fieldCount = count(array_filter(['title', 'content', 'excerpt', 'categories', 'tags'], fn ($field) => isset($result[$field])));

        $user->notify(new AiPostGenerated(
            true,
            'AI content generated',
            "Post [{$post->title}] — {$fieldCount} field(s) generated. Saved as draft."
        ));
    }
}
