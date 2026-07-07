<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName = 'Test User',
        public string $body = 'This is a test email sent from the automated test suite.'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Test Email – SMTP Verification',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.test-mail',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
