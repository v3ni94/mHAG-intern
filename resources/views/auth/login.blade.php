@extends('layouts.guest')
@section('title', 'Anmeldung')
@section('content')
    @php
        $zentraleAnmeldung = $zentraleAnmeldung ?? false;
        $oertlicheAnmeldung = $oertlicheAnmeldung ?? true;
    @endphp

    <h1 class="h5 mb-3">Anmeldung</h1>

    @if ($zentraleAnmeldung)
        <a href="{{ route('sso.start') }}"
           class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
            <span>Anmelden über das CRM</span>
        </a>
        <p class="text-muted small mt-2 mb-0">
            Die Anmeldung einschließlich zweitem Faktor erfolgt zentral im CRM
            der Müller Holding AG.
        </p>

        @error('email')
            <div class="alert alert-danger mt-3 mb-0" role="alert">{{ $message }}</div>
        @enderror

        @if ($oertlicheAnmeldung)
            <hr class="my-4">
            <details>
                <summary class="small text-muted">Notanmeldung für Administratoren</summary>
                <p class="text-muted small mt-2 mb-0">
                    Nur zu verwenden, wenn das CRM nicht erreichbar ist. Jede
                    Nutzung wird in der Prüfspur festgehalten.
                </p>
                @include('auth.partials.kennwort-formular', ['zweitrangig' => true])
            </details>
        @endif
    @elseif ($oertlicheAnmeldung)
        @include('auth.partials.kennwort-formular', ['zweitrangig' => false])
    @endif

    @unless ($zentraleAnmeldung || $oertlicheAnmeldung)
        <div class="alert alert-secondary mt-4 mb-0" role="alert">
            Es ist kein Anmeldeweg eingerichtet. Bitte wenden Sie sich an die
            Administration.
        </div>
    @endunless
@endsection
