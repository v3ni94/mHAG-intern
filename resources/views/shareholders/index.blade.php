@extends('layouts.app')
@section('title', 'Aktionäre')
@section('content')
    <x-page-header title="Aktionäre" label="Müller Holding AG">
        @can('shares.prepare')
            <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#newShareholder">
                <i class="bi bi-plus-lg"></i> Aktionär anlegen
            </button>
        @endcan
    </x-page-header>

    {{-- Stichtagsformular (Abschnitt 81) --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('shareholders.index') }}" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label for="as_of" class="col-form-label col-form-label-sm">Aktionärsstruktur zum Stichtag</label>
                </div>
                <div class="col-auto">
                    <input type="date" name="as_of" id="as_of" class="form-control form-control-sm"
                           value="{{ request('as_of', $asOf->format('Y-m-d')) }}">
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-secondary btn-sm">Anzeigen</button>
                </div>
                @if ($isHistorical)
                    <div class="col-auto">
                        <x-status-badge severity="info" icon="bi-clock-history" :label="'Historischer Stand: '.format_date($asOf)" />
                        <a href="{{ route('shareholders.index') }}" class="small ms-2">Aktueller Stand</a>
                    </div>
                @endif
            </form>
        </div>
    </div>

    @can('shares.prepare')
        <div class="collapse {{ $errors->hasAny(['entity_id', 'shareholder_number', 'joined_on']) ? 'show' : '' }} mb-3" id="newShareholder">
            <div class="card">
                <div class="card-header">Aktionär anlegen</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('shareholders.store') }}" class="row g-3">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label required" for="entity_id">Person oder Unternehmen</label>
                            <select name="entity_id" id="entity_id" class="form-select @error('entity_id') is-invalid @enderror" required>
                                <option value="">Bitte wählen ...</option>
                                @foreach ($availableEntities as $entity)
                                    <option value="{{ $entity->id }}" @selected(old('entity_id') == $entity->id)>{{ $entity->display_name }}</option>
                                @endforeach
                            </select>
                            @error('entity_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="shareholder_number">Aktionärsnummer</label>
                            <input type="text" name="shareholder_number" id="shareholder_number"
                                   class="form-control @error('shareholder_number') is-invalid @enderror"
                                   value="{{ old('shareholder_number') }}" placeholder="leer = automatisch (AKT-...)">
                            @error('shareholder_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="joined_on">Eintritt</label>
                            <input type="date" name="joined_on" id="joined_on" class="form-control" value="{{ old('joined_on') }}">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-primary w-100">Anlegen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Bestand zum {{ format_date($asOf) }} (berechnet aus wirksamen Bewegungen)</span>
            <span class="small text-muted">
                Ausgegeben: {{ number_format($outstanding, 0, ',', '.') }} von {{ number_format($totalShares, 0, ',', '.') }} Aktien
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                    <tr>
                        <th>Aktionärsnummer</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th class="text-end">Aktien</th>
                        <th class="text-end">Anteil</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row['shareholder']->shareholder_number }}</td>
                            <td>
                                <a href="{{ route('shareholders.show', $row['shareholder']) }}">
                                    {{ $row['shareholder']->entity?->display_name }}
                                </a>
                            </td>
                            <td>
                                @if ($row['shareholder']->status === 'active')
                                    <x-status-badge severity="success" label="Aktiv" />
                                @else
                                    <x-status-badge severity="neutral" label="Inaktiv" />
                                @endif
                            </td>
                            <td class="text-end">{{ number_format($row['shares'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ format_percent($row['percentage']) }}</td>
                            <td class="text-end">
                                <a href="{{ route('shareholders.show', $row['shareholder']) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-folder2-open"></i> Akte
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="bi-people" message="Keine Aktionäre vorhanden." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Offizielle Aktionärslisten (Abschnitte 82/83) --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Offizielle Aktionärslisten (unveränderliche Snapshots)</span>
            @can('shares.list')
                <form method="POST" action="{{ route('shareholders.list.create') }}" class="d-flex gap-2 align-items-center">
                    @csrf
                    <label class="small text-muted" for="snapshot_as_of">Stichtag</label>
                    <input type="date" name="as_of" id="snapshot_as_of" class="form-control form-control-sm" style="width: auto;"
                           value="{{ request('as_of', now()->format('Y-m-d')) }}">
                    <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF erzeugen</button>
                </form>
            @endcan
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                    <tr>
                        <th>Dokumentnummer</th>
                        <th>Stichtag</th>
                        <th>Erstellt</th>
                        <th>Ersteller</th>
                        <th>SHA-256</th>
                        <th>Signaturstatus</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($snapshots as $snapshot)
                        <tr>
                            <td>{{ $snapshot->document_number }}</td>
                            <td>{{ format_date($snapshot->as_of_date) }}</td>
                            <td>{{ format_datetime($snapshot->created_at) }}</td>
                            <td>{{ $snapshot->creator?->name }}</td>
                            <td class="text-muted small font-monospace">{{ substr((string) $snapshot->sha256, 0, 16) }}...</td>
                            <td>
                                @if ($snapshot->signature_status === 'signed')
                                    <x-status-badge severity="success" label="Unterschrieben" />
                                @else
                                    <x-status-badge severity="neutral" label="Ohne Unterschrift" />
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($snapshot->document_id)
                                    <a href="{{ route('shareholders.list.download', $snapshot) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-download"></i> PDF
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">Noch keine Aktionärsliste erzeugt.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
