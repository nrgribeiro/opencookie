<?php

namespace App\Mail;

use App\Models\Domain;
use App\Models\Scan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewCookieAlert extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{name: string, sourceDomain: string|null, category: string}>  $cookies
     */
    public function __construct(
        public readonly Domain $domain,
        public readonly Scan $scan,
        public readonly array $cookies,
        public readonly int $unclassifiedCount,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New cookies detected on {$this->domain->hostname}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.cookie-alert',
            with: [
                'domain' => $this->domain,
                'scan' => $this->scan,
                'cookies' => $this->cookies,
                'unclassifiedCount' => $this->unclassifiedCount,
            ],
        );
    }
}
