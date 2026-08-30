@extends('layouts.guest')
@section('title', 'Sitzung abgelaufen')
@section('content')
    <div class="text-center py-3">
        <i class="bi bi-hourglass-bottom" style="font-size: 2.5rem; color: var(--mhag-gold);"></i>
        <div class="versal-label mt-3">Fehler 419</div>
        <h1 class="h5 mt-1">Sitzung abgelaufen</h1>
        <p class="text-muted small">Ihre Sitzung ist abgelaufen. Bitte laden Sie die Seite neu und versuchen Sie es erneut.</p>
        @if (isset($$exception) && $$exception->getMessage() && app()->hasDebugModeEnabled())
            <p class="small text-danger">{{ $$exception->getMessage() }}</p>
        @endif
        <a href="{{ url('/') }}" class="btn btn-primary btn-sm mt-2">Zur Startseite</a>
    </div>
@endsection
