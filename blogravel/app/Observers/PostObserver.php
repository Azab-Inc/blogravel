<?php

namespace App\Observers;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Services\WebhookDispatcher;

class PostObserver
{
    public function __construct(
        protected WebhookDispatcher $dispatcher,
    ) {}

    public function created(Post $post): void
    {
        if ($post->status === PostStatus::Published) {
            $this->dispatcher->dispatch('post.published', $post->toArray(), $post->tenant_id);
        }
    }

    public function updated(Post $post): void
    {
        if ($post->wasChanged('status') && $post->status === PostStatus::Published) {
            $this->dispatcher->dispatch('post.published', $post->toArray(), $post->tenant_id);
        } elseif ($post->wasChanged()) {
            $this->dispatcher->dispatch('post.updated', $post->toArray(), $post->tenant_id);
        }
    }

    public function deleted(Post $post): void
    {
        $this->dispatcher->dispatch('post.deleted', $post->toArray(), $post->tenant_id);
    }
}
