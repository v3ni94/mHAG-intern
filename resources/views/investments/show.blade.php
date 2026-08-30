@extends('layouts.app')
@section('title', 'Beteiligung '.$investment->company?->display_name)
@section('content')
    <x-page-header :title="$investment->company?->display_name ?? 'Beteiligung'" label="Beteiligungsakte">
        <a href="{{ route('investments.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zur Übersicht
        </a>
        @can('shares.prepare')
            <a href="{{ route('investments.edit', $investment) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-pencil"></i> Bearbeiten
            </a>
        @endcan
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Beteiligung</span>
                    @if ($investment->status === 'active')
                        <x-status-badge severity="success" label="Aktiv" />
                    @elseif ($investment->status === 'sold')
                        <x-status-badge severity="neutral" label="Verkauft" />
                    @else
                        <x-status-badge severity="neutral" label="Liquidiert" />
                    @endif
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Unternehmen</dt>
                        <dd class="col-sm-7">{{ $investment->company?->display_name }}</dd>
                        <dt class="col-sm-5">Rechtsform</dt>
                        <dd class="col-sm-7">{{ $investment->company?->company?->legal_form ?: 'nicht erfasst' }}</dd>
                        <dt class="col-sm-5">Beteiligungsquote</dt>
                        <dd class="col-sm-7">{{ $investment->share_percentage !== null ? format_percent($investment->share_percentage) : 'nicht erfasst' }}</dd>
                        <dt class="col-sm-5">Anzahl Anteile</dt>
                        <dd class="col-sm-7">{{ $investment->share_count !== null ? number_format($investment->share_count, 0, ',', '.') : 'nicht erfasst' }}</dd>
                        <dt class="col-sm-5">Anschaffungsdatum</dt>
                        <dd class="col-sm-7">{{ format_date($investment->acquired_on) ?: 'nicht erfasst' }}</dd>
                        <dt class="col-sm-5">Anschaffungskosten</dt>
                        <dd class="col-sm-7">
                            @if ($investment->acquisition_cost !== null)<x-money :amount="$investment->acquisition_cost" />@else nicht erfasst @endif
                        </dd>
                        <dt class="col-sm-5">Aktueller interner Wert</dt>
                        <dd class="col-sm-7">
                            @if ($investment->current_value !== null)
                                <x-money :amount="$investment->current_value" />
                                <span class="text-muted small">(manuell gepflegt)</span>
                            @else
                                <span class="text-muted">nicht bewertet</span>
                            @endif
                        </dd>
                        @if (auth()->user()?->isInternal() && $investment->notes)
                            <dt class="col-sm-5">Interne Notizen</dt>
                            <dd class="col-sm-7">{{ $investment->notes }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Geschäftsführung / Vorstand</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($management as $role)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>{{ $role->person?->display_name }}</span>
                                <span class="text-muted small">{{ $role->role?->label() ?? $role->role }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-muted small">Keine aktiven Organstellungen erfasst.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header">Verknüpfte Beschlüsse</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($resolutionLinks as $link)
                            @if ($link->resolution)
                                <li class="list-group-item">
                                    <a href="{{ route('resolutions.show', $link->resolution) }}">{{ $link->resolution->resolution_number }}</a>
                                    · {{ $link->resolution->title }}
                                </li>
                            @endif
                        @empty
                            <li class="list-group-item text-muted small">Keine Beschlüsse verknüpft.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Dokumente</span>
                    @can('documents.upload')
                        <a href="{{ route('documents.create', ['link_type' => 'investment', 'link_id' => $investment->id]) }}"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-upload"></i> Hochladen
                        </a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($investment->documentLinks as $link)
                            @if ($link->document)
                                <li class="list-group-item small">
                                    <i class="bi bi-file-earmark me-1"></i>
                                    <a href="{{ route('documents.show', $link->document) }}" class="text-decoration-none">{{ $link->document->original_filename }}</a>
                                </li>
                            @endif
                        @empty
                            <li class="list-group-item text-muted small">Keine Dokumente verknüpft.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
