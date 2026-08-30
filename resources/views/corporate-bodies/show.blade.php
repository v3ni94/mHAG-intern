@extends('layouts.app')
@section('title', $body->name)
@section('content')
    <x-page-header :title="$body->name" :label="'Organ der '.($body->company?->display_name ?? 'Gesellschaft')">
        <a href="{{ route('corporate-bodies.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Zur Übersicht
        </a>
    </x-page-header>

    {{-- Stichtagsansicht (Abschnitt 87) --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('corporate-bodies.show', $body) }}" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="col-form-label col-form-label-sm" for="as_of">Wer war am Stichtag im Amt?</label>
                </div>
                <div class="col-auto">
                    <input type="date" name="as_of" id="as_of" class="form-control form-control-sm" value="{{ request('as_of') }}">
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-secondary btn-sm">Anzeigen</button>
                </div>
                @if ($asOf)
                    <div class="col-auto">
                        <x-status-badge severity="info" icon="bi-clock-history" :label="'Stichtag: '.format_date($asOf)" />
                        <a href="{{ route('corporate-bodies.show', $body) }}" class="small ms-2">Aktueller Stand</a>
                    </div>
                @endif
            </form>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header">
                    {{ $asOf ? 'Besetzung am '.format_date($asOf) : 'Aktive Mitglieder' }}
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                            <tr><th>Person</th><th>Rolle</th><th>Vertretung</th><th>Beginn</th><th>Ende</th><th></th></tr>
                            </thead>
                            <tbody>
                            @forelse ($membersAsOf as $member)
                                <tr>
                                    <td>
                                        {{ $member->person?->display_name }}
                                        @if ($member->is_chair)
                                            <x-status-badge severity="info" icon="bi-star-fill" label="Vorsitz" class="ms-1" />
                                        @endif
                                    </td>
                                    <td class="small">{{ $member->role }}</td>
                                    <td class="small">{{ $member->representation ?: '' }}</td>
                                    <td class="small">{{ format_date($member->started_on) ?: 'nicht erfasst' }}</td>
                                    <td class="small">{{ format_date($member->ended_on) }}</td>
                                    <td class="text-end">
                                        @if ($member->status === 'active' && $member->ended_on && $member->ended_on->lte($warningDate))
                                            <x-status-badge severity="warning" icon="bi-hourglass-split" label="Mandat läuft aus" />
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-3">Keine Mitglieder im gewählten Zeitraum.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Vollständige Historie (Mitglieder werden nie gelöscht)</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                            <tr><th>Person</th><th>Rolle</th><th>Beginn</th><th>Ende</th><th>Status</th><th></th></tr>
                            </thead>
                            <tbody>
                            @forelse ($allMembers as $member)
                                <tr class="{{ $member->status === 'ended' ? 'text-muted' : '' }}">
                                    <td>
                                        {{ $member->person?->display_name }}
                                        @if ($member->is_chair)<i class="bi bi-star-fill text-warning ms-1" title="Vorsitz"></i>@endif
                                    </td>
                                    <td class="small">{{ $member->role }}</td>
                                    <td class="small">{{ format_date($member->started_on) ?: 'nicht erfasst' }}</td>
                                    <td class="small">{{ format_date($member->ended_on) }}</td>
                                    <td>
                                        @if ($member->status === 'active')
                                            <x-status-badge severity="success" label="Aktiv" />
                                        @else
                                            <x-status-badge severity="neutral" label="Beendet" />
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @can('shares.prepare')
                                            @if ($member->status === 'active')
                                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse"
                                                        data-bs-target="#endMember{{ $member->id }}">
                                                    Mandat beenden
                                                </button>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                                @can('shares.prepare')
                                    @if ($member->status === 'active')
                                        <tr class="collapse" id="endMember{{ $member->id }}">
                                            <td colspan="6" class="bg-light">
                                                <form method="POST" action="{{ route('corporate-bodies.members.end', [$body, $member]) }}"
                                                      class="row g-2 align-items-end py-1">
                                                    @csrf
                                                    <div class="col-auto">
                                                        <label class="form-label small mb-1" for="ended_on_{{ $member->id }}">Mandatsende</label>
                                                        <input type="date" name="ended_on" id="ended_on_{{ $member->id }}"
                                                               class="form-control form-control-sm" value="{{ now()->format('Y-m-d') }}" required>
                                                    </div>
                                                    <div class="col">
                                                        <label class="form-label small mb-1" for="end_note_{{ $member->id }}">Notiz</label>
                                                        <input type="text" name="note" id="end_note_{{ $member->id }}" class="form-control form-control-sm">
                                                    </div>
                                                    <div class="col-auto">
                                                        <button class="btn btn-danger btn-sm"
                                                                onclick="return confirm('Mandat wirklich beenden? Der Eintrag bleibt in der Historie erhalten.');">
                                                            Beenden
                                                        </button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    @endif
                                @endcan
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-3">Keine Mitglieder erfasst.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            @can('shares.prepare')
                <div class="card">
                    <div class="card-header">Mitglied aufnehmen</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('corporate-bodies.members.store', $body) }}" class="row g-2">
                            @csrf
                            <div class="col-12">
                                <label class="form-label small" for="person_entity_id">Person</label>
                                <select name="person_entity_id" id="person_entity_id" class="form-select form-select-sm @error('person_entity_id') is-invalid @enderror" required>
                                    <option value="">Bitte wählen ...</option>
                                    @foreach ($persons as $person)
                                        <option value="{{ $person->id }}" @selected(old('person_entity_id') == $person->id)>{{ $person->display_name }}</option>
                                    @endforeach
                                </select>
                                @error('person_entity_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small" for="role">Rolle</label>
                                <input type="text" name="role" id="role" class="form-control form-control-sm @error('role') is-invalid @enderror"
                                       value="{{ old('role') }}" placeholder="z. B. Vorstand, Aufsichtsratsmitglied" required>
                                @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small" for="started_on">Beginn</label>
                                <input type="date" name="started_on" id="started_on" class="form-control form-control-sm"
                                       value="{{ old('started_on', now()->format('Y-m-d')) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small" for="ended_on">Mandatsende (optional)</label>
                                <input type="date" name="ended_on" id="ended_on" class="form-control form-control-sm" value="{{ old('ended_on') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small" for="representation">Vertretungsbefugnis</label>
                                <input type="text" name="representation" id="representation" class="form-control form-control-sm"
                                       value="{{ old('representation') }}" placeholder="z. B. einzelvertretungsberechtigt">
                            </div>
                            <div class="col-12 form-check ms-2">
                                <input type="hidden" name="is_chair" value="0">
                                <input type="checkbox" name="is_chair" id="is_chair" value="1" class="form-check-input" @checked(old('is_chair'))>
                                <label class="form-check-label small" for="is_chair">Vorsitz</label>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary btn-sm">Aufnehmen</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection
