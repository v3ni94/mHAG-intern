@extends('layouts.app')
@section('title', 'Beschluss '.$resolution->resolution_number)
@section('content')
    <x-page-header :title="$resolution->resolution_number.' · '.$resolution->title" label="Beschluss">
        <a href="{{ route('resolutions.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zum Register
        </a>
        <a href="{{ route('resolutions.pdf', $resolution) }}" class="btn btn-outline-secondary btn-sm" target="_blank">
            <i class="bi bi-file-earmark-pdf"></i> {{ $resolution->document_id ? 'PDF herunterladen' : 'PDF-Vorschau' }}
        </a>
        @can('resolutions.update')
            @if (in_array($resolution->status?->value, ['draft', 'submitted', 'review', 'voting', 'postponed'], true))
                <a href="{{ route('resolutions.edit', $resolution) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-pencil"></i> Bearbeiten
                </a>
            @endif
        @endcan
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Beschlussdaten</span>
                    <x-enum-badge :enum="$resolution->status" />
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Beschlussnummer</dt>
                        <dd class="col-sm-8">{{ $resolution->resolution_number }}</dd>
                        <dt class="col-sm-4">Gesellschaft</dt>
                        <dd class="col-sm-8">{{ $resolution->company?->display_name }}</dd>
                        <dt class="col-sm-4">Beschlussart</dt>
                        <dd class="col-sm-8">{{ $resolution->type?->label() }}</dd>
                        <dt class="col-sm-4">Antragsteller</dt>
                        <dd class="col-sm-8">{{ $resolution->applicant?->display_name ?? 'nicht erfasst' }}</dd>
                        <dt class="col-sm-4">Tatsächliches Beschlussdatum</dt>
                        <dd class="col-sm-8">{{ format_date($resolution->resolved_on) ?: 'noch nicht erfasst' }}</dd>
                        <dt class="col-sm-4">Technisches Erfassungsdatum</dt>
                        <dd class="col-sm-8">{{ format_datetime($resolution->recorded_at) }}</dd>
                        <dt class="col-sm-4">Ergebnis</dt>
                        <dd class="col-sm-8">
                            @switch($resolution->result)
                                @case('accepted') <x-status-badge severity="success" label="Angenommen" /> @break
                                @case('rejected') <x-status-badge severity="danger" label="Abgelehnt" /> @break
                                @case('postponed') <x-status-badge severity="warning" label="Vertagt" /> @break
                                @case('withdrawn') <x-status-badge severity="neutral" label="Zurückgezogen" /> @break
                                @default <span class="text-muted">offen</span>
                            @endswitch
                        </dd>
                    </dl>
                </div>
            </div>

            @if ($resolution->motion)
                <div class="card mb-3">
                    <div class="card-header">Antrag</div>
                    <div class="card-body" style="white-space: pre-wrap;">{{ $resolution->motion }}</div>
                </div>
            @endif
            @if ($resolution->reasoning)
                <div class="card mb-3">
                    <div class="card-header">Begründung</div>
                    <div class="card-body" style="white-space: pre-wrap;">{{ $resolution->reasoning }}</div>
                </div>
            @endif
            @if ($resolution->resolution_text)
                <div class="card mb-3">
                    <div class="card-header">Beschlusstext</div>
                    <div class="card-body" style="white-space: pre-wrap;">{{ $resolution->resolution_text }}</div>
                </div>
            @endif

            @if ($resolution->conflict_of_interest)
                <div class="card mb-3 border-warning">
                    <div class="card-header text-warning-emphasis">
                        <i class="bi bi-exclamation-triangle me-1"></i> Interessenkonflikt dokumentiert
                    </div>
                    <div class="card-body" style="white-space: pre-wrap;">{{ $resolution->conflict_notes }}</div>
                </div>
            @endif

            {{-- Abstimmung (Abschnitt 94) --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Teilnehmer &amp; Abstimmung</span>
                    <span class="small">
                        Ja: {{ $summary['yes'] }} · Nein: {{ $summary['no'] }} ·
                        Enthaltung: {{ $summary['abstain'] }} · Nicht teilgenommen: {{ $summary['absent'] }}
                    </span>
                </div>
                <div class="card-body p-0">
                    @php($votable = ! in_array($resolution->status?->value, ['signed', 'completed', 'archived'], true))
                    <form method="POST" action="{{ route('resolutions.vote', $resolution) }}">
                        @csrf
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <thead>
                                <tr>
                                    <th>Teilnehmer</th>
                                    <th>Rolle</th>
                                    <th>Stimme</th>
                                    <th class="text-center" title="Interessenkonflikt: von Beratung ausgeschlossen">o. Beratung</th>
                                    <th class="text-center" title="Interessenkonflikt: von Abstimmung ausgeschlossen">o. Abstimmung</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse ($resolution->participants as $participant)
                                    <tr>
                                        <td>{{ $participant->entity?->display_name }}</td>
                                        <td class="small">{{ $participant->role }}</td>
                                        <td style="max-width: 180px;">
                                            @can('resolutions.vote')
                                                @if ($votable)
                                                    <select name="votes[{{ $participant->id }}]" class="form-select form-select-sm">
                                                        <option value="">Keine Angabe</option>
                                                        <option value="yes" @selected($participant->vote?->vote?->value === 'yes')>Ja</option>
                                                        <option value="no" @selected($participant->vote?->vote?->value === 'no')>Nein</option>
                                                        <option value="abstain" @selected($participant->vote?->vote?->value === 'abstain')>Enthaltung</option>
                                                        <option value="absent" @selected($participant->vote?->vote?->value === 'absent')>Nicht teilgenommen</option>
                                                    </select>
                                                @else
                                                    {{ $participant->vote?->vote?->label() ?? 'Keine Angabe' }}
                                                @endif
                                            @else
                                                {{ $participant->vote?->vote?->label() ?? 'Keine Angabe' }}
                                            @endcan
                                        </td>
                                        <td class="text-center">
                                            @if ($votable && auth()->user()?->can('resolutions.vote'))
                                                <input type="hidden" name="excluded_from_deliberation[{{ $participant->id }}]" value="0">
                                                <input type="checkbox" name="excluded_from_deliberation[{{ $participant->id }}]" value="1"
                                                       class="form-check-input" @checked($participant->excluded_from_deliberation)>
                                            @else
                                                {{ $participant->excluded_from_deliberation ? 'ja' : '' }}
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if ($votable && auth()->user()?->can('resolutions.vote'))
                                                <input type="hidden" name="excluded_from_vote[{{ $participant->id }}]" value="0">
                                                <input type="checkbox" name="excluded_from_vote[{{ $participant->id }}]" value="1"
                                                       class="form-check-input" @checked($participant->excluded_from_vote)>
                                            @else
                                                {{ $participant->excluded_from_vote ? 'ja' : '' }}
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-3">Keine Teilnehmer erfasst.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        @can('resolutions.vote')
                            @if ($votable && $resolution->participants->isNotEmpty())
                                <div class="p-2 border-top">
                                    <button class="btn btn-primary btn-sm">Abstimmung speichern</button>
                                </div>
                            @endif
                        @endcan
                    </form>
                </div>
                <div class="card-footer small text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Das System zählt nur die dokumentierten Stimmen. Das System bewertet keine gesetzlichen Mehrheiten.
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            {{-- Workflow (Abschnitt 93) --}}
            <div class="card mb-3">
                <div class="card-header">Workflow</div>
                <div class="card-body">
                    @can('resolutions.update')
                        @if (! in_array($resolution->status?->value, ['signed', 'completed', 'archived'], true))
                            <form method="POST" action="{{ route('resolutions.status', $resolution) }}" class="row g-2 mb-3">
                                @csrf
                                <div class="col-8">
                                    <select name="status" class="form-select form-select-sm">
                                        @foreach ($manualStatuses as $status)
                                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-4">
                                    <button class="btn btn-outline-secondary btn-sm w-100">Status setzen</button>
                                </div>
                                <div class="col-12 form-text">
                                    Angenommen, abgelehnt, vertagt oder zurückgezogen dokumentiert zugleich das Ergebnis.
                                </div>
                            </form>
                        @endif
                    @endcan

                    @can('resolutions.finalize')
                        @if ($resolution->result !== null && ! in_array($resolution->status?->value, ['signed', 'completed', 'archived'], true))
                            <x-confirm-form :action="route('resolutions.finalize', $resolution)"
                                            confirm="Beschluss-PDF erzeugen, ablegen und zur Unterschrift stellen?"
                                            label="Finalisieren: PDF erzeugen und zur Unterschrift"
                                            icon="bi-file-earmark-check" class="btn btn-primary btn-sm w-100 mb-2" />
                        @endif
                    @endcan

                    @can('resolutions.sign')
                        @if ($resolution->document_id && in_array($resolution->status?->value, ['for_signature'], true))
                            <a class="btn btn-outline-secondary btn-sm w-100"
                               href="{{ route('signatures.create', ['subject_type' => 'resolution', 'subject_id' => $resolution->id]) }}">
                                <i class="bi bi-pen"></i> Signaturanfrage erstellen
                            </a>
                        @endif
                    @endcan

                    @if ($resolution->status?->value === 'signed')
                        <div class="alert alert-success mb-0 mt-2">
                            <i class="bi bi-check-circle me-1"></i> Der Beschluss ist unterschrieben.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Signaturanfragen --}}
            <div class="card mb-3">
                <div class="card-header">Signaturen</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($resolution->signatureRequests as $sr)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('signatures.show', $sr) }}">Anfrage #{{ $sr->id }}</a>
                                <x-enum-badge :enum="$sr->status" />
                            </li>
                        @empty
                            <li class="list-group-item text-muted small">Keine Signaturanfragen.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            {{-- Verknüpfungen (Abschnitt 96) --}}
            <div class="card">
                <div class="card-header">Verknüpfte Vorgänge</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($resolution->links as $link)
                            <li class="list-group-item d-flex justify-content-between align-items-center small">
                                <span>
                                    {{ class_basename($link->linkable_type) }} #{{ $link->linkable_id }}
                                    @if ($link->linkable && method_exists($link->linkable, 'getAttribute'))
                                        · {{ $link->linkable->display_name
                                            ?? $link->linkable->transaction_number
                                            ?? $link->linkable->loan_number
                                            ?? $link->linkable->contract_number
                                            ?? $link->linkable->name
                                            ?? '' }}
                                    @endif
                                </span>
                                @can('resolutions.update')
                                    <x-confirm-form :action="route('resolutions.links.destroy', [$resolution, $link])" method="DELETE"
                                                    confirm="Verknüpfung entfernen?" label="Entfernen" class="btn btn-link btn-sm text-danger p-0" />
                                @endcan
                            </li>
                        @empty
                            <li class="list-group-item text-muted small">Keine Verknüpfungen.</li>
                        @endforelse
                    </ul>
                </div>
                @can('resolutions.update')
                    <div class="card-footer">
                        <form method="POST" action="{{ route('resolutions.links.store', $resolution) }}" class="row g-2">
                            @csrf
                            <div class="col-5">
                                <select name="linkable_type" class="form-select form-select-sm">
                                    @foreach ($linkableTypes as $key => [$class, $label])
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-4">
                                <input type="number" name="linkable_id" class="form-control form-control-sm" placeholder="ID" min="1" required>
                            </div>
                            <div class="col-3">
                                <button class="btn btn-outline-secondary btn-sm w-100">Verknüpfen</button>
                            </div>
                        </form>
                    </div>
                @endcan
            </div>
        </div>
    </div>
@endsection
