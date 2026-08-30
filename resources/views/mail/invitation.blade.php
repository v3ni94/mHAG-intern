@extends('mail.layout')
@section('title', 'Einladung zum Intranet der Müller Holding AG')
@section('subtitle', 'Einladung')

@section('content')
    <p style="margin: 0 0 12px;">Guten Tag,</p>
    <p style="margin: 0 0 12px;">
        Sie wurden zum Intranet der Müller Holding AG eingeladen.
        @if (! empty($roles))
            Ihnen {{ count($roles) === 1 ? 'ist folgende Rolle' : 'sind folgende Rollen' }} zugeordnet:
            <strong>{{ implode(', ', $roles) }}</strong>.
        @endif
    </p>
    <p style="margin: 0 0 20px;">
        Über die folgende Schaltfläche legen Sie Ihr persönliches Passwort fest und aktivieren Ihr Konto:
    </p>
    <p style="margin: 0 0 20px;" align="center">
        <a href="{{ $url }}"
           style="display: inline-block; background: #2E2D2E; color: #FFFFFF; text-decoration: none; padding: 10px 24px; border-radius: 4px;">
            Konto aktivieren
        </a>
    </p>
    <p style="margin: 0 0 12px; font-size: 13px; color: #55534f;">
        Falls die Schaltfläche nicht funktioniert, kopieren Sie diesen Link in Ihren Browser:<br>
        <a href="{{ $url }}" style="color: #1D5FA6; word-break: break-all;">{{ $url }}</a>
    </p>
    <p style="margin: 0 0 12px; font-size: 13px; color: #55534f;">
        Der Link ist bis zum <strong>{{ format_datetime($expiresAt) }} Uhr</strong> gültig und kann nur einmal verwendet werden.
        Sollten Sie diese Einladung nicht erwarten, ignorieren Sie diese E-Mail bitte.
    </p>
@endsection
