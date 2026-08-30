@extends('layouts.app')
@section('title', 'Beschlussregister')
@section('content')
    <x-page-header title="Beschlussregister" label="Beschlüsse">
        <a href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}" class="btn btn-outline-secondary btn-sm" target="_blank">
            <i class="bi bi-file-earmark-pdf"></i> PDF-Export
        </a>
        @can('resolutions.create')
            <a href="{{ route('resolutions.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Beschluss erfassen
            </a>
        @endcan
    </x-page-header>

    {{-- Filter (Abschnitt 98) --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('resolutions.index') }}" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small mb-1" for="year">Jahr</label>
                    <input type="number" name="year" id="year" class="form-control form-control-sm"
                           value="{{ $filters['year'] ?? '' }}" placeholder="z. B. {{ now()->year }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="type">Organ / Beschlussart</label>
                    <select name="type" id="type" class="form-select form-select-sm">
                        <option value="">Alle</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="status">Status</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">Alle</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1" for="q">Volltext</label>
                    <input type="search" name="q" id="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary btn-sm">Filtern</button>
                    <a href="{{ route('resolutions.index') }}" class="btn btn-link btn-sm">Zurücksetzen</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                    <tr>
                        <th>Nr.</th>
                        <th>Datum</th>
                        <th>Art</th>
                        <th>Titel</th>
                        <th>Ergebnis</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($resolutions as $r)
                        <tr>
                            <td><a href="{{ route('resolutions.show', $r) }}">{{ $r->resolution_number }}</a></td>
                            <td>
                                {{ format_date($r->resolved_on) ?: 'nicht erfasst' }}
                                @if (! $r->resolved_on && $r->recorded_at)
                                    <span class="text-muted small" title="Technisches Erfassungsdatum">(erfasst {{ format_date($r->recorded_at) }})</span>
                                @endif
                            </td>
                            <td class="small">{{ $r->type?->label() }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($r->title, 60) }}</td>
                            <td class="small">
                                @switch($r->result)
                                    @case('accepted') Angenommen @break
                                    @case('rejected') Abgelehnt @break
                                    @case('postponed') Vertagt @break
                                    @case('withdrawn') Zurückgezogen @break
                                    @default <span class="text-muted">offen</span>
                                @endswitch
                            </td>
                            <td><x-enum-badge :enum="$r->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="bi-journal-check" message="Keine Beschlüsse gefunden." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($resolutions->hasPages())
            <div class="card-footer">{{ $resolutions->links() }}</div>
        @endif
    </div>
@endsection
