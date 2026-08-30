{{-- Dokumente des Darlehens (Abschnitte 57/58): Verknüpfungen über document_links --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Dokumente</span>
        @if (\Illuminate\Support\Facades\Route::has('documents.create'))
            @can('documents.upload')
                <a href="{{ route('documents.create', ['link_type' => 'loan', 'link_id' => $loan->id]) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-upload"></i> Dokument hochladen
                </a>
            @endcan
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Dateiname</th>
                    <th>Typ</th>
                    <th>Dokumentdatum</th>
                    <th>Beschreibung</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($loan->documentLinks as $link)
                    @if ($link->document)
                        <tr>
                            <td class="fw-semibold">{{ $link->document->original_filename }}</td>
                            <td>{{ $link->document->doc_type }}</td>
                            <td>{{ $link->document->document_date ? format_date($link->document->document_date) : '' }}</td>
                            <td class="small text-muted">{{ $link->document->description }}</td>
                            <td class="text-end">
                                @if (\Illuminate\Support\Facades\Route::has('documents.show'))
                                    <a href="{{ route('documents.show', $link->document) }}" class="btn btn-sm btn-outline-secondary" title="Anzeigen"><i class="bi bi-eye"></i></a>
                                @endif
                                @if (\Illuminate\Support\Facades\Route::has('documents.download'))
                                    @can('documents.download')
                                        <a href="{{ route('documents.download', $link->document) }}" class="btn btn-sm btn-outline-secondary" title="Herunterladen"><i class="bi bi-download"></i></a>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="5"><x-empty-state icon="bi-folder2-open" message="Keine Dokumente verknüpft." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
