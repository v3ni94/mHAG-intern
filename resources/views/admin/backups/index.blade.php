@extends('layouts.app')

@section('title', 'Backups')

@section('content')
    <x-page-header title="Backups" label="Administration">
        <x-confirm-form :action="route('admin.backups.run')"
                        confirm="Jetzt ein Datenbank-Backup erstellen?"
                        label="Backup jetzt ausführen" icon="bi-play-fill" class="btn btn-sm btn-primary" />
    </x-page-header>

    @php($lastRun = $status['last_run'])
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <x-kpi-card label="Letzter Lauf"
                        :value="$lastRun ? ($lastRun['success'] ? 'Erfolgreich' : 'Fehlgeschlagen') : 'noch keiner'"
                        :severity="$lastRun ? ($lastRun['success'] ? 'success' : 'danger') : 'warning'"
                        :hint="$lastRun['finished_at'] ?? null" />
        </div>
        <div class="col-6 col-md-3">
            <x-kpi-card label="Letzte Datei" :value="$lastRun['file'] ?? '–'"
                        :hint="isset($lastRun['size']) && $lastRun['size'] ? number_format($lastRun['size'] / 1048576, 2, ',', '.').' MB' : null" />
        </div>
        <div class="col-6 col-md-3">
            <x-kpi-card label="Vorhandene Backups" :value="(string) count($status['files'])" />
        </div>
        <div class="col-6 col-md-3">
            <x-kpi-card label="Ablageort" value="Server" :hint="$status['path']" />
        </div>
    </div>

    @if ($lastRun && ! $lastRun['success'])
        <div class="alert alert-danger">
            <strong>Letzter Backup-Fehler:</strong> {{ $lastRun['error'] ?? 'unbekannter Fehler' }}
        </div>
    @endif

    <div class="card">
        <div class="card-header">Backup-Dateien</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Datei</th>
                        <th class="num">Größe</th>
                        <th>Erstellt</th>
                        <th class="text-end">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($status['files'] as $file)
                        <tr>
                            <td class="font-monospace small">{{ $file['name'] }}</td>
                            <td class="num small">{{ number_format($file['size'] / 1048576, 2, ',', '.') }} MB</td>
                            <td class="small">{{ format_datetime($file['modified_at']) }}</td>
                            <td class="text-end">
                                @if (auth()->user()->hasRole('Administrator'))
                                    <a href="{{ route('admin.backups.download', $file['name']) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-download"></i> Herunterladen
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-empty-state icon="bi-archive" message="Noch keine Backup-Dateien vorhanden." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-muted small mt-3">
        Backups laufen zusätzlich täglich um 02:00 Uhr automatisch (Scheduler). Der Wiederherstellungsprozess
        ist in docs/RESTORE.md dokumentiert. Datenbank und Dokumentenablage müssen gemeinsam konsistent
        wiederhergestellt werden.
    </p>
@endsection
