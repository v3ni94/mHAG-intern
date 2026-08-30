<?php

namespace App\Mail;

use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Einladung neuer Benutzer (Abschnitt 12 Masterprompt).
 * Der Klartext-Token wird ausschließlich per E-Mail versendet;
 * gespeichert wird nur der SHA-256-Hash.
 */
class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public UserInvitation $invitation,
        public string $token,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ihre Einladung zum Intranet der Müller Holding AG',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.invitation',
            with: [
                'url' => route('invitations.show', $this->token),
                'expiresAt' => $this->invitation->expires_at,
                'roles' => $this->invitation->roles ?? [],
            ],
        );
    }
}
