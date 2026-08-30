@extends('layouts.guest')
@section('title', 'Zugriff verweigert')
@section('content')
    <div class="text-center py-3">
        <i class="bi bi-shield-lock" style="font-size: 2.5rem; color: var(--mhag-gold);"></i>
        <div class="versal-label mt-3">Fehler 403</div>
        <h1 class="h5 mt-1">Zugriff verweigert</h1>
        <p class="text-muted small">Sie haben keine Berechtigung für diese Seite oder diesen Datensatz.</p>
        @if (isset($$exception) && $$exception->getMessage() && app()->hasDebugModeEnabled())
            <p class="small text-danger">{{ $$exception->getMessage() }}</p>
        @endif
        <a href="{{ url('/') }}" class="btn btn-primary btn-sm mt-2">Zur Startseite</a>
    </div>
@endsection
