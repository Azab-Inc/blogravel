<?php

namespace App\Jobs;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendInvitationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Invitation $invitation,
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        Mail::to($this->invitation->email)
            ->send(new InvitationMail($this->invitation));
    }

    public function failed(\Throwable $exception): void
    {
        logger()->error('Failed to send invitation', [
            'invitation_id' => $this->invitation->id,
            'email' => $this->invitation->email,
            'error' => $exception->getMessage(),
        ]);
    }
}
