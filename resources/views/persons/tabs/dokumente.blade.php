{{-- Akte: verknüpfte Dokumente (document_links der Entity) --}}
@php
    $hasDocShow = \Illuminate\Support\Facades\Route::has('documents.show');
    $hasDocDownload = \Illuminate\Support\Facades\Route::has('documents.download');
    $canDownload = auth()->user()->can('documents.download');
@endphp

<div class="card">
    <div class="card-header">
        Verknüpfte Dokumente
        <x-help-icon text="Dokumente werden im Dokumentenmodul hochgeladen und mit dieser Akte verknüpft" />
    </div>
    @if ($entity->documentLinks->isEmpty())
        <div class="card-body">
            <x-empty-state icon="bi-folder2-open" message="Keine Dokumente verknüpft." />
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Dateiname</th>
                    <th>Typ</th>
                    <th>Dokumentdatum</th>
                    <th>Beschreibung</th>
                    <th>Status</th>
                    <th class="text-end">Aktionen</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($entity->documentLinks as $link)
                    @php($document = $link->document)
                    @continue(! $document)
                    <tr>
                        <td>
                            @if ($hasDocShow)
                                <a href="{{ route('documents.show', $document) }}" class="text-decoration-none">{{ $document->original_filename }}</a>
                            @else
                                {{ $document->original_filename }}
                            @endif
                        </td>
                        <td>{{ $document->doc_type }}</td>
                        <td>{{ format_date($document->document_date) }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($document->description, 60) }}</td>
                        <td><x-enum-badge :enum="$document->status" /></td>
                        <td class="text-end">
                            @if ($canDownload && $hasDocDownload)
                                <a href="{{ route('documents.download', $document) }}" class="btn btn-sm btn-outline-secondary" title="Herunterladen">
                                    <i class="bi bi-download"></i>
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
