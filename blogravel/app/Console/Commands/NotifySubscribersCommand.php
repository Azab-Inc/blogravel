<?php

namespace App\Console\Commands;

use App\Enums\SubscriberStatus;
use App\Models\Post;
use App\Models\Subscriber;
use App\Notifications\PostPublished;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('mailing:notify-subscribers {post_id : The ID of the published post}')]
#[Description('Notify subscribers about a newly published post')]
class NotifySubscribersCommand extends Command
{
    public function handle(): int
    {
        $postId = $this->argument('post_id');

        $post = Post::withoutGlobalScopes()
            ->with(['categories', 'author', 'tenant'])
            ->find($postId);

        if (! $post) {
            $this->error('Post not found.');

            return self::FAILURE;
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

        if ($subscribers->isEmpty()) {
            $this->info('No subscribers to notify.');

            return self::SUCCESS;
        }

        Notification::send($subscribers, new PostPublished($post));

        $this->info("Sent notification to {$subscribers->count()} subscriber(s).");

        return self::SUCCESS;
    }
}
