@extends('mail.layout')
@section('title', 'Testnachricht')
@section('subtitle', 'Prüfung der Mailkonfiguration')

@section('content')
    <p style="margin: 0 0 12px;">Diese Nachricht bestätigt, dass der Mailversand des Intranets funktioniert.</p>
    <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin: 0 0 16px; font-size: 14px;">
        <tr>
            <td style="padding: 6px 0; color: #9F9F9F; width: 40%;">Ausgelöst von</td>
            <td style="padding: 6px 0;">{{ $triggeredBy }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #9F9F9F;">Zeitpunkt</td>
            <td style="padding: 6px 0;">{{ format_datetime($sentAt) }} Uhr</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #9F9F9F;">Postausgangsserver</td>
            <td style="padding: 6px 0;">{{ $host }}:{{ $port }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #9F9F9F;">Absenderadresse</td>
            <td style="padding: 6px 0;">{{ $from }}</td>
        </tr>
    </table>
    <p style="margin: 0; font-size: 13px; color: #55534f;">
        Eine Antwort ist nicht erforderlich.
    </p>
@endsection
