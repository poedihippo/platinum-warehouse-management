<?php

namespace App\Mail\Loyalty;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Redemption fulfilment is out of Phase 1 scope, but the spec (§10)
 * requires this Mailable to exist. Kept ready for Phase 4.
 */
class RedemptionShippedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $prizeName,
        public string $trackingNumber,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Hadiah dikirim');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.loyalty.redemption-shipped');
    }
}
