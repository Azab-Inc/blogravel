<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ContactMessage extends Mailable
{
    public function __construct(
        public string $name,
        public string $senderEmail,
        public string $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Contact from '.$this->name,
            replyTo: [new Address($this->senderEmail, $this->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>'.nl2br(e($this->message)).'</p>',
        );
    }
}
