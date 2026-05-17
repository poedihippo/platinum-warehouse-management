<?php

namespace App\Mail\Loyalty;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClaimApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $invoiceNumber,
        public int $points,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pengajuan disetujui — Anda mendapat {$this->points} poin",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.loyalty.claim-approved');
    }
}
