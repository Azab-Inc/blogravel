<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tenant;
use App\Services\Feeds\FeedData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FeedController extends Controller
{
    public function posts(Request $request): Response|JsonResponse
    {
        $format = $request->query('format', 'xml');
        $tenant = $this->resolveTenant($request);

        $query = Post::with(['author', 'categories'])
            ->where('tenant_id', $tenant->id)
            ->where('status', 'published')
            ->orderByDesc('published_at');

        $data = FeedData::build(
            $query,
            $tenant->name.' — Posts',
            'Latest published posts from '.$tenant->name,
            $this->feedUrl($request, 'posts', $format, $tenant),
            route('home'),
        );

        return $this->respond($data, $format, $request);
    }

    public function category(Request $request, string $slug): Response|JsonResponse
    {
        $format = $request->query('format', 'xml');
        $tenant = $this->resolveTenant($request);
        $category = Category::where('tenant_id', $tenant->id)->where('slug', $slug)->firstOrFail();

        $query = Post::with(['author', 'categories'])
            ->where('tenant_id', $tenant->id)
            ->where('status', 'published')
            ->whereHas('categories', fn ($q) => $q->where('id', $category->id))
            ->orderByDesc('published_at');

        $data = FeedData::build(
            $query,
            $tenant->name.' — '.$category->name,
            'Posts in the '.$category->name.' category',
            $this->feedUrl($request, "categories/{$slug}", $format, $tenant),
            route('home'),
        );

        return $this->respond($data, $format, $request);
    }

    public function author(Request $request, string $author): Response|JsonResponse
    {
        $format = $request->query('format', 'xml');
        $tenant = $this->resolveTenant($request);

        $query = Post::with(['author', 'categories'])
            ->where('tenant_id', $tenant->id)
            ->where('status', 'published')
            ->where('author_id', $author)
            ->orderByDesc('published_at');

        $data = FeedData::build(
            $query,
            $tenant->name.' — Author',
            'Posts by this author',
            $this->feedUrl($request, "authors/{$author}", $format, $tenant),
            route('home'),
        );

        return $this->respond($data, $format, $request);
    }

    private function respond(array $data, string $format, Request $request): Response|JsonResponse
    {
        $view = match ($format) {
            'atom' => 'feeds.atom',
            default => 'feeds.rss',
        };

        if ($format === 'json') {
            return response()->json([
                'version' => 'https://jsonfeed.org/version/1.1',
                'title' => $data['title'],
                'home_page_url' => $data['link'],
                'feed_url' => $data['url'],
                'items' => collect($data['posts'])->map(fn (array $post) => [
                    'id' => $post['id'],
                    'url' => route('home').'#post-'.$post['slug'],
                    'title' => $post['title'],
                    'content_html' => $post['content'],
                    'summary' => $post['excerpt'],
                    'authors' => [['name' => $post['author']]],
                    'date_published' => $post['published_iso'],
                    'tags' => $post['categories'],
                ])->all(),
            ])->header('Content-Type', 'application/feed+json; charset=UTF-8');
        }

        return response()
            ->view($view, compact('data'), 200)
            ->header('Content-Type', ($format === 'atom' ? 'application/atom+xml' : 'text/xml').'; charset=UTF-8');
    }

    private function tenantUrl(Request $request): string
    {
        return $request->getSchemeAndHttpHost();
    }

    private function feedUrl(Request $request, string $path, string $format, Tenant $tenant): string
    {
        return $this->tenantUrl($request)."/feeds/{$path}.{$format}?tenant={$tenant->id}";
    }

    private function resolveTenant(Request $request): Tenant
    {
        $host = strtolower($request->getHost());
        if (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }

        $tenant = Tenant::where('domain', $host)->first();
        if ($tenant) {
            return $tenant;
        }

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
