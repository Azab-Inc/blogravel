<?php

namespace App\Notifications;

use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriberConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscriber $subscriber,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $confirmUrl = route('api.confirm', ['token' => $this->subscriber->confirmation_token]);

        return (new MailMessage)
            ->subject('Confirm your subscription')
            ->line('Thank you for subscribing!')
            ->line('Please confirm your email address by clicking the button below.')
            ->action('Confirm Subscription', $confirmUrl);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'subscriber_id' => $this->subscriber->id,
            'email' => $this->subscriber->email,
        ];
    }
}
