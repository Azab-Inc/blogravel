<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PostPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Post $post,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $unsubscribeUrl = route('api.unsubscribe', ['token' => $notifiable->confirmation_token ?? '']);

        return (new MailMessage)
            ->subject('New post: '.$this->post->title)
            ->line('A new post has been published: '.$this->post->title)
            ->line($this->post->excerpt)
            ->action('Read more', url('/posts/'.$this->post->slug))
            ->line('Unsubscribe: '.$unsubscribeUrl);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'post_id' => $this->post->id,
            'title' => $this->post->title,
        ];
    }
}
