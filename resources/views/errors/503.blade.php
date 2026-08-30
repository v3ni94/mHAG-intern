@extends('layouts.guest')
@section('title', 'Wartungsarbeiten')
@section('content')
    <div class="text-center py-3">
        <i class="bi bi-tools" style="font-size: 2.5rem; color: var(--mhag-gold);"></i>
        <div class="versal-label mt-3">Fehler 503</div>
        <h1 class="h5 mt-1">Wartungsarbeiten</h1>
        <p class="text-muted small">Das Intranet wird gerade gewartet und ist in Kürze wieder erreichbar.</p>
        @if (isset($$exception) && $$exception->getMessage() && app()->hasDebugModeEnabled())
            <p class="small text-danger">{{ $$exception->getMessage() }}</p>
        @endif
        <a href="{{ url('/') }}" class="btn btn-primary btn-sm mt-2">Zur Startseite</a>
    </div>
@endsection
