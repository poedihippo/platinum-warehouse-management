<?php

namespace App\Mail\Loyalty;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $token,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset kata sandi Anda');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.loyalty.password-reset');
    }
}
