@extends('layouts.app')
@section('title', 'Vorstand & Aufsichtsrat')
@section('content')
    <x-page-header title="Vorstand &amp; Aufsichtsrat" label="Organe der Müller Holding AG" />

    {{-- Stichtagsabfrage (Abschnitt 87): Wer war am X im Amt? --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('corporate-bodies.index') }}" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="col-form-label col-form-label-sm" for="as_of">Besetzung zum Stichtag</label>
                </div>
                <div class="col-auto">
                    <input type="date" name="as_of" id="as_of" class="form-control form-control-sm" value="{{ request('as_of') }}">
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-secondary btn-sm">Anzeigen</button>
                </div>
                @if ($asOf)
                    <div class="col-auto">
                        <x-status-badge severity="info" icon="bi-clock-history" :label="'Historischer Stand: '.format_date($asOf)" />
                        <a href="{{ route('corporate-bodies.index') }}" class="small ms-2">Aktueller Stand</a>
                    </div>
                @endif
            </form>
        </div>
    </div>

    <div class="row g-3">
        @forelse ($bodies as $entry)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi {{ $entry['body']->type === 'board' ? 'bi-person-badge' : 'bi-people' }} me-1"></i>
                            {{ $entry['body']->name }}
                        </span>
                        <a href="{{ route('corporate-bodies.show', $entry['body']) }}" class="small">Details &amp; Historie</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                <tr>
                                    <th>Person</th>
                                    <th>Rolle</th>
                                    <th>Beginn</th>
                                    <th>Ende</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse ($entry['members'] as $member)
                                    <tr>
                                        <td>
                                            {{ $member->person?->display_name }}
                                            @if ($member->is_chair)
                                                <x-status-badge severity="info" icon="bi-star-fill" label="Vorsitz" class="ms-1" />
                                            @endif
                                        </td>
                                        <td class="small">{{ $member->role }}</td>
                                        <td class="small">{{ format_date($member->started_on) ?: 'nicht erfasst' }}</td>
                                        <td class="small">{{ format_date($member->ended_on) }}</td>
                                        <td>
                                            @if ($member->status === 'active' && $member->ended_on && $member->ended_on->lte($warningDate))
                                                <x-status-badge severity="warning" icon="bi-hourglass-split"
                                                                :label="'Mandat endet am '.format_date($member->ended_on)" />
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">
                                            {{ $asOf ? 'Zum Stichtag war niemand im Amt.' : 'Keine aktiven Mitglieder.' }}
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <x-empty-state icon="bi-person-badge" message="Für die Gesellschaft sind noch keine Organe hinterlegt." />
            </div>
        @endforelse
    </div>
@endsection
