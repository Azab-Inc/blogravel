<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Crypt;

class DraftController extends Controller
{
    /**
     * List all draft posts for the authenticated tenant.
     */
    public function index(Request $request): CursorPaginator
    {
        $apiKey = $request->attributes->get('apiKey');
        $tenantId = $apiKey->tenant_id ?? abort(401, 'Unable to determine tenant.');

        $posts = Post::with(['author', 'categories', 'tags'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [PostStatus::Draft, PostStatus::Scheduled])
            ->latest();

        return $this->cursorPaginate(
            $posts,
            ['*'],
            ['id', 'created_at', 'published_at', 'title'],
        );
    }

    /**
     * Show a single draft post (authenticated via API key).
     */
    public function show(Request $request, string $id): PostResource
    {
        $post = Post::with(['author', 'categories', 'tags'])
            ->where('id', $id)
            ->firstOrFail();

        if ($post->status === PostStatus::Published) {
            abort(404, 'Post not found.');
        }

        return new PostResource($post);
    }

    /**
     * Show a single draft post (authenticated via signed URL).
     */
    public function showBySignedUrl(Request $request, string $id): PostResource
    {
        $post = Post::with(['author', 'categories', 'tags'])
            ->where('id', $id)
            ->firstOrFail();

        if ($post->status === PostStatus::Published) {
            abort(404, 'Post not found.');
        }

        $this->verifySignedUrl($request, $post);

        return new PostResource($post);
    }

    /**
     * Generate a signed URL for draft preview.
     */
    public function createSignedUrl(Request $request, string $id): JsonResponse
    {
        $apiKey = $request->attributes->get('apiKey');
        if (! $apiKey) {
            abort(401, 'API key required.');
        }

        $post = Post::where('id', $id)
            ->where('tenant_id', $apiKey->tenant_id)
            ->firstOrFail();

        if ($post->status === PostStatus::Published) {
            abort(400, 'Cannot generate preview URL for published posts.');
        }

        $expiresIn = $request->input('expires_in', 86400);
        $expiresAt = now()->addSeconds($expiresIn);

        $payload = Crypt::encryptString(json_encode([
            'post_id' => $post->id,
            'tenant_id' => $post->tenant_id,
            'expires_at' => $expiresAt->toIso8601String(),
        ]));

        $url = route('api.v1.drafts.preview', [
            'id' => $post->id,
            'token' => $payload,
            'expires' => $expiresAt->timestamp,
        ]);

        return response()->json([
            'url' => $url,
            'expires_at' => $expiresAt->toISOString(),
        ]);
    }

    private function verifySignedUrl(Request $request, Post $post): void
    {
        $token = $request->query('token');
        $expires = $request->query('expires');

        if (! $token || ! $expires) {
            abort(401, 'Draft preview requires a signed URL.');
        }

        if (now()->timestamp > (int) $expires) {
            abort(410, 'Signed URL has expired.');
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            abort(401, 'Invalid signed URL.');
        }

        if ($payload['post_id'] !== $post->id) {
            abort(401, 'Signed URL does not match this post.');
        }

        if ($payload['tenant_id'] !== $post->tenant_id) {
            abort(401, 'Signed URL does not match this post tenant.');
        }
    }
}
