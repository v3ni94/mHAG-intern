@extends('layouts.app')

@section('title', 'Systemstatus')

@section('content')
    <x-page-header title="Systemstatus" label="Administration" />

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <x-kpi-card label="App-Version" :value="$appVersion" hint="aus dem Changelog" />
        </div>
        <div class="col-6 col-md-3">
            <x-kpi-card label="PHP / Laravel" :value="$phpVersion" :hint="'Laravel '.$laravelVersion" />
        </div>
        <div class="col-6 col-md-3">
            <x-kpi-card label="Datenbank" :value="$dbOk ? 'OK' : 'Fehler'"
                        :severity="$dbOk ? 'success' : 'danger'" :hint="$dbConnection" />
        </div>
        <div class="col-6 col-md-3">
            <x-kpi-card label="Fehlgeschlagene Logins (24 h)" :value="(string) $failedLogins24h"
                        :severity="$failedLogins24h > 10 ? 'danger' : ($failedLogins24h > 0 ? 'warning' : 'success')" />
        </div>
        <div class="col-6 col-md-3">
            <x-kpi-card label="Offene Hintergrundjobs" :value="$openJobs === null ? 'unbekannt' : (string) $openJobs" />
        </div>
        <div class="col-6 col-md-3">
            <x-kpi-card label="Fehlgeschlagene Jobs" :value="$failedJobs === null ? 'unbekannt' : (string) $failedJobs"
                        :severity="($failedJobs ?? 0) > 0 ? 'danger' : 'success'" />
        </div>
        <div class="col-6 col-md-3">
            @php($lastBackup = $backupStatus['last_run'])
            <x-kpi-card label="Letztes Backup"
                        :value="$lastBackup ? ($lastBackup['success'] ? 'OK' : 'Fehler') : 'noch keins'"
                        :severity="$lastBackup ? ($lastBackup['success'] ? 'success' : 'danger') : 'warning'"
                        :hint="$lastBackup['finished_at'] ?? null" />
        </div>
        <div class="col-6 col-md-3">
            @if ($diskFree !== null)
                <x-kpi-card label="Freier Speicher"
                            :value="number_format($diskFree / 1073741824, 1, ',', '.').' GB'"
                            :hint="$diskTotal ? 'von '.number_format($diskTotal / 1073741824, 1, ',', '.').' GB' : null"
                            :severity="$diskFree < 1073741824 ? 'danger' : null" />
            @else
                <x-kpi-card label="Freier Speicher" value="unbekannt" />
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header">SFTP-Dokumentenablage</div>
                <div class="card-body">
                    @if (is_array($sftpStatus))
                        <dl class="row mb-0 small">
                            <dt class="col-5">Erreichbar</dt>
                            <dd class="col-7">
                                @if ($sftpStatus['online'] ?? false)
                                    <x-status-badge severity="success" label="Online" />
                                @else
                                    <x-status-badge severity="danger" label="Nicht erreichbar" />
                                @endif
                            </dd>
                            <dt class="col-5">Lesen / Schreiben / Umbenennen</dt>
                            <dd class="col-7">
                                {{ ($sftpStatus['read'] ?? false) ? 'OK' : 'Fehler' }} /
                                {{ ($sftpStatus['write'] ?? false) ? 'OK' : 'Fehler' }} /
                                {{ ($sftpStatus['rename'] ?? false) ? 'OK' : 'Fehler' }}
                            </dd>
                            <dt class="col-5">Letzter Test</dt>
                            <dd class="col-7">{{ $sftpStatus['tested_at'] ?? 'unbekannt' }}</dd>
                            @if (! empty($sftpStatus['error']))
                                <dt class="col-5">Fehler</dt>
                                <dd class="col-7 text-danger">{{ $sftpStatus['error'] }}</dd>
                            @endif
                        </dl>
                    @else
                        <p class="text-muted small mb-0">
                            Noch kein SFTP-Verbindungstest durchgeführt.
                            @if (Route::has('admin.sftp.index'))
                                <a href="{{ route('admin.sftp.index') }}">Zum SFTP-Test</a>
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header">Letzte Neuberechnungsfehler</div>
                @if ($recalculationErrors->isNotEmpty())
                    <ul class="list-group list-group-flush">
                        @foreach ($recalculationErrors as $error)
                            <li class="list-group-item small">
                                <div class="d-flex justify-content-between">
                                    <strong>{{ $error->loan?->loan_number ?? 'Darlehen #'.$error->loan_id }}</strong>
                                    <span class="text-muted">{{ format_datetime($error->created_at) }}</span>
                                </div>
                                <div class="text-danger">{{ \Illuminate\Support\Str::limit($error->error, 180) }}</div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="card-body">
                        <x-status-badge severity="success" label="Keine Neuberechnungsfehler" />
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
