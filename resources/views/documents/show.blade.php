@extends('layouts.app')

@section('title', 'Dokument '.$document->original_filename)

@section('content')
    <x-page-header :title="$document->original_filename" label="Dokument">
        @can('documents.download')
            <a href="{{ route('documents.download', $document) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-download"></i> Herunterladen
            </a>
        @endcan
        <a href="{{ route('documents.show', [$document, 'pruefen' => 1]) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-shield-check"></i> Integrität prüfen
        </a>
        @can('documents.archive')
            @if ($document->status !== \App\Enums\DocumentStatus::Archived)
                <x-confirm-form :action="route('documents.archive', $document)" method="POST"
                                confirm="Dokument wirklich archivieren?" label="Archivieren"
                                icon="bi-archive" class="btn btn-sm btn-outline-secondary" />
            @endif
        @endcan
        @can('documents.delete')
            <x-confirm-form :action="route('documents.destroy', $document)" method="DELETE"
                            confirm="Dokument endgültig löschen? Die Datei wird aus der Ablage entfernt. Dieser Vorgang kann nicht rückgängig gemacht werden."
                            label="Endgültig löschen" icon="bi-trash" class="btn btn-sm btn-outline-danger" />
        @endcan
    </x-page-header>

    @if ($integrity !== null)
        <div class="mb-3">
            @if ($integrity)
                <x-status-badge severity="success" icon="bi-check-circle-fill" label="Datei unverändert: Die gespeicherte Datei entspricht der SHA-256-Prüfsumme." />
            @else
                <x-status-badge severity="danger" icon="bi-exclamation-octagon-fill" label="Integritätsprüfung fehlgeschlagen: Die gespeicherte Datei weicht von der hinterlegten SHA-256-Prüfsumme ab." />
            @endif
        </div>
    @endif

    <div class="row">
        <div class="col-lg-7 mb-3">
            <div class="card h-100">
                <div class="card-header">Metadaten</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                        <tr><th class="w-25">UUID</th><td><code>{{ $document->uuid }}</code></td></tr>
                        <tr><th>Originaldateiname</th><td>{{ $document->original_filename }}</td></tr>
                        <tr><th>Technischer Dateiname</th><td><code>{{ $document->stored_filename }}</code></td></tr>
                        <tr><th>Dokumenttyp</th><td>{{ $docTypes[$document->doc_type] ?? $document->doc_type }}</td></tr>
                        <tr><th>Kategorie</th><td>{{ $document->category ?: '–' }}</td></tr>
                        <tr><th>Dateigröße</th><td>{{ number_format((int) ceil($document->file_size / 1024), 0, ',', '.') }} KB</td></tr>
                        <tr><th>MIME-Type</th><td>{{ $document->mime_type }}</td></tr>
                        <tr><th>SHA-256</th><td><code class="small">{{ $document->sha256 }}</code></td></tr>
                        <tr><th>Dokumentdatum</th><td>@date($document->document_date)</td></tr>
                        <tr><th>Hochgeladen am</th><td>{{ format_datetime($document->created_at) }}</td></tr>
                        <tr><th>Hochgeladen von</th><td>{{ $document->uploader?->name ?: '–' }}</td></tr>
                        <tr><th>Version</th><td>{{ $document->version }}</td></tr>
                        <tr><th>Status</th><td><x-enum-badge :enum="$document->status" /></td></tr>
                        <tr><th>Ablaufdatum</th><td>@date($document->expires_on)</td></tr>
                        <tr><th>Speicher-Disk</th><td>{{ $document->storage_disk }}</td></tr>
                        <tr><th>Speicherpfad</th><td><code class="small">{{ $document->storage_path }}</code></td></tr>
                        <tr><th>Beschreibung</th><td>{{ $document->description ?: '–' }}</td></tr>
                        <tr>
                            <th>Tags</th>
                            <td>
                                @forelse ($document->tags ?? [] as $tag)
                                    <span class="badge text-bg-light border">{{ $tag }}</span>
                                @empty
                                    –
                                @endforelse
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5 mb-3">
            <div class="card mb-3">
                <div class="card-header">Verknüpfungen</div>
                <div class="card-body">
                    @forelse ($document->links as $link)
                        <div class="mb-1">@include('documents._link_target', ['link' => $link])</div>
                    @empty
                        <div class="text-muted">Keine Verknüpfungen vorhanden.</div>
                    @endforelse

                    @can('documents.upload')
                        <hr>
                        <form method="POST" action="{{ route('documents.link', $document) }}" class="row g-2">
                            @csrf
                            <div class="col-5">
                                <label class="form-label small" for="link_type">Verknüpfungsart</label>
                                <select name="link_type" id="link_type" class="form-select form-select-sm" required>
                                    @foreach ($linkableLabels as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-4">
                                <label class="form-label small" for="link_id">Ziel-ID</label>
                                <input type="number" min="1" name="link_id" id="link_id" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-3 d-flex align-items-end">
                                <button class="btn btn-outline-secondary btn-sm w-100" type="submit">
                                    <i class="bi bi-link-45deg"></i> Verknüpfen
                                </button>
                            </div>
                        </form>
                    @endcan
                </div>
            </div>

            <div class="card">
                <div class="card-header">Versionen</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr><th>Version</th><th>Datum</th><th>Benutzer</th><th class="text-end">Größe</th></tr>
                        </thead>
                        <tbody>
                        @foreach ($document->versions as $version)
                            <tr @class(['table-active' => (int) $version->version === (int) $document->version])>
                                <td>{{ $version->version }} @if ((int) $version->version === (int) $document->version)<span class="badge text-bg-light border">aktuell</span>@endif</td>
                                <td>{{ format_datetime($version->created_at) }}</td>
                                <td>{{ $versionUploaders[$version->uploaded_by] ?? '–' }}</td>
                                <td class="text-end">{{ number_format((int) ceil($version->file_size / 1024), 0, ',', '.') }} KB</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @can('documents.upload')
                    <div class="card-footer">
                        <form method="POST" action="{{ route('documents.versions.store', $document) }}" enctype="multipart/form-data" class="d-flex gap-2">
                            @csrf
                            <input type="file" name="file" class="form-control form-control-sm" required>
                            <button class="btn btn-outline-secondary btn-sm text-nowrap" type="submit">
                                <i class="bi bi-file-earmark-plus"></i> Neue Version
                            </button>
                        </form>
                    </div>
                @endcan
            </div>
        </div>
    </div>
@endsection
