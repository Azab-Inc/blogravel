<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\ApiKey;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController extends Controller
{
    private function getTenantId(Request $request): string
    {
        $user = $request->user();

        if ($user) {
            return $user->tenant_id;
        }

        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('apiKey');

        return $apiKey?->tenant_id ?? abort(401, 'Unable to determine tenant.');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $posts = Post::with(['author', 'categories', 'tags'])
            ->where('tenant_id', $this->getTenantId($request))
            ->latest()
            ->paginate(15);

        return PostResource::collection($posts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'status' => 'in:draft,scheduled,published',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        $tenantId = $this->getTenantId($request);
        $user = $request->user();
        $authorId = $user?->id
            ?? User::where('tenant_id', $tenantId)->first()?->id
            ?? abort(401, 'No tenant user available to assign as author.');

        $post = Post::create([
            ...$validated,
            'tenant_id' => $tenantId,
            'author_id' => $authorId,
            'slug' => \Str::slug($validated['title']),
            'status' => $validated['status'] ?? 'draft',
        ]);

        if (! empty($validated['category_ids'])) {
            $post->categories()->sync($validated['category_ids']);
        }

        if (! empty($validated['tag_ids'])) {
            $post->tags()->sync($validated['tag_ids']);
        }

        return response()->json([
            'data' => new PostResource($post->load(['author', 'categories', 'tags'])),
        ], 201);
    }

    public function show(Post $post): PostResource
    {
        return new PostResource($post->load(['author', 'categories', 'tags']));
    }

    public function update(Request $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'excerpt' => 'nullable|string|max:500',
            'status' => 'in:draft,scheduled,published',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        if (isset($validated['title'])) {
            $validated['slug'] = \Str::slug($validated['title']);
        }

        $post->update($validated);

        if (array_key_exists('category_ids', $validated)) {
            $post->categories()->sync($validated['category_ids'] ?? []);
        }

        if (array_key_exists('tag_ids', $validated)) {
            $post->tags()->sync($validated['tag_ids'] ?? []);
        }

        return response()->json([
            'data' => new PostResource($post->load(['author', 'categories', 'tags'])),
        ]);
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $post->delete();

        return response()->json(null, 204);
    }
}
