<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePageRequest;
use App\Http\Requests\Api\V1\UpdatePageRequest;
use App\Http\Resources\PageResource;
use App\Models\ApiKey;
use App\Models\Page;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $pages = Page::where('tenant_id', $this->getTenantId($request))
            ->latest()
            ->paginate(15);

        return PageResource::collection($pages);
    }

    public function store(StorePageRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $tenantId = $this->getTenantId($request);
        $user = $request->user();
        $authorId = $user?->id
            ?? User::where('tenant_id', $tenantId)->first()?->id
            ?? abort(401, 'No tenant user available.');

        $page = Page::create([
            ...$validated,
            'tenant_id' => $tenantId,
            'author_id' => $authorId,
            'slug' => Str::slug($validated['title']),
            'status' => $validated['status'] ?? 'draft',
        ]);

        return response()->json([
            'data' => new PageResource($page),
        ], 201);
    }

    public function show(Page $page): PageResource
    {
        return new PageResource($page);
    }

    public function update(UpdatePageRequest $request, Page $page): JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['title'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        $page->update($validated);

        return response()->json([
            'data' => new PageResource($page),
        ]);
    }

    public function destroy(Page $page): JsonResponse
    {
        $page->delete();

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
