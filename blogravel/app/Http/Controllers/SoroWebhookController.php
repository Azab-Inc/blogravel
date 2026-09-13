<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Tenant;
use App\Services\TenantHostResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SoroWebhookController extends Controller
{
    public function __construct(private TenantHostResolver $resolver) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'excerpt' => ['nullable', 'string'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'featured_image' => ['nullable', 'url'],
            'metadata' => ['nullable', 'array'],
            'metadata.meta_title' => ['nullable', 'string'],
            'metadata.meta_description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:draft,published'],
        ]);

        $tenant = $this->resolveTenant($request);

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        $user = $tenant->users()->first();

        $post = Post::create([
            'tenant_id' => $tenant->id,
            'author_id' => $user?->id,
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']),
            'content' => $validated['content'],
            'excerpt' => $validated['excerpt'] ?? null,
            'status' => $validated['status'] ?? PostStatus::Draft,
            'published_at' => ($validated['status'] ?? 'draft') === 'published' ? now() : null,
        ]);

        if (! empty($validated['categories'])) {
            $categoryIds = [];
            foreach ($validated['categories'] as $name) {
                $category = Category::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => Str::slug($name)],
                    ['name' => $name]
                );
                $categoryIds[] = $category->id;
            }
            $post->categories()->sync($categoryIds);
        }

        if (! empty($validated['tags'])) {
            $tagIds = [];
            foreach ($validated['tags'] as $name) {
                $tag = Tag::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => Str::slug($name)],
                    ['name' => $name]
                );
                $tagIds[] = $tag->id;
            }
            $post->tags()->sync($tagIds);
        }

        return response()->json(['message' => 'Draft post created.', 'post_id' => $post->id], 201);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $host = strtolower($request->getHost());
        $tenant = $this->resolver->resolve($host);

        if ($tenant) {
            return $tenant;
        }

        if (! $this->isLocalHost($host)) {
            return null;
        }

        $param = $request->input('tenant');
        if ($param) {
            $query = Tenant::where('domain', $param);

            if (Str::isUuid($param)) {
                $query->orWhere('id', $param);
            }

            return $query->first();
        }

        return null;
    }

    private function isLocalHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '::1', 'lvh.me'], true);
    }
}
