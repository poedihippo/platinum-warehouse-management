<?php

namespace App\Mail\Loyalty;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClaimRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $invoiceNumber,
        public string $reason,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Pengajuan tidak disetujui');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.loyalty.claim-rejected');
    }
}
