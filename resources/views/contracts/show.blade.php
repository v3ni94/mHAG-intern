@extends('layouts.app')

@section('title', 'Vertrag '.$contract->contract_number)

@section('content')
    <x-page-header :title="$contract->title" :label="'Vertrag '.$contract->contract_number">
        @switch($contract->status)
            @case('draft')<x-status-badge severity="warning" icon="bi-pencil" label="ENTWURF" />@break
            @case('final')<x-status-badge severity="info" label="Final" />@break
            @case('signed')<x-status-badge severity="success" label="Unterschrieben" />@break
            @case('cancelled')<x-status-badge severity="danger" label="Storniert" />@break
        @endswitch

        <a href="{{ route('contracts.pdf', $contract) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-pdf"></i> {{ $contract->document ? 'PDF herunterladen' : 'Entwurfs-PDF (Vorschau)' }}
        </a>

        @if ($contract->status === 'draft')
            @can('contracts.finalize')
                <x-confirm-form :action="route('contracts.finalize', $contract)" method="POST"
                                confirm="Vertrag jetzt finalisieren? Es wird eine Vertragsnummer vergeben und das PDF in der Dokumentenablage gespeichert."
                                label="Finalisieren" icon="bi-check2-circle" class="btn btn-sm btn-primary" />
            @endcan
        @endif
    </x-page-header>

    @if ($contract->status === 'draft')
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <strong>ENTWURF:</strong> Dieser Vertrag ist noch nicht finalisiert. Die angezeigte Vertragsnummer ist vorläufig.
                @if ($missing !== [])
                    <div class="mt-1">
                        Für die Finalisierung fehlen noch Werte für folgende Platzhalter:
                        @foreach ($missing as $ph)
                            <code class="me-1">&#123;&#123;{{ $ph }}&#125;&#125;</code>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-4 mb-3">
            <div class="card mb-3">
                <div class="card-header">Vertragsdaten</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                        <tr><th class="w-50">Vertragsnummer</th><td>{{ $contract->contract_number }}@if ($contract->status === 'draft') <span class="badge text-bg-warning">vorläufig</span>@endif</td></tr>
                        <tr>
                            <th>Darlehen</th>
                            <td>{{ $contract->loan?->loan_number ?: '–' }}</td>
                        </tr>
                        <tr>
                            <th>Vorlage</th>
                            <td>
                                @if ($contract->templateVersion)
                                    {{ $contract->templateVersion->template?->name }} · Version {{ $contract->templateVersion->version }}
                                @else
                                    –
                                @endif
                            </td>
                        </tr>
                        <tr><th>Angelegt am</th><td>{{ format_datetime($contract->created_at) }}</td></tr>
                        <tr><th>Finalisiert am</th><td>{{ $contract->finalized_at ? format_datetime($contract->finalized_at) : '–' }}</td></tr>
                        <tr>
                            <th>PDF-Dokument</th>
                            <td>
                                @if ($contract->document)
                                    <a href="{{ route('documents.show', $contract->document) }}">{{ $contract->document->original_filename }}</a>
                                @else
                                    –
                                @endif
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Nachträge</div>
                <div class="card-body p-0">
                    @if ($contract->amendments->isEmpty())
                        <div class="p-3 text-muted small">Keine Nachträge erfasst.</div>
                    @else
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Typ</th><th>Wirkungsdatum</th><th>Beschreibung</th></tr></thead>
                            <tbody>
                            @foreach ($contract->amendments->sortByDesc('effective_date') as $amendment)
                                <tr>
                                    <td>{{ $amendmentTypes[$amendment->amendment_type] ?? $amendment->amendment_type }}</td>
                                    <td>@date($amendment->effective_date)</td>
                                    <td class="small">{{ $amendment->description }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
                @can('contracts.update')
                    <div class="card-footer">
                        <form method="POST" action="{{ route('contracts.amendments.store', $contract) }}">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small" for="amendment_type">Nachtragstyp</label>
                                <select name="amendment_type" id="amendment_type" class="form-select form-select-sm @error('amendment_type') is-invalid @enderror" required>
                                    @foreach ($amendmentTypes as $value => $label)
                                        <option value="{{ $value }}" @selected(old('amendment_type') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('amendment_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-2">
                                <label class="form-label small" for="effective_date">Wirkungsdatum</label>
                                <input type="date" name="effective_date" id="effective_date" value="{{ old('effective_date') }}" class="form-control form-control-sm @error('effective_date') is-invalid @enderror">
                                @error('effective_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-2">
                                <label class="form-label small" for="description">Beschreibung</label>
                                <textarea name="description" id="description" rows="2" class="form-control form-control-sm @error('description') is-invalid @enderror" required>{{ old('description') }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-plus-lg"></i> Nachtrag erfassen</button>
                        </form>
                    </div>
                @endcan
            </div>
        </div>

        <div class="col-lg-8 mb-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Vertragstext (Snapshot)</span>
                    @if ($contract->status === 'draft')
                        <x-status-badge severity="warning" icon="bi-pencil" label="ENTWURF" />
                    @endif
                </div>
                <div class="card-body contract-preview" style="background: #fff;">
                    {!! $contract->body_snapshot !!}
                </div>
            </div>
        </div>
    </div>
@endsection
