@extends('layouts.app')

@section('title', 'DocuSign')

@section('content')
    <x-page-header title="DocuSign" label="Administration">
        <form method="POST" action="{{ route('admin.docusign.test') }}">
            @csrf
            <button class="btn btn-sm btn-primary">
                <i class="bi bi-plug"></i> Verbindung testen
            </button>
        </form>
    </x-page-header>

    <div class="alert alert-info small">
        Die Anbindung erfolgt über JWT Grant, also ohne Anmeldung einer Person. Der Verbindungstest meldet sich an
        und fragt den API-Benutzer ab; es wird kein Umschlag erzeugt und nichts versendet.
        Geheimnisse werden hier nicht angezeigt.
    </div>

    @if ($aktiverAnbieter !== 'docusign')
        <div class="alert alert-warning small">
            <strong>Aktiver Signaturweg ist derzeit "{{ $aktiverAnbieter }}".</strong>
            Solange in der Konfiguration <code>SIGNATURE_PROVIDER=docusign</code> nicht gesetzt ist, läuft der
            Signaturprozess weiter über den manuellen Weg. Die Angaben unten können bereits geprüft werden.
        </div>
    @endif

    @if ($fehlend !== [])
        <div class="alert alert-danger small">
            <strong>DocuSign ist nicht vollständig konfiguriert.</strong> Es wird nichts versendet und nichts
            abgefragt, solange folgende Angaben fehlen:
            <ul class="mb-0">
                @foreach ($fehlend as $eintrag)
                    <li>{{ $eintrag }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">Konfiguration</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tbody>
                            @foreach ($konfiguration as $bezeichnung => $wert)
                                <tr>
                                    <th style="width: 45%;">{{ $bezeichnung }}</th>
                                    <td class="small">
                                        @if ($wert === null || $wert === '')
                                            <span class="text-danger">nicht hinterlegt</span>
                                        @else
                                            <span class="text-break">{{ $wert }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">Letzter Verbindungstest</div>
                <div class="card-body small">
                    @if (! $letzterTest)
                        <span class="text-muted">Noch kein Test durchgeführt.</span>
                    @else
                        <div class="mb-2">
                            <x-status-badge :severity="($letzterTest['ok'] ?? false) ? 'success' : 'danger'"
                                            :label="($letzterTest['ok'] ?? false) ? 'Erfolgreich' : 'Fehlgeschlagen'" />
                            <span class="text-muted ms-2">{{ $letzterTest['tested_at'] ?? '' }}</span>
                        </div>
                        @if (! empty($letzterTest['user_name']))
                            <div>Angemeldet als: <strong>{{ $letzterTest['user_name'] }}</strong>
                                @if (! empty($letzterTest['user_email']))
                                    ({{ $letzterTest['user_email'] }})
                                @endif
                            </div>
                        @endif
                        @if (! empty($letzterTest['accounts']))
                            <div class="mt-2">Konten des Benutzers:</div>
                            <ul class="mb-2">
                                @foreach ($letzterTest['accounts'] as $konto)
                                    <li>
                                        {{ $konto['account_name'] ?? 'ohne Namen' }}
                                        <span class="text-muted">{{ $konto['account_id'] ?? '' }}</span>
                                        @if (! empty($konto['base_uri']))
                                            <br><span class="text-muted">Basis: {{ $konto['base_uri'] }}/restapi</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if (! empty($letzterTest['error']))
                            <div class="text-danger">{{ $letzterTest['error'] }}</div>
                        @endif
                    @endif
                </div>
            </div>

            @if ($zustimmungsadresse)
                <div class="card">
                    <div class="card-header">Einmalige Zustimmung</div>
                    <div class="card-body small">
                        <p>
                            Vor der ersten Anmeldung muss der API-Benutzer der Anwendung einmalig zustimmen.
                            Ohne Zustimmung antwortet DocuSign mit <code>consent_required</code>.
                        </p>
                        <p class="mb-0">
                            <a href="{{ $zustimmungsadresse }}" target="_blank" rel="noopener">
                                Zustimmungsadresse öffnen
                            </a>
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">Einrichtung in Kurzform</div>
        <div class="card-body small">
            <ol class="mb-0">
                <li>In DocuSign unter Einstellungen, Apps und Schlüssel eine App anlegen und den
                    Integrationsschlüssel notieren.</li>
                <li>Ein RSA-Schlüsselpaar erzeugen lassen und den privaten Schlüssel als Datei auf dem Server
                    ablegen, außerhalb des öffentlichen Verzeichnisses.</li>
                <li>API-Konto (Account-ID), API-Benutzer (User-ID) und Basis-URL notieren.</li>
                <li>Angaben in der Konfigurationsdatei .env eintragen und die Zwischenspeicher leeren.</li>
                <li>Zustimmungsadresse einmal aufrufen und die Zustimmung erteilen.</li>
                <li>Verbindung testen. Erst danach <code>SIGNATURE_PROVIDER=docusign</code> setzen.</li>
                <li>Optional den Rückkanal einrichten: in DocuSign Connect eine Benachrichtigung mit HMAC auf die
                    oben genannte Adresse legen und dasselbe Geheimnis in der .env hinterlegen.</li>
            </ol>
        </div>
    </div>
@endsection
