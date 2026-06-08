<?php

namespace App\Mail\Loyalty;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RedemptionApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $prizeName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Penukaran disetujui — hadiah sedang disiapkan');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.loyalty.redemption-approved');
    }
}
