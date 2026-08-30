@extends('layouts.app')

@section('title', 'Erste Schritte')

@section('content')
    <x-page-header title="Erste-Schritte-Assistent" label="Einrichtung">
        <a href="{{ route('help.page', 'erste-schritte') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-journal-text"></i> Anleitung
        </a>
        @if ($status === \App\Http\Controllers\OnboardingController::STATUS_OPEN)
            <form method="POST" action="{{ route('onboarding.skip') }}">
                @csrf
                <button class="btn btn-sm btn-outline-secondary">Assistent überspringen</button>
            </form>
        @else
            <form method="POST" action="{{ route('onboarding.restart') }}">
                @csrf
                <button class="btn btn-sm btn-outline-secondary">Assistent erneut aufnehmen</button>
            </form>
        @endif
    </x-page-header>

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <div class="versal-label">Fortschritt</div>
                    <div class="fw-semibold">{{ $doneCount }} von {{ $totalCount }} Schritten erledigt</div>
                </div>
                <div>
                    @if ($status === \App\Http\Controllers\OnboardingController::STATUS_SKIPPED)
                        <x-status-badge severity="neutral" label="Übersprungen" />
                    @elseif ($status === \App\Http\Controllers\OnboardingController::STATUS_COMPLETED)
                        <x-status-badge severity="success" label="Abgeschlossen" />
                    @else
                        <x-status-badge severity="info" label="In Bearbeitung" />
                    @endif
                </div>
            </div>
            <div class="progress mt-3" role="progressbar" aria-label="Fortschritt der Einrichtung"
                 aria-valuenow="{{ $doneCount }}" aria-valuemin="0" aria-valuemax="{{ $totalCount }}">
                <div class="progress-bar" style="width: {{ $totalCount > 0 ? round($doneCount / $totalCount * 100) : 0 }}%"></div>
            </div>
            <p class="small text-muted mb-0 mt-2">
                Der Erledigungsstand wird aus den vorhandenen Daten ermittelt und bei jedem Aufruf neu geprüft.
                Der Assistent ist jederzeit überspringbar und über Administration, Erste Schritte erneut aufrufbar.
            </p>
        </div>
    </div>

    <div class="card">
        <div class="list-group list-group-flush">
            @foreach ($steps as $index => $step)
                <div class="list-group-item">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge text-bg-light">{{ $index + 1 }}</span>
                                <span class="fw-semibold">{{ $step['title'] }}</span>
                                @if ($step['done'])
                                    <x-status-badge severity="success" label="Erledigt" />
                                @else
                                    <x-status-badge severity="warning" label="Offen" />
                                @endif
                            </div>
                            <div class="small text-muted mt-1">{{ $step['description'] }}</div>
                            <div class="small mt-1"><strong>Stand:</strong> {{ $step['state'] }}</div>
                        </div>
                        @if ($step['url'])
                            <a href="{{ $step['url'] }}" class="btn btn-sm btn-outline-secondary text-nowrap">
                                {{ $step['link_label'] }} <i class="bi bi-arrow-right"></i>
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="alert alert-info small mt-3">
        Ein Datenimport aus Dateien ist nicht implementiert. Die Datensätze werden über die
        Erfassungsmasken der Module angelegt. Details unter
        <a href="{{ route('help.page', 'datenimport') }}">Hilfe, Datenimport aus Dateien</a>.
    </div>
@endsection
