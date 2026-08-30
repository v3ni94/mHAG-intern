@extends('layouts.app')

@section('title', 'Hilfe & Anleitung')

@section('content')
    <x-page-header title="Hilfe &amp; Anleitung" label="Unterstützung" />

    <form method="GET" action="{{ route('help.search') }}" class="mb-4" role="search">
        <div class="input-group" style="max-width: 480px;">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="search" name="q" class="form-control" placeholder="Hilfe durchsuchen, z. B. Zinsen nicht bezahlt" aria-label="Hilfe durchsuchen">
            <button class="btn btn-outline-secondary">Suchen</button>
        </div>
    </form>

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header">Anleitungen</div>
                <div class="list-group list-group-flush">
                    @foreach ($pages as $slug => $title)
                        <a href="{{ route('help.page', $slug) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i class="bi bi-journal-text text-secondary"></i> {{ $title }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            @can('admin.settings')
                <div class="card mb-3">
                    <div class="card-header">Erste-Schritte-Assistent</div>
                    <div class="card-body">
                        <p class="small text-muted mb-2">
                            Zehn Schritte für den Aufbau des Datenbestands, mit Erledigungsstand
                            aus den vorhandenen Daten.
                        </p>
                        <a href="{{ route('onboarding.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-flag"></i> Assistent öffnen
                        </a>
                    </div>
                </div>
            @endcan
            <div class="card mb-3">
                <div class="card-header">Fragen und Antworten</div>
                <div class="card-body">
                    <p class="small text-muted mb-2">{{ $faqCount }} Einträge für Ihre Rolle sichtbar.</p>
                    <a href="{{ route('faq.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-question-circle"></i> FAQ öffnen</a>
                </div>
            </div>
            <div class="card">
                <div class="card-header">Was ist neu?</div>
                <div class="card-body">
                    <p class="small text-muted mb-2">Versionshistorie mit neuen Funktionen und Fehlerbehebungen.</p>
                    <a href="{{ route('help.changelog') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-stars"></i> Changelog öffnen</a>
                </div>
            </div>
        </div>
    </div>
@endsection
