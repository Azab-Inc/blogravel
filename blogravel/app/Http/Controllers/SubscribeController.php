<?php

namespace App\Http\Controllers;

use App\Enums\SubscriberStatus;
use App\Models\Post;
use App\Models\Subscriber;
use App\Models\Tenant;
use App\Notifications\PostPublished;
use App\Notifications\SubscriberConfirmation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class SubscribeController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['exists:categories,id'],
        ]);

        $tenant = $this->resolveTenant($request);

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        $subscriber = Subscriber::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'email' => $validated['email'],
            ],
            [
                'status' => SubscriberStatus::Pending,
                'confirmation_token' => Str::random(64),
            ]
        );

        if (isset($validated['categories'])) {
            $subscriber->categories()->sync($validated['categories']);
        }

        $subscriber->notify(new SubscriberConfirmation($subscriber));

        return response()->json(['message' => 'Confirmation email sent.'], 201);
    }

    public function confirm(Request $request, string $token): JsonResponse
    {
        $subscriber = Subscriber::where('confirmation_token', $token)
            ->where('status', SubscriberStatus::Pending)
            ->first();

        if (! $subscriber) {
            return response()->json(['message' => 'Invalid or expired confirmation token.'], 404);
        }

        $subscriber->update([
            'status' => SubscriberStatus::Subscribed,
            'confirmation_token' => null,
        ]);

        return response()->json(['message' => 'Subscription confirmed.']);
    }

    public function unsubscribe(Request $request, string $token): JsonResponse
    {
        $subscriber = Subscriber::where('confirmation_token', $token)
            ->where('status', SubscriberStatus::Subscribed)
            ->first();

        if (! $subscriber) {
            return response()->json(['message' => 'Invalid or expired unsubscribe token.'], 404);
        }

        $subscriber->update([
            'status' => SubscriberStatus::Unsubscribed,
            'confirmation_token' => null,
        ]);

        return response()->json(['message' => 'Unsubscribed successfully.']);
    }

    public function notifySubscribers(Request $request, string $postId): JsonResponse
    {
        $post = Post::withoutGlobalScopes()
            ->with(['categories', 'author', 'tenant'])
            ->find($postId);

        if (! $post) {
            return response()->json(['message' => 'Post not found.'], 404);
        }

        $tenant = $post->tenant;
        $categoryIds = $post->categories->pluck('id');

        $subscribers = Subscriber::where('tenant_id', $tenant->id)
            ->where('status', SubscriberStatus::Subscribed)
            ->where(function ($query) use ($categoryIds) {
                $query->whereDoesntHave('categories')
                    ->orWhereHas('categories', function ($q) use ($categoryIds) {
                        $q->whereIn('categories.id', $categoryIds);
                    });
            })
            ->get();

        Notification::send($subscribers, new PostPublished($post));

        return response()->json(['message' => 'Notifications sent.', 'count' => $subscribers->count()]);
    }

    private function resolveTenant(Request $request): ?Tenant
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
            return Tenant::where('domain', $param)->orWhere('id', $param)->first();
        }

        return null;
    }
}
