@extends('layouts.guest')
@section('title', 'Zwei-Faktor-Authentifizierung')
@section('content')
    <h1 class="h5 mb-3"><i class="bi bi-shield-lock me-1"></i>Zwei-Faktor-Authentifizierung</h1>
    <p class="text-muted small">Geben Sie den 6-stelligen Code aus Ihrer Authenticator-App ein.</p>
    <form method="POST" action="{{ route('two-factor.challenge.store') }}">
        @csrf
        <div class="mb-3">
            <label for="code" class="form-label">Bestätigungscode</label>
            <input type="text" name="code" id="code" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                   class="form-control form-control-lg text-center @error('code') is-invalid @enderror"
                   autocomplete="one-time-code" autofocus>
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary w-100">Bestätigen</button>
        <hr>
        <details>
            <summary class="small text-muted">Kein Zugriff auf die App? Recovery-Code verwenden</summary>
            <div class="mt-2">
                <label for="recovery_code" class="form-label">Recovery-Code</label>
                <input type="text" name="recovery_code" id="recovery_code" class="form-control">
                <div class="form-text">Jeder Recovery-Code ist nur einmal verwendbar.</div>
            </div>
        </details>
    </form>
@endsection
