@extends('layouts.guest')
@section('title', 'Interner Fehler')
@section('content')
    <div class="text-center py-3">
        <i class="bi bi-exclamation-octagon" style="font-size: 2.5rem; color: var(--mhag-gold);"></i>
        <div class="versal-label mt-3">Fehler 500</div>
        <h1 class="h5 mt-1">Interner Fehler</h1>
        <p class="text-muted small">Es ist ein unerwarteter Fehler aufgetreten. Der Vorgang wurde protokolliert.</p>
        @if (isset($$exception) && $$exception->getMessage() && app()->hasDebugModeEnabled())
            <p class="small text-danger">{{ $$exception->getMessage() }}</p>
        @endif
        <a href="{{ url('/') }}" class="btn btn-primary btn-sm mt-2">Zur Startseite</a>
    </div>
@endsection
