<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invitation $invitation,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You have been invited to join {$this->invitation->tenant->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    protected function buildHtml(): string
    {
        $tenantName = e($this->invitation->tenant->name);
        $role = e($this->invitation->role->label());
        $acceptUrl = route('invitations.accept', ['token' => $this->invitation->token]);
        $expiresAt = $this->invitation->expires_at->format('F j, Y');

        return '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
<h2 style="color: #1f2937;">You are Invited!</h2>
<p>You have been invited to join <strong>'.$tenantName.'</strong> as a <strong>'.$role.'</strong>.</p>
<p>Click the button below to accept this invitation:</p>
<p style="text-align: center; margin: 30px 0;">
<a href="'.$acceptUrl.'" style="background-color: #f59e0b; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">Accept Invitation</a>
</p>
<p style="color: #6b7280; font-size: 14px;">This invitation expires on '.$expiresAt.'.</p>
<p style="color: #6b7280; font-size: 14px;">If you did not expect this invitation, you can safely ignore this email.</p>
</body>
</html>';
    }
}
