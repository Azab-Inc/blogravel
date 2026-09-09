<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\PageResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\TagResource;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

class PublicReadController extends Controller
{
    public function index(Request $request, string $resource): CursorPaginator
    {
        $tenant = $this->resolveTenant($request);

        $query = match ($resource) {
            'posts' => Post::with(['author', 'categories', 'tags'])
                ->where('tenant_id', $tenant->id)
                ->where('status', 'published')
                ->orderByDesc('published_at'),
            'pages' => Page::where('tenant_id', $tenant->id)
                ->where('status', 'published')
                ->latest(),
            'categories' => Category::withCount('posts')
                ->where('tenant_id', $tenant->id)
                ->orderBy('name'),
            'tags' => Tag::withCount('posts')
                ->where('tenant_id', $tenant->id)
                ->orderBy('name'),
            default => abort(404),
        };

        $allowedFields = match ($resource) {
            'posts' => ['id', 'title', 'slug', 'published_at', 'created_at'],
            'pages' => ['id', 'title', 'slug', 'created_at'],
            default => ['id', 'name', 'slug'],
        };

        return $this->cursorPaginate($query, ['*'], $allowedFields);
    }

    public function show(Request $request, string $resource, string $id): PostResource|PageResource|CategoryResource|TagResource
    {
        $tenant = $this->resolveTenant($request);

        return match ($resource) {
            'posts' => new PostResource(
                Post::with(['author', 'categories', 'tags'])
                    ->where('tenant_id', $tenant->id)
                    ->where('status', 'published')
                    ->findOrFail($id)
            ),
            'pages' => new PageResource(
                Page::where('tenant_id', $tenant->id)
                    ->where('status', 'published')
                    ->findOrFail($id)
            ),
            'categories' => new CategoryResource(
                Category::withCount('posts')
                    ->where('tenant_id', $tenant->id)
                    ->findOrFail($id)
            ),
            'tags' => new TagResource(
                Tag::withCount('posts')
                    ->where('tenant_id', $tenant->id)
                    ->findOrFail($id)
            ),
            default => abort(404),
        };
    }

    private function resolveTenant(Request $request): Tenant
    {
        // 1. Match Host header against tenant domain
        $host = strtolower($request->getHost());
        if (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }

        $tenant = Tenant::where('domain', $host)->first();
        if ($tenant) {
            return $tenant;
        }

        // 2. Fallback to ?tenant= query param (domain or UUID) — for local dev
        $param = $request->input('tenant');
        if ($param) {
            $tenant = Tenant::where('domain', $param)->orWhere('id', $param)->first();
            if ($tenant) {
                return $tenant;
            }
        }

        abort(404, 'Tenant not found.');
    }
}
