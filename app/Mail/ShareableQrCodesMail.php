<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShareableQrCodesMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string, array{url: string, image: string}> $codes */
    public function __construct(public ?string $name, public array $codes) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Jouw QR-codes — DCTerra 24 Uur Live',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.shareable-qr-codes',
            text: 'mail.shareable-qr-codes-text',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->codes['website']['image'], 'website-qr.png')->withMime('image/png'),
            Attachment::fromData(fn (): string => $this->codes['foundationDonation']['image'], 'wensstichting-qr.png')->withMime('image/png'),
        ];
    }
}
