<?php

namespace App\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantExportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $identifier,
        public string $format,
        public CarbonImmutable $expiresAt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'export_id' => $this->identifier,
            'format' => $this->format,
            'expires_at' => $this->expiresAt->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your tenant export is ready')
            ->line('Your tenant data export is ready to download.')
            ->line('Format: '.strtoupper($this->format))
            ->line('The private download expires in 24 hours.')
            ->action('Download export', route('filament.admin.tenant-export.download', [
                'identifier' => $this->identifier,
            ]));
    }
}
