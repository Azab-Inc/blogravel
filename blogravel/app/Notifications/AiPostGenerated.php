<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AiPostGenerated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public bool $success,
        public string $title,
        public string $body,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'success' => $this->success,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->line($this->body);

        return $this->success
            ? $mail->line('Your post has been saved as a draft.')
            : $mail->line('Your draft post was not modified.');
    }
}
