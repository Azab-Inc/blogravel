<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Blogravel test email',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>This is a test email from Blogravel.</p>',
        );
    }
}
