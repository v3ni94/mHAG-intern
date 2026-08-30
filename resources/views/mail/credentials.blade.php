@extends('mail.layout')
@section('title', 'Ihre Zugangsdaten')
@section('subtitle', 'Zugangsdaten')

@section('content')
    <p style="margin: 0 0 12px;">Guten Tag {{ $name }},</p>
    <p style="margin: 0 0 16px;">
        für Sie wurde ein Zugang zum Intranet der Müller Holding AG eingerichtet.
        Nachfolgend finden Sie die Angaben zu Ihrem Konto.
    </p>

    @if ($note)
        <p style="margin: 0 0 16px; padding: 10px 14px; background: #FBF6EC; border: 1px solid #DDDBD6; border-radius: 6px; font-size: 14px;">
            {{ $note }}
        </p>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin: 0 0 18px; font-size: 14px;">
        <tr>
            <td style="padding: 6px 0; color: #9F9F9F; width: 40%;">Adresse des Intranets</td>
            <td style="padding: 6px 0;"><a href="{{ $loginUrl }}" style="color: #1D5FA6;">{{ $loginUrl }}</a></td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #9F9F9F;">Benutzername</td>
            <td style="padding: 6px 0;"><strong>{{ $email }}</strong></td>
        </tr>
        @if (! empty($roles))
            <tr>
                <td style="padding: 6px 0; color: #9F9F9F;">{{ count($roles) === 1 ? 'Rolle' : 'Rollen' }}</td>
                <td style="padding: 6px 0;">{{ implode(', ', $roles) }}</td>
            </tr>
        @endif
    </table>

    @if ($passwordResetUrl)
        <p style="margin: 0 0 14px;">
            Ihr Passwort vergeben Sie selbst. Aus Sicherheitsgründen wird kein Passwort per E-Mail versendet:
        </p>
        <p style="margin: 0 0 18px;" align="center">
            <a href="{{ $passwordResetUrl }}"
               style="display: inline-block; background: #2E2D2E; color: #FFFFFF; text-decoration: none; padding: 10px 24px; border-radius: 4px;">
                Passwort jetzt festlegen
            </a>
        </p>
        <p style="margin: 0 0 14px; font-size: 13px; color: #55534f;">
            Falls die Schaltfläche nicht funktioniert, kopieren Sie diesen Link in Ihren Browser:<br>
            <a href="{{ $passwordResetUrl }}" style="color: #1D5FA6; word-break: break-all;">{{ $passwordResetUrl }}</a><br>
            Der Link ist zeitlich begrenzt. Ist er abgelaufen, verwenden Sie auf der Anmeldeseite
            die Funktion "Passwort vergessen".
        </p>
    @else
        <p style="margin: 0 0 14px;">
            Ihr Passwort wurde Ihnen auf einem getrennten Weg mitgeteilt. Sollten Sie es nicht erhalten haben,
            verwenden Sie auf der Anmeldeseite die Funktion "Passwort vergessen".
        </p>
    @endif

    @if ($twoFactorRequired)
        <p style="margin: 0 0 12px; padding: 10px 14px; background: #FDF2E0; border: 1px solid #EDD5A8; border-radius: 6px; font-size: 14px;">
            <strong>Hinweis zur Zwei-Faktor-Authentifizierung:</strong> Für Ihre Rolle ist ein zweiter Faktor
            verpflichtend. Nach der ersten Anmeldung werden Sie zur Einrichtung geführt. Sie benötigen dafür eine
            Authenticator-App auf Ihrem Mobiltelefon, etwa Google Authenticator, Microsoft Authenticator, Authy
            oder 1Password.
        </p>
    @endif

    <p style="margin: 0; font-size: 13px; color: #55534f;">
        Bitte behandeln Sie Ihre Zugangsdaten vertraulich und geben Sie sie nicht weiter.
    </p>
@endsection
