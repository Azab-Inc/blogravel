<?php

namespace App\Notifications;

use App\Models\Backup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BackupReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Backup $backup,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Your backup is ready: '.$this->backup->filename)
            ->line('A backup has been completed successfully.')
            ->line('File: '.$this->backup->filename)
            ->line('Size: '.$this->backup->sizeFormatted())
            ->line('Encrypted: '.($this->backup->encrypted ? 'Yes' : 'No'));

        $sizeMb = $this->backup->size_bytes / 1024 / 1024;
        if ($sizeMb <= 25) {
            $path = storage_path('app/private/'.$this->backup->path);
            if (file_exists($path)) {
                $message->attach($path, ['as' => $this->backup->filename]);
            }
        } else {
            $message->line('The backup file is too large to attach to this email ('.round($sizeMb, 1).'MB).')
                ->line('The file has been stored securely and is available in the admin panel.');
        }

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'backup_id' => $this->backup->id,
            'filename' => $this->backup->filename,
            'size_bytes' => $this->backup->size_bytes,
        ];
    }
}
