<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Benachrichtigung des Benutzers über Änderungen an seinem Konto
 * (Rollen, Aktivierung, Deaktivierung, Zurücksetzen der
 * Zwei-Faktor-Authentifizierung). Transparenz gegenüber dem Betroffenen.
 */
class UserAccountChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $changes  Klartext-Beschreibung der Änderungen
     */
    public function __construct(
        public User $user,
        public array $changes,
        public string $headline = 'Änderung an Ihrem Benutzerkonto',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->headline.' im Intranet der Müller Holding AG',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.account-changed',
            with: [
                'name' => $this->user->name,
                'headline' => $this->headline,
                'changes' => $this->changes,
                'loginUrl' => route('login'),
            ],
        );
    }
}
