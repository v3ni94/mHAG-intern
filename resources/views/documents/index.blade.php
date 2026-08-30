@extends('layouts.app')

@section('title', 'Dokumente')

@section('content')
    <x-page-header title="Dokumente" label="Dokumentenmanagement">
        @can('documents.upload')
            <a href="{{ route('documents.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-upload"></i> Dokument hochladen
            </a>
        @endcan
    </x-page-header>

    <form method="GET" action="{{ route('documents.index') }}" class="card mb-3">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="filter-q">Volltextsuche</label>
                <input type="search" name="q" id="filter-q" value="{{ request('q') }}" class="form-control form-control-sm"
                       placeholder="Dateiname, Beschreibung, Tags ...">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="filter-doc-type">Dokumenttyp</label>
                <select name="doc_type" id="filter-doc-type" class="form-select form-select-sm">
                    <option value="">Alle Typen</option>
                    @foreach ($docTypes as $value => $label)
                        <option value="{{ $value }}" @selected(request('doc_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="filter-category">Kategorie</label>
                <input type="text" name="category" id="filter-category" value="{{ request('category') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="filter-tag">Tag</label>
                <input type="text" name="tag" id="filter-tag" value="{{ request('tag') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-funnel"></i> Filtern</button>
                <a href="{{ route('documents.index') }}" class="btn btn-outline-secondary btn-sm">Zurücksetzen</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-body p-0">
            @if ($documents->isEmpty())
                <x-empty-state icon="bi-folder2-open" message="Keine Dokumente gefunden." />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Dokument</th>
                            <th>Typ</th>
                            <th>Kategorie</th>
                            <th>Dokumentdatum</th>
                            <th class="text-end">Größe</th>
                            <th>Version</th>
                            <th>Status</th>
                            <th>Verknüpfungen</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($documents as $document)
                            <tr>
                                <td>
                                    <a href="{{ route('documents.show', $document) }}" class="fw-bold text-decoration-none">
                                        <i class="bi bi-file-earmark me-1"></i>{{ $document->original_filename }}
                                    </a>
                                    @if ($document->description)
                                        <div class="text-muted small">{{ \Illuminate\Support\Str::limit($document->description, 80) }}</div>
                                    @endif
                                </td>
                                <td>{{ $docTypes[$document->doc_type] ?? $document->doc_type }}</td>
                                <td>{{ $document->category }}</td>
                                <td>@date($document->document_date)</td>
                                <td class="text-end">{{ number_format((int) ceil($document->file_size / 1024), 0, ',', '.') }} KB</td>
                                <td>{{ $document->version }}</td>
                                <td><x-enum-badge :enum="$document->status" /></td>
                                <td>
                                    @forelse ($document->links as $link)
                                        <div class="small">@include('documents._link_target', ['link' => $link])</div>
                                    @empty
                                        <span class="text-muted small">Keine</span>
                                    @endforelse
                                </td>
                                <td class="text-end">
                                    @can('documents.download')
                                        <a href="{{ route('documents.download', $document) }}" class="btn btn-sm btn-outline-secondary" title="Herunterladen">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="mt-3">
        {{ $documents->links() }}
    </div>
@endsection
