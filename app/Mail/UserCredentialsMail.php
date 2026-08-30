<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Zugangsdaten für ein bestehendes Benutzerkonto.
 *
 * Enthält bewusst KEIN Passwort im Klartext. Stattdessen wird ein zeitlich
 * begrenzter Link zum Setzen eines eigenen Passworts mitgesendet. Damit kennt
 * niemand außer dem Benutzer selbst dessen Passwort, auch die Administration
 * nicht.
 */
class UserCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ?string $passwordResetUrl = null,
        public ?string $note = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ihre Zugangsdaten für das Intranet der Müller Holding AG',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.credentials',
            with: [
                'name' => $this->user->name,
                'email' => $this->user->email,
                'roles' => $this->user->roles->pluck('name')->all(),
                'loginUrl' => route('login'),
                'passwordResetUrl' => $this->passwordResetUrl,
                'twoFactorRequired' => $this->user->requiresTwoFactor(),
                'note' => $this->note,
            ],
        );
    }
}
