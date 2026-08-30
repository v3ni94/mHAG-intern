<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Testnachricht zur Prüfung der Mailkonfiguration im Administrationsbereich.
 */
class SmtpTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $triggeredBy,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Testnachricht des Intranets der Müller Holding AG',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.smtp-test',
            with: [
                'triggeredBy' => $this->triggeredBy,
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'from' => config('mail.from.address'),
                'sentAt' => now(),
            ],
        );
    }
}
