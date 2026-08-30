@extends('layouts.guest')
@section('title', 'Seite nicht gefunden')
@section('content')
    <div class="text-center py-3">
        <i class="bi bi-compass" style="font-size: 2.5rem; color: var(--mhag-gold);"></i>
        <div class="versal-label mt-3">Fehler 404</div>
        <h1 class="h5 mt-1">Seite nicht gefunden</h1>
        <p class="text-muted small">Die angeforderte Seite oder der Datensatz existiert nicht oder ist nicht mehr verfügbar.</p>
        @if (isset($$exception) && $$exception->getMessage() && app()->hasDebugModeEnabled())
            <p class="small text-danger">{{ $$exception->getMessage() }}</p>
        @endif
        <a href="{{ url('/') }}" class="btn btn-primary btn-sm mt-2">Zur Startseite</a>
    </div>
@endsection
