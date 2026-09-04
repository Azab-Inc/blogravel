<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTagRequest;
use App\Http\Requests\Api\V1\UpdateTagRequest;
use App\Http\Resources\TagResource;
use App\Models\ApiKey;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class TagController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tags = Tag::withCount('posts')
            ->where('tenant_id', $this->getTenantId($request))
            ->orderBy('name')
            ->paginate(15);

        return TagResource::collection($tags);
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tenantId = $this->getTenantId($request);

        $tag = Tag::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'tenant_id' => $tenantId,
        ]);

        return response()->json([
            'data' => new TagResource($tag),
        ], 201);
    }

    public function show(Tag $tag): TagResource
    {
        return new TagResource($tag->loadCount('posts'));
    }

    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $tag->update($validated);

        return response()->json([
            'data' => new TagResource($tag),
        ]);
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $tag->delete();

        return response()->json(null, 204);
    }

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
}
