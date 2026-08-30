@extends('layouts.app')

@section('title', 'SFTP-Status')

@section('content')
    <x-page-header title="SFTP-Dateiablage" label="Administration">
        <form method="POST" action="{{ route('admin.sftp.test') }}">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-plug"></i> SFTP-Verbindung testen
            </button>
        </form>
    </x-page-header>

    @include('partials.config-cache-hint')

    <div class="row">
        <div class="col-lg-5 mb-3">
            <div class="card h-100">
                <div class="card-header">Konfiguration (ohne Zugangsdaten)</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                        <tr>
                            <th class="w-50">Aktive Dokumenten-Disk</th>
                            <td>
                                @if ($activeDisk === 'sftp')
                                    <x-status-badge severity="info" icon="bi-hdd-network" label="SFTP" />
                                @else
                                    <x-status-badge severity="neutral" icon="bi-hdd" label="Lokal (documents)" />
                                @endif
                            </td>
                        </tr>
                        @foreach ($configuration as $label => $value)
                            <tr>
                                <th>{{ $label }}</th>
                                <td>{{ $value ?? 'Nicht gesetzt' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-muted small">
                    Passwörter, private Schlüssel und Passphrasen werden aus Sicherheitsgründen nicht angezeigt.
                    Die Konfiguration erfolgt über Umgebungsvariablen (SFTP_HOST, SFTP_PORT, SFTP_USERNAME, ...).
                </div>
            </div>
        </div>

        <div class="col-lg-7 mb-3">
            <div class="card mb-3">
                <div class="card-header">Statuskarte</div>
                <div class="card-body">
                    @if (! $lastTest)
                        <x-empty-state icon="bi-hdd-network" message="Noch kein Verbindungstest durchgeführt." />
                    @else
                        @php($ok = ($lastTest['configured'] ?? false) && empty($lastTest['error']) && ($lastTest['read'] ?? false) && ($lastTest['write'] ?? false) && ($lastTest['rename'] ?? false))
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @if (! ($lastTest['configured'] ?? false))
                                <x-status-badge severity="neutral" icon="bi-dash-circle" label="Nicht konfiguriert" />
                            @elseif ($ok)
                                <x-status-badge severity="success" icon="bi-check-circle-fill" label="Online" />
                            @else
                                <x-status-badge severity="danger" icon="bi-x-octagon-fill" label="Offline / fehlerhaft" />
                            @endif
                        </div>

                        <div class="row g-2">
                            <div class="col-sm-4">
                                <x-kpi-card label="Lesetest"
                                            :value="($lastTest['read'] ?? false) ? 'OK' : 'Fehlgeschlagen'"
                                            :severity="($lastTest['read'] ?? false) ? 'success' : (($lastTest['configured'] ?? false) ? 'danger' : null)"
                                            icon="bi-eye" />
                            </div>
                            <div class="col-sm-4">
                                <x-kpi-card label="Schreibtest"
                                            :value="($lastTest['write'] ?? false) ? 'OK' : 'Fehlgeschlagen'"
                                            :severity="($lastTest['write'] ?? false) ? 'success' : (($lastTest['configured'] ?? false) ? 'danger' : null)"
                                            icon="bi-pencil-square" />
                            </div>
                            <div class="col-sm-4">
                                <x-kpi-card label="Umbenennungstest"
                                            :value="($lastTest['rename'] ?? false) ? 'OK' : 'Fehlgeschlagen'"
                                            :severity="($lastTest['rename'] ?? false) ? 'success' : (($lastTest['configured'] ?? false) ? 'danger' : null)"
                                            icon="bi-arrow-left-right" />
                            </div>
                        </div>

                        <table class="table table-sm mt-3 mb-0">
                            <tbody>
                            <tr><th class="w-50">Letzter Test</th><td>{{ $lastTest['tested_at'] ?? '–' }}</td></tr>
                            <tr><th>Letzter erfolgreicher Test</th><td>{{ $lastSuccess ?: '–' }}</td></tr>
                            <tr>
                                <th>Letzter Fehler</th>
                                <td>
                                    @if (is_array($lastError))
                                        <span class="text-danger">{{ $lastError['message'] ?? '' }}</span>
                                        <div class="text-muted small">{{ $lastError['at'] ?? '' }}</div>
                                    @else
                                        –
                                    @endif
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-body text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    Der Verbindungstest legt eine Testdatei an, liest sie zurück, benennt sie um und löscht sie
                    anschließend. Ist SFTP_HOST nicht gesetzt, gilt der Status als „nicht konfiguriert" und die
                    Dokumentenablage nutzt die lokale Disk.
                </div>
            </div>
        </div>
    </div>
@endsection
